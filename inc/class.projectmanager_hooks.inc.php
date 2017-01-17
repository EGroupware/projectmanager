<?php
/**
 * ProjectManager - diverse hooks: Admin-, Preferences-, SideboxMenu-Hooks, ...
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
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
			'notify'     => 'projectmanager.projectmanager_elements_bo.notify',
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
					'view' => array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.edit',
					),
					'view_id' => 'pe_id',
					'view_popup' => '600x450',
					'edit' => array(
						'menuaction' => 'projectmanager.projectmanager_elements_ui.edit',
					),
					'edit_id' => 'pe_id',
					'edit_popup' => '600x450',
					'titles' => 'projectmanager.projectmanager_elements_bo.titles',
					'query' => 'projectmanager.projectmanager_elements_bo.link_query',
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
			$file = array(
				'Projectlist' => Egw::link('/index.php',array(
					'menuaction' => 'projectmanager.projectmanager_ui.index',
					'ajax' => 'true',
				))
			);
			if($GLOBALS['projectmanager_bo']->check_acl(Acl::READ))
			{
				$file += array(
					array(
						'text' => 'Elementlist',
						'link' =>  Egw::link('/index.php',array(
							'menuaction' => 'projectmanager.projectmanager_ui.index',
							'ajax' => 'true',
						)),
					),
					array(
						'text' => 'Ganttchart',
						'link' =>  Egw::link('/index.php',array(
							'menuaction' => 'projectmanager.projectmanager_ui.index',
							'ajax' => 'true',
						)),
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
					'link' =>  Egw::link('/index.php',array(
						'menuaction' => 'projectmanager.projectmanager_ui.index',
						'ajax' => 'true',
					))
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

			// Target for project tree
			$file[] = array(
				'no_lang' => true,
				'text'=>'<span id="projectmanager-tree_target" />',
				'link'=>false,
				'icon' => false
			);

			$file['Placeholders'] = Egw::link('/index.php','menuaction=projectmanager.projectmanager_merge.show_replacements');
			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);

			// allways show sidebox
			unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site configuration' => Egw::link('/index.php','menuaction=projectmanager.projectmanager_admin.config&ajax=true'),
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
			if ($GLOBALS['egw_info']['user']['preferences']['projectmanager']['show_projectselection']=='tree_with_number_title')
				{
					$projects[$project['path']] = array(
						'label' => $project[$title].': '.$project[$label],
						'title' => $project[$title].': '.$project[$label],
					);
				} else {
					$projects[$project['path']] = array(
						'label' => $project[$label],
						'title' => $project[$title],
					);
				}
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
		$tree = Api\Html::tree($projects,$selected_project,false,'load_project');
		// hack for stupid ie (cant set it as a class!)
		//if (Api\Header\UserAgent::type() == 'msie') $tree = str_replace('id="foldertree"','id="foldertree" style="overflow: auto; width: 198px;"',$tree);
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
			'title' => lang('Data exchange settings'),
			'no_lang'=> true,
			'xmlrpc' => False,
			'admin'  => False
		);
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$settings['document_dir'] = array(
				'type'   => 'vfs_dirs',
				'size'   => 60,
				'label'  => 'Directory with documents to insert project data',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the data inserted.',lang('projectmanager')).' '.
					lang('the document can contain placeholder like {{%1}}, to be replaced with the data.','pm_title').' '.
					lang('Furthermore addressbook elements in the projectmanager elements list can be selected to define individual recipients of a serial letter.').' '.
					lang('The following document-types are supported:'). implode(',',Api\Storage\Merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '/templates/projectmanager',
			);
			$settings['document_download_name'] = array(
			'type'   => 'select',
			'label'  => 'Document download filename',
			'name'   => 'document_download_name',
			'values' => array(
				'%document%'      						=> lang('Template name'),
				'%pm_title%'      						=> lang('Project title'),
				'%pm_title% - %document%'				=> lang('Project title - template name'),
				'%document% - %pm_title%'				=> lang('Template name - project title'),
				'%pm_number% - %document%'				=> lang('Project ID - template name'),
				'(%pm_number%) %pm_title% - %document%'	=> lang('(Project ID) project title - template name'),

			),
			'help'   => 'Choose the default filename for downloaded documents.',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '%document%',
		);
		}
		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'projectmanager'
			));
			$options = array(
				'~nextmatch~'	=>	lang('Old fixed definition')
			);
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try
				{
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e)
				{
					// permission error
					continue;
				}
				if ($title = $definition->get_title())
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$default_def = 'export-projectmanager';
			$settings['nextmatch-export-definition-project'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export' . ' (' . lang('Projects') . ')',
				'name'   => 'nextmatch-export-definition-project',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
			$default_def = 'export-projectmanager-elements';
			$settings['nextmatch-export-definition-element'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export' . ' (' . lang('Elements') . ')',
				'name'   => 'nextmatch-export-definition-element',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
		}

		return $settings;
	}

	/**
	 * ACL rights and labels used
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected Acl owner
	 */
	public static function acl_rights($params)
	{
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
