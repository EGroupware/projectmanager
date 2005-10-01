<?php
/**************************************************************************\
* eGroupWare - ProjectManager                                              *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'projectmanager', 
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

include_once(EGW_INCLUDE_ROOT.'/projectmanager/setup/setup.inc.php');
if ($setup_info['projectmanager']['version'] != $GLOBALS['egw_info']['apps']['projectmanager']['version'])
{
	$GLOBALS['egw']->common->egw_header();
	parse_navbar();
	echo '<p style="text-align: center; color:red; font-weight: bold;">'.lang('Your database is NOT up to date (%1 vs. %2), please run %3setup%4 to update your database.',
		$setup_info['projectmanager']['version'],$GLOBALS['egw_info']['apps']['projectmanager']['version'],
		'<a href="../setup/">','</a>')."</p>\n";
	$GLOBALS['egw']->common->egw_exit();
}
unset($setup_info);

ExecMethod('projectmanager.pm_admin_prefs_sidebox_hooks.check_set_default_prefs');

$pm_id = $GLOBALS['egw']->session->appsession('pm_id','projectmanager');

$GLOBALS['egw']->redirect_link('/index.php',array(
	'menuaction' => $pm_id ? 'projectmanager.uiprojectelements.index' : 'projectmanager.uiprojectmanager.index',
));
$GLOBALS['egw']->common->egw_exit();
