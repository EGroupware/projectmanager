<?php
/**
 * Rights on the element list's "ignore" action, and the endpoint that runs it.
 *
 * @package projectmanager
 * @subpackage tests
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Link;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * projectmanager_elements_ui::ajax_action() is reachable by anyone who has the projectmanager
 * app, and it used to run the 'ignore' action on whatever pe_ids the request carried:
 *
 *  - with no eTemplate exec id, so nothing established the caller had one of our lists open, and
 *  - with no ACL check at all, unlike the 'cat' and 'delete' branches beside it, and
 *  - calling save() even when read() had just failed, which wrote pe_status onto whatever the
 *    object happened to still hold.
 *
 * Rights now come from the element's OWN project, read back from egw_pm_elements, rather than
 * from the project this object was constructed with - over ajax there is no project at all, and
 * in the list it is a client-owned filter the user can change. The pm_id is passed to check_acl()
 * explicitly, because projectmanager_bo::check_acl() reads a falsy one as "new entry, everything
 * allowed but delete" and would wave every id through.
 *
 * Not covered here: a user who genuinely lacks rights on the element's project. That needs a
 * second logged-in user, which this harness has no helper for (LoggedInTest offers asAdmin()
 * only). The checks below pin the parts that can go red without one.
 */
class ElementIgnoreAclTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \projectmanager_bo
	 */
	protected $bo;

	protected $pm_id;

	protected $pe_id;

	protected function setUp() : void
	{
		Link::run_notifies();

		$this->bo = new \projectmanager_bo();
		$this->makeProject();
		$this->makeElement();

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
				// a failed delete must not mask the test's own failure
				unset($e);
			}
		}
		// the elements bo is reused through a global singleton, so a stale one would hand the
		// next test this project's pm_id, see projectmanager_elements_bo's constructor
		unset($GLOBALS['projectmanager_elements_bo']);
		$this->pm_id = $this->pe_id = null;
	}

	protected function makeProject() : void
	{
		$this->assertFalse((bool)$this->bo->save(array(
			'pm_number'      => 'TEST-IGNORE-ACL',
			'pm_title'       => 'Auto-test for ' . $this->name(),
			'pm_status'      => 'active',
			'pm_description' => 'Element ignore ACL test',
		), true, false), 'Error making test project');
		$this->pm_id = $this->bo->data['pm_id'];
	}

	/**
	 * An element only exists once something is linked to the project and the link notifications
	 * have run - they otherwise wait for Egw::on_shutdown(), which never comes in a test.
	 */
	protected function makeElement() : void
	{
		$infolog = new \infolog_bo();
		// write() takes its values by reference
		$values = array(
			'info_type'    => 'task',
			'info_subject' => 'Element for ' . $this->name(),
			'info_des'     => 'Element ignore ACL test',
			'info_status'  => 'ongoing',
		);
		$info_id = $infolog->write($values, false, false, true, true);
		$this->assertNotFalse($info_id, 'could not make the linked infolog entry');

		Link::link('infolog', $info_id, 'projectmanager', $this->pm_id);
		Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->pm_id);
		$elements->sync_all($this->pm_id);

		$rows = array();
		foreach($elements->search(array('pm_id' => $this->pm_id), false) as $row)
		{
			$rows[] = $row;
		}
		$this->assertNotEmpty($rows, 'no project element was created');
		$this->pe_id = $rows[0]['pe_id'];
	}

	protected function elementStatus() : ?string
	{
		return $GLOBALS['egw']->db->select('egw_pm_elements', 'pe_status',
			array('pm_id' => $this->pm_id, 'pe_id' => $this->pe_id),
			__LINE__, __FILE__, false, '', 'projectmanager')->fetchColumn() ?: null;
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

	/**
	 * The fixture itself has to be sound, or the refusals below would pass for the wrong reason.
	 */
	public function testTheElementBelongsToTheProject()
	{
		$elements = new \projectmanager_elements_bo();
		$this->assertNotFalse($elements->read(array('pe_id' => $this->pe_id)),
			'an element has to be readable from its pe_id alone - that is where its pm_id comes from');
		$this->assertSame((int)$this->pm_id, (int)$elements->data['pm_id'],
			'and it has to name the project it really belongs to');
	}

	/**
	 * The owner is allowed, so the ACL check must not break the action it guards.
	 */
	public function testOwnerCanStillIgnoreTheirOwnElement()
	{
		$ui = new \projectmanager_elements_ui();
		$msg = '';
		$this->assertTrue($ui->action('ignore_1', array($this->pe_id), $msg, null),
			'the project owner has ADD rights, so this must succeed: ' . $msg);
		$this->assertSame('ignore', $this->elementStatus(), 'and it has to persist');

		$msg = '';
		$this->assertTrue($ui->action('ignore_', array($this->pe_id), $msg, null),
			'un-ignoring is the same path: ' . $msg);
		$this->assertSame('new', $this->elementStatus(), 'and it has to persist too');
	}

	/**
	 * read() failing used to fall through to save(), writing pe_status onto whatever the object
	 * still held from a previous iteration.
	 */
	public function testUnknownElementIsRefusedInsteadOfWritingSomethingElse()
	{
		$before = $this->elementStatus();

		$ui = new \projectmanager_elements_ui();
		$msg = '';
		$this->assertFalse($ui->action('ignore_1', array(0x7FFFFFF0), $msg, null),
			'an element id that resolves to nothing has to be reported as failed');
		$this->assertSame($before, $this->elementStatus(),
			'and it must not have written the status onto some other element');
	}

	/**
	 * The endpoint is a public menuaction - without the exec id, element ids are all an attacker
	 * needs.
	 */
	public function testEndpointRefusesWithoutAnExecId()
	{
		$before = $this->elementStatus();

		\projectmanager_elements_ui::ajax_action('', 'ignore',
			array('projectmanager_elements::infolog:1:' . $this->pe_id), true);

		$this->assertSame($before, $this->elementStatus(), 'nothing may be written without an exec id');
		$this->assertNotContains('egw.refresh', $this->responseFuncs(),
			'and it must not answer as though it had acted');
	}

	/**
	 * ... and with one it goes through, so the guard is not simply breaking the action.
	 */
	public function testEndpointActsWithAnExecId()
	{
		\projectmanager_elements_ui::ajax_action($this->execId(), 'ignore',
			array('projectmanager_elements::infolog:1:' . $this->pe_id), true);

		$this->assertSame('ignore', $this->elementStatus(), 'with a valid exec id it has to act');
	}
}
