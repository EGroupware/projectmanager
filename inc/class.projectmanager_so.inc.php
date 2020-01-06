<?php
/**
 * ProjectManager - General (projects) storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Acl;

/**
 * General storage object of the projectmanager: access the main project data
 *
 * Tables: egw_pm_projects, egw_pm_extra, egw_pm_roles, egw_pm_members
 *
 * A project P is the parent of an other project C, if link_id1=P.pm_id and link_id2=C.pm_id !
 */
class projectmanager_so extends Api\Storage
{
	/**
	 * Table name 'egw_links'
	 *
	 * @var string
	 */
	var $links_table = Link\Storage::TABLE;
	/**
	 * Configuration data
	 *
	 * @var array
	 */
	var $config = array(
		'customfields' => array(),
	);
	/**
	 * Name of project-members table
	 *
	 * @var string
	 */
	var $members_table = 'egw_pm_members';
	/**
	 * Name of roles table
	 *
	 * @var string
	 */
	var $roles_table = 'egw_pm_roles';
	/**
	 * Join with the members and the roles table to get the role-ACL of the current user
	 *
	 * @var string
	 */
	var $acl_join;
	/**
	 * Extra column from the members table
	 *
	 * @var string
	 */
	var $acl_extracol='role_acl';
	/**
	 * ACL grants from other users
	 *
	 * @var array
	 */
	var $grants;
	var $read_grants,$private_grants;
	/**
	 * Current user ($GLOBALS['egw_info']['user']['account_id'])
	 *
	 * @var int
	 */
	var $user;

	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id id of the project to load, default null
	 */
	function __construct($pm_id=null)
	{
		parent::__construct('projectmanager','egw_pm_projects','egw_pm_extra','','pm_extra_name','pm_extra_value','pm_id');

		$this->config = Api\Config::read('projectmanager');
		if (!$this->config)
		{
			$this->config = array(
				'hours_per_workday' => 8,
				'duration_units' => array('h', 'd'),
				'accounting_types' => array('status', 'times', 'budget', 'pricelist'),
				'ID_GENERATION_FORMAT' => 'P-%Y-%04ix',
				'ID_GENERATION_FORMAT_SUB' => '%px/%04ix',
			);
		}
		$this->config['duration_format'] = (is_array($this->config['duration_units']) ? implode('',$this->config['duration_units']) : str_replace(',','',$this->config['duration_units'])).','.$this->config['hours_per_workday'];

		$this->grants = $GLOBALS['egw']->acl->get_grants('projectmanager');
		$this->user = (int) $GLOBALS['egw_info']['user']['account_id'];

		$this->read_grants = $this->private_grants = array();
		foreach($this->grants as $owner => $rights)
		{
			if ($rights) $this->read_grants[] = $owner;		// ANY ACL implies READ!

			if ($rights & Acl::PRIVAT) $this->private_grants[] = $owner;
		}
		if (($memberships = $GLOBALS['egw']->accounts->memberships($this->user, true)))
		{
			$member_groups_uid = ','.implode(',', $memberships);
		}
		$this->acl_join = "LEFT JOIN $this->members_table ON ($this->table_name.pm_id=$this->members_table.pm_id AND {$this->members_table}.member_uid IN ($this->user $member_groups_uid)) ".
			" LEFT JOIN $this->roles_table ON $this->members_table.role_id=$this->roles_table.role_id";

		if ($pm_id) $this->read($pm_id);
	}

	/**
	 * reads a project
	 *
	 * reimplemented to handle custom fields
	 */
	function read($keys, $extra_cols = '', $join = '')
	{
		//echo "<p>soprojectmanager::read(".print_r($keys,true).")</p>\n";

		if ($keys && is_numeric($keys) && $this->data['pm_id'] == $keys ||
			is_array($keys) && $keys['pm_id'] && $this->data['pm_id'] == $keys['pm_id'])
		{
			return $this->data;
		}
		if (!parent::read($keys, $extra_cols, $join))
		{
			return false;
		}
		// query project_members and their roles
/*
		foreach($this->db->select($this->members_table,'*',$this->members_table.'.pm_id='.(int)$this->data['pm_id'],__LINE__,__FILE__,
			False,'','projectmanager',0,"LEFT JOIN $this->roles_table ON $this->members_table.role_id=$this->roles_table.role_id") as $row)
		{
			$this->data['pm_members'][$row['member_uid']] = $row;
		}
*/
		$this->data['pm_members'] = $this->read_members($this->data['pm_id']);
		$this->data['role_acl'] = $this->data['pm_members'][$this->user]['role_acl'];

		return $this->data;
	}

