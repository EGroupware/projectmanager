<?php
/**
 * ProjectManager - eRoles user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-11 by Christian Binder <christian-AT-jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_eroles_ui.inc.php 27222 2009-06-08 16:21:14Z jaytraxx $
 */
 
define('EGW_ACL_PROJECT_EROLES',EGW_ACL_EDIT);

/**
 * ProjectManager UI: eRoles
 * eRoles - element roles - define the role of an egroupware element when it gets merged with a document
 */
 
class projectmanager_eroles_ui extends projectmanager_bo
{
	/**
	 * Functions to call via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'eroles' => true,
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
	 * @return projectmanager_eroles_ui
	 */
	function __construct()
	{
		parent::__construct(null,'eroles');
	}

	/**
	 * Create and edit eRoles
	 *
	 * @param array $content=null
	 */
	function eroles($content=null)
	{
		$tpl = new etemplate('projectmanager.eroles');

		$pm_id = is_array($content) ? $content['pm_id'] : (int) $_REQUEST['pm_id'];

		$only = !!$pm_id;
		if (!($project_rights = $this->check_acl(EGW_ACL_PROJECT_EROLES,$pm_id)) || !$this->is_admin)
		{
			$only = $project_rights ? 1 : 0;
			$readonlys['1[pm_id]'] = true;

			if (!$project_rights && !$this->is_admin)
			{
				$readonlys['edit'] = $readonlys['apply'] = true;
			}
		}

		$erole_to_edit = array('pm_id' => $only);
		$js = 'window.focus();';

		if (($content['save'] || $content['apply']))
		{
			if (!$content[1]['role_title'])
			{
				$erole_to_edit = $content[1];
				$msg = lang('Title must not be empty');
			}
			else
			{
				$erole = array(
					'role_id'          => (int) $content[1]['role_id'],
					'role_title'       => $content[1]['role_title'],
					'role_description' => $content[1]['role_description'],
					'pm_id'            => $content[1]['pm_id'] || !$this->is_admin ? $pm_id : 0,
				);
				if ($this->eroles->save($erole) == 0)
				{
					$msg = lang('Element role saved');

					$js = 'opener.document.eTemplate.submit();';

					if ($content['save']) $js .= 'window.close();';
				}
				else
				{
					$msg = lang('Error: saving element role !!!');
				}
			}
		}
		if ($content['delete'] || $content['edit'])
		{
			list($erole) = $content['delete'] ? each($content['delete']) : each($content['edit']);
			if(!($erole = $this->eroles->read($erole)))
			{
				$msg = lang('Permission denied !!!');
			}
			elseif ($content['delete'])
			{
				if ($this->eroles->delete($erole))
				{
					$msg = lang('Element role deleted');
					$js = 'opener.document.eTemplate.submit();';
				}
				else
				{
					$msg = lang('Error: deleting element role !!!');
				}
			}
			else	// edit an existing erole
			{
				$erole_to_edit = $erole;
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
			1       => $erole_to_edit,
		);
		$n = 2;
		foreach((array)$this->eroles->search(array(),false,'role_title ASC','','',false,'AND',false,array('pm_id'=>array(0,$pm_id))) as $erole)
		{
			$content[$n++] = $erole;

			$readonlys['delete['.$erole['role_id'].']'] = $readonlys['edit['.$erole['role_id'].']'] =
				!$erole['pm_id'] && !$this->is_admin;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Add or edit element roles');
		$tpl->exec('projectmanager.projectmanager_eroles_ui.eroles',$content,array(
			'pm_id' => $this->query_list(),
		),$readonlys,array(
			'pm_id' => $pm_id,
			1       => array('role_id' => $content[1]['role_id']),
		),2);
	}
}