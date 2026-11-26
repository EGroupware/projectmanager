<?php

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Conditions Projectmanager builds as SQL itself have to be an Api\Db\SqlFragment, or the database
 * layer refuses them with "integer array key N used for a raw SQL fragment ...".
 *
 * Covers the places doing so outside the lists: the Gantt chart, the element list adding up the
 * timesheets of an InfoLog shown in the same project, and the date ranges of both CSV exports.
 */
class RawSqlConditionsTest extends Api\AppTest
{
	const PM_NUMBER = 'RAW-SQL-CONDITIONS-TEST';
	/**
	 * Planned start of the test project and its element: nothing else is planned that far ahead
	 */
	const PLANNED_START = '2099-03-03 10:00:00';

	protected $pm_id;
	protected $info_id;
	protected $ts_id;

	protected function setUp() : void
	{
		Link::run_notifies();

		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');

		$so = new \projectmanager_so();
		$stale = $so->read(array('pm_number' => self::PM_NUMBER));
		if ($stale && $stale['pm_id'])
		{
			$bo->history = '';
			$bo->delete($stale['pm_id'], true);
		}

		$start = (new Api\DateTime(self::PLANNED_START))->format('ts');
		$bo->save(array(
			'pm_number'        => self::PM_NUMBER,
			'pm_title'         => 'Auto-test for '.$this->name(),
			'pm_status'        => 'active',
			'pm_description'   => 'Test project for '.$this->name(),
			'pm_planned_start' => $start,
			'pm_planned_end'   => $start+7200,
		), true, false);
		$this->pm_id = (int)$bo->data['pm_id'];
		$this->assertGreaterThan(0, $this->pm_id, 'Could not make test project');

		$infolog_bo = new \infolog_bo();
		$values = array(
			'info_subject'   => 'Test infolog for #'.$this->pm_id,
			'info_status'    => 'ongoing',
			'info_percent'   => 50,
			'info_startdate' => $start,
			'info_enddate'   => $start+3600,
			'pm_id'          => $this->pm_id,
			'info_contact'   => array('app' => 'projectmanager', 'id' => $this->pm_id),
		);
		$this->info_id = $infolog_bo->write($values, true, true, true, true);
		$this->assertNotEmpty($this->info_id, 'Could not make test infolog');

		Link::run_notifies();
	}

	protected function tearDown() : void
	{
		try
		{
			if ($this->ts_id)
			{
				Link::unlink(0, 'timesheet', $this->ts_id);
				(new Api\Storage\Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));
			}
			if ($this->info_id)
			{
				$infolog_bo = new \infolog_bo();
				$infolog_bo->delete($this->info_id, true, false, true);
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
			unset($_GET['pm_id'], $_REQUEST['pm_id']);
		}
	}

	/**
	 * Book a timesheet on the InfoLog of the project, linked to both as the timesheet UI does
	 */
	protected function addTimesheet() : void
	{
		$so = new Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'Test timesheet for #'.$this->pm_id,
			'ts_start'    => time(),
			'ts_duration' => 90,
			'ts_quantity' => 1.5,
			'ts_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		);
		$this->assertEquals(0, $so->save(), 'Could not make test timesheet');
		$this->ts_id = (int)$so->data['ts_id'];

		Link::link('timesheet', $this->ts_id, 'projectmanager', $this->pm_id);
		Link::link('timesheet', $this->ts_id, 'infolog', $this->info_id);
		Link::run_notifies();
	}

	/**
	 * The Gantt chart reads the elements of a project without the ignored ones, limited to the
	 * shown time and, for the "ongoing" filter, to the not yet completed ones.
	 */
	public function testGanttElementsDoNotThrow()
	{
		$elements_bo = new \projectmanager_elements_bo($this->pm_id);
		$elements_bo->sync_all($this->pm_id);

		$_GET['pm_id'] = $this->pm_id;
		$gantt = new \projectmanager_gantt();
		$add_elements = new \ReflectionMethod($gantt, 'add_elements');

		$start = (new Api\DateTime(self::PLANNED_START))->format('ts');
		$data = array('data' => array(), 'links' => array());
		$elements = $add_elements->invokeArgs($gantt, array(&$data, $this->pm_id, array(
			'planned_times' => true,
			'start'  => $start-86400,
			'end'    => $start+86400,
			'filter' => 'ongoing',
			'depth'  => 2,
			'level'  => 1,
			'parent' => $this->pm_id,
		)));

		$this->assertIsArray($elements);
		$this->assertContains('infolog:'.$this->info_id, array_map(static function($element)
		{
			return $element['pe_app'].':'.$element['pe_app_id'];
		}, $elements), 'the InfoLog of the project is in the chart');
	}

