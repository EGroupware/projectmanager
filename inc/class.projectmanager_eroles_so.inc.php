<?php
/**
 * ProjectManager - eRoles storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-11 by Christian Binder <christian-AT-jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_eroles_so.inc.php 26091 2008-10-07 17:57:50Z jaytraxx $
 */

/**
 * eRoles storage object of the projectmanager
 * eRoles - element roles define the role of an egroupware element when it gets merged with a document
 *
 * Tables: egw_pm_eroles
 */
class projectmanager_eroles_so extends so_sql
{
	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id pm_id of the project to use, default null
	 * @param int $pe_id pe_id of the current element, default null
	 */
	function __construct($pm_id=null,$pe_id=null)
	{
		parent::__construct('projectmanager','egw_pm_eroles');

		if ((int) $pm_id)
		{
			$this->pm_id = (int) $pm_id;
		}
		elseif(isset($_REQUEST['pm_id']))
		{
			$this->pm_id = (int) $_REQUEST['pm_id'];
		}
		
		if ((int) $pe_id)
		{
			$this->pe_id = (int) $pe_id;
		}
		elseif(isset($_REQUEST['pe_id']))
		{
			$this->pe_id = (int) $_REQUEST['pe_id'];
		}

	}

	/**
	 * reimplemented to set some defaults and order by 'pm_id DESC'
	 *
	 * @param string $value_col column-name for the values of the array, can also be an expression aliased with AS
	 * @param string $key_col='' column-name for the keys, default '' = same as $value_col: returns a distinct list
	 * @param array $filter=array() to filter the entries
	 * @param string $order='' order, default '' = same as $value_col
	 * @return array with key_col => value_col pairs
	 */
	function query_list($value_col='role_title',$key_col='role_id',$filter=array(),$order='pm_id DESC')
	{
		return parent::query_list($value_col,$key_col,$filter,$order);
	}
	
	/**
	 * return an array of free element roles for the current project and element
	 * @return array
	 */
	public function get_free_eroles()
	{
		$free_eroles = array();
		if($project_eroles = parent::search(array('pm_id' => $this->pm_id),false,'role_title'))
		{
			$free_eroles = array_merge($free_eroles, $project_eroles);
		}
		if($global_eroles = parent::search(array('pm_id' => 0),false,'role_title','','',true))
		{
			$free_eroles = array_merge($free_eroles, $global_eroles);
		}
		
		// get all eroles used in other project elements
		$projectmanager_elements_so = new projectmanager_elements_so($this->pm_id);
		$elements = $projectmanager_elements_so->search(array('pm_id' => $this->pm_id),false,'','','',false,'AND',false,null,false);
		$used_eroles = array();
		if(is_array($elements) && count($elements) > 0)
		{
			foreach($elements as $id => $element)
			{
				if(isset($this->pe_id) && $this->pe_id == $element['pe_id']) continue; // ignore eroles of current element
				if(strlen($element['pe_eroles']) > 0)
				{
					$used_eroles = array_merge($used_eroles,explode(',',$element['pe_eroles']));
				}
			}
		}
		
		// remove used eroles from array 
		foreach($free_eroles as $id => $erole)
		{
			if(in_array($erole['role_id'],$used_eroles))
			{
				unset($free_eroles[$id]);
			}
		}
		
		return $free_eroles;
	}
	
	/**
	 * returns an element role title by a given id
	 * 
	 * @param int $role_id
	 * @return string
	 */
	public function id2title($role_id)
	{
		$erole = parent::read($role_id);
		return $erole['role_title'];
	}
}