	/**
	 * Read the projectmembers of one or more projects
	 *
	 * @param int/array $pm_id
	 * @return array with projectmembers
	 */
	function read_members($pm_id)
	{
		$members_table_def = $this->db->get_table_definitions('projectmanager',$this->members_table);
		$members = [];
		foreach($this->db->select($this->members_table,'*,'.$this->members_table.'.pm_id AS pm_id',
			$this->db->expression($members_table_def,$this->members_table.'.',array('pm_id'=>$pm_id)),__LINE__,__FILE__,
			False,'','projectmanager',0,"LEFT JOIN $this->roles_table ON $this->members_table.role_id=$this->roles_table.role_id") as $row)
		{
			$members[$row['pm_id']][$row['member_uid']] = $row;
		}
		return is_array($pm_id) ? $members : $members[$pm_id];
	}

	/**
	 * Delete the projectmembers of one or more projects
	 *
	 * @param int/array $pm_id
	 * @return int number of deleted projectmembers
	 */
	function delete_members($pm_id)
	{
		$this->db->delete($this->members_table,array('pm_id' => $pm_id),__LINE__,__FILE__,'projectmanager');

		return $this->db->affected_rows();
	}

	/**
	 * saves a project
	 *
	 * reimplemented to handle custom fields and set modification and creation data
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param int $check_modified =0 old modification date to check before update (include in WHERE)
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$check_modified=0)
	{
		//error_log(__METHOD__ . '('.print_r($keys,true).") this->data="); error_log(array2string($this->data));

		if (is_array($keys) && count($keys))
		{
			$this->data_merge($keys);
			$keys = null;
		}
		$where = array();
		if($check_modified)
		{
			$where['pm_modified'] = $check_modified;
		}
		$return = parent::save($keys, $where);
		if ($return == 0 && $this->data['pm_id'])
		{
			// project-members: first delete all, then save the (still) assigned ones
			$this->db->delete($this->members_table,array('pm_id' => $this->data['pm_id']),__LINE__,__FILE__,'projectmanager');
			foreach((array)$this->data['pm_members'] as $uid => $data)
			{
				$this->db->insert($this->members_table,array(
					'pm_id'      => $this->data['pm_id'],
					'member_uid' => $uid,
					'role_id'    => $data['role_id'],
					'member_availibility' => $data['member_availibility'],
				),false,__LINE__,__FILE__,'projectmanager');
			}
		}
		else if ($return === TRUE)
		{
			return TRUE;
		}

		return $this->db->Errno;
	}

	/**
	 * Overridden from parent to add in extra resources column
	 *
	 * @see so_sql_cf->get_rows()
	 */
	public function get_rows($query, &$rows, &$readonlys, $join = '', $need_full_no_count = false, $only_keys = false, $extra_cols = array())
	{
		// Only deal with resources if that column is selected
		$columsel = $this->prefs['nextmatch-projectmanager.list.rows'];
		$columselection = $columsel ? explode(',',$columsel) : array();
		if(!$columselection || in_array('resources', $columselection) || $query['col_filter']['resources'])
		{
			$extra_cols[] = $this->db->group_concat('resources.member_uid').' AS resources';
			$join .= ' LEFT JOIN egw_pm_members AS resources ON resources.pm_id = egw_pm_projects.pm_id ';

			if($query['col_filter']['resources'])
			{
				// Expend to include any qroups selected user(s) are in
				$members = array();
				foreach((array)$query['col_filter']['resources'] as $user)
				{
					$members = array_merge($members,(array)
						($user > 0 ? $GLOBALS['egw']->accounts->memberships($user,true) :
							$GLOBALS['egw']->accounts->members($user,true)));
					$members[] = $user;
				}
				if (is_array($members))
				{
					$members = array_unique($members);
				}
				$query['col_filter'][] = 'resources.member_uid IN ('.implode(', ',$members).' ) ';
			}
		}
		unset($query['col_filter']['resources']);

		$query['order'] = ' GROUP BY egw_pm_projects.pm_id ORDER BY '. $query['order'] ;
		return parent::get_rows($query, $rows, $readonlys, $join, $need_full_no_count,	$only_keys, $extra_cols);
	}

