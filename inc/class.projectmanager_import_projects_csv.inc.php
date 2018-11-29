<?php
/**
 * eGroupWare import CSV plugin to import projects
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
 * class to import projects from CSV
 */
class projectmanager_import_projects_csv extends importexport_basic_import_csv {

	/**
	 * Since projectmanager has 2 different record types, we need to specify
	 * which one to use.  Normally it's automatic.
	 *
	 * @var string
	 */
	static $record_class = 'projectmanager_egw_record_project';

	public static $special_fields = array(
		'parent'  => 'Parent project, use Project-ID or Title',
	);

	/**
	 * @var bo
	 */
	private $bo;

	/**
	* For figuring out if a record has changed
	*/
	protected $tracking;

	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function init(importexport_definition &$_definition )
	{
		// fetch the project bo
		$this->bo = new projectmanager_bo();

		// Get the tracker for changes
		$this->tracking = new projectmanager_tracking($this->bo);

		// List roles as account type
		$roles = new projectmanager_roles_so();
		$role_list = $roles->query_list();
		foreach($role_list as $id => $name) {
			projectmanager_egw_record_project::$types['select-account'][] = 'role-'.$id;
		}
	}

	/**
	 * Import a single record
	 *
	 * You don't need to worry about mappings or translations, they've been done already.
	 *
	 * Updates the count of actions taken
	 *
	 * @return boolean success
	 */
	protected function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{
		// Need to set overwrite bits to match the fields provided in the import file
		// Otherwise, they'll be cleared when project is edited.
		if (!$this->bo->pe_name2id)
		{
			// we need the PM_ id's
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

			$ds = new datasource();
			$this->bo->pe_name2id = $ds->name2id;
			unset($ds);
		}

		// Project is a sub-project of something
		if($record->parent && !is_numeric($record->parent))
		{
			$parent_id = self::project_id($record->parent);
			if(!$parent_id)
			{
				$this->warnings[$import_csv->get_current_position()] .= "\n" . lang('Unable to find parent project %1',$record->parent);
			}
			else
			{
				// Process after record is added / updated
				$record->parent = $parent_id;
			}
		}

		$roles = array();
		foreach($this->definition->plugin_options['field_mapping'] as $number => $field_name) {
			if(!$record->$field_name || substr($field_name,0,5) != 'role-') continue;
			list($role, $role_id) = explode('-', $field_name);

			// Known accounts are already changed to account IDs
			if(!is_array($record->$field_name)) $record->$field_name = explode(',',$record->$field_name);

			// Getter is magic, so we can't just do $record->pm_members[user] = ...
			$members = $record->pm_members ? $record->pm_members : array();
			foreach($record->$field_name as $user)
			{
				// User can only have 1 role according to backend
				$members[(int)$user] = array('member_uid' => (int)$user,'role_id'=>(int)$role_id);
			}
			$record->pm_members = $members;

			// Not really a valid field, so remove it now that we're done
			unset($record->field_name);
		}
		if(count($more_categories) > 0) $record->cat_id = array_merge(is_array($record->cat_id) ? $record->cat_id : explode(',',$record->cat_id), $more_categories);

		foreach($this->bo->pe_name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);
			if ($record->$pm_name)
			{
				$record->pm_overwrite |= $id;
			}
		}
		return parent::import_record($record, $import_csv);
	}

	/**
	 * Search for matching records, based on the the given condition
	 *
	 * @param record
	 * @param condition array = array('string' => field name)
	 * @param matches - On return, will be filled with matching records
	 *
	 * @return boolean
	 */
	protected function exists(importexport_iface_egw_record &$record, Array &$condition, &$matches = array())
	{
		$field = $condition['string'];
		if($record->$field) {
			$results = $this->bo->search(
				array( $condition['string'] => $record->$field),
				False
			);
		}

		if ( is_array( $results ) && count( array_keys( $results )) >= 1 ) {
			// apply action to all contacts matching this exists condition
			$action = $condition['true'];
			foreach ( (array)$results as $project ) {
				$record->pm_id = $project['pm_id'];
				if ( $this->definition->plugin_options['update_cats'] == 'add' )
				{
					if ( !is_array( $project['cat_id'] ) ) $project['cat_id'] = explode( ',', $project['cat_id'] );
					if ( !is_array( $record->cat_id ) ) $record->cat_id = explode( ',', $record->cat_id );
					$record->cat_id = implode( ',', array_unique( array_merge( $record->cat_id, $project['cat_id'] ) ) );
				}
				$matches[] = $project;
			}
			return true;
		}
		return false;
	}

	/**
	 * perform the required action
	 *
	 * If a record identifier (ID) is generated for the record because of the action
	 * (eg: a new entry inserted) make sure to update the record with the identifier
	 *
	 * Make sure you record any errors you encounter here:
	 * $this->errors[$record_num] = error message;
	 *
	 * @param int $_action one of $this->actions
	 * @param importexport_iface_egw_record $record contact data for the action
	 * @param int $record_num Which record number is being dealt with.  Used for error messages.
	 * @return bool success or not
	 */
	protected function action ( $_action, importexport_iface_egw_record &$record, $record_num = 0 )
	{
		$_data = $record->get_record_array();
		$this->bo->data = array();
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bo->read($_data['pm_id']);

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0) {
					return true;
				}

				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Backend doesn't like inserting with ID specified, it can overwrite
					unset($_data['pm_id']);
				}
				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					return true;
				} else {
					// Members needs special setting, from projectmanager_ui:173
					$this->bo->data['pm_members'] = $_data['pm_members'];

					$result = $this->bo->save( $_data, true, false );
					if($result) {
						$this->errors[$record_num] = $result;
						return false;
					} else {
						$this->results[$_action]++;
						$record->pm_id = $this->bo->data['pm_id'];
						// This does nothing (yet?) but update the identifier
						$record->save($result->pm_id);
						// Process parent, if present
						if($_data['parent'])
						{
							Link::link('projectmanager', $_data['parent'], 'projectmanager',$this->bo->data['pm_id'],'Linked by import');
						}
						return true;
					}
				}
			default:
				throw new Api\Exception('Unsupported action');

		}
	}

	/**
	 * Handle special fields
	 *
	 * These include linking to other records, which requires a valid identifier,
	 * so must be performed after the action.
	 *
	 * @param importexport_iface_egw_record $record
	 */
	protected function do_special_fields(importexport_iface_egw_record &$record, &$import_csv)
	{
		// Parent does some automatic linking based on field name, but parent doesn't
		// fit the conditions
		parent::do_special_fields($record, $import_csv);

		$id = $record->get_identifier();
		if($id && (int)$record->parent)
		{
			$link_id = Link::link($this->definition->application,$id,'projectmanager',$record->parent);
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Project CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports a list of projects from a CSV file.  Does not include project elements.");
	}

	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options => array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition=null)
	{
		// lets do it!
	}

	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public function get_selectors_etpl() {
		// lets do it!
	}

	/**
	* Returns warnings that were encountered during importing
	* Maximum of one warning message per record, but you can append if you need to
	*
	* @return Array (
	*       record_# => warning message
	*       )
	*/
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	* Returns errors that were encountered during importing
	* Maximum of one error message per record, but you can append if you need to
	*
	* @return Array (
	*       record_# => error message
	*       )
	*/
	public function get_errors() {
		return $this->errors;
	}

	/**
	* Returns a list of actions taken, and the number of records for that action.
	* Actions are things like 'insert', 'update', 'delete', and may be different for each plugin.
	*
	* @return Array (
	*       action => record count
	* )
	*/
	public function get_results() {
		return $this->results;
	}

	public static function project_id($num_or_title)
	{
		static $boprojects;

		if (!$num_or_title) return false;

		if (!is_object($boprojects))
		{
			$boprojects =& CreateObject('projectmanager.projectmanager_bo');
		}
		if (($projects = $boprojects->search(array('pm_number' => $num_or_title), array('pm_id'))) ||
			($projects = $boprojects->search(array('pm_title'  => $num_or_title), array('pm_id'))))
		{
			return $projects[0]['pm_id'];
		}
		return false;
	}
}
?>
