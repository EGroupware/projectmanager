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
}
