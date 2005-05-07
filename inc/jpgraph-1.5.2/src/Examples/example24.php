<?php
include ("../jpgraph.php");
include ("../jpgraph_bar.php");

$data1y=array(12,8,19,3,10,5);
$data2y=array(8,2,11,7,14,4);

$data3y=array(4,1,9,15,12,6);
$data4y=array(8,3,2,1,9,10);

// Create the graph. These two calls are always required
$graph = new Graph(300,315,"auto");	
$graph->img->SetMargin(40,30,20,40);
$graph->SetScale("textlin");
$graph->SetShadow();
$graph->SetBox();
$graph->SetTickDensity(TICKD_VERYSPARSE);

// Create the bar plots
$b1plot = new BarPlot($data1y);
$b1plot->SetFillColor("orange");
$b2plot = new BarPlot($data2y);
$b2plot->SetFillColor("blue");

$b3plot = new BarPlot($data3y);
$b3plot->SetFillColor("green");
$b4plot = new BarPlot($data4y);
$b4plot->SetFillColor("red");

// Create the accumulated bar plots
$ab1plot = new AccBarPlot(array($b1plot,$b2plot));
$ab2plot = new AccBarPlot(array($b3plot,$b4plot));

// Create the grouped bar plot
//$gbplot = new GroupBarPlot(array($ab1plot,$ab2plot));

// ...and add it to the graPH
//$graph->Add($gbplot);
$graph->Add($b1plot);
$graph->title->Set("Example 24");
$graph->xaxis->title->Set("X-title");
$graph->yaxis->title->Set("Y-title");

$graph->title->SetFont(FF_FONT1,FS_BOLD);
$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);

// Display the graph
$graph->Stroke();
?>
