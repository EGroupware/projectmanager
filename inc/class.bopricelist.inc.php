<?php
/**
 * ProjectManager - Pricelist buisness object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.sopricelist.inc.php');

/**
 * Pricelist buisness object of the projectmanager
 */
class bopricelist extends sopricelist
{
	/**
	 * @var array $timestamps timestaps that need to be adjusted to user-time on reading or saving
	 */
	var $timestamps = array(
		'pl_modified','pl_validsince',
	);
	/**
	 * @var int $tz_offset_s offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 */
	var $tz_offset_s;
	/**
	 * @var int $now actual USER time
	 */
	var $now;

	/**
	 * Constructor, calls the constructor of the extended class
	 * 
	 * @param int $pm_id=0 pm_id of the project to use, default 0 (project independent / standard prices)
	 */
	function bopricelist($pm_id=0)
	{
		$this->sopricelist($pm_id);

		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
		$this->now = time() + $this->tz_offset_s;
		
		if (!is_object($GLOBALS['boprojectmanager']))
		{
			CreateObject('projectmanager.boprojectmanager',$pm_id);
		}
		$this->project =& $GLOBALS['boprojectmanager'];
	}
	
	/**
	 * saves the content of data to the db, also checks acl and deletes not longer set prices!
	 *
	 * @param array $keys=null if given $keys are copied to data before saveing => allows a save as
	 * @return int/boolean 0 on success, true on missing acl-rights and errno != 0 else
	 */
	function save($keys=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);
		
		if ((int)$this->debug >= 2)
		{
			echo "<p>sopricelist::save(".print_r($keys,true).") data=";
			_debug_array($this->data);
		}
		if ($this->data['pl_id'])
		{
			$backup =& $this->data;		// would get overwritten by read
			unset($this->data);
			$old = $this->read(array(
				'pl_id' => $backup['pl_id'],
				'pm_id' => $backup['pm_id'] ? array($backup['pm_id'],0) : 0
			));
			$this->data =& $backup;
			unset($backup);
		}
		$need_general = count($old['prices']) > 0 || count($this->data['prices']) > 0;
		if (!($pricelist_need_save = !$this->data['pl_id']))
		{
			$this->data['cat_id'] = (int) $this->data['cat_id'];
			foreach($this->db_cols as $col => $data)
			{
				if (!$old || $this->data[$col] != $old[$col])
				{
					$pricelist_need_save = true;
					break;
				}
			}
		}
		if ($pricelist_need_save)
		{
			// check acl
			if (!$this->check_acl(EGW_ACL_EDIT,$need_general ? 0 : $this->data['pm_id']))
			{
				return lang('permission denied !!!').' need_general='.(int)$need_general;
			}
			if (($err = parent::save($this->data)))
			{
				return $err;
			}
		}
		$prices = array();
		foreach($this->data['prices'] as $key => $nul)
		{
			$price =& $this->data['prices'][$key];
			$price['pm_id'] = 0;
			$price['pl_billable'] = $price['pl_customertitle'] = null;
			if (count($this->data['prices']) == 1) $price['pl_validsince'] = 0;	// no date for first price
			$prices[] =& $price;
		}
		foreach($this->data['project_prices'] as $key => $nul)
		{
			$price =& $this->data['project_prices'][$key];
			foreach(array('pm_id','pl_billable','pl_customertitle') as $key)
			{
				if (!isset($price[$key])) $price[$key] = $this->data[$key];
			}
			if (count($this->data['project_prices']) == 1) $price['pl_validsince'] = 0;	// no date for first price
			$prices[] =& $price;
		}
		
