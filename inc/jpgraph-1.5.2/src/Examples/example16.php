<?php
include ("../jpgraph.php");
include ("../jpgraph_line.php");
include ("../jpgraph_error.php");

$errdatay = array(11,9,2,4,19,26,13,19,7,12);
$datax=array("Jan","Feb","Mar","Apr","May");

// Create the graph. These two calls are always required
$graph = new Graph(300,200,"auto");	
$graph->img->SetMargin(40,30,20,40);
$graph->SetScale("textlin");
$graph->SetShadow();
$graph->xaxis->SetLabelAngle(0);
$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,10);
$graph->img->SetAntiAliasing("white");

// Create the linear plot
$errplot=new ErrorLinePlot($errdatay);
$errplot->SetColor("red");
$errplot->SetWeight(2);
$errplot->SetCenter();
$errplot->line->SetWeight(2);
$errplot->line->SetColor("blue");
$errplot->SetLegend("Min/Max");
$errplot->line->SetLegend("Average");
// Add the plot to the graph
$graph->Add($errplot);

$graph->title->Set("Example 16");
$graph->yaxis->title->Set("Y-title");
$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->title->Set("X-title");
$graph->title->SetFont(FF_FONT1,FS_BOLD);

$graph->xaxis->SetTickLabels($datax);
$graph->xaxis->SetTextTickInterval(2);

// Display the graph
$graph->Stroke();
?>
