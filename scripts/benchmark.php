<?php

require_once(dirname(__FILE__).'/../lib/octopi.php');

$pdo = new PDO('mysql:host=localhost;dbname=graphtest',
	'graph',
	'graph',
	array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
);

$graph = new Octopi_Graph($pdo);
$graph->truncate();

printf("generating 1000000 nodes\n");
$time = microtime(true);

for($i=1; $i<=1000000; $i++)
{
	if($i % 100 == 0) printf("generating node %s\n", $i);

	$node = $graph->createNode(array(), $i);

	if($i>1)
	{
		$graph->node(rand(1,$i-1))->createEdge($node);
	}
}

printf("time elapsed: %fs\n", microtime(true)-$time);

for($i=0; $i<6; $i++)
{
	printf("querying root node for a depth of $i\n");
	$time = microtime(true);

	$traversal = new Octopi_Traversal(array(
		'start'=>$graph->n1,
		'depth'=>$i,
		'direction'=>Octopi_Edge::OUT
		));

	$result = $graph->traverse($traversal);
	printf("found %d nodes in %fs\n", $result->count(), microtime(true)-$time);
	printf("peak memory usage: %dKb\n", memory_get_peak_usage(true) / 1024);
}
