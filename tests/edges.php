<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfEdges extends DatabaseTestCase
{
	public function testEdgeCreation()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->createNode(array('name'=>'alice'));
		$bob = $graph->createNode(array('name'=>'bob'));
		$edge = $alice->createEdge($bob, 'knows', array('test'=>'data'));

		// check the returned edge is correct
		$this->assertEqual($edge->out, $alice);
		$this->assertEqual($edge->in, $bob);
		$this->assertEqual($edge->type, 'knows');
		$this->assertEqual($edge->data->test, 'data');

		// check the edge query works
		$this->assertEqual($alice->edges(Octopi_Edge::OUT, 'knows'), array($edge));
		$this->assertEqual($alice->edges(Octopi_Edge::IN, 'knows'), array());
		$this->assertEqual($bob->edges(Octopi_Edge::IN, 'knows'), array($edge));
	}

	public function testEdgeBetween()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->createNode(array('name'=>'alice'));
		$bob = $graph->createNode(array('name'=>'bob'));
		$edge = $bob->createEdge($alice, 'knows', array('test'=>'data'));

		// check the edge between method works
		$edges = $alice->edgesBetween($bob);
		$this->assertEqual(count($edges), 1);
		$this->assertEqual($edges[0]->in, $alice);
		$this->assertEqual($edges[0]->out, $bob);
	}
}

