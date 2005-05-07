<?php
include ("../jpgraph.php");
include ("../jpgraph_pie.php");

// Some data
$data = array(27,23,47,17);

// A new graph
$graph = new PieGraph(350,200,"auto");
$graph->SetShadow();

// Setup title
$graph->title->Set("Example of pie plot with absolute labels");
$graph->title->SetFont(FF_FONT1,FS_BOLD);

// The pie plot
$p1 = new PiePlot($data);

// Move center of pie to the left to make better room
// for the legend
$p1->SetCenter(0.35,0.5);

// Label font and color setup
$p1->SetFont(FF_FONT1,FS_BOLD);
$p1->SetFontColor("darkred");

// Use absolute values (type==1)
$p1->SetLabelType(1);

// Label format
$p1->SetLabelFormat("$%d");

// Size of pie in fraction of the width of the graph
$p1->SetSize(0.3);

// Legends
$p1->SetLegends(array("May ($%d)","June ($%d)","July ($%d)","Aug ($%d)"));
$graph->legend->Pos(0.05,0.15);

$graph->Add($p1);
$graph->Stroke();
?>


