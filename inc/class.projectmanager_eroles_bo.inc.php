<?php
/**
 * ProjectManager - eRoles business object
 *
 * @link http://www.egroupware.org
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2011 by Christian Binder <christian-AT-jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_eroles_bo.inc.php 26091 2008-10-07 17:57:50Z jaytraxx $
 */

/**
 * eRoles business object of the projectmanager
 * eRoles - element roles define the role of an egroupware element when it gets merged with a document
 *
 * Tables: egw_pm_eroles
 */
class projectmanager_eroles_bo extends projectmanager_eroles_so
{
	/**
	 * Id of current project
	 *
	 * @var int
	 */
	var $pm_id = null;
	
	/**
	 * Id of current project element
	 *
	 * @var int
	 */
	var $pe_id = null;
	
	/**
	 * Instance of the projectmanager_elements_so class
	 *
	 * @var projectmanager_elements_so
	 */
	var $projectmanager_elements_so;
	
	
	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id=null pm_id of the project to use
	 * @param int $pe_id=null pe_id of the current element
	 */
	function __construct($pm_id=null,$pe_id=null)
	{
		parent::__construct();
		
		// try to get pm_id from various sources
		if ((int) $pm_id)
		{
			$this->pm_id = (int) $pm_id;
		}
		elseif(isset($_REQUEST['pm_id']))
		{
			$this->pm_id = (int) $_REQUEST['pm_id'];
		}
		elseif((int)$GLOBALS['egw']->session->appsession('pm_id','projectmanager') > 0)
		{
			$this->pm_id = (int) $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
		}
		elseif(isset($_REQUEST['etemplate_exec_id']))
		{
			if($etemplate_request = etemplate_request::read($_REQUEST['etemplate_exec_id']))
			{
				$this->pm_id = $etemplate_request->content['pm_id'];
			}
		}
		
		// try to get pe_id from various sources
		if ((int) $pe_id)
		{
			$this->pe_id = (int) $pe_id;
		}
		elseif(isset($_REQUEST['pe_id']))
		{
			$this->pe_id = (int) $_REQUEST['pe_id'];
		}
		elseif(isset($_REQUEST['etemplate_exec_id']))
		{
			if(is_object($etemplate_request) || 
				($etemplate_request = etemplate_request::read($_REQUEST['etemplate_exec_id'])))
			{
				$this->pe_id = $etemplate_request->content['pe_id'];
			}
		}

		$this->projectmanager_elements_so = new projectmanager_elements_so($this->pm_id);
	}
	
	/**
	 * return an array of free element roles for the current project and element
	 * @return array
	 */
	public function get_free_eroles()
	{
		$free_eroles = array();
		if(!isset($this->pm_id)) return $free_eroles;
		
		if($project_eroles = parent::search(array('pm_id' => $this->pm_id),false,'role_title'))
		{
			$free_eroles = array_merge($free_eroles, $project_eroles);
		}
		if($global_eroles = parent::search(array('pm_id' => 0),false,'role_title','','',true))
		{
			$free_eroles = array_merge($free_eroles, $global_eroles);
		}
		
		// get all eroles used in other project elements
		$elements = $this->projectmanager_elements_so->search(array('pm_id' => $this->pm_id),false,'','','',false,'AND',false,null,false);
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
			if($erole['role_multi'] == true) continue; // ignore multi assignable eroles
			if(in_array($erole['role_id'],$used_eroles))
			{
				unset($free_eroles[$id]);
			}
		}
		
		return $free_eroles;
	}
	
	/**
	 * return an array of all items which are assigned to one specific erole
	 * @param int $erole_id of erole to search for
	 * @return array
	 */
	public function get_elements($erole_id)
	{
		$elements = array();
		if(!isset($this->pm_id)) return $elements;
		
		// get all elements of current project
		$project_elements = $this->projectmanager_elements_so->search(array('pm_id' => $this->pm_id),false,'','','',false,'AND',false,null,true);
		
		if(is_array($project_elements) && count($project_elements) > 0)
		{
			foreach($project_elements as $id => $element)
			{
				if(	strlen($element['pe_eroles']) > 0 
					&& ($element_eroles = explode(',',$element['pe_eroles']))
					&& in_array($erole_id, $element_eroles))
				{
					$elements[] = $element;
				}
			}
		}
		
		return $elements;
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
	
	/**
	 * returns an element role description by a given id
	 * if description is empty the title will be used instead
	 * 
	 * @param int $role_id
	 * @return string
	 */
	public function id2description($role_id)
	{
		$erole = parent::read($role_id);
		if(is_string($erole['role_description']) && strlen($erole['role_description']) > 0)
		{
			return $erole['role_description'];
		}
		else
		{
			return $erole['role_title'];
		}
	}
	
	/**
	 * returns a unique element role id by a given title
	 * project eroles have precedence before global eroles
	 * 
	 * @param int $role_title
	 * @return string
	 */
	public function title2id($role_title)
	{
		// search for project erole
		$erole = parent::read(array(
					'role_title' => $role_title,
					'pm_id' => $this->pm_id)
		);
		if(isset($erole['role_id'])) return $erole['role_id'];
		
		// search for global erole
		$erole = parent::read(array(
					'role_title' => $role_title,
					'pm_id' => 0)
		);
		if(isset($erole['role_id'])) return $erole['role_id'];
		
		return false;
	}
	
	/**
	 * creates a user readable string of the
	 * eroles global and multi assignable flags
	 * 
	 * @param int $role_id
	 * @return string
	 */
	public function get_info($role_id)
	{
		$erole = parent::read($role_id);
		$flags = array();
		
		if($erole['pm_id'] == 0)
		{
			$flags[] = lang('global');
		}
		if($erole['role_multi'] == true)
		{
			$flags[] = lang('multi assignments');
		}
		
		return !empty($flags) ? ' ('.ucfirst(implode(', ', $flags)).')' : '';
	}
	
}
