<?php
/**
 * ProjectManager - current table definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @subpackage setup
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_pm_projects' => array(
		'fd' => array(
			'pm_id' => array('type' => 'auto','nullable' => False),
			'pm_number' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'pm_title' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'pm_description' => array('type' => 'text','default' => ''),
			'pm_creator' => array('type' => 'int','precision' => '4','nullable' => False),
			'pm_created' => array('type' => 'int','precision' => '8','nullable' => False),
			'pm_modifier' => array('type' => 'int','precision' => '4'),
			'pm_modified' => array('type' => 'int','precision' => '8'),
			'pm_planned_start' => array('type' => 'int','precision' => '8'),
			'pm_planned_end' => array('type' => 'int','precision' => '8'),
			'pm_real_start' => array('type' => 'int','precision' => '8'),
			'pm_real_end' => array('type' => 'int','precision' => '8'),
			'cat_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'pm_access' => array('type' => 'varchar','precision' => '7','default' => 'public'),
			'pm_priority' => array('type' => 'int','precision' => '2','default' => '1'),
			'pm_status' => array('type' => 'varchar','precision' => '9','default' => 'active'),
			'pm_completion' => array('type' => 'int','precision' => '2','default' => '0'),
			'pm_used_time' => array('type' => 'int','precision' => '4'),
			'pm_planned_time' => array('type' => 'int','precision' => '4'),
			'pm_replanned_time' => array('type' => 'int','precision' => '4'),
			'pm_used_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
			'pm_planned_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
			'pm_overwrite' => array('type' => 'int','precision' => '4','default' => '0'),
			'pm_accounting_type' => array('type' => 'varchar','precision' => '10','default' => 'times')
		),
		'pk' => array('pm_id'),
		'fk' => array(),
		'ix' => array('pm_title'),
		'uc' => array('pm_number')
	),
	'egw_pm_extra' => array(
		'fd' => array(
			'pm_id' => array('type' => 'int','precision' => '4'),
			'pm_extra_name' => array('type' => 'varchar','precision' => '40'),
			'pm_extra_value' => array('type' => 'text')
		),
		'pk' => array('pm_id','pm_extra_name'),
		'fk' => array('pm_id' => array('egw_pm_projects' => 'pm_id')),
		'ix' => array(),
		'uc' => array()
	),
	'egw_pm_elements' => array(
		'fd' => array(
			'pm_id' => array('type' => 'int','precision' => '4'),
			'pe_id' => array('type' => 'int','precision' => '4'),
			'pe_title' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'pe_completion' => array('type' => 'int','precision' => '2'),
			'pe_planned_time' => array('type' => 'int','precision' => '4'),
			'pe_replanned_time' => array('type' => 'int','precision' => '4'),
			'pe_used_time' => array('type' => 'int','precision' => '4'),
			'pe_planned_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
			'pe_used_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
			'pe_planned_start' => array('type' => 'int','precision' => '8'),
			'pe_real_start' => array('type' => 'int','precision' => '8'),
			'pe_planned_end' => array('type' => 'int','precision' => '8'),
			'pe_real_end' => array('type' => 'int','precision' => '8'),
			'pe_overwrite' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'pl_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'pe_synced' => array('type' => 'int','precision' => '8'),
			'pe_modified' => array('type' => 'int','precision' => '8','nullable' => False),
			'pe_modifier' => array('type' => 'int','precision' => '4','nullable' => False),
			'pe_status' => array('type' => 'varchar','precision' => '8','nullable' => False,'default' => 'new'),
			'pe_unitprice' => array('type' => 'decimal','precision' => '20','scale' => '2'),
			'cat_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'pe_share' => array('type' => 'int','precision' => '4'),
			'pe_health' => array('type' => 'int','precision' => '2'),
			'pe_resources' => array('type' => 'varchar','precision' => '255'),
			'pe_details' => array('type' => 'text'),
			'pe_planned_quantity' => array('type' => 'float','precision' => '8'),
			'pe_used_quantity' => array('type' => 'float','precision' => '8'),
			'pe_eroles' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('pm_id','pe_id'),
		'fk' => array(),
		'ix' => array(array('pm_id','pe_status')),
		'uc' => array()
	),
	'egw_pm_constraints' => array(
		'fd' => array(
			'pm_id' => array('type' => 'int','precision' => '4'),
			'pe_id_end' => array('type' => 'int','precision' => '4'),
			'pe_id_start' => array('type' => 'int','precision' => '4'),
			'ms_id' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('pm_id','pe_id_end','pe_id_start','ms_id'),
		'fk' => array(),
		'ix' => array(array('pm_id','pe_id_start'),array('pm_id','ms_id')),
		'uc' => array()
	),
	'egw_pm_milestones' => array(
		'fd' => array(
			'ms_id' => array('type' => 'auto','nullable' => False),
			'pm_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'ms_date' => array('type' => 'int','precision' => '8','nullable' => False),
			'ms_title' => array('type' => 'varchar','precision' => '255'),
			'ms_description' => array('type' => 'text')
		),
		'pk' => array('ms_id'),
		'fk' => array(),
		'ix' => array('pm_id'),
		'uc' => array()
	),
	'egw_pm_roles' => array(
		'fd' => array(
			'role_id' => array('type' => 'auto','nullable' => False),
			'pm_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'role_title' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'role_description' => array('type' => 'varchar','precision' => '255'),
			'role_acl' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('role_id'),
		'fk' => array(),
		'ix' => array('pm_id'),
		'uc' => array()
	),
	'egw_pm_members' => array(
		'fd' => array(
			'pm_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'member_uid' => array('type' => 'int','precision' => '4','nullable' => False),
			'role_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'member_availibility' => array('type' => 'float','precision' => '4','default' => '100.0')
		),
		'pk' => array('pm_id','member_uid'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_pm_pricelist' => array(
		'fd' => array(
			'pl_id' => array('type' => 'auto','nullable' => False),
			'pl_title' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'pl_description' => array('type' => 'text','default' => ''),
			'cat_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'pl_unit' => array('type' => 'varchar','precision' => '20','nullable' => False)
		),
		'pk' => array('pl_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_pm_prices' => array(
		'fd' => array(
			'pm_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'pl_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'pl_validsince' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'pl_price' => array('type' => 'float','precision' => '8'),
			'pl_modifier' => array('type' => 'int','precision' => '4','nullable' => False),
			'pl_modified' => array('type' => 'int','precision' => '8','nullable' => False),
			'pl_customertitle' => array('type' => 'varchar','precision' => '255'),
			'pl_billable' => array('type' => 'int','precision' => '2','default' => '1')
		),
		'pk' => array('pm_id','pl_id','pl_validsince'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_pm_eroles' => array(
		'fd' => array(
			'role_id' => array('type' => 'auto','nullable' => False),
			'pm_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'role_title' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'role_description' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('role_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
