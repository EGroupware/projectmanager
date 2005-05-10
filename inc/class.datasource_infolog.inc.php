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
 * DataSource for InfoLog
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class datasource_infolog extends datasource
{
	/**
	 * Constructor
	 */
	function datasource_infolog()
	{
		$this->datasource('infolog');
		
		$this->valid = PM_COMPLETION|PM_PLANNED_START|PM_PLANNED_END|PM_RESOURCES;
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
		if (!is_object($GLOBALS['boinfolog']))
		{
			include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');
			$GLOBALS['boinfolog'] =& new boinfolog();
		}
		if (!is_array($data_id))
		{
			$data =& $GLOBALS['boinfolog']->read((int) $data_id);
			
			if (!is_array($data)) return false;
		}
		else
		{
			$data =& $data_id;
		}
		return array(
			'pe_title'        => $GLOBALS['boinfolog']->link_title($data),
			'pe_completion'   => $this->status2completion($data['info_status']).'%',
			'pe_planned_start' => $data['info_startdate'],
			'pe_planned_end'   => $data['info_enddate'],
			'pe_resources'    => array($data['info_responsible'] ? $data['info_responsible'] : $data['info_owner']),
		);
	}
	
	/**
	 * converts InfoLog status into a percentage completion
	 *
	 * percentages are just used, done&billed give 100, ongoing&will-call give 50, rest (incl. all custome status) give 0
	 *
	 * @param string $status
	 * @return int completion in percent
	 */
	function status2completion($status)
	{
		if ((int) $status || substr($status,-1) == '%') return (int) $status;	// allready a percentage
		
		switch ($status)
		{
			case 'done':
			case 'billed':
				return 100;
				
			case 'will-call':
				return 50;

			case 'ongoing':
				return 10;
		}
		return 0;
	}
}