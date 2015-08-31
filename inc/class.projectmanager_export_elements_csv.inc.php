<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * export project elements to CSV
 */
class projectmanager_export_elements_csv implements importexport_iface_export_plugin {

	// Used in conversions
	static $types = array(
		'select-account' => array('pe_creator','pe_modifier'),
		'date-time' => array('pe_modified','pe_created','pe_synced'),
		'date' => array('pe_planned_start','pe_planned_end', 'pe_real_start', 'pe_real_end'),
		'select-cat' => array('cat_id'),
	);

	/**
	 * If exporting all elements from a single project, name the file for the project
	 */
	protected $project_name = '';

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;
		$no_project = true;

		if($options['pm_id']) {
			$_REQUEST['pm_id'] = $options['pm_id'];
			$no_project = false;
		} elseif(!$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project']) {
			// Fake a pm_id so elements_ui works
			$_REQUEST['pm_id'] = 1;
		}
		$ui = new projectmanager_elements_ui();
		$selection = array();
		if ($options['selection'] == 'selected') {
			// ui selection with 'Use search results'
			$query = $old_query = $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager');
			$query['num_rows'] = -1;	// all
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session

			// Getting elements from the project list, use those search results
			if($no_project)
			{
				$p_query = $old_p_query = $GLOBALS['egw']->session->appsession('project_list','projectmanager');
				$pm_ui = new projectmanager_ui();
				$p_query['num_rows'] = -1;        // all
				$p_query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
				$count = $pm_ui->get_rows($p_query,$selection,$readonlys);

				// Reset nm params
				$GLOBALS['egw']->session->appsession('project_list','projectmanager', $old_p_query);
				$query['col_filter']['pm_id'] = array();
				if($count)
				{
					foreach($selection as $project)
					{
						if($project['pm_id'] && is_numeric($project['pm_id'])) $query['col_filter']['pm_id'][] = $project['pm_id'];
					}
				}
				else
				{
					// Project list is empty, use empty list (otherwise all projects would be OK)
					$query['num_rows'] = 0;
				}
				$selection = array();
			}

			// Clear the PM ID or results will be restricted to that project
			unset($ui->pm_id);

			if($query['num_rows']) $ui->get_rows($query,$selection,$readonlys);

			// Reset nm params
			unset($query['num_rows']);
			$GLOBALS['egw']->session->appsession('projectelements_list','projectmanager', $old_query);
		}
		elseif ( $options['selection'] == 'all' ) {
			$_query = $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager');
			// Clear the PM ID or results will be restricted
			unset($ui->pm_id);

			$query = array(
				'num_rows' => -1,		// all
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);
			$ui->get_rows($query,$selection,$readonlys);
			$GLOBALS['egw']->session->appsession('projectelements_list','projectmanager', $_query);
		} else {
			$_query = $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager');
			$query = array(
				'num_rows' => -1,
				'col_filter' => array('pm_id'	=> $options['pm_id']),
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);
			$ui->get_rows($query,$selection,$readonlys);
			$GLOBALS['egw']->session->appsession('projectelements_list','projectmanager', $_query);
			$this->project_name = egw_link::title('projectmanager', $options['pm_id']);
		}
		if($no_project)
		{
			// Reset faked project ID
			unset($_REQUEST['pm_id']);
		}

		$this->export_object = new importexport_export_csv($_stream, (array)$options);
		$this->export_object->set_mapping($options['mapping']);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $record) {
			if(!is_array($record) || !$record['pe_id']) continue;
			if(is_array($record['pe_resources'])) {
				$resources = array();
				foreach($record['pe_resources'] as $resource) {
					$resources[] = common::grab_owner_name($resource);
				}
				$record['pe_resources'] = implode(',', $resources);
			}
			$element = new projectmanager_egw_record_element();
			$element->set_record($record);
			if($options['mapping']['pm_title']) {
				$project = ExecMethod('projectmanager.projectmanager_bo.read', $element->pm_id);
				$element->pm_title = $project['pm_title'];
			}

			if($options['convert']) {
				importexport_export_csv::convert($element, self::$types);
			}
			$this->convert($element, $options);
			$this->export_object->export_record($element);
			unset($element);
		}
		return $this->export_object;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Project element CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports a list of project elements to a CSV File.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	public static function get_mimetype() {
		return 'text/csv';
	}

	/**
	 * Suggest a file name for the downloaded file
	 * No suffix
	 */
	public function get_filename()
	{
		if($this->project_name) return $this->project_name . ' ' . lang('Elements');

		if(is_object($this->export_object) && $this->export_object->get_num_of_records() == 1)
		{
			return $this->export_object->record->get_title();
		}
		return 'egw_export_'.lang('elements') . '-' . date('Y-m-d');
	}

	/**
	 * return html for options.
	 * this way the plugin has all opportunities for options tab
	 *
	 */
	public function get_options_etpl() {
	}

	/**
	 * returns selectors information
	 *
	 */
	public function get_selectors_etpl() {
		return array(
			'name'	=> 'projectmanager.export_elements_csv_selectors',
			'content'	=> array(
				'selection' => 'selected',
				'pm_id' => $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project']
			)
		);
	}

	/**
	 * Do some conversions from internal format and structures to human readable / exportable
	 * formats
	 *
	 * @param projectmanager_egw_record_project $record Record to be converted
	 */
	protected static function convert(projectmanager_egw_record_element &$record, array $options = array()) {
		foreach(array('pe_used_time', 'pe_planned_time', 'pe_replanned_time') as $duration) {
			switch($options[$duration]) {
				case 'd':
					$record->$duration = round($record->$duration / 480, 2);
					break;
				case 'h':
					$record->$duration = round($record->$duration / 60, 2);
					break;
			}
			if($options['include_duration_unit']) {
				$record->$duration .= $options[$duration];
			}
		}
	}
}
