<?php


namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/src/test/AppTest.php');	// Application test base
//$GLOBALS['egw_info']['flags']['currentapp'] = 'projectmanager';

use Egroupware\Api;

class DeleteTest extends \EGroupware\Api\AppTest
{

	protected $bo;

	// Project ID, so we can check if it gets deleted
	protected $pm_id;
	
	// List of element IDs so we can check if they get deleted
	protected $elements = array();

	// History setting, so we can reset it after
	protected static $pm_history_setting = '';
	protected static $infolog_history_setting = '';
	const HISTORY_SETTING = 'history';

	/**
	 * Start session once before tests
	 */
	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		$config = Api\Config::read('projectmanager');
		static::$pm_history_setting = $config[static::HISTORY_SETTING];

		$config = Api\Config::read('infolog');
		static::$infolog_history_setting = $config[static::HISTORY_SETTING];
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'infolog');
	}

	/**
	 * End session when done - restore original history setting
	 */
	public static function tearDownAfterClass()
	{
		// Restore original settings
		Api\Config::save_value(static::HISTORY_SETTING, static::$pm_history_setting, 'projectmanager');
		Api\Config::save_value(static::HISTORY_SETTING, static::$infolog_history_setting, 'infolog');

		// This removes the database and session
		parent::tearDownAfterClass();
	}

	public function setUp()
	{
		$this->bo = new \projectmanager_bo();
		$this->mockTracking();

		$this->makeProject();
	}

	public function tearDown()
	{
		$this->deleteProject();

		$this->bo = null;
	}

	public function testDelete()
	{
		// Setup values - history has to be changed in the config for when
		// projectmanager_bo is created other places, and the current object
		Api\Config::save_value(static::HISTORY_SETTING, '', 'projectmanager');
		$this->bo->history = '';
		$this->bo->tracking->expects($this->once())
                 ->method('track');

		// Delete
		$this->bo->delete($this->pm_id);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check - null means not found
		$this->assertNull($this->bo->read($this->pm_id));

		// Check that elements are gone
		$this->checkElements('', 0);

		// Check datasources are still there
		$this->checkDatasources('open');
	}

	/**
	 * Check that a project that is deleted gets its status changed, but
	 * leave datasource entries alone
	 */
	public function testDeleteAndHold()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';
		$this->bo->tracking->expects($this->once())
                ->method('track');

		// Execute
		$this->bo->delete($this->pm_id, false);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$project = $this->bo->read($this->pm_id);
		$this->assertNotNull($project, 'Project is not there');
		if($project)
		{
			$this->assertArraySubset(
				array('pm_id' => $this->pm_id, 'pm_status' => 'deleted'),
				$this->bo->read($this->pm_id),
				false,
				'Project status was not set to deleted'
			);
		}
		
		// Check datasources are still there
		$this->checkDatasources('open');
	}

	/**
	 * Check that a project that is deleted gets its datasource entries changed to match
	 */
	public function testDeleteAndHoldDatasource()
	{
		// Setup
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';
		$this->bo->tracking->expects($this->once())
                 ->method('track');

		// Execute
		$this->bo->delete($this->pm_id, true);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project
		$project = $this->bo->read($this->pm_id);
		$this->assertNotNull($project, 'Project is not there');
		if($project)
		{
			$this->assertArraySubset(
				array('pm_id' => $this->pm_id, 'pm_status' => 'deleted'),
				$this->bo->read($this->pm_id),
				false,
				'Project status was not set to deleted'
			);
		}
		
		// Check datasources are deleted
		$this->checkDatasources('deleted');

	}

	/**
	 * Check that a project that is deleted and restored gets its status changed.
	 * We restore elements at the same time
	 *
	 * @depends testDeleteAndHold
	 */
	public function testDeleteAndRestore()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';

		// Tracker will be called twice, one for deletion, once for restore
		$this->bo->tracking->expects($this->atLeastOnce())
                ->method('track')
				->withConsecutive(
					[$this->callback(function($subject) { return $subject['pm_status'] == 'deleted';})],
					[$this->callback(function($subject) { return $subject['pm_status'] == 'active';})]
				);

		// Execute
		$this->bo->delete($this->pm_id, false);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check datasources are still there
		$this->checkDatasources('open');


		$this->bo->read($this->pm_id);
		$this->bo->save(array('pm_status' => 'active'));
		
		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project restoration
		$project = $this->bo->read($this->pm_id);
		$this->assertNotNull($project, 'Project is not there');
		if($project)
		{
			$this->assertArraySubset(
				array('pm_id' => $this->pm_id, 'pm_status' => 'active'),
				$this->bo->read($this->pm_id),
				false,
				'Project status was not set to deleted'
			);
		}
	}

	/**
	 * Check that a project that is deleted and restored gets its status changed.
	 * We restore elements at the same time
	 *
	 * @depends testDeleteAndHold
	 */
	public function testDeleteAndRestoreDatasource()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';

		// Tracker will be called twice, one for deletion, once for restore
		$this->bo->tracking->expects($this->atLeastOnce())
                ->method('track')
				->withConsecutive(
					[$this->callback(function($subject) { return $subject['pm_status'] == 'deleted';})],
					[$this->callback(function($subject) { return $subject['pm_status'] == 'active';})]
				);

		// Execute
		$this->bo->delete($this->pm_id, true);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check datasources are gone - this depends on datasource settings
		$this->checkDatasources('deleted');


		$this->bo->read($this->pm_id);
		$this->bo->save(array('pm_status' => 'active'));
		$elements_bo = new \projectmanager_elements_bo();
		$elements_bo->run_on_sources('change_status', array('pm_id'=>$this->pm_id),'active');

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project restoration
		$project = $this->bo->read($this->pm_id);
		$this->assertNotNull($project, 'Project is not there');
		if($project)
		{
			$this->assertArraySubset(
				array('pm_id' => $this->pm_id, 'pm_status' => 'active'),
				$this->bo->read($this->pm_id),
				false,
				'Project status was not set to deleted'
			);
		}

		// Check datasources are back - this depends on datasource settings
		$this->checkDatasources('not-started');
	}

	/**
	 * Check that a project that is deleted and deleted again gets fully removed, but
	 * leave datasource entries alone
	 */
	public function testDeleteAndPurge()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';

		// Tracker will be called only once, for first deletion
		$this->bo->tracking->expects($this->once())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_status'] == 'deleted';}));

		// Execute
		$this->bo->delete($this->pm_id, false);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check datasources are still there
		$this->checkDatasources('open');

		// Purge it
		$this->bo->delete($this->pm_id);
		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project is gone
		$project = $this->bo->read($this->pm_id);
		$this->assertNull($project, 'Project is still there');
		
		$this->checkElements('', 0);
	}

	/**
	 * Check that a project that is completely deleted gets its datasource entries
	 * purged as well
	 */
	public function testDeleteAndPurgeDatasource()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';

		// Tracker will be called only once, for first deletion
		$this->bo->tracking->expects($this->once())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_status'] == 'deleted';}));

		// Execute
		$this->bo->delete($this->pm_id, true);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check datasources are still there, but deleted
		$this->checkDatasources('deleted');

		// Purge it
		$this->bo->delete($this->pm_id, true);
		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project is gone
		$project = $this->bo->read($this->pm_id);
		$this->assertNull($project, 'Project is still there');

		$this->checkElements('', 0);
		
		// Check datasources are gone
		$info_bo = new \infolog_bo();
		foreach($this->elements as $id)
		{
			$info = $info_bo->read($id);
			$this->assertNull($info);
		}
	}


	/**
	 * Check that a project that is deleted cannot be purged when that setting
	 * is used
	 */
	public function testDeleteNoPurging()
	{
		// Setup - keep deleted project
		Api\Config::save_value(static::HISTORY_SETTING, 'history_no_delete', 'projectmanager');
		$this->bo->history = 'history_no_delete';

		// Tracker will be called only once, for first deletion
		$this->bo->tracking->expects($this->once())
                ->method('track')
				->with($this->callback(function($subject) { return $subject['pm_status'] == 'deleted';}));

		// Execute
		$this->bo->delete($this->pm_id, false);

		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check datasources are still there
		$this->checkDatasources('open');

		// Purge it
		$this->bo->delete($this->pm_id);
		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		// Check project still there, still deleted
		$project = $this->bo->read($this->pm_id);
		$this->assertNotNull($project, 'Project is not there');
		if($project)
		{
			$this->assertArraySubset(
				array('pm_id' => $this->pm_id, 'pm_status' => 'deleted'),
				$this->bo->read($this->pm_id),
				false,
				'Project status was not set to deleted'
			);
		}
	}

	/**
	 * Make a project so we can test deleting it
	 */
	protected function makeProject()
	{
		$project = array(
			'pm_number'         =>	'TEST',
			'pm_title'          =>	'Auto-test for ' . $this->getName(),
			'pm_status'         =>	'active',
			'pm_description'    =>	'Test project for ' . $this->getName()
		);

		// Save & set modifier, no notifications
		try
		{
			$result = true;
			$result = $this->bo->save($project, true, false);
		}
		catch (\Exception $e)
		{
			// Something went wrong, we'll just fail
			$this->fail($e);
		}

		$this->assertFalse((boolean)$result, 'Error making test project');
		$this->assertArrayHasKey('pm_id', $this->bo->data, 'Could not make test project');
		$this->assertThat($this->bo->data['pm_id'],
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);
		$this->pm_id = $this->bo->data['pm_id'];

		// Add some elements
		$info_bo = new \infolog_bo();
		for($i = 1; $i <= 5; $i++)
		{
			$element = array(
				'info_subject' => "Test element #{$i}",
				'info_des'     => 'Test element for as part of the project for test ' . $this->getName(),
				'info_status'  => 'open',
				'pm_id'	=> $this->pm_id,
				'info_contact' => array('app' => 'projectmanager', 'id' => $this->pm_id)
			);
			$this->elements[] = $info_bo->write($element, true, true, true, true);
		}
		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->bo);
		$elements->sync_all($this->pm_id);
	}

	/**
	 * Fully delete a project and its elements, no matter what state or settings
	 */
	protected function deleteProject()
	{
		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();
		
		// Force to ignore setting
		$this->bo->history = '';
		$this->bo->delete(null, true);

		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();
		
		// Delete all elements
		$info_bo = new \infolog_bo();
		$info_bo->history = '';
		foreach($this->elements as $id)
		{
			$info_bo->delete($id, true, false, true);

			// Delete a second time to make sure it's gone
			$info_bo->delete($id, true, false, true);
		}
	}

	/**
	 * Check that the project elements are present, and have the provided status.
	 *
	 * @param String $status
	 */
	protected function checkElements($status = '', $expected_count = 0)
	{
		$element_bo = new \projectmanager_elements_bo();
		$element_count = 0;

		foreach($element_bo->search(array('pm_id' => $this->pm_id), false) as $element)
		{
			$element_count++;
			$this->assertEquals($status, $element['pe_status'], "Project element status was {$element['pe_status']}, expected $status");
		}
		
		$this->assertEquals($expected_count, $element_count, "Incorrect number of elements");
	}

	/**
	 * Check that the datasources are present, and have the provided status.
	 * Datasource deletion is covered by each app's own setting.
	 *
	 * @param String $status
	 */
	protected function checkDatasources($status = '')
	{
		$info_bo = new \infolog_bo();
		foreach($this->elements as $id)
		{
			$info = $info_bo->read($id);
			$this->assertArraySubset(array('info_id' => $id), $info, false, "Unable to read infolog datasource $id");
			$this->assertEquals($status, $info['info_status'], "Project datasource status was {$info['info_status']}, expected $status");
		}
	}

	/**
	 * Sets the tracking object to a mock object
	 */
	protected function mockTracking()
	{
		$this->bo->tracking = $this->getMockBuilder(\projectmanager_tracking::class)
			->disableOriginalConstructor()
			->setMethods(['track'])
			->getMock($this->bo);
	}

}