	/**
	 * search projects, re-implemented to include sub-cats and allow to filter for subs and mains
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
	 * @param string/boolean $join=true sql to do a join, added as is after the table-name, default true=add join for Acl
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true,$need_full_no_count=false)
	{
		// include sub-categories in the search
		if ($filter['cat_id'])
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = new Api\Categories();
			}
			$filter['cat_id'] = $GLOBALS['egw']->categories->return_all_children($filter['cat_id']);
		}
		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		if ($join !== false)	// add acl-join, to get role_acl of current user
		{
			$original_join = $join === true ? $this->acl_join : $join;
			$join = $join === true ? $this->acl_join : $join . ' ' . $this->acl_join;

			$extra_cols = array_merge($extra_cols,array(
				$this->table_name.'.pm_id AS pm_id',
			));
			if ($only_keys === true) $only_keys='';	// otherwise we use ambigues pm_id

			if (is_array($criteria) && isset($criteria['pm_id']))
			{
				$criteria[$this->table_name.'.pm_id'] = $criteria['pm_id'];
				unset($criteria['pm_id']);
			}
			if (isset($filter['pm_id']) && $filter['pm_id'])
			{
				$filter[$this->table_name.'.pm_id'] = $filter['pm_id'];
				unset($filter['pm_id']);
			}
			// include an ACL filter for read-access
			$filter[] = "(pm_access='anonym' OR pm_access='public' AND pm_creator IN (".implode(',',$this->read_grants).
				") OR pm_access='private' AND pm_creator IN (".implode(',',$this->private_grants).')'.
				' OR '.$this->acl_extracol.'!=0)';

			// only add role-acl column if we NOT already group by something (eg. stylite.links for PM groups by it's hash)
			if (stripos($order_by, 'GROUP BY') === false)
			{
				$order_by = 'GROUP BY '.$this->table_name.'.pm_id'.($order_by ? ' ORDER BY '.$order_by : '');
				$extra_cols[] = 'BIT_OR('.$this->acl_extracol.') AS '.$this->acl_extracol;
			}
		}

		if ($filter['subs_or_mains'])
		{
			$subs_mains_join = '';
			if ($filter['subs_or_mains'] == 'mains')
			{
				$filter[] = $this->links_table.'.link_id2 IS NULL';
				$subs_mains_join .= ' LEFT';
			}
			// PostgreSQL requires cast as link_idx is varchar and pm_id an integer
			$pm_id = $this->db->to_varchar($this->table_name.'.pm_id');
			$subs_mains_join .= " JOIN $this->links_table ON {$this->links_table}.link_app2='projectmanager' AND {$this->links_table}.link_app1='projectmanager' AND {$this->links_table}.link_id2=$pm_id";

			if (is_array($filter['subs_or_mains']) || is_numeric($filter['subs_or_mains']))	// sub-projects of given parent-projects
			{
				$subs_mains_join .= ' AND '.$this->db->expression($this->links_table,array($this->links_table.'.link_id1' => $filter['subs_or_mains']));
			}
			$join .= $subs_mains_join;
		}
		unset($filter['subs_or_mains']);

		// if extending class or instanciator set columns to search, convert string criteria to array
		if ($criteria && !is_array($criteria))
		{
			$search = $this->search2criteria($criteria,$wildcard,$op);
			$criteria = array($search);
		}
		if (!is_array($criteria))
		{
			$filter[] = $criteria;
		}
		else
		{
			$query = $this->parse_search($criteria, $wildcard, $empty, $op);
			if(is_string($query))
			{
				$filter[] = $query;
			}
			else if (is_array($query))
			{
				$filter = array_merge($filter, $query);
			}
		}

		// for non-mysql (specially PostgreSQL) use regular Api\Storage::search(), no further optimisation
		if (stripos($this->db->Type, 'mysql') === false)
		{
			// should we return (number or) children
			$join .= $this->check_add_children_join($extra_cols);

			return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
		}

		// from here on we use special optimisation for MariaDB/MySQL

		// run parent search logic on parameters to generate correct $filter and $join values to use in our sub-query
		$this->process_search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $op, $filter, $join);

		// Use this sub-query to speed things up
		$columns = [$this->table_name.'.pm_id'];
		$order = $this->fix_group_by_columns($order_by, $columns, $this->table_name, $this->autoinc_id);
		$sub = "SELECT DISTINCT ".implode(',', $columns)
				. " FROM {$this->table_name} "
				. $join
				. " WHERE " . $this->db->column_data_implode(' AND ', $filter, True, False) . ' '
				. $order;

		// Nesting and limiting the subquery prevents us getting the total in the normal way
		$total = $this->db->select($this->table_name,'COUNT(*)',array("pm_id IN ($sub)"),__LINE__,__FILE__,false,'',$this->app,0)->fetchColumn();

		$num_rows = 50;
		$offset = 0;
		if (is_array($start)) list($offset,$num_rows) = $start;
		if (!is_int($offset)) $offset = (int)$offset;
		if (!is_int($num_rows)) $num_rows = (int)$num_rows;
		if($start !== FALSE)
		{
			$limit = " LIMIT {$offset}, {$num_rows}";
		}
		$sql_filter = ["{$this->table_name}.pm_id IN (SELECT * FROM ($sub $limit) AS something)"];
		$start = false;

		// workaround for a bug in MariaDB 10.4.11 (and further versions until it's fixed)
		// https://jira.mariadb.org/browse/MDEV-21328
		if (version_compare($this->db->ServerInfo['version'], '10.4.11', '>='))
		{
			try {
				$this->db->query("SET optimizer_switch='split_materialized=off';");
			}
			catch(Api\Exception\Db $e) {
				// ignore exception
				_egw_log_exception($e);
			}
		}

		// Need subs for something
		if ($subs_mains_join && stripos($only_keys, 'egw_links') !== false)
		{
			$columns = [$this->table_name.'.pm_id'];
			$order = $this->fix_group_by_columns($order_by, $columns, $this->table_name, $this->autoinc_id);
			$sub = "SELECT DISTINCT ".implode(',', $columns)
					. " FROM {$this->table_name} "
					. $join
					. " WHERE " . $this->db->column_data_implode(' AND ', $filter, True, False) . ' '
					. $order;

			// Nesting and limiting the subquery prevents us getting the total in the normal way
			$total = $this->db->select($this->table_name,'COUNT(*)',array("pm_id IN ($sub)"),__LINE__,__FILE__,false,'',$this->app,0)->fetchColumn();

			$num_rows = 50;
			$offset = 0;
			$limit = '';
			if (is_array($start)) list($offset,$num_rows) = $start;
			if($start !== FALSE)
			{
				$limit = " LIMIT {$offset}, {$num_rows}";
			}

			// MariaDB guys say this works after v10.3.20
			if (stripos($this->db->Type, 'mysql') !== FALSE && version_compare($this->db->ServerInfo['version'], '10.3.20') >= 0)
			{
				$sql_filter = ["{$this->table_name}.pm_id IN (SELECT * FROM ($sub $limit) AS something)"];
			}
			// and this works before
			else
			{
				$sql_filter = ["{$this->table_name}.pm_id IN ($sub )"];
			}

			// Need subs for something
			if ($subs_mains_join && stripos($only_keys, 'egw_links') !== false)
			{
				$original_join .= $subs_mains_join;
			}
		}
		else if ($subs_mains_join)
		{
			$original_join = $join;
			$sql_filter = $filter;
		}
		// should we return (number or) children
		$original_join .= $this->check_add_children_join($extra_cols);

		$result = Api\Storage\Base::search(array(),$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$sql_filter,$original_join,$need_full_no_count);
		$this->total = $total;
		return $result;
	}

	/**
	 * Get join for children colums, if necessary
	 *
	 * @param array& $extra_cols
	 * @return string
	 */
	private function check_add_children_join(&$extra_cols)
	{
		// should we return (number or) children
		if ($extra_cols && ($key=array_search('children', $extra_cols)) !== false)
		{
			// for performance reasons we dont check ACL here, as tree deals well with no children returned later
			$extra_cols[$key] = 'COUNT(children.link_id2) AS children';

			return " LEFT JOIN egw_links AS children ON (children.link_app1='projectmanager'
				AND children.link_app2='projectmanager'
				AND children.link_id1=".$this->db->to_varchar('egw_pm_projects.pm_id').')';
		}
		return '';
	}

