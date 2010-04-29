<?php

class DatabaseTestCase extends UnitTestCase
{
	public function before($method)
	{
		$this->pdo = new PDO('mysql:host=localhost;dbname=graphtest',
			'graph',
			'graph',
			array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
		);

		$this->loadSchema();
		parent::before($method);
	}

	public function loadSchema()
	{
		$statements = array_filter(array_map('trim',preg_split('/;/',
			file_get_contents(dirname(__FILE__).'/../db/schema.sql')
			)));

		foreach($statements as $statement)
		{
			$this->pdo->exec($statement);
		}
	}
}
