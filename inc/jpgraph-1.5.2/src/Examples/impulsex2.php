<?php
include ("../jpgraph.php");
include ("../jpgraph_scatter.php");

$datay = array(20,22,12,13,17,20,16,19,30,31,40,43);
$graph = new Graph(300,200,"auto");
$graph->img->SetMargin(40,40,40,40);		
$graph->SetScale("textlin");
$graph->SetShadow();
$graph->title->Set("Example 2 of impuls scatter plot");
$graph->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->title->Set("Impuls respons");
$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);

$sp1 = new ScatterPlot($datay);//,$datax);
$sp1->mark->SetType(MARK_FILLEDCIRCLE);
$sp1->mark->SetFillColor("red");
$sp1->SetImpuls();
$sp1->SetColor("blue");
$sp1->SetWeight(3);
$sp1->mark->SetWidth(4);

$graph->Add($sp1);

$graph->Stroke();

?>