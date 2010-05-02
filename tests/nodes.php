<?php

class TestOfNodes extends DatabaseTestCase
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
}
