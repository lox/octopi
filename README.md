Octopi
==========

Octopi is a graph database built on top of mysql. Nothing complicated, just an adjacency table and then
one left join for each depth of traversal.

Basically an implementation of this Basically an implementation of http://markorodriguez.com/Blarko/Entries/2010/3/29_MySQL_vs._Neo4j_on_a_Large-Scale_Graph_Traversal.html.

For serious projects, Neo4j is recommended, however I wanted something simpler that fit into a LAMP stack.

Example
-------

<pre><code>
$pdo = new PDO('mysql:host=localhost;dbname=graph','graph','graph');
$graph = new Octopi_Graph($pdo);

// add some nodes
$alice = $graph->createNode(array('name'=>'alice'));
$bob = $graph->createNode(array('name'=>'bob'));
$frank = $graph->createNode(array('name'=>'frank'));

// add some edges
$alice->createEdge($bob, 'knows');
$bob->createEdge($frank, 'knows');

// traverse the graph, two deep, outwards
$result = $graph->traverse(new Octopi_Traversal(array(
	'depth'=>2,
	'direction'=>Octopi_Edge::OUT
)));

// get back a list of nodes, and the paths
// $result->toArray() => array( array( $alice, $bob, $frank ) )
</code></pre>

Performance
-----------

Some initial tests indicate that it's definately not in the same league as a dedicated
graph database, but fairly servicable for collections of data where you are dealing with
node graphs less than several million nodes.

For a graph of 1,000,000 nodes with two randomly generated connection to nodes previously created
the following basic stats were captured (MacBook Pro 2008, 2.5Ghz CoreDuo2):

<pre>
querying root node for a depth of 1
found 15 nodes in 0.000800s
peak memory usage: 512Kb

querying root node for a depth of 2
found 129 nodes in 0.003341s
peak memory usage: 1024Kb

querying root node for a depth of 3
found 663 nodes in 0.022859s
peak memory usage: 3072Kb

querying root node for a depth of 4
found 2633 nodes in 0.103763s
peak memory usage: 13312Kb

querying root node for a depth of 5
found 7928 nodes in 0.336301s
peak memory usage: 45312Kb

</pre>

Running the tests
-----------------

<pre><code>
$ ./tests/all.php
all.php
OK
Test cases run: 14/14, Passes: 206, Failures: 0, Exceptions: 0
</code></pre>

