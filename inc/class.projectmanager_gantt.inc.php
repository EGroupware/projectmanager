<?php

class projectmanager_gantt extends projectmanager_elements_bo {

	public $public_functions = array(
		'chart'	=> true,
		'ajax_gantt_project' => true,
		'ajax_update' => true
	);
	public function __construct() {
		parent::__construct();
	}

	public function chart($data = array()) {
		if (isset($_REQUEST['pm_id']))
                {
                        $pm_id = $_REQUEST['pm_id'];
                        $GLOBALS['egw']->session->appsession('pm_id','projectmanager',$pm_id);
                }
                else
                {
                        $pm_id = $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
                }
		if(!$pm_id) 
		{
			egw::redirect_link('/index.php', array(
                                'menuaction' => 'projectmanager.projectmanager_ui.index',
                                'msg'        => lang('You need to select a project first'),
                        ));
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

		egw_framework::validate_file('dhtmlxGantt/sources','dhtmlxcommon');
		egw_framework::validate_file('dhtmlxGantt/sources','dhtmlxgantt');
		egw_framework::includeCSS('/phpgwapi/js/dhtmlxGantt/codebase/dhtmlxgantt.css');
		egw_framework::validate_file('.','gantt','projectmanager');
		egw_framework::includeCSS('projectmanager','gantt');

		// Yes, we want the link registry
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;

		$content .= '<script type="text/javascript">var gantt_project_ids = ' . json_encode($pm_id) . ';
var gantt_hours_per_day = ' . ($GLOBALS['egw_info']['user']['preferences']['calendar']['workdayends'] - $GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts']) . ';
		</script>';

		// Default to project elements
		if(!array_key_exists('depth',$data)) $data['depth'] = 1;

		
		if ($pm_id != $this->project->data['pm_id'])
		{
			if (!$this->project->read($pm_id) || !$this->project->check_acl(EGW_ACL_READ))
			{
				return;
			}
			if(!$data['start']) $data['start'] = $this->project->data['pm_real_start'];
			if(!$data['end']) $data['end'] = $this->project->data['pm_real_end'];
		}

