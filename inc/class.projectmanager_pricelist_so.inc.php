<?php
/**
 * ProjectManager - Pricelist storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Pricelist storage object of the projectmanager
 *
 * Tables: egw_pm_pricelist, egw_pm_prices
 */
class projectmanager_pricelist_so extends so_sql
{
	/**
	 * Table name of the prices table
	 *
	 * @var string
	 */
	var $prices_table = 'egw_pm_prices';
	/**
	 * Default join with the prices table
	 *
	 * @var string
	 */
	var $prices_join = 'JOIN egw_pm_prices p ON egw_pm_pricelist.pl_id=p.pl_id';
	/**
	 * Extracolumns from $this->prices_table
	 *
	 * @var array
	 */
	var $prices_extracols = array('pm_id','pl_validsince','pl_price','pl_customertitle','pl_modifier','pl_modified','pl_billable');
	/**
	 * Project we work on or 0 for standard pricelist only
	 *
	 * @var int
	 */
	var $pm_id;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id=0 pm_id of the project to use, default 0 (project independent / standard prices)
	 */
	function __construct($pm_id=0)
	{
		parent::__construct('projectmanager','egw_pm_pricelist');	// sets $this->table_name

		$this->pm_id = (int) $pm_id;
	}

	/**
	 * reads one pricelist-item specified by $keys, reimplemented to use $this->pm_id, if no pm_id given
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string/boolean $join=true default join with links-table or string as in so_sql
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join=true)
	{
		if (!is_array($keys)) $keys = array('pl_id' => (int) $keys);

		if ($join === true)	// add join with links-table and extra-columns
		{
			// just pl_id would be ambigues
			$keys[] = $this->table_name.'.pl_id='.(int)$keys['pl_id'];
			unset($keys['pl_id']);

			if (isset($keys['pl_validsince']))
			{
				$keys[] = 'pl_validsince <= '.(int)$keys['pl_validsince'];
				unset($keys['pl_validsince']);
			}
			if (isset($keys['pm_id']))
			{
				$keys[] = $this->db->expression($this->prices_table,array('pm_id' => $keys['pm_id']));
				unset($keys['pm_id']);
			}
			$join = $this->prices_join;

			if (!$extra_cols) $extra_cols = $this->prices_extracols;

			// we use search as the join might return multiple columns, which we put in the prices and project_prices array
			if (!($prices = $this->search(false,false,'pm_id DESC,pl_validsince DESC',$extra_cols,'',false,'AND',false,$keys,$join)))
			{
				return false;
			}
			list(,$this->data) = each($prices);
			$this->data['prices'] = $this->data['project_prices'] = array();

			foreach($prices as $price)
			{
				if ($price['pm_id'])
				{
					$this->data['project_prices'][] = $price;
				}
				else
				{
					if (array_key_exists('gen_pl_billable',$this->data) === false)
					{
						$this->data['gen_pl_billable'] = $price['pl_billable'];
					}
					$this->data['prices'][] = $price;
				}
			}
			return $this->data;
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * sql to define a priority depending on the relation-ship of the project the price was defined for to the given one
	 *
	 * The given project $pm_id has the highest priority, if $use_standard the standard-pricelist has priority 0
	 *
	 * @param int $pm_id=0 project to use, default $this->pm_id
	 * @param string $col='pm_id' column-name to use, default 'pm_id', can be changed to use a table-name/-alias too
	 * @param boolean $use_general=true should the general-pricelist be included too (pm_id=0)
	 * @return string sql with CASE WHEN
	 */
	function sql_priority($pm_id=0,$col='pm_id',$use_general=true)
	{
		if (!$pm_id) $pm_id = $this->pm_id;

		$ancestors = array($pm_id);

		if (!($ancestors = $this->project->ancestors($pm_id,$ancestors)))
		{
			echo "<p>sql_priority($pm_id,$col,$use_general) ancestory returnd false</p>\n";
			return false;
		}
		$sql = 'CASE '.$col;
		foreach($ancestors as $n => $pm_id)
		{
			$sql .= ' WHEN '.$pm_id.' THEN '.(count($ancestors)-$n);
		}
		if ($use_general)
		{
			$sql .= ' WHEN 0 THEN 0';
		}
		$sql .= ' END';

		return $sql;
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
	function search($criteria,$only_keys=false,$order_by='pl_title',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		if ($join === true)	// add join with prices-table
		{
			$join = $this->prices_join;

			if (isset($filter['pl_validsince']))
			{
				$filter[] = 'pl_validsince <= '.($validsince=(int)$filter['pl_validsince']);
				unset($filter['pl_validsince']);
			}
			else
			{
				$validsince = time();
			}
			if (!$extra_cols)
			{
				$extra_cols = $this->prices_extracols;
			}
			else
			{
				$extra_cols = array_merge($this->prices_extracols,
					is_array($extra_cols) ? $extra_cols : explode(',',$extra_cols));
			}
			if (($no_general = isset($criteria['pm_id'])))
			{
				$pm_id = $criteria['pm_id'];
				unset($criteria['pm_id']);
			}
			else
			{
				$pm_id = isset($filter['pm_id']) ? $filter['pm_id'] : $this->pm_id;
				unset($filter['pm_id']);
			}
			if ($this->db->capabilities['sub_queries'])
			{
				$filter[] = "pl_validsince = (SELECT MAX(t.pl_validsince) FROM $this->prices_table t WHERE p.pl_id=t.pl_id AND p.pm_id=t.pm_id AND t.pl_validsince <= $validsince)";

				if (!$order_by) $order_by = 'pl_title';

				if ($pm_id)
				{
					$filter[] = $this->sql_priority($pm_id,'p.pm_id').' = (select MAX('.$this->sql_priority($pm_id,'m.pm_id').
						") FROM $this->prices_table m WHERE m.pl_id=p.pl_id)";

					if ($no_general) $filter[] = 'pl_billable IN (0,1)';
				}
				else
				{
					$filter['pm_id'] = 0;
				}
			}
			else	// mysql 4.0 or less
			{
				// ToDo: avoid subqueries (eg. use a join) or do it manual inside php
				//echo "<p>Pricelist needs a DB capabal of subqueries (eg. MySQL 4.1+) !!!</p>\n";
				$filter['pm_id'] = $this->project->ancestors($this->pm_id,array(0,(int)$this->pm_id));
				$filter[] = 'pl_validsince <= '.$validsince;
			}
			$prices_filter = array();
			foreach($extra_cols as $col)
			{
				if (isset($filter[$col]))
				{
					$prices_filter[$col] = $filter[$col];
					unset($filter[$col]);
				}
			}
			if (count($prices_filter))
			{
				$filter[] = $this->db->expression($this->prices_table,$prices_filter);
			}
			// do we run a search
			if ($op == 'OR' && $wildcard == '%' && $criteria['pl_id'] && $criteria['pl_id'] == $criteria['pl_title'])
			{
				foreach(array('pl_customertitle','pl_price') as $col)
				{
					$criteria[] = $col. ' LIKE '.$this->db->quote('%'.$criteria['pl_id'].'%');
				}
				unset($criteria['pl_id']);	// its ambigues
			}
		}
		// include sub-categories in the search
		if ($filter['cat_id'])
		{
			$filter['cat_id'] = $GLOBALS['egw']->categories->return_all_children($filter['cat_id']);
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	/**
	 * saves a single price
	 *
	 * @param array $price price-data
	 * @param boolean $touch_modified=true should the modififcation date and user be set
	 * @param int 0 on success, db::Errno otherwise
	 */
	function save_price(&$price,$touch_modified=true)
	{
		//echo "<p>sopricelist::save_price(".print_r($price,true).",$touch_modified)</p>\n";
		$price = $this->data2db($price);

		if ($touch_modified)
		{
			$price['pl_modified'] = time();
			$price['pl_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		$this->db->insert($this->prices_table,$price,array(
			'pm_id' => $price['pm_id'],
			'pl_id' => $price['pl_id'],
			'pl_validsince' => $price['pl_validsince'],
		),__LINE__,__FILE__);

		$price = $this->db2data($price);

		return $this->db->Errno;
	}

	/**
	 * delete pricelist-entries and price(s) specified by keys pl_id, pm_id and/or pl_validsince
	 *
	 * If the last price of a pricelist-entry gets deleted, the pricelist entry is automatic deleted too!
	 *
	 * @param array/int $keys array with keys pm_id, pl_id and/or pl_validsince to delete or integer pm_id
	 * @return int number of deleted prices
	 */
	function delete($keys)
	{
		//echo "<p>sopricelist::delete(".print_r($keys,true).",$touch_modified)</p>\n";
		if (!is_array($keys) && (int) $keys) $keys = array('pm_id' => (int) $keys);

		$keys = $this->data2db($keys);	// adjust the validsince timestampt to server-time

		$where = array();
		foreach(array('pm_id','pl_id','pl_validsince') as $key)
		{
			if (isset($keys[$key])) $where[$key] = $keys[$key];
		}
		if (!$where) return false;

		$this->db->delete($this->prices_table,$where,__LINE__,__FILE__);
		$deleted = $this->db->affected_rows();

		// check for pricelist items with no prices and delete them too
		$this->db->select($this->table_name,$this->table_name.'.pl_id',$this->prices_table.'.pl_id IS NULL',__LINE__,__FILE__,false,'',false,0,"LEFT JOIN $this->prices_table ON $this->table_name.pl_id=$this->prices_table.pl_id");
		$pl_ids = array();
		while($this->db->next_record())
		{
			$pl_ids[] = $this->db->f(0);
		}
		if ($pl_ids)
		{
			$this->db->delete($this->table_name,array('pl_id' => $pl_ids),__LINE__,__FILE__);
		}
		return $deleted;
	}
}
