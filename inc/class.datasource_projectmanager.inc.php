<?php
/**************************************************************************\
* eGroupWare - ProjectManager - DataSource for InfoLog                     *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for ProjectManager itself
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class datasource_projectmanager extends datasource
{
	/**
	 * @var int/string $debug 0 = no debug-messages, 1 = main, 2 = more, 3 = all, or string function-name to debug
	 */
	var $debug=false;

	/**
	 * Constructor
	 */
	function datasource_projectmanager()
	{
		$this->datasource('projectmanager');
		
		$this->valid = PM_ALL_DATA;
	}
	
	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 * 
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		// we use $GLOBALS['boprojectmanager'] as an already running instance may be availible there
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.boprojectmanager.inc.php');
			$GLOBALS['boprojectmanager'] =& new boprojectmanager();
		}
		if (!is_array($data_id))
		{
			if (!$GLOBALS['boprojectmanager']->read((int) $data_id)) return false;

			$data =& $GLOBALS['boprojectmanager']->data;
		}
		else
		{
			$data =& $data_id;
		}
		$ds = array();
		foreach($this->name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);
			
			if (isset($data[$pm_name]))
			{
				$ds[$name] = $data[$pm_name];
			}
		}
		$ds['pe_title'] = $GLOBALS['boprojectmanager']->link_title($data['pm_id'],$data);

		if (is_numeric($ds['pe_completion'])) $ds['pe_completion'] .= '%';

		if ((int) $this->debug > 1 || $this->debug == 'get') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::get($data_id) =".print_r($ds,true));

		return $ds;
	}
	
	/**
	 * Copy the datasource of a projectelement (sub-project) and re-link it with project $target
	 *
	 * @param array $element source project element representing an sub-project, $element['pe_app_id'] = pm_id
	 * @param int $target target project id
	 * @param array $target_data=null data of target-project, atm only pm_number is used
	 * @return array/boolean array(pm_id,link_id) on success, false otherwise
	 */
	function copy($element,$target,$target_data=null)
	{
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.boprojectmanager.inc.php');
			$GLOBALS['boprojectmanager'] =& new boprojectmanager();
		}
		if ((int) $this->debug > 1 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy(".print_r($element,true).",$target)");

		$data_backup = $GLOBALS['boprojectmanager']->data;

		$pm_id = false;
		if ($GLOBALS['boprojectmanager']->copy((int) $element['pe_app_id'],0,$target_data['pm_number']))
		{
			if ($this->debug > 3 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy() data=".print_r($GLOBALS['boprojectmanager']->data,true));

			$pm_id = $GLOBALS['boprojectmanager']->data['pm_id'];
			// link the new sub-project against the project
			$link_id = $GLOBALS['boprojectmanager']->link->link('projectmanager',$target,'projectmanager',$pm_id,$element['pe_remark'],0,0,1);
		}
		$GLOBALS['boprojectmanager']->data = $data_backup;

		if ($this->debug > 2 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy() returning pm_id=$pm_id, link_id=$link_id, data=".print_r($GLOBALS['boprojectmanager']->data,true));

		return $pm_id ? array($pm_id,$link_id) : false;
	}
}