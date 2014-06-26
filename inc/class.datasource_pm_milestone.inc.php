<?php
/**
 * ProjectManager - DataSource for ProjectManger milestones
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package projectmanager
 * @copyright (c) 2014 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for ProjectManager milestones
 */
class datasource_pm_milestone extends datasource
{

	protected $milestones;

	public function __construct()
	{
		parent::__construct('pm_milestone');

		$this->valid = PM_TITLE | PM_DETAILS | PM_REAL_START;
		$this->milestones = new projectmanager_milestones_so();
	}

	/**
	 * get an item from the underlaying app and convert applying data ia a datasource array
	 *
	 * A datasource array can contain values for the keys: completiton, {planned|used}_time, {planned|used}_budget,
	 *	{planned|real}_start, {planned|real}_end and pe_status
	 * Not set values mean they are not supported by the datasource.
	 *
	 * Reimplemented for milestones
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		error_log(__METHOD__ . "($data_id");
		if (!is_array($data_id))
		{
			$data = $this->milestones->read($data_id);
			if (!is_array($data)) return false;
		}
		else
		{
			$data =& $data_id;
		}
		return array(
			'pe_title'        => $this->milestones->titles(array($data['ms_id'])),
			'pe_real_start'   => $data['ms_date'],
			'pe_details'      => nl2br($data['ms_description'])
		);
	}
}