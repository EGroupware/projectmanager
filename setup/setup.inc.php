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

$setup_info['projectmanager']['name']      = 'projectmanager';
$setup_info['projectmanager']['version']   = '0.2.009';
$setup_info['projectmanager']['app_order'] = 5;
$setup_info['projectmanager']['tables']    = array('egw_pm_projects','egw_pm_extra','egw_pm_elements','egw_pm_constraints','egw_pm_milestones','egw_pm_roles','egw_pm_members');
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
by using other already existing apps like InfoLog, Calendar or the TroubleTicketSystem.<br>
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
$setup_info['projectmanager']['hooks']['preferences'] = 'projectmanager.pm_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['projectmanager']['hooks'][] = 'settings';
$setup_info['projectmanager']['hooks']['admin'] = 'projectmanager.pm_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['projectmanager']['hooks']['sidebox_menu'] = 'projectmanager.pm_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['projectmanager']['hooks']['search_link'] = 'projectmanager.boprojectmanager.search_link';

/* Dependencies for this app to work */
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.0.0','1.0.1')
);
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.0.0','1.0.1')
);
// as long as the link class is not in the API
$setup_info['projectmanager']['depends'][] = array(
	 'appname' => 'infolog',
	 'versions' => Array('1.0.0','1.0.1')
);











