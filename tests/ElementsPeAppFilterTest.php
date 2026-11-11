<?php

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Regression test: projectmanager_elements_so::search()'s own "filter by linked app" handling
 * built a raw `$this->pe_app_sql.'='.$this->db->quote(...)` string and appended it to the filter
 * array at a bare (integer) key, unwrapped in Api\Db\SqlFragment::raw() - the same shape of bug
 * already found and fixed in projectmanager_so::search() and timesheet's Events::getPending()
 * this session. Live report: selecting a project (which loads its element list, filtered by
 * linked app) threw "integer array key 0 used for a raw SQL fragment...".
 */
class ElementsPeAppFilterTest extends Api\AppTest
{
	protected $pm_id;
	protected $elements = array();

	protected function setUp() : void
	{
		Link::run_notifies();

		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');

		$so = new \projectmanager_so();
		$stale = $so->read(array('pm_number' => 'PEAPP-FILTER-TEST'));
		if ($stale && $stale['pm_id'])
		{
			$bo->history = '';
			$bo->delete($stale['pm_id'], true);
		}

		$bo->save(array(
			'pm_number'      => 'PEAPP-FILTER-TEST',
			'pm_title'       => 'Auto-test for '.$this->name(),
			'pm_status'      => 'active',
			'pm_description' => 'Test project for '.$this->name(),
		), true, false);
		$this->pm_id = (int)$bo->data['pm_id'];
		$this->assertGreaterThan(0, $this->pm_id, 'Could not make test project');

		$infolog_bo = new \infolog_bo();
		$values = array(
			'info_subject' => 'Test infolog for #'.$this->pm_id,
			'info_status'  => 'open',
			'pm_id'        => $this->pm_id,
			'info_contact' => array('app' => 'projectmanager', 'id' => $this->pm_id),
		);
		$element_id = $infolog_bo->write($values, true, true, true, true);
		$this->elements[] = 'infolog:'.$element_id;

		Link::run_notifies();
	}

	protected function tearDown() : void
	{
		try
		{
			foreach ($this->elements as $id)
			{
				list(, $info_id) = explode(':', $id);
				$infolog_bo = new \infolog_bo();
				$infolog_bo->delete($info_id, true, false, true);
			}
			if ($this->pm_id)
			{
				$bo = new \projectmanager_bo();
				$this->mockTracking($bo, 'projectmanager_tracking');
				$bo->history = '';
				$bo->delete($this->pm_id, true);

				$so = new \projectmanager_so();
				if ($so->read($this->pm_id)) $so->delete($this->pm_id);
			}
		}
		finally
		{
			Link::run_notifies();
			unset($GLOBALS['projectmanager_bo']);
			unset($GLOBALS['projectmanager_elements_bo']);
		}
	}

	public function testSearchWithPeAppFilterDoesNotThrow()
	{
		$elements_bo = new \projectmanager_elements_bo($this->pm_id);
		$elements_bo->sync_all($this->pm_id);

		// pe_app travels via the $filter parameter (col_filter, in Nextmatch terms), not $criteria -
		// matches how projectmanager_elements_ui.inc.php actually calls this (query['col_filter'])
		$rows = $elements_bo->search('', false, '', '', '', false, 'AND', false, array(
			'pm_id'  => $this->pm_id,
			'pe_app' => 'infolog',
		));

		$this->assertIsArray($rows, 'search() with a pe_app filter must return rows, not throw');
		$this->assertNotEmpty($rows, 'the linked infolog element must be found');
	}
}
