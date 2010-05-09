<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfNodes extends DatabaseTestCase
{
	public function testNodeCreation()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));

		$this->assertEqual($alice->id, 1);
		$this->assertEqual($bob->id, 2);
		$this->assertEqual($alice->data->name, 'alice');
		$this->assertEqual($bob->data->name, 'bob');
	}
}

