<?php
/**
 * Projectmanager - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * Element roles - array with keys app, app_id and erole_id
	 *
	 * @var array
	 */
	var $eroles = null;

	/**
	 * Constructor
	 *
	 * @return projectmanager_merge
	 */
	function __construct()
	{
		parent::__construct();
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
		
		// TODO: replace projectmanager content
		//if (!(strpos($content,'$$projectmanager/') === false))
		//{
			//$replacements += $this->projectmanager_replacements();
		//}
		
		// further replacements are made by eroles (if given)
		if(!empty($this->eroles) && is_array($this->eroles))
		{			
			$projectmanager_eroles_so = new projectmanager_eroles_so();
			foreach($this->eroles as $erole)
			{
				switch($erole['app']) {
					case 'addressbook':
						if($replacement = $this->contact_replacements($erole['app_id'],'erole/'.$projectmanager_eroles_so->id2title($erole['erole_id'])))
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
		if($_GET['serial_letter'] == 'true')
		{
			echo '<tr><td colspan="4"><h3>'.lang('Contact fields for serial letters:')."</h3></td></tr>";

			$n = 0;
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
}
