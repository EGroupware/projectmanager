<?php
/**
 * ProjectManager - General business object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('EGW_ACL_BUDGET',EGW_ACL_CUSTOM_1);
define('EGW_ACL_EDIT_BUDGET',EGW_ACL_CUSTOM_2);

/**
 * General business object of the projectmanager
 *
 * This class does all the timezone-conversation: All function expect user-time and convert them to server-time
 * before calling the storage object.
 */
class projectmanager_bo extends projectmanager_so
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
	 * Instance of the timesheet_tracking object
	 *
	 * @var timesheet_tracking
	 */
	var $historylog;
	/**
	 * Translates field / acl-names to labels
	 *
	 * @var array
	 */
	var $field2label = array(
		'pm_id'		         => 'Projectid',
		'pm_title'     	     => 'Title',
		'pm_number'    	     => 'Projectnumber',
		'pm_description'     => 'Description',
		'pm_creator'         => 'Owner',
		'pm_created'    	 => 'Created',
		'pm_modifier' 		 => 'Modifier',
		'pm_modified'    	 => 'Modified',
		'pm_planned_start'   => 'Planned start',
		'pm_planned_end'     => 'Planned end',
		'pm_real_start'      => 'Real start',
		'pm_real_end'        => 'Real end',
		'cat_id'             => 'Category',
		'pm_access'          => 'Access',
		'pm_priority'        => 'Priority',
		'pm_status'          => 'Status',
		'pm_completion'      => 'Completion',
		'pm_used_time'       => 'Used time',
		'pm_planned_time'    => 'Planned time',
		'pm_replanned_time'  => 'Replanned time',
		'pm_used_budget'     => 'Used budget',
		'pm_planned_budget'  => 'Planned budget',
		'pm_overwrite'       => 'Overwrite',
	    'pm_accounting_type' => 'Accounting type',
		// pseudo fields used in edit
		//'link_to'        => 'Attachments & Links',
		'customfields'   => 'Custom fields',
	);
	/**
	 * setting field-name from DB to history status
	 *
	 * @var array
	 */
	var $field2history = array();
	/**
	 * Names of all config vars
	 *
	 * @var array
	 */
	var $tracking;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id id of the project to load, default null
	 * @param string $instanciate='' comma-separated: constraints,milestones,roles
	 * @return projectmanager_bo
	 */
	function __construct($pm_id=null,$instanciate='')
	{
		if ((int) $this->debug >= 3 || $this->debug == 'projectmanager') $this->debug_message(function_backtrace()."\nprojectmanager_bo::projectmanager_bo($pm_id) started");

		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
		$this->now_su = time() + $this->tz_offset_s;

		parent::__construct($pm_id);

		// save us in $GLOBALS['boprojectselements'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['projectmanager_bo']))
		{
			$GLOBALS['projectmanager_bo'] =& $this;
		}
		// atm. projectmanager-admins are identical to eGW admins, this might change in the future
		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);

		if ($instanciate) $this->instanciate($instanciate);

		if ((int) $this->debug >= 3 || $this->debug == 'projectmanager') $this->debug_message("projectmanager_bo::projectmanager_bo($pm_id) finished");
		//set fields for tracking
		$this->field2history = array_keys($this->db_cols);
		$this->field2history = array_diff(array_combine($this->field2history,$this->field2history),
		array('pm_modified'));
		$this->field2history = $this->field2history +array('customfields'   => '#c');  //add custom fields for history
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
				$cname = 'projectmanager_'.$class.'_'.$pre;
				$this->$class = new $cname();
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

		return ExecMethod('projectmanager.projectmanager_elements_bo.summary',$pm_id);
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

			if (!$this->read(array('pm_id' => $pm_id))) return;	// project does (no longer) exist
		}
		$pe_summary = $this->pe_summary($pm_id);

		if ((int) $this->debug >= 2 || $this->debug == 'update') $this->debug_message("projectmanager_bo::update($pm_id,$update_necessary) pe_summary=".print_r($pe_summary,true));

		if (!$this->pe_name2id)
		{
			// we need the PM_ id's
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

			$ds = new datasource();
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
		if ((int) $this->debug >= 1 || $this->debug == 'save') $this->debug_message("projectmanager_bo::save(".print_r($keys,true).",".(int)$touch_modified.") data=".print_r($this->data,true));

		// check if we have a real modification
		// read the old record needed for history logging
		$new =& $this->data;
		unset($this->data);
		$this->read($new['pm_id']);
		$old =& $this->data;
		$this->data =& $new;
		if (!($err = parent::save()) && $do_notify)
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			egw_link::notify_update('projectmanager',$this->data['pm_id'],$this->data);
		}
		//$changed[] = array();
		if (isset($old)) foreach($old as $name => $value)
		{
			if (isset($new[$name]) && $new[$name] != $value)
			{
				$changed[$name] = $name;
				if ($name =='pm_completion' && $new['pm_completion'].'%' == $value) unset($changed[$name]);
				if ($name =='pm_modified') unset($changed[$name]);
				if ($name =='pm_members') unset($changed[$name]);
			}
		}
		if (!$changed && $old['pm_id']!='')
		{
			return false;
		}
		if (!is_object($this->tracking))
		{
			$this->tracking = new projectmanager_tracking($this);
			$this->tracking->html_content_allow = true;
		}
		if ($this->customfields)
		{
			$data_custom = $old_custom = array();
			foreach($this->customfields as $name => $custom)
			{
				if (isset($this->data['#'.$name]) && (string)$this->data['#'.$name]!=='') $data_custom[] = $custom['label'].': '.$this->data['#'.$name];
				if (isset($old['#'.$name]) && (string)$old['#'.$name]!=='') $old_custom[] = $custom['label'].': '.$old['#'.$name];
			}
			$this->data['customfields'] = implode("\n",$data_custom);
			$old['customfields'] = implode("\n",$old_custom);
		}
		if (!$this->tracking->track($this->data,$old,$this->user))
		{
			return implode(', ',$this->tracking->errors);
		}
		return $err;
	}

	/**
	 * deletes a project identified by $keys or the loaded one, reimplemented to remove the project-elements too
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @param boolean $delete_sources=false true=delete datasources of the elements too (if supported by the datasource), false dont do it
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null,$delete_sources=false)
	{
		if ((int) $this->debug >= 1 || $this->debug == 'delete') $this->debug_message("projectmanager_bo::delete(".print_r($keys,true).",$delete_sources) this->data[pm_id] = ".$this->data['pm_id']);

		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('pm_id' => (int) $keys);
		}
		$pm_id = is_null($keys) ? $this->data['pm_id'] : $keys['pm_id'];

		if (($ret = parent::delete($keys)) && $pm_id)
		{
			// delete the projectmembers
			parent::delete_members($pm_id);

			ExecMethod2('projectmanager.projectmanager_elements_bo.delete',array('pm_id' => $pm_id),$delete_sources);

			// the following is not really necessary, as it's already one in projectmanager_elements_bo::delete
			// delete all links to project $pm_id
			egw_link::unlink(0,'projectmanager',$pm_id);

			$this->instanciate('constraints,milestones,pricelist,roles');

			// delete all constraints of the project
			$this->constraints->delete(array('pm_id' => $pm_id));

			// delete all milestones of the project
			$this->milestones->delete(array('pm_id' => $pm_id));

			// delete all pricelist items of the project
			$this->pricelist->delete(array('pm_id' => $pm_id));

			// delete all project specific roles
			$this->roles->delete(array('pm_id' => $pm_id));
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
	 * generate a project-ID / generted by config format
	 *
	 * @param boolean $set_data=true set generated number in $this->data, default true
	 * @param string $parent='' pm_number of parent
	 * @return string the new pm_number
	 */
	function generate_pm_number($set_data=true,$parent='')
	{
		if(!$this->config['ID_GENERATION_FORMAT']) $this->config['ID_GENERATION_FORMAT'] = 'P-%Y-%04ix'; //this used to be the default
		if(!$this->config['ID_GENERATION_FORMAT_SUB']) $this->config['ID_GENERATION_FORMAT_SUB'] = '%px/%04ix';
		$format = $parent === '' ? $this->config['ID_GENERATION_FORMAT'] : $this->config['ID_GENERATION_FORMAT_SUB'];
		//echo "format: $format<br>";
		$pm_format = '';
		$index = false;
		for($i = 0;$i < strlen($format);$i++)
		{
			//echo "i:$i char=".$format[$i].'<br>';
			if($format[$i] == '%')
			{
				$filler = $format[++$i];
				$count = $format[++$i];
				if(is_numeric($count) && is_numeric($filler))
				{
					// all right ...
				}
				elseif(is_numeric($count) && is_string($filler))
				{
					// if filler is nonnummerical, that should work too as padding char
					// note thar char padding requires a preceding '
					$filler="'".$filler;
				}
				elseif(is_numeric($filler))
				{
					$count = $filler;	// only one part given (e.g. %4n), fill with '0'
					$filler = '0';
					$i--;
				}
				else
				{
					$filler = $count = '';	// no specialism
					$i -= 2;
				}

				$name = substr($format, $i + 1, 2);
				if($name == 'px' && $parent !== '')	// parent id
				{
					$pm_format .= $parent;
					$i += 2;
				}
				elseif($name == 'ix')	// index
				{
					if(!$index)	// insert only one index
					{
						$pm_format .= ($filler && $count ? "%{$filler}{$count}s" :
							($count ? "%0{$count}s" : "%s"));
						$index = true;
					}
					$i += 2;
				}
				else	// date
				{
					$date = '';
					//while(in_array($char = $format[++$i], array('d','D','j','l','N','S','w','z','W','F','m','M','n','t','L','o',
					//	'Y','y','a','A','B','g','G','h','H','i','s','u','e','I','O','P','T','Z','c','r','U')))
					//{
					//	$date .= $char;
					//}
					//echo " Char at Pos: ".++$i.":".$format[$x]."<br>";
					// loop through thevrest until we find the next % to indicate the next replacement
					for($x = ++$i;$x < strlen($format);$x++)
					{
						//echo "x: $x ($i) char here:".$format[$x]."<br>";
						if ($format[$x] == "%") 
						{
							break;
						}
						$date .= $format[$x];
						$i++;
					}
					//echo "Date format:".$date."Filler:$filler, Count:$count<br>";
					$pm_format .= sprintf($filler && $count ? "%{$filler}{$count}s" :
							($count ? "%0{$count}s" : "%s"), date($date));
					//echo "PM-Date format:".$pm_format."<br>";
					$i--;
				}
			}
			else	// normal character
			{
				$pm_format .= $format[$i];
			}
		}
		if(!$index && $this->not_unique(array('pm_number' => $pm_format)))	// no index given and not unique
		{
			// have to use default
			$pm_format = $parent === '' ? sprintf('P-%04Y-%04d', date('Y')) : $parent.'/%04d';
		}
		elseif(!$index)
		{
			$pm_number = $pm_format;
		}
		if(!isset($pm_number))
		{
			$n = 1;
			do
			{
				$pm_number = sprintf($pm_format, $n++);
			}
			while ($this->not_unique(array('pm_number' => $pm_number)));
		}

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
		if ((int) $this->debug >= 2 || $this->debug == 'check_acl') $this->debug_message("projectmanager_bo::check_acl($required,pm_id=$pm_id) rights[$pm_id]=".$rights[$pm_id]);

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
	 * @return string/boolean string with title, null if project not found or false if no perms to view it
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
	 * get titles for multiple project identified by $ids
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $entry int pm_id or array with project entry
	 * @return array or titles, see link_title
	 */
	function link_titles( array $ids )
	{
		$titles = array();
		if (($projects = $this->search(array('pm_id' => $ids),'pm_number,pm_title')))
		{
			foreach($projects as $project)
			{
				$titles[$project['pm_id']] = $this->link_title($project);
			}
		}
		// we assume all not returned projects are not readable by the user, as we notify egw_link about all deletes
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}

	/**
	 * query projectmanager for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array $options Array of options for the search
	 * @return array with pm_id - title pairs of the matching entries
	 */
	function link_query( $pattern, Array &$options = array() )
	{
		$limit = false;
		$need_count = false;
		if($options['start'] || $options['num_rows']) {
			$limit = array($options['start'], $options['num_rows']);
			$need_count = true;
		}
		$result = array();
		foreach((array) $this->search($pattern,false,'pm_number','','%',false,'OR',$limit,array('pm_status'=>'active'), true, $need_count) as $prj )
		{
			if ($prj['pm_id']) $result[$prj['pm_id']] = $this->link_title($prj);
		}
		$options['total'] = $need_count ? $this->total : count($result);
		return $result;
	}

	/**
	 * Check access to the projects file store
	 *
	 * We currently map file access rights:
	 *  - file read rights = project read rights
	 *  - file write or delete rights = project edit rights
	 *
	 * @ToDo Implement own acl rights for file access
	 * @param int $id pm_id of project
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path)
	{
		return $this->check_acl($check,$id);
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
			foreach(egw_link::get_links('projectmanager',$pm_id,'projectmanager') as $link_id => $data)
			{
				// we need to read the complete link, to know if the entry is a child (link_id1 == pm_id)
				$link = egw_link::get_link($link_id);
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
			foreach(egw_link::get_links('projectmanager',$pm_id,'projectmanager') as $link_id => $data)
			{
				// we need to read the complete link, to know if the entry is a child (link_id1 == pm_id)
				$link = egw_link::get_link($link_id);
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
		while (($children = $this->search($filter,$GLOBALS['projectmanager_bo']->table_name.'.pm_id AS pm_id,pm_number,pm_title,link_id1 AS pm_parent,pm_status',
			'pm_status,pm_number','','',false,$filter_op,false,array('subs_or_mains' => $parents))))
		{
			//echo $parents == 'mains' ? "Mains" : "Children of ".implode(',',$parents)."<br>"; #_debug_array($children);
			// sort the children behind the parents
			$parents = $both = array();
			foreach ($projects as $parent)
			{
				//echo "Parent:".$parent['path']."<br>";
				$arr = explode("/",$parent['path']);
				$search = array_pop($arr);
				if (count($arr) >= 1 && in_array($search,$arr))
				{
					echo "<div>".lang('ERROR: Rekursion found: Id %1 more than once in Projectpath, while building Projecttree:',$search).' '.$parent['path'].array2string($projects[$parent['path']])."</div>";
					error_log(lang('ERROR: Rekursion found: Id %1 more than once in Projectpath, while building Projecttree:',$search).' '.$parent['path']."\n".array2string($projects[$parent['path']]));
					break 2;
				}
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
				$prefs = new preferences($uid);
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
			$this->bocal = new calendar_bo();
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

		if ((int) $this->debug >= 3 || $this->debug == 'date_add') $this->debug_message("projectmanager_bo::date_add($start=".date('D Y-m-d H:i',$start).", $time=".($time/60.0)."h, $uid)=".date('D Y-m-d H:i',$end_s));

		return $end_s;
	}

	/**
	 * Copies a project
	 *
	 * @param int $source id of project to copy
	 * @param int $only_stage=0 0=both stages plus saving the project, 1=copy of the project, 2=copying the element tree
	 * @param string $parent_number='' number of the parent project, to create a sub-project-number
	 * @return int/boolean successful copy new pm_id or true if $only_stage==1, false otherwise (eg. permission denied)
	 */
	function copy($source,$only_stage=0,$parent_number='')
	{
		if ((int) $this->debug >= 1 || $this->debug == 'copy') $this->debug_message("projectmanager_bo::copy($source,$only_stage)");

		if ($only_stage == 2)
		{
			if (!(int)$this->data['pm_id']) return false;

			$data_backup = $this->data;
		}
		if (!$this->read((int) $source) || !$this->check_acl(EGW_ACL_READ))
		{
			if ((int) $this->debug >= 1 || $this->debug == 'copy') $this->debug_message("projectmanager_bo::copy($source,$only_stage) returning false (not found or no perms), data=".print_r($this->data,true));
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
		$this->instanciate('milestones,constraints');

		// copying the milestones
		$milestones = $this->milestones->copy((int)$source,$this->data['pm_id']);

		// copying the element tree
		include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.projectmanager_elements_bo.inc.php');
		$boelements = new projectmanager_elements_bo($this->data['pm_id']);

		if (($elements = $boelements->copytree((int) $source)))
		{
			// copying the constrains
			$this->constraints->copy((int)$source,$elements,$milestones,$boelements->pm_id);
		}
		return $boelements->pm_id;
	}
}
