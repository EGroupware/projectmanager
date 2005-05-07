<?php
// ==========================================================================
// File:	TESTSUIT_JPGRAPH.PHP
// Created:	2001-02-24
// Ver:		$Id$
//
// Description: Generate a page with all individual test graphs suitable
//		for visual inspection. Note: This script must be run from
//		the same directory as where all the individual test graphs
//		are.
//		NOTE: Apache/PHP must have write permission to this directory
//		otherwise the source file can't be created.
//
// Notes:	This script can be called with a parameter type=1 or type=2
// 		which controls whether the images should be in situ on the page (2)
//		or just as a link (1). If not specified defaults to (1).
//
// License:	This code is released under GPL 2.0
// Copyright (C) 2001 Johan Persson  
// ==========================================================================

// Default to 1 if not explicitly specified
$type = !empty($HTTP_GET_VARS['type']) ? $HTTP_GET_VARS['type'] : 1;

function GetArrayOfTestGraphs($dp) {
    if( !chdir($dp) )
	die("Can't change to directory: $dir");	
    $d = dir($dp);
    while($entry=$d->read()) {
	if( !strstr($entry,".phps") &&  strstr($entry,".php") && strstr($entry,"x") && !strstr($entry,"show"))
	    $a[] = $entry;
    }
    $d->Close();
    if( empty($a) ) 
   	die("JpGraph Tetsuit Error: Apache/PHP does not have enough permission to read".
	    "the testfiles in directory: $dp");
    return $a;
}

$tf=GetArrayOfTestGraphs(getcwd());
sort($tf);

echo "<h2>Visual test suit for JpGraph</h2><p>";
echo "Number of tests: ".count($tf)."<p>";
echo "<ol>";

for($i=0; $i<count($tf); ++$i) {
	
    $exname = substr($tf[$i], 0, strrpos($tf[$i], '.'));
		
    switch( $type ) {
	case 1:
	    echo '<li><a href="show-example.php?target='.urlencode($tf[$i]).'">'.$exname.'</a>';
	    if( isset($showdate) ) echo '['.date("Y-m-d H:i",filemtime($tf[$i])).']';
	    echo "\n";
	    break;
	case 2:
	    echo '<li><a href="show-example.php?target='.urlencode($tf[$i]).'">
					<img src="'.$tf[$i].'" border=0 align=top></a>
					<br><strong>Filename:</strong> <i>'.$exname.'</i><br>&nbsp;';
	    if( isset($showdate) )
		echo ' ['.date('Y-m-d H:i',filemtime($tf[$i])).']';
	    echo "\n";
	    break;	
    }		
}
echo "</ol>";
echo "<p>Test suit done.";

/* EOF */
?>