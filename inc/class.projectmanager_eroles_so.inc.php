<?php
/**
 * ProjectManager - eRoles storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-11 by Christian Binder <christian-AT-jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_eroles_so.inc.php 26091 2008-10-07 17:57:50Z jaytraxx $
 */

/**
 * eRoles storage object of the projectmanager
 * eRoles - element roles define the role of an egroupware element when it gets merged with a document
 *
 * Tables: egw_pm_eroles
 */
class projectmanager_eroles_so extends so_sql
{	
	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 */
	function __construct()
	{
		parent::__construct('projectmanager','egw_pm_eroles');
	}

	/**
	 * reimplemented to set some defaults and order by 'pm_id DESC'
	 *
	 * @param string $value_col column-name for the values of the array, can also be an expression aliased with AS
	 * @param string $key_col='' column-name for the keys, default '' = same as $value_col: returns a distinct list
	 * @param array $filter=array() to filter the entries
	 * @param string $order='' order, default '' = same as $value_col
	 * @return array with key_col => value_col pairs
	 */
	function query_list($value_col='role_title',$key_col='role_id',$filter=array(),$order='pm_id DESC')
	{
		return parent::query_list($value_col,$key_col,$filter,$order);
	}
}
