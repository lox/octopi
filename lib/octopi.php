<?php

/**
 * Octopi - A graph database
 * @author Lachlan Donald <lachlan@ljd.cc>
 */

/**
 * A graph of nodes connected by edges
 */
class Octopi_Graph
{
	private $_db;

	public function __construct($pdo)
	{
		$this->_db = new Octopi_Db($pdo);
	}

	/**
	 * @return Octopi_Node
	 */
	public function node($id)
	{
		return new Octopi_Node($this->_db, $id);
	}

	/**
	 * @return Octopi_Node
	 */
	public function createNode($data=array(), $id=null)
	{
		$json = empty($data) ? "{}" : json_encode($data);
		$result = $this->_db->execute("INSERT INTO node VALUES (?, ?)",$id, $json);
		return $this->node($this->_db->lastInsertId());
	}

	/**
	 * @return Octopi_TraversalResult
	 */
	public function traverse($traversal)
	{
		$builder = new Octopi_SqlBuilder($this->_db);
		$select = array($traversal->start->id);

		// choose the lhs and rhs based on traversal type
		if($traversal->direction == Octopi_Edge::EITHER ||
			$traversal->direction == Octopi_Edge::OUT)
		{
			$lhs = "inid";
			$rhs = "outid";
		}
		else
		{
			$lhs = "outid";
			$rhs = "inid";
		}

		// build the depth joins on the adjacency list
		for($i=1; $i<=$traversal->depth; $i++)
		{
			// uni-directional traversals don't use inferred edges
			$inferred = $traversal->direction != Octopi_Edge::EITHER ?
				sprintf("and e%d.inferred=0", $i+1) : '';
			$previous = array($traversal->start->id);

			foreach(range(1,$i) as $int)
				$previous[] = sprintf("e%d.%s", $int, $lhs);

			$select[] = sprintf("e%d.%s n%d", $i, $lhs, $i);
			$builder->leftJoin(sprintf(
				"edge e%d ON e%d.%s=e%d.%s and e%d.%s not in (%s) %s",
				$i+1, $i, $lhs, $i+1, $rhs, $i+1, $lhs, implode(',',$previous), $inferred
				));
		}

		// construct the sql
		$builder
			->select(implode(', ', $select))
			->from('edge e1')
			->andWhere("e1.$rhs=?", array($traversal->start->id))
			;

		if($traversal->direction != Octopi_Edge::EITHER)
			$builder->andWhere("e1.inferred=0");

		return new Octopi_TraversalResult($this->_db, $builder->execute());
	}

	/**
	 * @return Octopi_Index
	 */
	public function index()
	{
		return new Octopi_Index($this->_db);
	}

	/**
	 * @chainable
	 */
	public function truncate()
	{
		$this->_pdo->exec("TRUNCATE node");
		$this->_pdo->exec("TRUNCATE edge");
		$this->_pdo->exec("TRUNCATE edgemeta");

		$index = new Octopi_Index($this->_db);
		foreach($index->allIndexes() as $index)
		{
			$this->_pdo->exec("TRUNCATE $index->table");
		}

		return $this;
	}

	public function __get($key)
	{
		if(preg_match('/^n(\d+)$/i', $key, $m))
		{
			return $this->node($m[1]);
		}
		else
		{
			throw new Exception("No property called $key");
		}
	}
}

/**
 * PDO wrapper, basic helpers
 */
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

/**
 * Helper to construct sql
 */
class Octopi_SqlBuilder
{
	private $_db, $_fragments;

