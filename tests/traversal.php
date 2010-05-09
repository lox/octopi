<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfTraversal extends DatabaseTestCase
{
	public function setUp()
	{
		$this->graph = new Octopi_Graph($this->pdo);
		$this->nodes = array();
	}

	// lookup or create a node
	private function node($nodeId, $data=array())
	{
		if(!isset($this->nodes[$nodeId]))
			$this->nodes[$nodeId] = $this->graph->addNode($data, $nodeId);

		return $this->nodes[$nodeId];
	}

	// build a graph from a set of paths
	private function buildGraph($array)
	{
		foreach($array as $path)
		{
			for($i=0; $i<count($path)-1; $i++)
			{
				$from = $this->node($path[$i]);
				$to = $this->node($path[$i+1]);

				if(!$from->edgesBetween($to))
					$from->addEdge($to);
			}
		}

		return $this->graph;
	}

	public function testIteratorTraversal()
	{
		// 1 -> 2 -> 6

		$graph = $this->buildGraph(array(array(1, 2, 6)));
		$result = $graph->traverse(new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>2,
			'direction'=>Octopi_Edge::OUT,
			)));

		$paths = array();
		foreach($result as $row) $paths[] = $row;

		$this->assertEqual($paths, array(
			array($graph->n1, $graph->n2, $graph->n6),
			));
	}

	public function testOutDirectedTraversal()
	{
		//      + -> 6  + - > 5
		//      |       |
		// 1 -> 2 ----> 3
		//      |
		//      + -> 4

		$graph = $this->buildGraph(array(
			array(1, 2, 6),
			array(1, 2, 3, 5),
			array(1, 2, 4)
			));

		$result = $graph->traverse(new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>array(1, 4),
			'direction'=>Octopi_Edge::OUT,
			)));

		$this->assertEqual($result->count(), 3);
		$this->assertEqual($result->toArray(), array(
			array($graph->n1, $graph->n2, $graph->n6),
			array($graph->n1, $graph->n2, $graph->n3, $graph->n5),
			array($graph->n1, $graph->n2, $graph->n4),
			));

		// nodes should come back in breadth first order
		$this->assertEqual(count($result->nodes()), 6);
		$this->assertEqual($result->nodes(), array(
			$graph->n1, $graph->n2, $graph->n6,
			$graph->n3, $graph->n5, $graph->n4
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>array(1,4),
			'direction'=>Octopi_Edge::IN
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->toArray(), array());
	}

	public function testUndirectedTraversal()
	{
		// 1 -> 2 <- 3
		//      ^
		//      |+-  4

		$graph = $this->buildGraph(array(
			array(1, 2),
			array(3, 2),
			array(4, 2)
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>array(1, 4),
			'direction'=>Octopi_Edge::EITHER
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->count(), 2);

		$this->assertEqual($result->toArray(), array(
			array($graph->n1, $graph->n2, $graph->n3),
			array($graph->n1, $graph->n2, $graph->n4),
			));
	}

	public function testInwardDirectedTraversal()
	{
		// 1 -> 2 -> 3
		//      |
		//      |+-> 4

		$graph = $this->buildGraph(array(
			array(1, 2, 3),
			array(1, 2, 4),
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n4,
			'depth'=>2,
			'direction'=>Octopi_Edge::IN
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->toArray(), array(
			array($graph->n4, $graph->n2, $graph->n1),
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n4,
			'depth'=>array(1,4),
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->toArray(), array());
	}

	public function testFixedDepthTraversal()
	{
		//           + -> 5
		//           |
		// 1 -> 2 -> 3
		//      |
		//      |+-> 4

		$graph = $this->buildGraph(array(
			array(1, 2, 3, 5),
			array(1, 2, 4),
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>3,
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->toArray(), array(
			array($graph->n1, $graph->n2, $graph->n3, $graph->n5),
			));
	}

	public function testTraversalWithAnEndNode()
	{
		//           + -> 5
		//           |
		// 1 -> 2 -> 3
		//      |
		//      |+-> 4

		$graph = $this->buildGraph(array(
			array(1, 2, 3, 5),
			array(1, 2, 4),
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'end'=>$graph->n4,
			'depth'=>2,
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->toArray(), array(
			array($graph->n1, $graph->n2, $graph->n4),
			));
	}
}

