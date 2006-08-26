<?php
/**
 * ProjectManager - Roles storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/**
 * Roles storage object of the projectmanager
 *
 * Tables: egw_pm_roles
 */
class soroles extends so_sql
{
	/**
	 * Constructor, calls the constructor of the extended class
	 * 
	 * @param int $pm_id pm_id of the project to use, default null
	 */
	function soroles($pm_id=null)
	{
		$this->so_sql('projectmanager','egw_pm_roles');

		if ((int) $pm_id) 
		{
			$this->pm_id = (int) $pm_id;
		}
	}
	
	/**
	 * reimplemented to set some defaults and order by 'pm_id DESC,role_acl DESC'
	 *
	 * @param string $value_col column-name for the values of the array, can also be an expression aliased with AS
	 * @param string $key_col='' column-name for the keys, default '' = same as $value_col: returns a distinct list
	 * @param array $filter=array() to filter the entries
	 * @param string $order='' order, default '' = same as $value_col
	 * @return array with key_col => value_col pairs
	 */
	function query_list($value_col='role_title',$key_col='role_id',$filter=array(),$order='pm_id DESC,role_acl DESC')
	{
		return parent::query_list($value_col,$key_col,$filter,$order);
	}
}