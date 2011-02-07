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
	 * Element roles - array with keys app, app_id and erole_id
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
			$this->pm_id = $pm_id;
			$this->projectmanager_eroles_bo = new projectmanager_eroles_bo($pm_id);
		}
		$this->projectmanager_bo = new projectmanager_bo($pm_id);		
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
		);
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
		
		// replace projectmanager content
		if (isset($this->pm_id) && $this->pm_id > 0)
		{
			$replacements += $this->projectmanager_replacements($this->pm_id);
		}
		
		// further replacements are made by eroles (if given)
		if(!empty($this->eroles) && is_array($this->eroles))
		{			
			foreach($this->eroles as $erole)
			{
				switch($erole['app']) {
					case 'addressbook':
						if($replacement = $this->contact_replacements($erole['app_id'],'erole/'.$this->projectmanager_eroles_bo->id2title($erole['erole_id'])))
						{
							$replacements += $replacement;
						}
						break;
					case 'infolog':
						if(!is_object($infolog_merge))
						{
							$infolog_merge = new infolog_merge();
						}
						if($replacement = $infolog_merge->infolog_replacements($erole['app_id'],'erole/'.$this->projectmanager_eroles_bo->id2title($erole['erole_id'])))
						{
							$replacements += $replacement;
						}
						break;
					default:
						// app not supported
						break;
				}
			}
			
		}
		
		return empty($replacements) ? false : $replacements;
	}
	
	/**
	 * Return replacements for a project
	 *
	 * @param int|array $project project-array or id
	 * @param string $prefix='' prefix like eg. 'user'
	 * @return array
	 */
	public function projectmanager_replacements($project,$prefix='')
	{
		if (!is_array($project))
		{
			$project = $this->projectmanager_bo->read($project);
		}
		if (!is_array($project)) return array();

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
			}
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		// TODO: set custom fields
		// ...
		
		return $replacements;
	}
	
	/**
	 * Set element roles for merging
	 *
	 * @param array $eroles element roles with keys app, app_id and erole_id
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
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Projectmanager').' - '.lang('Replacements for inserting project elements into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		
		$n = 0;
		echo '<tr><td colspan="4"><h3>'.lang('Projectmanager fields:')."</h3></td></tr>";
		foreach($this->projectmanager_fields as $name => $label)
		{
			if (!($n&1)) echo '<tr>';
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		
		
		if($_GET['serial_letter'] == 'true')
		{
			$n = 0;
			echo '<tr><td colspan="4"><h3>'.lang('Contact fields for serial letters:')."</h3></td></tr>";
			foreach($this->contacts->contact_fields as $name => $label)
			{
				if (in_array($name,array('tid','label','geo'))) continue;	// dont show them, as they are not used in the UI atm.

				if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)		// main values, which should be in the first column
				{
					echo "</tr>\n";
					$n++;
				}
				if (!($n&1)) echo '<tr>';
				echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
				if ($n&1) echo "</tr>\n";
				$n++;
			}
		}
		
		echo '<tr><td colspan="4"><h3>'.lang('Element role fields:')."</h3></td></tr>";
		foreach(array(
			'erole/{rolename}/{fieldname}' => lang('Element given by {rolename} will be replaced with supported fields of the element - e.g. if element is a contact, {fieldname}s like n_fn, n_family or n_given are available'),
			) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example $$IF n_prefix~Mr~Hello Mr.~Hello Ms.$$ - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example $$NELF role$$ - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example $$NELFNV role$$ - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example $$LETTERPREFIX$$ - Gives a letter prefix without double spaces, if the title is emty for  example'),
			'LETTERPREFIXCUSTOM' => lang('Example $$LETTERPREFIXCUSTOM n_prefix title n_family$$ - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";
		common::egw_footer();
	}
	
	/**
	 * Table plugin for eroles
	 *
	 * @param string $plugin
	 * @param int $erole_id
	 * @param int $n
	 * @param string $repeat the line to repeat
	 * @return array
	 */
	public function table_eroles($plugin,$id,$n,$repeat)
	{	
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
		if (isset($element))
		{
			switch($element['pe_app']) {
					case 'addressbook':
						if($replacement = $this->contact_replacements($element['pe_app_id'],'erole/'.$erole_title))
						{
							return $replacement;
						}
						break;
					case 'infolog':
						if(!is_object($infolog_merge))
						{
							$infolog_merge = new infolog_merge();
						}
						if($replacement = $infolog_merge->infolog_replacements($element['pe_app_id'],'erole/'.$erole_title))
						{
							return $replacement;
						}
						break;
					default:
						// app not supported
						break;
				}
		}
		
		return false;
	}
}
