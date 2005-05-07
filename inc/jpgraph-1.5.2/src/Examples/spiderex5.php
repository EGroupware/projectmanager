<?php
	include ("../jpgraph.php");
	include ("../jpgraph_spider.php");
	
	// Some data to plot
	$data = array(55,80,46,21,95);
	$axtitles=array("Jan","Feb","Mar","Apr","May");

	// Create the graph and the plot
	$graph = new SpiderGraph(250,200,"auto");
	$plot = new SpiderPlot($data);
	$plot->SetLegend("Defects");

	// Set position and size	
	$graph->SetCenter(0.5,0.55);
	$graph->SetTitles($axtitles);
	$graph->title->Set("Result 2001");
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->axis->title->SetFont(FF_ARIAL,FS_BOLD);
	$graph->SupressTickMarks();
	$graph->SetShadow();
	$graph->SetColor(array(200,230,230));
	
	$graph->grid->SetLineStyle("dashed");
	$graph->grid->SetColor("darkred");
	$graph->grid->Show();


	// Add the plot and display the graph
	$graph->Add($plot);
	$graph->Stroke();
?>

	