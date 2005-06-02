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
	
	/* tables_update.inc.php,v 1.7 2005/06/02 23:09:30 ralfbecker Exp */

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
				'ms_id' => array('type' => 'auto','nullable' => False),
				'pm_id' => array('type' => 'int','precision' => '4'),
				'ms_date' => array('type' => 'int','precision' => '8','nullable' => False),
				'ms_title' => array('type' => 'varchar','precision' => '255'),
				'ms_description' => array('type' => 'text')
			),
			'pk' => array('ms_id'),
			'fk' => array(),
			'ix' => array('pm_id'),
			'uc' => array()
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.004';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.004';
	function projectmanager_upgrade0_2_004()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameTable('egw_pm_constrains','egw_pm_constraints');

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.005';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.005';
	function projectmanager_upgrade0_2_005()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_pm_milestones','pm_id',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => False
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.006';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.006';
	function projectmanager_upgrade0_2_006()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('egw_pm_roles',array(
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
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.007';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.007';
	function projectmanager_upgrade0_2_007()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('egw_pm_members',array(
			'fd' => array(
				'pm_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'member_uid' => array('type' => 'int','precision' => '4','nullable' => False),
				'role_id' => array('type' => 'int','precision' => '4','default' => '0')
			),
			'pk' => array('pm_id','member_uid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.008';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.008';
	function projectmanager_upgrade0_2_008()
	{
		// adding some default roles
		foreach(array(
			1 => array(
				'role_title'       => 'Coordinator',
				'role_description' => 'full access',
				'role_acl'         => 0xffff),
			2 => array(
				'role_title'       => 'Accounting',
				'role_description' => 'edit access, incl. editing budget and elements',
				'role_acl'         => 1|2|4|64|128),
			3 => array(
				'role_title'       => 'Assistant',
				'role_description' => 'read access, incl. budget and adding elements',
				'role_acl'         => 1|2|64),
			4 => array(
				'role_title'       => 'Projectmember',
				'role_description' => 'read access, no budget',
				'role_acl'         => 1),
		) as $role_id => $data)
		{
			$GLOBALS['phpgw_setup']->oProc->insert('egw_pm_roles',$data,array('role_id'=>$role_id),__LINE__,__FILE__,'projectmanager');
		}
		// copying the existing coordinators to the new egw_pm_members table, before droping the column
		$GLOBALS['phpgw_setup']->db->select('egw_pm_projects','pm_id,pm_coordinator',false,__LINE__,__FILE__,false,'','projectmanager');
		while(($row = $GLOBALS['phpgw_setup']->db->row(true)))
		{
			if ($row['pm_coordinator'])
			{
				$GLOBALS['phpgw_setup']->oProc->insert('egw_pm_members',array(
					'pm_id'      => $row['pm_id'],
					'member_uid' => $row['pm_coordinator'],
					'role_id'    => 1,
				),false,__LINE__,__FILE__,'projectmanager');
			}
		}
		$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_pm_projects',array(
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
				'pm_used_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pm_planned_budget' => array('type' => 'decimal','precision' => '20','scale' => '2'),
				'pm_overwrite' => array('type' => 'int','precision' => '4','default' => '0')
			),
			'pk' => array('pm_id'),
			'fk' => array(),
			'ix' => array('pm_title'),
			'uc' => array('pm_number')
		),'pm_coordinator');

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.2.009';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.2.009';
	function projectmanager_upgrade0_2_009()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_pm_elements','pe_share',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_pm_elements','pe_health',array(
			'type' => 'int',
			'precision' => '2'
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.3.001';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}


	$test[] = '0.3.001';
	function projectmanager_upgrade0_3_001()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_pm_projects','pm_accounting_type',array(
			'type' => 'varchar',
			'precision' => '10',
			'default' => 'times'
		));

		$GLOBALS['setup_info']['projectmanager']['currentver'] = '0.3.002';
		return $GLOBALS['setup_info']['projectmanager']['currentver'];
	}
?>