		// index prices in old by pm_id and date (!) of validsince
		$old_prices = array();
		if ($old)
		{
			foreach(array_merge($old['prices'],$old['project_prices']) as $old_price)
			{
				$old_prices[(int)$old_price['pm_id']][date('Y-m-d',(int)$old_price['pl_validsince'])] = $old_price;
			}
		}
		foreach($prices as $key => $nul)
		{
			$price =& $prices[$key];
			if (!isset($price['pl_id'])) $price['pl_id'] = $this->data['pl_id'];
			$old_price = $old_prices[(int)$price['pm_id']][date('Y-m-d',(int)$price['pl_validsince'])];
			if (!$this->prices_equal($price,$old_price))
			{
				// price needs saving, checking acl now
				if (!$this->check_acl(EGW_ACL_EDIT,$price['pm_id']))
				{
					return lang('permission denied !!!').' check_acl(EGW_ACL_EDIT(pm_id='.(int)$price[pm_id].')';
				}
				// maintain time of old price, to not create doublets with different times by users operating in different TZ's
				if ($old_price) $price['pl_validsince'] = $old_price['pl_validsince'];

				if (($err = parent::save_price($price)))
				{
					return $err;
				}
			}
			unset($old_prices[(int)$price['pm_id']][date('Y-m-d',(int)$old_price['pl_validsince'])]);
		}
		// check if there are old prices not longer set ==> delete them
		foreach($old_prices as $pm_id => $prices)
		{
			foreach($prices as $price)
			{
				if (!$this->check_acl(EGW_ACL_DELETE,$price['pm_id']))
				{
					return lang('permission denied !!!').' check_acl(EGW_ACL_DELETE(pm_id='.(int)$price[pm_id].')';
				}
				if (!parent::delete($price))
				{
					return lang('Error: deleting price !!!');
				}
			}
		}
		return 0;
	}
	
	/**
	 * search elements, reimplemented to use $this->pm_id, if no pm_id given in criteria or filter and join with the prices table
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys True returns only keys, False returns all cols
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard appended befor and after each criteria
	 * @param boolean $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param int/boolean $start if != false, return only maxmatch rows begining with start
	 * @param array $filter if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string/boolean $join=true default join with prices-table or string as in so_sql
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function search($criteria,$only_keys=false,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		if (!$this->check_acl(EGW_ACL_READ,(int)($criteria['pm_id'] ? $criteria['pm_id'] : $this->pm_id)))
		{
			return false;
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}
	
	/**
	 * return priceslist of a given project (only bookable or billable price, no general pricelist)
	 *
	 * @param int $pm_id project
	 * @return array/boolean array with pl_id => pl_unit: pl_tiltle (pl_price) pairs or false on error (eg. no ACL)
	 */
	function pricelist($pm_id)
	{
		//echo "<p>bopricelist::pricelist($pm_id)</p>\n";
		if (!($prices =& $this->search(array('pm_id' => $pm_id))))
		{
			return false;
		}
		$options = array();
		foreach($prices as $price)
		{
			$options[$price['pl_id']] = $price['pl_unit'].' '.$price['pl_title'].
				($price['pl_customertitle'] ? ': '.$price['pl_customertitle'] : '').
				' ('.$price['pl_price'].')';
		}
		return $options;
	}

	/**
	 * reads one pricelist-itme specified by $keys, reimplemented to use $this->pm_id, if no pm_id given
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string/boolean $join=true default join with links-table or string as in so_sql
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join=true)
	{
		// check if we have the requested access to all given pricelists
		foreach(!is_array($keys) || !isset($keys['pm_id']) ? array($this->pm_id) : 
			(is_array($keys['pm_id']) ? $keys['pm_id'] : array($keys['pm_id'])) as $pm_id)
		{
			if (!$this->check_acl(EGW_ACL_READ,(int)$pm_id)) return false;
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * delete pricelist-entries and price(s) specified by keys pl_id, pm_id and/or pl_validsince
	 *
	 * If the last price of a pricelist-entry gets deleted, the pricelist entry is automatic deleted too!
	 *
	 * @param array/int $keys array with keys pm_id, pl_id and/or pl_validsince to delete or integer pm_id
	 * @return int/boolean number of deleted prices or false if permission denied
	 */
	function delete($keys)
	{
		if (!$this->check_acl(EGW_ACL_EDIT,(int)(is_array($keys) ? $keys['pm_id'] : $keys)))
		{
			return false;
		}
		return parent::delete($keys);
	}

	/**
	 * checks if the user has sufficent rights for a certain action
	 *
	 * For project-spez. prices/data you need a EGW_ACL_BUDGET right of the project for read or 
	 * EGW_ACL_EDIT_BUDGET for write or delete.
	 * For general pricelist data you need atm. no extra read rights, but is_admin to write/delete.
	 *
	 * @param int $required EGW_ACL_{READ|WRITE|DELETE}
	 * @param int $pm_id=0 project-id for project-spez. prices/data to check, default 0 = general pricelist
	 * @param array/int $data=null data/id of pricelist-entry, default null = use $this->data ($pm_id is ignored)
	 * @return boolean true if the user has the rights, false otherwise
	 */
	function check_acl($required,$pm_id=0,$data=null)
	{
/*		not used atm.
		if (is_null($data))
		{
			$data =& $this->data;
		}
		elseif (!is_array($data))
		{
			if ((int) $data)
			{
				$backup = $this->data;
				$data = $this->read(array('pm_id'=>(int)$pm_id,'pl_id' => (int)$data));
				$this->data = $backup;
			}
			else
			{
				return false;
			}
		}
*/
		if (!$pm_id)
		{
			return $required == EGW_ACL_READ || $this->project->is_admin;
		}
		return $this->project->check_acl($required == EGW_ACL_READ ? EGW_ACL_BUDGET : EGW_ACL_EDIT_BUDGET,$pm_id);
	}

	/**
	 * Compares two prices to check if they are equal
	 *
	 * The compared fields depend on the price being project-specific or not
	 *
	 * @param array $price
	 * @param array $price2
	 * @return boolean true if the two prices are identical, false otherwise or if they are no arrays!
	 */
	function prices_equal($price,$price2)
	{
		if (!is_array($price) || !is_array($price2)) return false;
		
		$to_compare = array('pl_id','pm_id','pl_price','pl_validsince','pl_modified','pl_modifier');
		
		if ($price['pm_id'])
		{
			$to_compare[] = 'pl_customertitle';
			$to_compare[] = 'pl_billable';
		}
		$equal = true;
		foreach($to_compare as $key)
		{
			switch($key)
			{
				case 'pm_id':
					$equal = (int) $price['pm_id'] == (int) $price2['pm_id'];
					break;
				case 'pl_validsince':
					$equal = date('Y-m-d',(int)$price['pl_validsince']) == date('Y-m-d',(int)$price2['pl_validsince']);
					break;
				default:
					$equal = $price[$key] == $price2[$key];
					break;
			}
			if (!$equal) break;
		}
		if ((int)$this->debug >= 3) echo "<p>bopricelist::prices_equal(".print_r($price,true).','.print_r($price2,true).') = '.($equal ? 'true' : "differ in $key: {$price[$key]} != {$price2[$key]}")."</p>\n";

		return $equal;
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
		return $data;
	}
}