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
	public function addNode($data=array(), $id=null)
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

		$builder = new Octopi_SqlBuilder($this->_db);
		$select = array($traversal->start->id, "e1.$lhs n1");

		// define a depth for traversal
		$depth = $traversal->depth;
		$min = is_array($depth) ? $depth[0] : $depth;
		$max = is_array($depth) && isset($depth[1]) ? $depth[1] : $min;

		// build the depth joins on the adjacency list
		for($i=1; $i<$max; $i++)
		{
			// uni-directional traversals don't use inferred edges
			$inferred = $traversal->direction != Octopi_Edge::EITHER ?
				sprintf("and e%d.inferred=0", $i+1) : '';

			// exclude nodes already in the path to prevent cycles
			$exclude = array($traversal->start->id);
			foreach(range(1,$i) as $int)
				$exclude[] = sprintf("e%d.%s", $int, $lhs);

			$select[] = sprintf("e%d.%s n%d", $i+1, $lhs, $i+1);
			$join = $i > $min ? 'leftJoin' : 'innerJoin';

			// add either a left or inner join to traverse a node
			$builder->$join(sprintf(
				"edge e%d ON e%d.%s=e%d.%s and e%d.%s not in (%s) %s",
				$i+1, $i, $lhs, $i+1, $rhs, $i+1, $lhs, implode(',',$exclude), $inferred
				));
		}

		// optionally include an end node as a stopping point
		if(isset($traversal->end))
		{
			$coalesce = array();

			foreach(range($max,1) as $int)
				$coalesce[] = sprintf("e%d.%s", $int, $lhs);

			$builder->andWhere(sprintf("COALESCE(%s) = %d",
				implode(',', $coalesce), $traversal->end->id));
		}

		// construct the sql
		$builder
			->select(implode(', ', $select))
			->from('edge e1')
			->andWhere("e1.$rhs=?", array($traversal->start->id))
			;

		if($traversal->direction != Octopi_Edge::EITHER)
			$builder->andWhere("e1.inferred=0");

		return new Octopi_TraversalResult($builder->execute(), $this->_db);
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
		$this->_db->exec("TRUNCATE node");
		$this->_db->exec("TRUNCATE edge");
		$this->_db->exec("TRUNCATE edgemeta");

		$index = new Octopi_Index($this->_db);
		foreach($index->allIndexes() as $index)
		{
			$this->_db->exec("TRUNCATE $index");
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

	public function addEdge($node, $type=null, $data=array())
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
	public function index($params)
	{
		$index = new Octopi_Index($this->_db);

		foreach($params as $key=>$value)
			$index->addNode($this, $key, $value);

		return $this;
	}

	/**
	 *
	 */
	public function degree($direction=Octopi_Edge::EITHER, $type=null)
	{
		$builder = new Octopi_SqlBuilder($this->_db);
		$builder
			->select('count(*) count')
			->from('edge')
			->where('inferred=0')
			;

		// build the edge selector
		if($direction == Octopi_Edge::EITHER)
		{
			$builder->andWhere('outid=? or inid=?',array($this->id, $this->id));
		}
		else if($direction == Octopi_Edge::IN)
		{
			$builder->andWhere('inid=?',array($this->id));
		}
		else if($direction == Octopi_Edge::OUT)
		{
			$builder->andWhere('outid=?',array($this->id));
		}

		// add an optional type selector
		if($type)
			$builder->andWhere('type=?',array($type));

		$result = $builder->execute()->fetchObject();
		return $result->count;
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

/**
 * Defines a traversal, the following attributes are supported:
 *
 * start (required) => the starting node
 * end (optionnal) => an optional end point which all paths must have
 * depth (default 1) => the number of traversals to perform
 * direction (default OUT) => what direction to traverse edges
 */
class Octopi_Traversal
{
	public $start;
	public $direction=Octopi_Edge::OUT;
	public $depth=1;
	public $end;

	public function __construct($params=array())
	{
		// TODO: validate settable properties
		foreach($params as $key=>$value)
		{
			$this->$key = $value;
		}
	}
}

class Octopi_TraversalResult implements Iterator
{
	private $_db, $_stmt, $_rows, $_cursor=-1, $_valid=true;

	public function __construct(PDOStatement $stmt, $db)
	{
		$this->_stmt = $stmt;
		$this->_db = $db;
	}

	public function toArray()
	{
		$paths = array();

		foreach($this as $row)
			$paths[] = $row;

		return $paths;
	}

	public function nodes()
	{
		$nodes = array();
		foreach($this as $path)
			foreach($path as $node)
				$nodes[$node->id] = $node;

		return array_values($nodes);
	}

	public function count()
	{
		return $this->_stmt->rowCount();
	}

	private function _hydrateCurrent()
	{
		$nodes = array();
		foreach(array_filter($this->_rows[$this->_cursor]) as $nodeId)
			$nodes[] = new Octopi_Node($this->_db, $nodeId);

		return $nodes;
	}

	// ------------------------------
	// iterator interface

	public function current()
	{
		if(isset($this->_rows[$this->_cursor]))
			return $this->_hydrateCurrent();
	}

	public function key()
	{
		return $this->_cursor;
	}

	public function next()
	{
		$this->_cursor++;

		if(empty($this->_rows[$this->_cursor]) && $row = $this->_stmt->fetch(PDO::FETCH_NUM))
			$this->_rows[$this->_cursor] = $row;

		$this->_valid = isset($this->_rows[$this->_cursor]);
	}

	public function valid()
	{
		return $this->_valid;
	}

	public function rewind()
	{
		$this->_cursor = 0;
		$this->_valid = true;
		$this->next();
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
	 * Find nodes that match all of the provided key/values.
	 * @return array
	 */
	public function find($params)
	{
		$keys = array_unique(array_keys($params));
		$tablemap = array();

		// fail fast if we can
		foreach($keys as $key)
			if(!$this->exists($key)) return array();

		// build a list of unique tables
		foreach($keys as $key)
			$tablemap[$key] = $this->_indexTable($key);

		$tables = array_values($tablemap);
		$innerJoins = array();

		$builder = new Octopi_SqlBuilder($this->_db);
		$builder->select('*')->from($tables[0]);

		foreach(array_slice($tables,1) as $table)
			$innerJoins[] = sprintf("$table USING(nodeid)");

		foreach($params as $key=>$value)
			$builder->andWhere(sprintf("%s.value=?",$tablemap[$key]), array($value));

		if($innerJoins)
			$builder->innerJoin(implode("\n",$innerJoins));

		$result = $builder->execute();
		$nodes = array();

		foreach($result->fetchAll(PDO::FETCH_COLUMN) as $id)
			$nodes[] = new Octopi_Node($this->_db, $id);

		return $nodes;
	}

	/**
	 * Like {@link find()} but only returns one result, or an exception
	 */
	public function findOne($params)
	{
		$results = $this->find($params);

		if(count($results) == 0 || count($results) > 1)
			throw new Exception("Found a node count other than one");

		return $results[0];
	}

	/**
	 * @return bool
	 */
	public function exists($key)
	{
		return in_array($key, $this->allIndexes());
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
				$this->_indexes[] = substr($row[0],6);
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
