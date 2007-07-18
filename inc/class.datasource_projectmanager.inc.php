<?php
/**
 * ProjectManager - DataSource for ProjectManger (Subprojects)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for ProjectManager itself
 */
class datasource_projectmanager extends datasource
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
	 * Constructor
	 */
	function datasource_projectmanager()
	{
		$this->datasource('projectmanager');
		
		$this->valid = PM_ALL_DATA;
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
		// we use $GLOBALS['boprojectmanager'] instanciated by get

		if ((int) $this->debug > 1 || $this->debug == 'read')
		{
			$GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::read(pm_id=$data_id,".print_r($pe_data,true).')='.print_r($ds,true));
		}
		// check if datasource::read changed our planned start, because it's determined by the constrains or parent
		if (!is_null($pe_data) && $pe_data['pe_id'] && $ds['pe_planned_start'] != $pe_data['pe_planned_start'])
		{
			$pm_id = is_array($data_id) ? $data_id['pm_id'] : $data_id;

			$GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::read(pm_id=$pm_id: $ds[pe_title]) planned start changed from $pe_data[pe_planned_start]=".date('Y-m-d H:i',$pe_data['pe_planned_start'])." to $ds[pe_planned_start]=".date('Y-m-d H:i',$ds['pe_planned_start'])/*.", pe_data=".print_r($pe_data,true)*/);

			$bope =& CreateObject('projectmanager.boprojectelements',$pm_id);
			
			if (!($bope->project->data['pm_overwrite'] & PM_PLANNED_START))
			{
				// set the planned start, as it came from the project elements and then call sync_all to move the elements
				$bope->project->data['pm_planned_start'] = $ds['pe_planned_start'];
				$bope->project->save(null,false,false);	// not modification and NO notification
				if (($updated_pes = $bope->sync_all()))
				{
					$GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::read(pm_id=$pm_id: $ds[pe_title]) $updated_pes elements updated to new project-start $ds[pe_planned_start]=".date('Y-m-d H:i',$ds['pe_planned_start']));					
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
		// we use $GLOBALS['boprojectmanager'] as an already running instance may be availible there
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.boprojectmanager.inc.php');
			$GLOBALS['boprojectmanager'] =& new boprojectmanager();
		}
		if (!is_array($data_id))
		{
			if (!$GLOBALS['boprojectmanager']->read((int) $data_id)) return false;

			$data =& $GLOBALS['boprojectmanager']->data;
		}
		else
		{
			$data =& $data_id;
		}
		// if pm_ds_ignore_elements is set, ignore planned start&end for the element-list (not overwritten)
		$ds = !$GLOBALS['egw_info']['flags']['projectmanager']['pm_ds_ignore_elements'] ? array() :	array(
			'ignore_planned_start' => !($data['pm_overwrite'] & PM_PLANNED_START),
			'ignore_planned_end'   => !($data['pm_overwrite'] & PM_PLANNED_END),
			'ignore_real_start'    => !($data['pm_overwrite'] & PM_REAL_START),
			'ignore_real_end'      => !($data['pm_overwrite'] & PM_REAL_END),
		);
		foreach($this->name2id as $name => $id)
		{
			$pm_name = str_replace('pe_','pm_',$name);
			
			if (isset($data[$pm_name]))
			{
				$ds[$name] = $data[$pm_name];
			}
		}
		$ds['pe_title'] = $GLOBALS['boprojectmanager']->link_title($data['pm_id'],$data);
		// return the projectmembers as resources
		$ds['pe_resources'] = $data['pm_members'] ? array_keys($data['pm_members']) : array($data['pm_creator']);
		$ds['pe_details'] = $data['pm_description'];
		if (is_numeric($ds['pe_completion'])) $ds['pe_completion'] .= '%';

		if ((int) $this->debug > 1 || $this->debug == 'get') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::get($data_id) =".print_r($ds,true));

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
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.boprojectmanager.inc.php');
			$GLOBALS['boprojectmanager'] =& new boprojectmanager();
		}
		if ((int) $this->debug > 1 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy(".print_r($element,true).",$target)");

		$data_backup = $GLOBALS['boprojectmanager']->data;

		if (($pm_id = $GLOBALS['boprojectmanager']->copy((int) $element['pe_app_id'],0,$target_data['pm_number'])))
		{
			if ($this->debug > 3 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy() data=".print_r($GLOBALS['boprojectmanager']->data,true));

			// link the new sub-project with the project
			$link_id = $GLOBALS['boprojectmanager']->link->link('projectmanager',$target,'projectmanager',$pm_id,$element['pe_remark'],0,0,1);
		}
		$GLOBALS['boprojectmanager']->data = $data_backup;

		if ($this->debug > 2 || $this->debug == 'copy') $GLOBALS['boprojectmanager']->debug_message("datasource_projectmanager::copy() returning pm_id=$pm_id, link_id=$link_id, data=".print_r($GLOBALS['boprojectmanager']->data,true));

		return $pm_id ? array($pm_id,$link_id) : false;
	}
}