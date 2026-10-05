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

	protected $pref_project;

	protected function setUp() : void
	{
		Link::run_notifies();

		$this->pref_project = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] ?? null;
		$this->bo = new \projectmanager_bo();
		$this->purgeStale();
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
		unset($_REQUEST['pm_id']);
		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $this->pref_project;
		$this->pm_id = $this->pe_id = null;
	}

	/**
	 * An element list for a given project.
	 *
	 * Which project the object is built for matters to more than rights: so::read() adds
	 * `pm_id => $this->pm_id` to the keys whenever it has one, so a pe_id is only resolvable
	 * within that project. The constructor takes it from $_REQUEST, falling back to the
	 * current_project preference - which is per-user state left behind by whatever was opened
	 * last, so a test that does not pin both gets whichever project ran before it.
	 *
	 * @param int $pm_id 0 for "no project at all", the shape ajax_action() builds
	 */
	protected function ui(int $pm_id) : \projectmanager_elements_ui
	{
		$_REQUEST['pm_id'] = $pm_id;
		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $pm_id;
		return new \projectmanager_elements_ui();
	}

	/**
	 * pm_number is unique, so a fixture left behind by an earlier failed run blocks every later
	 * one with "Duplicate entry". Same pattern as DeleteTest's purgeStaleProjectFixture().
	 */
	protected function purgeStale() : void
	{
		$project = (new \projectmanager_so())->read(array('pm_number' => 'TEST-IGNORE-ACL'));
		if ($project && $project['pm_id'])
		{
			// a throwaway bo: delete() leaves its ->data pointing at the deleted project, and
			// the caller's bo is about to save() a new one through the same object
			$purge = new \projectmanager_bo();
			$purge->history = '';
			try
			{
				$purge->delete($project['pm_id'], true);
			}
			catch (\Exception $e)
			{
				unset($e);
			}
			$GLOBALS['egw']->db->delete('egw_pm_elements', array('pm_id' => $project['pm_id']),
				__LINE__, __FILE__, 'projectmanager');
			$GLOBALS['egw']->db->delete('egw_pm_projects', array('pm_id' => $project['pm_id']),
				__LINE__, __FILE__, 'projectmanager');
		}
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
		$ui = $this->ui($this->pm_id);
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

		$ui = $this->ui($this->pm_id);
		$msg = '';
		$this->assertFalse($ui->action('ignore_1', array(0x7FFFFFF0), $msg, null),
			'an element id that resolves to nothing has to be reported as failed');
		$this->assertSame($before, $this->elementStatus(),
			'and it must not have written the status onto some other element');
	}

	/**
	 * The rejection path: rights are refused for the element's project.
	 *
	 * A second logged-in user would be the fuller fixture, but this harness has no helper for
	 * one, and what actually needs pinning is this class's own decision rather than the ACL
	 * system's: that a refusal stops the write, and that the project asked about is the
	 * ELEMENT's, passed explicitly. The explicit pm_id is the whole point - with a falsy one
	 * projectmanager_bo::check_acl() answers "new entry, everything allowed but delete" and
	 * would wave every id through, which is the bypass this guards.
	 */
	public function testRefusedWhenTheElementsProjectDeniesAdd()
	{
		$before = $this->elementStatus();

		// built for the element's own project, so read() resolves it and the refusal below can
		// only be coming from the rights check
		$ui = $this->ui($this->pm_id);
		$ui->project = new class extends \projectmanager_bo {
			public $asked = array();
			// deliberately not calling parent::__construct(): this stands in for the project
			// only to answer check_acl(), and action() must not get as far as anything else
			public function __construct() {}
			function check_acl($required, $data=0, $no_cache=false, $user=null)
			{
				unset($no_cache, $user);
				$this->asked[] = array('required' => $required, 'pm_id' => $data);
				return false;
			}
		};

		$msg = '';
		$this->assertFalse($ui->action('ignore_1', array($this->pe_id), $msg, null),
			'a denied element has to be reported as failed');
		$this->assertSame($before, $this->elementStatus(),
			'and nothing may be written for it');

		$this->assertNotEmpty($ui->project->asked, 'rights have to actually be checked');
		$asked = $ui->project->asked[0];
		$this->assertSame(Acl::ADD, $asked['required'], 'ADD is the right this action needs');
		$this->assertSame((int)$this->pm_id, (int)$asked['pm_id'],
			"the element's own project has to be the one asked about");
		$this->assertNotEmpty($asked['pm_id'],
			'and it has to be passed explicitly - a falsy pm_id means "allow everything but delete"');
	}

	/**
	 * The real rejection: a different logged-in user, with no rights on the element's project.
	 *
	 * projectmanager_bo::check_acl() has no admin bypass - rights come from the creator's ACL
	 * grants plus project membership - so the admin test account is simply another user here,
	 * and it is neither a member of this project nor granted anything by its creator.
	 */
	public function testAnotherUserWithoutRightsIsRefused()
	{
		$before = $this->elementStatus();
		$msg = '';

		$acting_as = null;
		$refused = $this->asAdmin(function() use (&$msg, &$acting_as)
		{
			$acting_as = $GLOBALS['egw_info']['user']['account_lid'];
			// BOTH bos are process-wide singletons caching the grants of whoever they were built
			// for, and projectmanager_bo is the one that answers check_acl() - leaving it would
			// have this user judged by the previous user's grants
			unset($GLOBALS['projectmanager_elements_bo'], $GLOBALS['projectmanager_bo']);
			$ui = $this->ui($this->pm_id);
			// PRE-EXISTING BUG, compensated for here: projectmanager_bo::check_acl() caches
			// computed rights in a `static $cache` keyed by pm_id ALONE, with no user in the
			// key. Within one PHP process the first user to ask about a project decides the
			// answer for every later one - here the owner warmed it during setUp, so without
			// this line the switched-in user is handed the OWNER's rights and the action
			// succeeds. One no_cache call recomputes it for whoever is logged in now.
			// Harmless in a web request (one user per process); real wherever a process
			// switches user - tests, CLI, admin_cmd. See the project doc.
			$ui->project->check_acl(Acl::ADD, (int)$this->pm_id, true);

			return $ui->action('ignore_1', array($this->pe_id), $msg, null);
		});
		unset($GLOBALS['projectmanager_elements_bo'], $GLOBALS['projectmanager_bo']);

		$this->assertSame($GLOBALS['EGW_ADMIN_USER'], $acting_as,
			'the callback has to actually run as the other user, or this proves nothing');
		$this->assertNotSame($GLOBALS['EGW_USER'], $acting_as, 'and not as the project owner');
		$this->assertFalse($refused,
			'a user with no rights on the project must be refused: ' . $msg);
		$this->assertSame($before, $this->elementStatus(),
			'and nothing may be written for them');
	}

	/**
	 * The endpoint is a public menuaction - without the exec id, element ids are all an attacker
	 * needs.
	 */
	public function testEndpointRefusesWithoutAnExecId()
	{
		$before = $this->elementStatus();
		$this->ui($this->pm_id);	// pin what ajax_action()'s own constructor falls back to

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
		$this->ui($this->pm_id);	// pin what ajax_action()'s own constructor falls back to

		\projectmanager_elements_ui::ajax_action($this->execId(), 'ignore',
			array('projectmanager_elements::infolog:1:' . $this->pe_id), true);

		$this->assertSame('ignore', $this->elementStatus(), 'with a valid exec id it has to act');
	}
}
