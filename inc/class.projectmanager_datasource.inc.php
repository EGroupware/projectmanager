<?php
/**
 * ProjectManager - DataSource for ProjectManger (Subprojects)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for ProjectManager itself
 */
class projectmanager_datasource extends datasource
{
	/**
	 * @var int/string $debug 0 = no debug-messages, 1 = main, 2 = more, 3 = all, or string function-name to debug
	 */
	var $debug=false;
	/**
	 * @var array $get_cache return value or the last call to the get method, used by the re-implemented read method
	 */
	var $get_cache=null;
	/**
	 * Reference to $GLOBALS['projectmanager_bo']
	 *
	 * @var projectmanager_bo
	 */
	var $projectmanager_bo;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct('projectmanager');

		$this->valid = PM_ALL_DATA;

		// we use $GLOBALS['projectmanager_bo'] as an already running instance may be availible there
		if (!is_object($GLOBALS['projectmanager_bo']))
		{
			$GLOBALS['projectmanager_bo'] = new projectmanager_bo();
		}
		$this->projectmanager_bo =& $GLOBALS['projectmanager_bo'];
	}

	/**
	 * read an item from a datasource (via the get methode) and try to set (guess) some not supported values
	 *
	 * Reimplemented from the datasource parent to set the start-date of the project itself and call it's sync-all
	 * method if necessary to move it's elements
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @param array $pe_data data of the project-element or null, eg. to use the constraints
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function read($data_id,$pe_data=null)
	{
		$ds = parent::read($data_id,$pe_data);	// calls $this->get($data_id) to fetch the data

		if ((int) $this->debug > 1 || $this->debug == 'read')
		{
			$this->projectmanager_bo->debug_message("projectmanager_datasource::read(pm_id=$data_id,".print_r($pe_data,true).')='.print_r($ds,true));
		}
		// check if datasource::read changed our planned start, because it's determined by the constrains or parent
		if (!is_null($pe_data) && $pe_data['pe_id'] && $ds['pe_planned_start'] != $pe_data['pe_planned_start'])
		{
			$pm_id = is_array($data_id) ? $data_id['pm_id'] : $data_id;

			if ((int) $this->debug > 2 || $this->debug == 'read')
			{
				$this->projectmanager_bo->debug_message("projectmanager_datasource::read(pm_id=$pm_id: $ds[pe_title]) planned start changed from $pe_data[pe_planned_start]=".date('Y-m-d H:i',$pe_data['pe_planned_start'])." to $ds[pe_planned_start]=".date('Y-m-d H:i',$ds['pe_planned_start'])/*.", pe_data=".print_r($pe_data,true)*/);
			}
			$bope = new projectmanager_elements_bo($pm_id);

			if (!($bope->project->data['pm_overwrite'] & PM_PLANNED_START))
			{
				// set the planned start, as it came from the project elements and then call sync_all to move the elements
				$bope->project->data['pm_planned_start'] = $ds['pe_planned_start'];
				$bope->project->save(null,false,false);	// not modification and NO notification
				if (($updated_pes = $bope->sync_all()) && ((int) $this->debug > 2 || $this->debug == 'read'))
				{
					$this->projectmanager_bo->debug_message("projectmanager_datasource::read(pm_id=$pm_id: $ds[pe_title]) $updated_pes elements updated to new project-start $ds[pe_planned_start]=".date('Y-m-d H:i',$ds['pe_planned_start']));
				}
			}
		}
		return $ds;
	}

	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		if (!is_array($data_id))
		{
			if (!$this->projectmanager_bo->read((int) $data_id)) return false;

			$data =& $this->projectmanager_bo->data;
		}
		else
		{
			$data =& $data_id;
		}
		// we ignore used time or real ends, comming only from the element list
		$ds = array(
			'ignore_real_end'      => !($data['pm_overwrite'] & PM_REAL_END),
			'ignore_used_time'     => !($data['pm_overwrite'] & PM_USED_TIME),
		);
		// if pm_ds_ignore_elements is set, ignore planned start&end for the element-list (not overwritten)
		if ($GLOBALS['egw_info']['flags']['projectmanager']['pm_ds_ignore_elements'])
		{
			$ds += array(
				'ignore_planned_start' => !($data['pm_overwrite'] & PM_PLANNED_START),
				'ignore_planned_end'   => !($data['pm_overwrite'] & PM_PLANNED_END),
				'ignore_real_start'    => !($data['pm_overwrite'] & PM_REAL_START),
			);
		}
		foreach($this->name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);

			if (isset($data[$pm_name]))
			{
				$ds[$name] = $data[$pm_name];
			}
		}
		$ds['pe_title'] = $this->projectmanager_bo->link_title($data['pm_id'],$data);
		// return the projectmembers as resources
		$ds['pe_resources'] = $data['pm_members'] ? array_keys($data['pm_members']) : array($data['pm_creator']);
		$ds['pe_details'] = $data['pm_description'];

		// use completition calculated by times, if completion is only set from the elements
		// if re is set, use this
		if (!($data['pm_overwrite'] & PM_COMPLETION) && $data['pm_replanned_time'] && $data['pm_used_time'])
		{
			$ds['pe_completion'] = round(100*$data['pm_used_time']/$data['pm_replanned_time']).'%';
			if ($ds['pe_completion'] > 100) $ds['pe_completion'] = '100%';
		}
		elseif (!($data['pm_overwrite'] & PM_COMPLETION) && $data['pm_planned_time'] && $data['pm_used_time'])
		{
			$ds['pe_completion'] = round(100*$data['pm_used_time']/$data['pm_planned_time']).'%';
			if ($ds['pe_completion'] > 100) $ds['pe_completion'] = '100%';
		}
		elseif (is_numeric($ds['pe_completion']))
		{
			$ds['pe_completion'] .= '%';
		}
		if ((int) $this->debug > 1 || $this->debug == 'get') $this->projectmanager_bo->debug_message("projectmanager_datasource::get($data_id) =".print_r($ds,true));

		return $ds;
	}

	/**
	 * Copy the datasource of a projectelement (sub-project) and re-link it with project $target
	 *
	 * @param array $element source project element representing an sub-project, $element['pe_app_id'] = pm_id
	 * @param int $target target project id
	 * @param array $target_data=null data of target-project, atm only pm_number is used
	 * @return array/boolean array(pm_id,link_id) on success, false otherwise
	 */
	function copy($element,$target,$target_data=null)
	{
		if ((int) $this->debug > 1 || $this->debug == 'copy') $this->projectmanager_bo->debug_message("projectmanager_datasource::copy(".print_r($element,true).",$target)");

		$data_backup = $this->projectmanager_bo->data;

		if (($pm_id = $this->projectmanager_bo->copy((int) $element['pe_app_id'],0,$target_data['pm_number'])))
		{
			if ($this->debug > 3 || $this->debug == 'copy') $this->projectmanager_bo->debug_message("projectmanager_datasource::copy() data=".print_r($this->projectmanager_bo->data,true));

			// link the new sub-project with the project
			$link_id = egw_link::link('projectmanager',$target,'projectmanager',$pm_id,$element['pe_remark'],0,0,1);
		}
		$this->projectmanager_bo->data = $data_backup;

		if ($this->debug > 2 || $this->debug == 'copy') $this->projectmanager_bo->debug_message("projectmanager_datasource::copy() returning pm_id=$pm_id, link_id=$link_id, data=".print_r($this->projectmanager_bo->data,true));

		return $pm_id ? array($pm_id,$link_id) : false;
	}

	/**
	 * Delete the datasource of a project element
	 *
	 * @param int $id
	 * @return boolean true on success, false on error
	 */
	function delete($id)
	{
		return $this->projectmanager_bo->delete($id,true);	// true = propagate the source deletion
	}

	/**
	 * Change the status of an infolog entry according to the project status
	 *
	 * @param int $id
	 * @param string $status
	 * @return boolean true if status changed, false otherwise
	 */
	function change_status($id,$status)
	{
		$data_backup = $this->projectmanager_bo->data;

		if (($Ok = (boolean) $this->projectmanager_bo->read((int) $id)))
		{
			$Ok = $this->projectmanager_bo->save(array('pm_status' => $status)) == 0;
		}
		$this->projectmanager_bo->data = $data_backup;

		if (!$Ok) return false;

		return ExecMethod2('projectmanager.projectmanager_elements_bo.run_on_sources','change_status',array('pm_id'=>$id),$status);
	}
}