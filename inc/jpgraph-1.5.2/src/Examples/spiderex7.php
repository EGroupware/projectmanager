<?php
	include ("../jpgraph.php");
	include ("../jpgraph_spider.php");
	
	$graph = new SpiderGraph(300,200,"auto");
	$graph->SetColor("white");
	$graph->SetShadow();
	$graph->SetCenter(0.4,0.55);
	
	$graph->axis->SetFont(FF_FONT1,FS_BOLD);
	$graph->axis->SetWeight(2);
	$graph->grid->SetLineStyle("longdashed");
	$graph->grid->SetColor("navy");
	$graph->grid->Show();
	$graph->SupressTickMarks();
		
	$graph->title->Set("Quality result");
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetTitles(array("One","Two","Three","Four","Five","Sex","Seven","Eight","Nine","Ten"));
		
	$plot = new SpiderPlot(array(30,80,60,40,71,81,47));
	$plot->SetLegend("Goal");
	$plot->SetColor("red","lightred");
	$plot->SetFill(false);
	$plot->SetLineWeight(2);

	$plot2 = new SpiderPlot(array(70,40,30,80,31,51,14));
	$plot2->SetLegend("Actual");
	$plot2->SetColor("blue","lightred");

	$graph->Add($plot2);
	$graph->Add($plot);
	$graph->Stroke();

?>