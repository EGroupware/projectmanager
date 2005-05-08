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
	
	/* tables_update.inc.php,v 1.1 2005/05/08 08:00:03 ralfbecker Exp */

	$test[] = '0.1.008';
	function projectmanager_upgrade0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_projects','pm_planed_start','pm_planned_start');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_projects','pm_planed_end','pm_planned_end');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_projects','pm_planed_time','pm_planned_time');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_projects','pm_planed_budget','pm_planned_budget');

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.001';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.001';
	function projectmanager_upgrade0_2_001()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_elements','pe_planed_time','pe_planned_time');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_elements','pe_planed_budget','pe_planned_budget');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_elements','pe_planed_start','pe_planned_start');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_pm_elements','pe_planed_end','pe_planned_end');

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.002';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.002';
	function projectmanager_upgrade0_2_002()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('egw_pm_constrains',array(
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
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.003';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}

	$test[] = '0.2.003';
	function projectmanager_upgrade0_2_003()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('egw_pm_milestones',array(
			'fd' => array(
				'pm_id' => array('type' => 'int','precision' => '4'),
				'ms_id' => array('type' => 'int','precision' => '4'),
				'ms_date' => array('type' => 'int','precision' => '8','nullable' => False),
				'ms_title' => array('type' => 'varchar','precision' => '64'),
				'ms_description' => array('type' => 'text')
			),
			'pk' => array('pm_id','ms_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.004';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}
?>
