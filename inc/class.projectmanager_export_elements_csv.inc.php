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
		'date-time' => array('pe_modified','pe_created','pe_planned_start','pe_planned_end', 'pe_real_start', 'pe_real_end', 'pe_synced'),
		'date' => array('pe_planned_start','pe_planned_end', 'pe_real_start', 'pe_real_end'),
		'select-cat' => array('cat_id'),
	);

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$ui = new projectmanager_elements_ui();
		$selection = array();
		if ($options['selection'] == 'selected') {
			// ui selection with checkbox 'use_all'
			$query = $GLOBALS['egw']->session->appsession('projectelements_list','projectmanager');
			$query['num_rows'] = -1;	// all
			$ui->get_rows($query,$selection,$readonlys);
		}
		elseif ( $options['selection'] == 'all' ) {
			$query = array('num_rows' => -1);	// all
			$ui->get_rows($query,$selection,$readonlys);
		} else {
			$query = array(
				'num_rows' => -1,
				'pm_id'	=> $options['pm_id']
			);
			$ui->get_rows($query,$selection,$readonlys);
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

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
			$this->convert($element, $options);
			$export_object->export_record($element);
			unset($element);
		}
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
			'name'	=> 'projectmanager.export_elements_csv_selectors'
		);
	}

	/**
	 * Do some conversions from internal format and structures to human readable / exportable
	 * formats
	 *
	 * @param projectmanager_egw_record_project $record Record to be converted
	 */
	protected static function convert(projectmanager_egw_record_element &$record, array $options = array()) {
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

		$cats = array();
		foreach(explode(',',$record->cat_id) as $n => $cat_id) {
			if ($cat_id) $cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
		}

		$record->cat_id = implode(', ',$cats);
	}
}
