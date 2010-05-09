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

		$alice->index(array('type'=>'person'));
		$bob->index(array('type'=>'person'));

		$this->assertEqual(
			$graph->index()->find(array('type'=>'person')),
			array($alice, $bob));

		$this->assertEqual(
			$graph->index()->find(array('type'=>'person')),
			array($alice, $bob));
	}

	public function testSearchingMultipleIndexes()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));
		$janet = $graph->addNode(array('name'=>'janet'));

		$alice->index(array('type'=>'person','gender'=>'girl'));
		$bob->index(array('type'=>'person','gender'=>'boy'));
		$janet->index(array('type'=>'animal','gender'=>'girl'));

		$this->assertEqual(
			$graph->index()->find(array('type'=>'person', 'gender'=>'girl')),
			array($alice));

		$this->assertEqual(
			$graph->index()->find(array('gender'=>'girl')),
			array($alice, $janet));
	}

	public function testFindOne()
	{
		$graph = new Octopi_Graph($this->pdo);
		$alice = $graph->addNode(array('name'=>'alice'));
		$bob = $graph->addNode(array('name'=>'bob'));

		$alice->index(array('type'=>'person','gender'=>'girl'));
		$bob->index(array('type'=>'person','gender'=>'boy'));

		$this->assertEqual($graph->index()->findOne(array('gender'=>'girl')), $alice);
		$this->assertEqual($graph->index()->findOne(array('gender'=>'boy')), $bob);
	}
}

