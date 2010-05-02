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
			$this->nodes[$nodeId] = $this->graph->createNode($data, $nodeId);

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
					$from->createEdge($to);
			}
		}

		return $this->graph;
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

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>4,
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->count(), 6);

		$this->assertEqual($result->paths(), array(
			array($graph->n1, $graph->n2, $graph->n6),
			array($graph->n1, $graph->n2, $graph->n3, $graph->n5),
			array($graph->n1, $graph->n2, $graph->n4),
			));

		// nodes should come back in breadth first order
		$this->assertEqual($result->nodes(), array(
			1=>$graph->n1, 2=>$graph->n2, 6=>$graph->n6,
			3=>$graph->n3, 4=>$graph->n4, 5=>$graph->n5
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n1,
			'depth'=>4,
			'direction'=>Octopi_Edge::IN
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->paths(), array());
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
			'depth'=>4,
			'direction'=>Octopi_Edge::EITHER
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->count(), 4);

		$this->assertEqual($result->paths(), array(
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
			'depth'=>4,
			'direction'=>Octopi_Edge::IN
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->paths(), array(
			array($graph->n4, $graph->n2, $graph->n1),
			));

		$traversal = new Octopi_Traversal(array(
			'start'=>$graph->n4,
			'depth'=>4,
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->paths(), array());
	}
}

