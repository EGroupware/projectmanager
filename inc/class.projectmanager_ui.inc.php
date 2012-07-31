<?php
/**
 * ProjectManager - Projects user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * ProjectManager UI: list and edit projects
 */
class projectmanager_ui extends projectmanager_bo
{
	/**
	 * Functions to call via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);
	/**
	 * Labels for pm_status, value - label pairs
	 *
	 * @var array
	 */
	var $status_labels;
	/**
	 * Labels for pm_access, value - label pairs
	 *
	 * @var array
	 */
	var $access_labels;
	/**
	 * Labels for mains- & sub-projects filter
	 *
	 * @var array
	 */
	var $filter_labels;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @return projectmanager_ui
	 */
	function __construct()
	{
		parent::__construct();

		$this->status_labels = array(
			'active'    => lang('Active'),
			'nonactive' => lang('Nonactive'),
			'archive'   => lang('Archive'),
			'template'  => lang('Template'),
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
		if ((int) $this->debug >= 1 || $this->debug == 'edit') $this->debug_message("projectmanager_ui::edit(,$view) content=".print_r($content,true));

		$tpl = new etemplate('projectmanager.edit');

		if (is_array($content))
		{
			$old_status = $content['old_status'];

			if ($content['pm_id'])
			{
				$this->read($content['pm_id']);
			}
			else
			{
				$this->init();
			}
			$view = $content['view'] && !$content['edit'] || !$this->check_acl(EGW_ACL_EDIT);

			if (!$content['view'])	// just changed to edit-mode, still counts as view
			{
				//echo "content="; _debug_array($content);
				$this->data_merge($content);
				//echo "after data_merge data="; _debug_array($this->data);

				// set the data of the pe_summary, if the project-value is unset
				$pe_summary = $this->pe_summary();
				$datasource = CreateObject('projectmanager.datasource');
				foreach($datasource->name2id as $pe_name => $id)
				{
					$pm_name = str_replace('pe_','pm_',$pe_name);
					// check if update is necessary, because a field has be set or changed
					if (($content[$pm_name] || $pm_name == 'pm_completion' && $content[$pm_name] !== '') &&
						($content[$pm_name] != $this->data[$pm_name] || !($this->data['pm_overwrite'] & $id)))
					{
						//echo "$pm_name set to '".$this->data[$pm_name]."'<br>\n";
						$this->data['pm_overwrite'] |= $id;
					}
					// or check if a field is no longer set, or the datasource changed => set it from the datasource
					elseif (!$content[$pm_name] && ($pm_name != 'pm_completion' || $content[$pm_name] === '') &&
						    ($this->data['pm_overwrite'] & $id) ||
						    !($this->data['pm_overwrite'] & $id) && $this->data[$pm_name] != $pe_summary[$pe_name])
					{
						// if we have a change in the datasource, set pe_synced
						if ($this->data[$pm_name] != $pe_summary[$name])
						{
							$this->data['pm_synced'] = $this->now_su;
						}
						$this->data[$pm_name] = $pe_summary[$pe_name];
						//echo "$pm_name re-set to default '".$this->data[$pm_name]."'<br>\n";
						$this->data['pm_overwrite'] &= ~$id;
					}
				}
				//echo "after foreach(datasource->name2id...) data="; _debug_array($this->data);
				// process new and changed project-members
				foreach((array)$content['member'] as $n => $uid)
				{
					if (!(int) $content['role'][$n])
					{
						unset($this->data['pm_members'][$uid]);
					}
					elseif ((int) $uid)
					{
						$this->data['pm_members'][(int)$uid] = array(
							'member_uid' => (int) $uid,
							'member_availibility' => empty($content['availibility'][$n]) ? 100.0 : $content['availibility'][$n],
							'role_id'    => (int) $content['role'][$n],
						);
						if ($GLOBALS['egw_info']['user']['apps']['admin'] && $content['general_avail'][$n])
						{
							$this->set_availibility($uid,$content['general_avail'][$n]);
						}
					}
				}
			}
			//echo "projectmanager_ui::edit(): data="; _debug_array($this->data);

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
						egw_link::link($app,$app_id,'projectmanager',$this->data['pm_id']);
					}
					// writing links for new entry, existing ones are handled by the widget itself
					if (!$content['pm_id'] && is_array($content['link_to']['to_id']))
					{
						egw_link::link('projectmanager',$this->data['pm_id'],$content['link_to']['to_id']);
					}
					if ($content['template'] && $this->copy($content['template'],2))
					{
						$msg = lang('Template including elment-tree saved as new project');
						unset($content['template']);
					}
					if ($content['status_sources'] && $old_status != $this->data['pm_status'])
					{
						ExecMethod2('projectmanager.projectmanager_elements_bo.run_on_sources','change_status',
							array('pm_id'=>$this->data['pm_id']),$this->data['pm_status']);
					}
				}
			}
			if ($content['delete'] && $this->check_acl(EGW_ACL_DELETE))
			{
				$msg = $this->delete($pm_id,$delete_sources) ? lang('Project deleted') : lang('Error: deleting project !!!');
			}
			if ($content['save'] || $content['delete'])	// refresh opener and output message
			{
				echo "<html><body><script>var referer = opener.location;opener.location.href = referer+(referer.search?'&':'?')+'msg=".
					addslashes(urlencode($msg))."'; window.close();</script></body></html>\n";
				common::egw_exit();
			}
			$template = $content['template'];
		}
		else
		{
			if ($_GET['msg']) $msg = strip_tags($_GET['msg']);

			if ((int) $_GET['pm_id'])
			{
				$this->read((int) $_GET['pm_id']);
			}
			// for a new sub-project set some data from the parent
			elseif ($_GET['link_app'] == 'projectmanager' && (int) $_GET['link_id'] && $this->read((int) $_GET['link_id']))
			{
				if (!$this->check_acl(EGW_ACL_READ))	// no read-rights for the parent, eg. someone edited the url
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					common::egw_exit();
				}
				$this->generate_pm_number(true,$parent_number=$this->data['pm_number']);
				foreach(array('pm_id','pm_title','pm_description','pm_creator','pm_created','pm_modified','pm_modifier','pm_real_start','pm_real_end','pm_completion','pm_status','pm_used_time','pm_planned_time','pm_replanned_time','pm_used_budget','pm_planned_budget') as $key)
				{
					unset($this->data[$key]);
				}
				include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');
				$this->data['pm_overwrite'] &= PM_PLANNED_START | PM_PLANNED_END;
			}
			if((int)$_GET['template'] && $this->read((int) $_GET['template']))
			{
				if (!$this->check_acl(EGW_ACL_READ))	// no read-rights for the template, eg. someone edited the url
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					common::egw_exit();
				}
				// we do only stage 1 of the copy, so if the user hits cancel everythings Ok
				$this->copy($template = (int) $_GET['template'],1,$parent_number);
			}
			if ($this->data['pm_id'])
			{
				if (!$this->check_acl(EGW_ACL_READ))
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					common::egw_exit();
				}
				if (!$this->check_acl(EGW_ACL_EDIT)) $view = true;
			}
			// no pm-number set, generate one
			if (!$this->data['pm_number']) $this->generate_pm_number(true);

			$old_status = $this->data['pm_status'];
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
			'msg'  => $msg,
			'tabs' => $content['tabs'],
			'view' => $view,
			'ds'   => $pe_summary,
			'link_to' => array(
				'to_id' => $content['link_to']['to_id'] ? $content['link_to']['to_id'] : $this->data['pm_id'],
				'to_app' => 'projectmanager',
			),
			'duration_format' => ','.$this->config['duration_format'],
			'no_budget' => !$this->check_acl(EGW_ACL_BUDGET,0,true) || in_array($this->data['pm_accounting_type'],array('status','times')) ||
				$this->config['accounting_types'] && !array_intersect(explode(',',$this->config['accounting_types']),array('budget','pricelist')),
			'status_sources' => $content['status_sources'],
		);
		if ($add_link && !is_array($content['link_to']['to_id']))
		{
			list($app,$app_id) = explode(':',$add_link,2);
			egw_link::link('projectmanager',$content['link_to']['to_id'],$app,$app_id);
		}
		$content['links'] = $content['link_to'];

		// empty not explicitly in the project set values
		if (!is_object($datasource)) $datasource =& CreateObject('projectmanager.datasource');
		foreach($datasource->name2id as $pe_name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$pe_name);
			if (!($this->data['pm_overwrite'] & $id) && !in_array($pm_name, array('cat_id', 'pm_title')))
			{
				$content[$pm_name] = $preserv[$pm_name] = '';
			}
		}
		// check if user should inherit coordinator role from being part of a group set as coordinator member
		$memberships = $GLOBALS['egw']->accounts->memberships($this->user);
		$member_from_groups = array_intersect_key((array)$this->data['pm_members'], $memberships);
		$coord_from_groups_roles = false;
		foreach ($member_from_groups as $member_from_group => $member_acl)
		{
			if ($this->data['pm_members'][$member_from_group]['role_id'] == 1)
			{
				$coord_from_groups_roles = true;
				break;
			}
		}

		$readonlys = array(
			'delete' => !$this->data['pm_id'] || !$this->check_acl(EGW_ACL_DELETE),
			'edit' => !$view || !$this->check_acl(EGW_ACL_EDIT),
			'tabs' => array(
				'accounting' => !$this->check_acl(EGW_ACL_BUDGET) &&	// disable the tab, if no budget rights and no owner or coordinator
					($this->config['accounting_types'] && count(explode(',',$this->config['accounting_types'])) == 1 ||
					!($this->data['pm_creator'] == $this->user || $this->data['pm_members'][$this->user]['role_id'] == 1 ||
					$coord_from_groups_roles)) ||
					$this->config['accounting_types'] == 'status' || $this->config['accounting_types'] == 'times',
				'custom' => !count($this->customfields),	// only show customfields tab, if there are some
				'history' => !$this->data['pm_id'],        //suppress history for the first loading without ID
			),
			'customfields' => $view,
			'general_avail[1]' => !$GLOBALS['egw_info']['user']['apps']['admin'],
		);
		if ($readonlys['delete']) $tpl->disable_cells('delete_sources');

		if (!$this->check_acl(EGW_ACL_EDIT_BUDGET))
		{
			$readonlys['pm_planned_budget'] = $readonlys['pm_used_budget'] = true;
		}
		$n = 2;
		foreach((array)$this->data['pm_members'] as $uid => $data)
		{
			$content['role'][$n] = $data['role_id'];
			$content['member'][$n] = $data['member_uid'];
			$content['availibility'][$n] = empty($data['member_availibility']) ? 100.0 : $data['member_availibility'];
			if (!is_array($general_avail)) $general_avail = $this->get_availibility();
			$content['general_avail'][$n] = empty($general_avail[$uid]) ? 100.0 : $general_avail[$uid];
			$readonlys["general_avail[$n]"] = $view || !$GLOBALS['egw_info']['user']['apps']['admin'];
			$readonlys["role[$n]"] = $readonlys["availibility[$n]"] = $view;
			++$n;
		}
		//_debug_array($content);
		$preserv = $this->data + array(
			'view'     => $view,
			'add_link' => $add_link,
			'member'   => $content['member'],
			'template' => $template,
			'old_status' => $old_status,
		);
		$this->instanciate('roles');

		$sel_options = array(
			'pm_status' => &$this->status_labels,
			'pm_access' => &$this->access_labels,
			'role'      => $this->roles->query_list(array(
				'label' => 'role_title',
				'title' => 'role_description',
			),'role_id',array(
				'pm_id' => array(0,(int)$this->data['pm_id'])
			)),
			'pm_accounting_type' => array(
				'status' => 'No accounting, only status',
				'times'  => 'No accounting, only times and status',
				'budget' => 'Budget (no pricelist)',
				'pricelist' => 'Budget and pricelist',
			),
		);
		$content['history'] = array(
				'id'  => $this->data['pm_id'],
				'app' => 'projectmanager',
				'status-widgets' => array(
					'pm_modifier' => 'select-account',
					'cat_id' => 'select-cat',
					'pm_modified' => 'date-time',
					'pm_planned_start' => 'date-time',
					'pm_planned_end' => 'date-time',
					'pm_real_start' => 'date-time',
					'pm_real_end' => 'date-time',
				),
		);
		$sel_options['status'] = $this->field2label;

		if ($this->config['accounting_types'])	// only allow the configured types
		{
			$allowed = explode(',',$this->config['accounting_types']);
			foreach($sel_options['pm_accounting_type'] as $key => $label)
			{
				if (!in_array($key,$allowed)) unset($sel_options['pm_accounting_type'][$key]);
			}
			if (count($sel_options['pm_accounting_type']) == 1)
			{
				$readonlys['pm_accounting_type'] = true;
			}
		}
		if ($view)
		{
			foreach($this->db_cols as $name)
			{
				$readonlys[$name] = true;
			}
			$readonlys['save'] = $readonlys['apply'] = true;

			// add fields not stored in the main-table
			$readonlys['pm_members'] = $readonlys['edit_roles'] = true;

			$readonlys['links'] = $readonlys['link_to'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager') . ' - ' .
			($this->data['pm_id'] ? ($view ? lang('View project') : lang('Edit project')) : lang('Add project'));
		$tpl->exec('projectmanager.projectmanager_ui.edit',$content,$sel_options,$readonlys,$preserv,2);
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
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('project_list','projectmanager',$query=$query_in);

		//echo "<p>projectmanager_ui::get_rows(".print_r($query,true).")</p>\n";
		// save the state of the index in the user prefs
		$state = serialize(array(
			'filter'     => $query['filter'],
			'filter2'    => $query['filter2'],
			'cat_id'     => $query['cat_id'],
			'order'      => $query['order'],
			'sort'       => $query['sort'],
			));
		if ($state != $this->prefs['pm_index_state'])
		{
			$GLOBALS['egw']->preferences->add('projectmanager','pm_index_state',$state);
			// save prefs, but do NOT invalid the cache (unnecessary)
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);
		}

		// handle nextmatch filters like col_filters
		foreach(array('cat_id' => 'cat_id','filter2' => 'pm_status') as $nm_name => $pm_name)
		{
			unset($query['col_filter'][$pm_name]);
			if ($query[$nm_name]) $query['col_filter'][$pm_name] = $query[$nm_name];
		}
		$query['col_filter']['subs_or_mains'] = $query['filter'];

		$total = parent::get_rows($query,$rows,$readonlys,true,true);

		$this->instanciate('roles');

		$readonlys = array();
		foreach($rows as &$row)
		{
			if (!$this->check_acl(EGW_ACL_EDIT,$row))
			{
				$row['class'] .= ' rowNoEdit';
			}
			if (!$this->check_acl(EGW_ACL_DELETE,$row))
			{
				$readonlys["delete[$row[pm_id]]"] = true;
				$row['class'] .= ' rowNoDelete';
			}
			$pm_ids[] = $row['pm_id'];

			if (!$this->check_acl(EGW_ACL_BUDGET,$row))
			{
				unset($row['pm_used_budget']);
				unset($row['pm_planned_budget']);
			}
		}
		$roles = $this->roles->query_list();
		// query the project-members only, if user choose to display them
		if ($pm_ids && @strstr($GLOBALS['egw_info']['user']['preferences']['projectmanager']['nextmatch-projectmanager.list.rows'],',role') !== false)
		{
			$all_members = $this->read_members($pm_ids);
			foreach($rows as $n => $val)
			{
				$row =& $rows[$n];
				$members = $row['pm_members'] = $all_members[$row['pm_id']];
				if (!$members) continue;

				foreach($members as $uid => $data)
				{
					if (($pos = array_search($data['role_id'],array_keys($roles))) !== false)
					{
						$row['role'.$pos][] = $uid;
					}
				}
			}
		}
		//_debug_array($rows);
		if ((int) $this->debug >= 2 || $this->debug == 'get_rows')
		{
			$this->debug_message("projectmanager_ui::get_rows(".print_r($query,true).") total=$total, rows =".print_r($rows,true)."\nreadonlys=".print_r($readonlys,true));
		}
		$rows['roles'] = array_values($roles);
		for($i = count($roles); $i < 5; ++$i)
		{
			$rows['no_role'.$i] = true;
		}
		// disable time & budget columns if pm is configures for status or status and time only
		if ($this->config['accounting_types'] == 'status')
		{
			$rows['no_pm_used_time_pm_planned_time'] = $rows['no_pm_used_time_pm_planned_time_pm_replanned_time'] = true;
			$rows['no_pm_used_budget_pm_planned_budget'] = true;
			$query_in['options-selectcols']['pm_used_time'] = $query_in['options-selectcols']['pm_planned_time'] = $query_in['options-selectcols']['pm_replanned_time'] = false;
			$query_in['options-selectcols']['pm_used_budget'] = $query_in['options-selectcols']['pm_planned_budget'] = false;
		}
		if ($this->config['accounting_types'] == 'status,times')
		{
			$rows['no_pm_used_budget_pm_planned_budget'] = true;
			$query_in['options-selectcols']['pm_used_budget'] = $query_in['options-selectcols']['pm_planned_budget'] = false;
		}
		$rows['duration_format'] = ','.$this->config['duration_format'].',,1';

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
		if ((int) $this->debug >= 1 || $this->debug == 'index') $this->debug_message("projectmanager_ui::index(,$msg) content=".print_r($content,true));

		$tpl = new etemplate('projectmanager.list');

		if ($_GET['msg']) $msg = $_GET['msg'];

		if ($content['nm']['action'])
		{
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
			}
			else
			{
				if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,'project_list',$msg,$content['nm']['checkboxes']['sources_too']))
				{
					$msg .= lang('%1 project(s) %2',$success,$action_msg);
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 project(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
			}
		}
		$delete_sources = $content['delete_sources'];
		$content = $content['nm']['rows'];

		if ($content['delete'] || $content['ganttchart'])
		{
			foreach(array('delete','ganttchart') as $action)
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
				case 'ganttchart':
					$tpl->location(array(
						'menuaction' => 'projectmanager.projectmanager_ganttchart.show',
						'pm_id'      => $pm_id,
					));
					break;

				case 'delete':
					if (!$this->read($pm_id) || !$this->check_acl(EGW_ACL_DELETE))
					{
						$msg = lang('Permission denied !!!');
					}
					else
					{
						$msg = $this->delete($pm_id,$delete_sources) ? lang('Project deleted') :
							lang('Error: deleting project !!!');
					}
					break;
			}
		}
		$content = array(
			'nm' => $GLOBALS['egw']->session->appsession('project_list','projectmanager'),
			'msg' => $msg,
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.projectmanager_ui.get_rows',
				'filter2'        => 'active',// I initial value for the filter
				'options-filter2'=> $this->status_labels,
				'filter2_no_lang'=> True,// I  set no_lang for filter (=dont translate the options)
				'filter'         => 'mains',
				'filter_label'   => lang('Filter'),// I  label for filter    (optional)
				'options-filter' => $this->filter_labels,
				'filter_no_lang' => True,// I  set no_lang for filter (=dont translate the options)
				'order'          =>	'pm_modified',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'   => '!role0,role1,role2,role3,role4,pm_used_time_pm_planned_time_pm_replanned_time,legacy_actions,cat_id',
				'csv_fields'     => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['nextmatch-export-definition-project'],
				'header_right'   => 'projectmanager.list.right',
				'row_id'         => 'pm_id',
				'actions'        => $this->get_actions(),
			);
			// use the state of the last session stored in the user prefs
			if ($state = @unserialize($this->prefs['pm_index_state']))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}
		if($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}
		$sel_options = array(
			'template' => $this->get_templates(),
		);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Projectlist');
		$tpl->exec('projectmanager.projectmanager_ui.index',$content,$sel_options);
	}

	/**
	 * Get list of templates
	 *
	 * @param string $label='label' key for label
	 * @param string $title='title' key for title
	 * @return array of array with keys $label and $title
	 */
	private function get_templates($label='label', $title='title')
	{
		static $templates;	// cache result within request
		if (!isset($templates))
		{
			$templates = array();
			list(,,$show) = explode('_',$this->prefs['show_projectselection']);
			foreach((array)$this->search(array(
				'pm_status' => 'template',
			),$this->table_name.'.pm_id AS pm_id,pm_number,pm_title','pm_number','','',False,'OR') as $template)
			{
				$templates[$template['pm_id']] = array(
					$label => $show == 'number' ? $template['pm_number'] : $template['pm_title'],
					$title => $show == 'number' ? $template['pm_title'] : $template['pm_number'],
				);
			}
		}
		return $templates;
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private function get_actions()
	{
		$actions = array(
			'view' => array(
				'caption' => 'Elementlist',
				'default' => true,
				'allowOnMultiple' => false,
				'egw_open' => 'view-projectmanager',
				'target' => '_self',
				'group' => $group=1,
				'default' => true,
			),
			'open' => array(	// does edit if allowed, otherwise view
				'caption' => 'Open',
				'allowOnMultiple' => false,
				'egw_open' => 'edit-projectmanager',
				'group' => $group,
			),
			'add' => array(
				'caption' => 'Add',
				'group' => $group,
				'children' => array(
					'new' => array(
						'caption' => 'Empty',
						'egw_open' => 'add-projectmanager',
					),
					'copy' => array(
						'caption' => 'Copy',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&template=$id',
						'popup' => egw_link::get_registry('projectmanager', 'add_popup'),
					),
					'template' => array(
						'caption' => 'Template',
						'icon' => 'move',
						'children' => $this->get_templates('caption','hint'),
						// get inherited by children
						'prefix' => 'template_',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&template=$action',
						'popup' => egw_link::get_registry('projectmanager', 'add_popup'),
					),
					'sub' => array(
						'caption' => 'Subproject',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&link_app=projectmanager&link_id=$id',
						'popup' => egw_link::get_registry('projectmanager', 'add_popup'),
						'icon' => 'navbar',
					),
					'templatesub' => array(
						'caption' => 'Template as subproject',
						'icon' => 'move',
						'children' => $this->get_templates('caption','hint'),
						// get inherited by children
						'prefix' => 'templatesub_',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&template=$action&link_app=projectmanager&link_id=$id',
						'popup' => egw_link::get_registry('projectmanager', 'add_popup'),
					),
				),
			),
			'ganttchart' => array(
				'icon' => 'projectmanager/navbar',
				'caption' => 'Ganttchart',
				'url' => 'menuaction=projectmanager.projectmanager_ganttchart.show&pm_id=$id',
				'group' => ++$group,
			),
			'pricelist' => array(
				'icon' => 'pricelist',
				'caption' => 'Pricelist',
				'url' => 'menuaction=projectmanager.projectmanager_pricelist_ui.index&pm_id=$id',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'filemanager' => array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/projectmanager/$id',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'select_all' => array(
				'caption' => 'Whole query',
				'checkbox' => true,
				'hint' => 'Apply the action on the whole query, NOT only the shown entries',
				'group' => ++$group,
			),
			'documents' => projectmanager_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['projectmanager']['document_dir'],
				$group, 'Insert in document', 'document_'
			),
			'cat' => nextmatch_widget::category_action(
				'projectmanager',$group,'Change category','cat_'
			)+array(
				'disableClass' => 'rowNoEdit',
			),
			'sources_too' => array(
				'caption' => 'Datasources too',
				'checkbox' => true,
				'hint' => 'If checked the datasources of the elements (eg. InfoLog entries) will change their status too.',
				'group' => ++$group,
			),
			'status' => array(
				'icon' => 'apply',
				'caption' => 'Modify status',
				'group' => $group,
				'children' => $this->status_labels,
				'prefix' => 'status_',
				'disableClass' => 'rowNoEdit',
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this project',
				'confirm_multiple' => 'Delete these entries',
				'group' => $group,
				'disableClass' => 'rowNoDelete',
			),
		);

		if (!$GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			unset($actions['filemanager']);
		}
		// show pricelist only if we use pricelists
		if ($this->config['accounting_types'] && !in_array('pricelist',explode(',',$this->config['accounting_types'])))
		{
			unset($actions['pricelist']);
		}
		//_debug_array($actions);
		return $actions;
	}

	/**
	 * apply an action to multiple projects
	 *
	 * @param string/int $action Action to take
	 * @param array $checked project id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 entries 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @param string &$msg
	 * @param booelan $sources_too=false should delete or status be changed in resources too
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg,$sources_too=false)
	{
		//echo "<p>projects_ui::action('$action',".print_r($checked,true).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all)
		{
			// get the whole selection
			$old_query = $GLOBALS['egw']->session->appsession('project_list','projectmanager');
			$query = is_array($session_name) ? $session_name : $GLOBALS['egw']->session->appsession($session_name,'projectmanager');

			@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;	// all
			$this->get_rows($query,$projects,$readonlys);
			// only use the ids
			foreach($projects as $project)
			{
				if($project['pm_id'] && is_numeric($project['pm_id'])) $checked[] = $project['pm_id'];
			}
			// Reset query
			$GLOBALS['egw']->session->appsession('project_list','projectmanager',$old_query);
		}

		// Dialogs to get options
		list($action, $settings) = explode('_', $action, 2);

		switch($action)
		{
			case 'gantt':
				egw::redirect_link('/index.php', array(
					'menuaction' => 'projectmanager.projectmanager_ganttchart.show',
					'pm_id'      => implode(',',$checked),
				));
				break;
			case 'delete':
				$action_msg = lang('deleted');
				foreach($checked as $pm_id)
				{
					if (!$this->read($pm_id) || !$this->check_acl(EGW_ACL_DELETE))
					{
						$failed++;
					}
					elseif ($this->delete($pm_id,$settings||$sources_too))
					{
						$success++;
					}
				}
				break;
			case 'cat':
			case 'status':
				$action_msg = $action == 'cat' ? lang('category set') : lang('status set');
				foreach($checked as $pm_id)
				{
					if (!$this->read($pm_id) || !$this->check_acl(EGW_ACL_EDIT))
					{
						$failed++;
					}
					else
					{
						$old_status = $this->data['pm_status'];
						$this->data[$action == 'cat' ? 'cat_id' : 'pm_status'] = $settings;
						if (!$this->save())
						{
							if ($action == 'status' && $sources_too && $old_status == $this->data['pm_status'])
							{
								ExecMethod2('projectmanager.projectmanager_elements_bo.run_on_sources','change_status',
									array('pm_id'=>$this->data['pm_id']),$this->data['pm_status']);
							}
							$success++;
						}
					}
				}
				break;
			case 'document':
				if (!$settings) $settings = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['default_document'];
				$document_merge = new projectmanager_merge();
				$msg = $document_merge->download($settings, $checked, '', $GLOBALS['egw_info']['user']['preferences']['projectmanager']['document_dir']);
				$failed = count($checked);
				return false;
		}

		return !$failed;
	}
}
