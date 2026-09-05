<?php

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Regression test for the "51st element missing" bug: when a project has more
 * linked elements than fit on one nextmatch page, the synthetic "project itself"
 * row that projectmanager_elements_ui::get_rrows() prepends to the first page
 * used to eat one slot of the page without shrinking the DB fetch window or
 * shifting the offset of later pages, so exactly one real element silently
 * disappeared between page 1 and page 2 (help.egroupware.org topic 79952).
 */
class ElementsPaginationTest extends Api\AppTest
{
	protected $pm_id;

	// element-ids ('infolog:123') created for this test, so we can clean them up
	protected $elements = array();

	const NUM_ELEMENTS = 55;
	const PAGE_SIZE = 50;

	protected function setUp() : void
	{
		Link::run_notifies();

		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');

		$so = new \projectmanager_so();
		$stale = $so->read(array('pm_number' => 'PAGINATION-TEST'));
		if ($stale && $stale['pm_id'])
		{
			$bo->history = '';
			$bo->delete($stale['pm_id'], true);
		}

		$bo->save(array(
			'pm_number'      => 'PAGINATION-TEST',
			'pm_title'       => 'Auto-test for '.$this->name(),
			'pm_status'      => 'active',
			'pm_description' => 'Test project for '.$this->name(),
		), true, false);
		$this->pm_id = $bo->data['pm_id'];
		$this->assertGreaterThan(0, (int)$this->pm_id, 'Could not make test project');

		$infolog_bo = new \infolog_bo();
		for ($n = 0; $n < self::NUM_ELEMENTS; $n++)
		{
			$values = array(
				'info_subject' => sprintf('Test infolog #%02d for #%s', $n, $this->pm_id),
				'info_des'     => 'Test element as part of the project for test '.$this->name(),
				'info_status'  => 'open',
				'pm_id'        => $this->pm_id,
				'info_contact' => array('app' => 'projectmanager', 'id' => $this->pm_id),
			);
			$element_id = $infolog_bo->write($values, true, true, true, true);
			$this->elements[] = 'infolog:'.$element_id;
		}

		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		$elements_bo = new \projectmanager_elements_bo($this->pm_id);
		$elements_bo->sync_all($this->pm_id);

		$this->assertEquals(self::NUM_ELEMENTS, count((array)$elements_bo->search(array('pm_id' => $this->pm_id), false)),
			'Unable to create all project elements');
	}

	protected function tearDown() : void
	{
		try
		{
			foreach ($this->elements as $id)
			{
				list(, $info_id) = explode(':', $id);
				$infolog_bo = new \infolog_bo();
				// Delete twice to make sure it's really gone (history setting)
				$infolog_bo->delete($info_id, true, false, true);
				$infolog_bo->delete($info_id, true, false, true);
			}

			if ($this->pm_id)
			{
				$bo = new \projectmanager_bo();
				$this->mockTracking($bo, 'projectmanager_tracking');
				$bo->history = '';
				$bo->delete($this->pm_id, true);

				$so = new \projectmanager_so();
				if ($so->read($this->pm_id))
				{
					$so->delete($this->pm_id);
				}
			}
		}
		finally
		{
			unset($GLOBALS['projectmanager_bo']);
			unset($GLOBALS['projectmanager_elements_bo']);
		}
	}

	/**
	 * Fetch all pages of the projectmanager elements list (as the nextmatch
	 * widget would) and make sure every real element shows up exactly once,
	 * with the synthetic "project itself" row only on the first page.
	 */
	public function testNoElementLostAcrossPages()
	{
		// projectmanager_elements_ui reads pm_id from $_GET when there's no pm_id in $_REQUEST
		$_GET['pm_id'] = $this->pm_id;
		unset($_REQUEST['pm_id']);

		$ui = new \projectmanager_elements_ui();

		$seen_infolog_ids = array();
		$self_row_pages = array();
		$totals = array();

		for ($start = 0, $page = 0; $page < 3; $start += self::PAGE_SIZE, $page++)
		{
			$query = array(
				'start'      => $start,
				'num_rows'   => self::PAGE_SIZE,
				'filter'     => 'all',
				'filter2'    => 0,
				'col_filter' => array('pm_id' => $this->pm_id),
				'order'      => 'pe_modified',
				'sort'       => 'DESC',
			);
			$rows = $readonlys = array();
			$total = $ui->get_rrows($query, $rows, $readonlys);
			$totals[] = $total;

			foreach ($rows as $key => $row)
			{
				// get_rrows() also stuffs non-row widget flags (no_*, total_*) into $rows keyed
				// by name rather than by index - skip those, only real rows have integer keys.
				if (!is_int($key) || !is_array($row))
				{
					continue;
				}
				if ($row['pe_app'] === 'projectmanager' && (int)$row['pe_app_id'] === (int)$this->pm_id)
				{
					$self_row_pages[] = $page;
					continue;
				}
				if ($row['pe_app'] === 'infolog')
				{
					$seen_infolog_ids[$row['pe_app_id']] = true;
				}
			}

			// Stop once we've paged past the end
			if (count($rows) === 0)
			{
				break;
			}
		}

		$this->assertEquals(array(0), array_values(array_unique($self_row_pages)),
			'The synthetic "project itself" row must appear exactly once, on the first page');

		$this->assertCount(self::NUM_ELEMENTS, $seen_infolog_ids,
			'Every linked infolog must appear exactly once across all pages - none may be silently dropped '.
			'(this is the "51st element missing" regression, help.egroupware.org topic 79952)');

		foreach ($this->elements as $id)
		{
			list(, $info_id) = explode(':', $id);
			$this->assertArrayHasKey((int)$info_id, $seen_infolog_ids, "Infolog $info_id was not found on any page");
		}

		$this->assertEquals(self::NUM_ELEMENTS + 1, reset($totals),
			'Reported total must include all real elements plus the synthetic project row');
		$this->assertEquals($totals[0], end($totals), 'Total must be stable across pages');
	}
}
