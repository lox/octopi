<?php

class TestOfTraversal extends DatabaseTestCase
{
	public function testTraversal()
	{
		//      + -> 6  + - > 5
		//      |       |
		// 1 -> 2 ----> 3
		//      |
		//      + -> 4

		$graph = new Octopi_Graph($this->pdo);

		$n1 = $graph->createNode();
		$n2 = $graph->createNode();
		$n3 = $graph->createNode();
		$n4 = $graph->createNode();
		$n5 = $graph->createNode();
		$n6 = $graph->createNode();

		$n1->createEdge($n2);
		$n2->createEdge($n6);
		$n2->createEdge($n3);
		$n2->createEdge($n4);
		$n3->createEdge($n5);

		$traversal = new Octopi_Traversal(array(
			'start'=>$n1,
			'depth'=>4,
			'direction'=>Octopi_Edge::OUT
			));

		$result = $graph->traverse($traversal);
		$this->assertEqual($result->count(), 6);

		// nodes should come back in breadth first order
		$this->assertEqual($result->nodes(), array(
			1=>$n1, 2=>$n2, 6=>$n6, 3=>$n3, 4=>$n4, 5=>$n5
			));
	}
}

