<?php
include ("../jpgraph.php");
include ("../jpgraph_pie.php");

// Some data
$data = array(40,21,17,14,23);

// Create the Pie Graph. 
$graph = new PieGraph(300,200);
$graph->SetShadow();

// Set A title for the plot
$graph->title->Set("Client side image map");
$graph->title->SetFont(FF_FONT1,FS_BOLD);

// Create
$p1 = new PiePlot($data);
$p1->SetLegends(array("Jan","Feb","Mar","Apr","May","Jun","Jul"));
$targ=array("pie_csimex1.php#1","pie_csimex1.php#2","pie_csimex1.php#3",
"pie_csimex1.php#4","pie_csimex1.php#5","pie_csimex1.php#6");
$alts=array("val=%v","val=%v","val=%v","val=%v","val=%v","val=%v");
$p1->SetCSIMTargets($targ,$alts);

$graph->Add($p1);
$graph->Stroke(GenImgName());


echo $graph->GetHTMLImageMap("myimagemap");
echo "<img src=\"".GenImgName()."\" ISMAP USEMAP=\"#myimagemap\" border=0>";

?>


