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

/**
 * Projectmanager - document merge object
 */
class projectmanager_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('show_replacements' => true);
	
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
	 * @param int $pm_id=null id of current project
	 * @return projectmanager_merge
	 */
	function __construct($pm_id=null)
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
			'pm_id'					=> lang('Project ID'),
			'pm_number'				=> lang('Project number'),
			'pm_title'				=> lang('Title'),
			'pm_description'		=> lang('Description'),
			'pm_creator'			=> lang('Project creator'),
			'pm_created'			=> lang('Creation date and time'),
			'pm_modifier'			=> lang('Project modifier'),
			'pm_modified'			=> lang('Modified date and time'),
			'pm_planned_start'		=> lang('Planned start date and time'),
			'pm_planned_end'		=> lang('Planned end date and time'),
			'pm_real_start'			=> lang('Real start date and time'),
			'pm_real_end'			=> lang('Real end date and time'),
			'cat_id'				=> lang('Project category'),
			'pm_access'				=> lang('Project access (e.g. public)'),
			'pm_priority'			=> lang('Project priority'),
			'pm_status'				=> lang('Project status'),
			'pm_completion'			=> lang('Project completion (e.g. 100%)'),
			'pm_used_time'			=> lang('Used time'),
			'pm_planned_time'		=> lang('Planned time'),
			'pm_replanned_time'		=> lang('Re-planned time'),
			'pm_used_budget'		=> lang('Used budget'),
			'pm_planned_budget'		=> lang('Planned budget'),
			'pm_accounting_type'	=> lang('Accounting type'),
			'user_timezone_read'	=> lang('Timezone'),
			
			'all_roles'		=> lang('All roles'),
		);
		$role_so = new projectmanager_roles_so();
		$roles = $role_so->query_list();
		$this->projectmanager_fields += array_combine($roles, $roles);


		$this->pm_fields_translate = array(
			'cat_id'				=> 'pm_cat_id',
			'user_timezone_read'	=> 'pm_user_timezone',
		);
		
		$this->projectmanager_element_fields = array(
			'pe_id'					=> lang('Element ID'),
			'pe_title'				=> lang('Element title'),
			'pe_details'			=> lang('Element details'),
			'pe_completion'			=> lang('Completion'),
			'pe_used_time'			=> lang('Used time in minutes'),
			'pe_planned_time'		=> lang('Planned time in minutes'),
			'pe_replanned_time'		=> lang('Replanned time in minutes'),
			'pe_share'				=> lang('Shared time in minutes'),
			'pe_planned_quantity'	=> lang('Planned quantitiy'),
			'pe_used_quantity'		=> lang('Used quantity'),
			'pe_unitprice'			=> lang('Price per unit'),
			'pe_planned_budget'		=> lang('Planned budget'),
			'pe_used_budget'		=> lang('Used budget'),
			'pe_planned_start'		=> lang('Planned start date and time'),
			'pe_real_start'			=> lang('Real start date and time'),
			'pe_planned_end'		=> lang('Planned end date and time'),
			'pe_real_end'			=> lang('Real end date and time'),
			'pe_synced'				=> lang('Last sync date and time'),
			'pe_modified'			=> lang('Modified date and time'),
			'pe_modifier'			=> lang('Element modifier'),
			'pe_status'				=> lang('Element status'),
			'cat_id'				=> lang('Element category'),
			'pe_remark'				=> lang('Remark'),
			'user_timezone_read'	=> lang('Timezone'),
			'pe_app'		=> lang('Application'),
			'pe_resources'		=> lang('Resources')
		);
		$this->pe_fields_translate = array(
			'cat_id'				=> 'pe_cat_id',
			'user_timezone_read'	=> 'pe_user_timezone',
		);

		// Add in element summary
		$this->projectmanager_elements_bo = new projectmanager_elements_bo($pm_id);
		$summary = $this->projectmanager_elements_bo->summary();
		foreach($summary as $key => $value) {
			$this->projectmanager_fields[$key.'_total'] = $this->projectmanager_element_fields[$key] ? $this->projectmanager_element_fields[$key] : $key . ' ' . lang('Total');
		}

	}

	/**
	 * Change the currently merging project
	 *
	 * @param int $id of the project
	 */
	protected function change_project($id) {
		$this->pm_id = $id;
		if($id) {
			$this->projectmanager_eroles_bo = new projectmanager_eroles_bo($id);
			$this->projectmanager_elements_bo = new projectmanager_elements_bo($id);	
		}
		$this->projectmanager_bo = new projectmanager_bo($id);
	}

	/**
	 * Get projectmanager replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are in use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		$replacements = array();
		
		// first replacement is always the contact defined by $id (if valid)
		if($id > 0) {
			$replacements += $this->contact_replacements($id);
		}
		
		// replace project content
		if (isset($this->pm_id) && $this->pm_id > 0)
		{
			$replacements += $this->projectmanager_replacements($this->pm_id);
		} else {
			$this->change_project($id);
			$replacements += $this->projectmanager_replacements($this->pm_id);
		}
		
		// further replacements are made by eroles (if given)
		if(!empty($this->eroles) && is_array($this->eroles))
		{			
			foreach($this->eroles as $erole)
			{
				$erole_title = $this->projectmanager_eroles_bo->id2title($erole['erole_id']);
				if(!empty($erole_title) && ($replacement = $this->get_element_replacements(
									$erole['pe_id'],
									'erole/'.$erole_title,
									$erole['app'],
									$erole['app_id'])))
				{
					$replacements += $replacement;
				}
			}
		}
		return empty($replacements) ? false : $replacements;
	}
	
	/**
	 * Get element replacements
	 *
	 * @param int $pe_id element id
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @param string $app=null element app name (no app detail will be resolved if omitted)
	 * @param string $app_id=null element app_id (no app detail will be resolved if omitted)
	 * @return array|boolean
	 */
	protected function get_element_replacements($pe_id,$prefix='',$app=null,$app_id=null)
	{
		$replacements = array();
		// resolve project element fields
		if($replacement = $this->projectmanager_element_replacements($pe_id,$prefix))
		{
			$replacements += $replacement;
		}
		// resolve app fields of project element
		if($app && $app_id)
		{
			switch($app) {
				case 'addressbook':
					if($replacement = $this->contact_replacements($app_id,$prefix))
					{
						$replacements += $replacement;
					}
					break;
				case 'calendar':
					if(!is_object($calendar_merge))
					{
						$calendar_merge = new calendar_merge();
					}
					if($replacement = $calendar_merge->calendar_replacements($app_id,$prefix))
					{
						$replacements += $replacement;
					}
					break;
				case 'infolog':
					if(!is_object($infolog_merge))
					{
						$infolog_merge = new infolog_merge();
					}
					if($replacement = $infolog_merge->infolog_replacements($app_id,$prefix))
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
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array
	 */
	public function projectmanager_replacements($project,$prefix='')
	{
		if (!is_array($project))
		{
			$project = $this->projectmanager_bo->read($project);
		}
		if (!is_array($project)) return array();

		foreach($project['pm_members'] as $account_id => $info) {
			$all_roles[$info['role_title']][] = common::grab_owner_name($info['member_uid']);
		} 
		foreach($all_roles as $name => $users) {
			$project[$name] = implode(', ', $users);
			$project['all_roles'][] = $name . ': ' . $project[$name];
		}
		$project['all_roles'] = implode("\n",$project['all_roles']);

		$summary = $this->projectmanager_elements_bo->summary();
		foreach($summary as $key => $value) {
			$project[$key.'_total'] = $value;
		}

		$replacements = array();
		foreach(array_keys($project) as $name)
		{
			if(!isset($this->projectmanager_fields[$name])) continue; // not a supported field
			
			$value = $project[$name];
			switch($name)
			{
				case 'pm_created': case 'pm_modified':
				case 'pm_planned_start': case 'pm_planned_end':
				case 'pm_real_start': case 'pm_real_end':
				case 'pe_planned_start_total': case 'pe_planned_end_total':
				case 'pe_real_start_total': case 'pe_real_end_total':
					$value = $this->format_datetime($value);
					break;
				case 'pm_creator': case 'pm_modifier':
					$value = common::grab_owner_name($value);
					break;
				case 'cat_id':
					if ($value)
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$use = $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree' ? 'path' : 'name';
						$cats = array();
						foreach(is_array($value) ? $value : explode(',',$value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id,$use);
						}
						$value = implode(', ',$cats);
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
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		// TODO: set custom fields
		// ...
		
		return $replacements;
	}
	
	/**
	 * Return replacements for a given project element
	 *
	 * @param int $pe_id project element id
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array
	 */
	public function projectmanager_element_replacements($pe_id,$prefix='')
	{	
		$replacements = array();
		if(!is_object($this->projectmanager_elements_bo)) return $replacements;
		
		$element = $this->projectmanager_elements_bo->read(array('pe_id' => $pe_id));
		foreach(array_keys($element) as $name)
		{
			if(!isset($this->projectmanager_element_fields[$name])) continue; // not a supported field
			
			$value = !is_array($element[$name]) ? strip_tags($element[$name]) : $element[$name];
			switch($name)
			{
				case 'pe_planned_start': case 'pe_planned_end':
				case 'pe_real_start': case 'pe_real_end':
				case 'pe_synced': case 'pe_modified':
					$value = $this->format_datetime($value);
					break;
				case 'pe_modifier':
					$value = common::grab_owner_name($value);
					break;
				case 'cat_id':
					if ($value)
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$use = $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree' ? 'path' : 'name';
						$cats = array();
						foreach(is_array($value) ? $value : explode(',',$value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id,$use);
						}
						$value = implode(', ',$cats);
					}
					break;
				case 'pe_resources':
					foreach($value as $id => $user_id)
					{
						$names[] = common::grab_owner_name($user_id);
					}
					$value = implode(', ', $names);
					break;
			}
			if(isset($this->pe_fields_translate[$name]))
			{
				$name = $this->pe_fields_translate[$name];
			}
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
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
		if(empty($eroles)) return false;
		
		$this->eroles = $eroles;
		return true;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Projectmanager').' - '.lang('Replacements for inserting project data into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		
		// Projectmanager
		$n = 0;
		echo '<tr><td colspan="4"><h3><a name="pm_fields">'.lang('Projectmanager fields:')."</a></h3></td></tr>";
		foreach($this->projectmanager_fields as $name => $label)
		{
			if(isset($this->pm_fields_translate[$name]))
			{
				$name = $this->pm_fields_translate[$name];
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		// Elements
		$n = 0;
		echo '<tr><td colspan="4">'
				.'<h3><a name="pe_fields">'.lang('Projectmanager element fields:').'</a></h3>'
				.'<p>'.lang('can be used with element roles, "eroles" table plugin and "elements" table plugin').'</p>'
				.'</td></tr>';
		foreach($this->projectmanager_element_fields as $name => $label)
		{
			if(isset($this->pe_fields_translate[$name]))
			{
				$name = $this->pe_fields_translate[$name];
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		
		// Element roles
		if(!($this->projectmanager_bo->config['enable_eroles']))
		{
			$eroles_enable_hint = '<p style="font-weight: bold;">('.lang('Element roles feature is currently not enabled in your global projectmanager configuration').')</p>';
		}
		echo '<tr><td colspan="4">'
				.'<h3>'.lang('Element roles:').'</h3>'
				.$eroles_enable_hint
				.'<p>'.lang('Elements given by {rolename} will be replaced with the element fields;'
				.' additionally all fields of the elements application are available if the application is supported.').'</p>'
				.'</td></tr>';
		echo '<tr><td colspan="4">'.lang('Usage').': {{erole/{rolename}/{fieldname}}}</td></tr>';
		echo '<tr>';
		echo '<td colspan="2">'
				.'<h4>'.lang('Fields for element roles:').'</h4>'
				.'<ul>'
				.'<li><a href="#pe_fields">'.lang('Projectmanager element fields').'</a></li>';
				foreach(array(
					'Addressbook fields' 	=> egw::link('/index.php','menuaction=addressbook.addressbook_merge.show_replacements'),
					'Calendar fields'		=> egw::link('/index.php','menuaction=calendar.calendar_merge.show_replacements'),
					'Infolog fields'		=> egw::link('/index.php','menuaction=infolog.infolog_merge.show_replacements'),
				) as $placeholder => $link)
				{
					echo '<li><a href="'.$link.'" target="_blank">'.lang($placeholder).'</a></li>';
				}
				echo '</ul>';
				echo '</td>';
		echo '<td colspan="2">'
				.'<h4>'.lang('Examples:').'</h4>'
				.'{{erole/myrole/pe_title}}<br />{{erole/myrole/n_fn}}<br />{{erole/myrole/info_subject}}'
				.'</td>';
		echo "</tr>\n";
		
		// Table plugins
		echo '<tr><td colspan="4">'
				.'<h3>'.lang('Table plugins:').'</h3>'
				.'</td></tr>'."\n";
		echo '<tr><td colspan="4">'
				.'<h4>'.lang('Elements').'</h4>'
				.lang('Lists all project elements in a table.')
				.'</td></tr>'."\n";
		echo '<tr><td colspan="4">'.lang('Usage').': {{table/elements}} ... {{endtable}}</td></tr>';
		echo '<tr>'
				.'<td colspan="2">'
				.lang('Available fields for this plugin:')
				.'<ul>'
				.'<li><a href="#pe_fields">'.lang('Projectmanager element fields').'</a></li>'
				.'</ul>'
				.'</td>'
				.'<td colspan="2">'
				.'<h4>'.lang('Example:').'</h4>'
				.'<table border="1"><tr><td>Title</td><td>Details {{table/elements}}</td></tr>'
				.'<tr><td>{{element/pe_title}}</td><td>{{element/pe_details}} {{endtable}}</td></tr></table>'
				.'</td>'
				.'</tr>'."\n";
		echo '<tr><td colspan="4">'
				.'<h4>'.lang('Element roles').'</h4>'
				.$eroles_enable_hint
				.lang('Lists all elements assigned to an element role in a table.').' '
				.lang('Element roles defined as "mutliple" can be used here.')
				.'</td></tr>'."\n";
		echo '<tr><td colspan="4">'.lang('Usage').': {{table/eroles}} ... {{endtable}}</td></tr>';
		echo '<tr>'
				.'<td colspan="2">'
				.lang('Available fields for this plugin:')
				.'<ul>'
				.'<li><a href="#pe_fields">'.lang('Projectmanager element fields').'</a></li>';
				foreach(array(
					'Addressbook fields' 	=> egw::link('/index.php','menuaction=addressbook.addressbook_merge.show_replacements'),
					'Calendar fields'		=> egw::link('/index.php','menuaction=calendar.calendar_merge.show_replacements'),
					'Infolog fields'		=> egw::link('/index.php','menuaction=infolog.infolog_merge.show_replacements'),
				) as $placeholder => $link)
				{
					echo '<li><a href="'.$link.'" target="_blank">'.lang($placeholder).'</a></li>';
				}
		echo '</ul></td>'."\n";
		echo '<td colspan="2">'
				.'<h4>'.lang('Example:').'</h4>'
				.'<table border="1"><tr><td>Title</td><td>Infolog subject {{table/eroles}}</td></tr>'
				.'<tr><td>{{erole/myrole/pe_title}}</td><td>{{erole/myrole/info_subject}} {{endtable}}</td></tr></table>'
				.'</td>'
				.'</tr>'."\n";
		
		// Serial letter
		$link = egw::link('/index.php','menuaction=addressbook.addressbook_merge.show_replacements');
		echo '<tr><td colspan="4">'
				.'<h3>'.lang('Contact fields for serial letters').'</h3>'
				.lang('Addressbook elements of a project can be used to define individual serial letter recipients. Available fields are').':'
				.'<ul>'
				.'<li><a href="'.$link.'" target="_blank">'.lang('Addressbook fields').'</a></li>'
				.'</ul>'
				.'</td></tr>';

		// General
		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is emty for  example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";
		common::egw_footer();
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
	public function table_elements($plugin,$id,$n,$repeat)
	{
		if(!isset($this->pm_id) && !$id) return false;
		
		static $elements;
		if($id && $id != $this->pm_id) { 
			$this->change_project($id);
			$elements = array();
		}
		if (!$n)	// first row inits environment
		{
			// get project elements
			if(!($elements = $this->projectmanager_elements_bo->search(array('pm_id' => $this->pm_id),false)))
			{
				return false;
			}
		}
		
		$element =& $elements[$n];
		$replacement = false;
		if(isset($element))
		{
			$replacement = $this->get_element_replacements($element['pe_id'],'element');
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
	public function table_eroles($plugin,$id,$n,$repeat)
	{	
		if(!($this->projectmanager_bo->config['enable_eroles'])) return false; // eroles are disabled
		
		static $erole_id;
		static $erole_title;
		static $elements;
		
		if (!$n)	// first row inits environment
		{
			// get erole_id from repeated line
			preg_match_all('/\\$\\$erole\\/([A-Za-z0-9_]+)\\//s',$repeat,$matches);
			
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
								'erole/'.$erole_title,
								$element['pe_app'],
								$element['pe_app_id']);
		}

		return $replacement;
	}	
}
