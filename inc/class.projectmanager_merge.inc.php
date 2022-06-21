<?php
/**
 * Projectmanager - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2011 by Christian Binder <christian-AT-jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_merge.inc.php 30377 2010-09-27 19:35:10Z jaytraxx $
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Egw;
use EGroupware\Api\Storage\Merge;

/**
 * Projectmanager - document merge object
 */
class projectmanager_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request' => true,
		'show_replacements'   => true,
		'merge_entries'       => true
	);

	/**
	 * Id of current project
	 *
	 * @var int
	 */
	var $pm_id = null;

	/**
	 * Instance of the projectmanager_bo class
	 *
	 * @var projectmanager_bo
	 */
	var $projectmanager_bo;

	/**
	 * Instance of the projectmanager_elements_bo class
	 *
	 * @var projectmanager_elements_bo
	 */
	var $projectmanager_elements_bo;

	/**
	 * Instance of the projectmanager_eroles_bo class
	 *
	 * @var projectmanager_eroles_bo
	 */
	var $projectmanager_eroles_bo;

	/**
	 * List of projectmanager fields which can be used for merging
	 *
	 * @var array
	 */
	var $projectmanager_fields = array();

	/**
	 * Translate list for merged projectmanager fields
	 *
	 * @var array
	 */
	var $pm_fields_translate = array();


	/**
	 * List of projectmanager element fields which can be used for merging
	 *
	 * @var array
	 */
	var $projectmanager_element_fields = array();

	/**
	 * Translate list for merged element fields
	 *
	 * @var array
	 */
	var $pe_fields_translate = array();

	/**
	 * Element roles - array with keys pe_id, app, app_id and erole_id
	 *
	 * @var array
	 */
	var $eroles = null;

	/**
	 * Constructor
	 *
	 * @param int $pm_id =null id of current project
	 * @return projectmanager_merge
	 */
	function __construct($pm_id = null)
	{
		parent::__construct();

		if(isset($pm_id) && $pm_id > 0)
		{
			$this->change_project($pm_id);
		}
		$this->projectmanager_bo = new projectmanager_bo($pm_id);
		$this->table_plugins['elements'] = 'table_elements';
		$this->table_plugins['eroles'] = 'table_eroles';

		$this->projectmanager_fields = array(
			'pm_id'              => lang('Project ID'),
			'pm_number'          => lang('Project number'),
			'pm_title'           => lang('Title'),
			'pm_description'     => lang('Description'),
			'pm_creator'         => lang('Creator'),
			'pm_created'         => lang('Creation date and time'),
			'pm_modifier'        => lang('Modifier'),
			'pm_modified'        => lang('Modified date and time'),
			'pm_planned_start'   => lang('Planned start date and time'),
			'pm_planned_end'     => lang('Planned end date and time'),
			'pm_real_start'      => lang('Real start date and time'),
			'pm_real_end'        => lang('Real end date and time'),
			'cat_id'             => lang('Category'),
			'pm_access'          => lang('Access'),
			'pm_priority'        => lang('Priority'),
			'pm_status'          => lang('Status'),
			'pm_completion'      => lang('Completion'),
			'pm_used_time'       => lang('Used time'),
			'pm_planned_time'    => lang('Planned time'),
			'pm_replanned_time'  => lang('Re-planned time'),
			'pm_used_budget'     => lang('Used budget'),
			'pm_planned_budget'  => lang('Planned budget'),
			'pm_accounting_type' => lang('Accounting type'),
			'user_timezone_read' => lang('Timezone'),

			'all_roles' => lang('All roles'),
		);
		$this->role_so = new projectmanager_roles_so();
		$roles = $this->role_so->query_list();
		$roles = array_combine($roles, $roles);
		$this->projectmanager_fields += $roles;
		foreach($roles as $role => &$label)
		{
			$label = lang($label);
		}
		$this->projectmanager_fields += array_combine($roles, $roles);

		// Handle dates as dates in spreadsheets
		$this->date_fields = projectmanager_egw_record_project::$types['date-time'];


		$this->pm_fields_translate = array(
			'cat_id'             => 'pm_cat_id',
			'user_timezone_read' => 'pm_user_timezone',
		);

		$this->projectmanager_element_fields = array(
			'pe_id'               => lang('Element ID'),
			'pe_title'            => lang('Title'),
			'pe_details'          => lang('Description'),
			'pe_completion'       => lang('Completion'),
			'pe_used_time'        => lang('Used time in minutes'),
			'pe_planned_time'     => lang('Planned time in minutes'),
			'pe_replanned_time'   => lang('Replanned time in minutes'),
			'pe_share'            => lang('Shared time in minutes'),
			'pe_planned_quantity' => lang('Planned quantitiy'),
			'pe_used_quantity'    => lang('Used quantity'),
			'pe_unitprice'        => lang('Price per unit'),
			'pe_planned_budget'   => lang('Planned budget'),
			'pe_used_budget'      => lang('Used budget'),
			'pe_planned_start'    => lang('Planned start date and time'),
			'pe_real_start'       => lang('Real start date and time'),
			'pe_planned_end'      => lang('Planned end date and time'),
			'pe_real_end'         => lang('Real end date and time'),
			'pe_synced'           => lang('Last sync date and time'),
			'pe_modified'         => lang('Modified date and time'),
			'pe_modifier'         => lang('Modifier'),
			'pe_status'           => lang('Status'),
			'cat_id'              => lang('Category'),
			'pe_remark'           => lang('Remark'),
			'user_timezone_read'  => lang('Timezone'),
			'pe_app'              => lang('Application'),
			'pe_resources'        => lang('Resources')
		);
		$this->pe_fields_translate = array(
			'cat_id'             => 'pe_cat_id',
			'user_timezone_read' => 'pe_user_timezone',
		);

		// Add in element summary
		$this->projectmanager_elements_bo = new projectmanager_elements_bo($pm_id);
		$summary = $this->projectmanager_elements_bo->summary();
		foreach($summary as $key => $value)
		{
			$this->projectmanager_fields[$key . '_total'] = $this->projectmanager_element_fields[$key] ? $this->projectmanager_element_fields[$key] : $key . ' ' . lang('Total');
		}

	}

	/**
	 * Merge the selected IDs into the given document, save it to the VFS, then
	 * either open it in the editor or have the browser download the file.
	 *
	 * @param string[]|null $ids Allows extending classes to process IDs in their own way.  Leave null to pull from request.
	 * @param Merge|null $document_merge Already instantiated Merge object to do the merge.
	 * @param boolean|null $pdf Convert result to PDF
	 * @throws Api\Exception
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function merge_entries(array $ids = null, Merge &$document_merge = null, $pdf = null)
	{
		$document_merge = new projectmanager_merge();
		if(is_null($ids))
		{
			$ids = is_string($_REQUEST['id']) && strpos($_REQUEST['id'], '[') === FALSE ? explode(',', $_REQUEST['id']) : json_decode($_REQUEST['id'], true);
		}
		if($_REQUEST['select_all'] === 'true')
		{
			$ids = self::get_all_ids($document_merge);
		}

		// Project list IDs are just PM ID, element action id's are pe_app:pe_app_id:pe_id --> pe_id
		if(!is_numeric($ids[0]))
		{
			$ids = static::merge_element_entries($ids, $document_merge);
		}
		else
		{
			if(count($ids) > 0)
			{
				$document_merge->change_project($ids[0]);
			}
		}

		return parent::merge_entries($ids, $document_merge, $pdf);
	}

	/**
	 * Setup & deal with merge from element list
	 *
	 * @param $ids
	 * @param Merge $document_merge
	 */
	protected static function merge_element_entries($ids, projectmanager_merge &$document_merge)
	{
		$document_projects = array();
		$eroles = [];
		$contacts = [];

		foreach($ids as $key => &$id)
		{
			list($app, $app_id, $id) = explode(':', $id);
			if($app == 'projectmanager' && $id == 0)
			{
				// Special handling for top-level projects - they show in the element list and
				// can be selected, but can't be retrieved by pe_id
				$document_projects[] = $app_id;
				unset($ids[$key]);
			}
			else
			{
				$document_merge->elements[] = $id;
			}
		}
		unset($id);

		$elements_ui = new projectmanager_elements_ui();

		// User selected only projects, so select all elements in that project
		if(count($ids) == 0 && count($document_projects) > 0)
		{
			// Use all elements from project
			$query = $old_query = Api\Cache::getSession('projectmanager', 'projectelements_list');
			$query['num_rows'] = -1;        // all
			$elements_ui->get_rows($query, $selection, $readonlys);
			foreach($selection as $key => $element)
			{
				if(!is_int($key))
				{
					continue;
				}    // ignore string keys from get_rows
				if($element['pe_id'] && is_numeric($element['pe_id']))
				{
					$document_merge->elements[] = $element['pe_id'];
				}
			}

			// Reset nm params
			Api\Cache::setSession('projectmanager', 'projectelements_list', $old_query);
		}

		// Did not get project in the list, make sure to get it
		if(count($document_projects) == 0)
		{
			$query = Api\Cache::getSession('projectmanager', 'projectelements_list');
			$document_projects[] = $query['col_filter']['pm_id'];
		}

		foreach($elements_ui->search(array('pm_id' => $document_projects), false) as $id => $element)
		{
			// add contact
			if($element['pe_app'] == 'addressbook' && in_array($element['pe_id'], $document_merge->elements))
			{
				$contacts[] = $element['pe_app_id'];
			}
			// add erole(s)
			if($elements_ui->config['enable_eroles'] && !empty($element['pe_eroles']))
			{
				// one element could have multiple eroles
				foreach(explode(',', $element['pe_eroles']) as $erole_id)
				{
					$eroles[] = array(
						'pe_id'    => $element['pe_id'],
						'app'      => $element['pe_app'],
						'app_id'   => $element['pe_app_id'],
						'erole_id' => $erole_id,
					);
				}
			}
		}

		$document_merge->eroles = $eroles;
		$document_merge->contact_ids = array_unique($contacts);

		return $document_projects;
	}

	/**
	 * Generate a filename for the merged file
	 *
	 * Override the default to include the project name / title
	 *
	 * @param string $document Template filename
	 * @param string[] $ids List of IDs being merged
	 * @return string
	 */
	protected function get_filename($document, $ids = []) : string
	{
		$name = '';
		if(isset($this->projectmanager_bo->prefs['document_download_name']))
		{
			$ext = '.' . pathinfo($document, PATHINFO_EXTENSION);
			$name = preg_replace(
				array('/%document%/', '/%pm_number%/', '/%pm_title%/'),
				array(basename($document, $ext), $this->projectmanager_bo->data['pm_number'],
					  $this->projectmanager_bo->data['pm_title']),
				$this->projectmanager_bo->prefs['document_download_name']
			);
		}
		return $name;
	}

	/**
	 * Change the currently merging project
	 *
	 * @param int $id of the project
	 */
	protected function change_project($id)
	{
		$this->pm_id = $id;
		if($id)
		{
			$this->projectmanager_eroles_bo = new projectmanager_eroles_bo($id);
			$this->projectmanager_elements_bo = new projectmanager_elements_bo($id);
		}
		$this->projectmanager_bo = new projectmanager_bo($id);

		// add erole(s)
		$this->eroles = array();
		if($this->projectmanager_bo->config['enable_eroles'])
		{
			$elements = $this->projectmanager_elements_bo->search(array('pm_id' => $id), false);
			if(!is_array($elements))
			{
				return;
			}
			foreach($elements as $element)
			{
				if(!empty($element['pe_eroles']))
				{
					// one element could have multiple eroles
					foreach(explode(',', $element['pe_eroles']) as $erole_id)
					{
						$this->eroles[] = array(
							'pe_id'    => $element['pe_id'],
							'app'      => $element['pe_app'],
							'app_id'   => $element['pe_app_id'],
							'erole_id' => $erole_id,
						);
					}
				}
			}
		}
	}

	/**
	 * Get projectmanager replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content =null content to create some replacements only if they are in use
	 * @return array|boolean
	 */
	protected function get_replacements($id, &$content = null)
	{
		$replacements = array();

		// first replacement is always the contacts (if valid)
		// If this special case is no longer needed, it can be removed & contacts handled as
		// any other element
		if(!empty($this->contact_ids) && is_array($this->contact_ids))
		{
			foreach($this->contact_ids as $contact_id)
			{
				$replacements += $this->contact_replacements($contact_id);
			}
		}

		// replace project content
		if($id > 0 && $this->pm_id != $id)
		{
			$this->change_project($id);
		}

		$replacements += $this->projectmanager_replacements($this->pm_id, '', $content);

		$replacements += $this->get_erole_replacements($content);

		return empty($replacements) ? false : $replacements;
	}

	/**
	 * Get erole replacements for the project
	 *
	 * Handles normal erole placeholders, not in an erole table
	 *
	 * @param string $content Repeating content, used to remove any missing placeholders
	 *
	 * @return Array
	 */
	protected function get_erole_replacements(&$content)
	{
		$replacements = array();
		// further replacements are made by eroles (if given)
		if(!empty($this->eroles) && is_array($this->eroles))
		{
			foreach($this->eroles as $erole)
			{
				$erole_title = $this->projectmanager_eroles_bo->id2title($erole['erole_id']);
				if(!empty($erole_title) && ($replacement = $this->get_element_replacements(
						$erole['pe_id'],
						'erole/' . $erole_title,
						$erole['app'],
						$erole['app_id'],
						$content
					)))
				{
					$replacements += $replacement;
				}
			}
		}

		// Strip unassigned erole tags
		$matches = array();
		preg_match_all('@\$\$erole/([A-Za-z0-9_]+)(/?(?:[^\$])*)?\$\$@s', $content, $matches);
		foreach($matches[0] as $missing_erole)
		{
			if(!$replacements[$missing_erole])
			{
				$replacements[$missing_erole] = '';
			}
		}

		return $replacements;
	}


	/**
	 * Get element replacements
	 *
	 * @param int $pe_id element id
	 * @param string $prefix ='' prefix like eg. 'erole'
	 * @param string $app =null element app name (no app detail will be resolved if omitted)
	 * @param string $app_id =null element app_id (no app detail will be resolved if omitted)
	 * @return array|boolean
	 */
	protected function get_element_replacements($pe_id, $prefix = '', $app = null, $app_id = null, $content = '')
	{
		$replacements = array();
		// resolve project element fields
		if($replacement = $this->projectmanager_element_replacements($pe_id, $prefix, $content))
		{
			$replacements += $replacement;
		}

		// For handling date/times as such in a spreadsheet
		foreach(projectmanager_egw_record_element::$types['date-time'] as $field)
		{
			$this->date_fields[] = ($prefix ? $prefix . '/' : '') . $field;
		}

		// resolve app fields of project element
		if($app && $app_id)
		{
			switch($app)
			{
				case 'addressbook':
					if($replacement = $this->contact_replacements($app_id, $prefix))
					{
						$replacements += $replacement;
					}
					break;
				case 'calendar':
					if(!is_object($calendar_merge))
					{
						$calendar_merge = new calendar_merge();
					}
					if($replacement = $calendar_merge->calendar_replacements($app_id, $prefix))
					{
						$replacements += $replacement;
					}
					break;
				case 'infolog':
					if(!is_object($infolog_merge))
					{
						$infolog_merge = new infolog_merge();
					}
					if($replacement = $infolog_merge->infolog_replacements($app_id, $prefix))
					{
						$replacements += $replacement;
					}
					break;
				default:
					// app not supported
					break;
			}
		}
		return empty($replacements) ? false : $replacements;
	}


	/**
	 * Return replacements for a project
	 *
	 * @param int|array $project project-array or id
	 * @param string $prefix ='' prefix like eg. 'erole'
	 * @param string $content Used to see if we have to look up all the links, it's expensive
	 * @return array
	 */
	public function projectmanager_replacements($project, $prefix = '', &$content = '')
	{
		$record = new projectmanager_egw_record_project(is_array($project) ? $pm_id : $project);
		$project = $record->get_record_array();

		if(!is_array($project))
		{
			return array();
		}
		$replacements = array();

		// Convert to human friendly values
		$types = projectmanager_egw_record_project::$types;
		$selects = array();
		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $replacements);
		}

		importexport_export_csv::convert($record, $types, 'projectmanager', $selects);
		$project = $record->get_record_array();

		// Set any missing custom fields, or the marker will stay
		$custom = Api\Storage\Customfields::get('projectmanager');
		foreach($custom as $name => $field)
		{
			$this->projectmanager_fields['#' . $name] = $field['label'];
			if(!$project['#' . $name])
			{
				$project['#' . $name] = '';
			}
		}

		// Add in roles
		$roles = $this->role_so->query_list();

		// Sort with Coordinator first, others alphabetical
		sort($roles);
		$all_roles = array('Coordinator' => array());
		$all_roles += array_fill_keys($roles, array());
		foreach((array)$project['pm_members'] as $account_id => $info)
		{
			$all_roles[$info['role_title']][] = Api\Accounts::username($info['member_uid']);
		}
		foreach($all_roles as $name => $users)
		{
			$project[$name] = implode(', ', $users);
			$project[lang($name)] = $project[$name];
			if(count($users) == 0)
			{
				unset($all_roles[$name]);
				continue;
			}
			$project['all_roles'][] = lang($name) . ': ' . $project[$name];
		}
		$project['all_roles'] = implode("\n", (array)$project['all_roles']);

		// Add in element summary
		$summary = $this->projectmanager_elements_bo->summary();
		foreach($summary as $key => $value)
		{
			$project[$key . '_total'] = $value;
		}

		foreach(array_keys($project) as $name)
		{
			if(!isset($this->projectmanager_fields[$name]))
			{
				continue;
			} // not a supported field

			$value = $project[$name];
			switch($name)
			{
				case 'pe_planned_start_total':
				case 'pe_planned_end_total':
				case 'pe_real_start_total':
				case 'pe_real_end_total':
					if($value)
					{
						$value = Api\DateTime::to($value);
					}
					break;
				case 'pm_creator':
				case 'pm_modifier':
				$value = is_numeric($value) ? Api\Accounts::username($value) : $value;
					break;
				case 'cat_id':
					if($value && $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree')
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$cats = array();
						$value = $this->projectmanager_bo->data['cat_id'];
						foreach(is_array($value) ? $value : explode(',', $value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id, 'path');
						}
						$value = implode(', ', $cats);
					}
					break;
				case 'pe_used_time_total':
				case 'pe_planned_time_total':
				case 'pe_replanned_time_total':
					$value = round($value / 60, 2);
			}
			if(isset($this->pm_fields_translate[$name]))
			{
				$name = $this->pm_fields_translate[$name];
			}
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . $name . '$$'] = $value;
		}
		// Project links - check content first, finding all the links is expensive
		$replacements += $this->get_all_links('projectmanager', $project['pm_id'], $prefix, $content);


		return $replacements;
	}

	/**
	 * Return replacements for a given project element
	 *
	 * @param int $pe_id project element id
	 * @param string $prefix ='' prefix like eg. 'erole'
	 * @return array
	 */
	public function projectmanager_element_replacements($pe_id, $prefix = '', $content = '')
	{
		$replacements = array();
		if(!is_object($this->projectmanager_elements_bo))
		{
			return $replacements;
		}

		// Filter selected elements
		if($this->elements && !in_array($pe_id, $this->elements))
		{
			return $replacements;
		}

		$element = $this->projectmanager_elements_bo->read(array('pe_id' => $pe_id));
		foreach(array_keys($element) as $name)
		{
			if(!isset($this->projectmanager_element_fields[$name]))
			{
				continue;
			} // not a supported field

			$value = !is_array($element[$name]) ? strip_tags($element[$name]) : $element[$name];
			switch($name)
			{
				case 'pe_planned_start':
				case 'pe_planned_end':
				case 'pe_real_start':
				case 'pe_real_end':
				case 'pe_synced':
				case 'pe_modified':
					if($value)
					{
						$value = Api\DateTime::to($value);
					}
					break;
				case 'pe_modifier':
					$value = Api\Accounts::username($value);
					break;
				case 'cat_id':
					if($value)
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$use = $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree' ? 'path' : 'name';
						$cats = array();
						foreach(is_array($value) ? $value : explode(',', $value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id, $use);
						}
						$value = implode(', ', $cats);
					}
					else
					{
						$value = '';
					}
					break;
				case 'pe_resources':
					if(!is_array($value))
					{
						$value = explode(',', $value);
					}
					$names = array();
					foreach($value as $user_id)
					{
						$names[] = is_numeric($user_id) ? Api\Accounts::username($user_id) : $user_id;
					}
					$value = implode(', ', $names);
					break;
			}
			if(isset($this->pe_fields_translate[$name]))
			{
				$name = $this->pe_fields_translate[$name];
			}
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . $name . '$$'] = $value;
		}

		// Element links
		if(strpos($content, ($prefix ? $prefix . '/' : '') . 'links') !== false)
		{
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'links$$'] = $this->get_links($element['pe_app'], $element['pe_app_id'], '!' . Link::VFS_APPNAME);
		}
		if(strpos($content, ($prefix ? $prefix . '/' : '') . 'attachments') !== false)
		{
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'attachments$$'] = $this->get_links($element['pe_app'], $element['pe_app_id'], Link::VFS_APPNAME);
		}
		if(strpos($content, ($prefix ? $prefix . '/' : '') . 'links_attachments') !== false)
		{
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'links_attachments$$'] = $this->get_links($element['pe_app'], $element['pe_app_id']);
		}

		return $replacements;
	}


	/**
	 * Set element roles for merging
	 *
	 * @param array $eroles element roles with keys pe_id, app, app_id and erole_id
	 * @return boolean true on success
	 */
	public function set_eroles($eroles)
	{
		if(empty($eroles))
		{
			return false;
		}

		$this->eroles = $eroles;
		return true;
	}

	/**
	 * Generate table with replacements for the Api\Preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Projectmanager') . ' - ' . lang('Replacements for inserting project data into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";

		// Projectmanager
		$n = 0;
		echo '<tr><td colspan="4"><h3><a name="pm_fields">' . lang('Projectmanager fields:') . "</a></h3></td></tr>";
		foreach($this->projectmanager_fields as $name => $label)
		{
			if(isset($this->pm_fields_translate[$name]))
			{
				$name = $this->pm_fields_translate[$name];
			}
			if(!($n & 1))
			{
				echo '<tr>';
			}
			echo '<td>{{' . $name . '}}</td><td>' . $label . '</td>';
			if($n & 1)
			{
				echo "</tr>\n";
			}
			$n++;
		}

		// Custom fields
		echo '<tr><td colspan="4"><h3>' . lang('Custom fields') . ":</h3></td></tr>";
		$custom = Api\Storage\Customfields::get('projectmanager');
		foreach($custom as $name => $field)
		{
			echo '<tr><td>{{#' . $name . '}}</td><td colspan="3">' . $field['label'] . "</td></tr>\n";
		}

		// Elements
		$n = 0;
		echo '<tr><td colspan="4">'
			. '<h3><a name="pe_fields">' . lang('Projectmanager element fields:') . '</a></h3>'
			. '<p>' . lang('can be used with element roles, "eroles" table plugin and "elements" table plugin') . '</p>'
			. '</td></tr>';
		foreach($this->projectmanager_element_fields as $name => $label)
		{
			if(isset($this->pe_fields_translate[$name]))
			{
				$name = $this->pe_fields_translate[$name];
			}
			if(!($n & 1))
			{
				echo '<tr>';
			}
			echo '<td>{{' . $name . '}}</td><td>' . $label . '</td>';
			if($n & 1)
			{
				echo "</tr>\n";
			}
			$n++;
		}

		// Element roles
		if(!($this->projectmanager_bo->config['enable_eroles']))
		{
			$eroles_enable_hint = '<p style="font-weight: bold;">(' . lang('Element roles feature is currently not enabled in your global projectmanager configuration') . ')</p>';
		}
		echo '<tr><td colspan="4">'
			. '<h3>' . lang('Element roles:') . '</h3>'
			. $eroles_enable_hint
			. '<p>' . lang('Elements given by {rolename} will be replaced with the element fields;'
						   . ' additionally all fields of the elements application are available if the application is supported.'
			) . '</p>'
			. '</td></tr>';
		echo '<tr><td colspan="4">' . lang('Usage') . ': {{erole/{rolename}/{fieldname}}}</td></tr>';
		echo '<tr>';
		echo '<td colspan="2">'
			. '<h4>' . lang('Fields for element roles:') . '</h4>'
			. '<ul>'
			. '<li><a href="#pe_fields">' . lang('Projectmanager element fields') . '</a></li>';
		foreach(array(
					'Addressbook fields' => Egw::link('/index.php', 'menuaction=addressbook.addressbook_merge.show_replacements'),
					'Calendar fields'    => Egw::link('/index.php', 'menuaction=calendar.calendar_merge.show_replacements'),
					'Infolog fields'     => Egw::link('/index.php', 'menuaction=infolog.infolog_merge.show_replacements'),
				) as $placeholder => $link)
		{
			echo '<li><a href="' . $link . '" target="_blank">' . lang($placeholder) . '</a></li>';
		}
		echo '</ul>';
		echo '</td>';
		echo '<td colspan="2">'
			. '<h4>' . lang('Examples:') . '</h4>'
			. '{{erole/myrole/pe_title}}<br />{{erole/myrole/n_fn}}<br />{{erole/myrole/info_subject}}'
			. '</td>';
		echo "</tr>\n";

		// Table plugins
		echo '<tr><td colspan="4">'
			. '<h3>' . lang('Table plugins:') . '</h3>'
			. '</td></tr>' . "\n";
		echo '<tr><td colspan="4">'
			. '<h4>' . lang('Elements') . '</h4>'
			. lang('Lists all project elements in a table.')
			. '</td></tr>' . "\n";
		echo '<tr><td colspan="4">' . lang('Usage') . ': {{table/elements}} ... {{endtable}}</td></tr>';
		echo '<tr>'
			. '<td colspan="2">'
			. lang('Available fields for this plugin:')
			. '<ul>'
			. '<li><a href="#pe_fields">' . lang('Projectmanager element fields') . '</a></li>'
			. '</ul>'
			. '</td>'
			. '<td colspan="2">'
			. '<h4>' . lang('Example:') . '</h4>'
			. '<table border="1"><tr><td>Title</td><td>Details {{table/elements}}</td></tr>'
			. '<tr><td>{{element/pe_title}}</td><td>{{element/pe_details}} {{endtable}}</td></tr></table>'
			. '</td>'
			. '</tr>' . "\n";
		echo '<tr><td colspan="4">'
			. '<h4>' . lang('Element roles') . '</h4>'
			. $eroles_enable_hint
			. lang('Lists all elements assigned to an element role in a table.') . ' '
			. lang('Element roles defined as "mutliple" can be used here.')
			. '</td></tr>' . "\n";
		echo '<tr><td colspan="4">' . lang('Usage') . ': {{table/eroles}} ... {{endtable}}</td></tr>';
		echo '<tr>'
			. '<td colspan="2">'
			. lang('Available fields for this plugin:')
			. '<ul>'
			. '<li><a href="#pe_fields">' . lang('Projectmanager element fields') . '</a></li>';
		foreach(array(
					'Addressbook fields' => Egw::link('/index.php', 'menuaction=addressbook.addressbook_merge.show_replacements'),
					'Calendar fields'    => Egw::link('/index.php', 'menuaction=calendar.calendar_merge.show_replacements'),
					'Infolog fields'     => Egw::link('/index.php', 'menuaction=infolog.infolog_merge.show_replacements'),
				) as $placeholder => $link)
		{
			echo '<li><a href="' . $link . '" target="_blank">' . lang($placeholder) . '</a></li>';
		}
		echo '</ul></td>' . "\n";
		echo '<td colspan="2">'
			. '<h4>' . lang('Example:') . '</h4>'
			. '<table border="1"><tr><td>Title</td><td>Infolog subject {{table/eroles}}</td></tr>'
			. '<tr><td>{{erole/myrole/pe_title}}</td><td>{{erole/myrole/info_subject}} {{endtable}}</td></tr></table>'
			. '</td>'
			. '</tr>' . "\n";

		// Serial letter
		$link = Egw::link('/index.php', 'menuaction=addressbook.addressbook_merge.show_replacements');
		echo '<tr><td colspan="4">'
			. '<h3>' . lang('Contact fields for serial letters') . '</h3>'
			. lang('Addressbook elements of a project can be used to define individual serial letter recipients. Available fields are') . ':'
			. '<ul>'
			. '<li><a href="' . $link . '" target="_blank">' . lang('Addressbook fields') . '</a></li>'
			. '</ul>'
			. '</td></tr>';

		// General
		echo '<tr><td colspan="4"><h3>' . lang('General fields:') . "</h3></td></tr>";
		foreach($this->get_common_replacements() as $name => $label)
		{
			echo '<tr><td>{{' . $name . '}}</td><td colspan="3">' . $label . "</td></tr>\n";
		}

		echo "</table>\n";
		echo $GLOBALS['egw']->framework->footer();
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		$placeholders = array(
				'project'      => [],
				'element'      => [],
				'erole'        => [],
				'customfields' => []
			) + parent::get_placeholder_list($prefix);

		// Add project placeholders
		$this->get_project_placeholder_list($prefix, $placeholders);

		// Add element placeholders
		$this->get_element_placeholder_list($prefix, $placeholders);

		// Add erole placeholders
		$this->get_erole_placeholder_list($prefix, $placeholders);

		return $placeholders;
	}

	/**
	 * Get the list of project placeholders
	 *
	 * @param string $prefix
	 * @param array $placeholders
	 */
	protected function get_project_placeholder_list($prefix, &$placeholders)
	{
		// Project placeholders
		$group = 'project';
		foreach($this->projectmanager_fields as $name => $label)
		{
			if(isset($this->pm_fields_translate[$name]))
			{
				$name = $this->pm_fields_translate[$name];
			}

			$marker = $this->prefix($prefix, $name, '{');
			if(!array_filter($placeholders, function ($a) use ($marker)
			{
				return array_key_exists($marker, $a);
			}))
			{
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}
	}

	/**
	 * Get the list of element placeholders.
	 * These should be wrapped in elements table plugin
	 *
	 * @param string $prefix
	 * @param array $placeholders
	 */
	protected function get_element_placeholder_list($prefix, &$placeholders)
	{
		$group = 'element';
		// This isn't used anywhere in the UI, 'label' & 'title' are not allowed for group.  I'm not sure where to stick it.
		//'help' => lang('can be used with element roles, "eroles" table plugin and "elements" table plugin')

		foreach($this->projectmanager_element_fields as $name => $label)
		{
			if(isset($this->pe_fields_translate[$name]))
			{
				$name = $this->pe_fields_translate[$name];
			}
			$marker = $this->prefix($prefix, $name, '{');
			if(!array_filter($placeholders, function ($a) use ($marker)
			{
				return array_key_exists($marker, $a);
			}))
			{
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}
	}

	/**
	 * Get placeholders for eroles
	 * We only list the single roles, but these are also available as a table using {{table/eroles}}...{{endtable}}
	 *
	 * @param $prefix
	 * @param $placeholders
	 */
	protected function get_erole_placeholder_list($prefix, &$placeholders)
	{
		if(!$this->projectmanager_bo->config['enable_eroles'])
		{
			return;
		}
		if(!$this->projectmanager_eroles_bo)
		{
			$this->projectmanager_eroles_bo = new projectmanager_eroles_bo();
		}
		foreach((array)$this->projectmanager_eroles_bo->search(array(), ['role_title'], 'role_title ASC', '', '', false, 'AND', false, array()) as $erole)
		{
			// TODO: If we knew what app was in the erole, we could list the placeholders...
			$placeholders['erole'][$erole['role_title']][] = [
				'value' => $this->prefix($prefix, "erole/{$erole['role_title']}/...", '{'),
				'label' => $erole['role_title']
			];
		}
	}

	/**
	 * Table plugin for project elements
	 *
	 * @param string $plugin
	 * @param int $id Project ID
	 * @param int $n
	 * @param string $repeat the line to repeat
	 * @return array
	 */
	public function table_elements($plugin, $id, $n, $repeat)
	{
		if(!isset($this->pm_id) && !$id)
		{
			return false;
		}

		static $elements;
		if($id && $id != $this->pm_id)
		{
			$this->change_project($id);
			$elements = array();
		}
		if(!$n)    // first row inits environment
		{
			// get project elements
			$query = array('pm_id' => $this->pm_id);
			if($this->elements)
			{
				foreach($this->elements as $elem_id)
				{
					$elements[] = $this->projectmanager_elements_bo->read($elem_id);
				}
			}
			else
			{
				$limit = array(0, -1);
				if($this->export_limit && !Api\Storage\Merge::is_export_limit_excepted())
				{
					$limit = array(0, (int)$this->export_limit);
					// Need to do this to give an error
					$count = count($this->projectmanager_elements_bo->search($query));
				}
				if(!($elements = $this->projectmanager_elements_bo->search($query, false, '', '', '', False, 'AND', $limit)))
				{
					return false;
				}
			}
			if($count && count($elements) < $count)
			{
				throw new Api\Exception(lang('No rights to export more then %1 entries!', (int)$this->export_limit));
			}
		}

		$element =& $elements[$n];
		$replacement = false;
		if(isset($element))
		{
			$replacement = $this->get_element_replacements($element['pe_id'], 'element', null, null, $repeat);
		}
		return $replacement;
	}

	/**
	 * Table plugin for eroles
	 *
	 * @param string $plugin
	 * @param int $id (contact id - not used for this plugin)
	 * @param int $n
	 * @param string $repeat the line to repeat
	 * @return array
	 */
	public function table_eroles($plugin, $id, $n, $repeat)
	{
		if(!($this->projectmanager_bo->config['enable_eroles']))
		{
			return false;
		} // eroles are disabled

		static $erole_id;
		static $erole_title;
		static $elements;

		if(!$n)    // first row inits environment
		{
			// get erole_id from repeated line
			preg_match_all('/\\$\\$erole\\/([A-Za-z0-9_]+)\\//s', $repeat, $matches);

			if(!is_array($matches[1]))
			{
				return false; // no erole found
			}
			if(count(($erole_titles = array_unique($matches[1]))) !== 1)
			{
				return false; // multiple eroles in one row not supported
			}
			$erole_title = array_shift($erole_titles);
			if(!($erole_id = $this->projectmanager_eroles_bo->title2id($erole_title)))
			{
				return false; // erole_id cannot be determined
			}

			// get elements assigned to erole
			$elements = $this->projectmanager_eroles_bo->get_elements($erole_id);
		}

		$element =& $elements[$n];
		$replacement = false;
		if(isset($element))
		{
			$replacement = $this->get_element_replacements(
				$element['pe_id'],
				'erole/' . $erole_title,
				$element['pe_app'],
				$element['pe_app_id'],
				$repeat
			);
		}

		return $replacement;
	}

	/**
	 * Get preference settings
	 *
	 * Merge has some preferences that the same across apps, but can have different values for each app.
	 * Overridden from parent because projectmanager has different filename generation
	 */
	public function merge_preferences()
	{
		$settings = parent::merge_preferences();
		$settings[self::PREF_DOCUMENT_FILENAME] += array(
			'type'    => 'select',
			'values'  => array(
				'%document%'                            => lang('Template name'),
				'%pm_title%'                            => lang('Project title'),
				'%pm_title% - %document%'               => lang('Project title - template name'),
				'%document% - %pm_title%'               => lang('Template name - project title'),
				'%pm_number% - %document%'              => lang('Project ID - template name'),
				'(%pm_number%) %pm_title% - %document%' => lang('(Project ID) project title - template name'),

			),
			'default' => '%document%',
		);
		return $settings;
	}
}
