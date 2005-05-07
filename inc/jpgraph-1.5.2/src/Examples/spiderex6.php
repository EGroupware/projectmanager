<?php
	include ("../jpgraph.php");
	include ("../jpgraph_spider.php");
	
	// Some data to plot
	$data = array(55,80,46,21,95);
	$data2 = array(65,95,50,75,60);
	$axtitles=array("Jan","Feb","Mar","Apr","May");

	// Create the graph and the plot
	$graph = new SpiderGraph(250,200,"auto");
	$graph->img->SetAntiAliasing("white");
	
	$plot = new SpiderPlot($data);
	$plot->SetLegend("Defects");

	$plot2 = new SpiderPlot($data2);
	$plot2->SetFill(false);
	$plot2->SetLineWeight(2);
	$plot2->SetColor("red");
	$plot2->SetLegend("Target");

	// Set position and size	
	$graph->SetCenter(0.5,0.55);
	$graph->SetTitles($axtitles);
	$graph->title->Set("Result 2001");
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SupressTickMarks();
	$graph->SetShadow();
	//$graph->SetColor("teal");
	
	$graph->grid->SetLineStyle("solid");
	$graph->grid->SetColor("blue");
	$graph->grid->Show();

	// Add the plot and display the graph
	//$graph->Add($plot);
	$graph->Add($plot2);
	$graph->Stroke();
?>

	