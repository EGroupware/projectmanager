<?php
/**************************************************************************\
* eGroupWare - ProjectManager - default records for new installations      *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

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
) as $role_id => $data)
{
	$GLOBALS['phpgw_setup']->oProc->insert('egw_pm_roles',$data,array('role_id'=>$role_id),__LINE__,__FILE__,'projectmanager');
}
