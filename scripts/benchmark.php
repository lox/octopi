<?php

define('NODE_COUNT',1000000);

require_once(dirname(__FILE__).'/../lib/octopi.php');

$pdo = new PDO('mysql:host=localhost;dbname=graphtest',
	'graph',
	'graph',
	array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
);

$graph = new Octopi_Graph($pdo);
$starttime = microtime(true);

if(in_array('--generate', $argv))
{
	$graph->truncate();

	printf("generating %s nodes\n", number_format(NODE_COUNT));
	$time = microtime(true);

	for($i=1; $i<=NODE_COUNT; $i++)
	{
		$elapsed = microtime(true)-$starttime;
		$rate = $i / $elapsed;
		$estimate = (NODE_COUNT - $i) / $rate;

		if($i % 1000 == 0)
			printf("generating node %s (%d node/sec, %d seconds left)\n",
				$i, $rate, $estimate);

		$node = $graph->addNode(array(), $i);

		// pick four random nodes
		$p1 = $graph->node(rand(1,$i-1));
		$p2 = $graph->node(rand(1,$i-1));

		if($i>1)
		{
			// use the rich-get-richer method to build a natural graph
			$node->addEdge($p1->degree() > $p2->degree() ? $p1 : $p2);
		}
	}

	printf("time elapsed: %fs\n", microtime(true)-$time);
}

for($i=0; $i<6; $i++)
{
	printf("querying root node for a depth of $i\n");
	$time = microtime(true);

	$traversal = new Octopi_Traversal(array(
		'start'=>$graph->n1,
		'depth'=>$i,
		'direction'=>Octopi_Edge::EITHER
		));

	$result = $graph->traverse($traversal);
	printf("found %d paths in %fs\n", $result->count(), microtime(true)-$time);
	printf("peak memory usage: %dKb\n", memory_get_peak_usage(true) / 1024);
}
