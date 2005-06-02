<?php
/**************************************************************************\
* eGroupWare - ProjectManager - Administration                             *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

/**
 * ProjectManager: Administration
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class admin
{
	/**
	 * @var array $public_functions Functions to call via menuaction
	 */
	var $public_functions = array(
		'config' => true,
	);
	var $accounting_types;
	var $duration_units;

	/**
	 * Constructor, calls the constructor of the extended class
	 */
	function admin()
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$GLOBALS['egw']->common->redirect_link('/index.php',array(
				'menuaction' => 'projectmanager.uiprojectmanger.index',
				'msg'        => lang('Permission denied !!!'),
			));
		}
		$this->config =& CreateObject('phpgwapi.config','projectmanager');
		$this->config->read_repository();

		$this->accounting_types = array(
			'status' => lang('No accounting, only status'),
			'times'  => lang('No accounting, only times and status'),
			'budget' => lang('Budget (no pricelist)'),
			'pricelist' => lang('Budget and pricelist'),
		);
		$this->duration_units = array(
			'd' => 'days',
			'h' => 'hours',
		);
	}
	
	/**
	 * Edit the site configuration
	 *
	 * @param array $content=null
	 */
	function config($content=null)
	{
		$tpl =& new etemplate('projectmanager.config');
		
		if ($content['save'] || $content['apply'])
		{
			foreach(array('duration_units','hours_per_workday','accounting_types') as $name)
			{
				$this->config->config_data[$name] = $content[$name];
			}
			$this->config->save_repository();
			$msg = lang('Site configuration saved');
		}
		if ($content['cancel'] || $content['save'])
		{
			$tpl->location(array(
				'menuaction' => 'projectmanager.uiprojectmanager.index',
				'msg' => $msg,
			));
		}
		$content = $this->config->config_data;
		if (!$content['duration_units']) $content['duration_units'] = array_keys($this->duration_units);
		if (!$content['hours_per_workday']) $content['hours_per_workday'] = 8;
		if (!$content['accounting_types']) $content['accounting_types'] = array_keys($this->accounting_types);
		
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Site configuration');
		$tpl->exec('projectmanager.admin.config',$content,array(
			'duration_units'   => $this->duration_units,
			'accounting_types' => $this->accounting_types,
		));
	}		
}