<?php
	include ("../jpgraph.php");
	include ("../jpgraph_spider.php");
	
	// Some data to plot
	$data = array(55,80,46,71,95);
	
	// Create the graph and the plot
	$graph = new SpiderGraph(250,200,"auto");
	$plot = new SpiderPlot($data);

	// Add the plot and display the graph
	$graph->Add($plot);
	$graph->Stroke();
?>