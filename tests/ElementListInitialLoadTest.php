<?php

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Stands in for the Etemplate projectmanager_elements_ui::index() renders, so a test can look at
 * the content it was about to send.
 *
 * Deliberately not an Api\Etemplate subclass: extending it makes the autoloader pull in
 * Etemplate\Widget\Template while this file is being loaded, whose scanForWidgets() wants an
 * EGroupware environment that setUpBeforeClass() has not built yet.  projectmanager_elements_ui
 * types its $tpl as nothing at all, so any object with the three methods index() calls will do.
 */
class CapturedElementTemplate
{
	public $content;

	public function read($name)
	{
		unset($name);
		return true;
	}

	public function setElementAttribute($name, $attribute, $value)
	{
		unset($name, $attribute, $value);
	}

	public function exec($method, array $content, ?array $sel_options=null, ?array $readonlys=null)
	{
		unset($method, $sel_options, $readonlys);
		$this->content = $content;
	}
}

/**
 * What the element list is given on a page load.
 *
 * Two things it must not do, both of which it used to do on every load but the first of a
 * session - index() restores its whole nextmatch value from a session cache that
 * get_rrows() fills with the client's last query:
 *
 * - ship rows.  The app starts on the project list with the element list hidden, so a page of
 *   elements fetched here is work nobody asked for, and is thrown away again as soon as the
 *   user picks a project and the filter changes.  The restored num_rows silently undid the
 *   "no data when first sent" that the fresh value sets.
 * - leave the project out of the filters.  Without it get_rrows() re-derives the project from
 *   the current_project preference on every request, so the client never knows which project
 *   its own list is showing - which is what a pushed element has to be matched against, and
 *   the reason a filter can not simply be changed to show a different project.
 */
class ElementListInitialLoadTest extends Api\AppTest
{
	protected $pm_id;

	protected function setUp() : void
	{
		Link::run_notifies();

		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');

		$so = new \projectmanager_so();
		$stale = $so->read(array('pm_number' => 'INITIAL-LOAD-TEST'));
		if ($stale && $stale['pm_id'])
		{
			$bo->history = '';
			$bo->delete($stale['pm_id'], true);
		}

		$bo->save(array(
			'pm_number' => 'INITIAL-LOAD-TEST',
			'pm_title'  => 'Auto-test for '.$this->name(),
			'pm_status' => 'active',
		), true, false);
		$this->pm_id = (int)$bo->data['pm_id'];
		$this->assertGreaterThan(0, $this->pm_id, 'Could not make test project');

		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $this->pm_id;
		Api\Cache::unsetSession('projectmanager', 'projectelements_list');
	}

	protected function tearDown() : void
	{
		Api\Cache::unsetSession('projectmanager', 'projectelements_list');
		try
		{
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

	/**
	 * Run index() and hand back the nextmatch value it would have sent
	 *
	 * @return array
	 */
	protected function initialNextmatchValue() : array
	{
		$_GET['pm_id'] = $_REQUEST['pm_id'] = $this->pm_id;

		$ui = new \projectmanager_elements_ui();
		$ui->tpl = new CapturedElementTemplate();
		$ui->index();

		$this->assertIsArray($ui->tpl->content, 'index() sent nothing to the template');
		$this->assertIsArray($ui->tpl->content['nm'], 'index() sent no nextmatch value');

		return $ui->tpl->content['nm'];
	}

	public function testSendsNoRowsOnAFreshSession()
	{
		$this->assertSame(0, (int)$this->initialNextmatchValue()['num_rows']);
	}

	public function testSendsNoRowsWhenTheSessionRemembersAPage()
	{
		// what get_rrows() leaves behind after the client asked for a page of rows
		Api\Cache::setSession('projectmanager', 'projectelements_list', array(
			'num_rows'   => 50,
			'start'      => 0,
			'filter'     => 'all',
			'col_filter' => array('pe_resources' => null),
		));

		$this->assertSame(0, (int)$this->initialNextmatchValue()['num_rows'],
			'a remembered page size must not bring the initial row fetch back');
	}

	public function testFiltersOnTheProject()
	{
		$this->assertSame($this->pm_id, (int)$this->initialNextmatchValue()['col_filter']['pm_id']);
	}

	public function testProjectFilterWinsOverAStaleSession()
	{
		Api\Cache::setSession('projectmanager', 'projectelements_list', array(
			'num_rows'   => 50,
			'col_filter' => array('pm_id' => $this->pm_id + 1000, 'pe_resources' => null),
		));

		$this->assertSame($this->pm_id, (int)$this->initialNextmatchValue()['col_filter']['pm_id'],
			'the project being opened wins, not the one the session was left on');
	}

	/**
	 * get_rrows() keeps deriving the project from the preference, for callers that pass no
	 * filters at all - the CSV export runs get_rows() directly.
	 */
	public function testRowsStillFallBackToThePreferenceWithoutAFilter()
	{
		$ui = new \projectmanager_elements_ui();
		$query = array(
			'start'      => 0,
			'num_rows'   => 10,
			'filter'     => 'all',
			'filter2'    => 0,
			'col_filter' => array(),
			'order'      => 'pe_modified',
			'sort'       => 'DESC',
		);
		$rows = $readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertEquals($this->pm_id, $ui->pm_id,
			'without a pm_id filter the preference still says which project');
	}
}
