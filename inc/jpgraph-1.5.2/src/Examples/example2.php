<?php
include ("../jpgraph.php");
include ("../jpgraph_line.php");


$ydata = array(11,6,8,12,5,6,9,13,6,7);

// Create the graph. These two calls are always required
$graph = new Graph(300,200,"auto");	
$graph->SetScale("textlin");
$graph->yscale->SetAutoMin(0);

// Create the linear plot
$lineplot=new LinePlot($ydata);

// Add the plot to the graph
$graph->Add($lineplot);

$graph->img->SetMargin(40,20,20,40);
$graph->title->Set("Example 2");
$graph->xaxis->title->Set("X-title");
$graph->yaxis->title->Set("Y-title");


// Display the graph
$graph->Stroke();
?>
