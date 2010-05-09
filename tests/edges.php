<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfEdges extends DatabaseTestCase
{
	public function testEdgeCreation()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));
		$edge = $alice->addEdge($bob, 'knows', array('test'=>'data'));

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
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));
		$edge = $bob->addEdge($alice, 'knows', array('test'=>'data'));

		// check the edge between method works
		$edges = $alice->edgesBetween($bob);
		$this->assertEqual(count($edges), 1);
		$this->assertEqual($edges[0]->in, $alice);
		$this->assertEqual($edges[0]->out, $bob);
	}

	public function testDegree()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));
		$bob->addEdge($alice, 'knows');
		$alice->addEdge($bob, 'likes');

		$this->assertEqual($alice->degree(), 2);
		$this->assertEqual($alice->degree(Octopi_Edge::EITHER), 2);
		$this->assertEqual($alice->degree(Octopi_Edge::OUT), 1);
		$this->assertEqual($alice->degree(Octopi_Edge::IN), 1);

		$this->assertEqual($bob->degree(), 2);
		$this->assertEqual($bob->degree(Octopi_Edge::EITHER), 2);
		$this->assertEqual($bob->degree(Octopi_Edge::OUT), 1);
		$this->assertEqual($bob->degree(Octopi_Edge::IN), 1);

		$this->assertEqual($alice->degree(Octopi_Edge::OUT, 'likes'), 1);
		$this->assertEqual($alice->degree(Octopi_Edge::OUT, 'hates'), 0);
	}
}

