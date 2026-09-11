<?php

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Hooks;
use EGroupware\Api\Link;

/**
 * Coverage for what projectmanager tells connected clients about changes.
 *
 * Everything a client learns goes through the "notify-all" hook, which the push backend turns
 * into one websocket message per call - so capturing that hook is capturing the push, without
 * needing a push server (or the app providing one) installed.  Two things matter to the client
 * and are asserted here:
 *
 * - a project change is pushed at all.  Link::notify_update() defaults the type to "unknown",
 *   which the push deliberately drops as coming from an application that is not push aware, so
 *   for years no projectmanager change reached anyone.
 * - an element that is removed is pushed.  Link::unlink() only announces a deleted entry when
 *   it is called without an app2/link_id, which unlinking an element from a project never is,
 *   and the element update notification is only sent for elements that still exist.
 *
 * The element list addresses its rows by "pe_app:pe_app_id:pe_id", so every element
 * notification has to carry enough to build that, after the push has reduced the data to the
 * keys registered as push_data - which is what assertElementPush() checks.
 */
class ElementPushTest extends Api\AppTest
{
	protected $pm_id;
	protected $info_id;

	/**
	 * notify-all hook arguments captured during a test, see captureNotifyAll()
	 */
	protected static $captured = array();

	/**
	 * Hooks::$locations as it was before we replaced the notify-all entry
	 */
	protected $saved_locations;

	/**
	 * Capture instead of push, registered as the only notify-all hook for the test
	 *
	 * @param array $args
	 */
	public static function captureNotifyAll(array $args)
	{
		self::$captured[] = $args;
	}

	protected function setUp() : void
	{
		Link::run_notifies();

		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');

		$so = new \projectmanager_so();
		$stale = $so->read(array('pm_number' => 'PUSH-TEST'));
		if ($stale && $stale['pm_id'])
		{
			$bo->history = '';
			$bo->delete($stale['pm_id'], true);
		}

		$bo->save(array(
			'pm_number'      => 'PUSH-TEST',
			'pm_title'       => 'Auto-test for '.$this->name(),
			'pm_status'      => 'active',
			'pm_description' => 'Test project for '.$this->name(),
		), true, false);
		$this->pm_id = $bo->data['pm_id'];
		$this->assertGreaterThan(0, (int)$this->pm_id, 'Could not make test project');

		// replace every notify-all hook with our own, so we capture what would be pushed and
		// nothing else (a push server, kanban, the RAG indexer, ...) reacts to the test data
		$locations = $this->hookLocations();
		$this->saved_locations = $locations;
		$locations['notify-all'] = array('projectmanager' => array(self::class.'::captureNotifyAll'));
		$this->setHookLocations($locations);
		self::$captured = array();
	}

