<?php
/**************************************************************************\
* eGroupWare - ProjectManager - General business object                    *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.soprojectmanager.inc.php');

define('EGW_ACL_BUDGET',EGW_ACL_CUSTOM_1);
define('EGW_ACL_EDIT_BUDGET',EGW_ACL_CUSTOM_2);

/**
 * General business object of the projectmanager
 *
 * This class does all the timezone-conversation: All function expect user-time and convert them to server-time
 * before calling the storage object.
 *
 * @package projectmanager
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class boprojectmanager extends soprojectmanager 
{
	/**
	 * @var boolean $debug switch debug-messates on or off
	 */
	var $debug=false;
	/**
	 * @var string $logfile file to log debug-messages, ''=echo them
	 */
	var $logfile='/tmp/pm_log';
	/**
	 * @var bolink-object $link instance of the link-class
	 */
	var $link;
	/**
	 * @var array $timestamps timestaps that need to be adjusted to user-time on reading or saving
	 */
	var $timestamps = array(
		'pm_created','pm_modified','pm_planned_start','pm_planned_end','pm_real_start','pm_real_end',
	);
	/**
	 * @var int $tz_offset_s offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 */
	var $tz_offset_s;
	/**
	 * @var object $constraints soconstraints-object, not instanciated automatic!
	 */
	var $constraints;
	/**
	 * @var object $milestones somilestones-object, not instanciated automatic!
	 */
	var $milestones;
	/**
	 * @var object $roles instance of the soroles-class, not instanciated automatic!
	 */
	var $roles;
	/**
	 * @var boolean $is_admin atm. projectmanager-admins are identical to eGW admins, this might change in the future
	 */
	var $is_admin;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id id of the project to load, default null
	 * @param string $instanciate='' comma-separated: constraints,milestones,roles
	 */
	function boprojectmanager($pm_id=null,$instanciate='')
	{
		if ($this->debug) $this->debug_message(function_backtrace()."\nboprojectmanager::boprojectmanager($pm_id) started");
		$this->soprojectmanager($pm_id);
		
		if (!is_object($GLOBALS['egw']->datetime))
		{
			$GLOBALS['egw']->datetime =& CreateObject('phpgwapi.datetime');
		}
		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
		
		// save us in $GLOBALS['boprojectselements'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			$GLOBALS['boprojectmanager'] =& $this;
		}
		// instanciation of link-class has to be after making us globaly availible, as it calls us to get the search_link
		if (!is_object($GLOBALS['egw']->link))
		{
			$GLOBALS['egw']->link =& CreateObject('infolog.bolink');
		}
		$this->link =& $GLOBALS['egw']->link;
		
		// atm. projectmanager-admins are identical to eGW admins, this might change in the future
		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);

		if ($instanciate) $this->instanciate($instanciate);
		
		if ($this->debug) $this->debug_message("boprojectmanager::boprojectmanager($pm_id) finished");
	}
	
	/**
	 * Instanciates some classes which dont get instanciated by default
	 *
	 * @param string $instanciate comma-separated: constraints,milestones,roles
	 */
	function instanciate($instanciate)
	{
		foreach(explode(',',$instanciate) as $class)
		{
			if (!is_object($this->$class))
			{
				$this->$class =& CreateObject('projectmanager.so'.$class);
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

		if ($this->debug) $this->debug_message("boprojectmanager::update($pm_id) pe_summary=".print_r($pe_summary,true));
		
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
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true)
	{
		if ($keys) $this->data_merge($keys);

		// check if we have a project-ID and generate one if not
		if (empty($this->data['pm_number']))
		{
			$this->generate_pm_number();
		}
		if ($this->debug) $this->debug_message("boprojectmanager::save(".print_r($keys,true).",".(int)$touch_modified.") data=".print_r($this->data,true));

		if (!($err = parent::save(null,$touch_modified)))
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			$this->link->notify_update('projectmanager',$this->data['pm_id'],$this->data);
		}
	}
	
	/**
	 * deletes a project identified by $keys or the loaded one, reimplemented to remove the project-elements too
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		//echo "<p>boprojectmanager::delete(".print_r($keys,true).") this->data[pm_id] = ".$this->data['pm_id']."</p>\n";
		$pm_id = is_null($keys) ? $this->data['pm_id'] : (is_array($keys) ? $keys['pm_id'] : $keys);
		
		if (($ret = parent::delete($keys)) && $pm_id)
		{
			ExecMethod('projectmanager.boprojectelements.delete',array('pm_id' => $pm_id));

			// the following is not really necessary, as it's already one in boprojectelements::delete
			// delete all links to project $pm_id
			$this->link->unlink(0,'projectmanager',$pm_id);

			$this->instanciate('constraints,milestones');

			// delete all constraints of the project
			$this->constraints->delete(array('pm_id' => $pm_id));
	
			// delete all milestones of the project
			$this->milestones->delete(array('pm_id' => $pm_id));
		}
		return $ret;
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (adding $this->tz_adjust_s to get user-time)
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
			if (isset($data[$name]) && $data[$name]) $data[$name] += $this->tz_adjust_s;
		}
		if (is_numeric($data['pm_completion'])) $data['pm_completion'] .= '%';

		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (subtraction $this->tz_adjust_s to get server-time)
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
			if (isset($data[$name]) && $data[$name]) $data[$name] -= $this->tz_adjust_s;
		}
		if (substr($data['pm_completition'],-1) == '%') $data['pm_completition'] = (int) round(substr($data['pm_completition'],0,-1));

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
	 * @return boolean true if the rights are ok, false if not
	 */
	function check_acl($required,$data=0)
	{
		static $rights = array();
		$pm_id = (!$data ? $this->data['pm_id'] : (is_array($data) ? $data['pm_id'] : $data));
		
		if (!$pm_id)	// new entry, everything allowed, but delete
		{
			return $required != EGW_ACL_DELETE;
		}
		if (!isset($rights[$pm_id]))	// check if we have a cache entry for $pm_id
		{
			if ($data)
			{
				if (!is_array($data))
				{
					$data_backup =& $this->data; unset($this->data);
					$data =& $this->read($data);
					$this->data =& $data_backup; unset($data_backup);
				
					if (!$data) return false;	// $pm_id not found ==> no rights
				}
			}
			else
			{
				$data =& $this->data;
			}
			// rights come from owner grants or role based acl
			$rights[$pm_id] = (int) $this->grants[$data['pm_creator']] | (int) $data['role_acl'];
			
			// for status or times accounting-type (no accounting) remove the budget-rigts from everyone
			if ($data['pm_accounting_type'] == 'status' || $data['pm_accounting_type'] == 'times')
			{
				$rights[$pm_id] &= ~(EGW_ACL_BUDGET | EGW_ACL_EDIT_BUDGET);
			}
		}
		//echo "<p>boprojectmanager::check_acl($required,pm_id=$pm_id) rights[$pm_id]=".$rights[$pm_id]."</p>\n";

		if ($required == EGW_ACL_READ)	// read-rights are implied by all other rights
		{
			return (boolean) $rights[$pm_id];
		}
		if ($required == EGW_ACL_BUDGET) $required |= EGW_ACL_EDIT_BUDGET;	// EDIT_BUDGET implies BUDGET

		return (boolean) ($rights[$pm_id] & $required);
	}
	
	/**
	 * get title for an project identified by $entry
	 * 
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $entry int pm_id or array with project entry
	 * @param string the title
	 */
	function link_title( $entry )
	{
		if (!is_array($entry))
		{
			$entry = $this->read( $entry );
		}
		if (!$entry)
		{
			return False;
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
		foreach((array) $this->search($criteria,false,'','','%',false,'OR') as $prj )
		{
			$result[$prj['pm_id']] = $this->link_title($prj);
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
		if (!$pm_id && !($pm_id = $this->pm_id)) return false;
		
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
			if (!in_array($parent,$ancestors))
			{
				$ancestors[] = $parent;
				// now we call ourself recursivly to get all parents of the parents
				$ancestors =& $this->ancestors($parent,$ancestors);
			}			
		}
		return $ancestors;
	}

	/**
	 * write a debug-message to the log-file $this->logfile (if set)
	 *
	 * @param string $msg
	 */
	function log2file($msg)
	{
		if ($this->logfile && ($f = fopen($this->logfile,'a+')))
		{
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
		if (!$this->logfile)
		{
			echo '<pre>'.$msg."</pre>\n";
		}
		$this->log2file($msg);
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
			for($day=$user_prefs['duration']; $day <= 6; ++$day)
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
		
		$end_s = $start;
		// we use do-while to allow with time=0 to advance to the next working time
		do {
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
			
			$end_s += $add_s;
			$time_s -= $add_s;
		} while ($time_s > 0);

		//echo "<p>boprojectmanager::date_add($start=".date('D Y-m-d H:i',$start).", $time=".($time/60.0)."h, $uid)=".date('D Y-m-d H:i',$end_s)."</p>\n";
		return $end_s;
	}
}