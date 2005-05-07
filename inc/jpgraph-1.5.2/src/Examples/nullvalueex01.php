<?php
include ("../jpgraph.php");
include ("../jpgraph_line.php");

// Some data
$datax = array("2001-04-01","2001-04-02","2001-04-03",
				   "2001-04-04","2001-04-05","2001-04-06");
$datay = array(28,13,24,"",90,11);
$data2y = array(11,41,"-",33,"-",63);

// A nice graph with anti-aliasing
$graph = new Graph(450,250,"auto");
$graph->img->SetMargin(40,170,40,80);	
//$graph->img->SetAntiAliasing();
$graph->SetScale("textlin");
$graph->SetShadow();
$graph->title->Set("Line plot with null values");

// Use built in font
$graph->title->SetFont(FF_FONT1,FS_BOLD);

// Slightly adjust the legend from it's default position in the
// top right corner. 
$graph->legend->Pos(0.03,0.5,"right","center");

// Setup X-scale
$graph->xaxis->SetTickLabels($datax);
$graph->xaxis->SetFont(FF_FONT1);
$graph->xaxis->SetLabelAngle(90);

// Create the first line
$p1 = new LinePlot($datay);
$p1->mark->SetType(MARK_FILLEDCIRCLE);
$p1->mark->SetFillColor("red");
$p1->mark->SetWidth(4);
$p1->SetColor("blue");
$p1->SetCenter();
$p1->SetLegend("Undefined variant 1");
$graph->Add($p1);

// ... and the second
$p2 = new LinePlot($data2y);
$p2->mark->SetType(MARK_STAR);
$p2->mark->SetFillColor("red");
$p2->mark->SetWidth(4);
$p2->SetColor("red");
$p2->SetCenter();
$p2->SetLegend("Undefined variant 2");
$graph->Add($p2);

// Output line
$graph->Stroke();

?>


