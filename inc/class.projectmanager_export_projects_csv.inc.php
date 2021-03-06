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

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * export projects to CSV
 */
class projectmanager_export_projects_csv implements importexport_iface_export_plugin
{

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
	public function export( $_stream, importexport_definition $_definition)
	{
		$options = $_definition->plugin_options;

		$ui = new projectmanager_ui();
		$selection = array();
		$query = array();

		// do we need to query the cf's
		foreach($options['mapping'] as $field => $map) {
			if($field[0] == '#') $query['custom_fields'][] = $field;
		}

		// Determine the appropriate list (project or element) to use for query
		$pm_id = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'];
		if($pm_id && $options['selection'] == 'project')
		{
			// Looking at a certain project
			$list = 'projectelements_list';
			$ui = new projectmanager_elements_ui();
			$options['selection'] = 'all';
		}
		else
		{
			$list = 'project_list';
		}

		if ($options['selection'] == 'selected')
		{
			// Use search results
			$old_query = Api\Cache::getSession('projectmanager', $list);
			$query = array_merge($old_query, $query);
			$query['num_rows'] = -1;	// all
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$ui->get_rows($query,$selection,$readonlys);

			// Reset nm params
			unset($query['num_rows']);
			Api\Cache::setSession('projectmanager', $list, $old_query);
		}
		elseif ( $options['selection'] == 'all' )
		{
			$_query = Api\Cache::getSession('projectmanager', $list);
			$query = array(
				'filter2' => $list == 'project_list' ? 'active' : '0' ,
				'col_filter' => $list == 'project_list' ? array() : array('pm_id' => $pm_id),
				'num_rows' => -1,		// all
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);
			$ui->get_rows($query,$selection,$readonlys);

			// Reset nm params
			Api\Cache::setSession('projectmanager', $list, $_query);
		}
		elseif ($options['selection'] == 'filter')
		{
			$filter = $_definition->filter;
			$query = array(
				'filter2' => $list == 'project_list' ? 'active' : '0' ,
				'col_filter' => $list == 'project_list' ? array() : array('pm_id' => $pm_id),
				'num_rows' => -1,		// all
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);

			// Handle ranges
			foreach($filter as $field => $value)
			{
				if($field == 'cat_id')
				{
					$query[$field] = implode(',',$value);
					continue;
				}

				$query['col_filter'][$field] = $value;
				if(!is_array($value) || (!$value['from'] && !$value['to'])) continue;

				// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
				if($value['from']) $query['col_filter'][] = "$field >= " . (int)$value['from'];
				if($value['to']) $query['col_filter'][] = "$field <= " . (int)$value['to'];
				unset($query['col_filter'][$field]);
			}

			$ui->get_rows($query,$selection,$readonlys);
		}
		else
		{
			$selection = explode(',',$options['selection']);
		}
		if(get_class($ui) != 'projectmanager_ui')
		{
			// Reset UI to project
			$ui = new projectmanager_ui();

			$projects = array();
			foreach($selection as $element)
			{
				// Got projects as elements, need to do them as projects
				if(is_array($element) && $element['pe_app'] == 'projectmanager')
				{
					$projects[] = $element['pe_app_id'];
				}
				// Project list passed as list
				else if (!is_array($element))
				{
					$projects[] = $element;
				}
			}
			$selection = $ui->search(array('pm_id'=>$projects), false);
		}

		if($options['mapping']['roles'])
		{
			$roles = new projectmanager_roles_so();
			$this->roles = $roles->query_list();
			foreach($this->roles as $id => $name)
			{
				$options['mapping'][$name] = $name;
				self::$types['select-account'][] = $name;
			}
		}
		$this->export_object = new importexport_export_csv($_stream, (array)$options);
		$this->export_object->set_mapping($options['mapping']);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $record)
		{
			if((int)$record) $record = $ui->read($record);
			if(!is_array($record) || !$record['pm_id']) continue;

			// Add in roles
			if($options['mapping']['roles'])
			{
				$roles = $ui->read_members($record['pm_id']);
				foreach((array)$roles as $person)
				{
					$role_name = $this->roles[$person['role_id']];
					$record[$role_name][] = $person['member_uid'];
				}
			}

			// Add in element summary
			if(true || $options['mapping']['element_summary'])
			{
				$record += ExecMethod('projectmanager.projectmanager_elements_bo.summary',$record['pm_id']);
			}

			$project = new projectmanager_egw_record_project();
			$project->set_record($record);
			if($options['convert'])
			{
				importexport_export_csv::convert($project, self::$types, 'projectmanager');
				$this->convert($project, $options);
			}
			else
			{
				// Implode arrays, so they don't say 'Array'
				foreach($project->get_record_array() as $key => $value)
				{
					if(is_array($value)) $project->$key = implode(',', $value);
				}
			}
			$this->export_object->export_record($project);
			unset($project);
		}
		return $this->export_object;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name()
	{
		return lang('Project CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description()
	{
		return lang("Exports a list of projects to a CSV File.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix()
	{
		return 'csv';
	}

	public static function get_mimetype()
	{
		return 'text/csv';
	}

	/**
	 * Suggest a file name for the downloaded file
	 * No suffix
	 */
	public function get_filename()
	{
		if(is_object($this->export_object) && $this->export_object->get_num_of_records() == 1)
		{
			return $this->export_object->record->get_title();
		}
		return 'egw_export_'.lang('Projects') . '-' . date('Y-m-d');
	}

	/**
	 * Return array of settings for export dialog
	 *
	 * @param $definition Specific definition
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options	=> array,
	 * 		readonlys	=> array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition = NULL)
	{
		return false;
	}

	/**
	 * returns selectors information
	 *
	 */
	public function get_selectors_etpl()
	{

		// If there's a current project, add it as an option
		$pm_id = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'];
		if($pm_id)
		{
			$project_title = Link::title('projectmanager', $pm_id);
		}
		return array(
			'name'	=> 'projectmanager.export_csv_selectors',
			'content'	=> array(
				'selection' => 'selected',
				'project_title' => $project_title
			)
		);
	}

	/**
	 * Do some conversions from internal format and structures to human readable / exportable
	 * formats
	 *
	 * @param projectmanager_egw_record_project $record Record to be converted
	 */
	protected static function convert(projectmanager_egw_record_project &$record, array $options = array())
	{
		$record->pm_description = strip_tags($record->pm_description);
		foreach(array('pm_', 'pe_') as $prefix)
		{
			foreach(array('used_time', 'planned_time', 'replanned_time') as $_duration)
			{
				$duration = $prefix . $_duration;
				switch($options['pm_'.$_duration])
				{
					case 'd':
						$record->$duration = round($record->$duration / 480, 2);
						break;
					case 'h':
						$record->$duration = round($record->$duration / 60, 2);
						break;
				}
				if($options['include_duration_unit'])
				{
					$record->$duration = $record->$duration.($options[$duration] ? $options[$duration] : $options['pm_'.$_duration]);
				}
			}
		}
	}

	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'projectmanager_egw_record_project';
	}
}
