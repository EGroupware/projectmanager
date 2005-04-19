<?php
/**************************************************************************\
* eGroupWare - ProjectManager - UI list and edit projects                  *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.boprojectmanager.inc.php');
include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

/**
 * ProjectManage UI: list and edit projects
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uiprojectmanager extends boprojectmanager 
{
	/**
	 * @var array $public_functions Functions to call via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);
	/**
	 * @var array $status_labels for pm_status, value - label pairs
	 */
	var $status_labels;
	/**
	 * @var array $access_labels for pm_access, value - label pairs
	 */
	var $access_labels;
	/**
	 * @var array $filter_labels for mains- & sub-projects
	 */
	var $filter_labels;

	/**
	 * Constructor, calls the constructor of the extended class
	 */
	function uiprojectmanager()
	{
		$this->boprojectmanager();
		
		$this->status_labels = array(
			'active'    => lang('Active'),
			'nonactive' => lang('Nonactive'),
			'archive'   => lang('Archive'),
		);
		$this->access_labels = array(
			'public'    => lang('Public'),
			'anonym'    => lang('Anonymous public'),
			'private'   => lang('Private'),
		);
		$this->filter_labels = array(
			''			=> lang('All'),
			'mains'		=> lang('Mainprojects'),
			'subs'		=> lang('Subprojects'),
		);
	}
	
	
	/**
	 * View a project
	 */
	function view()
	{
		$this->edit(null,true);
	}
	
	/**
	 * Edit, add or view a project
	 *
	 * @var array $content content-array if called by process-exec
	 * @var boolean $view only view project, default false, only used on first call !is_array($content)
	 */
	function edit($content=null,$view=false)
	{
		$tpl =& new etemplate('projectmanager.edit');

		if (is_array($content))
		{
			if ($content['cancel'])
			{
				$tpl->location(array(
					'menuaction' => 'projectmanager.uiprojectmanager.index',
				));
			}
			if ($content['pm_id'])
			{
				$this->read($content['pm_id']);
			}
			else
			{
				$this->init();
			}
			$view = $content['view'] && !($content['edit'] && $this->check_acl(EGW_ACL_EDIT));
			
			if (!$view)
			{
				//_debug_array($content);
				$this->data_merge($content);
				//_debug_array($this->data);

				// set the data of the pe_summary, if the project-value is unset
				$pe_summary = $this->pe_summary();
				$datasource =& CreateObject('projectmanager.datasource'); 
				foreach($datasource->name2id as $pe_name => $id)
				{
					$pm_name = str_replace('pe_','pm_',$pe_name);
					// check if update is necessary, because a field has be set or changed
					if (($content[$pm_name] || $pm_name == 'pm_completion' && $content[$pm_name] !== '') &&
						($content[$pm_name] != $this->data[$pm_name] || !($this->data['pm_overwrite'] & $id)))
					{
						$this->data['pm_overwrite'] |= $id;
					}
					// or check if a field is no longer set, or the datasource changed => set it from the datasource
					elseif ((!$content[$pm_name] || $pm_name == 'pm_completion' && $content[$pm_name] === '') &&
						    ($this->data['pm_overwrite'] & $id) || $this->data[$pm_name] != $pe_summary[$pe_name])
					{
						// if we have a change in the datasource, set pe_synced
						if ($this->data[$pm_name] != $pe_summary[$name])
						{
							$this->data['pm_synced'] = $this->now_su;
						}
						$this->data[$pm_name] = $pe_summary[$pe_name];
						$this->data['pm_overwrite'] &= ~$id;
					}
				}
			}
			//echo "uiprojectmanager::edit(): data="; _debug_array($this->data);

			if (($content['save'] || $content['apply']) && $this->check_acl(EGW_ACL_EDIT))
			{
				// generate project-number, taking into account a given parent-project
				if (empty($this->data['pm_number'])) 
				{
					$parent_number = '';
					if ($content['add_link'])
					{
						list($app,$app_id) = explode(':',$content['add_link'],2);
						if ($app == 'projectmanager' && ($parent = $this->search(array('pm_id'=>$app_id),'pm_number')))
						{
							$parent_number = $parent[0]['pm_number'];
						}
					}						
					$this->generate_pm_number(true,$parent_number);
				}				
				if ($this->not_unique())
				{
					$msg = lang('Error: project-ID already exist, choose an other one or have one generated by leaving it emtpy !!!');
					unset($content['save']);	// dont exit
				}
				elseif ($this->save() != 0)
				{
					$msg = lang('Error: saving the project (%1) !!!',$this->db->Error);
					unset($content['save']);	// dont exit
				}
				else
				{
					$msg = lang('Project saved');

					// create project already linked to a parent, in that case param of link-call need to be swaped
					// as we want the new project to be the sub of the given project
					if ($content['add_link'])
					{
						list($app,$app_id) = explode(':',$content['add_link'],2);
						$this->link->link($app,$app_id,'projectmanager',$this->data['pm_id']);
					}
					// writing links for new entry, existing ones are handled by the widget itself
					if (!$content['pm_id'] && is_array($content['link_to']['to_id']))	
					{
						$this->link->link('projectmanager',$this->data['pm_id'],$content['link_to']['to_id']);
					}
				}
			}
			if ($content['save'] || $content['cancel'])
			{
				$tpl->location(array(
					'menuaction' => 'projectmanager.uiprojectmanager.index',
					'msg'        => $msg,
				));
			}
			if ($content['delete'] && $this->check_acl(EGW_ACL_DELETE))
			{
				// all delete are done by index
				return $this->index(array('nm'=>array('rows'=>array(
					'delete' => array($this->data['pm_id']=>true)
				))));
			}
		}
		else
		{
			if ((int) $_GET['pm_id'])
			{
				$this->read((int) $_GET['pm_id']);
			}
			if ($this->data['pm_id'])
			{
				if (!$this->check_acl(EGW_ACL_READ))
				{
					$tpl->location(array(
						'menuaction' => 'projectmanager.uiprojectmanager.index',
						'msg' => lang('Permission denied !!!'),
					));
				}
				if (!$this->check_acl(EGW_ACL_EDIT)) $view = true;
			}
		}
		if (!$pe_summary) $pe_summary = $this->pe_summary();

		if (!isset($content['add_link']) && !$this->data->pm_id && isset($_GET['link_app']) && isset($_GET['link_id']) &&
			preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$_GET['link_app'].':'.$_GET['link_id']))	// gard against XSS
		{
			$add_link = $_GET['link_app'].':'.$_GET['link_id'];
		}
		else
		{
			$add_link = $content['add_link'];
		}
		$content = $this->data + array(
			'msg' => $msg,
			'ds'  => $pe_summary,
			'link_to' => array(
				'to_id' => $content['link_to']['to_id'] ? $content['link_to']['to_id'] : $this->data['pm_id'],
				'to_app' => 'projectmanager',
		));
		if ($add_link && !is_array($content['link_to']['to_id']))
		{
			list($app,$app_id) = explode(':',$add_link,2);
			$this->link->link('projectmanager',$content['link_to']['to_id'],$app,$app_id);
		}
		$content['links'] = $content['link_to'];

		$preserv = $this->data + array(
			'view' => $view,
			'add_link' => $add_link,
		);
		// empty not explicitly in the project set values
		if (!is_object($datasource)) $datasource =& CreateObject('projectmanager.datasource'); 
		foreach($datasource->name2id as $pe_name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$pe_name);
			if (!($this->data['pm_overwrite'] & $id))
			{
				$content[$pm_name] = $preserv[$pm_name] = '';
			}
		}
		//_debug_array($content);
		$sel_options = array(
			'pm_status' => &$this->status_labels,
			'pm_access' => &$this->access_labels,
		);
		$readonlys = array(
			'delete' => !$this->data['pm_id'] || !$this->check_acl(EGW_ACL_DELETE),
			'edit' => !$view || !$this->check_acl(EGW_ACL_EDIT),
		);
		if ($view)
		{
			foreach($this->db_cols as $name)
			{
				$readonlys[$name] = true;
			}
			$readonlys['save'] = $readonlys['apply'] = true;

			// add fields not stored in the main-table
			$readonlys['pm_members'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager') . ' - ' . 
			($this->data['pm_id'] ? ($view ? lang('View project') : lang('Edit project')) : lang('Add project'));
		$tpl->exec('projectmanager.uiprojectmanager.edit',$content,$sel_options,$readonlys,$preserv);		
	}

	/**
	 * query projects for nextmatch in the projects-list
	 *
	 * reimplemented from so_sql to disable action-buttons based on the acl and make some modification on the data
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['phpgw']->session->appsession('project_list','projectmanager',$query);

		// handle nextmatch filters like col_filters
		foreach(array('cat_id' => 'cat_id','filter2' => 'pm_status') as $nm_name => $pm_name)
		{
			unset($query['col_filter'][$pm_name]);
			if ($query[$nm_name]) $query['col_filter'][$pm_name] = $query[$nm_name];
		}
		$query['col_filter']['subs_or_mains'] = $query['filter'];

		$total = parent::get_rows($query,$rows,$readonlys);
		
		$readonlys = array();
		foreach($rows as $n => $val)
		{
			$row =& $rows[$n];
			if (!$this->check_acl(EGW_ACL_EDIT,$row))
			{
				$readonlys["edit[$row[pm_id]]"] = true;
			}
			if (!$this->check_acl(EGW_ACL_DELETE,$row))
			{
				$readonlys["delete[$row[pm_id]]"] = true;
			}
		}
		if ($this->debug)
		{
			echo "<p>uiprojectmanager::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;		
	}

	/**
	 * List existing projects
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='')
	{
		$tpl =& new etemplate('projectmanager.list');

		if ($_GET['msg']) $msg = $_GET['msg'];

		if ($content['add'])
		{
			$tpl->location(array(
				'menuaction' => 'projectmanager.uiprojectmanager.edit',
			));
		}
		$content = $content['nm']['rows'];
		
		if ($content['view'] || $content['edit'] || $content['delete'])
		{
			foreach(array('view','edit','delete') as $action)
			{
				if ($content[$action])
				{
					list($pm_id) = each($content[$action]);
					break;
				}
			}
			//echo "<p>uiprojectmanger::index() action='$action', pm_id='$pm_id'</p>\n";
			switch($action)
			{
				case 'view':
				case 'edit':
					$tpl->location(array(
						'menuaction' => 'projectmanager.uiprojectmanager.'.$action,
						'pm_id'      => $pm_id,
					));
					break;
					
				case 'delete':
					if ($this->read($pm_id) && !$this->check_acl(EGW_ACL_DELETE))
					{
						$msg = lang('Permission denied !!!');
					}
					else
					{
						$msg = $this->delete($pm_id) ? lang('Project deleted') : 
							lang('Error: deleting project !!!');
					}
					break;
			}						
		}
		$content = array(
			'nm' => $GLOBALS['phpgw']->session->appsession('project_list','projectmanager'),
			'msg' => $msg,
		);		
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.uiprojectmanager.get_rows',
				'filter2'        => 'active',// I initial value for the filter
//				'filter2_label'  => lang('State'),// I  label for filter    (optional)
				'options-filter2'=> $this->status_labels,
				'filter2_no_lang'=> True,// I  set no_lang for filter (=dont translate the options)
				'filter'         => 'mains',
				'filter_label'   => lang('Filter'),// I  label for filter    (optional)
				'options-filter' => $this->filter_labels,
				'filter_no_lang' => True,// I  set no_lang for filter (=dont translate the options)
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'pm_modified',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
			);
		}

		$GLOBALS['phpgw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Projectlist');
		$tpl->exec('projectmanager.uiprojectmanager.index',$content,array(
		));
	}
}