<?php
/**
 * ProjectManager - Gantchart creation
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray <ng@stylite.de>
 * @package projectmanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class projectmanager_gantt extends projectmanager_elements_ui {

	public $public_functions = array(
		'chart'	=> true,
		'ajax_gantt_project' => true,
		'ajax_update' => true
	);
	public function __construct() {
		parent::__construct();
	}

	public function chart($data = array())
	{

		// Find out which project we're working with
		if (isset($_REQUEST['pm_id']))
		{
			$pm_id = $_REQUEST['pm_id'];
			$GLOBALS['egw']->preferences->add('projectmanager','current_project', $pm_id);
			$GLOBALS['egw']->preferences->save_repository();
		}
		else if ($_GET['pm_id'])
		{
			// AJAX requests have pm_id only in GET, not REQUEST
			$pm_id = $_GET['pm_id'];
		}
		else if ($data['project_tree'])
		{
			$pm_id = array();
			$data['project_tree'] = is_array($data['project_tree']) ? $data['project_tree'] : explode(',',$data['project_tree']);
			foreach($data['project_tree'] as $project)
			{
				list(,$pm_id[]) = explode('::',$project,2);
			}
		}
		$pm_id = is_array($pm_id) ? $pm_id : explode(',',$pm_id);
		$this->pm_id = $pm_id[0];

		// Deal with incoming
		if($data['gantt']['action'])
		{
			$result = $this->action($data['gantt']['action'], $data['gantt']['selected'], $msg, $add_existing);
			if($msg)
			{
				egw_framework::message($msg, $result ? 'success' : 'error');
			}
		}
		if ($data['sync_all'])
		{
			$this->project = new projectmanager_bo($pm_id);
			if($this->project->check_acl(EGW_ACL_ADD))
			{
				$data['msg'] = lang('%1 element(s) updated',$this->sync_all());
			}
			unset($data['sync_all']);
		}

		if($data)
		{
			// Save settings implicitly as user preference
			$GLOBALS['egw']->preferences->add('projectmanager','gantt_planned_times',$data['planned_times']);
			$GLOBALS['egw']->preferences->add('projectmanager','gantt_constraints',$data['constraints']);
			// save prefs, but do NOT invalid the cache (unnecessary)
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);

			// Save filters in session per project
			$result = egw_cache::setSession('projectmanager', 'gantt_'.$pm_id[0], $data['gantt']);
		}
		else
		{
			$data = array(
				'planned_times' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['gantt_planned_times'],
				'constraints' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['gantt_constraints'],
				'gantt' => (array)egw_cache::getSession('projectmanager', 'gantt_'.$pm_id[0])
			);
		}
		egw_framework::includeCSS('projectmanager','gantt');
		$GLOBALS['egw_info']['flags']['app_header'] = '';

		// Yes, we want the link registry
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;

		// Default to project elements, and their children - others will be done via ajax
		if(!array_key_exists('depth',$data)) $data['depth'] = 2;


		$data['gantt'] = $data['gantt'] + array('data' => array(), 'links' => array());
		
		// Only try to load if there is an ID, or we get every task.
		if($pm_id)
		{
			foreach($pm_id as $id)
			{
				$this->add_project($data['gantt'], $id, $data);
			}
		}

		$sel_options = array(
			'filter' => array(
				''        => lang('All'),
				'not'     => lang('Not started (0%)'),
				'ongoing' => lang('Ongoing (0 < % < 100)'),
				'done'    => lang('Done (100%)'),
			),
		);
		$template = new etemplate_new();
		$template->read('projectmanager.gantt');

		$template->setElementAttribute('gantt','actions', $this->get_gantt_actions());
		
		// Allow Stylite extensions a chance
		if(class_exists('stylite_projectmanager_gantt'))
		{
			stylite_projectmanager_gantt::chart($template, $data, $sel_options, $readonlys);
		}

		$template->exec('projectmanager.projectmanager_gantt.chart', $data, $sel_options, $readonlys);
	}

	/**
	 * Get (context menu) actions for the gantt chart
	 */
	protected function get_gantt_actions()
	{
		$actions = $this->get_actions();

		// Redirect action to gantt specific JS code
		$actions['open']['onExecute'] = 'javaScript:app.projectmanager.gantt_open_action';
		$actions['edit']['onExecute'] = 'javaScript:app.projectmanager.gantt_edit_element';
		$actions['edit']['enabled'] = 'javaScript:app.projectmanager.gantt_edit_enabled';

		// Cat IDs don't get a prefix, nm does something extra to them
		$add_id = function(&$action) use (&$add_id)
		{
			$children = $action['children'];
			$action['children'] = array();
			foreach($children as $id => $sub)
			{
				if($sub['id'] == $id) continue;
				$sub['id'] = 'cat_'.$id;
				$action['children'][] = $sub;
				if($sub['children'])
				{
					$add_id($sub);
				}
			}
		};
		$add_id($actions['cat']);



		// Don't do add existing, documents or timesheet,
		// they're not implemented / tested
		unset($actions['add_existing']);
		unset($actions['timesheet']);
		unset($actions['documents']);

		return $actions;
	}

	/**
	 * Ajax callback to load the elements for a project.  The project itself
	 * (and it's elements) are already in the gantt, we're loading one level
	 * lower
	 *
	 * @param string $project_id Global (prefixed with projectmanager::) project ID
	 * @param Array $params form values
	 * @param string $parent Global (prefixed with projectmanager::) project ID to use as
	 *	parent.  All top-level results will be children of this, to allow dynamic task expansion
	 *  as well as ajax loading
	 */
	public static function ajax_gantt_project($project_id, $params, $parent = false)
	{
		if(!is_array($project_id)) {
			$project_id = explode(',',$project_id);
		}
		$data = array('data' => array(), 'links' => array());
		if(is_array($params['gantt'])) $params += $params['gantt'];
		$params = array_merge(array(
			'planned_times' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['gantt_planned_times'],
			'constraints' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['gantt_constraints']
		), $params);
		
		$params['level'] = 1;
		if(!$params['depth']) $params['depth'] = 2;

		$bo = null;
		foreach($project_id as $pm_id) {
			list(,$pm_id) = explode('::',$pm_id);
			$params['parent'] = $parent ? str_replace('projectmanager::','',$parent) : $pm_id;
			if($bo == null)
			{
				// Parent class checks $_GET for the ID, so just put it there
				$_GET['pm_id'] = (int)$pm_id;
				$bo = new projectmanager_gantt();
			}
			$projects[] = $bo->add_project($data, $pm_id, $params);
		}
		$response = egw_json_response::get();
		$response->data($data);
	}

	// Get the data into required format
	protected function &add_project(&$data, $pm_id, $params) {
		if(!$pm_id || !$this->project->check_acl(EGW_ACL_READ, $pm_id))
		{
			return;
		}
		if ($pm_id != $this->project->data['pm_id'])
		{
			if (!$this->project->read($pm_id) || !$this->project->check_acl(EGW_ACL_READ))
			{
				return array();
			}
		}
		$project = $this->project->data + array(
			'id'	=>	'projectmanager::'.$this->project->data['pm_id'],
			'text'	=>	$this->prefs['gantt_element_title_length'] ? substr(egw_link::title('projectmanager', $this->project->data['pm_id']), 0, $this->prefs['gantt_element_title_length']) : egw_link::title('projectmanager', $this->project->data['pm_id']),
			'edit'	=>	$this->project->check_acl(EGW_ACL_EDIT),
			'start_date'	=>	egw_time::to($params['planned_times'] ? $this->project->data['pm_planned_start'] : $this->project->data['pm_real_start'],egw_time::DATABASE),
			'open'	=>	$params['level'] < $params['depth'],
			'progress' => ((int)substr($this->project->data['pm_completion'],0,-1))/100,
			'parent' => $params['parent'] && $params['parent'] != $this->project->data['pm_id'] ? 'projectmanager::'.$params['parent'] : 0
		);
		// Set field for filter to filter on
		$project['filter'] = $project['pm_completion'] > 0 ? ($pe['pm_completion'] != 100 ? 'ongoing' : 'done') : 'not';

		if($params['planned_times'] ? $this->project->data['pm_planned_end'] : $this->project->data['pm_real_end'])
		{
			// Make sure we don't kill the gantt chart with too large a time span - limit to 10 years
			$start = $params['planned_times'] ? $this->project->data['pm_planned_start'] : $this->project->data['pm_real_start'];
			$end = min($params['planned_times'] ? $this->project->data['pm_planned_end'] : $this->project->data['pm_real_end'],
				strtotime('+10 years',$start)
			);
			// Avoid a 0 length project, that causes display and control problems
			// Add 1 day - 1 second to go from 0:00 to 23:59
			if($end == $start) strtotime('+1 day', $end)-1;
			$project['end_date'] = egw_time::to($end,egw_time::DATABASE);
		}
		else
		{
			$project['duration'] = $params['planned_times'] ? $this->project->data['pm_planned_time'] : 1;
		}
		// Add in planned start & end, if different
		if(!$params['planned_times'])
		{
			if($project['pm_planned_start'] && $project['pm_planned_start'] != egw_time::to($project['start_date'],egw_time::DATABASE))
			{
				$project['planned_start'] = egw_time::to((int)$project['pm_planned_start'], egw_time::DATABASE);
			}
			if($project['pm_planned_end'] && $project['pm_planned_end'] != $project['pm_real_end'])
			{
				$project['planned_end'] = egw_time::to((int)$project['pm_planned_end'], egw_time::DATABASE);
			}
		}
		
		// Not sure how it happens, but it causes problems
		if($project['start'] && $project['start'] < 10) $project['start'] = 0;

		if(is_array($project['pm_members'])) {
			foreach($project['pm_members'] as $uid => &$member_data) {
				$member_data['name'] = common::grab_owner_name($member_data['member_uid']);
			}
		}
		$data['data'][] =& $project;

		// Try to set a reasonable duration based on the project length
		if(!$params['duration_unit'])
		{
			$params['duration_unit'] = self::get_duration_unit($project);
		}
		if($params['duration_unit'] != $data['duration_unit'])
		{
			$data['duration_unit'] = $params['duration_unit'];
		}
		if($project['duration'] && $params['duration_unit'] != 'minute')
		{
			$project['duration'] = self::get_duration($data, $project['duration']);
		}

		// Milestones are tasks too
		$milestones = $this->milestones->search(array('pm_id' => $pm_id),'ms_id,ms_date,ms_title');
		foreach((array)$milestones as $milestone)
		{
			$data['data'][] = array(
				'id'	=>	'pm_milestone:'.$milestone['ms_id'],
				'pm_id' => $pm_id,
				'ms_id' => $milestone['ms_id'],
				'text'	=>	$milestone['ms_title'],
				'parent' => 'projectmanager::'.$pm_id,
				'edit'	=>	$this->project->check_acl(EGW_ACL_EDIT),
				'start_date'	=>	egw_time::to($milestone['ms_date'],egw_time::DATABASE),
				'type' => 'milestone',
				'pe_icon' => 'projectmanager/milestone'
			);
		}

		if($params['depth'])
		{
			$elements = $this->add_elements($data, $pm_id, $params, $params['level'] ? $params['level'] : 1);
			$data['data'] = array_merge($data['data'], $elements);
		}

		return $project;
	}

	protected function add_elements(&$data, $pm_id, $params, $level = 1) {
		$elements = array();

		if($level > $params['depth']) return $elements;

		// defining start- and end-times depending on $params['planned_times'] and the availible data
		foreach(array('start','end') as $var)
		{
			if ($params['planned_times'])
			{
				$$var = "CASE WHEN pe_planned_$var IS NULL THEN pe_real_$var ELSE pe_planned_$var END";
			}
			else
			{
				$$var = "CASE WHEN pe_real_$var IS NULL THEN pe_planned_$var ELSE pe_real_$var END";
			}
		}
		$filter = array(
			'pm_id'	=> $pm_id,
			"pe_status != 'ignore'",
			'cumulate' => true,
		);
		$extra_cols = array(
			$start.' AS pe_start',
			$end.' AS pe_end',
		);
		if($params['end'])
		{
			$filter[] = $start.' <= ' . (int)$params['end'];
		}
		if($params['start'])
		{
			$filter[] = $end.' >= ' . (int)$params['start'];
		}
		if($this->prefs['gantt_show_elements_by_type'])
		{
			if(!is_array($this->prefs['gantt_show_elements_by_type']))
			{
				$this->prefs['gantt_show_elements_by_type'] = explode(',',$this->prefs['gantt_show_elements_by_type']);
			}
		}
		switch ($params['filter'])
		{
			case 'not':
				$filter['pe_completion'] = 0;
				break;
			case 'ongoing':
				$filter[] = 'pe_completion!=100';
				break;
			case 'done':
				$filter['pe_completion'] = 100;
				break;
		}
		if ($params['pe_resources'])
		{
			$filter['pe_resources'] = $params['pe_resources'];
		}
		if ($params['cat_id'])
		{
			$filter['cat_id'] = $params['cat_id'];
		}


		$hours_per_day = $GLOBALS['egw_info']['user']['preferences']['calendar']['workdayends'] - $GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts'];

		$element_index = array();
		foreach((array) $this->search(array(),false,'pe_start,pe_end',$extra_cols,
                        '',false,'AND',false,$filter) as $pe)
		{
			// No element, or user prefers not to see elements from this app
			if (!$pe || ($this->prefs['gantt_show_elements_by_type'] && !in_array($pe['pe_app'], $this->prefs['gantt_show_elements_by_type'])))
			{
				continue;
			}

			// Skip milestones, they're loaded by project
			if($pe['pe_app'] == 'pm_milestone') continue;

			// Limit children for sub-projects, we just need to know there are some
			if($level > 1 && count($elements))
			{
				break;
			}

			// Check to see if we need project info
			if($pe['pe_app'] == 'projectmanager') {
				$project = true;
			}
			$pe['id'] = $pe['pe_app'].':'.$pe['pe_app_id'].':'.$pe['pe_id'];
			$pe['text'] = $this->prefs['gantt_element_title_length'] ? substr($pe['pe_title'], 0, $this->prefs['gantt_element_title_length']) : $pe['pe_title'];
			$pe['parent'] = 'projectmanager::'.$pm_id;
			$pe['start_date'] = egw_time::to((int)$pe['pe_start'],egw_time::DATABASE);
			if($pe['pe_planned_start'] && $pe['pe_planned_start'] != $pe['pe_start'])
			{
				$pe['start_date'] = egw_time::to($pe['pe_real_start'], egw_time::DATABASE);
				$pe['planned_start'] = egw_time::to((int)$pe['pe_planned_start'], egw_time::DATABASE);
			}
			$pe['duration'] = self::get_duration($data,(float)($params['planned_times'] ? $pe['pe_planned_time'] : $pe['pe_used_time']));
			if($pe['pe_end'])
			{
				// Make sure we don't kill the gantt chart with too large a time span - limit to 10 years
				$pe['end_date'] = egw_time::to(min($pe['pe_end'],strtotime('+10 years',$pe['pe_start'])),egw_time::DATABASE	);
			}
			if($pe['pe_planned_end'] && $pe['pe_planned_end'] != $pe['pe_end'])
			{
				$pe['end_date'] = egw_time::to($pe['pe_real_end'], egw_time::DATABASE);
				$pe['planned_end'] = egw_time::to((int)$pe['pe_planned_end'], egw_time::DATABASE);
			}
			$pe['progress'] = ((int)substr($pe['pe_completion'],0,-1))/100;
			$pe['edit'] = $this->check_acl(EGW_ACL_EDIT, $pe);

			// Set field for filter to filter on
			$pe['filter'] = $pe['pe_completion'] > 0 ? ($pe['pe_completion'] != 100 ? 'ongoing' : 'done') : 'not';

			// Fix elements that would be 0 duration and cause problems
			if(!($pe['duration'] || $pe['end_date']))
			{
				$pe['end_date'] = $pe['start_date'];
			}
			$elements[] = $pe;

			$element_index[$pe['pe_id']] = $pe;
		}

		// Get project children
		if($project)
		{
			foreach($elements as $e_id => $pe)
			{
				// 0 duration tasks must be handled specially to avoid errors
				if(!$pe['duration']) $pe['duration'] = 1;
				$params['level'] = $level + 1;
				$params['parent'] = $pm_id;
				if($pe['pe_app'] == 'projectmanager')
				{
					$p =& $this->add_project($data, $pe['pe_app_id'], $params);
					$p += $pe;
					unset($elements[$e_id]);
				}
				unset($params['parent']);
			}
		}

		// adding the constraints for found elements
		if($params['constraints'] && count($element_index) > 0)
		{
			foreach((array)$this->constraints->search(array('pm_id'=>$pm_id, 'pe_id'=>array_keys($element_index)),false) as $constraint)
			{
				// IDs have to match what we give the gantt chart
				$start = $element_index[$constraint['pe_id_start']];
				$end = $element_index[$constraint['pe_id_end']];
				$constraint['pe_id_start'] = $start ? $start['pe_app'].':'.$start['pe_app_id'].':'.$start['pe_id'] : 'pm_milestone:'.$constraint['ms_id'];
				$constraint['pe_id_end'] = $end ? $end['pe_app'].':'.$end['pe_app_id'].':'.$end['pe_id'] : 'pm_milestone:'.$constraint['ms_id'];
				$data['links'][] = array(
					'id' => $constraint['pm_id'] . ':'.$constraint['pe_id_start'].':'.$constraint['pe_id_end'],
					'source' => $constraint['pe_id_start'],
					'target' => $constraint['pe_id_end'],
					// TODO: Get proper type
					'type' => $constraint['type']
				);
			}
		}
		return $elements;
	}

	/**
	 * User updated start date or duration from gantt chart
	 */
	public static function ajax_update($values, $mode, $params)
	{
		if($params['planned_times'] == 'false') $params['planned_times'] = false;

		// Sub project - handle as project, or a tree loop might occur
		if($values['pe_id'] && $values['pe_app'] == 'projectmanager' && $values['pe_app_id'])
		{
			unset($values['pe_id']);
		}

		// Handle duration scaling
		if($params['gantt']['duration_unit'] && $values['duration'])
		{
			switch($params['gantt']['duration_unit'])
			{
				case 'year':
					$values['duration'] *= 52;
				case 'week':
					$values['duration'] *= 7;
				case 'day':
					$values['duration'] *= 24;
				case 'hour':
					$values['duration'] *= 60;
			}
		}
			
		// Don't change timesheets
		if($values['pe_app'] == 'timesheet')
		{
			egw_json_response::get()->call('egw.message',lang('Editing timesheets is not supported, edit the entry directly'), 'error');
			return;
		}
		if(class_exists('stylite_projectmanager_gantt') && $params['datasource_update'])
		{
			$handled = stylite_projectmanager_gantt::ajax_update($values, $mode, $params);
			if($handled) return;
		}
		else if(!$GLOBALS['egw_info']['user']['preferences']['projectmanager']['skip_stylite_warning'])
		{
			// Only tell them once
			$GLOBALS['egw']->preferences->add('projectmanager','skip_stylite_warning', true);
			$GLOBALS['egw']->preferences->save_repository();
			egw_json_response::get()->apply('egw.message', Array(lang('Direct update from Gantt chart requires <a href="http://www.egroupware.org/products">Stylite EGroupware Enterprise Line (EPL)</a>.'
			. '<br />Project elements will be updated but the associated datasource will not.'),'info'));
		}

		// Needed for field constants
		include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

		$update_mask = (int)PM_COMPLETION;
		$update_mask |= ($params['planned_times'] ?
			PM_PLANNED_TIME | PM_PLANNED_START | PM_PLANNED_END :
			PM_USED_TIME | PM_REAL_START | PM_REAL_END
		);

		if($values['pe_id'])
		{
			$pe_bo = new projectmanager_elements_bo((int)$values['pm_id']);
			$pe_bo->read(array('pe_id' => (int)$values['pe_id']));
			$update_mask = $update_mask | $pe_bo->data['pe_overwrite'];
			$keys = array('pe_overwrite' => $update_mask);
			$keys['pe_completion'] = (int)($values['progress'] * 100).'%';
			if($mode == 'resize' && array_key_exists('duration', $values))
			{
				$keys['pe_' . ($params['planned_times'] ? 'planned' : 'used') .'_time'] = $values['duration'];
			}
			if(array_key_exists('start_date', $values))
			{
				$keys['pe_' . ($params['planned_times'] ? 'planned' : 'real') . '_start'] = egw_time::to($values['start_date'],'ts');
			}
			if(array_key_exists('end_date', $values))
			{
				$keys['pe_' . ($params['planned_times'] ? 'planned' : 'real') . '_end'] = egw_time::to($values['end_date'],'ts');
			}
			if($keys)
			{
				$result = $pe_bo->save($keys,true, $update_mask);
			}
		}
		else if ($values['ms_id'])
		{
			// Update milestone
			$pe_bo = new projectmanager_elements_bo((int)$values['pm_id']);
			$milestone = $pe_bo->milestones->read((int)$values['ms_id']);
			$pe_bo->milestones->save(array('ms_date' => egw_time::to($values['start_date'],'ts')));
		}
		else if ($values['pm_id'])
		{
			$pm_bo = new projectmanager_bo((int)$values['pm_id']);
			$keys = array('pm_overwrite' => $update_mask | $pm_bo->data['pm_overwrite']);
			$keys['pm_completion'] = (int)($values['progress'] * 100).'%';
			if(array_key_exists('duration', $values))
			{
				$keys['pm_' . ($params['planned_times'] ? 'planned' : 'used') .'_time'] = $values['duration'];
			}
			if(array_key_exists('start_date', $values))
			{
				$keys['pm_' . ($params['planned_times'] ? 'planned' : 'real') . '_start'] = egw_time::to($values['start_date'],'ts');
			}
			if(array_key_exists('end_date', $values))
			{
				$keys['pm_' . ($params['planned_times'] ? 'planned' : 'real') . '_end'] = egw_time::to($values['end_date'],'ts');
			}
			if($keys)
			{
				$result = $pm_bo->save($keys);
			}
		}
		else if ($values['id'] && $values['source'] && $values['target'])
		{
			// Link added or removed
			$pe_bo = new projectmanager_elements_bo((int)$pm_id);

			list(,$pm_id) = explode('::',$values['parent']);
			list(,$m_start_id,$start_id) = explode(':',$values['source']);
			list(,$m_end_id,$end_id) = explode(':',$values['target']);
			$keys = array(
				'pm_id' => $pm_id,
				'pe_id_start' => (int)$start_id,
				'pe_id_end' => (int)$end_id,
				'ms_id' => !(int)$start_id ? $m_start_id : (!(int)$end_id ? $m_end_id : 0),
				'type' => $values['type']
			);
			// Gantt chart gives new links integer IDs
			if($values['id'] && is_numeric($values['id']))
			{
				$pe_bo->constraints->save($keys);

				// Return the new key so we can tell new from old
				egw_json_response::get()->data($keys['pm_id'] . ':'.$values['source'].':'.$values['target']);
			}
			else if ($values['id'])
			{
				$pe_bo->constraints->delete($keys);
			}
		}
		else
		{
			error_log(__METHOD__.' ['.__LINE__.']:'.array2string($values));
		}
		//error_log(__METHOD__ .' Save ' . array2string($keys) . '= ' .$result);
	}

	/**
	 * Set an appropriate duration unit based on start/end dates
	 *
	 */
	protected static function get_duration_unit($task)
	{
		$start = new egw_time($task['start_date']);
		$end = new egw_time($task['end_date']);
		$diff = $start->diff($end);

		// Determine a good unit.
		// Values arbitrarily chosen
		if($diff->y > 5)
		{
			$duration_unit = 'year';
		}
		else if ($diff->days > 90)
		{
			$duration_unit = 'week';
		}
		else if ($diff->days > 28)
		{
			$duration_unit = 'hour';
		}
		else
		{
			$duration_unit = 'minute';
		}
		
		return $duration_unit;
	}

	/**
	 * PM stores duration in minutes, but gantt can't handle that for long
	 * projects, so we re-calculate to duration_unit
	 *
	 * @param array $data
	 * @param integer $duration Task duration in minutes
	 */
	protected static function get_duration(&$data, $duration)
	{
		switch($data['duration_unit'])
		{
			case 'year':
				$duration /= 52.0;
			case 'week':
				$duration /= 7.0;
			case 'day':
				$duration /= 24.0;
			case 'hour':
				$duration /= 60.0;
		}
		return round($duration,1);
	}
}
?>
