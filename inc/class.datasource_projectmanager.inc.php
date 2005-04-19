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
		// we use $GLOBALS['boprojectmanager'] as an already running instance is availible there
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
		$ds = array(
			'pe_title' => $GLOBALS['boprojectmanager']->link_title($data['pm_id'],$data),
		);
		foreach($this->name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);
			
			if (isset($data[$pm_name]))
			{
				$ds[$name] = $data[$pm_name];
			}
		}
		if (is_numeric($ds['pe_completion'])) $ds['pe_completion'] .= '%';

		return $ds;
	}
}