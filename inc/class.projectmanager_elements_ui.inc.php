<?php
/**
 * ProjectManager - UI to list and edit project-elments
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * ProjectManage UI: list and edit projects-elements
 */
class projectmanager_elements_ui extends projectmanager_elements_bo
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
	 * Instance of the etemplate class
	 *
	 * @var etemplate
	 */
	var $tpl;
	/**
	 * Labels for status-filter
	 *
	 * @var array
	 */
	var $status_labels;
	/**
	 * Config-settings for projectmanager
	 *
	 * @var array
	 */
	var $config;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @return projectmanager_elements_ui
	 */
	function __construct()
	{
		$this->tpl = new etemplate_new();

		if ((int) $_REQUEST['pm_id'])
		{
			$pm_id = (int) $_REQUEST['pm_id'];
			// store the current project (only for index, as popups may be called by other parent-projects)
		}
		else
		{
			$pm_id = $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
		}
		if (!$pm_id)
		{
			$this->tpl->location(array(
				'menuaction' => 'projectmanager.projectmanager_ui.index',
				'msg'        => lang('You need to select a project first'),
			));
		}
		parent::__construct($pm_id);

		// check if we have at least read-access to this project
		if (!$this->project->check_acl(EGW_ACL_READ))
		{
			$this->tpl->location(array(
				'menuaction' => 'projectmanager.projectmanager_ui.index',
				'msg'        => lang('Permission denied !!!'),
			));
		}

		$this->status_labels = array(
			'all'     => lang('all'),
			'used'    => lang('used'),
			'new'     => lang('new'),
			'ignored' => lang('ignored'),
		);
	}


	/**
	 * View a project-element, just calls edit with view-param set
	 */
	function view()
	{
		$this->edit(null,true);
	}

	/**
	 * Edit or view a project-element
	 *
	 * @var array $content content-array if called by process-exec
	 * @var boolean $view only view project, default false, only used on first call !is_array($content)
	 */
	function edit($content=null,$view=false)
	{
		if ((int) $this->debug >= 1 || $this->debug == 'edit') $this->debug_message("projectmanager_elements_ui::edit(".print_r($content,true).",$view)");

		if (is_array($content))
		{
			$this->data = $content['data'];
			$update_necessary = $save_necessary = 0;
			$datasource = $this->datasource($this->data['pe_app']);
			if (($ds_read_from_element = !($ds = $datasource->read($this->data['pe_app_id'],$this->data))))
			{
				$ds = $datasource->element_values($this->data);
			}
			if (!$content['view'])
			{
				if ($content['pe_completion'] !== '') $content['pe_completion'] .= '%';

				foreach($datasource->name2id as $name => $id)
				{
					if ($id == PM_TITLE || $id == PM_DETAILS) continue;	// title and details can NOT be edited

					//echo "checking $name=$id (overwrite={$this->data['pe_overwrite']}&$id == ".($this->data['pe_overwrite']&$id?'true':'false')."), content='{$content[$name]}'<br>\n";
					// check if update is necessary, because a field has be set or changed
					if ($content[$name] && ($content[$name] != $this->data[$name] || !($this->data['pe_overwrite'] & $id)))
					{
						//echo "need to update $name as content[$name] changed to '".$content[$name]."' != '".$this->data[$name]."'<br>\n";
						$this->data[$name] = $content[$name];
						$this->data['pe_overwrite'] |= $id;
						$update_necessary |= $id;
					}
					// check if a field is no longer set, or it's not set and datasource changed
					// => set it from the datasource
					elseif (($this->data['pe_overwrite'] & $id) && !$content[$name] ||
						    !($this->data['pe_overwrite'] & $id) && (int)$this->data[$name] != (int)$ds[$name])
					{
						//echo "need to update $name as content[$name] is unset or datasource changed cont='".$content[$name]."', data='".$this->data[$name]."', ds='".$ds[$name]."'<br>\n";
						// if we have a change in the datasource, set pe_synced
						if ($this->data[$name] != $ds[$name])
						{
							$this->data['pe_synced'] = $this->now_su;
						}
						$this->data[$name] = $ds[$name];
						$this->data['pe_overwrite'] &= ~$id;
						$update_necessary |= $id;
					}
				}
				$content['cat_id'] = (int) $content['cat_id'];	// as All='' and cat_id column is int

				// calculate the new summary and if a percentage give the share in hours
				//echo "<p>project_summary[pe_total_shares]={$this->project_summary['pe_total_shares']}, old_pe_share={$content['old_pe_share']}, old_default_share=$content[old_default_share], content[pe_share]={$content['pe_share']}</p>\n";
				if ($this->data['pe_replanned_time'])
				{
					$planned_time = $this->data['pe_replanned_time'];
				}
				elseif ($this->data['pe_planned_time'])
				{
					$planned_time = $this->data['pe_planned_time'];
				}
				else
				{
					$planned_time = $ds['pe_planned_time'];
				}
				$default_share = $planned_time && $this->project->data['pm_accounting_type'] != 'status' ? $planned_time : $this->default_share;

				$this->project_summary['pe_total_shares'] -= round((string) $content['old_pe_share'] !== '' ? $content['old_pe_share'] : $content['old_default_share']);
				if (substr($content['pe_share'],-1) == '%' || $this->project->data['pm_accounting_type'] == 'status')
				{
					//echo "<p>project_summary[pe_total_shares]={$this->project_summary['pe_total_shares']}</p>\n";
					if ((float) $content['pe_share'] == 100 || !$this->project_summary['pe_total_shares'])
					{
						$content['pe_share'] = $this->default_share;
					}
					else
					{
						$content['pe_share'] = round($this->project_summary['pe_total_shares'] * (float) $content['pe_share'] /
							(100 - (float) $content['pe_share']),1);
					}
					//echo "<p>pe_share={$content['pe_share']}</p>\n";
				}
				$this->project_summary['pe_total_shares'] += round((string) $content['pe_share'] !== '' ? $content['pe_share'] : $default_share);
				//echo "<p>project_summary[pe_total_shares]={$this->project_summary['pe_total_shares']}, default_share=$default_share, content[pe_share]={$content['pe_share']}</p>\n";

				foreach(array('pe_status','pe_remark','pe_constraints','pe_share','pe_eroles') as $name)
				{
					if ($name == 'pe_constraints')
					{
						foreach($content['pe_constraints'] as $type => $data)
						{
							if (!$data)
							{
								unset($content['pe_constraints'][$type]);	// ignore not set constraints
							}
							elseif (!is_array($data))
							{
								$content['pe_constraints'][$type] = explode(',',$data);		// otherwise it's detected as change
							}
						}
					}
					if ($content[$name] != $this->data[$name] ||
						($name == 'pe_share' && $content[$name] !== $this->data[$name]) ||
						($name == 'pe_eroles' && $content[$name] !== $this->data[$name]))	// for pe_share and pe_eroles we differ between 0 and empty!
					{
						//echo "need to update $name as content[$name] changed to '".print_r($content[$name],true)."' != '".print_r($this->data[$name],true)."'<br>\n";
						$this->data[$name] = $content[$name];
						$save_necessary = true;

						if ($name == 'pe_remark') $this->data['update_remark'] = true;
					}
				}
			}
			//echo "projectmanager_elements_ui::edit(): save_necessary=".(int)$save_necessary.", update_necessary=$update_necessary, data="; _debug_array($this->data);

			$view = $content['view'] && !($content['edit'] && $this->check_acl(EGW_ACL_EDIT));

			if (($content['save'] || $content['apply']) && $this->check_acl(EGW_ACL_EDIT))
			{
				if ($update_necessary || $save_necessary)
				{
					if ($this->save(null,true,$update_necessary) != 0)
					{
						$msg = lang('Error: saving the project-element (%1) !!!',$this->db->Error);
						unset($content['save']);	// dont exit
					}
					else
					{
						$msg = lang('Project-Element saved');

						egw_framework::refresh_opener($msg,'projectmanager',$content[pe_id], 'edit');
					}
				}
				else
				{
					$msg = lang('no save necessary');
				}
			}
			error_log(__METHOD__. "Caller = " . $content['caller']);

			if ($content['save'] || $content['cancel'] || $content['delete'])
			{
				egw_framework::window_close();
				common::egw_exit();
			}
		}
		else
		{
			if ((int) $_GET['pe_id'])
			{
				$this->read((int) $_GET['pe_id']);
			}
			if ($this->data['pe_id'])
			{
				if (!$this->check_acl(EGW_ACL_READ))
				{
					$this->tpl->location(array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.index',
						'msg' => lang('Permission denied !!!'),
					));
				}
				if (!$this->check_acl(EGW_ACL_EDIT)) $view = true;
			}

			$datasource = $this->datasource($this->data['pe_app']);
			if (($ds_read_from_element = !($ds = $datasource->read($this->data['pe_app_id'],$this->data))))
			{
				$ds = $datasource->element_values($this->data);
			}
			else
			{
				$this->data['pe_title'] = $ds['pe_title'];	// updating the title, not all datasources do it automatic
			}
		}
		if ($this->data['pe_replanned_time'])
		{
			$planned_time = $this->data['pe_replanned_time'];
		}
		elseif ($this->data['pe_planned_time'])
		{
			$planned_time = $this->data['pe_planned_time'];
		}
		else
		{
			$planned_time = $ds['pe_planned_time'];
		}

		$default_share = $planned_time && $this->project->data['pm_accounting_type'] != 'status' ? $planned_time : $this->default_share;

		if ($ds_read_from_element && !$view)
		{
			$msg .= lang('No READ access to the datasource: removing overwritten values will just empty them !!!');
		}
		$preserv = $this->data + array(
			'view' => $view,
			'data' => $this->data,
			'caller' => !$content['caller'] && preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ?
				 $matches[1] : $content['caller'],
			'old_pe_share' => $this->data['pe_share'],
			'old_default_share' => $default_share,
		);
		unset($preserv['pe_resources']);	// otherwise we cant detect no more resources set

		foreach($datasource->name2id as $name => $id)
		{
			if ($id != PM_TITLE && !($this->data['pe_overwrite'] & $id)) 	// empty not explicitly set values
			{
				$this->data[$name] = '';
			}
		}
		$tabs = 'dates|times|budget|constraints|resources|details|eroles';
		if ($this->data['pe_replanned_time'])
		{
			$planned_quantity_blur = $this->data['pe_replanned_time'] / 60;
		}
		elseif ($this->data['pe_planned_time'])
		{
			$planned_quantity_blur = $this->data['pe_planned_time'] / 60;
		}
		else
		{
			$planned_quantity_blur = $ds['pe_planned_quantity'];
		}

		$content = $this->data + array(
			'ds'  => $ds,
			'msg' => $msg,
			'default_share' => $default_share,
			'duration_format' => $this->config['duration_format'],
			'no_times' => $this->project->data['pm_accounting_type'] == 'status',
			$tabs => $content[$tabs],
			'no_pricelist' => $this->project->data['pm_accounting_type'] != 'pricelist',
			'planned_quantity_blur' => $planned_quantity_blur,
			'used_quantity_blur' => $this->data['pe_used_time'] ? $this->data['pe_used_time'] / 60 : $ds['pe_used_quantity'],
		);
		// calculate percentual shares
		$content['default_total'] = $content['share_total'] = $this->project_summary['pe_total_shares'];
		if ((string) $this->data['pe_share'] !== '')
		{
			if ($this->project_summary['pe_total_shares'])
			{
				$content['share_percentage'] = round(100.0 * $this->data['pe_share'] / $this->project_summary['pe_total_shares'],1) . '%';
			}
			$content['default_total'] += $default_share - $this->data['pe_share'];
		}
		if ($content['default_total'])
		{
			$content['default_percentage'] = round(100.0 * $default_share / $content['default_total'],1) . '%';
		}
		if ($this->project->data['pm_accounting_type'] == 'status')
		{
			$content['pe_share'] = $content['share_percentage'];
		}
		//_debug_array($content);
		$sel_options = array(
			'pe_constraints' => $this->titles(array(	// only titles of elements displayed in a gantchart
				"pe_status != 'ignore'",
//				'(pe_planned_start IS NOT NULL OR pe_real_start IS NOT NULL)',
//				'(pe_planned_end IS NOT NULL OR pe_real_end IS NOT NULL)',
				'pe_id != '.(int)$this->data['pe_id'],	// dont show own title
			)),
			'milestone'     => $this->milestones->titles(array('pm_id' => $this->data['pm_id'])),
		);
		$readonlys = array(
			'delete' => !$this->data['pe_id'] || !$this->check_acl(EGW_ACL_DELETE),
			'edit' => !$view || !$this->check_acl(EGW_ACL_EDIT),
			'eroles_edit' => $view,
		);
		// display eroles tab only if it's enabled in config and for supported erole applications
		$readonlys[$tabs]['eroles'] = (!$this->config['enable_eroles']) || !(in_array($this->data['pe_app'],$this->erole_apps));
		// disable the times tab, if accounting-type status
		$readonlys[$tabs]['times'] = $this->project->data['pm_accounting_type'] == 'status';
		// check if user has the necessary rights to view or edit the budget
		$readonlys[$tabs]['budget'] = !$this->check_acl(EGW_ACL_BUDGET);
		if (!$this->check_acl(EGW_ACL_EDIT_BUDGET))
		{
			foreach(array('pe_planned_budget','pe_used_budget','pl_id','pe_unitprice','pe_planned_quantity','pe_used_quantity') as $key)
			{
				$readonlys[$key] = true;
			}
		}
		if ($view)
		{
			foreach($this->db_cols as $name)
			{
				$readonlys[$name] = true;
			}
			$readonlys['pe_remark'] = true;
			$readonlys['save'] = $readonlys['apply'] = true;
			$readonlys['pe_constraints[start]'] = $readonlys['pe_constraints[end]'] = $readonlys['pe_constraints[milestone]'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager') . ' - ' .
			($this->data['pm_id'] ? ($view ? lang('View project-elements') : lang('Edit project-elements')) : lang('Add project-elements'));
		$this->tpl->read('projectmanager.elements.edit');
		$this->tpl->exec('projectmanager.projectmanager_elements_ui.edit',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * query projects for nextmatch in the projects-list
	 *
	 * reimplemented from so_sql to disable action-buttons based on the acl and make some modification on the data
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('projectelements_list','projectmanager',$query=$query_in);

		//echo "<p>project_elements_ui::get_rows(".print_r($query,true).")</p>\n";
		// save the state of the index in the user prefs
		$state = serialize(array(
			'filter'     => $query['filter'],
			'filter2'    => $query['filter2'],
			'cat_id'     => $query['cat_id'],
			'order'      => $query['order'],
			'sort'       => $query['sort'],
			));
		if ($state != $this->prefs['pe_index_state'])
		{
			$GLOBALS['egw']->preferences->add('projectmanager','pe_index_state',$state);
			// save prefs, but do NOT invalid the cache (unnecessary)
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);
		}

		if ($this->status_filter[$query['filter']])
		{
			$query['col_filter']['pe_status'] = $this->status_filter[$query['filter']];
		}
		else
		{
			unset($query['col_filter']['pe_status']);
		}
		if ($query['cat_id'])
		{
			$query['col_filter']['cat_id'] = $query['cat_id'];
			$query_in['link_add']['extra'] = array('cat_id' => $query['cat_id']);
		}
		else
		{
			unset($query['col_filter']['cat_id']);
			unset($query_in['link_add']['extra']);
		}
		if (!$query['col_filter']['pe_resources'])
		{
			unset($query['col_filter']['pe_resources']);
		}
		if (!$query['col_filter']['pe_app'])
		{
			unset($query['col_filter']['pe_app']);
		}
		if ($query['filter2'] & 2)	// show sub-elements (elements of sub-projects)
		{
			$query['col_filter']['pm_id'] = $this->project->children($this->pm_id,array($this->pm_id));
			if (count($query['col_filter']['pm_id']) <= 1) $query['col_filter']['pm_id'] = $this->pm_id;
			// dont show the sub-projects
			$query['col_filter'][] = "link_app1!='projectmanager'";
		}
		// cumulate eg. timesheets in also included infologs
		$query['col_filter']['cumulate'] = !($query['filter2'] & 4);
		$total = parent::get_rows($query,$rows,$readonlys,true);
		unset($query['col_filter']['cumulate']);

		// adding the project itself always as first line
		$self = $this->update('projectmanager',$this->pm_id);
		$self['pe_app']    = 'projectmanager';
		$self['pe_app_id'] = $this->pm_id;
		$self['pe_icon']   = 'projectmanager/navbar';
		$self['pe_modified'] = $this->project->data['pm_modified'];
		$self['pe_modifier'] = $this->project->data['pm_modifier'];
		$self['link'] = array(
			'app'=>'projectmanager',
			'id' => $this->pm_id
		);
		$self['class'] = 'th rowNoDelete';
		$rows = array_merge(array($self),$rows);
		$total++;

		$readonlys = array();
		foreach($rows as $n => &$row)
		{
			if ($n && !$this->check_acl(EGW_ACL_EDIT,$row))
			{
				$readonlys["edit[$row[pe_id]]"] = true;
				$row['class'] .= ' rowNoEdit';
			}
			if ($n && !$this->check_acl(EGW_ACL_DELETE,$row))
			{
				$readonlys["delete[$row[pe_id]]"] = true;
				$row['class'] .= ' rowNoDelete';
			}
			if (!$n)
			{
				// no link for own project
				if (!$this->project->check_acl(EGW_ACL_EDIT,$this->project->data))
				{
					$readonlys['edit'] = true;
				}
			}
			else
			{
				$row['link'] = array(
					'app'  => $row['pe_app'],
					'id'   => $row['pe_app_id'],
					'title'=> $row['pe_title'],
					'help' => $row['pe_app'] == 'projectmanager' ? lang("Select this project and show it's elements") :
						lang('View this element in %1',lang($row['pe_app'])),
				);
			}
			if (!($query['filter2']&1)) unset($row['pe_details']);

			// add project-title for elements from sub-projects
			if (($query['filter2']&2) && $row['pm_id'] != $this->pm_id)
			{
				$row['pm_title'] = $this->project->link_title($row['pm_id']);
				$row['pm_link'] = array(
					'app'  => 'projectmanager',
					'id'   => $row['pm_id'],
					'title'=> $this->project->link_title($row['pm_id']),
					'help' => lang("Select this project and show it's elements"),
				);
			}
			$row['pe_completion_icon'] = $row['pe_completion'] == 100 ? 'done' : $row['pe_completion'];

			$custom_app_icons[$row['pe_app']][] = $row['pe_app_id'];

			$row['elem_id'] = $row['pe_app'].':'.$row['pe_app_id'].':'.$row['pe_id'];
			// add pe links
			if ($query['filter2']&3)
			{
				if ($this->prefs['show_links'] &&
					(isset($row['pe_all_links']) || ($row['pe_all_links'] = egw_link::get_links($row['link']['app'],$row['link']['id'],'',true))))
				{
					foreach ($row['pe_all_links'] as $link)
					{
						if ($show_links != 'none' &&
							!($row['pm_link']['id'] == $link['id'] && $link['app'] == 'projectmanager') &&
							!($row['pm_id'] == $link['id'] && $link['app'] == 'projectmanager') &&
							($show_links == 'all' || ($show_links == 'links') === ($link['app'] != egw_link::VFS_APPNAME)))
						{
							$row['pe_links'][] = $link;
						}
					}
				}
 			}
		}

		if ($this->prefs['show_custom_app_icons'] || $this->prefs['show_infolog_type_icon'])
		{
			$custom_app_icons['location'] = 'pm_custom_app_icons';
			$custom_app_icons = $GLOBALS['egw']->hooks->process($custom_app_icons);
			foreach($rows as $n => &$row)
			{
				$app_info = $custom_app_icons[$row['pe_app']][$row['pe_app_id']];
				if (isset($app_info))
				{
					// old hook returns only custom completition / status icon
					if (!is_array($app_info))
					{
						$row['pe_completion_icon'] = $app_info;
					}
					else	// new hook returning all three informations
					{
						if ($this->prefs['show_custom_app_icons'] && isset($app_info['status']))
						{
							$row['pe_completion_icon'] = $app_info['status'];
						}
						if ($this->prefs['show_infolog_type_icon'] && isset($app_info['icon']))
						{
							$row['pe_icon'] = $app_info['icon'];
						}
						if (isset($app_info['class']))
						{
							$row['class'] .= ' '.$app_info['class'];
						}
					}
				}
			}
		}
		if (!$this->project->check_acl(EGW_ACL_BUDGET))
		{
			$rows['no_pe_used_budget_pe_planned_budget'] = true;
		}
		if ($this->project->data['pm_accounting_type'] == 'status')
		{
			$rows['no_pe_used_time_pe_planned_time'] = $rows['no_pe_used_time_pe_planned_time_pe_replanned_time'] = true;
		}
		// disable time & budget columns if pm is configures for status or status and time only
		if ($this->config['accounting_types'] == 'status')
		{
			$rows['no_pm_used_time_pm_planned_time_pe_replanned_time'] = true;
			$rows['no_pm_used_budget_pm_planned_budget'] = true;
			$query_in['options-selectcols']['pm_used_time'] = $query_in['options-selectcols']['pm_planned_time'] = false;
			$query_in['options-selectcols']['pm_used_time'] = $query_in['options-selectcols']['pm_replanned_time'] = false;
			$query_in['options-selectcols']['pm_used_budget'] = $query_in['options-selectcols']['pm_planned_budget'] = false;
		}
		if ($this->config['accounting_types'] == 'status,times')
		{
			$rows['no_pm_used_budget_pm_planned_budget'] = true;
			$query_in['options-selectcols']['pm_used_budget'] = $query_in['options-selectcols']['pm_planned_budget'] = false;
		}
		$rows['duration_format'] = ','.$this->config['duration_format'].',,1';
		if ($query['cat_id']) $rows['no_cat_id'] = true;
		// calculate the filter-specific summary if we have a filter, beside the default pe_status=used=array(new,regular)
		unset($query['col_filter']['pe_app']); //pe_app should not change summary
		if (array_diff(array_keys($query['col_filter']),array(0,'pe_status','pm_id')) || !is_array($query['col_filter']['pe_status']))
		{
			$rows += $this->summary(null,$query['col_filter']);
		}
		if ((int)$this->debug >= 2 || $this->debug == 'get_rows')
		{
			$this->debug_message("projectmanager_elements_ui::get_rows(".print_r($query,true).") total=$total, rows =".print_r($rows,true)."readonlys=".print_r($readonlys,true));
		}
		return $total;
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
			'open' => array(	// Open for project itself and elements other then (sub-)projects
				'caption' => 'Open',
				'group' => $group=1,
				'egw_open' => 'edit-',	// no app to use one the in id
				'enableId' => '^(?!projectmanager:[^:]+:[1-9])',
				'hideOnDisabled' => true,
				'allowOnMultiple' => false,
				'default' => true,
			),
			'view' => array(	// Open for sub-projects view elements
				'caption' => 'Elementlist',
				'icon' => 'navbar',
				'group' => $group,
				'egw_open' => 'view-',	// no app to use one the in id
				'enableId' => '^projectmanager:[^:]+:(?!0$)',
				'target' => '_self',
				'hideOnDisabled' => true,
				'allowOnMultiple' => false,
				'default' => true,
				'enabled' => true,
			),
			'project' => array(	// Edit sub-project
				'caption' => 'Project',
				'icon' => 'edit',
				'group' => $group,
				'egw_open' => 'edit-',
				'enableId' => '^projectmanager:[^:]+:[1-9]',
				'hideOnDisabled' => true,
				'allowOnMultiple' => false,
				'enabled' => true,
			),
			'edit' => array(	// Edit project element for all elements
				'caption' => 'Project-element',
				'allowOnMultiple' => false,
				'group' => $group,
				'egw_open' => 'edit-projectelement-2',
				'enableId' => ':[^:]+:[1-9]',
				'hideOnDisabled' => true,
				'allowOnMultiple' => false,
			),
			'ganttchart' => array(
				'icon' => 'projectmanager/navbar',
				'caption' => 'Ganttchart',
				'group' => $group,
				'enableId' => 'projectmanager:',
				'onExecute' => 'javaScript:app.projectmanager.show_gantt',
				'enabled' => true,
			),
			'timesheet' => array(
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'egw_open' => 'add-timesheet',
				'allowOnMultiple' => false,
				'group' => ++$group,
			),
			'infolog-subs' => array(
				'icon' => 'infolog/navbar',
				'caption' => 'View subs',
				'hint' => 'View all subs of this entry',
				'group' => $group,
				'allowOnMultiple' => false,
				'enableId' => '^infolog:',
				'enableClass' => 'projectmanager_rowHasSubs',
				'url' => 'menuaction=infolog.infolog_ui.index&action=sp&action_id=$id',
				'targetapp' => 'infolog',
			),
		);
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				// Has to be a submit, IDs are more than just a number
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'allowOnMultiple' => false,
				'group' => $group,
			);
		}
		$actions += array(
			'sync_all' => array(
				'caption' => 'Synchronise all',
				'icon' => 'agt_reload',
				'hint' => 'necessary for project-elements doing that not automatic',
				'group' => ++$group,
			),
			/* not (yet) implemented
			'select_all' => array(
				'caption' => 'Whole query',
				'checkbox' => true,
				'hint' => 'Apply the action on the whole query, NOT only the shown entries',
				'group' => ++$group,
			),*/
			'documents' => projectmanager_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['projectmanager']['document_dir'],
				$group, 'Insert in document', 'document_'
			),
			'cat' => nextmatch_widget::category_action(
				'projectmanager',$group,'Change category','cat_'
			)+array(
				'disableClass' => 'rowNoEdit',
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this project-element, does NOT remove the linked entry',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
			),
		);

		//_debug_array($actions);
		return $actions;
	}

	/**
	 * List existing projects-elements
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='')
	{

		if ((int) $this->debug >= 1 || $this->debug == 'index') $this->debug_message("projectmanager_elements_ui::index(".print_r($content,true).",$msg)");

		// store the current project (only for index, as popups may be called by other parent-projects)
		$GLOBALS['egw']->session->appsession('pm_id','projectmanager',$this->project->data['pm_id']);

		if ($_GET['msg']) $msg = $_GET['msg'];

		if ($content['nm']['rows']['delete'])
		{
			list($pe_id) = each($content['nm']['rows']['delete']);
			$content['nm']['selected'] = array($pe_id);
			$content['nm']['action'] = 'delete';
		}
		if ((int)$_GET['delete'])
		{
			$content['nm']['selected'] = array((int)$_GET['delete']);
			$content['nm']['action'] = 'delete';
		}
		if ($content['nm']['action'])
		{
			$this->action($content['nm']['action'], $content['nm']['selected'], $msg);
		}
		$content = array(
			'nm' => $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager'),
			'msg'      => $msg,
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.projectmanager_elements_ui.get_rows',
				'filter'         => 'used',// I initial value for the filter
				'filter_label'   => lang('Filter'),// I  label for filter    (optional)
				'options-filter' => $this->status_labels,
				'filter_no_lang' => True,// I  set no_lang for filter (=dont translate the options)
				'options-filter2' => array(
					0 => 'No details',
					1 => 'Details',
					2 => 'Subelements',
					3 => 'Details of subelements',
					4 => 'Cumulated elements too',
					5 => 'Details of cumulated',
				),
				'col_filter' => array('pe_resources' => null),	// default value, to suppress loop
				'order'          =>	'pe_modified',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'   => '!cat_id,pe_used_time_pe_planned_time_pe_replanned_time,legacy_actions',
				'csv_fields'     => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['nextmatch-export-definition-element'],
				'row_id' => 'elem_id',	// pe_app:pe_app_id:pe_id
				'dataStorePrefix' => 'projectmanager_elements'
			);
			// use the state of the last session stored in the user prefs
			if ($state = @unserialize($this->prefs['pe_index_state']))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}

		// Need to set this each time.  Nextmatch widget removes string keys from action, so we can't
		// edit sync_all
		$content['nm']['actions'] = $this->get_actions();

		// add "buttons" only with add-rights
		if ($this->project->check_acl(EGW_ACL_ADD))
		{
			$content['nm']['header_right'] = 'projectmanager.elements.list.add';
			$content['nm']['header_left']  = 'projectmanager.elements.list.add-new';
			if(!$this->config['enable_eroles'])
			{
				// disable moreOptions button to select eRoles
				$readonlys['nm']['extra_icons'] = true;
			}
			unset($content['nm']['eroles_add']); // remove selected value(s) from link_add widget
			$content['nm']['actions']['sync_all']['enabled'] = true;
		}
		else
		{
			unset($content['nm']['header_right']);
			unset($content['nm']['header_left']);
			$content['nm']['actions']['sync_all']['enabled'] = false;
		}
		$content['nm']['link_to'] = array(
			'to_id'    => $this->pm_id,
			'to_app'   => 'projectmanager',
			'no_files' => true,
			'search_label' => 'Add existing',
			'link_label'   => 'Add',
		);
		$content['nm']['link_add'] = array(
			'to_id'    => $this->pm_id,
			'to_app'   => 'projectmanager',
			'add_app'  => 'infolog',
		);

		// set id for automatic linking via quick add
		$GLOBALS['egw_info']['flags']['currentid'] = $this->pm_id;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Elementlist') .
			': ' . $this->project->data['pm_number'] . ': ' .$this->project->data['pm_title'] ;
		$this->tpl->read('projectmanager.elements.list');

		// fill the sel_options Applications
		$sel_options ['pe_app'] = egw_link::app_list('add_app');
		$this->tpl->exec('projectmanager.projectmanager_elements_ui.index',$content,$sel_options,$readonlys);
	}

	/**
	 * Returning document actions / files from the document_dir
	 *
	 * @return array
	 */
	function get_document_actions()
	{
		if (!$this->prefs['document_dir']) return array();

		if (!is_array($actions = egw_session::appsession('document_actions','projectmanager')))
		{
			$actions = array();
			if (($files = egw_vfs::find($this->prefs['document_dir'],array('need_mime'=>true),true)))
			{
				foreach($files as $file)
				{
					// return only the mime-types we support
					if (!projectmanager_merge::is_implemented($file['mime'],substr($file['name'],-4))) continue;

					$actions['document-'.$file['name']] = $file['name'];
				}
			}
			egw_session::appsession('document_actions','projectmanager',$actions);
		}
		return $actions;
	}

	/**
	 * apply an action in element list
	 *
	 * @param string/int $action 'document' only at the moment
	 * @param array $checked checked element id's
	 * @param string $msg to give back for the view or index
	 * @return boolean true on success, false otherwise
	 */
	function action($action,$checked,&$msg)
	{
		$document_projects = array();

		// action id's are pe_app:pe_app_id:pe_id --> pe_id
		if (!is_numeric($checked[0]))
		{
			foreach($checked as $key => &$id)
			{
				list($app,$app_id,$id) = explode(':', $id);
				if ($action == 'ganttchart')
				{
					if ($app == 'projectmanager')
					{
						$id = $app_id;
					}
					else
					{
						unset($checked[$key]);
					}
				}
				else if ($action == 'filemanager')
				{
					$link = array(
						'menuaction' => 'filemanager.filemanager_ui.index',
						'path' => "/apps/$app/$app_id"
					);
					egw::redirect_link('/index.php',$link);
				}
				elseif (strpos($action,'document') !== false && $app == 'projectmanager' && $id == 0)
				{
					// Special handling for top-level projects - they show in the element list and
					// can be selected, but can't be retrieved by pe_id
					$document_projects[] = $app_id;
					unset($checked[$key]);
				}
			}
			unset($id);
		}
		if (substr($action,0,9) == 'document_')
		{
			$document = substr($action,9);
			$action = 'document';
		}
		if (substr($action,0,4) == 'cat_') list($action,$cat_id) = explode('_',$action);

		switch($action)
		{
			case 'cat':
				if (!$this->project->check_acl(EGW_ACL_ADD) ||
					($num = $this->update_cat($checked, $cat_id)) === false)
				{
					$msg = lang('Permission denied !!!');
				}
				else
				{
					$msg = lang('Category in %1 project-element(s) updated.',$num);
					return true;
				}
				break;

			case 'delete':
				if (!$this->project->check_acl(EGW_ACL_ADD))
				{
					$msg = lang('Permission denied !!!');
				}
				else
				{
					$msg = ($num=$this->delete(array('pe_id' => $checked))) ?
						($num == 1 ? lang('Project-element deleted') : lang('%1 project-element(s) deleted.',$num)) :
						lang('Error: deleting project-element !!!');
				}
				break;

			case 'sync_all':	// does NOT use id's
				if ($this->project->check_acl(EGW_ACL_ADD))
				{
					$msg = lang('%1 element(s) updated',$this->sync_all());
					return true;
				}
				else
				{
					$msg = lang('Permission denied !!!');
				}
				break;

			case 'ganttchart':
				egw::redirect_link('/index.php', array(
					'menuaction' => 'projectmanager.projectmanager_ganttchart.show',
					'pm_id'      => implode(',',$checked),
				));
				break;

			case 'document':
				$document_projects = array();
				$contacts = array();
				$eroles = array();
				if(count($checked) == 0)
				{
					// Use all, from merge selectbox in side menu
					$query = $old_query = $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager');
					$query['num_rows'] = -1;        // all
					$this->get_rows($query,$selection,$readonlys);
					foreach($selection as $element)
					{
						if($element['pe_id'] && is_numeric($element['pe_id'])) $checked[] = $element['pe_id'];
					}

					// Reset nm params
					$GLOBALS['egw']->session->appsession('projectelements_list','projectmanager', $old_query);

				}
				foreach($this->search(array('pm_id' => $this->data['pm_id']),false) as $id => $element)
				{
					// add contact
					if($element['pe_app'] == 'addressbook' && in_array($element['pe_id'],$checked))
					{
						$contacts[] = $element['pe_app_id'];
					}
					// add erole(s)
					if($this->config['enable_eroles'] && !empty($element['pe_eroles']))
					{
						// one element could have multiple eroles
						foreach(explode(',',$element['pe_eroles']) as $erole_id)
						{
							$eroles[] = array(
								'pe_id'		=> $element['pe_id'],
								'app' 		=> $element['pe_app'],
								'app_id' 	=> $element['pe_app_id'],
								'erole_id'	=> $erole_id,
							);
						}
					}
				}

				// Check to see if the user selected an element from another (child) project,
				// and add that project to the list of IDs so merge won't skip it
				$current_pm_id = $this->pm_id;
				$document_projects[] = $current_pm_id;
				foreach($checked as $key => $id) {
					// Need to clear pm_id or read won't actually read
					unset($this->pm_id);
					$element = $this->read(array('pe_id' => $id));
					if($element['pm_id'] && $element['pm_id'] != $current_pm_id) $document_projects[] = $element['pm_id'];
				}
				$this->pm_id = $current_pm_id;

				if(!empty($contacts))
				{
					$document_projects['contacts'] = array_unique($contacts);
				}

				// Actually send the elements the user selected
				$document_projects['elements'] = $checked;
				$msg = $this->download_document($document_projects, $document, $eroles);
				return true;
		}
		return false;
	}


	/**
	 * Download a document with inserted contact(s)
	 *
	 * @param array $ids contact-ids
	 * @param string $document vfs-path of document
	 * @param array $eroles=null element roles with keys pe_id, app, app_id and erole_id
	 * @return string error-message or error, otherwise the function does NOT return!
	 */
	function download_document($ids,$document='',$eroles=null)
	{
		$document_merge = new projectmanager_merge($this->pm_id);
		if($this->config['enable_eroles'] && !empty($eroles))
		{
			$document_merge->set_eroles($eroles);
		}

		if($ids['contacts']) {
			$document_merge->contact_ids = $ids['contacts'];
			unset($ids['contacts']);
		}

		if(isset($this->prefs['document_download_name']))
		{
			$ext = '.'.pathinfo($document,PATHINFO_EXTENSION);
			$name = preg_replace(
				array('/%document%/','/%pm_number%/','/%pm_title%/'),
				array(basename($document,$ext),$this->project->data['pm_number'],$this->project->data['pm_title']),
				$this->prefs['document_download_name']
			);
		}
		if($ids['elements'])
		{
			if($document_merge->export_limit &&
				!bo_merge::is_export_limit_excepted() && count($ids['elements']) > (int)$document_merge->export_limit)
			{
				return lang('No rights to export more then %1 entries!',(int)$document_merge->export_limit);
			}
			$document_merge->elements = $ids['elements'];
			unset($ids['elements']);
		}

		return $document_merge->download($document, $ids, isset($name) ? $name : null, $this->prefs['document_dir']);
	}
}
