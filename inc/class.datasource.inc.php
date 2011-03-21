<?php
/**
 * ProjectManager - DataSource baseclass
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * constants for the different types of data
 *
 * or'ed together eg. for egw_pm_eletemts.pe_overwrite
 */
/** int percentage completion 0-100 */
define('PM_COMPLETION',1);
/** int seconds planned time */
define('PM_PLANNED_TIME',2);
/** int seconds used time */
define('PM_USED_TIME',4);
/** double planned budget */
define('PM_PLANNED_BUDGET',8);
/** double planned budget */
define('PM_USED_BUDGET',16);
/** int timestamp planned start-date */
define('PM_PLANNED_START',32);
/** int timestamp real start-date */
define('PM_REAL_START',64);
/** int timestamp planned end-date */
define('PM_PLANNED_END',128);
/** int timestamp real end-date */
define('PM_REAL_END',256);
/** array with (int) user- or (string) resource-ids */
define('PM_RESOURCES',512);
/** string title */
define('PM_TITLE',1024);
/** string details */
define('PM_DETAILS',2048);
/** int pl_id */
define('PM_PRICELIST_ID',4096);
/** double price */
define('PM_UNITPRICE',8192);
/** double planned quantity */
define('PM_PLANNED_QUANTITY',16384);
/** double used quantity */
define('PM_USED_QUANTITY',32768);
/** int seconds replanned time */
define('PM_REPLANNED_TIME',65536);
/** all data-types or'ed together, need to be changed if new data-types get added */
define('PM_ALL_DATA',131071);

/**
 * DataSource baseclass of the ProjectManager
 *
 * This is the baseclass of all DataSources, each spezific DataSource extends it and implement
 * an own get method.
 *
 * The read method of this class sets (if not set by the get method) the planned start- and endtime:
 *  - planned start from the end of a start constrain or the project start-time
 *  - planned end from the planned time and a start-time
 *  - real or planned start and end from each other
 */
class datasource
{
	/**
	 * @var string $type type of the datasource, eg. name of the supported app
	 */
	var $type;
	/**
	 * @var bolink-object $link instance of the link-class
	 */
	var $link;
	/**
	 * @var object $bo bo-object of the used app
	 */
	var $bo;
	/**
	 * @var int $valid valid data-types of that source (or'ed PM_ constants)
	 */
	var $valid = 0;
	/**
	 * @var array $name2id translated names / array-keys to the numeric ids PM_*
	 */
	var $name2id = array(
		'pe_completion'     => PM_COMPLETION,
		'pe_planned_time'   => PM_PLANNED_TIME,
		'pe_replanned_time' => PM_REPLANNED_TIME,
		'pe_used_time'      => PM_USED_TIME,
		'pe_planned_budget' => PM_PLANNED_BUDGET,
		'pe_used_budget'    => PM_USED_BUDGET,
		'pe_planned_start'  => PM_PLANNED_START,
		'pe_real_start'     => PM_REAL_START,
		'pe_planned_end'    => PM_PLANNED_END,
		'pe_real_end'       => PM_REAL_END,
		'pe_title'          => PM_TITLE,
		'pe_resources'		=> PM_RESOURCES,
		'pe_details'		=> PM_DETAILS,
		'pl_id'				=> PM_PRICELIST_ID,
		'pe_unitprice'      => PM_UNITPRICE,
		'pe_planned_quantity' => PM_PLANNED_QUANTITY,
		'pe_used_quantity'  => PM_USED_QUANTITY,
	);
	/**
	 * @var projectmanager_elements_bo-object $bo_pe pe object to read other pe's (eg. for constraints)
	 */
	var $bo_pe;

	/**
	 * Constructor
	 *
	 * @param string $type=null type of the datasource
	 */
	function __construct($type=null)
	{
		$this->type = $type;
	}

	/**
	 * PHP4 constructor
	 */
	function datasource($type=null)
	{
		self::__construct($type);
	}

