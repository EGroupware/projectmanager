<?php
/**
 * ProjectManager - Roles user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('EGW_ACL_ROLES',EGW_ACL_EDIT);	// maybe this gets an own ACL later

/**
 * ProjectManager UI: roles
 */
class projectmanager_roles_ui extends projectmanager_bo
{
	/**
	 * Functions to call via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'roles' => true,
	);
	var $acl2id = array(
		'read'   => EGW_ACL_READ,
		'edit'   => EGW_ACL_EDIT,
		'delete' => EGW_ACL_DELETE,
		'add'    => EGW_ACL_ADD,
		'budget' => EGW_ACL_BUDGET,
		'edit_budget' => EGW_ACL_EDIT_BUDGET,
	);
	/**
	 * Instance of the boprojectmanger class
	 *
	 * @var projectmanager_bo
	 */
	var $project;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @return projectmanager_roles_ui
	 */
	function __construct()
	{
		parent::__construct(null,'roles');
	}

	/**
	 * Create and edit roles
	 *
	 * @param array $content=null
	 */
	function roles($content=null)
	{
		$tpl = new etemplate('projectmanager.roles');

		$pm_id = is_array($content) ? $content['pm_id'] : (int) $_REQUEST['pm_id'];

		$only = !!$pm_id;
		if (!($project_rights = $this->check_acl(EGW_ACL_ROLES,$pm_id)) || !$this->is_admin)
		{
			$only = $project_rights ? 1 : 0;
			$readonlys['1[pm_id]'] = true;

			if (!$project_rights && !$this->is_admin)
			{
				$readonlys['edit'] = $readonlys['apply'] = true;
			}
		}
		$role_to_edit = array('pm_id' => $only);
		$js = 'window.focus();';

		if (($content['save'] || $content['apply']) && (!$pm_id && $this->is_admin || $pm_id && $project_rights))
		{
			if (!$content[1]['role_title'])
			{
				$role_to_edit = $content[1];
				$msg = lang('Title must not be empty');
			}
			else
			{
				$role = array(
					'role_id'          => (int) $content[1]['role_id'],
					'role_title'       => $content[1]['role_title'],
					'role_description' => $content[1]['role_description'],
					'pm_id'            => $content[1]['pm_id'] || !$this->is_admin ? $pm_id : 0,
					'role_acl'         => 0,
				);
				foreach($this->acl2id as $acl => $id)
				{
					if ($content[1]['acl_'.$acl]) $role['role_acl'] |= $id;
				}
				if ($this->roles->save($role) == 0)
				{
					$msg = lang('Role saved');

					$js = 'opener.document.eTemplate.submit();';

					if ($content['save']) $js .= 'window.close();';
				}
				else
				{
					$msg = lang('Error: saving role !!!');
				}
			}
		}
		if ($content['delete'] || $content['edit'])
		{
			list($role) = $content['delete'] ? each($content['delete']) : each($content['edit']);
			if(!($role = $this->roles->read($role)) ||
				$role['pm_id'] && !$this->check_acl(EGW_ACL_ROLES,$role['pm_id']) ||
				!$role['pm_id'] && !$this->is_admin)
			{
				$msg = lang('Permission denied !!!');
			}
			elseif ($content['delete'])
			{
				if ($this->roles->delete($role))
				{
					$msg = lang('Role deleted');
					$js = 'opener.document.eTemplate.submit();';
				}
				else
				{
					$msg = lang('Error: deleting role !!!');
				}
			}
			else	// edit an existing role
			{
				$role_to_edit = $role;
				foreach($this->acl2id as $acl => $id)
				{
					$role_to_edit['acl_'.$acl] = $role['role_acl'] & $id;
				}
			}
		}
		if (($view = !($pm_id && $project_rights) && !$this->is_admin))
		{
			$readonlys['save'] = $readonlys['apply'] = true;
		}
		$content = array(
			'pm_id' => $pm_id,
			'msg'   => $msg,
			'view'  => !($pm_id && $project_rights) && !$this->is_admin,
			'js'    => '<script>'.$js.'</script>',
			1       => $role_to_edit,
		);
		$n = 2;
		foreach((array)$this->roles->search(array(),false,'pm_id DESC,role_acl DESC','','',false,'AND',false,array('pm_id'=>array(0,$pm_id))) as $role)
		{
			foreach($this->acl2id as $acl => $id)
			{
				$role['acl_'.$acl] = $role['role_acl'] & $id;
			}
			$content[$n++] = $role;

			$readonlys['delete['.$role['role_id'].']'] = $readonlys['edit['.$role['role_id'].']'] =
				!$role['pm_id'] && !$this->is_admin || $role['pm_id'] && !$this->check_acl(EGW_ACL_ROLES,$role['pm_id']);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Add or edit roles and their ACL');
		$tpl->exec('projectmanager.projectmanager_roles_ui.roles',$content,array(
			'pm_id' => $this->query_list(),
		),$readonlys,array(
			'pm_id' => $pm_id,
			1       => array('role_id' => $content[1]['role_id']),
		),2);
	}
}