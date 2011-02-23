<?php
/**
 * ProjectManager - diverse hooks: Admin-, Preferences-, SideboxMenu-Hooks, ...
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
		self::$config = config::read('projectmanager');
	}

	/**
	 * Hook called by link-class to include projectmanager in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		return array(
			'query' => 'projectmanager.projectmanager_bo.link_query',
			'title' => 'projectmanager.projectmanager_bo.link_title',
			'titles' => 'projectmanager.projectmanager_bo.link_titles',
			'view'  => array(
				'menuaction' => 'projectmanager.projectmanager_elements_ui.index',
			),
			'view_id' => 'pm_id',
			'view_list' => 'projectmanager.projectmanager_ui.index',
			'notify' => 'projectmanager.projectmanager_elements_bo.notify',
			'add' => array(
				'menuaction' => 'projectmanager.projectmanager_ui.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'file_access' => 'projectmanager.projectmanager_bo.file_access',
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
			// project-dropdown in sidebox menu
			if (!is_object($GLOBALS['projectmanager_bo']))
			{
				// dont assign it to $GLOBALS['projectmanager_bo'], as the constructor does it!!!
				CreateObject('projectmanager.projectmanager_ui');
			}
			if (isset($_REQUEST['pm_id']))
			{
				egw_session::appsession('pm_id','projectmanager',$pm_id = (int) $_REQUEST['pm_id']);
			}
			else
			{
				$pm_id = (int) egw_session::appsession('pm_id','projectmanager');
			}
			$file = array(
				'Projectlist' => egw::link('/index.php',array(
					'menuaction' => 'projectmanager.projectmanager_ui.index' )),
				array(
					'text' => 'Elementlist',
					'link' => $pm_id ? egw::link('/index.php',array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.index',
					)) : False,
				),
				array(
					'text' => 'Ganttchart',
					'link' => $pm_id ? egw::link('/index.php',array(
						'menuaction' => 'projectmanager.projectmanager_ganttchart.show',
					)) : False,
				),
			);
			// show pricelist menuitem only if we use pricelists
			if (!self::$config['accounting_types'] || in_array('pricelist',explode(',',self::$config['accounting_types'])))
			{
				// menuitem links to project-spezific priclist only if user has rights and it is used
				// to not always instanciate the priclist class, this code dublicats bopricelist::check_acl(EGW_ACL_READ),
				// specialy the always existing READ right for the general pricelist!!!
				$file['Pricelist'] = egw::link('/index.php',array(
					'menuaction' => 'projectmanager.projectmanager_pricelist_ui.index',
					'pm_id' => $pm_id && $GLOBALS['projectmanager_bo']->check_acl(EGW_ACL_BUDGET,$pm_id) &&
						 $GLOBALS['projectmanager_bo']->data['pm_accounting_type'] == 'pricelist' ? $pm_id : 0,
				));
			}
			if (isset($GLOBALS['egw_info']['user']['apps']['filemanager']))
			{
				$file[] = array(
					'text' => 'Filemanager',
					'icon' => 'navbar',
					'app'  => 'filemanager',
					'link' => egw::link('/index.php',array(
						'menuaction' => 'filemanager.filemanager_ui.index',
						'pm_id'      => $pm_id,
					)),
				);
			}

			// include the filter of the projectlist into the projectlist, eg. if you watch the list of templates, include them in the tree
			$filter = array('pm_status' => 'active');
			$list_filter = egw_session::appsession('project_list','projectmanager');
			if ($_GET['menuaction'] == 'projectmanager.projectmanager_ui.index' && isset($_POST['exec']['nm']['filter2']))
			{
				//echo "<p align=right>set pm_status={$_POST['exec']['nm']['filter2']}</p>\n";
				$list_filter['pm_status'] = $_POST['exec']['nm']['filter2'];	// necessary as projectmanager_ui::get_rows is not yet executed
			}
			if(in_array($list_filter['filter2'],array('nonactive','archive','template')))
			{
				$filter['pm_status'] = array('active',$list_filter['filter2']);
			}
			switch($_GET['menuaction'])
			{
				case 'projectmanager.projectmanager_ganttchart.show':
				case 'projectmanager.projectmanager_pricelist_ui.index':
				case 'filemanager.filemanager_ui.index':
					$selbox_action = $_GET['menuaction'];
					break;
				default:
					$selbox_action = 'projectmanager.projectmanager_elements_ui.index';
					break;
			}
			$select_link = egw::link('/index.php',array('menuaction' => $selbox_action),false).'&pm_id=';

			// show the project-selection as tree or -selectbox
			// $_POST['user']['show_projectselection'] is used to give the user immediate feedback, if he changes the prefs
			$type = isset($_POST['user']['show_projectselection']) ? $_POST['user']['show_projectselection'] :
				$GLOBALS['egw_info']['user']['preferences']['projectmanager']['show_projectselection'];
			if (substr($type,-5) == 'title')
			{
				$label = 'pm_title';
				$title = 'pm_number';
			}
			else
			{
				$label = 'pm_number';
				$title = 'pm_title';
			}
			if (substr($type,0,9) == 'selectbox')
			{
				$projectlist =& self::_project_selectbox($pm_id,$filter,$select_link,$label,$title);
			}
			else
			{
				$projectlist =& self::_project_tree($pm_id,$filter,$select_link,$label,$title);
			}
			if ($projectlist) $file[] =& $projectlist;

			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);

			// allways show sidebox
			unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => egw::link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => egw::link('/index.php','menuaction=projectmanager.projectmanager_admin.config'),
				'Custom fields' => egw::link('/index.php','menuaction=admin.customfields.edit&appname=projectmanager'),
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
					'CSV-Import'         => egw::link('/projectmanager/csv_import.php')
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
	 * Show the project-selection as tree
	 *
	 * @param int $pm_id current active project
	 * @param array $filter filter for the project-list
	 * @param string $select_link link to load the elementslist of an appended project (pm_id)
	 * @param string $label column to use as label
	 * @param string $title column to use as title (tooltip)
	 * @return array suitable for the sidebox-menu
	 */
	static private function &_project_tree($pm_id,$filter,$select_link,$label,$title)
	{
		$selected_project = false;
		$projects = array();
		foreach($GLOBALS['projectmanager_bo']->get_project_tree($filter) as $project)
		{
			$projects[$project['path']] = array(
				'label' => $project[$label],
				'title' => $project[$title],
			);
			if (!$selected_project && $pm_id == $project['pm_id']) $selected_project = $project['path'];
		}
		if ($_GET['menuaction'] == 'projectmanager.projectmanager_pricelist_ui.index')
		{
			$projects['general'] = array(
				'label' => lang('General pricelist'),
				'image' => 'kfm_home.png',
			);
			if (!$pm_id) $selected_project = 'general';
		}
		if (!$projects)	// show project-tree only if it's not empty
		{
			return null;
		}
		$tree = html::tree($projects,$selected_project,false,'load_project');
		// hack for stupid ie (cant set it as a class!)
		//if (html::$user_agent == 'msie') $tree = str_replace('id="foldertree"','id="foldertree" style="overflow: auto; width: 198px;"',$tree);
		// do it all the time, as we want distinct behavior here
		$tree = str_replace('id="foldertree"','id="foldertree" style="overflow: auto; max-width:400px; width:100%; max-height:450px;"',$tree);
		return array(
			'text' => "<script>function load_project(_nodeId) { egw_appWindow('projectmanager').location.href='$select_link'+_nodeId.substr(_nodeId.lastIndexOf('/')+1,99); }</script>\n".$tree,
			'no_lang' => True,
			'link' => False,
			'icon' => False,
		);
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
			'text' => html::select('pm_id',$pm_id,$projects,true,' style="width: 100%;"'.
				' onchange="egw_appWindow(\'projectmanager\').location.href=\''.$select_link.'\'+this.value;" title="'.html::htmlspecialchars(
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
		$settings['show_custom_app_icons'] = array(
			'type'   => 'check',
			'label'  => 'Show status icons of the datasources',
			'name'   => 'show_custom_app_icons',
			'help'   => 'Should Projectmanager display the status icons of the datasource (eg. InfoLog) or just a progressbar with the numerical status (faster).',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> true,
		);
		$settings['show_projectselection'] = array(
			'type'   => 'select',
			'label'  => 'Show the project selection as',
			'name'   => 'show_projectselection',
			'values' => array(
				'tree_with_number'      => lang('Tree with %1',lang('Project ID')),
				'tree_with_title'       => lang('Tree with %1',lang('Title')),
				'selectbox_with_number' => lang('Selectbox with %1',lang('Project ID').': '.lang('Title')),
				'selectbox_with_title'  => lang('Selectbox with %1',lang('Title').' ('.lang('Project ID').')'),
			),
			'help'   => 'How should the project selection in the menu be displayed: A tree gives a better overview, a selectbox might perform better.',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> 'tree_with_title',
		);
		
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$link = egw::link('/index.php','menuaction=projectmanager.projectmanager_merge.show_replacements');
			$settings['document_dir'] = array(
				'type'   => 'input',
				'size'   => 60,
				'label'  => 'Directory with documents to insert project data',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, projectmanager displays an action for each document.').' '.
					lang('That action allows to download the specified document with the project and elements data inserted.').' '.
					lang('The document can contain placeholders like %1 to be replaced with the project data (%2full list of placeholder names%3).','&#123;&#123;pm_title&#125;&#125;','<a href="'.$link.'" target="_blank">','</a>').' '.
					lang('Furthermore addressbook elements in the projectmanager elements list can be selected to define individual recipients of a serial letter.').' '.
					lang('At the moment the following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
		}
		
		return $settings;
	}
}

projectmanager_hooks::init_static();
