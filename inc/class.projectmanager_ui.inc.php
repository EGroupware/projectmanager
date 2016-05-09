<?php
/**
 * ProjectManager - Projects user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

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
		'list'	=> true,
		'edit'  => true,
		'view'  => true,
	);
	/**
	 * Labels for pm_status, value - label pairs
	 *
	 * @var array
	 */
	static $status_labels;
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

		static::$status_labels = array(
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
	 * Set up all project templates so the user can quickly switch between them,
	 * with no reload needed
	 */
	public function index(array $content = null)
	{
		// Check ACL first, no read access will trigger redirects
		if ((int) $_REQUEST['pm_id'])
		{
			$pm_id = (int) $_REQUEST['pm_id'];
			// store the current project (only for index, as popups may be called by other parent-projects)
		}
		else if ($_GET['pm_id'])
		{
			// AJAX requests have pm_id only in GET, not REQUEST
			$pm_id = (int)$_GET['pm_id'];
		}
		else
		{
			$pm_id = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'];
		}
		if(!$this->check_acl(Acl::READ,$pm_id))
		{
			$pm_id = $_GET['pm_id'] = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = 0;

		}
		if($this->check_acl(Acl::READ, $pm_id))
		{
			$this->pm_list();

			$element_list = new projectmanager_elements_ui();
			$element_list->index();

			$gantt = new projectmanager_gantt();
			$gantt->chart();

			$prices = new projectmanager_pricelist_ui();
			$prices->index();
		}
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

		$tpl = new Etemplate('projectmanager.edit');

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
			$view = $content['view'] && !$content['edit'] || !$this->check_acl(Acl::EDIT);

			if (!$content['view'])	// just changed to edit-mode, still counts as view
			{
				//echo "content="; _debug_array($content);
				$this->data_merge($content);
				//error_log("after data_merge data="); error_log(array2string($this->data));

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
						//error_log( "$pm_name set to '".$this->data[$pm_name]);
						$this->data['pm_overwrite'] |= $id;
					}
					// or check if a field is no longer set, or the datasource changed => set it from the datasource
					elseif (!$content[$pm_name] && ($pm_name != 'pm_completion' || $content[$pm_name] === '') &&
						    ($this->data['pm_overwrite'] & $id) ||
						    !($this->data['pm_overwrite'] & $id) && $this->data[$pm_name] != $pe_summary[$pe_name])
					{
						// if we have a change in the datasource, set pe_synced
						if ($this->data[$pm_name] != $pe_summary[$pe_name])
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

			if (($content['save'] || $content['apply']) && $this->check_acl(Acl::EDIT))
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
						Link::link($app,$app_id,'projectmanager',$this->data['pm_id']);
					}
					// writing links for new entry, existing ones are handled by the widget itself
					if (!$content['pm_id'] && is_array($content['link_to']['to_id']))
					{
						Link::link('projectmanager',$this->data['pm_id'],$content['link_to']['to_id']);

						// check if we have dragged in images and fix their image urls
						if (Etemplate\Widget\Vfs::fix_html_dragins('projectmanager', $this->data['pm_id'],
							$content['link_to']['to_id'], $content['pm_description']))
						{
							Api\Storage\Base::update(array(
								'pm_description' => $content['pm_description'],
							));
						}
					}
					if ($content['template'] && $new_id = $this->copy($content['template'],2))
					{
						$msg = lang('Template including elment-tree saved as new project');
						$content['pm_id'] = $new_id;
						unset($content['template']);
					}
					if ($content['status_sources'] && $old_status != $this->data['pm_status'])
					{
						ExecMethod2('projectmanager.projectmanager_elements_bo.run_on_sources','change_status',
							array('pm_id'=>$this->data['pm_id']),$this->data['pm_status']);
					}
				}
				if ($content['apply']) Framework::refresh_opener($msg, 'projectmanager', $this->data['pm_id'], 'edit');
			}
			if ($content['delete'] && $this->check_acl(Acl::DELETE))
			{
				$msg = $this->delete($pm_id,$delete_sources) ? lang('Project deleted') : lang('Error: deleting project !!!');
			}
			if ($content['save'] || $content['delete'])	// refresh opener and output message
			{
				Framework::refresh_opener($msg,'projectmanager', $this->data['pm_id'], $content['save']?'edit':'delete');
				Framework::window_close();
				exit();
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
				if (!$this->check_acl(Acl::READ))	// no read-rights for the parent, eg. someone edited the url
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					exit();
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
				if (!$this->check_acl(Acl::READ))	// no read-rights for the template, eg. someone edited the url
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					exit();
				}
				// we do only stage 1 of the copy, so if the user hits cancel everythings Ok
				$this->copy($template = (int) $_GET['template'],1,$parent_number);
			}
			if ($this->data['pm_id'])
			{
				if (!$this->check_acl(Acl::READ))
				{
					$GLOBALS['egw']->framework->render(lang('Permission denied !!!'));
					exit();
				}
				if (!$this->check_acl(Acl::EDIT)) $view = true;
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
			'no_budget' => !$this->check_acl(EGW_ACL_BUDGET,0,true) || !$this->data['pm_accounting_type'] || in_array($this->data['pm_accounting_type'],array('status','times')) ||
				$this->config['accounting_types'] && !array_intersect(!is_array($this->config['accounting_types']) ? explode(',',$this->config['accounting_types']) : $this->config['accounting_types'],array('budget','pricelist')),
			'status_sources' => $content['status_sources'],
		);
		if ($add_link && !is_array($content['link_to']['to_id']))
		{
			list($app,$app_id) = explode(':',$add_link,2);
			Link::link('projectmanager',$content['link_to']['to_id'],$app,$app_id);
		}
		$content['links'] = $content['link_to'];

		$preserv = $this->data;
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

		if(!is_array($this->config['accounting_types']))
		{
			$this->config['accounting_types'] = explode(',',$this->config['accounting_types']);
		}
		$readonlys = array(
			'delete' => !$this->data['pm_id'] || !$this->check_acl(Acl::DELETE),
			'edit' => !$view || !$this->check_acl(Acl::EDIT),
			'tabs' => array(
				'accounting' => !$this->check_acl(EGW_ACL_BUDGET) &&	// disable the tab, if no budget rights and no owner or coordinator
					($this->config['accounting_types'] && count($this->config['accounting_types']) == 1 ||
					!($this->data['pm_creator'] == $this->user || $this->data['pm_members'][$this->user]['role_id'] == 1 ||
					$coord_from_groups_roles)) ||
					$this->config['accounting_types'] == array('status') || $this->config['accounting_types'] == array('times'),
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
			unset($content['pm_planned_budget']);
			unset($content['pm_used_budget']);
			unset($content['ds']['pe_planned_budget']);
			unset($content['ds']['pe_used_budget']);
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
		$preserv += array(
			'view'     => $view,
			'add_link' => $add_link,
			'member'   => $content['member'],
			'template' => $template,
			'old_status' => $old_status,
		);
		$this->instanciate('roles');

		$sel_options = array(
			'pm_status' => &self::$status_labels,
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
			$allowed = $this->config['accounting_types'];
			if(!is_array($allowed))
			{
				$allowed = explode(',',$allowed);
			}
			foreach($sel_options['pm_accounting_type'] as $key => $label)
			{
				if (!in_array($key,$allowed)) unset($sel_options['pm_accounting_type'][$key]);
			}
			if (count($sel_options['pm_accounting_type']) == 1)
			{
				if(!$content['pm_accounting_type'])
				{
					reset($sel_options['pm_accounting_type']);
					$content['pm_accounting_type'] = $preserv['pm_accounting_type'] =
						key($sel_options['pm_accounting_type']);
				}
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
	 * reimplemented from Api\Storage\Base to disable action-buttons based on the Acl and make some modification on the data
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		// for unknown reason, order is sometimes set to an element column, eg. pe_modified
		// need to fix that, as it gives a sql error otherwise
		if (substr($query_in['order'], 0, 3) === 'pe_')
		{
			$query_in['order'] = 'pm_'.substr($query_in['order'], 3);
		}
		if (!$this->db->get_column_attribute($query_in['order'], $this->table_name,'projectmanager'))
		{
			$query_in['order'] = 'pm_modified';
		}

		$query = $query_in;
		// Don't keep pm_id filter in seesion
		unset($query_in['col_filter']['pm_id']);
		Api\Cache::setSession('projectmanager', 'project_list', $query_in);

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
		// Sub-projects
		if($query['col_filter']['pm_id'])
		{
			$query['col_filter']['subs_or_mains'] = $query['col_filter']['pm_id'];
		}
		unset($query['col_filter']['pm_id']);

		$total = parent::get_rows($query,$rows,$readonlys,'',true, false, array('children'));

		$readonlys = array();
		foreach($rows as &$row)
		{
			// Hide as much as possible for users without read access, but still have other permissions
			if(!$this->check_acl(Acl::READ,$row['pm_id']))
			{
				foreach($row as $key => &$value)
				{
					if(!in_array($key, array('pm_id','pm_number','pm_title'))) $value = '';
				}
			}
			if (!$this->check_acl(Acl::EDIT,$row))
			{
				$row['class'] .= ' rowNoEdit';
			}
			if (!$this->check_acl(Acl::DELETE,$row))
			{
				$row['class'] .= ' rowNoDelete';
			}
			$pm_ids[] = $row['pm_id'];

			if (!$this->check_acl(EGW_ACL_BUDGET,$row))
			{
				unset($row['pm_used_budget']);
				unset($row['pm_planned_budget']);
			}
		}

		//Roles
		// query the project-members only, if user choose to display them
		if ($pm_ids && (@strstr($GLOBALS['egw_info']['user']['preferences']['projectmanager']['nextmatch-projectmanager.list.rows'],',role') !== false ||
			// Current value, if user just changed column selection
			@strstr(implode(',', $query['selectcols']),',role') !== false ))
		{
			$this->instanciate('roles');
			$roles = $this->roles->query_list();

			$all_members = $this->read_members($pm_ids);
			foreach($rows as &$row)
			{
				$members = $row['pm_members'] = $all_members[$row['pm_id']];
				// Set a value even if empty, or previous row won't be cleared.
				for($i = 0; $i < 5; $i++)
				{
					$row['role'.$i] = array();
				}
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

		return $total;
	}

	/**
	 * List existing projects
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function pm_list($content=null,$msg='')
	{
		if ((int) $this->debug >= 1 || $this->debug == 'index') $this->debug_message("projectmanager_ui::index(,$msg) content=".print_r($content,true));

		$tpl = new Etemplate('projectmanager.list');

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
					if (!$this->read($pm_id) || !$this->check_acl(Acl::DELETE))
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
			'nm' => Api\Cache::getSession('projectmanager', 'project_list'),
			'duration_format' => ','.$this->config['duration_format'],
		);
		if($msg)
		{
			Framework::message($msg);
		}
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.projectmanager_ui.get_rows',
				'filter2'        => 'active',// I initial value for the filter
				'options-filter2'=> self::$status_labels,
				'filter2_no_lang'=> True,// I  set no_lang for filter (=dont translate the options)
				'filter'         => 'mains',
				'options-filter' => array('' => lang('All projects'))+$this->filter_labels,
				'filter_no_lang' => True,// I  set no_lang for filter (=dont translate the options)
				'order'          =>	'pm_modified',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'   => '!role0,role1,role2,role3,role4,pm_used_time_pm_planned_time_pm_replanned_time,legacy_actions,cat_id',
				'row_id'         => 'pm_id',
				'favorites'		=> true,
				'row_modified'	=> 'pm_modified',
				'is_parent'		=> 'children',
				'parent_id'		=> 'pm_id'
			);
			// use the state of the last session stored in the user prefs
			if (($state = @unserialize($this->prefs['pm_index_state'])))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}
		$content['nm']['actions'] = $this->get_actions();
		if($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}

		// Set up role columns
		$this->instanciate('roles');
		$roles = $this->roles->query_list();
		$role_count = 0;
		foreach($roles as $role_name)
		{
			if($role_count > 5)
			{
				break;
			}
			$content['nm']['roles'][$role_count] = $role_name;
			$role_count++;
		}
		// Clear extras
		for(; $role_count < 5; $role_count++)
		{
			$content['nm']['no_role'.$role_count] = true;
		}

		$sel_options = array(
			'project_tree' => $this->ajax_tree(0, true,$this->prefs['current_project'])
		);
		$tpl->setElementAttribute('project_tree','actions', projectmanager_ui::project_tree_actions());
		if($this->prefs['current_project'])
		{
			$content['project_tree'] = 'projectmanager::'.$this->prefs['current_project'];
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Projectlist');
		$tpl->exec('projectmanager.projectmanager_ui.pm_list',$content,$sel_options);
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
	public function get_actions()
	{
		$actions = array(
			'view' => array(
				'caption' => 'Elementlist',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.projectmanager.set_project',
				'target' => '_self',
				'group' => $group=1,
				'default' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['pm_list'] != '~edit~',
			),
			'open' => array(	// does edit if allowed, otherwise view
				'caption' => 'Open',
				'allowOnMultiple' => false,
				'egw_open' => 'edit-projectmanager',
				'group' => $group,
				'default' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['pm_list'] == '~edit~',
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
						'popup' => Link::get_registry('projectmanager', 'add_popup'),
					),
					'template' => array(
						'caption' => 'Template',
						'icon' => 'move',
						'children' => $this->get_templates('caption','hint'),
						// get inherited by children
						'prefix' => 'template_',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&template=$action',
						'popup' => Link::get_registry('projectmanager', 'add_popup'),
					),
					'sub' => array(
						'caption' => 'Subproject',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&link_app=projectmanager&link_id=$id',
						'popup' => Link::get_registry('projectmanager', 'add_popup'),
						'icon' => 'navbar',
					),
					'templatesub' => array(
						'caption' => 'Template as subproject',
						'icon' => 'move',
						'children' => $this->get_templates('caption','hint'),
						// get inherited by children
						'prefix' => 'templatesub_',
						'url' => 'menuaction=projectmanager.projectmanager_ui.edit&template=$action&link_app=projectmanager&link_id=$id',
						'popup' => Link::get_registry('projectmanager', 'add_popup'),
					),
				),
			),
			'ganttchart' => array(
				'icon' => 'projectmanager/navbar',
				'caption' => 'Ganttchart',
				'onExecute' => 'javaScript:app.projectmanager.show_gantt',
				'group' => ++$group,
			),
			'pricelist' => array(
				'icon' => 'pricelist',
				'caption' => 'Pricelist',
				'onExecute' => 'javaScript:app.projectmanager.show_pricelist',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'filemanager' => array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'onExecute' => 'javaScript:app.projectmanager.show_filemanager',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'documents' => projectmanager_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['projectmanager']['document_dir'],
				$group, 'Insert in document', 'document_'
			),
			'cat' => Etemplate\Widget\Nextmatch::category_action(
				'projectmanager',$group,'Change category','cat_'
			)+array(
				'disableClass' => 'rowNoEdit',
			),
			'export' => array(
				'caption' => 'Export',
				'icon' => 'filesave',
				'group' => $group,
				'allowOnMultiple' => true,
				'url' => 'menuaction=importexport.importexport_export_ui.export_dialog&appname=projectmanager&plugin=projectmanager_export_projects_csv&selection=$id',
				'popup' => '850x440'
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
				'children' => self::$status_labels,
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
		if ($this->config['accounting_types'] && !in_array('pricelist',$this->config['accounting_types']))
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
			$old_query = Api\Cache::getSession('projectmanager', 'project_list');
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('projectmanager', $session_name);

			@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;	// all
			$this->get_rows($query,$projects,$readonlys);
			// only use the ids
			foreach($projects as $project)
			{
				if(is_array($project) && $project['pm_id'] && is_numeric($project['pm_id'])) $checked[] = $project['pm_id'];
			}
			// Reset query
			Api\Cache::setSession('projectmanager', 'project_list', $old_query);
		}

		// Dialogs to get options
		list($action, $settings) = explode('_', $action, 2);

		switch($action)
		{
			case 'gantt':
				Egw::redirect_link('/index.php', array(
					'menuaction' => 'projectmanager.projectmanager_ganttchart.show',
					'pm_id'      => implode(',',$checked),
				));
				break;
			case 'delete':
				$action_msg = lang('deleted');
				foreach($checked as $pm_id)
				{
					if (!$this->read($pm_id) || !$this->check_acl(Acl::DELETE))
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
					if (!$this->read($pm_id) || !$this->check_acl(Acl::EDIT))
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

	/**
	 * Generate the project tree nodes
	 *
	 * @param int $parent_pm_id= just return children of this project
	 * @param boolean $return Return the information (true), or send it back as JSON
	 * @param int $_pm_id=null current project allways to include
	 */
	public static function ajax_tree($parent_pm_id=null, $return=false, $_pm_id=null)
	{
		if (!$return && !isset($parent_pm_id) && !empty($_GET['id']))
		{
			list($filter,$parent_pm_id) = explode('::', $_GET['id']);
		}

		if($return || !($parent_pm_id || $filter))
		{
			$projects = array();
			foreach(self::$status_labels as $status => $label)
			{
				$projects[] = array(
					'id'	=> $status,
					'open'	=> $status == 'active',
					'text'	=> $label,
					'item'	=> array(),
					'child'	=> 1
				);
			}
			$nodes = array(
				'id' => empty($_GET['id']) ? 0 : $_GET['id'],
				'item' => $projects,
			);
		}
		else
		{
			$nodes = array(
				'id' => $_GET['id'],
				'item' => array()
			);
			//error_log(array2string(self::$status_labels));
			if(in_array($filter, array_keys(self::$status_labels)))
			{
				$nodes = array(
					'id'	=> $filter,
					'text'	=> self::$status_labels[$filter],
					'item'	=> array()
				);
				$filter = array('pm_status' => $filter);
			}
			else
			{
				$filter = array();
				if($parent_pm_id)
				{
					$project = $GLOBALS['projectmanager_bo']->read($parent_pm_id);
					$filter['pm_status'] = $project['pm_status'];
				}
			}
			self::_project_tree_leaves($filter,$parent_pm_id?$parent_pm_id : 'mains',$_pm_id ? $_pm_id : $parent_pm_id,$nodes);
		}

		// Remove keys for tree widget
		$f = function(&$project) use (&$f)
		{
			if(!$project['item']) return;
			$project['item'] = array_values($project['item']);
			foreach($project['item'] as &$item)
			{
				$f($item);
			}
		};
		$f($nodes);

		//error_log(__METHOD__."($parent_pm_id, $return, $_pm_id) \$_GET['id']=".array2string($_GET['id']).", projects=".array2string($nodes));
		if ($return)
		{
			return $nodes;
		}
		Etemplate\Widget\Tree::send_quote_json($nodes);
	}

	protected static function _project_tree_leaves($filter, $parent_pm_id = 'mains', $_pm_id, &$projects = array())
	{
		//error_log(__METHOD__ . "(".array2string($filter).", $parent_pm_id, $_pm_id)");

		$type = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['show_projectselection'];
		if (substr($type,-5) == 'title')
		{
			$label = 'pm_title';
			$title = 'pm_number';
		}
		else
		{
			$label = 'pm_number';
			$title = 'pm_title';
		}
		foreach($GLOBALS['projectmanager_bo']->get_project_tree($filter,'AND',$parent_pm_id, $_pm_id) as $project)
		{
			if ($GLOBALS['egw_info']['user']['preferences']['projectmanager']['show_projectselection']=='tree_with_number_title')
			{
				$text = $project[$title].': '.$project[$label];
			}
			else
			{
				$text = $project[$label];
			}
			$p = array(
				// Using UID for consistency with nextmatch
				'id' => 'projectmanager::'.$project['pm_id'],
				'text'	=>	$text,
				'path'	=>	$project['path'],
				/*
				These ones to play nice when a user puts a tree & a selectbox with the same
				ID on the form (addressbook edit):
				if tree overwrites selectbox options, selectbox will still work
				*/
				'label'	=>	$text,
				'title'	=>	$project[$title],
				'child' => (int)($project['children'] > 0),
			);
			if($project['pm_parent'] == null && !$filter)
			{
				$projects[$project['pm_id']] = $p;
			}
			else
			{
				$path = explode('/',$project['path']);
				array_shift($path);
				array_pop($path);
				unset($p['path']);
				$parent =& $projects['item'];
				foreach($path as $part)
				{
					$parent =& $parent[$part]['item'];
				}
				$parent[$project['pm_id']] = $p;
			}
		}
	}

	/**
	 * Generate the project tree actions
	 */
	public static function project_tree_actions()
	{
		$actions = array(
			array(
				'caption' => 'Elementlist',
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.projectmanager.set_project',
				'default' => true,
				'allowOnMultiple' => false,
			),
			array(
				'caption' => 'Ganttchart',
				'icon' => 'navbar',
				'app'  => 'projectmanager',
				'onExecute' => 'javaScript:app.projectmanager.show_gantt'
			),

		);
		// show pricelist only if we use pricelists
		$config = Api\Config::read('projectmanager');
		if (!$config['accounting_types'] || in_array('pricelist',(is_array($config['accounting_types'])?$config['accounting_types']:explode(',',$config['accounting_types']))))
		{
			// menuitem links to project-spezific priclist only if user has rights and it is used
			// to not always instanciate the priclist class, this code dublicats bopricelist::check_acl(Acl::READ),
			// specialy the always existing READ right for the general pricelist!!!
			$actions[] = array(
				'caption' => 'Pricelist',
				'icon' => 'pricelist',
				'app'  => 'projectmanager',
				'onExecute' => 'javaScript:app.projectmanager.show_pricelist',
				'allowOnMultiple' => false,
			);
		}
		if (isset($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			$actions[] = array(
				'caption' => 'Filemanager',
				'icon' => 'filemanager/navbar',
				'onExecute' => 'javaScript:app.projectmanager.show_filemanager',
				'allowOnMultiple' => false,
			);
		}
		return $actions;
	}
}
