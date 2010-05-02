<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class AllTests extends TestSuite
{
	function __construct()
	{
		parent::__construct('All tests');
		$this->addFile('nodes.php');
		$this->addFile('edges.php');
		$this->addFile('traversal.php');
	}
}

