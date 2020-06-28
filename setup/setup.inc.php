<?php
/**
 * EGroupware ProjectManager - setup definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @subpackage setup
 * @copyright (c) 2005-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$setup_info['projectmanager']['name']      = 'projectmanager';
$setup_info['projectmanager']['version']   = '20.1';
$setup_info['projectmanager']['app_order'] = 5;
$setup_info['projectmanager']['tables']    = array('egw_pm_projects','egw_pm_extra','egw_pm_elements','egw_pm_constraints','egw_pm_milestones','egw_pm_roles','egw_pm_members','egw_pm_pricelist','egw_pm_prices','egw_pm_eroles');
$setup_info['projectmanager']['enable']    = 1;
$setup_info['projectmanager']['index']    = 'projectmanager.projectmanager_ui.index&ajax=true';
$setup_info['projectmanager']['author'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);
$setup_info['projectmanager']['maintainer'] = array(
	'name'  => 'EGroupware GmbH',
	'email' => 'info@egroupware.org'
);
$setup_info['projectmanager']['license']  = 'GPL';
$setup_info['projectmanager']['description'] =
'The projectmanager is a complete rewrite of the projects app using modern object orientated programming
technics and a widget based user-interface (eTemplate). It has a better integration in eGroupWare
by using other already existing apps like InfoLog, Calendar or the TimeSheet.<br>
For more information see the <a href="http://outdoor-training.de/pdf/projects-rewrite.pdf"
target="_blank">concept of the rewrite</a> (<a href="http://outdoor-training.de/pdf/Neuprogrammierung-Projektmanagement.pdf"
target="_blank">german version</a>).';
$setup_info['projectmanager']['note'] =
'It was sponsored by:<ul>
<li> <a href="http://www.blanke-textil.de" target="_blank">Fritz Blanke GmbH & Co.KG</a></li>
<li> <a href="http://www.digitask.de" target="_blank">DigiTask GmbH</a></li>
<li> <a href="http://www.stylite.de" target="_blank">Stylite GmbH</a></li>
<li> <a href="http://www.outdoor-training.de" target="_blank">Outdoor Unlimited Training GmbH</a></li>
</ul>';

/* The hooks this app includes, needed for hooks registration */
$setup_info['projectmanager']['hooks']['settings'] = 'projectmanager_hooks::settings';
$setup_info['projectmanager']['hooks']['verify_settings'] = 'projectmanager_hooks::verify_settings';
$setup_info['projectmanager']['hooks']['admin'] = 'projectmanager_hooks::all_hooks';
$setup_info['projectmanager']['hooks']['sidebox_menu'] = 'projectmanager_hooks::all_hooks';
$setup_info['projectmanager']['hooks']['search_link'] = 'projectmanager_hooks::search_link';
$setup_info['projectmanager']['hooks']['acl_rights'] = 'projectmanager_hooks::acl_rights';
$setup_info['projectmanager']['hooks']['categories'] = 'projectmanager_hooks::categories';
$setup_info['projectmanager']['hooks']['timesheet_set'] = 'projectmanager_hooks::timesheet_set';
$setup_info['projectmanager']['hooks']['deleteaccount'] = 'projectmanager.projectmanager_bo.change_delete_owner';

/* Dependencies for this app to work */
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('20.1')
);