	/**
	 * reimplemented to set some defaults and cope with ambigues pm_id column
	 *
	 * @param string $value_col='pm_title' column-name for the values of the array, can also be an expression aliased with AS
	 * @param string $key_col='pm_id' column-name for the keys, default '' = same as $value_col: returns a distinct list
	 * @param array $filter=array() to filter the entries
	 * @return array with key_col => value_col pairs, ordered by value_col
	 */
	function query_list($value_col='pm_title',$key_col='pm_id',$filter=array('pm_status'=>'active'), $order = '')
	{
		if ($key_col == 'pm_id') $key_col = $this->table_name.'.pm_id AS pm_id';

		return parent::query_list($value_col,$key_col,$filter,$order);
	}

	/**
	 * read the general availebility of one or all users
	 *
	 * A not set availibility is by default 100%
	 *
	 * @param int $uid user-id or 0 to read all
	 * @return array uid => availibility
	 */
	function get_availibility($uid=0)
	{
		$where = array('pm_id' => 0);

		if ($uid) $where['member_uid'] = $uid;

		$avails = array();
		foreach($this->db->select($this->members_table,'member_uid,member_availibility',$where,__LINE__,__FILE__,false,'','projectmanager') as $row)
		{
			$avails[$row['member_uid']] = empty($row['member_availibility']) ? 100.0 : $row['member_availibility'];
		}
		return $avails;
	}

