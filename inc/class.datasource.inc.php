<?php
/**************************************************************************\
* eGroupWare - ProjectManager - DataSource baseclass                       *
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
 * constants for the different types of data
 *
 * or'ed together eg. for egw_pm_eletemts.pe_overwrite
 */
/** int percentage completion 0-100 */
define('PM_COMPLETION',1);
/** int seconds planed time */
define('PM_PLANED_TIME',2);
/** int seconds used time */
define('PM_USED_TIME',4);
/** double planed budget */
define('PM_PLANED_BUDGET',8);
/** double planed budget */
define('PM_USED_BUDGET',16);
/** int timestamp planed start-date */
define('PM_PLANED_START',32);
/** int timestamp real start-date */
define('PM_REAL_START',64);		
/** int timestamp planed end-date */
define('PM_PLANED_END',128);
/** int timestamp real end-date */
define('PM_REAL_END',256);
/** int timestamp real end-date */
define('PM_RESOURCES',512);
/** all data-types or'ed together, need to be changed if new data-types get added */
define('PM_ALL_DATA',1023);

/**
 * DataSource baseclass of the ProjectManager
 *
 * This is the baseclass of all DataSources, each spezific DataSource extends it.
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class datasource
{
	/**
	 * @var string $type type of the datasource, eg. name of the supported app
	 */
	var $type;
	/**
	 * @var bolink-object $link instance of the link-class
	 */
	var $link;
	/**
	 * @var object $bo bo-object of the used app
	 */
	var $bo;
	/**
	 * @var int $valid valid data-types of that source (or'ed PM_ constants)
	 */
	var $valid = 0;
	/**
	 * @var array $name2id translated names / array-keys to the numeric ids PM_*
	 */
	var $name2id = array(
		'pe_completion'    => PM_COMPLETION,
		'pe_planed_time'   => PM_PLANED_TIME,
		'pe_used_time'     => PM_USED_TIME,
		'pe_planed_budget' => PM_PLANED_BUDGET,
		'pe_used_budget'   => PM_USED_BUDGET,
		'pe_planed_start'  => PM_PLANED_START,
		'pe_real_start'    => PM_REAL_START,
		'pe_planed_end'    => PM_PLANED_END,
		'pe_real_end'      => PM_REAL_END,
	);

	/**
	 * Constructor
	 *
	 * @param string $type=null type of the datasource
	 */
	function datasource($type=null)
	{
		$this->type = $type;

		if (!is_object($GLOBALS['egw']->link))
		{
			$GLOBALS['egw']->link =& CreateObject('infolog.bolink');
		}
		$this->link =& $GLOBALS['egw']->link;
	}
	
	/**
	 * get an item from the underlaying app and convert applying data ia a datasource array
	 *
	 * A datasource array can contain values for the keys: completiton, {planed|used}_time, {planed|used}_budget,
	 *	{planed|real}_start, {planed|real}_end
	 * Not set values mean they are not supported by the datasource.
	 *
	 * Reimplent this function for spezial datasource types (not read!)
	 * 
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		if (($title = $this->link->title($this->type,$data_id)))
		{
			return array(
				'pe_title' => $title,
			);
		}
		return false;
	}
	
	/**
	 * read an item from a datasource (via the get methode) and try to set (guess) some not supported values
	 *
	 * A datasource array can contain values for the keys: completiton, {planed|used}_time, {planed|used}_budget,
	 *	{planed|real}_start, {planed|real}_end
	 * Not set values mean they are not supported by the datasource.
	 * 
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function read($data_id)
	{
		$ds = $this->get($data_id);
		
		if ($ds)
		{
			// setting real or planed start- or end-date, from each other if not set
			foreach(array('start','end') as $name)
			{
				if (!isset($ds['pe_real_'.$name]) && isset($ds['pe_planed_'.$name]))
				{
					$ds['pe_real_'.$name] = $ds['pe_planed_'.$name];
				}
				elseif (!isset($ds['pe_planed_'.$name]) && isset($ds['pe_real_'.$name]))
				{
					$ds['pe_planed_'.$name] = $ds['pe_real_'.$name];
				}
			}
			// try calculating a (second) completion from the times
			if (!empty($ds['pe_used_time']) && (int) $ds['pe_planed_time'] > 0)
			{
				$compl_by_time = $ds['pe_used_time'] / $ds['pe_planed_time'];

				// if no completion is given by the datasource use the calculated one
				if (!isset($ds['pe_completion']))
				{
					$ds['pe_completion'] = $compl_by_time;
				}
				elseif ($compl_by_time < $ds['pe_completion'])
				{
					$ds['warning']['completion_by_time'] = $compl_by_time;
				}
			}
			// try calculating a (second) completion from the budget
			if(!empty($ds['pe_used_budget']) && $ds['pe_planed_budget'] > 0)
			{
				$compl_by_budget = $ds['pe_used_budget'] / $ds['pe_planed_budget'];
			
				// if no completion is given by the datasource use the calculated one
				if (!isset($ds['pe_completion']))
				{
					$ds['pe_completion'] = $compl_by_budget;
				}
				elseif ($compl_by_budget < $ds['pe_completion'])
				{
					$ds['warning']['completion_by_budget'] = $compl_by_budget;
				}
			}
		}		
		return $ds;
	}
}