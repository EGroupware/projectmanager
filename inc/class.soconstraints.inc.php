<?php
/**************************************************************************\
* eGroupWare - ProjectManager - Constraints storage object                 *
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
 * Constraints storage object of the projectmanager
 *
 * Tables: egw_pm_constraints
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class soconstraints extends so_sql
{
	/**
	 * Constructor, calls the constructor of the extended class
	 * 
	 * @param int $pm_id pm_id of the project to use, default null
	 */
	function soconstraints($pm_id=null)
	{
		$this->so_sql('projectmanager','egw_pm_constraints');

		if ((int) $pm_id) 
		{
			$this->pm_id = (int) $pm_id;
		}
	}

	/**
	 * searches db for rows matching searchcriteria, reimplemented to automatic add $this->pm_id
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys=true True returns only keys, False returns all cols
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		if (!$this->pm_id && !isset($criteria['pm_id']) && !isset($filter['pm_id']))
		{
			$filter['pm_id'] = $this->pm_id;
		}
		if (isset($criteria['pe_id']) && (int)$criteria['pe_id'])
		{
			$pe_id = (int) $criteria['pe_id'];
			unset($criteria['pe_id']);
			$criteria[] = "(pe_id_end=$pe_id OR pe_id_start=$pe_id)";
		}
		if (isset($filter['pe_id']) && (int)$filter['pe_id'])
		{
			$pe_id = (int) $filter['pe_id'];
			unset($filter['pe_id']);
			$filter[] = "(pe_id_end=$pe_id OR pe_id_start=$pe_id)";
		}
		if ($pe_id)
		{
			if ($extra_cols && !is_array($extra_cols)) $extra_cols = explode(',',$extra_cols);
			// defines 3 constrain-types: milestone, start and end
			$extra_cols[] = "CASE WHEN ms_id != 0 THEN 'milestone' WHEN pe_id_start=$pe_id THEN 'start' ELSE 'end' END AS constraint_type";
			if (!$order_by) $order_by = 'constraint_type';
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}
	
	/**
	 * reads all constraints of a milestone (ms_id given), an element (pe_id given) or a project (pm_id given)
	 *
	 * It calls allways search to retrive the data. The form of the data returned depends on the given keys!
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @return array/boolean milestones: array with pe_id's, element: array with subarrays for start, end, milestone, 
	 *	or same as search($keys) would return
	*/
	function read($keys,$extra_cols='',$join='')
	{
		if (!$search =& $this->search($keys))
		{
			return false;
		}
		$ret = array();

		if ((int) $keys['ms_id'])
		{
			foreach($search as $row)
			{
				$ret[] = $row['pe_id_end'];
			}
		}
		elseif ((int) $keys['pe_id'])
		{
			foreach($search as $row)
			{
				switch($row['constraint_type'])
				{
					case 'milestone':
						$ret['milestone'] = $row['ms_id'];
						break;
					case 'start':
						$ret['start'] = $row['pe_id_end'];
						break;
					case 'end':
						$ret['start'] = $row['pe_id_start'];
						break;
				}
			}
		}
		else
		{
			$ret =& $search;
		}
		return $ret;
	}

	/**
	 * saves the given data to the db
	 *
	 * @param array $data with either data for one row or null, or 
	 *	for the constraints of an elements the keys pe_id, start, end, milestone, or 
	 *	for the constraints of a milestone the keys ms_id, pe_id (pm_id can be given or is taken from $this->pm_id)
	 * @return int 0 on success and errno != 0 else
	 */
	function save($data=null)
	{
		if ($this->debug) { echo "<p>soconstraints::save:"; _debug_array($data); }

		// constraints of an element?
		if ($data['pe_id'])
		{
			$pm_id = $data['pm_id'] ? $data['pm_id'] : $this->pm_id;
			unset($data['pm_id']);
			$pe_id = $data['pe_id'];
			unset($data['pe_id']);

			$this->delete(array(
				'pm_id' => $pm_id,
				'pe_id' => $pe_id,
			));
			foreach($data as $type => $ids)
			{
				foreach(is_array($ids) ? $ids : explode(',',$ids) as $id)
				{
					if (!$id) continue;

					switch($type)
					{
						case 'milestone':
							$row = array(
								'pe_id_end'   => $pe_id,
								'pe_id_start' => 0,
								'ms_id'       => $id,
							);
							break;
						case 'start':
							$row = array(
								'pe_id_end'   => $id,
								'pe_id_start' => $pe_id,
								'ms_id'       => 0,
							);
							break;
						case 'end':
							$row = array(
								'pe_id_end'   => $pe_id,
								'pe_id_start' => $id,
								'ms_id'       => 0,
							);
							break;
					}
					$row['pm_id'] = $pm_id;

					if (($err = parent::save($row)))
					{
						return $err;
					}
				}
			}
			return 0;
		}
		// constraints of a milestone
		if ($data['ms_id'] && is_array($data['pe_id']))
		{
			$keys = array(
				'pm_id'       => $data['pm_id'] ? $data['pm_id'] : $this->pm_id,
				'pe_id_start' => 0,
				'ms_id'       => $data['ms_id'],
			);
			$this->delete($keys);

			foreach($data['pe_id'] as $pe_id);
			{
				$keys['pe_id_end'] = $pe_id;
				
				if (($err = parent::save($keys)))
				{
					return $err;
				}
			}
			return 0;
		}
		return parent::save($data);
	}		
		
	/**
	 * reimplented to delete all constraints from a project-element if a pe_id is given
	 *
	 * @param array/int $keys if given array with col => value pairs to characterise the rows to delete or pe_id
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		if ($this->debug) echo "<p>soconstraints::delete(".print_r($keys,true).")</p>\n";

		if (is_numeric($keys) || is_array($keys) && (int) $keys['pe_id'])
		{
			if (is_array($keys))
			{
				$pe_id = (int) $keys['pe_id'];
				unset($keys['pe_id']);
			}
			else
			{
				$pe_id = (int) $keys;
				$keys = array();
			}
			$keys[] = "(pe_id_end=$pe_id OR pe_id_start=$pe_id)";		
			return $this->db->delete($this->table_name,$keys,__LINE__,__FILE__);
		}
		return parent::delete($keys);
	}
}