	/**
	 * get an item from the underlaying app and convert applying data ia a datasource array
	 *
	 * A datasource array can contain values for the keys: completiton, {planned|used}_time, {planned|used}_budget,
	 *	{planned|real}_start, {planned|real}_end and pe_status
	 * Not set values mean they are not supported by the datasource.
	 *
	 * Reimplent this function for spezial datasource types (not read!)
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		if (($title = egw_link::title($this->type,$data_id)))
		{
			return array(
				'pe_title'  => $title,
				'pe_status' => 'ignore',	// not supported datasources are ignored, as they contain no values, eg. addressbook
			);
		}
		return false;
	}

	/**
	 * read an item from a datasource (via the get methode) and try to set (guess) some not supported values
	 *
	 * A datasource array can contain values for the keys: completiton, {planned|used}_time, {planned|used}_budget,
	 *	{planned|real}_start, {planned|real}_end
	 * Not set values mean they are not supported by the datasource.
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @param array $pe_data data of the project-element or null, eg. to use the constraints
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function read($data_id,$pe_data=null)
	{
		$ds = $this->get($data_id);

		//echo "<p>datasource::read($data_id,$pe_data) ds="; _debug_array($ds);

		if ($ds)
		{
			// setting a not set planned start from a contrains
			if ((!$ds['pe_planned_start'] || $ds['ignore_planned_start']) && !is_null($pe_data) && $pe_data['pe_constraints']['start'])
			{
				//echo "start-constr."; _debug_array($pe_data['pe_constraints']['start']);
				$start = 0;
				if (!is_object($this->bo_pe))
				{
					$this->bo_pe = new projectmanager_elements_bo($pe_data['pm_id']);
				}
				foreach($pe_data['pe_constraints']['start'] as $start_pe_id)
				{
					if ($this->bo_pe->read(array('pm_id'=>$pe_data['pm_id'],'pe_id'=>$start_pe_id)) &&
						$start < $this->bo_pe->data['pe_planned_end'])
					{
						$start = $this->bo_pe->data['pe_planned_end'];
						//echo "startdate from startconstrain with"; _debug_array($this->bo_pe->data);
					}
				}
				if ($start)
				{
					$ds['pe_planned_start'] = $this->project->date_add($start,0,$ds['pe_resources'][0]);
					//echo "<p>$ds[pe_title] set planned start to ".date('D Y-m-d H:i',$ds['pe_planned_start'])."</p>\n";
					unset($ds['ignore_planned_start']);
				}
			}
			// setting the planned start from the real-start
			if (!$ds['pe_planned_start'] && !$ds['ignore_planned_start'] && $ds['pe_real_start'])
			{
				$ds['pe_planned_start'] = $ds['pe_real_start'];
			}
			// setting the planned start from the projects start
			if ((!$ds['pe_planned_start'] || $ds['ignore_planned_start']) && $pe_data['pm_id'])
			{
				if (!is_object($this->bo_pe))
				{
					$this->bo_pe = new projectmanager_elements_bo($pe_data['pm_id']);
				}
				if ($this->bo_pe->pm_id != $pe_data['pm_id'])
				{
					$this->bo_pe->__construct($pe_data['pm_id']);
				}
				if ($this->bo_pe->project->data['pm_planned_start'] || $this->bo_pe->project->data['pm_real_start'])
				{
					$ds['pe_planned_start'] = $this->bo_pe->project->data['pm_planned_start'] ?
						$this->bo_pe->project->data['pm_planned_start'] : $this->bo_pe->project->data['pm_real_start'];
					unset($ds['ignore_planned_start']);
				}
			}
			// calculating the planned end-date from the planned or replanned time
			if ((!$ds['pe_planned_end'] || $ds['ignore_planned_end']) && $ds['pe_replanned_time'])
			{
				if ($ds['pe_planned_start'] && is_object($this->project))
				{
					$ds['pe_planned_end'] = $this->project->date_add($ds['pe_planned_start'],$ds['pe_planned_time'],$ds['pe_resources'][0]);
					//echo "<p>$ds[pe_title] set planned end to ".date('D Y-m-d H:i',$ds['pe_planned_end']).' '.__LINE__."</p>\n";
					unset($ds['ignore_planned_end']);
				}
			}
			elseif ((!$ds['pe_planned_end'] || $ds['ignore_planned_end']) && $ds['pe_planned_time'])
			{
				if ($ds['pe_planned_start'] && is_object($this->project))
				{
					$ds['pe_planned_end'] = $this->project->date_add($ds['pe_planned_start'],$ds['pe_planned_time'],$ds['pe_resources'][0]);
					//echo "<p>$ds[pe_title] set planned end to ".date('D Y-m-d H:i',$ds['pe_planned_end']).' '.__LINE__."</p>\n";
					unset($ds['ignore_planned_end']);
				}
			}
			// setting real or planned start-date, from each other if not set
			if ((!isset($ds['pe_real_start']) || $ds['ignore_real_start']) && isset($ds['pe_planned_start']))
			{
				$ds['pe_real_start'] = $ds['pe_planned_start'];
			}
			elseif (!isset($ds['pe_planned_start']) && isset($ds['pe_real_start']))
			{
				$ds['pe_planned_start'] = $ds['pe_real_start'];
			}

			// calculating the real end-date from the real or planned/replanned time
			if ((!$ds['pe_real_end'] || $ds['ignore_real_end']) && $ds['pe_real_start'] &&
				($ds['pe_used_time'] && !$ds['ignore_used_time'] || $ds['pe_replanned_time']) && is_object($this->project))
			{
				$ds['pe_real_end'] = $this->project->date_add($ds['pe_real_start'],
					($t = $ds['pe_used_time'] && !$ds['ignore_used_time'] ? $ds['pe_used_time'] : $ds['pe_replanned_time']),$ds['pe_resources'][0]);
				//echo "<p>$ds[pe_title] set real end to ".date('D Y-m-d H:i',$ds['pe_real_end']).' '.__LINE__."</p>\n";
				unset($ds['ignore_real_end']);
			}
			elseif ((!$ds['pe_real_end'] || $ds['ignore_real_end']) && $ds['pe_real_start'] &&
				($ds['pe_used_time'] && !$ds['ignore_used_time'] || $ds['pe_planned_time']) && is_object($this->project))
			{
				$ds['pe_real_end'] = $this->project->date_add($ds['pe_real_start'],
					($t = $ds['pe_used_time'] && !$ds['ignore_used_time'] ? $ds['pe_used_time'] : $ds['pe_planned_time']),$ds['pe_resources'][0]);
				//echo "<p>$ds[pe_title] set real end to ".date('D Y-m-d H:i',$ds['pe_real_end']).' '.__LINE__."</p>\n";
				unset($ds['ignore_real_end']);
			}
			// setting real or planned end-date, from each other if not set
			if ((!isset($ds['pe_real_end']) || $ds['ignore_real_end']) && isset($ds['pe_planned_end']))
			{
				$ds['pe_real_end'] = $ds['pe_planned_end'];
			}
			elseif (!isset($ds['pe_planned_end']) && isset($ds['pe_real_end']))
			{
				$ds['pe_planned_end'] = $ds['pe_real_end'];
			}

			// try calculating a (second) completion from the times
			if (!empty($ds['pe_used_time']) && (int) $ds['pe_planned_time'] > 0)
			{
				$compl_by_time = $ds['pe_used_time'] / $ds['pe_planned_time'];

				// if no completion is given by the datasource use the calculated one
				if (!isset($ds['pe_completion']))
				{
					$ds['pe_completion'] = $compl_by_time;
				}
				elseif ($compl_by_time < $ds['pe_completion'])
				{
					$ds['warning']['completion_by_time'] = $compl_by_time;
				}
			}
			// try calculating a (second) completion from the times
			if (!empty($ds['pe_used_time']) && (int) $ds['pe_replanned_time'] > 0)
			{
				$compl_by_time = $ds['pe_used_time'] / $ds['pe_replanned_time'];

				// if no completion is given by the datasource use the calculated one
				if (!isset($ds['pe_completion']))
				{
					$ds['pe_completion'] = $compl_by_time;
				}
				elseif ($compl_by_time < $ds['pe_completion'])
				{
					$ds['warning']['completion_by_time'] = $compl_by_time;
				}
			}
			// try calculating a (second) completion from the budget
			if(!empty($ds['pe_used_budget']) && $ds['pe_planned_budget'] > 0)
			{
				$compl_by_budget = $ds['pe_used_budget'] / $ds['pe_planned_budget'];

				// if no completion is given by the datasource use the calculated one
				if (!isset($ds['pe_completion']))
				{
					$ds['pe_completion'] = $compl_by_budget;
				}
				elseif ($compl_by_budget < $ds['pe_completion'])
				{
					$ds['warning']['completion_by_budget'] = $compl_by_budget;
				}
			}
			// setting quantity from time, if not given by the ds
			foreach(array(
				'pe_planned_time' => 'pe_planned_quantity',
				'pe_used_time'    => 'pe_used_quantity',
			) as $time => $quantity)
			{
				if (!isset($ds[$quantity]) && isset($ds[$time]))
				{
					$ds[$quantity] = $ds[$time] / 60.0;	// time is in min, quantity in h
				}
			}
			// setting the budget from unitprice and quantity
			if (isset($ds['pe_unitprice']))
			{
				foreach(array(
					'pe_planned_quantity' => 'pe_planned_budget',
					'pe_used_quantity'    => 'pe_used_budget',
				) as $quantity => $budget)
				{
					if (!isset($ds[$budget]) && isset($ds[$quantity]))
					{
						$ds[$budget] = $ds[$quantity] * $ds['pe_unitprice'];
					}
				}
			}
		}
		//_debug_array($ds);
		return $ds;
	}

	/**
	 * reading the not-overwritten values from the element-data
	 *
	 * Can be used instead of read, if there's no read-access to the datasource itself
	 *
	 * @param array $data element data
	 * @return array
	 */
	function element_values($data)
	{
		$values = array();

		foreach($this->name2id as $name => $id)
		{
			if (!($data['pe_overwrite'] & $id))
			{
				$values[$name] = $data[$name];
			}
		}
		return $values;
	}
}
