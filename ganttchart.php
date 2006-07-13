<?php
/**************************************************************************\
* eGroupWare - ProjectManager - output a gantchart-image                   *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * As ganttcharts contain an image-map and the image, we save the image as a temporary file.
 * This for performance reasons, it saves a second creation / script-run.
 * This script reads and output the temporary file/image and unlinks it after.
 * If the temp. image is not found, it creates a new one. 
 * It can be used standalone, eg. from SiteMgr.
 */
error_reporting(E_ALL & ~E_NOTICE);

$tmp = $GLOBALS['egw_info']['server']['temp_dir'];
if (!$tmp || !is_dir($tmp) || !is_writable($tmp))
{
	@unlink($tmp = tempnam('','test'));	// get the systems temp-dir
	$tmp = dirname($tmp);
}
if (isset($_GET['img']) && is_readable($ganttchart = $tmp.'/'.basename($_GET['img'])))
{
	header('Content-type: image/png');
	readfile($ganttchart);
	@unlink($ganttchart);
	exit;
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'projectmanager', 
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

ExecMethod('projectmanager.ganttchart.create');

$GLOBALS['egw']->common->egw_exit();