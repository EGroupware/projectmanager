<?php
/**
 * ProjectManager - diverse hooks: Admin-, Preferences-, SideboxMenu-Hooks, ...
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;

/**
 * diverse hooks for ProjectManager: all static functions
 *
 */
class projectmanager_hooks
{
	private static $weekdays = array(
		1 => 'monday',
		2 => 'tuesday',
		3 => 'wednesday',
		4 => 'thursday',
		5 => 'friday',
		6 => 'saturday',
		0 => 'sunday',
	);
	private static $config = array();

	/**
	 * Init our static properties
	 *
	 */
	public static function init_static()
	{
		self::$config = Api\Config::read('projectmanager');
	}

	/**
	 * Hook called by link-class to include projectmanager in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		unset($location);	// not used, but required by function signature

		return array(
			'query'      => 'projectmanager.projectmanager_bo.link_query',
			'title'      => 'projectmanager.projectmanager_bo.link_title',
			'titles'     => 'projectmanager.projectmanager_bo.link_titles',
			'view'       => array(
				'menuaction' => 'projectmanager.projectmanager_ui.index',
				'ajax'   => 'true',
			),
			'view_id'    => 'pm_id',
			'list'       => array(
				'menuaction' => 'projectmanager.projectmanager_ui.index',
			),
			'notify'     => 'projectmanager_elements_bo::notify',
			'add'        => array(
				'menuaction' => 'projectmanager.projectmanager_ui.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '900x450',
			'edit'       => array(
				'menuaction' => 'projectmanager.projectmanager_ui.edit',
			),
			'edit_id'    => 'pm_id',
			'edit_popup' => '900x480',
			'file_access' => 'projectmanager.projectmanager_bo.file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'additional' => array(
				'projectelement' => array(
					'view'       => array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.edit',
					),
					'view_id'    => 'pe_id',
					'view_popup' => '715x570',
					'edit'       => array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.edit',
					),
					'edit_id'    => 'pe_id',
					'edit_popup' => '715x570',
					'title'      => 'projectmanager.projectmanager_elements_bo.title',
					'titles'     => 'projectmanager.projectmanager_elements_bo.titles',
					'query'      => 'projectmanager.projectmanager_elements_bo.link_query',
				),
				'pm_milestone' => array(
					'view' => array(
						'menuaction' => 'projectmanager.projectmanager_milestones_ui.edit'
					),
					'view_id' => 'ms_id',
					'view_popup' => '680x450',
					'edit' => array(
						'menuaction' => 'projectmanager.projectmanager_milestones_ui.edit'
					),
					'edit_id' => 'ms_id',
					'edit_popup' => '680x450',
					'add' => array(
						'menuaction' => 'projectmanager.projectmanager_milestones_ui.edit'
					),
					'add_id' => 'ms_id',
					'add_popup' => '680x450',
					'titles' => 'projectmanager.projectmanager_milestones_so.titles',
					'query' => 'projectmanager.projectmanager_milestones_so.link_query',
				)
			),
			'merge' => true,
			'entry' => 'Project',
			'entries' => 'Projects',
		);
	}

	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = 'projectmanager';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ranking_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			// Magic etemplate2 favorites menu (from nextmatch widget)
			display_sidebox($appname, lang('Favorites'), Framework\Favorites::list_favorites($appname, 'nextmatch-projectmanager.list.rows-favorite'));

			// project-dropdown in sidebox menu
			if (!is_object($GLOBALS['projectmanager_bo']))
			{
				// dont assign it to $GLOBALS['projectmanager_bo'], as the constructor does it!!!
				CreateObject('projectmanager.projectmanager_ui');
			}
			if (isset($_REQUEST['pm_id']))
			{
				$pm_id = (int) $_REQUEST['pm_id'];
				$GLOBALS['egw']->preferences->add('projectmanager','current_project', $pm_id);
				$GLOBALS['egw']->preferences->save_repository();
			}
			else if ($_GET['pm_id'])
			{
				$pm_id = (int) $_REQUEST['pm_id'];
			}
			else
			{
				$pm_id = (int) $GLOBALS['egw_info']['preferences']['projectmanager']['current_project'];
			}
			$file = array('Projectlist' => 'javascript:app.projectmanager.show("list")');
			if($GLOBALS['projectmanager_bo']->check_acl(Acl::READ))
			{
				$file += array(
					array(
						'text' => 'Elementlist',
						'link' => 'javascript:app.projectmanager.show("elements")'
					),
					array(
						'text' => 'Ganttchart',
						'link' => 'javascript:app.projectmanager.show("gantt")'
					)
				);
			}
			// show pricelist menuitem only if we use pricelists
			if (!self::$config['accounting_types'] || in_array('pricelist',(is_array(self::$config['accounting_types'])?self::$config['accounting_types']:explode(',',self::$config['accounting_types']))))
			{
				// menuitem links to project-spezific priclist only if user has rights and it is used
				// to not always instanciate the priclist class, this code dublicats bopricelist::check_acl(Acl::READ),
				// specialy the always existing READ right for the general pricelist!!!
				$file[] = array(
					'text' => 'Pricelist',
					'icon' => 'pricelist',
					'app'  => 'projectmanager',
					'link' => 'javascript:app.projectmanager.show("prices")'
				);
			}
			if (isset($GLOBALS['egw_info']['user']['apps']['filemanager']))
			{
				$file[] = array(
					'text' => 'Filemanager',
					'icon' => 'navbar',
					'app'  => 'filemanager',
					'link' => Egw::link('/index.php',array(
						'menuaction' => 'filemanager.filemanager_ui.index',
						'ajax'       => 'true',
					),'filemanager'),
				);
			}

			$file[] = ['text'=>'--'];
			// Target for project tree
			$file[] = array(
				'no_lang' => true,
				'text'=>'<span id="projectmanager-tree_target" />',
				'link'=>false,
				'icon' => false
			);

			$file[] = ['text'=>'--'];
			$file['Placeholders'] = Egw::link('/index.php','menuaction=projectmanager.projectmanager_merge.show_replacements');
			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);

			// allways show sidebox
			unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site configuration' => Egw::link('/index.php',array(
					'menuaction' => 'projectmanager.projectmanager_admin.config',
					'ajax' => 'true',
				)),
				'Custom fields' => Egw::link('/index.php','menuaction=admin.admin_customfields.index&appname=projectmanager&use_private=1&ajax=true'),
				'Global Categories'  => Egw::link('/index.php',array(
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
					'ajax' => 'true',
				)),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * Show the project-selection as selectbox
	 *
	 * @param int $pm_id current active project
	 * @param array $filter filter for the project-list
	 * @param string $select_link link to load the elementslist of an appended project (pm_id)
	 * @param string $label column to use as label
	 * @param string $title column to use as title (tooltip)
	 * @return array suitable for the sidebox-menu
	 */
	static private function &_project_selectbox($pm_id,$filter,$select_link,$label,$title)
	{
		unset($filter);	// not used anymore
		$projects = array();
		foreach((array)$GLOBALS['projectmanager_bo']->search(array(
			'pm_status' => 'active',
			'pm_id'     => $pm_id,        // active or the current one
		),$GLOBALS['projectmanager_bo']->table_name.'.pm_id AS pm_id,pm_number,pm_title','pm_number','','',False,'OR') as $project)
		{
			$projects[$project['pm_id']] = $project[$label].($label == 'pm_number' ? ': '.$project[$title] : ' ('.$project[$title].')');
		}
		if ($_GET['menuaction'] == 'projectmanager.projectmanager_pricelist_ui.index')
		{
			$projects[0] = lang('General pricelist');
		}
		elseif (!$pm_id)
		{
			$projects[0] = lang('Select a project');
		}
		return array(
			'text' => Api\Html::select('pm_id',$pm_id,$projects,true,' style="width: 100%;"'.
				' onchange="egw_appWindow(\'projectmanager\').location.href=\''.$select_link.'\'+this.value;" title="'.Api\Html::htmlspecialchars(
				$pm_id && isset($projects[$pm_id]) ? $projects[$pm_id] : lang('Select a project')).'"'),
			'no_lang' => True,
			'link' => False
		);
	}

