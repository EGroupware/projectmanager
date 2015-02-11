<?php
/**
 * ProjectManager - default records for new installs
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @subpackage setup
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// adding some default roles
foreach(array(
	1 => array(
		'role_title'       => 'Coordinator',
		'role_description' => 'full access',
		'role_acl'         => 0xffff),
	2 => array(
		'role_title'       => 'Accounting',
		'role_description' => 'edit access, incl. editing budget and elements',
		'role_acl'         => 1|2|4|64|128),	// READ, ADD, EDIT, BUDGET, EDIT_BUDGET
	3 => array(
		'role_title'       => 'Assistant',
		'role_description' => 'read access, incl. budget and adding elements',
		'role_acl'         => 1|2|64),			// READ, ADD, BUDGET
	4 => array(
		'role_title'       => 'Projectmember',
		'role_description' => 'read access, no budget',
		'role_acl'         => 1),				// READ
	5 => array(
		'role_title'       => 'External',
		'role_description' => 'Add timesheet only',
		'role_acl'         => 256),				// ADD_TIMESHEET
) as $role_id => $data)
{
	$GLOBALS['egw_setup']->oProc->insert('egw_pm_roles',$data,array('role_id'=>$role_id),__LINE__,__FILE__,'projectmanager');
}
