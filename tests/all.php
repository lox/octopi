<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfGraph extends DatabaseTestCase
{
	public function testNodeCreation()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->createNode(array('name'=>'alice'));
		$bob = $graph->createNode(array('name'=>'bob'));

		$this->assertEqual($alice->id, 1);
		$this->assertEqual($bob->id, 2);
		$this->assertEqual($alice->data->name, 'alice');
		$this->assertEqual($bob->data->name, 'bob');
	}

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
}

