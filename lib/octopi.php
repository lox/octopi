<?php

class Octopi_Graph
{
	private $_db, $_index;

	public function __construct($pdo)
	{
		$this->_db = new Octopi_Db($pdo);
		$this->_index = new Octopi_Index($this->db);
	}

	public function node($id)
	{
		return new Octopi_Node($this->_db, $id);
	}

	public function createNode($data)
	{
		$result = $this->_db
			->execute("INSERT INTO node VALUES (NULL, ?)", json_encode($data));
		return $this->node($this->_db->lastInsertId());
	}

	/*
	public function addNodeToIndex($nodeId, $key, $value)
	{
		if(!$this->_indexExists($key)) $this->_createIndex($key);

		$table = $this->_indexTable($key);
		$insert = $this->_pdo->prepare("INSERT INTO `$table` VALUES (?, ?)");
		$insert->execute(array($nodeId, $value));
		return $this;
	}

	public function queryIndex($key, $value)
	{
		if(!$this->_indexExists($key)) return array();

		$table = $this->_indexTable($key);
		$select = $this->_pdo->prepare("SELECT nodeid FROM `$table` WHERE value=?");
		$select->execute(array($value));
		return $select->fetchAll(PDO::FETCH_COLUMN);
	}
	*/
}

class Octopi_Db
{
	public $pdo;

	public function __construct($pdo)
	{
		$this->pdo = $pdo;
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	public function execute($sql /*, $params */)
	{
		$params = array_slice(func_get_args(), 1);
		$statement = $this->pdo->prepare($sql);
		$statement->execute($params);
		return $statement;
	}

	public function __call($method, $params)
	{
		return call_user_func_array(array($this->pdo, $method), $params);
	}
}

class Octopi_SqlBuilder
{
	private $_db, $_fragments;

	public function __construct($db)
	{
		$this->_db = $db;
		$this->_fragments = new stdClass();
		$this->_fragments->where = array();
	}

	public function select($fields)
	{
		$this->_fragments->select = $fields;
		return $this;
	}

	public function from($table)
	{
		$this->_fragments->from = $table;
		return $this;
	}

	public function andWhere($sql, $params=array())
	{
		$this->_fragments->where[] = 'AND ('.$this->bind($sql, $params).')';
		return $this;
	}

	public function orWhere($sql, $params=array())
	{
		$this->_fragments->where[] = 'AND ('.$this->bind($sql, $params).')';
		return $this;
	}

	public function bind($sql, $params)
	{
		$result = '';
		$counter = 0;

		// TODO: support strings
		foreach(str_split($sql,1) as $c)
			$result .= $c == '?' ? $this->_db->quote($params[$counter++]) : $c;

		return $result;
	}

	public function build()
	{
		return sprintf(
			'SELECT %s FROM %s WHERE %s',
			$this->_fragments->select,
			$this->_fragments->from,
			ltrim(implode(' ', $this->_fragments->where), 'ANDOR ')
			);
	}

	public function execute()
	{
		return $this->_db->execute($this->build());
	}
}

class Octopi_Node
{
	private $_db;
	public $id;

	public function __construct($db, $id)
	{
		$this->_db = $db;
		$this->id = (int) $id;
	}

	public function __get($key)
	{
		switch($key)
		{
			case 'data': return $this->data();
		}
	}

	public function data()
	{
		return json_decode($this->_db
			->execute("SELECT json FROM node WHERE nodeid=?", $this->id)
			->fetchObject()
			->json
			);
	}

	public function createEdge($node, $type, $data=array())
	{
		$this->_db->execute("INSERT INTO edge VALUES (NULL, ?, ?, ?)",
			$this->id, $node->id, $type);

		$edge = new Octopi_Edge(
				$this->_db,
				$this->_db->lastInsertId(),
				$this->id,
				$node->id,
				$type
				);

		if($data)
		{
			$this->_db->execute(
				"INSERT INTO edgemeta VALUES (?, ?)", $edge->id, json_encode($data));
		}

		return $edge;
	}

	public function edges($dir=Octopi_Edge::EITHER, $type=null)
	{
		$edges = array();
		$builder = new Octopi_SqlBuilder($this->_db);
		$builder->select('*')->from('edge');

		// add conditional edges
		if($dir & Octopi_Edge::OUT) $builder->andWhere('outid=?', array($this->id));
		if($dir & Octopi_Edge::IN) $builder->andWhere('inid=?', array($this->id));

		$edges = array();
		foreach($builder->execute()->fetchAll(PDO::FETCH_OBJ) as $obj)
			$edges[] = Octopi_Edge::fromRow($this->_db, $obj);

		return $edges;
	}
}

class Octopi_Edge
{
	const OUT=1;
	const IN=2;
	const EITHER=3;

	private $_db, $_out, $_in;
	public $id;
	public $type;

	public function __construct($db, $id, $out, $in, $type)
	{
		$this->_db = $db;
		$this->_out = (int) $out;
		$this->_in = (int) $in;
		$this->id = (int) $id;
		$this->type = $type;
	}

	public function __get($key)
	{
		switch($key)
		{
			case 'data': return $this->data();
			case 'out': return new Octopi_Node($this->_db, $this->_out);
			case 'in': return new Octopi_Node($this->_db, $this->_in);
		}
	}

	public function data()
	{
		return json_decode($this->_db
			->execute("SELECT json FROM edgemeta WHERE edgeid=?", $this->id)
			->fetchObject()
			->json
			);
	}

	public static function fromRow($db, $row)
	{
		return new Octopi_Edge($db,
			$row->edgeid, $row->outid, $row->inid, $row->type
			);
	}
}

class Octopi_Index
{
	private $_pdo;
	private $_indexes;

	public function __construct($pdo)
	{
		$this->_pdo = $pdo;
	}

	public function truncate()
	{
		$this->_pdo->exec("TRUNCATE node");
		$this->_pdo->exec("TRUNCATE edge");
		$this->_pdo->exec("TRUNCATE edgemeta");

		foreach($this->_allIndexes() as $index)
		{
			$this->_pdo->exec("TRUNCATE $index");
		}

		return $this;
	}

	public function indexExists($key)
	{
		return in_array($this->_indexTable($key), $this->_allIndexes());
	}

	public function createIndex($key)
	{
		$table = $this->_indexTable($key);
		$this->_pdo->exec("CREATE TABLE $table (
			`nodeid` INT NOT NULL,
			`value` VARCHAR(255) NOT NULL,
			INDEX (`value`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$this->_indexes[] = $table;
	}

	public function allIndexes()
	{
		if(!isset($this->_indexes))
		{
			foreach($this->_pdo->query("SHOW TABLES LIKE 'index_%'") as $row)
			{
				$this->_indexes[] = $row[0];
			}
		}

		return $this->_indexes;
	}

	private function _indexTable($key)
	{
		if(!preg_match('/^[a-z0-9]+$/i',$key))
			throw new Exception("Keys can only contain alphanumberic characters");

		return "index_$key";
	}
}
