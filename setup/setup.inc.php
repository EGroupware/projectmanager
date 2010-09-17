<?php
/**
 * EGroupware ProjectManager - setup definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @subpackage setup
 * @copyright (c) 2005-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['projectmanager']['name']      = 'projectmanager';
$setup_info['projectmanager']['version']   = '1.8';
$setup_info['projectmanager']['app_order'] = 5;
$setup_info['projectmanager']['tables']    = array('egw_pm_projects','egw_pm_extra','egw_pm_elements','egw_pm_constraints','egw_pm_milestones','egw_pm_roles','egw_pm_members','egw_pm_pricelist','egw_pm_prices');
$setup_info['projectmanager']['enable']    = 1;

$setup_info['projectmanager']['author'] =
$setup_info['projectmanager']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
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
'It is sponsored by:<ul>
<li> <a href="http://www.blanke-textil.de" target="_blank">Fritz Blanke GmbH & Co.KG</a></li>
<li> <a href="http://www.digitask.de" target="_blank">DigiTask GmbH</a></li>
<li> <a href="http://www.stylite.de" target="_blank">Stylite GmbH</a></li>
<li> <a href="http://www.outdoor-training.de" target="_blank">Outdoor Unlimited Training GmbH</a></li>
</ul>';

/* The hooks this app includes, needed for hooks registration */
$setup_info['projectmanager']['hooks']['preferences'] = 'projectmanager_hooks::all_hooks';
$setup_info['projectmanager']['hooks']['settings'] = 'projectmanager_hooks::settings';
$setup_info['projectmanager']['hooks']['admin'] = 'projectmanager_hooks::all_hooks';
$setup_info['projectmanager']['hooks']['sidebox_menu'] = 'projectmanager_hooks::all_hooks';
$setup_info['projectmanager']['hooks']['search_link'] = 'projectmanager_hooks::search_link';

/* Dependencies for this app to work */
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.7','1.8','1.9')
);
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.7','1.8','1.9')
);
// installation checks for email
$setup_info['projectmanager']['check_install'] = array(
	'gd' => array(
		'func' => 'gd_check',
	),
	'jpgraph' => array(
		'func' => 'jpgraph_check',
		'min_version' => '1.13',
	),
);