	public function __construct($db)
	{
		$this->_db = $db;
		$this->_fragments = new stdClass();
		$this->_fragments->where = array();
		$this->_fragments->joins = array();
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

	public function where($sql, $params=array())
	{
		$this->_fragments->where = array($this->bind($sql, $params));
		return $this;
	}

	public function andWhere($sql, $params=array())
	{
		$this->_fragments->where[] = 'AND ('.$this->bind($sql, $params).')';
		return $this;
	}

	public function orWhere($sql, $params=array())
	{
		$this->_fragments->where[] = 'OR ('.$this->bind($sql, $params).')';
		return $this;
	}

	public function innerJoin($sql, $params=array())
	{
		$this->_fragments->joins[] = 'INNER JOIN '.$this->bind($sql, $params);
		return $this;
	}

	public function leftJoin($sql, $params=array())
	{
		$this->_fragments->joins[] = 'LEFT JOIN '.$this->bind($sql, $params);
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
			"SELECT %s\nFROM %s\n%sWHERE %s",
			$this->_fragments->select,
			$this->_fragments->from,
			$this->_fragments->joins ? implode("\n", $this->_fragments->joins)."\n" : "",
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

	public function createEdge($node, $type=null, $data=array())
	{
		// create an inferred edge in the reverse to allow bi-directional search
		$this->_db->execute("INSERT INTO edge VALUES (NULL, ?, ?, ?, 1)",
			$node->id, $this->id, $type);

		$this->_db->execute("INSERT INTO edge VALUES (NULL, ?, ?, ?, 0)",
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
		$builder->select('*')->from('edge')->where('inferred=0');

		// add conditional edges
		if($dir == Octopi_Edge::OUT)
			$builder->andWhere('outid=?', array($this->id));
		else if($dir == Octopi_Edge::IN)
			$builder->andWhere('inid=?', array($this->id));
		else
			$builder->andWhere('outid=? or inid=?', array($this->id, $this->id));

		$edges = array();
		foreach($builder->execute()->fetchAll(PDO::FETCH_OBJ) as $obj)
			$edges[] = Octopi_Edge::fromRow($this->_db, $obj);

		return $edges;
	}

	public function edgesBetween($node)
	{
		$edges = array();

		foreach($this->edges() as $edge)
		{
			if($edge->out == $node || $edge->in == $node)
				$edges[] = $edge;
		}

		return $edges;
	}

	/**
	 * @chainable
	 */
	public function index($key, $value)
	{
		$index = new Octopi_Index($this->_db);
		$index->addNode($this, $key, $value);
		return $this;
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

class Octopi_Traversal
{
	public $start;
	public $direction=Octopi_Edge::OUT;
	public $depth=1;

	public function __construct($params=array())
	{
		// TODO: validate settable properties
		foreach($params as $key=>$value)
		{
			$this->$key = $value;
		}
	}
}

class Octopi_TraversalResult
{
	private $_paths = array();
	private $_nodes = array();

	public function __construct($db, $result)
	{
		while($row = $result->fetch(PDO::FETCH_OBJ))
		{
			$path = array();

			foreach(array_filter(array_values((array) $row)) as $id)
			{
				$node = new Octopi_Node($db, $id);
				$path[] = $node;
				$this->_nodes[$id] = $node;
			}

			$this->_paths[] = $path;
		}
	}

	public function count()
	{
		return count($this->_nodes);
	}

	public function nodes()
	{
		return $this->_nodes;
	}

	public function paths()
	{
		return $this->_paths;
	}
}

class Octopi_Index
{
	private $_db;
	private $_indexes=array();

	public function __construct($db)
	{
		$this->_db = $db;
	}

	/**
	 * @chainable
	 */
	public function addNode($node, $key, $value)
	{
		if(!$this->exists($key)) $this->create($key);

		$table = $this->_indexTable($key);
		$insert = $this->_db->execute("INSERT INTO `{$table}` VALUES (?, ?)",
			$node->id, $value);

		return $this;
	}

	/**
	 * @return array
	 */
	public function query($key, $value)
	{
		if(!$this->exists($key)) return array();

		$table = $this->_indexTable($key);
		$nodes = array();
		$select = $this->_db->execute("SELECT nodeid FROM `$table` WHERE value=?",$value);

		foreach($select->fetchAll(PDO::FETCH_COLUMN) as $id)
			$nodes[] = new Octopi_Node($this->_db, $id);

		return $nodes;
	}

	/**
	 * @return bool
	 */
	public function exists($key)
	{
		return in_array($this->_indexTable($key), $this->allIndexes());
	}

	/**
	 * @chainable
	 */
	public function create($key)
	{
		$table = $this->_indexTable($key);
		$this->_db->execute("CREATE TABLE $table (
			`nodeid` INT NOT NULL,
			`value` VARCHAR(255) NOT NULL,
			INDEX (`value`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		$this->_indexes[] = $table;
		return $this;
	}

	/**
	 * @return array
	 */
	public function allIndexes()
	{
		if(empty($this->_indexes))
		{
			foreach($this->_db->execute("SHOW TABLES LIKE 'index_%'") as $row)
			{
				$this->_indexes[] = $row[0];
			}
		}

		return $this->_indexes;
	}

	/**
	 * @return string
	 */
	private function _indexTable($key)
	{
		if(!preg_match('/^[a-z0-9]+$/i',$key))
			throw new Exception("Keys can only contain alphanumberic characters");

		return "index_$key";
	}
}