	protected function tearDown() : void
	{
		if (isset($this->saved_locations))
		{
			$this->setHookLocations($this->saved_locations);
		}
		self::$captured = array();

		try
		{
			if ($this->info_id)
			{
				$infolog_bo = new \infolog_bo();
				// Delete twice to make sure it's really gone (history setting)
				$infolog_bo->delete($this->info_id, true, false, true);
				$infolog_bo->delete($this->info_id, true, false, true);
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
			Link::run_notifies();
			unset($GLOBALS['projectmanager_bo']);
			unset($GLOBALS['projectmanager_elements_bo']);
		}
	}

	/**
	 * Hooks caches its registry in a private static, reachable only by reflection
	 */
	protected function hookLocations() : array
	{
		Hooks::process('some-location-nobody-implements');	// make sure the registry is read
		$prop = new \ReflectionProperty(Hooks::class, 'locations');
		$prop->setAccessible(true);
		return (array)$prop->getValue();
	}

	protected function setHookLocations(array $locations)
	{
		$prop = new \ReflectionProperty(Hooks::class, 'locations');
		$prop->setAccessible(true);
		$prop->setValue(null, $locations);
	}

	/**
	 * Captured notify-all calls for one application, in order
	 *
	 * @param string $app
	 * @return array
	 */
	protected function pushesFor(string $app) : array
	{
		return array_values(array_filter(self::$captured, static function($args) use ($app)
		{
			return $args['app'] === $app;
		}));
	}

	/**
	 * Add an infolog linked to the test project, ie. create a project-element
	 *
	 * @return int info_id
	 */
	protected function addElement() : int
	{
		$infolog_bo = new \infolog_bo();
		// write() takes its values by reference
		$values = array(
			'info_subject' => 'Test infolog for #'.$this->pm_id,
			'info_des'     => 'Test element for '.$this->name(),
			'info_status'  => 'open',
			'pm_id'        => $this->pm_id,
			'info_contact' => array('app' => 'projectmanager', 'id' => $this->pm_id),
		);
		$this->info_id = $infolog_bo->write($values, true, true, true, true);

		// links normally notify on Egw::on_shutdown()
		Link::run_notifies();

		return $this->info_id;
	}

	/**
	 * What the client gets: the notification reduced to the keys registered as push_data,
	 * exactly as the push backend does it before sending
	 *
	 * @param array $push one captured notify-all call
	 * @return array
	 */
	protected function pushedAcl(array $push) : array
	{
		$push_data = Link::get_registry($push['app'], 'push_data');
		if (!$push_data || !is_array($push_data) || empty($push['data']))
		{
			return array();
		}
		return array_intersect_key($push['data'], array_flip($push_data));
	}

	/**
	 * An element notification has to survive the push_data reduction with everything the
	 * client needs to find the row: the project, and the three parts of its row-id
	 *
	 * @param array $push
	 * @param int $info_id
	 */
	protected function assertElementPush(array $push, int $info_id)
	{
		$acl = $this->pushedAcl($push);

		$this->assertEquals($this->pm_id, $acl['pm_id'], 'element push has to say which project');
		$this->assertEquals('infolog', $acl['pe_app'], 'element push has to say which application');
		$this->assertEquals($info_id, $acl['pe_app_id'], 'element push has to say which entry');
		$this->assertNotEmpty($acl['pe_id'], 'element push has to carry the pe_id');
		$this->assertEquals($acl['pe_id'], $push['id'],
			'the pushed id is the pe_id, the client builds the row-id from acl + id');
	}

	public function testPushDataIsRegisteredForElements()
	{
		$push_data = Link::get_registry('projectelement', 'push_data');

		$this->assertIsArray($push_data, 'projectelement needs push_data, or the push carries no acl at all');
		foreach(array('pm_id', 'pe_id', 'pe_app', 'pe_app_id') as $key)
		{
			$this->assertContains($key, $push_data, "the client needs $key to find the element's row");
		}
	}

	public function testAddingAnElementIsPushed()
	{
		$info_id = $this->addElement();

		$pushes = $this->pushesFor('projectelement');
		$this->assertNotEmpty($pushes, 'linking an entry to a project has to notify about the element');
		$this->assertElementPush($pushes[0], $info_id);
	}

	public function testUnlinkingAnElementIsPushedAsDelete()
	{
		$info_id = $this->addElement();
		self::$captured = array();

		Link::unlink(0, 'projectmanager', $this->pm_id, 0, 'infolog', $info_id);
		Link::run_notifies();

		$pushes = $this->pushesFor('projectelement');
		$this->assertNotEmpty($pushes, 'removing an element from a project has to be pushed');
		$this->assertEquals('delete', $pushes[0]['type']);
		$this->assertElementPush($pushes[0], $info_id);
	}

	public function testDeletingTheEntryIsPushedAsDelete()
	{
		$info_id = $this->addElement();
		self::$captured = array();

		$infolog_bo = new \infolog_bo();
		$infolog_bo->delete($info_id, true, false, true);
		$infolog_bo->delete($info_id, true, false, true);
		Link::run_notifies();
		$this->info_id = null;

		$pushes = $this->pushesFor('projectelement');
		$this->assertNotEmpty($pushes, 'deleting the entry behind an element has to be pushed');
		$this->assertEquals('delete', $pushes[0]['type']);
		$this->assertElementPush($pushes[0], $info_id);
	}

	public function testSavingAProjectIsPushedWithAType()
	{
		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');
		$bo->read($this->pm_id);
		$bo->save(array('pm_id' => $this->pm_id, 'pm_description' => 'Changed by '.$this->name()));
		Link::run_notifies();

		$pushes = $this->pushesFor('projectmanager');
		$this->assertNotEmpty($pushes, 'a changed project has to be pushed');
		$this->assertEquals('edit', $pushes[0]['type'],
			'without an explicit type the push drops the change as coming from a not push aware app');
	}

	/**
	 * Link::notify_update() calls a change that renames an entry an "update" whatever type it is
	 * given, so those move no row.  Preserved as-is: it is Link behaviour shared by every app.
	 */
	public function testRenamingAProjectStaysAnUpdate()
	{
		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');
		$bo->read($this->pm_id);
		$bo->save(array('pm_id' => $this->pm_id, 'pm_title' => 'Renamed by '.$this->name()));
		Link::run_notifies();

		$pushes = $this->pushesFor('projectmanager');
		$this->assertNotEmpty($pushes, 'a renamed project has to be pushed');
		$this->assertEquals('update', $pushes[0]['type']);
	}

	public function testCreatingAProjectIsPushedAsAdd()
	{
		// setUp() creates its project with notifications off, so make one that notifies
		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');
		$bo->save(array(
			'pm_number' => 'PUSH-TEST-ADD',
			'pm_title'  => 'Auto-test for '.$this->name(),
			'pm_status' => 'active',
		));
		$added = $bo->data['pm_id'];
		Link::run_notifies();

		$pushes = array_values(array_filter($this->pushesFor('projectmanager'), static function($push) use ($added)
		{
			return $push['id'] == $added;
		}));

		$bo->history = '';
		$bo->delete($added, true);
		$so = new \projectmanager_so();
		if ($so->read($added)) $so->delete($added);

		$this->assertNotEmpty($pushes, 'a new project has to be pushed');
		$this->assertEquals('add', $pushes[0]['type'], 'a new project is an add, it needs re-sorting');
	}

	public function testDeletingAProjectIsPushed()
	{
		$bo = new \projectmanager_bo();
		$this->mockTracking($bo, 'projectmanager_tracking');
		$bo->history = 'history';	// keep the entry, the branch where Link::unlink() stays quiet
		$bo->delete($this->pm_id);
		Link::run_notifies();

		$pushes = array_values(array_filter($this->pushesFor('projectmanager'), static function($push)
		{
			return $push['type'] === 'delete';
		}));
		$this->assertNotEmpty($pushes, 'a deleted project has to be pushed, also when only marked deleted');
		$this->assertEquals($this->pm_id, $pushes[0]['id']);
	}
}