	/**
	 * A timesheet booked on an InfoLog of the same project is not listed on its own: the element
	 * list reads it separately, and adds its time to the InfoLog.
	 */
	public function testElementsCumulatingTimesheetsDoNotThrow()
	{
		$this->addTimesheet();

		$elements_bo = new \projectmanager_elements_bo($this->pm_id);
		$elements_bo->sync_all($this->pm_id);

		$rows = $elements_bo->search('', false, '', '', '', false, 'AND', false, array(
			'pm_id'    => $this->pm_id,
			'cumulate' => true,
		));

		$this->assertIsArray($rows);
		$apps = array_column($rows, 'pe_used_time', 'pe_app');
		$this->assertArrayHasKey('infolog', $apps, 'the InfoLog is listed');
		$this->assertArrayNotHasKey('timesheet', $apps, 'the timesheet is not listed on its own');
		$this->assertEquals(90, $apps['infolog'], 'the time of the timesheet is added to the InfoLog');
	}

	protected function export(string $plugin, array $plugin_options, array $filter) : \importexport_export_csv
	{
		$definition = new \importexport_definition();
		$definition->set_record(array(
			'name'           => 'phpunit_'.$plugin.'_range',
			'application'    => 'projectmanager',
			'plugin'         => $plugin,
			'type'           => 'export',
			'plugin_options' => $plugin_options+array('selection' => 'filter'),
			'filter'         => $filter,
		));
		$stream = fopen('php://memory', 'w+');
		try
		{
			return (new $plugin())->export($stream, $definition);
		}
		finally
		{
			fclose($stream);
		}
	}

	/**
	 * Exporting projects with a date range as filter
	 */
	public function testProjectExportWithDateRange()
	{
		$start = (new Api\DateTime(self::PLANNED_START))->format('ts');
		$options = array('mapping' => array('pm_number' => 'Number'));

		$result = $this->export('projectmanager_export_projects_csv', $options, array(
			'pm_planned_start' => array('from' => $start-3600, 'to' => $start+3600),
		));
		$this->assertSame(1, $result->get_num_of_records(), 'only the test project is planned to start in that range');

		$result = $this->export('projectmanager_export_projects_csv', $options, array(
			'pm_planned_start' => array('from' => $start+3600, 'to' => $start+7200),
		));
		$this->assertSame(0, $result->get_num_of_records(), 'no project is planned to start in that range');
	}

	/**
	 * A filter field not being the name of a column is ignored, not made part of the query
	 */
	public function testProjectExportIgnoresInvalidRangeField()
	{
		$start = (new Api\DateTime(self::PLANNED_START))->format('ts');

		$result = $this->export('projectmanager_export_projects_csv', array('mapping' => array('pm_number' => 'Number')), array(
			'pm_planned_start' => array('from' => $start+3600, 'to' => $start+7200),
			'pm_id) OR 1=1-- -' => array('from' => 1, 'to' => 1),
		));
		$this->assertSame(0, $result->get_num_of_records());
	}

	/**
	 * Exporting the elements of a project with a date range as filter
	 */
	public function testElementExportWithDateRange()
	{
		$elements_bo = new \projectmanager_elements_bo($this->pm_id);
		$elements_bo->sync_all($this->pm_id);

		$start = (new Api\DateTime(self::PLANNED_START))->format('ts');
		$options = array('pm_id' => $this->pm_id, 'mapping' => array('pe_title' => 'Title'));

		$result = $this->export('projectmanager_export_elements_csv', $options, array(
			'pe_planned_start' => array('from' => $start-3600, 'to' => $start+3600),
		));
		$this->assertSame(1, $result->get_num_of_records(), 'the InfoLog is planned to start in that range');

		$result = $this->export('projectmanager_export_elements_csv', $options, array(
			'pe_planned_start' => array('from' => $start+3600, 'to' => $start+7200),
			'pe_id) OR 1=1-- -' => array('from' => 1, 'to' => 1),
		));
		$this->assertSame(0, $result->get_num_of_records(), 'nothing is planned to start in that range');
	}
}
