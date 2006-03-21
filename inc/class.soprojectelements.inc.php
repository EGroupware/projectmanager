<?php
/**************************************************************************\
* eGroupWare - ProjectManager - Elements storage object                    *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/**
 * Elements storage object of the projectmanager
 *
 * Tables: egw_pm_elements, egw_links
 *
 * A project P is the parent of an other project C, if link_id1=P.pm_id and link_id2=C.pm_id !
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class soprojectelements extends so_sql
{
	/**
	 * @var string $links_table table name 'egw_links'
	 */
	var $links_table = 'egw_links';
	/**
	 * @var string $links_join join in the links table
	 */
	var $links_join = ',egw_links WHERE pe_id=link_id';
	/**
	 * @var array $links_extracols extracolumns from the links table
	 */
	var $links_extracols = array(
		"CASE WHEN link_app1='projectmanager' AND link_id1=pm_id THEN link_app2 ELSE link_app1 END AS pe_app",
		"CASE WHEN link_app1='projectmanager' AND link_id1=pm_id THEN link_id2 ELSE link_id1 END AS pe_app_id",
		'link_remark AS pe_remark',
	);
	/**
	 * @var int $default_share default share in minutes (on the whole project), used if no planned time AND no pe_share set
	 */
	var $default_share = 240; // minutes

	/**
	 * Constructor, calls the constructor of the extended class
	 * 
	 * It is sufficent to give just the pe_id, as it is unique!
	 *
	 * @param int $pm_id pm_id of the project to use, default null
	 * @param int $pe_id pe_id of the project-element to load, default null
	 */
	function soprojectelements($pm_id=null,$pe_id=null)
	{
		$this->so_sql('projectmanager','egw_pm_elements');

		if ((int) $pm_id || (int) $pe_id) 
		{
			$this->pm_id = (int) $pm_id;
			
			if ((int) $pe_id)
			{
				if ($this->read($pe_id)) $this->pm_id = $this->data['pm_id'];
			}
		}
	}
	
	/**
	 * Summarize the information of all elements of a project: min(start-time), sum(time), avg(completion), ...
	 *
	 * @param int/array $pm_id=null int project-id, array of project-id's or null to use $this->pm_id
	 * @param array $filter=array() columname => value pairs to filter, eg. '
	 * @return array/boolean with summary information (keys as for a single project-element), false on error
	 */
	function summary($pm_id=null,$filter=array())
	{
		if (is_null($pm_id)) $pm_id = $this->pm_id;

		if ($this->project->data['pm_id'] != $pm_id)
		{
			$save_data = $this->project->data;
			$this->project->read($pm_id);
		}
		if ($this->project->data['pm_accounting_type'] == 'status')	// we dont have a times!
		{
			$share = "CASE WHEN pe_share IS NULL THEN $this->default_share ELSE pe_share END";
		}
		else
		{
			$share = "CASE WHEN pe_share IS NULL AND pe_planned_time IS NULL THEN $this->default_share WHEN pe_share IS NULL THEN pe_planned_time ELSE pe_share END";
		}
		if ($save_data) $this->project->data = $save_data;

		if (!isset($filter['pm_id'])) $filter['pm_id'] = $pm_id;
		if (!isset($filter['pe_status'])) $filter[] = "pe_status != 'ignore'";
		// fix some special filters: resources, cats
		$filter = $this->_fix_filter($filter);

		$this->db->select($this->table_name,array(
			"SUM(pe_completion * ($share)) AS pe_sum_completion_shares",
			"SUM(CASE WHEN pe_completion IS NULL THEN NULL ELSE ($share) END) AS pe_total_shares",
//			'AVG(pe_completion) AS pe_completion',
			'SUM(pe_used_time) AS pe_used_time',
			'SUM(pe_planned_time) AS pe_planned_time',
			'SUM(pe_used_budget) AS pe_used_budget',
			'SUM(pe_planned_budget) AS pe_planned_budget',
			'MIN(pe_real_start) AS pe_real_start',
			'MIN(pe_planned_start) AS pe_planned_start',
			'MAX(pe_real_end) AS pe_real_end',
			'MAX(pe_planned_end) AS pe_planned_end',
		),$filter,__LINE__,__FILE__);
		
		if (!($data = $this->db->row(true)))
		{
			return false;
		}
		if ($data['pe_total_shares'])
		{
			$data['pe_completion'] = round($data['pe_sum_completion_shares'] / $data['pe_total_shares'],1);
		}
		return $this->db2data($data);
	}

	/**
	 * search elements, reimplemented to use $this->pm_id, if no pm_id given in criteria or filter
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys True returns only keys, False returns all cols
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard appended befor and after each criteria
	 * @param boolean $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param int/boolean $start if != false, return only maxmatch rows begining with start
	 * @param array $filter if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string/boolean $join=true default join with links-table or string as in so_sql
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		if ($this->pm_id && !isset($criteria['pm_id']) && (!isset($filter['pm_id']) || !$filter['pm_id']))
		{
			$filter['pm_id'] = $this->pm_id;
		}
		if ($join === true)	// add join with links-table and extra-columns
		{
			$join = $this->links_join;
			
			if (!$extra_cols)
			{
				$extra_cols = $this->links_extracols;
			}
			else
			{
				$extra_cols = array_merge($this->links_extracols,
					is_array($extra_cols) ? $extra_cols : explode(',',$extra_cols));
			}			
		}
		// fix some special filters: resources, cats
		$filter = $this->_fix_filter($filter);

		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}
	
	function _fix_filter($filter)
	{
		// handle search for a single resource in comma-separated pe_resources column
		if (isset($filter['pe_resources']))
		{
			if ($filter['pe_resources'])
			{
				$filter[] = $this->db->concat("','",'pe_resources',"','").' LIKE '.$this->db->quote('%,'.$filter['pe_resources'].',%');
			}
			unset($filter['pe_resources']);
		}
		// include sub-categories in the search
		if ($filter['cat_id'])
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories');
			}
			$filter['cat_id'] = $GLOBALS['egw']->categories->return_all_children($filter['cat_id']);
		}
		return $filter;
	}

	/**
	 * reads one project-element specified by $keys, reimplemented to use $this->pm_id, if no pm_id given
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string/boolean $join=true default join with links-table or string as in so_sql
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join=true)
	{
		if ($this->pm_id && !isset($keys['pm_id']))
		{
			if (!is_array($keys) && (int) $keys) $keys = array('pe_id' => (int) $keys);
			$keys['pm_id'] = $this->pm_id;
		}
		if ($join === true)	// add join with links-table and extra-columns
		{
			$join = $this->links_join;
			
			if (!$extra_cols) $extra_cols = $this->links_extracols;
		}
		return parent::read($keys,$extra_cols,$join);
	}
}