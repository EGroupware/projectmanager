<?php
/**
 * ProjectManager - Index page
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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

include_once(EGW_INCLUDE_ROOT.'/projectmanager/setup/setup.inc.php');
if ($setup_info['projectmanager']['version'] != $GLOBALS['egw_info']['apps']['projectmanager']['version'])
{
	// 1.9.001 update is run inside EGroupware by datasource constructor
	if ($GLOBALS['egw_info']['apps']['projectmanager']['version'] === '1.9.001')
	{
		include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');
		new datasource();
	}
	else
	{
		common::egw_header();
		parse_navbar();
		echo '<p style="text-align: center; color:red; font-weight: bold;">'.lang('Your database is NOT up to date (%1 vs. %2), please run %3setup%4 to update your database.',
			$setup_info['projectmanager']['version'],$GLOBALS['egw_info']['apps']['projectmanager']['version'],
			'<a href="../setup/">','</a>')."</p>\n";
		common::egw_exit();
	}
}
unset($setup_info);

$pm_id = $GLOBALS['egw']->session->appsession('pm_id','projectmanager');

$GLOBALS['egw']->redirect_link('/index.php',array(
	'menuaction' => $pm_id ? 'projectmanager.projectmanager_elements_ui.index' : 'projectmanager.projectmanager_ui.index',
));
common::egw_exit();
