<?php
/**
 * The project list's ajax endpoint refuses a request that carries no eTemplate exec id.
 *
 * @package projectmanager
 * @subpackage tests
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * projectmanager_ui::ajax_action() is what the project list's converted context-menu actions
 * call (app.projectmanager.change_status -> 'projectmanager.projectmanager_ui.ajax_action').
 * It checks rights per project in action(), which is the substantive guard, but it took no exec
 * id at all - so unlike every other endpoint this project converted, nothing established that
 * the caller had one of our lists open rather than a bare project id they guessed.
 *
 * These pin the refusal, which is the half that silently does nothing when it regresses: an
 * endpoint that stops validating still passes every test about what it does on success.
 */
class ProjectListAjaxActionTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \projectmanager_bo
	 */
	protected $bo;

	protected $pm_id;

	protected function setUp() : void
	{
		Api\Link::run_notifies();

		$this->bo = new \projectmanager_bo();
		$this->assertFalse((bool)$this->bo->save(array(
			'pm_number'      => 'TEST-EXECID',
			'pm_title'       => 'Auto-test for ' . $this->name(),
			'pm_status'      => 'active',
			'pm_description' => 'Project list exec-id test',
		), true, false), 'Error making test project');
		$this->pm_id = $this->bo->data['pm_id'];

		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		if ($this->pm_id)
		{
			try
			{
				$this->bo->delete(array('pm_id' => $this->pm_id), true);
			}
			catch (\Exception $e)
			{
				unset($e);
			}
		}
		unset($GLOBALS['projectmanager_elements_bo']);
		$this->pm_id = null;
	}

	protected function projectStatus() : ?string
	{
		return $GLOBALS['egw']->db->select('egw_pm_projects', 'pm_status',
			array('pm_id' => $this->pm_id), __LINE__, __FILE__, false, '', 'projectmanager')
			->fetchColumn() ?: null;
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along.
	 */
	protected function execId() : string
	{
		$request = Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = array('nm' => array());
		unset($request);
		return $id;
	}

	protected function responseFuncs() : array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		$funcs = array();
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			$funcs[] = $chunk['data']['func'] ?? $chunk['type'] ?? '';
		}
		return $funcs;
	}

	public function testRefusesWithoutAnExecId()
	{
		$ui = new \projectmanager_ui();
		$ui->ajax_action('', 'status_archive', array($this->pm_id));

		$this->assertSame('active', $this->projectStatus(),
			'nothing may be written without an exec id');
		$this->assertNotContains('egw.refresh', $this->responseFuncs(),
			'and it must not answer as though it had acted');
	}

	public function testRefusesAnExecIdThatIsNotOurs()
	{
		$ui = new \projectmanager_ui();
		$ui->ajax_action('projectmanager_nobody_madeThisUp', 'status_archive', array($this->pm_id));

		$this->assertSame('active', $this->projectStatus(),
			'an id that resolves to no request is no better than none');
	}

	/**
	 * ... and with a real one it goes through, so the guard is not simply breaking the action.
	 */
	public function testActsWithAnExecId()
	{
		$ui = new \projectmanager_ui();
		$ui->ajax_action($this->execId(), 'status_archive', array($this->pm_id));

		$this->assertSame('archive', $this->projectStatus(), 'with a valid exec id it has to act');
	}

	/**
	 * "Select all" re-runs the query the list last stored. With none stored - a session that was
	 * replaced while the page stayed open, or a list that never loaded - there is no way to know
	 * what the user was looking at, and an empty query is every project they can see: it has to
	 * be refused.
	 *
	 * The action name is one the endpoint does not know, so should the refusal ever regress this
	 * test fails without having changed a single project.
	 */
	public function testSelectAllIsRefusedWithoutAStoredQuery()
	{
		Api\Cache::unsetSession('projectmanager', 'project_list');

		$this->expectException(Api\Exception\WrongUserinput::class);
		(new \projectmanager_ui())->ajax_action($this->execId(), 'selectalltest-noop', array(), true);
	}

	/**
	 * The egw.refresh message of the response, or null
	 */
	protected function refreshMessage() : ?string
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['data']['func'] ?? null) === 'egw.refresh')
			{
				return (string)((array)$chunk['data']['parms'])[0];
			}
		}
		return null;
	}

	/**
	 * With "select all" the client still sends the ids of the rows it has loaded, and the query
	 * the server re-runs finds those rows again. The action must run once per project: the ids
	 * the client sent are replaced by what the query found, not added to it. Adding them changed
	 * every loaded project twice, and made a delete report each of them as failed the second
	 * time round.
	 *
	 * The stored query is narrowed to this test's project by its title, and the count sent
	 * along (1) makes the server refuse should it ever find more than two.
	 */
	public function testSelectAllActsOncePerProject()
	{
		// narrowed by the fixture's title: the list's row method drops a pm_id column filter
		Api\Cache::setSession('projectmanager', 'project_list', array(
			'start' => 0, 'order' => 'pm_id', 'sort' => 'ASC', 'filter' => '', 'filter2' => '',
			'search' => '', 'col_filter' => array('pm_title' => 'Auto-test for ' . $this->name()),
		));

		$ui = new \projectmanager_ui();
		$query = Api\Cache::getSession('projectmanager', 'project_list') + array('num_rows' => -1, 'csv_export' => true);
		$rows = $readonlys = null;
		$ui->get_rrows($query, $rows, $readonlys);
		$this->assertContains((string)$this->pm_id, array_map('strval', array_column(array_filter((array)$rows, 'is_array'), 'pm_id')),
			'the stored query has to find the fixture project');

		$ui->ajax_action($this->execId(), 'status_archive', array($this->pm_id), 1);

		$this->assertSame('archive', $this->projectStatus(), 'the project the list showed is changed');
		$this->assertStringContainsString('1 project', (string)$this->refreshMessage(),
			'and reported once, not once for the id sent and once for the id found');
		Api\Cache::unsetSession('projectmanager', 'project_list');
	}
}
