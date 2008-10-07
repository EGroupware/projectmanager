<?php
/**
 * ProjectManager - Constraints storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Milestones storage object of the projectmanager
 *
 * Tables: egw_pm_milestones
 */
class projectmanager_milestones_so extends so_sql
{
	/**
	 * pm_id of current project
	 *
	 * @var int
	 */
	var $pm_id;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * It is sufficent to give a ms_id, as they are unique
	 *
	 * @param int $pm_id pm_id of the project to use, default null
	 * @param int $ms_id ms_id of the milestone to load, default null
	 */
	function __construct($pm_id=null,$ms_id=null)
	{
		parent::__construct('projectmanager','egw_pm_milestones');

		if ((int) $ms_id)
		{
			$this->read($ms_id);
			$this->pm_id = $this->data['pm_id'];
		}
		if ((int) $pm_id)
		{
			$this->pm_id = (int) $pm_id;
		}
	}

	/**
	 * searches db for rows matching searchcriteria, reimplemented to automatic add $this->pm_id
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys=true True returns only keys, False returns all cols
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='ms_date',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		if ($this->pm_id && !isset($criteria['pm_id']) && !isset($filter['pm_id']))
		{
			$filter['pm_id'] = $this->pm_id;
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	function &titles($keys=array())
	{
		$milestones = array();
		foreach((array)$this->search($keys,'ms_id,ms_date,ms_title') as $milestone)
		{
			if (!$milestone) continue;

			$milestones[$milestone['ms_id']] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],$milestone['ms_date']).
				': '.$milestone['ms_title'];
		}
		return $milestones;
	}

	/**
	 * Copy the milestones from an other project
	 *
	 * @param int $source pm_id of the project to copy
	 * @param int $pm_id=null pm_id to use, default null to use the current pm_id
	 * @return array with source => new ms_id's
	 */
	function copy($source,$pm_id=null)
	{
		if (is_null($pm_id)) $pm_id = $this->pm_id;

		$copied = array();
		if (($milestones = $this->search(array('pm_id' => $source),false)))
		{
			foreach($milestones as $milestone)
			{
				$old = $milestone['ms_id'];
				unset($milestone['ms_id']);
				$milestone['pm_id'] = $pm_id;
				$this->init($milestone);
				$this->save();

				$copied[$old] = $this->data['ms_id'];
			}
		}
		return $copied;
	}
}