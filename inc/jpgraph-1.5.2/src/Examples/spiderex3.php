<?php
	include ("../jpgraph.php");
	include ("../jpgraph_spider.php");
	
	// Some data to plot
	$data = array(55,80,46,71,95);
	$axtitles=array("Jan","Feb","Mar","Apr","May");

	
	// Create the graph and the plot
	$graph = new SpiderGraph(250,200,"auto");
	$plot = new SpiderPlot($data);

	$plot->SetLegend("Defects");

	// Set position and size	
	$graph->SetCenter(0.5,0.55);
	$graph->SetTitles($axtitles);
	$graph->axis->title->SetColor("navy");
	$graph->axis->title->SetFont(FF_ARIAL,FS_BOLD,10);
	$graph->title->Set("Result 2000");
	$graph->title->SetFont(FF_COURIER,FS_BOLD,11);

	// Add the plot and display the graph
	$graph->Add($plot);
	$graph->Stroke();
?>

	