	/**
	 * Return settings for the preferences
	 *
	 * @return array
	 */
	static function settings()
	{
		$apps = Link::app_list('add_app');
		foreach (array('addressbook', 'bookmarks', 'tracker', 'resources') as $unset_app) // these apps never show as pe since they don't have end date
		{
			unset($apps[$unset_app]);
		}
		asort($apps);
		$settings[] = Array(
			'type'  => 'section',
			'title' => lang('General settings'),
			'no_lang'=> true,
			'xmlrpc' => False,
			'admin'  => False
		);
		$settings['show_custom_app_icons'] = array(
			'type'   => 'check',
			'label'  => 'Show status icons of the datasources',
			'name'   => 'show_custom_app_icons',
			'help'   => 'Should Projectmanager display the status icons of the datasource (eg. InfoLog) or just a progressbar with the numerical status (faster).',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> true,
		);
		$settings['link_sort_order'] = array(
			'type'     => 'select',
			'label'    => 'Project sort order',
			'name'     => 'link_sort_order',
			'values'   => array(
				'pm_number DESC' => lang('Project ID') . '▼',
				'pm_number ASC' => lang('Project ID') . '▲',
				'pm_title ASC'  => lang('Project title'),
				'pm_created DESC' => lang('creation date and time')
			),
			'help'     => 'Select project sort order for project tree and link search',
			'default'  => 'pm_created DESC'
		);
		$settings['show_links'] = array(
			'type'   => 'check',
			'label'  => 'Show links in the Project Elements list',
			'name'   => 'show_links',
			'help'   => 'Should Project Elements show the links to other applications and/or the file-attachments in the Project Elements list (only when showing details).',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> false,
		);
		$settings['show_infolog_type_icon'] = array(
			'type'   => 'check',
			'label'  => 'Show infolog type icon in the Project Elements list',
			'name'   => 'show_infolog_type_icon',
			'help'   => 'Should Project Elements list show the dedicated icons of the infolog types. Icons for infolog custom types can be added at the VFS-Path where additional images, icons or logos can be found (see Site Configuration). If 32x32 pixels icons are uploaded with a file name ending with \'_element\', that bigger icon will be loaded in the element list.',
			'xmlrpc' => True,
			'admin'  => False,
			'forced'=> False,
		);
		$pm_list_options = array(
			'~edit~'    => lang('Edit project'),
			'list' => lang('Element list'),
		);
		$settings['pm_list'] = array(
			'type'   => 'select',
			'label'  => 'Default action on double-click',
			'name'   => 'pm_list',
			'values' => $pm_list_options,
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'list',
		);
		$settings['show_projectselection'] = array(
			'type'   => 'select',
			'label'  => 'Show the project selection as',
			'name'   => 'show_projectselection',
			'values' => array(
				'tree_with_number'      => lang('Tree with %1',lang('Project ID')),
				'tree_with_title'       => lang('Tree with %1',lang('Title')),
				'tree_with_number_title'=> lang('Tree with %1',lang('Project ID').': '.lang('Title'))
			),
			'help'   => 'How should the project selection in the menu be displayed',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> 'tree_with_title',
		);
		$settings['gantt_element_title_length'] = array(
			'type'   => 'input',
			'size'   => 5,
			'label'  => 'Limit number of characters in ganttchart element title (0 for no limit)',
			'name'   => 'gantt_element_title_length',
			'help'   => 'Number of characters to which title of ganttchart elements should be shortened to.',
			'run_lang' => false,
			'xmlrpc' => True,
			'admin'  => False,
			'forced' => '0',
		);
		$settings['gantt_pm_elementbars_order'] = array(
			'type'   => 'select',
			'label'  => 'Order of sub-project bars in ganttcharts',
			'name'   => 'gantt_pm_elementbars_order',
			'values' => array(
				'pe_start,pe_end'    => lang('Start Date'),
				'pe_end'             => lang('End Date'),
				'pe_title'           => lang('Project ID'),
			),
			'help'   => 'Set order to show sub-project bars in ganttcharts.',
			'run_lang' => false,
			'xmlrpc' => True,
			'admin'  => False,
			'forced' => 'pe_start,pe_end',
		);
		$settings['gantt_show_elements_by_type'] = array (
			'type'		=> 'multiselect',
			'label'		=> 'Show elements in GanttChart by applications',
			'help'		=> 'Show elements in GanttChart depending on applications they come from (none = all)',
			'name'		=> 'gantt_show_elements_by_type',
			'values' 	=> $apps,
			'xmlrpc' 	=> True,
			'admin'  	=> False
		);
		$start = array();
		for($i = 0; $i < 24*60; $i += 30)
		{
			if ($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12)
			{
				if (!($hour = ($i / 60) % 12))
				{
					$hour = 12;
				}
				$start[$i] = sprintf('%01d:%02d %s',$hour,$i % 60,$i < 12*60 ? 'am' : 'pm');
			}
			else
			{
				$start[$i] = sprintf('%01d:%02d',$i/60,$i % 60);
			}
		}
		$duration = array(0 => lang('not working'));
		for($i = 30; $i <= 24*60; $i += 30)
		{
			$duration[$i] = sprintf('%3.1lf',$i / 60.0).' '.lang('hours');
		}
		$settings[] = array(
			'type'  => 'section',
			'title' => lang('Availability settings'),
			'no_lang'=> true,
			'xmlrpc' => False,
			'admin'  => False
		);
		foreach(self::$weekdays as $day => $label)
		{
			$settings['duration_'.$day] = array(
				'type'   => 'select',
				'label'  => lang('Working duration on %1',lang($label)),
				'run_lang' => -1,
				'name'   => 'duration_'.$day,
				'values' => $duration,
				'help'   => 'How long do you work on the given day.',
				'xmlrpc' => True,
				'admin'  => !self::$config['allow_change_workingtimes'],
				'default'=> $day && $day < 5 ? 8*60 : ($day == 5 ? 6*60 : 0),
			);
			$settings['start_'.$day] = array(
				'type'   => 'select',
				'label'  => lang('Start working on %1',lang($label)),
				'run_lang' => -1,
				'name'   => 'start_'.$day,
				'values' => $start,
				'help'   => 'At which time do you start working on the given day.',
				'xmlrpc' => True,
				'admin'  => !self::$config['allow_change_workingtimes'],
				'default'=> $day && $day < 6 ? 9*60 : 0,
			);
			// forcing Saturday (6) and Sunday (0)
			if ($day == 6 || $day == 0)
			{
				$settings['duration_'.$day]['forced'] = $settings['start_'.$day]['forced'] = '0';
			}
		}

		$settings[] = array(
				'type'  => 'section',
				'title' => lang('Notification settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
		);

		// notification preferences
		$settings['notify_creator'] = array(
			'type'   => 'check',
			'label'  => 'Receive notifications about own items',
			'name'   => 'notify_creator',
			'help'   => 'Do you want a notification, if items you created get updated?',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '1',	// Yes
		);
		$roles = new projectmanager_roles_so();
		$settings['notify_assigned'] = array(
			'type'   => 'multiselect',
			'label'  => 'Receive notifications about items assigned to you with these roles',
			'name'   => 'notify_assigned',
			'help'   => 'Do you want a notification, if items get assigned to you or assigned items get updated?',
			'values' => array(
				// Roles
				'0' => lang('No')
			) + $roles->query_list(),
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);

		// to add options for more then 3 days back or in advance, you need to update soinfolog::users_with_open_entries()!
		$options = array(
			'0'   => lang('No'),
			'-1d' => lang('one day after'),
			'0d'  => lang('same day'),
			'1d'  => lang('one day in advance'),
			'2d'  => lang('%1 days in advance',2),
			'3d'  => lang('%1 days in advance',3),
		);
		$settings['notify_due_planned'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about due entries (planned dates)',
			'name'   => 'notify_due_planned',
			'help'   => 'Do you want a notification, if items are due according to their planned dates?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);
		$settings['notify_due_real'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about due entries (real dates)',
			'name'   => 'notify_due_real',
			'help'   => 'Do you want a notification, if items are due according to their real dates?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0d',	// Same day
		);
		$settings['notify_start_planned'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about starting entries (planned dates)',
			'name'   => 'notify_start_planned',
			'help'   => 'Do you want a notification, if items are about to start according to their planned dates?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);
		$settings['notify_start_real'] = array(
			'type'    => 'select',
			'label'   => 'Receive notifications about starting entries (real dates)',
			'name'    => 'notify_start_real',
			'help'    => 'Do you want a notification, if items are about to start according to their real dates?',
			'values'  => $options,
			'xmlrpc'  => True,
			'admin'   => False,
			'default' => '0d',    // Same day
		);

		$settings[] = array(
			'type'    => 'section',
			'title'   => lang('ID generation'),
			'no_lang' => true,
			'xmlrpc'  => False,
			'admin'   => False
		);
		$settings['id-generation-format'] = array(
			'type'    => 'input',
			'size'    => 20,
			'label'   => 'How should IDs for new projects be generated?',
			'name'    => 'id-generation-format',
			'help'    => "You can use %Ymd to insert the date of creation. It uses the same syntax like the PHP funktion date(). Other placeholders are %px to insert the parents ID (only at the subprojects generation) or %ix to insert an index. Indices will be increased automatically to avoid duplicated IDs. Every generation format should contain exactly one index! (Exept you are sure that the date will identify the project). You can also use e.g. %04ix. This index will be filled with '0' to 4 digits (e.g. 0001). If you leave out the filling character (e.g. %5ix), the index will be filled with '0'.",
			'xmlrpc'  => true,
			'admin'   => false,
			'default' => 'P-%Y-%04ix',
		);
		$settings['id-generation-format-sub'] = array(
			'type'    => 'input',
			'size'    => 20,
			'label'   => 'How should IDs for new subprojects be generated?',
			'name'    => 'id-generation-format-sub',
			'help'    => "You can use %Ymd to insert the date of creation. It uses the same syntax like the PHP funktion date(). Other placeholders are %px to insert the parents ID (only at the subprojects generation) or %ix to insert an index. Indices will be increased automatically to avoid duplicated IDs. Every generation format should contain exactly one index! (Exept you are sure that the date will identify the project). You can also use e.g. %04ix. This index will be filled with '0' to 4 digits (e.g. 0001). If you leave out the filling character (e.g. %5ix), the index will be filled with '0'.",
			'xmlrpc'  => true,
			'admin'   => false,
			'default' => '%px/%04ix',
		);

		$settings[] = array(
			'type'    => 'section',
			'title'   => lang('Data exchange settings'),
			'no_lang' => true,
			'xmlrpc'  => False,
			'admin'   => False
		);
		if($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$merge = new projectmanager_merge();
			$settings += $merge->merge_preferences();
		}
		return $settings;
	}

	/**
	 * Verification hook called if settings / preferences get stored
	 *
	 * Installs a task to send async infolog notifications at 2h everyday
	 *
	 * @param array $data
	 */
	static function verify_settings($data)
	{
		// If no assigned roles, make sure that's the only thing selected
		$assigned =& $data['prefs']['notify_assigned'];
		if(strpos($assigned, '0') !== FALSE)
		{
			$assigned = '0';
		}
		if(empty($assigned) && $assigned !== '0')
		{
			// Don't let an actual empty stay
			unset($data['prefs']['notify_assigned']);
		}

		// Make sure async notification is there
		if ($data['prefs']['notify_due_planned'] || $data['prefs']['notify_due_real'] ||
			$data['prefs']['notify_start_planned'] || $data['prefs']['notify_start_real'])
		{
			$async = new Api\Asyncservice();

			if (!$async->read('projectmanager-async-notification'))
			{
				$async->set_timer(array('hour' => 2),'projectmanager-async-notification','projectmanager.projectmanager_bo.async_notification',null);
			}
		}
	}

	/**
	 * ACL rights and labels used
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected Acl owner
	 */
	public static function acl_rights($params)
	{
		unset($params);	// not used, but required by function signature

		return array(
			Acl::READ    => 'read',
			Acl::EDIT    => 'edit',
			Acl::DELETE  => 'delete',
			Acl::PRIVAT  => 'private',
			Acl::ADD     => 'add element',
			Acl::CUSTOM1 => 'budget',
			Acl::CUSTOM2 => 'edit budget',
		);
	}

	/**
	 * Hook to tell framework we use standard Api\Categories method
	 *
	 * @param string|array $data hook-data or location
	 * @return boolean
	 */
	public static function categories($data)
	{
		unset($data);	// not used, but required by function signature

		return true;
	}

	/**
	 * Hook for timesheet to set some extra data and links
	 *
	 * @param array $data
	 * @param int $data[id] project_id
	 * @return array with key => value pairs to set in new timesheet and link_app/link_id arrays
	 */
	public static function timesheet_set($data)
	{
		$set = array();
		if (!is_object($GLOBALS['projectmanager_bo']))
		{
			// dont assign it to $GLOBALS['projectmanager_bo'], as the constructor does it!!!
			CreateObject('projectmanager.projectmanager_ui');
		}
		if ((int)$data['id'] && ($entry = $GLOBALS['projectmanager_bo']->read($data['id'])))
		{
			if ($entry['cat_id']) $set['cat_id'] = $entry['cat_id'];
		}
		return $set;
	}

}

projectmanager_hooks::init_static();