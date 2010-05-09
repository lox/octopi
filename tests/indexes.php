<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class TestOfIndexes extends DatabaseTestCase
{
	public function testBasicIndexing()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));

		$alice->index('type', 'person');
		$bob->index('type', 'person');

		$this->assertEqual($graph->index()->query('type', 'person'),
			array($alice, $bob));

		$this->assertEqual($graph->index()->query('type', 'person'),
			array($alice, $bob));
	}
}

