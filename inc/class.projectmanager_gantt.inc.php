<?php

class projectmanager_gantt extends projectmanager_elements_bo {

	public $public_functions = array(
		'chart'	=> true,
		'ajax_gantt_project' => true,
	);
	public function __construct() {
		parent::__construct();
	}

	public function chart() {
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
		// Use Google CDN, it has to be loaded first
		$GLOBALS['egw_info']['flags']['java_script_thirst'] .= '<script src="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/dojo.xd.js" type="text/javascript"></script>';
		$GLOBALS['egw_info']['flags']['css'] .= '@import url("http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/resources/dojo.css");';
		$GLOBALS['egw_info']['flags']['css'] .= '@import url("http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojox/gantt/resources/gantt.css");';

		// Local source
		//egw_framework::validate_file('dojo/dojo','dojo');
/*
		egw_framework::includeCSS('/phpgwapi/js/dojo/dojo/resources/dojo.css');
		egw_framework::includeCSS('/phpgwapi/js/dojo/dijit/themes/claro/claro.css');
		egw_framework::includeCSS('/phpgwapi/js/dojo/dojox/gantt/resources/gantt.css');
*/

		egw_framework::validate_file('.','gantt','projectmanager');
		egw_framework::includeCSS('projectmanager','gantt');
		$content .= '<script type="text/javascript">var gantt_project_ids = ' . json_encode($pm_id) . ';
var gantt_hours_per_day = ' . ($GLOBALS['egw_info']['user']['preferences']['calendar']['workdayends'] - $GLOBALS['egw_info']['user']['preferences']['calendar']['workdaystarts']) . ';
		</script>';
		$content .= '<div class="ganttContent"><div id="gantt"></div></div>';
		$GLOBALS['egw']->framework->render($content, 'Test', true);
	}

	public function ajax_gantt_project($project_id) {
		if(!is_array($project_id)) {
			$project_id = explode(',',$project_id);
		}
		$projects = array();
		foreach($project_id as $pm_id) {
			$projects[] = $this->add_project($pm_id);
		}
		$response = egw_json_response::get();
		$response->data($projects);
	}

	// Get the data into required format
	protected function add_project($pm_id) {
		if ($pm_id != $this->project->data['pm_id'])
		{
			if (!$this->project->read($pm_id) || !$this->project->check_acl(EGW_ACL_READ))
			{
				return;
			}
		}
		$project = $this->project->data + array(
			'name'	=>	egw_link::title('projectmanager', $this->project->data['pm_id']),
		);
		if(is_array($project['pm_members'])) {
			foreach($project['pm_members'] as $uid => &$member_data) {
				$member_data['name'] = common::grab_owner_name($member_data['member_uid']);
			}
		}
		$project['elements'] = $this->add_elements($pm_id);
		return $project;
	}

	protected function add_elements($pm_id) {
		$elements = array();
		$filter = array(
			'pm_id'	=> $pm_id
		);
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
		$extra_cols = array(
			$start.' AS pe_start',
			$end.' AS pe_end',
		);

		$element_index = array();
		foreach((array) $this->search(array(),false,'pe_start,pe_end',$extra_cols,
                        '',false,'AND',false,$filter) as $pe)
                {
                        //echo "$line: ".print_r($pe,true)."<br>\n";
                        if (!$pe) continue;

			if($pe['pe_app'] == 'projectmanager') {
				$project = $this->add_project($pe['pe_app_id']);
				if($project) $elements[] = $project;
			} else {
				$pe['pe_start'] = (int)$pe['pe_start'];
				$pe['duration'] = max(($pe['pe_end'] - $pe['pe_start']) / 3600 / 8, 0);
			
				$elements[] = $pe;
			}
			$element_index[$pe['pe_id']] = $pe;
		}
		// adding the constraints
		foreach($elements as &$pe) {
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
//_debug_array($elements);
		return $elements;
	}
}
?>
