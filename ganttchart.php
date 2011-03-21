<?php
/**
 * ProjectManager - Gantchart creation
 *
 * As ganttcharts contain an image-map and the image, we save the image as a temporary file.
 * This for performance reasons, it saves a second creation / script-run.
 * This script reads and output the temporary file/image and unlinks it after.
 * If the temp. image is not found, it creates a new one.
 * It can be used standalone, eg. from SiteMgr.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'projectmanager',
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

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
}
else
{
	ExecMethod('projectmanager.projectmanager_ganttchart.create');
}
common::egw_exit();