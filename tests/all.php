<?php

require_once(dirname(__FILE__).'/simpletest/autorun.php');
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/../lib/octopi.php');

class AllTests extends TestSuite
{
	function __construct()
	{
		parent::__construct('All tests');
		$this->addFile(dirname(__FILE__).'/nodes.php');
		$this->addFile(dirname(__FILE__).'/edges.php');
		$this->addFile(dirname(__FILE__).'/traversal.php');
		$this->addFile(dirname(__FILE__).'/indexes.php');
	}
}

