<?php
/**************************************************************************\
* eGroupWare - ProjectManager - General storage object                     *
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
 * General storage object of the projectmanager: access the main project data
 *
 * Tables: egw_pm_projects, egw_pm_extra
 *
 * A project P is the parent of an other project C, if link_id1=P.pm_id and link_id2=C.pm_id !
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class soprojectmanager extends so_sql
{
	/**
	 * @var string $links_table table name 'phpgw_links', might change to egw_links in future
	 */
	var $links_table = 'phpgw_links';
	/**
	 * @var array $config configuration data
	 */
	var $config = array(
		'customfields' => array(),
	);
	/**
	 * @var string $extra_table name of customefields table
	 */
	var $extra_table = 'egw_pm_extra';

	/**
	 * Constructor, class the constructor of the extended class
	 * 
	 * @param int $pm_id id of the project to load, default null
	 */
	function soprojectmanager($pm_id=null)
	{
		$this->so_sql('projectmanager','egw_pm_projects');
		
		$config =& CreateObject('phpgwapi.config','projectmanager');
		$this->config =& $config->data;
		unset($config);
		$this->customfields =& $this->config['customfields'];
		
		if ($pm_id) $this->read($pm_id);
	}
	
	/**
	 * reads a project
	 *
	 * reimplemented to handle custom fields
	 */
	function read($keys)
	{
		if (!parent::read($keys))
		{
			return false;
		}
		if ($this->customfields)
		{
			$this->db->select($this->extra_table,'*',array('pm_id' => $this->data['pm_id']),__LINE__,__FILE__);

			while (($row = $this->db->row(true)))
			{
				$this->data['extra_'.$row['pm_extra_name']] = $row['pm_extra_value'];
			}
		}
		return $this->data;
	}
	
	/**
	 * saves a project
	 *
	 * reimplemented to handle custom fields and set modification and creation data
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param boolean $touch_modified=true should modification date+user be set, default yes
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true)
	{
		//echo "soprojectmanager::save(".print_r($keys,true).") this->data="; _debug_array($this->data);
		
		if (is_array($keys) && count($keys))
		{
			$this->data_merge($keys);
			$keys = null;
		}
		// set creation and modification data
		if (!$this->data['pm_id'])
		{
			$this->data['pm_creator'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['pm_created'] = time();
		}
		if ($touch_modified)
		{
			$this->data['pm_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['pm_modified'] = time();
		}
		if (parent::save($keys) && $this->data['pm_id'] && $this->customfields)
		{
			$this->db->delete($this->extra_table,array('pm_id' => $this->data['pm_id']),__LINE__,__FILE__);
			
			foreach($this->customfields as $name => $data)
			{
				if ($name && isset($this->data['extra_'.$name]) && !empty($this->data['extra_'.$name]))
				{
					$this->db->insert($this->extra_table,array(
						'pm_id' => $this->data['pm_id'],
						'pm_extra_name' => $name,
						'pm_extra_value' => $this->data['extra_'.$name],
					),false,__LINE__,__FILE__);
				}
			}
		}
		return $this->db->Errno;
	}

	/**
	 * search projects, re-implemented to include sub-cats and allow to filter for subs and mains
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
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		// include sub-categories in the search
		if ($filter['cat_id'])
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories');
			}
			$filter['cat_id'] = $GLOBALS['egw']->categories->return_all_children($filter['cat_id']);
		}
		if ($filter['subs_or_mains'])
		{
			$ids = "SELECT link_id2 FROM phpgw_links WHERE link_app2='projectmanager' AND link_app1='projectmanager'";
			if (!$this->db->capabilities['sub_queries'])
			{
				$this->db->query($ids,__LINE__,__FILE__);
				$ids = array();
				while($this->db->next_record())
				{
					$ids[] = $this->db->f(0);
				}
				$ids = count($ids) ? implode(',',$ids) : 0;
			}
			$filter[] = 'pm_id '.($filter['subs_or_mains'] == 'mains' ? 'NOT ' : '').'IN ('.$ids.')';
		}
		unset($filter['subs_or_mains']);

		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$extra);
	}
}