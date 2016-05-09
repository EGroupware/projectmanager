<?php
/**
 * eGroupWare - Wizard for Project CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

class projectmanager_wizard_import_projects_csv extends importexport_wizard_basic_import_csv
{

	/**
	 * constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->steps += array(
			'wizard_step50' => lang('Manage mapping'),
		);

		// Field mapping
		$bo = new projectmanager_bo();
		$this->mapping_fields = $bo->field2label + projectmanager_import_projects_csv::$special_fields;

		// Roles in seperate categories
		$roles = new projectmanager_roles_so();
		$roles = $roles->query_list();
		$role_list = array();
		foreach($roles as $id => $name) {
			$role_list['role-'.$id] = $name;
		}
		if(count($role_list) > 0) {
			$this->mapping_fields[lang('Roles')] = $role_list;
		}
		$custom = Api\Storage\Customfields::get('projectmanager', true);
		foreach($custom as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}

		// Actions
		$this->actions = array(
			'none'		=>	lang('none'),
			'update'	=>	lang('update'),
			'insert'	=>	lang('insert'),
			'delete'	=>	lang('delete'),
		);

		// Conditions
		$this->conditions = array(
			'exists'	=>	lang('exists'),
		);
	}

	function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step50($content, $sel_options, $readonlys, $preserv);
		
		return $result;
	}
}
