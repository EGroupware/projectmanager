<?php
/**************************************************************************\
* eGroupWare - ProjectManager - eTemplates Widgets                         *
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
 * ProjectManager: eTemplate widgets
 *
 * The Select Price Widget show the pricelist of the project with pm_id=$content['pm_id']!!!
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class projectmanager_widget
{
	/** 
	 * @var array $public_functions exported methods of this class
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * @var array $human_name availible extensions and there names for the editor
	 */
	var $human_name = array(
		'projectmanager-select'    => 'Select Project',
		'projectmanager-pricelist' => 'Select Price',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function projectmanager_widget($ui)
	{
		$this->ui = $ui;
	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets 
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		static $pricelist = array();
		// check if user has rights to run projectmanager
		if (!$GLOBALS['egw_info']['user']['apps']['projectmanager'])
		{
			$cell = $tmpl->empty_cell();
			$value = '';
			return false;
		}
		list($rows,$type,$type2,$type3) = explode(',',$cell['size']);

		$extension_data['type'] = $cell['type'];

		switch ($cell['type'])
		{
			case 'projectmanager-select':
				if (!is_object($GLOBALS['boprojectmanager']))
				{
					CreateObject('projectmanager.boprojectmanager');	// assigns itselft to $GLOBALS['boprojectmanager']
				}
				$cell['sel_options'] = $GLOBALS['boprojectmanager']->link_query('');
				if (!$cell['help']) $cell['help'] = /*lang(*/ 'Select a project' /*)*/;
				break;

			case 'projectmanager-pricelist':
				$pm_id = (int) $tmpl->content['pm_id'];
				// some caching for the pricelist, in case it's needed multiple times
				if (!isset($pricelist[$pm_id]))
				{
					if (!is_object($this->pricelist))
					{
						$this->pricelist =& CreateObject('projectmanager.bopricelist');
					}
					$pricelist[$pm_id] = $this->pricelist->pricelist($pm_id);
				}
				$cell['sel_options'] = $pricelist[$pm_id];
				if (!$cell['help']) $cell['help'] = /*lang(*/ 'Select a price' /*)*/;
				break;
		}
		$cell['no_lang'] = True;
		$cell['type'] = 'select';
		if ($rows > 1)
		{
			unset($cell['sel_options']['']);
		}
		return True;	// extra Label Ok
	}
}
