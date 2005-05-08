<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.eGroupWare.org                                                *
	* Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/
	
	/* tables_baseline.inc.php,v 1.1 2005/05/08 08:00:03 ralfbecker Exp */


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
				'pm_planed_start' => array('type' => 'int','precision' => '8'),
				'pm_planed_end' => array('type' => 'int','precision' => '8'),
				'pm_real_start' => array('type' => 'int','precision' => '8'),
				'pm_real_end' => array('type' => 'int','precision' => '8'),
				'cat_id' => array('type' => 'int','precision' => '4','default' => '0'),
				'pm_access' => array('type' => 'varchar','precision' => '7','default' => 'public'),
				'pm_priority' => array('type' => 'int','precision' => '2','default' => '1'),
				'pm_status' => array('type' => 'varchar','precision' => '9','default' => 'active'),
				'pm_completion' => array('type' => 'int','precision' => '2','default' => '0'),
				'pm_coordinator' => array('type' => 'int','precision' => '4'),
				'pm_used_time' => array('type' => 'int','precision' => '4'),
				'pm_planed_time' => array('type' => 'int','precision' => '4'),
				'pm_used_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pm_planed_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pm_overwrite' => array('type' => 'int','precision' => '4','default' => '0')
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
				'pe_planed_time' => array('type' => 'int','precision' => '4'),
				'pe_used_time' => array('type' => 'int','precision' => '4'),
				'pe_planed_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pe_used_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pe_planed_start' => array('type' => 'int','precision' => '8'),
				'pe_real_start' => array('type' => 'int','precision' => '8'),
				'pe_planed_end' => array('type' => 'int','precision' => '8'),
				'pe_real_end' => array('type' => 'int','precision' => '8'),
				'pe_overwrite' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'pe_activity_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'pe_synced' => array('type' => 'int','precision' => '8'),
				'pe_modified' => array('type' => 'int','precision' => '8','nullable' => False),
				'pe_modifier' => array('type' => 'int','precision' => '4','nullable' => False),
				'pe_status' => array('type' => 'varchar','precision' => '8','nullable' => False,'default' => 'new'),
				'pe_cost_per_time' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'cat_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array('pm_id','pe_id'),
			'fk' => array(),
			'ix' => array(array('pm_id','pe_status')),
			'uc' => array()
		)
	);
