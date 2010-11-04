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
		}
		elseif ( $options['selection'] == 'all' ) {
			$query = array('num_rows' => -1);	// all
			$ui->get_rows($query,$selection,$readonlys);
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

			$this->convert($project, $options);
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
			'name'	=> 'projectmanager.export_csv_selectors'
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
		$custom = config::get_customfields('projectmanager');
		foreach($custom as $name => $c_field) {
			$name = '#' . $name;
			if($c_field['type'] == 'date') {
				self::$types['date-time'][] = $name;
			} elseif ($c_field['type'] == 'select-account') {
				self::$types['select-account'][] = $name;
			}
		}
		foreach(self::$types['select-account'] as $name) {
			if ($record->$name) {
				if(is_array($record->$name)) {
					$names = array();
					foreach($record->$name as $_name) {
						$names[] = $GLOBALS['egw']->common->grab_owner_name($_name);
					}
					$record->$name = implode(', ', $names);
				} else {
					$record->$name = $GLOBALS['egw']->common->grab_owner_name($record->$name);
				}
			}
		}
		foreach(self::$types['date-time'] as $name) {
			if ($record->$name) $record->$name = date('Y-m-d H:i:s',$record->$name); // Standard date format
		}
		foreach(self::$types['date'] as $name) {
			if ($record->$name) $record->$name = date('Y-m-d',$record->$name); // Standard date format
		}
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

		$cats = array();
		foreach(explode(',',$record->cat_id) as $n => $cat_id) {
			if ($cat_id) $cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
		}

		$record->cat_id = implode(', ',$cats);
	}
}
