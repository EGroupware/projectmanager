<?php
include ("../jpgraph.php");
include ("../jpgraph_bar.php");

$datay=array(12,26,9,17,31);

// Create the graph. 
// One minute timeout for the cached image
// INLINE_NO means don't stream it back to the browser.
$graph = new Graph(310,250);
$graph->SetScale("textlin");
$graph->img->SetMargin(60,30,20,40);
$graph->yaxis->SetTitleMargin(45);
$graph->yaxis->scale->SetGrace(30);
$graph->SetShadow();

// Turn the tickmarks
$graph->xaxis->SetTickDirection(SIDE_DOWN);
$graph->yaxis->SetTickDirection(SIDE_LEFT);

// Create a bar pot
$bplot = new BarPlot($datay);

// Create targets for the image maps. One for each column
$targ=array("bar_clsmex1.php#1","bar_clsmex1.php#2","bar_clsmex1.php#3","bar_clsmex1.php#4","bar_clsmex1.php#5","bar_clsmex1.php#6");
$alts=array("val=%d","val=%d","val=%d","val=%d","val=%d","val=%d");
$bplot->SetCSIMTargets($targ,$alts);
$bplot->SetFillColor("orange");

// Use a shadow on the bar graphs (just use the default settings)
$bplot->SetShadow();
$bplot->SetValueFormat(" $ %2.1f",70);
$bplot->SetValueFont(FF_ARIAL,FS_NORMAL,9);
$bplot->SetValueColor("blue");
$bplot->ShowValue();

$graph->Add($bplot);

$graph->title->Set("Image maps barex1");
$graph->xaxis->title->Set("X-title");
$graph->yaxis->title->Set("Y-title");

$graph->title->SetFont(FF_FONT1,FS_BOLD);
$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);

// Write the image to a file.
$graph->Stroke("auto");

echo $graph->GetHTMLImageMap("myimagemap");
echo "<img src=\"".GenImgName()."\" ISMAP USEMAP=\"#myimagemap\" border=0>";
?>
