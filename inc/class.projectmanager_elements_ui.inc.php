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

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

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
		$this->tpl = new Etemplate();

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
		parent::__construct($pm_id);


		// check if we have at least read-access to this project
		if (!$this->project->check_acl(Acl::READ))
		{
			Framework::message(lang('Permission denied !!!'),'error');
			$pm_id = 0;
		}

		$GLOBALS['egw']->preferences->add('projectmanager','current_project', $pm_id);
		$GLOBALS['egw']->preferences->save_repository();

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
		if ((int) static::DEBUG >= 1 || static::DEBUG == 'edit') projectmanager_bo::debug_message("projectmanager_elements_ui::edit(".print_r($content,true).",$view)");

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
					if ($name == 'pe_constraints' && is_array($content['pe_constraints']))
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
				if($content['new_constraint'])
				{
					if($content['new_constraint']['target']['id'] )
					{
						$save_necessary = true;
						$new = $content['new_constraint'];
						$this->data['pe_constraints'][] = array(
							'pm_id' => $this->data['pm_id'],
							'pe_id_start' => $this->data['pe_id'],
							'pe_id_end' => $new['target']['app'] == 'pm_milestone' ? 0 : $new['target']['id'],
							'ms_id' => $new['target']['app'] != 'pm_milestone' ? 0 : $new['target']['id'],
							'type' => $new['type']
						);
					}
					unset($this->data['new_constraint']);
				}
				if($content['pe_constraints']['delete'])
				{
					unset($this->data['pe_constraints'][key($content['pe_constraints']['delete'])]);
					unset($this->data['pe_constraints']['delete']);
					$save_necessary = true;

					// Doesn't re-load, so we need to explicitly re-key the constraints
					$this->data['pe_constraints'] = array_values($this->data['pe_constraints']);
				}
			}
			//echo "projectmanager_elements_ui::edit(): save_necessary=".(int)$save_necessary.", update_necessary=$update_necessary, data="; _debug_array($this->data);

			$view = $content['view'] && !($content['edit'] && $this->check_acl(Acl::EDIT));

			if (($content['save'] || $content['apply'] ||
				$content['pe_constraints']['delete'] || $content['new_constraint']['add_button']) && $this->check_acl(Acl::EDIT))
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

						Framework::refresh_opener($msg,'projectmanager',$content['pe_id'], 'edit');
					}
				}
				else
				{
					$msg = lang('no save necessary');
				}
			}

			if ($content['save'] || $content['cancel'] || $content['delete'])
			{
				Framework::window_close();
				exit();
			}
		}
		else
		{
			if ((int) $_GET['pe_id'])
			{
				$read = array('pe_id' => (int)$_GET['pe_id']);
				if((int)$_GET['pm_id'])
				{
					$read['pm_id'] = (int)$_GET['pm_id'];
				}
				$this->read($read);
			}
			else if ($_GET['pe_id'] && strpos($_GET['pe_id'],':') > 0)
			{
				list($app,$app_id,$pe_id) = explode(':',$_GET['pe_id']);
				$this->read((int) $pe_id);
			}
			if ($this->data['pe_id'])
			{
				if (!$this->check_acl(Acl::READ))
				{
					$this->tpl->location(array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.index',
						'msg' => lang('Permission denied !!!'),
					));
				}
				if (!$this->check_acl(Acl::EDIT)) $view = true;
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
		if($this->data['pe_completion'])
		{
			$this->data['pe_completion'] = (int)$this->data['pe_completion'];
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
		// Set classes on constraints
		if(is_array($content['pe_constraints']))
		{
			foreach($content['pe_constraints'] as &$constraint)
			{
				$constraint['class'] = $constraint['pe_id_start'] == $this->data['pe_id'] ? 'source' : 'target';
			}
		}

		//_debug_array($content);
		$sel_options = array(
			// These match the gantt chart
			'type' => projectmanager_constraints_so::$constraint_types,
		);
		$readonlys = array(
			'delete' => !$this->data['pe_id'] || !$this->check_acl(Acl::DELETE),
			'edit' => !$view || !$this->check_acl(Acl::EDIT),
			'eroles_edit' => $view,
		);
		// display eroles tab only if it's enabled in Api\Config and for supported erole Egw\Applications
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
			unset($content['pe_planned_budget']);
			unset($content['pe_used_budget']);
		}
		if ($view)
		{
			foreach($this->db_cols as $name)
			{
				$readonlys[$name] = true;
			}
			$readonlys['pe_remark'] = true;
			$readonlys['save'] = $readonlys['apply'] = true;
			$readonlys['pe_constraints'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager') . ' - ' .
			($this->data['pm_id'] ? ($view ? lang('View project-elements') : lang('Edit project-elements')) : lang('Add project-elements'));
		$this->tpl->read('projectmanager.elements.edit');
		$this->tpl->exec('projectmanager.projectmanager_elements_ui.edit',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * query projects for nextmatch in the projects-list
	 *
	 * reimplemented from Api\Storage\Base to disable action-buttons based on the Acl and make some modification on the data
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 */
	function get_rrows(&$query_in,&$rows,&$readonlys)
	{
		if(!$query_in['csv_export'])
		{
			if($query_in['col_filter']['pm_id'])
			{
				$this->pm_id = (int)$query_in['col_filter']['pm_id'];
			}
			else if ( $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'])
			{
				$this->pm_id = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'];
			}
			if (!property_exists ($this, 'pm_id') || !$this->pm_id)
			{
				return 0;
			}
			if(!$this->project->check_acl(Acl::READ, $this->pm_id))
			{
				return 0;
			}
		}

		// Check for filter change, need to get totals
		$session = Api\Cache::getSession('projectmanager', 'projectelements_list');
		$get_totals = $query_in['start'] === 0 || ($session && $session['filter'] != $query_in['filter']) || !$session && $query_in['filter'];
		$query=$query_in;
		unset($query_in['col_filter']['parent_id']);
		if(!$query_in['csv_export'])
		{
			Api\Cache::setSession('projectmanager', 'projectelements_list',
				array_diff_key ($query_in, array_flip(array('rows','actions','action_links','placeholder_actions'))));
		}

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
		$GLOBALS['egw']->session->commit_session();

		if ($this->status_filter[$query['filter']])
		{
			$query['col_filter']['pe_status'] = $this->status_filter[$query['filter']];

			if ($query['col_filter']['pe_status'][0] === '!')
			{
				$query['col_filter'][] = 'pe_status != '.$this->db->quote(substr($query['col_filter']['pe_status'], 1));
				unset($query['col_filter']['pe_status']);
			}
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
		// Sub-grid queries
		if($query['col_filter']['parent_id'])
		{
			list(,$query['col_filter']['pm_id']) = explode(':',$query['col_filter']['parent_id']);
			$sub_query = true;
		}
		unset($query['col_filter']['parent_id']);

		// cumulate eg. timesheets in also included infologs
		$query['col_filter']['cumulate'] = !($query['filter2'] & 4);
		$total = parent::get_rows($query,$rows,$readonlys,true);
		unset($query['col_filter']['cumulate']);

		// adding the project itself as first line
		if(!$sub_query)
		{
			$self = $this->updateElement('projectmanager',$this->pm_id);
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
		}

		// Re-init user preference, $this->update() changes it indirectly
		Api\DateTime::init();

		$readonlys = array();
		$budget_rights = $this->project->check_acl(EGW_ACL_BUDGET);
		foreach($rows as $n => &$row)
		{
			if ($n && !$this->check_acl(Acl::EDIT,$row))
			{
				$row['class'] .= ' rowNoEdit';
			}
			if ($n && !$this->check_acl(Acl::DELETE,$row))
			{
				$row['class'] .= ' rowNoDelete';
			}
			// Don't show sub triangle for first project (self)
			$row['is_parent'] = ($row['pe_app'] == 'projectmanager') && ($sub_query ? true: $n );

			if (!$budget_rights)
			{
				unset($row['pe_used_budget']);
				unset($row['pe_planned_budget']);
			}
			if ($n || $sub_query)
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
			$row['pe_completion_icon'] = $row['pe_completion'] == 100 ? 'done' : 'ongoing';

			$custom_app_icons[$row['pe_app']][] = $row['pe_app_id'];

			$row['elem_id'] = $row['pe_app'].':'.$row['pe_app_id'].':'.$row['pe_id'];
			// add pe links
			if ($query['filter2']&3)
			{
				if ($this->prefs['show_links'] &&
					(isset($row['pe_all_links']) || ($row['pe_all_links'] = Link::get_links($row['link']['app'],$row['link']['id'],'',true))))
				{
					foreach ($row['pe_all_links'] as $link)
					{
						if ($show_links != 'none' &&
							!($row['pm_link']['id'] == $link['id'] && $link['app'] == 'projectmanager') &&
							!($row['pm_id'] == $link['id'] && $link['app'] == 'projectmanager') &&
							($show_links == 'all' || ($show_links == 'links') === ($link['app'] != Link::VFS_APPNAME)))
						{
							$row['pe_links'][] = $link;
						}
					}
				}
 			}
			//Set icon for milestone as Milestone is not an application therefore, its icon won't get set like the others
			if ($row['pe_app'] === 'pm_milestone')
			{
				$row['pe_icon'] = 'projectmanager/milestone';
			}
		}

		if ($this->prefs['show_custom_app_icons'] || $this->prefs['show_infolog_type_icon'])
		{
			$custom_app_icons['location'] = 'pm_custom_app_icons';
			$custom_app_icons = Api\Hooks::process($custom_app_icons);
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
							$row['pe_completion_icon'] = $app_info['status_icon'] ? $app_info['status_icon'] : $app_info['status'];
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
				$row['ignored'] = ($row['pe_status'] == 'ignore') ? 'projectmanager/ignored' : '';
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
		if ($query['cat_id']) $rows['no_cat_id'] = true;
		// calculate the filter-specific summary if we have a filter change
		if ($get_totals)
		{
			$totals = $this->summary(null,$query['col_filter']);
			foreach($totals as $field => $value)
			{
				if ($budget_rights || !in_array($field, array('pe_planned_budget', 'pe_used_budget')))
				{
					$rows['total_' . $field] = $value;
				}
				// etemplate requires unique IDs, these ones are in the second times column
				if(in_array($field, array('pe_planned_time', 'pe_used_time')))
				{
					$rows['total_' . $field . '_2'] = $value;
				}
			}
		}
		if ((int)static::DEBUG >= 2 || static::DEBUG == 'get_rows')
		{
			projectmanager_bo::debug_message("projectmanager_elements_ui::get_rows(".print_r($query,true).") total=$total, rows =".print_r($rows,true)."readonlys=".print_r($readonlys,true));
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
	protected function get_actions()
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
				'enableId' => '^(?!.*pm_milestone).*:[0-9]+:[1-9]',
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
				'hideOnMobile' => true
			),
			'add' => array (
				'caption' => 'Add new',
				'group' => ++$group,
				'icon' => 'add',
			),
			'add_existing' => array(
				'caption' => 'Add existing',
				'group' => $group,
				'nm_action' => 'open_popup',
			),
			'sync_all' => array(
				'caption' => 'Synchronise all',
				'icon' => 'agt_reload',
				'hint' => 'necessary for project-elements doing that not automatic',
				'group' => ++$group,
			),
			'cat' => Etemplate\Widget\Nextmatch::category_action(
				'projectmanager',$group,'Change category','cat_'
				)+array(
					'disableClass' => 'rowNoEdit',

			),
			'erole' => array(
				'caption' => 'Element roles',
				'group' => $group,
				'disableClass' => 'rowNoEdit',

			),
			'ignore' => array(
				'caption' => 'Ignore that entry',
				'group' => $group,
				'disableClass' => 'rowNoEdit',
				'checkbox' => true,
				'isChecked' => 'javaScript:app.projectmanager.is_ignored',
				'onExecute' => 'javaScript:app.projectmanager.ignore_action'
			)
		);
		$group++;
		if ($GLOBALS['egw_info']['user']['apps']['timesheet'])
		{
			$actions['timesheet'] = array(
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'egw_open' => 'add-timesheet',
				'allowOnMultiple' => false,
				'group' => $group,
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['infolog'])
		{
			$actions['infolog-subs'] = array(
				'icon' => 'infolog/navbar',
				'caption' => 'View subs',
				'hint' => 'View all subs of this entry',
				'group' => $group,
				'allowOnMultiple' => false,
				'enableId' => '^infolog:',
				'enableClass' => 'infolog_rowHasSubs',
				'url' => 'menuaction=infolog.infolog_ui.index&action=sp&action_id=$id',
				'targetapp' => 'infolog',
				'hideOnDisabled' => true
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'allowOnMultiple' => false,
				'group' => $group,
				'onExecute' => 'javaScript:app.projectmanager.show_filemanager',
			);
		}
		$actions += array(
			'documents' => projectmanager_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['projectmanager']['document_dir'],
				$group, 'Insert in document', 'document_'
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this project-element, does NOT remove the linked entry',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
				'hideOnMobile' => true
			),
		);
		if(!$this->config['enable_eroles'])
		{
			unset($actions['erole']);
		}
		else
		{
			$actions['erole']['children'] = array();
			foreach($this->eroles->get_free_eroles() as $erole)
			{
				$actions['erole']['children']['erole_'.$erole['role_id']] = $erole + array(
					'caption' => $erole['role_title'],
					'group' => $actions['erole']['group'],
					'enabled' => 'javaScript:app.projectmanager.is_erole_allowed'
				);
			}
		}

		//Create app list for "Add new" menu items
		$app_list = Link::app_list('add');
		// Bookmarks doesn't support link via URL this way
		unset($app_list['bookmarks']);
		$actions['add']['children'] = array();
		foreach ($app_list as $inx => $val)
		{
			$actions['add']['children']['act-'.$inx] = array(
				'caption' => $val,
				'icon' => $inx.'/navbar',
				'onExecute' => 'javaScript:app.projectmanager.add_new',
			);
		}
		// Milestone isn't an app, so is not returned by app_list()
		$actions['add']['children']['act-pm_milestone'] = array(
			'caption' => 'Milestone',
			'icon' => 'projectmanager/milestone',
			'onExecute' => 'javaScript:app.projectmanager.add_new',
		);
		//error_log(array2string($actions));
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

		if ((int) static::DEBUG >= 1 || static::DEBUG == 'index') projectmanager_bo::debug_message("projectmanager_elements_ui::index(".print_r($content,true).",$msg)");

		// store the current project (only for index, as popups may be called by other parent-projects)
		$GLOBALS['egw']->preferences->add('projectmanager','current_project', $this->project->data['pm_id']);
		$GLOBALS['egw']->preferences->save_repository();

		if ($_GET['msg']) $msg = $_GET['msg'];

		if ((int)$_GET['delete'])
		{
			$content['nm']['selected'] = array((int)$_GET['delete']);
			$content['nm']['action'] = 'delete';
		}
		if ($content['nm']['action'])
		{
			$this->action($content['nm']['action'], $content['nm']['selected'], $msg, $content['add_existing_popup']);
		}
		$content = array(
			'nm' => Api\Cache::getSession('projectmanager', 'projectelements_list'),
			'msg'      => $msg,
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.projectmanager_elements_ui.get_rrows',
				'num_rows'       => 0, // No data when first sent
				'filter'         => 'used',// I initial value for the filter
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
				'row_id' => 'elem_id',	// pe_app:pe_app_id:pe_id
				'dataStorePrefix' => 'projectmanager_elements',
				'parent_id'      => 'parent_id',
				'is_parent'		 => 'is_parent'
			);
			// use the state of the last session stored in the user prefs
			if (($state = @unserialize($this->prefs['pe_index_state'])))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}


		}
		// If no PM ID, don't get initial rows
		if(!$this->pm_id)
		{
			$content['nm']['num_rows'] = 0;
		}
		// Set duration format once for all
		$content['duration_format']= ','.$this->config['duration_format'].',,1';

		// Put totals in the right place for initial load
		$totals = $this->summary($this->project->data['pm_id'],$content['nm']['col_filter']);
		foreach($totals as $field => $value)
		{
			$content['nm']['total_' . $field] = $value;
		}

		// Need to set this each time.  Nextmatch widget removes string keys from action, so we can't
		// edit sync_all
		$content['nm']['actions'] = $this->get_actions();

		// add "buttons" only with add-rights
		if ($this->project->check_acl(Acl::ADD))
		{
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
			$content['nm']['actions']['sync_all']['enabled'] = false;
		}
		$content['nm']['link_add'] = array(
			'to_id'    => $this->pm_id,
			'to_app'   => 'projectmanager',
			'add_app'  => 'infolog',
		);
		$this->tpl->read('projectmanager.elements.list');

		// set id for automatic linking via quick add
		$GLOBALS['egw_info']['flags']['currentid'] = $this->pm_id;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Elementlist') .
			': ' . $this->project->data['pm_number'] . ': ' .$this->project->data['pm_title'] ;

		// fill the sel_options Applications
		$sel_options ['pe_app'] = Link::app_list('add_app');
		$this->tpl->setElementAttribute('nm[link_add]', 'application', Link::app_list('add'));
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

		if (!is_array($actions = Api\Cache::getSession('projectmanager', 'document_actions')))
		{
			$actions = array();
			if (($files = Vfs::find($this->prefs['document_dir'],array('need_mime'=>true),true)))
			{
				foreach($files as $file)
				{
					// return only the mime-types we support
					if (!projectmanager_merge::is_implemented($file['mime'],substr($file['name'],-4))) continue;

					$actions['document-'.$file['name']] = $file['name'];
				}
			}
			Api\Cache::setSession('projectmanager', 'document_actions', $actions);
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
	function action($action,$checked,&$msg, $add_existing)
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
		if (substr($action,0,6) == 'erole_') list($action,$erole) = explode('_',$action);
		if (substr($action,0,7) == 'ignore_') list($action,$ignore) = explode('_',$action);

		switch($action)
		{
			case 'add_existing':
				$btn = $add_existing['link_action'];
				$link_id = $add_existing['link']['id'];
				$app = $add_existing['link']['app'];
				if($btn['cancel'] || !$link_id)
				{
					break;
				}
				$title = Link::title($app, $link_id);

				if($btn['add'])
				{
					$action_msg = lang('linked to %1', $title);
					if(Link::link('projectmanager', $this->pm_id, $app, $link_id))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				return $failed == 0;

			case 'cat':
				if (!$this->project->check_acl(Acl::ADD) ||
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
			case 'erole':
				foreach($checked as $id)
				{
					$element = $this->read($id);
					if($element['pe_eroles'] && !is_array($element['pe_eroles']))
					{
						$element['pe_eroles'] = explode(',',$element['pe_eroles']);
					}
					else
					{
						$element['pe_eroles'] = array();
					}
					$element['pe_eroles'][] = $erole;

					if($this->save(array('pe_eroles' => implode(',',array_unique($element['pe_eroles'])))))
					{

					}
					else
					{
						$msg = lang('%1 element(s) updated',count($checked));
					}
				}
				break;
			case 'ignore':
				$success = $failed = 0;
				foreach($checked as $id)
				{
					$element = $this->read(array('pe_id' => $id));
					if(!$this->save(array('pe_status' => $ignore ? 'ignore' : 'new')))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				$msg = lang('%1 element(s) updated',$success);
				return $failed==0;
				break;
			case 'delete':
				if (!$this->project->check_acl(Acl::ADD))
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
				if ($this->project->check_acl(Acl::ADD))
				{
					$msg = lang('%1 element(s) updated',$this->sync_all());
					return true;
				}
				else
				{
					$msg = lang('Permission denied !!!');
				}
				break;

			case 'document':
				$document_projects = array();
				$contacts = array();
				$eroles = array();
				if(count($checked) == 0)
				{
					// Use all, from merge selectbox in side menu
					$query = $old_query = Api\Cache::getSession('projectmanager', 'projectelements_list');
					$query['num_rows'] = -1;        // all
					$this->get_rows($query,$selection,$readonlys);
					foreach($selection as $key => $element)
					{
						if (!is_int($key)) continue;	// ignore string keys from get_rows
						if($element['pe_id'] && is_numeric($element['pe_id'])) $checked[] = $element['pe_id'];
					}

					// Reset nm params
					Api\Cache::setSession('projectmanager', 'projectelements_list', $old_query);

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
	 * Run given action on given path(es) and return array/object with values for keys 'msg', 'errs', 'dirs', 'files'
	 *
	 * @param string $action eg. 'delete', ...
	 * @param array $selected selected path(s)
	 * @param string $data Action specific data
	 * @see static::action()
	 */
	public static function ajax_action($action, $selected, $data = array())
	{

		$response = EGroupware\Api\Json\Response::get();

		switch($action)
		{
			case 'ignore':
				$ui = new projectmanager_elements_ui();
				$checked = array();
				foreach($selected as $entry)
				{
					list($prefix,$checked[]) = explode('::',$entry);
				}
				$msg = '';
				if($ui->action('ignore_'.(!!$data),$checked,$msg, $add_existing))
				{
					// We could just update the selected rows here, but this is easier and gets
					// the totals too
					$response->call('egw.refresh',$msg,'projectmanager');
				}
				else
				{
					$response->error($msg);
				}
				break;
		}

		//error_log(__METHOD__."('$action',".array2string($selected).') returning '.array2string($arr));
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
		}
		unset($ids['contacts']);

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
				!Api\Storage\Merge::is_export_limit_excepted() && count($ids['elements']) > (int)$document_merge->export_limit)
			{
				return lang('No rights to export more then %1 entries!',(int)$document_merge->export_limit);
			}
			$document_merge->elements = $ids['elements'];
		}
		unset($ids['elements']);

		return $document_merge->download($document, $ids, isset($name) ? $name : null, $this->prefs['document_dir']);
	}
}
