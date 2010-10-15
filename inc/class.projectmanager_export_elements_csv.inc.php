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
}
