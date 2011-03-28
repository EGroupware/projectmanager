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
 * export projects to CSV
 */
class projectmanager_export_projects_csv implements importexport_iface_export_plugin {

	// Used in conversions
	static $types = array(
		'select-account' => array('pm_creator','pm_modifier'),
		'date-time' => array('pm_modified','pm_created'),
		'date' => array('pm_planned_start','pm_planned_end', 'pm_real_start', 'pm_real_end',
			// Dates from element summary
			'pe_planned_start','pe_planned_end', 'pe_real_start', 'pe_real_end'),
		'select-cat' => array('cat_id'),
	);

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$ui = new projectmanager_ui();
		$selection = array();
		if ($options['selection'] == 'selected') {
			// ui selection with checkbox 'use_all'
			$query = $GLOBALS['egw']->session->appsession('project_list','projectmanager');
			$query['num_rows'] = -1;	// all
			$ui->get_rows($query,$selection,$readonlys);
			
			// Reset nm params
			unset($query['num_rows']);
			$GLOBALS['egw']->session->appsession('project_list','projectmanager', $query);
		}
		elseif ( $options['selection'] == 'all' ) {
			$_query = $GLOBALS['egw']->session->appsession('project_list','projectmanager');
			$query = array('num_rows' => -1);	// all
			$ui->get_rows($query,$selection,$readonlys);

			// Reset nm params
			$GLOBALS['egw']->session->appsession('project_list','projectmanager', $_query);
		} else {
			$selection = explode(',',$options['selection']);
		}

		if($options['mapping']['roles']) {
			$this->roles = projectmanager_roles_so::query_list();
			foreach($this->roles as $id => $name) {
				$options['mapping'][$name] = $name;
				self::$types['select-account'][] = $name;
			}
		}
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $record) {
			if(!is_array($record) || !$record['pm_id']) continue;

			// Add in roles
			if($options['mapping']['roles']) {
				$roles = $ui->read_members($record['pm_id']);
				foreach($roles as $person) {
					$role_name = $this->roles[$person['role_id']];
					$record[$role_name][] = $person['member_uid'];
				}
			}

			// Add in element summary
			if(true || $options['mapping']['element_summary']) {
				$record += ExecMethod('projectmanager.projectmanager_elements_bo.summary',$record['pm_id']);
			}

			$project = new projectmanager_egw_record_project();
			$project->set_record($record);
			if($options['convert']) {
				importexport_export_csv::convert($project, self::$types, 'projectmanager');
				$this->convert($project, $options);
			} else {
				// Implode arrays, so they don't say 'Array'
				foreach($project->get_record_array() as $key => $value) {
					if(is_array($value)) $project->$key = implode(',', $value);
				}
			}
			$export_object->export_record($project);
			unset($project);
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Project CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports a list of projects to a CSV File.");
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
			'name'	=> 'projectmanager.export_csv_selectors',
			'content'	=> 'selected'
		);
	}

	/**
	 * Do some conversions from internal format and structures to human readable / exportable
	 * formats
	 *
	 * @param projectmanager_egw_record_project $record Record to be converted
	 */
	protected static function convert(projectmanager_egw_record_project &$record, array $options = array()) {
		$record->pm_description = strip_tags($record->pm_description);
		foreach(array('pm_', 'pe_') as $prefix) {
			foreach(array('used_time', 'planned_time', 'replanned_time') as $_duration) {
				$duration = $prefix . $_duration;
				switch($options['pm_'.$_duration]) {
					case 'd':
						$record->$duration = round($record->$duration / 480, 2);
						break;
					case 'h':
						$record->$duration = round($record->$duration / 60, 2);
						break;
				}
				if($options['include_duration_unit']) {
					$record->$duration .= $options[$_duration];
				}
			}
		}
	}
}
