<?php
/**
 * ProjectManager - General business object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005/6 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.soprojectmanager.inc.php');

define('EGW_ACL_BUDGET',EGW_ACL_CUSTOM_1);
define('EGW_ACL_EDIT_BUDGET',EGW_ACL_CUSTOM_2);

/**
 * General business object of the projectmanager
 *
 * This class does all the timezone-conversation: All function expect user-time and convert them to server-time
 * before calling the storage object.
 */
class boprojectmanager extends soprojectmanager 
{
	/**
	 * Debuglevel: 0 = no debug-messages, 1 = main, 2 = more, 3 = all, 4 = all incl. so_sql, or string with function-name to debug
	 * 
	 * @var int/string
	 */
	var $debug=false;
	/**
	 * File to log debug-messages, ''=echo them
	 * 
	 * @var string
	 */
	var $logfile='/tmp/pm_log';
	/**
	 * Instance of the link-class
	 * 
	 * @var bolink
	 */
	var $link;
	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 * 
	 * @var array
	 */
	var $timestamps = array(
		'pm_created','pm_modified','pm_planned_start','pm_planned_end','pm_real_start','pm_real_end',
	);
	/**
	 * Offset in secconds between user and server-time,	it need to be add to a server-time to get the user-time 
	 * or substracted from a user-time to get the server-time
	 * 
	 * @var int
	 */
	var $tz_offset_s;
	/**
	 * Current time as timestamp in user-time
	 * 
	 * @var int
	 */
	var $now_su;
	/**
	 * Instance of the soconstraints-class
	 * 
	 * @var soconstraints
	 */
	var $constraints;
	/**
	 * Instance of the somilestones-class
	 * 
	 * @var somilestones
	 */
	var $milestones;
	/**
	 * Instance of the soroles-class, not instanciated automatic!
	 * 
	 * @var soroles
	 */
	var $roles;
	/**
	 * Atm. projectmanager-admins are identical to eGW admins, this might change in the future
	 * 
	 * @var boolean
	 */
	var $is_admin;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id id of the project to load, default null
	 * @param string $instanciate='' comma-separated: constraints,milestones,roles
	 * @return boprojectmanager
	 */
	function boprojectmanager($pm_id=null,$instanciate='')
	{
		if ((int) $this->debug >= 3 || $this->debug == 'projectmanager') $this->debug_message(function_backtrace()."\nboprojectmanager::boprojectmanager($pm_id) started");

		if (!is_object($GLOBALS['egw']->datetime))
		{
			$GLOBALS['egw']->datetime =& CreateObject('phpgwapi.datetime');
		}
		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
		$this->now_su = time() + $this->tz_offset_s;
		
		$this->soprojectmanager($pm_id);
		
		// save us in $GLOBALS['boprojectselements'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			$GLOBALS['boprojectmanager'] =& $this;
		}
		// instanciation of link-class has to be after making us globaly availible, as it calls us to get the search_link
		if (!is_object($GLOBALS['egw']->link))
		{
			$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
		}
		$this->link =& $GLOBALS['egw']->link;
		$this->links_table = $this->link->link_table;
		