	/**
	 * set or delete the general availibility of a user
	 *
	 * A not set availibility is by default 100%
	 *
	 * @param int $uid user-id
	 * @param float $availibility =null percentage to set or nothing to delete the avail. for the user
	 */
	function set_availibility($uid,$availibility=null)
	{
		if (!is_numeric($uid)) return;

		if (!is_null($availibility) && !empty($availibility) && $availibility != 100.0)
		{
			$this->db->insert($this->members_table,array(
				'member_availibility' => $availibility
			),array(
				'member_uid' => $uid,
				'pm_id'      => 0,
			),__LINE__,__FILE__,'projectmanager');
		}
		else
		{
			$this->db->delete($this->members_table,array(
				'pm_id' => 0,
				'member_uid' => $uid,
			),__LINE__,__FILE__,'projectmanager');
		}
	}


	/**
	 * Query infolog for users with open entries, either own or responsible, with start or end within 4 days
	 *
	 * This functions tries to minimize the users really checked with the complete filters, as creating a
	 * user enviroment and running the specific check costs ...
	 *
	 * @return array with acount_id's groups get resolved to their memebers
	 */
	function users_with_open_entries()
	{
		$users = array();

		foreach($this->db->select($this->table_name,'DISTINCT pm_creator',array(
			'pm_status' => 'active',
			'(ABS(pm_real_start-'.time().')<'.(4*24*60*60).' OR '.	// start within 4 days
			'ABS(pm_planned_start-'.time().')<'.(4*24*60*60).' OR '.	// planned start within 4 days
			'ABS(pm_real_end-'.time().')<'.(4*24*60*60).' OR '.		// end_day within 4 days
			'ABS(pm_planned_end-'.time().')<'.(4*24*60*60).')',		// end_day within 4 days
		),__LINE__,__FILE__) as $row)
		{
			$users[] = $row['pm_creator'];
		}
		foreach($this->db->select($this->members_table, "DISTINCT $this->members_table.member_uid AS member_uid",
			array(), __LINE__, __FILE__, false, '', 'projectmanager', 0,
			"JOIN $this->table_name ON $this->table_name.pm_id=$this->members_table.pm_id AND pm_status = 'active'") as $row)
		{
			$responsible = $row['member_uid'];

			if ($GLOBALS['egw']->accounts->get_type($responsible) == 'g')
			{
				$responsible = $GLOBALS['egw']->accounts->members($responsible,true);
			}
			if ($responsible)
			{
				foreach((array)$responsible as $user)
				{
					if ($user && !in_array($user,$users)) $users[] = $user;
				}
			}
		}
		return $users;
	}
}
