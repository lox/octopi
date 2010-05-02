<?php


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
}

