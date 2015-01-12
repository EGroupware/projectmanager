<?php

/*
 * Egroupware - Infolog - A portlet for displaying a list of portlet entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * The projectmanager_list_portlet uses a nextmatch / favorite
 * to display a list of projects.
 */
class projectmanager_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'projectmanager';
		
		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$this->nm_settings += array(
			'get_rows'	=> 'projectmanager.projectmanager_ui.get_rows',
			'template'	=> 'projectmanager.list.rows',
			// Don't overwrite projectmanager
			'session_for'	=> 'home',
			'no_filter2'	=> true,
			// Allow add actions even when there's no rows
			'placeholder_actions'	=> array(),
			// Start with reduced columns, it's easier for user to add them in
			// than remove them
			'default_cols'	=> 'pm_number,pm_title,pm_completion',
			'row_id'        => 'pm_id',
			'row_modified'	=> 'pm_modified'
		);
	}

	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		$ui = new projectmanager_ui();
		$this->nm_settings['options-filter'] = $ui->filter_labels;
		$this->nm_settings['options-filter2']= projectmanager_ui::$status_labels;
		$this->nm_settings['actions'] = $ui->get_actions();

		$this->context['template'] = 'projectmanager.list.rows';
		// Set up role columns
		$ui->instanciate('roles');
		$roles = $ui->roles->query_list();
		$role_count = 0;
		foreach($roles as $role_name)
		{
			if($role_count > 5)
			{
				break;
			}
			$this->nm_settings['roles'][$role_count] = $role_name;
			$role_count++;
		}
		// Clear extras
		for(; $role_count < 5; $role_count++)
		{
			$this->nm_settings['no_role'.$role_count] = true;
		}
		return parent::exec($id, $etemplate);
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param array $content Values returned from etemplate submit
	 */
	public static function process($content = array())
	{
		parent::process($values);
		$ui = new projectmanager_ui();
		if ($content['nm']['action'])
		{
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
				egw_json_response::get()->apply('egw.message',array($msg,'error'));
			}
			else
			{
				if ($ui->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,'project_list',$msg,$content['nm']['checkboxes']['sources_too']))
				{
					$msg .= lang('%1 project(s) %2',$success,$action_msg);
					egw_json_response::get()->apply('egw.message',array($msg,'success'));
					foreach($content['nm']['selected'] as &$id)
					{
						$id = 'projectmanager::'.$id;
					}
					// Directly request an update - this will get timesheet tab too
					egw_json_response::get()->apply('egw.dataRefreshUIDs',array($content['nm']['selected']));
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 project(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					egw_json_response::get()->apply('egw.message',array($msg,'error'));
				}
			}
		}
	}
 }