		$sel_options = array(
			'depth' => array(
				0  => '0: '.lang('Mainproject only'),
				1  => '1: '.lang('Project-elements'),
				2  => '2: '.lang('Elements of elements'),
				99 => lang('Everything recursive'),
			),
			'filter' => array(
				''        => lang('All'),
				'not'     => lang('Not started (0%)'),
				'ongoing' => lang('0ngoing (0 < % < 100)'),
				'done'    => lang('Done (100%)'),
			),
		);
		$template = new etemplate();
		$template->read('projectmanager.gantt');
		$content .= $template->exec('projectmanager.projectmanager_gantt.chart', $data, $sel_options, $readonlys, 2);
		$GLOBALS['egw']->framework->render($content, 'Test', true);
	}

	public function ajax_gantt_project($project_id, $params) {
		if(!is_array($project_id)) {
			$project_id = explode(',',$project_id);
		}
		$projects = array();
		$params = $params['exec'];


		// Parse times
		if($params['start']['str']) {
			$time = egw_time::createFromFormat(
				egw_time::$user_dateformat,
				$params['start']['str']
			);
			$params['start'] = $time->format('U');
		} else {
			$params['start'] = null;
		}
		if($params['end']['str']) {
			$time = egw_time::createFromFormat(
				egw_time::$user_dateformat,
				$params['end']['str']
			);
			$params['end'] = $time->format('U');
		} else {
			$params['end'] = null;
		}

		foreach($project_id as $pm_id) {
			$projects[] = $this->add_project($pm_id, $params);
		}
		$response = egw_json_response::get();
		$response->data($projects);
	}

	// Get the data into required format
	protected function add_project($pm_id, $params) {
		if ($pm_id != $this->project->data['pm_id'])
		{
			if (!$this->project->read($pm_id) || !$this->project->check_acl(EGW_ACL_READ))
			{
				return;
			}
		}
		$project = $this->project->data + array(
			'name'	=>	egw_link::title('projectmanager', $this->project->data['pm_id']),
			'edit'	=>	$this->project->check_acl(EGW_ACL_EDIT),
			'start'	=>	$params['planned_times'] ? $this->project->data['pm_planned_start'] : $this->project->data['pm_real_start'],
			'end'	=>	$params['planned_times'] ? $this->project->data['pm_planned_end'] : $this->project->data['pm_real_end']
		);

		// Not sure how it happens, but it causes problems
		if($project['start'] && $project['start'] < 10) $project['start'] = 0;

		if(is_array($project['pm_members'])) {
			foreach($project['pm_members'] as $uid => &$member_data) {
				$member_data['name'] = common::grab_owner_name($member_data['member_uid']);
			}
		}
		if($params['depth'])
		{
			$project['elements'] = $this->add_elements($pm_id, $params);
		}
		
		return $project;
	}

	protected function add_elements($pm_id, $params, $level = 1) {
		$elements = array();

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
	//		"$start IS NOT NULL",
	//		"$end IS NOT NULL",
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
error_log(print_r($extra_cols,true));
error_log(print_r($filter,true));
		foreach((array) $this->search(array(),false,'pe_start,pe_end',$extra_cols,
                        '',false,'AND',false,$filter) as $pe)
                {
                        //echo "$line: ".print_r($pe,true)."<br>\n";
                        if (!$pe) continue;

			if($pe['pe_app'] == 'projectmanager' && $level < $params['depth']) {
				$project = $this->add_project($pe['pe_app_id']);
				if($project) $elements[] = $project;
			} else {
				$pe['pe_start'] = (int)$pe['pe_start'];
				$pe['duration'] = ($params['planned_times'] ? $pe['pe_planned_time'] : $pe['pe_used_time']) / 60;
				if($pe['pe_end'] && !$pe['duration'])
				{
					$pe['duration'] = max((($pe['pe_end'] - $pe['pe_start']) / 3600 / 24) * $hours_per_day, 0);
					if(function_exists('date_diff')) {
						$diff = date_diff(new egw_time($pe['pe_end']), new egw_time($pe['pe_start']));
						$pe['duration'] = $diff->d * $hours_per_day + $diff->h;
					}
				}
				$pe['edit'] = $this->check_acl(EGW_ACL_EDIT, $pe);
			
				$elements[] = $pe;
			}
			$element_index[$pe['pe_id']] = $pe;
		}
		// adding the constraints
		if($params['constraints']) {
			foreach($elements as &$pe)
			{
				foreach((array)$this->constraints->search(array('pm_id'=>$pm_id, 'pe_id'=>$pe['pe_id'])) as $constraint)
				{
					if($pe['pe_id'] == $constraint['pe_id_end']) continue;
					$next_id = $constraint['pe_id_end'];
					// Chart requires constraints to respect dates
					if($pe['pe_start'] > $element_index[$next_id]['pe_start'])
					{
					//	$pe['pe_constraint'][] = $next_id;
					}
					$pe['pe_constraint'][] = $next_id;
				}
			}
		}
		return $elements;
	}

	/**
	 * User updated start date or duration from gantt chart
	 */
	public function ajax_update($changes, $params)
	{
		$params = $params['exec'];

		foreach((array)$changes as $pe_id => $values)
		{
			$this->read(array('pe_id' => (int)$pe_id));
			$keys = array();
			if(array_key_exists('duration', $values))
			{
				// Duration comes in hours
				$keys['pe_' . ($params['planned_times'] ? 'planned' : 'used') .'_time'] = $values['duration'] * 60;
			}
			if(array_key_exists('start', $values)) 
			{
				$keys['pe_' . ($params['planned_times'] ? 'planned' : 'real') . '_start'] = $values['start'];
			}
			if($keys)
			{
				$result = $this->save($keys);
			}
		}
	}
}
?>
