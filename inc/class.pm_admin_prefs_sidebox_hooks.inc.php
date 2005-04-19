<?php
/**************************************************************************\
* eGroupWare - ProjectManager: Admin-, Preferences- and SideboxMenu-Hooks  *
* http://www.eGroupWare.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* -------------------------------------------------------                  *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

class pm_admin_prefs_sidebox_hooks
{
	var $public_functions = array(
		'all_hooks' => true,
	);
	function all_hooks($args)
	{
		$appname = 'projectmanager';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ranking_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			// project-dropdown in sidebox menu
			if (!is_object($GLOBALS['egw']->html))
			{
				$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
			}
			if (!is_object($GLOBALS['boprojectmanager']))
			{
				// dont assign it to $GLOBALS['boprojectmanager'], as the constructor does it!!!
				CreateObject('projectmanager.uiprojectmanager');
			}
			if (($pm_id = (int) $_REQUEST['pm_id']))
			{
				$GLOBALS['egw']->session->appsession('pm_id','projectmanager',$pm_id);
			}
			else
			{
				$pm_id = (int) $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
			}
			$projects = array();
			foreach((array)$GLOBALS['boprojectmanager']->search(array(
				'pm_status' => 'active',
				'pm_id'     => $pm_id,
			),'pm_id,pm_number,pm_title','pm_modified','','',False,'OR') as $project)
			{
				$projects[$project['pm_id']] = array(
					'label' => $project['pm_number'],
					'title' => $project['pm_title'],
				);
				if ($pm_id == $project['pm_id'])
				{
					$pm_title = $project['pm_title'];
				}
			}
			if (!$pm_title) 
			{
				$projects[0] = lang('select a project');
			}
			$file = array(
				array(
					'text' => $GLOBALS['egw']->html->select('pm_id',$pm_id,$projects,true,
						' onchange="location.href=\''.$GLOBALS['egw']->link('/index.php',array(
							'menuaction'=>'projectmanager.uiprojectelements.index',
						)).'&pm_id=\'+this.value;" title="'.$GLOBALS['egw']->html->htmlspecialchars($pm_title).'"'),
					'no_lang' => True,
					'link' => False
				),
				'Projectlist' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'projectmanager.uiprojectmanager.index' )),
				'Elementlist' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'projectmanager.uiprojectelements.index' )),
			);
			display_sidebox($appname,$GLOBALS['phpgw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['phpgw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				//'Preferences'     => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname='.$appname),
				//'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['phpgw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				/*'Site configuration' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => $appname,
				 )),*/
				'Global Categories'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}
}