		// atm. projectmanager-admins are identical to eGW admins, this might change in the future
		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);

		if ($instanciate) $this->instanciate($instanciate);
		
		if ((int) $this->debug >= 3 || $this->debug == 'projectmanager') $this->debug_message("boprojectmanager::boprojectmanager($pm_id) finished");
	}
	
	/**
	 * Instanciates some classes which dont get instanciated by default
	 *
	 * @param string $instanciate comma-separated: constraints,milestones,roles
	 * @param string $pre='so' class prefix to use, default so
	 */
	function instanciate($instanciate,$pre='so')
	{
		foreach(explode(',',$instanciate) as $class)
		{
			if (!is_object($this->$class))
			{
				$this->$class =& CreateObject('projectmanager.'.$pre.$class);
			}
		}		
	}

	/**
	 * Summarize the information of all elements of a project: min(start-time), sum(time), avg(completion), ...
	 *
	 * This is implemented in the projectelements class, we call it via ExecMethod
	 *
	 * @param int/array $pm_id=null int project-id, array of project-id's or null to use $this->pm_id
	 * @return array/boolean with summary information (keys as for a single project-element), false on error
	 */
	function pe_summary($pm_id=null)
	{
		if (is_null($pm_id)) $pm_id = $this->data['pm_id'];
		
		if (!$pm_id) return array();
		
		return ExecMethod('projectmanager.boprojectelements.summary',$pm_id);
	}

	/**
	 * update a project after a change in one of it's project-elements
	 *
	 * If the data and the exact changes gets supplied (see params), 
	 * an whole update or even the update itself might be avoided.
	 * Not used at the moment!
	 *
	 * @param int $pm_id=null project-id or null to use $this->data['pm_id']
	 * @param int $update_necessary=-1 which fields need updating, or'ed PM_ constants from the datasource class
	 * @param array $data=null data of the project-element if availible
	 */
	function update($pm_id=null,$update_necessary=-1,$data=null)
	{
		if (!$pm_id)
		{
			$pm_id = $this->data['pm_id'];
		}
		elseif ($pm_id != $this->data['pm_id'])
		{
			// we need to restore it later
			$save_data = $this->data;

			$this->read(array('pm_id' => $pm_id));
		}
		$pe_summary = $this->pe_summary($pm_id);

		if ((int) $this->debug >= 2 || $this->debug == 'update') $this->debug_message("boprojectmanager::update($pm_id) pe_summary=".print_r($pe_summary,true));
		
		if (!$this->pe_name2id)
		{
			// we need the PM_ id's
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');
			
			$ds =& new datasource();
			$this->pe_name2id = $ds->name2id;
			unset($ds);
		}		
		$save_necessary = false;
		foreach($this->pe_name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);
			if (!($this->data['pm_overwrite'] & $id) && $this->data[$pm_name] != $pe_summary[$name])
			{
				$this->data[$pm_name] = $pe_summary[$name];
				$save_necessary = true;
			}
		}
		if ($save_necessary)
		{
			$this->save(null,false);	// dont touch modification date
		}
		// restore $this->data
		if (is_array($save_data) && $save_data['pm_id'])
		{
			$this->data = $save_data;
		}	
	}
	
	/**
	 * saves a project
	 *
	 * reimplemented to automatic create a project-ID / pm_number, if empty
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param boolean $touch_modified=true should modification date+user be set, default yes
	 * @param boolean $do_notify=true should link::notify be called, default yes
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true,$do_notify=true)
	{
		if ($keys) $this->data_merge($keys);

		// check if we have a project-ID and generate one if not
		if (empty($this->data['pm_number']))
		{
			$this->generate_pm_number();
		}
		// set creation and modification data
		if (!$this->data['pm_id'])
		{
			$this->data['pm_creator'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['pm_created'] = $this->now_su;
		}
		if ($touch_modified)
		{
			$this->data['pm_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['pm_modified'] = $this->now_su;
		}
		if ((int) $this->debug >= 1 || $this->debug == 'save') $this->debug_message("boprojectmanager::save(".print_r($keys,true).",".(int)$touch_modified.") data=".print_r($this->data,true));

		if (!($err = parent::save()) && $do_notify)
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			$this->link->notify_update('projectmanager',$this->data['pm_id'],$this->data);
		}
		return $err;
	}
	
	/**
	 * deletes a project identified by $keys or the loaded one, reimplemented to remove the project-elements too
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		if ((int) $this->debug >= 1 || $this->debug == 'delete') $this->debug_message("boprojectmanager::delete(".print_r($keys,true).") this->data[pm_id] = ".$this->data['pm_id']);

		$pm_id = is_null($keys) ? $this->data['pm_id'] : (is_array($keys) ? $keys['pm_id'] : $keys);
		
		if (($ret = parent::delete($keys)) && $pm_id)
		{
			ExecMethod('projectmanager.boprojectelements.delete',array('pm_id' => $pm_id));

			// the following is not really necessary, as it's already one in boprojectelements::delete
			// delete all links to project $pm_id
			$this->link->unlink(0,'projectmanager',$pm_id);

			$this->instanciate('constraints,milestones,pricelist');

			// delete all constraints of the project
			$this->constraints->delete(array('pm_id' => $pm_id));
	
			// delete all milestones of the project
			$this->milestones->delete(array('pm_id' => $pm_id));
			
			// delete all pricelist items of the project
			$this->pricelist->delete(array('pm_id' => $pm_id));
		}
		return $ret;
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (adding $this->tz_offset_s to get user-time)
	 * Please note, we do NOT call the method of the parent or so_sql !!!
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array with changed data
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]) && $data[$name]) $data[$name] += $this->tz_offset_s;
		}
		if (is_numeric($data['pm_completion'])) $data['pm_completion'] .= '%';

		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (subtraction $this->tz_offset_s to get server-time)
	 * Please note, we do NOT call the method of the parent or so_sql !!!
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array with changed data
	 */
	function data2db($data=null)
	{
		if ($intern = !is_array($data))
		{
			$data = &$this->data;
		}
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]) && $data[$name]) $data[$name] -= $this->tz_offset_s;
		}
		if (substr($data['pm_completion'],-1) == '%') $data['pm_completion'] = (int) round(substr($data['pm_completion'],0,-1));

		return $data;
	}
	
	/**
	 * generate a project-ID / pm_number in the form P-YYYY-nnnn (YYYY=year, nnnn=incrementing number)
	 *
	 * @param boolean $set_data=true set generated number in $this->data, default true
	 * @param string $parent='' pm_number of parent, if given a /nnnn is added
	 * @return string the new pm_number
	 */
	function generate_pm_number($set_data=true,$parent='')
	{
		$n = 1;
		do
		{
			if ($parent)
			{
				$pm_number = sprintf('%s/%04d',$parent,$n++);
			}
			else
			{
				$pm_number = sprintf('P-%04d-%04d',date('Y'),$n++);
			}
		}
		while ($this->not_unique(array('pm_number' => $pm_number)));
		
		if ($set_data) $this->data['pm_number'] = $pm_number;
		
		return $pm_number;
	}
	
	/**
	 * checks if the user has enough rights for a certain operation
	 *
	 * Rights are given via owner grants or role based acl
	 *
	 * @param int $required EGW_ACL_READ, EGW_ACL_WRITE, EGW_ACL_ADD, EGW_ACL_DELETE, EGW_ACL_BUDGET, EGW_ACL_EDIT_BUDGET
	 * @param array/int $data=null project or project-id to use, default the project in $this->data
	 * @param boolean $no_cache=false should a cached value be used, if availible, or not
	 * @return boolean true if the rights are ok, false if not or null if entry not found
	 */
	function check_acl($required,$data=0,$no_cache=false)
	{
		static $rights = array();
		$pm_id = (!$data ? $this->data['pm_id'] : (is_array($data) ? $data['pm_id'] : $data));
		
		if (!$pm_id)	// new entry, everything allowed, but delete
		{
			return $required != EGW_ACL_DELETE;
		}
		if (!isset($rights[$pm_id]) || $no_cache)	// check if we have a cache entry for $pm_id
		{
			if ($data)
			{
				if (!is_array($data))
				{
					$data_backup =& $this->data; unset($this->data);
					$data =& $this->read($data);
					$this->data =& $data_backup; unset($data_backup);
				
					if (!$data) return null;	// $pm_id not found ==> no rights
				}
			}
			else
			{
				$data =& $this->data;
			}
			// rights come from owner grants or role based acl
			$rights[$pm_id] = (int) $this->grants[$data['pm_creator']] | (int) $data['role_acl'];
			
			// for status or times accounting-type (no accounting) remove the budget-rights from everyone
			if ($data['pm_accounting_type'] == 'status' || $data['pm_accounting_type'] == 'times')
			{
				$rights[$pm_id] &= ~(EGW_ACL_BUDGET | EGW_ACL_EDIT_BUDGET);
			}
		}
		if ((int) $this->debug >= 2 || $this->debug == 'check_acl') $this->debug_message("boprojectmanager::check_acl($required,pm_id=$pm_id) rights[$pm_id]=".$rights[$pm_id]);

		if ($required == EGW_ACL_READ)	// read-rights are implied by all other rights
		{
			return (boolean) $rights[$pm_id];
		}
		if ($required == EGW_ACL_BUDGET) $required |= EGW_ACL_EDIT_BUDGET;	// EDIT_BUDGET implies BUDGET

		return (boolean) ($rights[$pm_id] & $required);
	}
	
	/**
	 * Read a project
	 * 
	 * reimplemented to add an acl check
	 *
	 * @param array $keys
	 * @return array/boolean array with project, null if project not found or false if no perms to view it
	 */
	function read($keys)
	{
		if (!parent::read($keys))
		{
			return null;
		}
		if (!$this->check_acl(EGW_ACL_READ))
		{
			return false;
		}
		return $this->data;
	}
	
	/**
	 * get title for an project identified by $entry
	 * 
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $entry int pm_id or array with project entry
	 * @param string/boolean string with title, null if project not found or false if no perms to view it
	 */
	function link_title( $entry )
	{
		if (!is_array($entry))
		{
			$entry = $this->read( $entry );
		}
		if (!$entry)
		{
			return $entry;
		}
		return $entry['pm_number'].': '.$entry['pm_title'];
	}

	/**
	 * query projectmanager for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @return array with pm_id - title pairs of the matching entries
	 */
	function link_query( $pattern )
	{
		$criteria = array();
		foreach(array('pm_number','pm_title','pm_description') as $col)
		{
			$criteria[$col] = $pattern;
		}
		$result = array();
		foreach((array) $this->search($criteria,false,'pm_number','','%',false,'OR',false,array('pm_status'=>'active')) as $prj )
		{
			if ($prj['pm_id']) $result[$prj['pm_id']] = $this->link_title($prj);
		}
		return $result;
	}
	
	/**
	 * Hook called by link-class to include projectmanager in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	function search_link($location)
	{
		return array(
			'query' => 'projectmanager.boprojectmanager.link_query',
			'title' => 'projectmanager.boprojectmanager.link_title',
			'view'  => array(
				'menuaction' => 'projectmanager.uiprojectelements.index',
			),
			'view_id' => 'pm_id',
			'notify' => 'projectmanager.boprojectelements.notify',
			'add' => array(
				'menuaction' => 'projectmanager.uiprojectmanager.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',		
		);
	}
	
	/**
	 * gets all ancestors of a given project (calls itself recursivly)
	 *
	 * A project P is the parent of an other project C, if link_id1=P.pm_id and link_id2=C.pm_id !
	 * To get all parents of a project C, we use all links to the project, which link_id2=C.pm_id.
	 *
	 * @param int $pm_id=0 id or 0 to use $this->pm_id
	 * @param array $ancestors=array() already identified ancestors, default none
	 * @return array with ancestors
	 */
	function &ancestors($pm_id=0,$ancestors=array())
	{
		static $ancestors_cache = array();	// some caching

		if (!$pm_id && !($pm_id = $this->pm_id)) return false;
		
		if (!isset($ancestors_cache[$pm_id]))
		{
			$ancestors_cache[$pm_id] = array();

			// read all projectmanager entries attached to this one
			foreach($this->link->get_links('projectmanager',$pm_id,'projectmanager') as $link_id => $data)
			{
				// we need to read the complete link, to know if the entry is a child (link_id1 == pm_id)
				$link = $this->link->get_link($link_id);
				if ($link['link_id1'] == $pm_id)
				{
					continue;	// we are the parent in this link ==> ignore it
				}
				$parent = (int) $link['link_id1'];
				if (!in_array($parent,$ancestors_cache[$pm_id]))
				{
					$ancestors_cache[$pm_id][] = $parent;
					// now we call ourself recursivly to get all parents of the parents
					$ancestors_cache[$pm_id] =& $this->ancestors($parent,$ancestors_cache[$pm_id]);
				}			
			}
		}
		//echo "<p>ancestors($pm_id)=".print_r($ancestors_cache[$pm_id],true)."</p>\n";
		return array_merge($ancestors,$ancestors_cache[$pm_id]);
	}
	
	/**
	 * gets recursive all children (only projects) of a given project (calls itself recursivly)
	 *
	 * A project P is the parent of an other project C, if link_id1=P.pm_id and link_id2=C.pm_id !
	 * To get all children of a project C, we use all links to the project, which link_id1=C.pm_id.
	 *
	 * @param int $pm_id=0 id or 0 to use $this->pm_id
	 * @param array $children=array() already identified ancestors, default none
	 * @return array with children
	 */
	function &children($pm_id=0,$children=array())
	{
		static $children_cache = array();	// some caching

		if (!$pm_id && !($pm_id = $this->pm_id)) return false;
		
		if (!isset($children_cache[$pm_id]))
		{
			$children_cache[$pm_id] = array();

			// read all projectmanager entries attached to this one
			foreach($this->link->get_links('projectmanager',$pm_id,'projectmanager') as $link_id => $data)
			{
				// we need to read the complete link, to know if the entry is a child (link_id1 == pm_id)
				$link = $this->link->get_link($link_id);
				if ($link['link_id1'] != $pm_id)
				{
					continue;	// we are NOT the parent in this link ==> ignore it
				}
				$child = (int) $link['link_id2'];
				if (!in_array($child,$children_cache[$pm_id]))
				{
					$children_cache[$pm_id][] = $child;
					// now we call ourself recursivly to get all parents of the parents
					$children_cache[$pm_id] =& $this->children($child,$children_cache[$pm_id]);
				}			
			}
		}
		//echo "<p>children($pm_id)=".print_r($children_cache[$pm_id],true)."</p>\n";
		return array_merge($children,$children_cache[$pm_id]);
	}
	
	/**
	 * Query the project-tree from the DB, project tree is indexed by a path consisting of pm_id's delimited by slashes (/)
	 *
	 * @param array $filter=array('pm_status' => 'active') filter for the search, default active projects
	 * @param string $filter_op='AND' AND or OR filters together, default AND
	 * @return array with path => array(pm_id,pm_number,pm_title,pm_parent) pairs
	 */
	function get_project_tree($filter = array('pm_status' => 'active'),$filter_op='AND')
	{
		$projects = array();
		$parents = 'mains';
		// get the children
		while (($children = $this->search($filter,$GLOBALS['boprojectmanager']->table_name.'.pm_id AS pm_id,pm_number,pm_title,link_id1 AS pm_parent',
			'pm_status,pm_number','','',false,$filter_op,false,array('subs_or_mains' => $parents))))
		{
			//echo $parents == 'mains' ? "Mains" : "Children of ".implode(',',$parents); _debug_array($children);
			
			// sort the children behind the parents
			$parents = $both = array();
			foreach ($projects as $parent)
			{
				$both[$parent['path']] = $parent;
				
				foreach($children as $key => $child)
				{
					if ($child['pm_parent'] == $parent['pm_id'])
					{
						$child['path'] = $parent['path'] . '/' . $child['pm_id'];
						$both[$child['path']] = $child;
						$parents[] = $child['pm_id'];
						unset($children[$key]);
					}
				}
			}
			// mains or orphans
			foreach ($children as $child)
			{
				$child['path'] = '/' . $child['pm_id'];
				$both[$child['path']] = $child;
				$parents[] = $child['pm_id'];
				
			}
			$projects = $both;
		}
		//echo "tree"; _debug_array($projects);
		return $projects;
	}

	/**
	 * write a debug-message to the log-file $this->logfile (if set)
	 *
	 * @param string $msg
	 */
	function log2file($msg)
	{
		if ($this->logfile && ($f = @fopen($this->logfile,'a+')))
		{
			fwrite($f,date('Y-m-d H:i:s: ').$GLOBALS['egw']->common->grab_owner_name($GLOBALS['egw_info']['user']['account_id'])."\n");
			fwrite($f,$msg."\n\n");
			fclose($f);
		}
	}
	
	/**
	 * EITHER echos a (preformatted / no-html) debug-message OR logs it to a file
	 *
	 * @param string $msg
	 */
	function debug_message($msg)
	{
		$msg = 'Backtrace: '.function_backtrace(2)."\n".$msg;

		if (!$this->logfile)
		{
			echo '<pre>'.$msg."</pre>\n";
		}
		else
		{
			$this->log2file($msg);
		}
	}

	/**
	 * Add a timespan to a given datetime, taking into account the availibility and worktimes of the user
	 *
	 * ToDo: take exclusivly blocked times (calendar) into account
	 *
	 * @param int $start start timestamp (usertime)
	 * @param int $time working time in minutes to add, 0 advances to the next working time
	 * @param int $uid user-id
	 * @return int/boolean end-time or false if it cant be calculated because user has no availibility or worktime
	 */
	function date_add($start,$time,$uid)
	{
		// we cache the user-prefs with the working times globaly, as they are expensive to read
		$user_prefs =& $GLOBALS['egw_info']['projectmanager']['user_prefs'][$uid];
		if (!is_array($user_prefs))
		{
			if ($uid == $GLOBALS['egw_info']['user']['account_id'])
			{
				$user_prefs = $GLOBALS['egw_info']['user']['preferences']['projectmanager'];
			}
			else
			{
				$prefs =& CreateObject('phpgwapi.preferences',$uid);
				$prefs->read_repository();
				$user_prefs =& $prefs->data['projectmanager'];
				unset($prefs);
			}
			// calculate total weekly worktime
			for($day=$user_prefs['duration']=0; $day <= 6; ++$day)
			{
				$user_prefs['duration'] += $user_prefs['duration_'.$day];
			}
		}
		$availibility = 1.0;
		if (isset($this->data['pm_members'][$uid]))
		{
			$availibility = $this->data['pm_members'][$uid]['member_availibility'] / 100.0;
		}
		$general = $this->get_availibility($uid);
		if (isset($general[$uid]))
		{
			$availibility *= $general[$uid] / 100.0;
		}
		// if user has no availibility or no working duration ==> fail
		if (!$availibility || !$user_prefs['duration'])
		{
			return false;
		}
		$time_s = $time * 60 / $availibility;
		
		if (!is_object($this->bocal))
		{
			$this->bocal =& CreateObject('calendar.bocal');
		}
		$events =& $this->bocal->search(array(
			'start' => $start,
			'end'   => $start+max(10*$time,30*24*60*60),
			'users' => $uid,
			'show_rejected' => false,
			'ignore_acl' => true,
		));
		if ($events) $event = array_shift($events);

		$end_s = $start;
		// we use do-while to allow with time=0 to advance to the next working time
		do {
			// ignore non-blocking events or events already over
			while ($event && ($event['non_blocking'] || $event['end'] <= $end_s))
			{
				//echo "<p>ignoring event $event[title]: ".date('Y-m-d H:i',$event['start'])."</p>\n";
				$event = array_shift($events);
			}
			$day = date('w',$end_s);	// 0=Sun, 1=Mon, ...
			$work_start_s = $user_prefs['start_'.$day] * 60;
			$max_add_s = 60 * $user_prefs['duration_'.$day];
			$time_of_day_s = $end_s - mktime(0,0,0,date('m',$end_s),date('d',$end_s),date('Y',$end_s));
			
			// befor workday starts ==> go to start of workday
			if ($max_add_s && $time_of_day_s < $work_start_s)
			{
				$end_s += $work_start_s - $time_of_day_s;
			}
			// after workday ends or non-working day ==> go to start of NEXT workday
			elseif (!$max_add_s || $time_of_day_s >= $work_start_s+$max_add_s)	// after workday ends
			{
				//echo date('D Y-m-d H:i',$end_s)." ==> go to next day: work_start_s=$work_start_s, time_of_day_s=$time_of_day_s, max_add_s=$max_add_s<br>\n";
				do {
					$day = ($day+1) % 7;
					$end_s = mktime($user_prefs['start_'.$day]/60,$user_prefs['start_'.$day]%60,0,date('m',$end_s),date('d',$end_s)+1,date('Y',$end_s));
				} while (!($max_add_s = 60 * $user_prefs['duration_'.$day]));
			}
			// in the working period ==> adjust max_add_s accordingly
			else
			{
				$max_add_s -= $time_of_day_s - $work_start_s;
			}
			$add_s = min($max_add_s,$time_s);
			
			//echo date('D Y-m-d H:i',$end_s)." + ".($add_s/60/60)."h / ".($time_s/60/60)."h<br>\n";
			
			if ($event)
			{
				//echo "<p>checking event $event[title] (".date('Y-m-d H:i',$event['start']).") against end_s=$end_s=".date('Y-m-d H:i',$end_s)." + add_s=$add_s</p>\n";
				if ($end_s+$add_s > $event['start'])	// event overlaps added period
				{
					$time_s -= max(0,$event['start'] - $end_s);	// add only time til events starts (if any)
					$end_s = $event['end'];				// set time for further calculation to event end
					//echo "<p>==> event overlaps: time_s=$time_s, end_s=$end_s now</p>\n";
					$event = array_shift($events);		// advance to next event
					continue;
				}
			}
			$end_s += $add_s;
			$time_s -= $add_s;
		} while ($time_s > 0);

		if ((int) $this->debug >= 3 || $this->debug == 'date_add') $this->debug_message("boprojectmanager::date_add($start=".date('D Y-m-d H:i',$start).", $time=".($time/60.0)."h, $uid)=".date('D Y-m-d H:i',$end_s));

		return $end_s;
	}
	
	/**
	 * Copies a project
	 *
	 * @param int $source id of project to copy
	 * @param int $only_stage=0 0=both stages plus saving the project, 1=copy of the project, 2=copying the element tree
	 * @param string $parent_number='' number of the parent project, to create a sub-project-number
	 * @return boolean true on successful copy, false otherwise (eg. permission denied)
	 */
	function copy($source,$only_stage=0,$parent_number='')
	{
		if ((int) $this->debug >= 1 || $this->debug == 'copy') $this->debug_message("boprojectmanager::copy($source,$only_stage)");

		if ($only_stage == 2)
		{
			if (!(int)$this->data['pm_id']) return false;

			$data_backup = $this->data;
		}
		if (!$this->read((int) $source) || !$this->check_acl(EGW_ACL_READ))
		{
			if ((int) $this->debug >= 1 || $this->debug == 'copy') $this->debug_message("boprojectmanager::copy($source,$only_stage) returning false (not found or no perms), data=".print_r($this->data,true));
			return false;
		}
		if ($only_stage == 2)
		{
			$this->data = $data_backup;
			unset($data_backup);
		}
		else
		{
			// if user has no budget rights on the source, we need to unset the budget fields
			if ($this->check_acl(EGW_ACL_BUDGET))
			{
				include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');
				foreach(array(PM_PLANNED_BUDGET => 'pm_planned_budget',PM_USED_BUDGET => 'pm_used_budget') as $id => $key)
				{
					unset($this->data[$key]);
					$this->data['pm_overwrite'] &= ~$id;
				}
			}
			// we unset a view things, as this should be a new project
			foreach(array('pm_id','pm_number','pm_creator','pm_created','pm_modified','pm_modifier') as $key)
			{
				unset($this->data[$key]);
			}
			$this->data['pm_status'] = 'active';

			if ($parent_number) $this->generate_pm_number(true,$parent_number);

			if ($only_stage == 1)
			{
				return true;
			}
			if ($this->save() != 0) return false;
		}
		// copying the element tree
		$elements =& CreateObject('projectmanager.boprojectelements',$this->data['pm_id']);
		
		return $elements->copytree((int) $source);
	}
}