<?php


namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base
//$GLOBALS['egw_info']['flags']['currentapp'] = 'projectmanager';

use Egroupware\Api;

/**
 * Test deleting a project.
 *
 * Tests deleting just the project, deleting the project and datasource elements,
 * and deleting and restoring with delete history setting.
 */
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
	public static function setUpBeforeClass() : void
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
	public static function tearDownAfterClass() : void
	{
		// This removes the database and session
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \projectmanager_bo();
		$this->mockTracking($this->bo, 'projectmanager_tracking');

		// Make sure projects are not there first
		$pm_numbers = array(
			'TEST',
			'SUB-TEST'
		);
		foreach($pm_numbers as $number)
		{
			$project = $this->bo->read(Array('pm_number' => $number));
			if($project && $project['pm_id'])
			{
				$this->bo->delete($project);
			}
		}

		$this->makeProject();
	}

	protected function tearDown() : void
	{
		$this->deleteProject();

		$this->bo = null;

		// Restore original settings
		Api\Config::save_value(static::HISTORY_SETTING, static::$pm_history_setting, 'projectmanager');
		Api\Config::save_value(static::HISTORY_SETTING, static::$infolog_history_setting, 'infolog');

		// Projectmanager sets a lot of global stuff
		unset($GLOBALS['projectmanager_bo']);
		unset($GLOBALS['projectmanager_elements_bo']);
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
		$this->assertEquals(1, $this->bo->delete($this->pm_id), 'Failure when trying to delete project');

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
		$this->checkDatasources();
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
		$this->checkDatasources();
	}

	/**
	 * Check that a project that is deleted gets its datasource entries changed to match
	 */
	public function testDeleteAndHoldDatasource()
	{
		// Setup
		Api\Config::save_value(static::HISTORY_SETTING, 'history', 'projectmanager');
		$this->bo->history = 'history';
		$this->bo->tracking->expects($this->atLeastOnce())
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
		$this->checkDatasources();


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
					[$this->callback(function($subject) { return $subject['pm_status'] == 'deleted';})], // Sub project
					[$this->callback(function($subject) { return $subject['pm_status'] == 'deleted';})], // Main project
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
		$this->checkDatasources();
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
		$this->bo->tracking->expects($this->atLeastOnce())
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
		$this->checkDatasources();

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
		$this->bo->tracking->expects($this->atLeastOnce())
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
		$this->checkDatasources();

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
	 * Test deleting the datasource(s), but leaving the project alone.
	 * Deleting the datasource should remove the project element, regardless
	 * of the history setting.
	 */
	public function testDeleteDataSource()
	{
		$this->deleteElements();

		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		$this->checkElements('', 0);
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
		$this->assertThat((int)$this->bo->data['pm_id'],
			$this->logicalAnd(
				$this->isType('integer'),
				$this->greaterThan(0)
			)
		);
		$this->pm_id = $this->bo->data['pm_id'];

		// Add some elements
		$this->assertGreaterThan(0, count($GLOBALS['egw_info']['apps']),
			'No apps found to use as projectmanager elements'
		);
		foreach($GLOBALS['egw_info']['apps'] as $app => $app_vals)
		{
			// if datasource can not be autoloaded, skip
			if (!class_exists($class = $app.'_datasource') || !class_exists($bo_class = '\\'.$app.'_bo'))
			{
				continue;
			}
			if(method_exists($this, "make_$app"))
			{
				$this->{"make_$app"}();
			}
			else
			{
				$this->markTestIncomplete("$app has a datasource, but cannot be tested - add a make_$app() function to ". get_class());
			}
		}

		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->bo);
		$elements->sync_all($this->pm_id);

		// Make sure all elements are created
		$this->checkElements(false, count($this->elements), "Unable to create all project elements");
	}

	/**
	 * Make an infolog entry and add it to the project
	 */
	protected function make_calendar()
	{
		$bo = new \calendar_boupdate();
		$element = array(
			'title' => "Test calendar for #{$this->pm_id}",
			'des'   => 'Test element as part of the project for test ' . $this->getName(),
			'start' => \time(),
			'end'   => \time() + 60,
			'pm_id'	=> $this->pm_id,
		);
		$element_id = $bo->save($element);
		Api\Link::link('calendar',$element_id,'projectmanager',$this->pm_id);
		$this->elements[] = 'calendar:'.$element_id;
	}

	/**
	 * Make an infolog entry and add it to the project
	 */
	protected function make_infolog()
	{
		$bo = new \infolog_bo();
		$element = array(
			'info_subject' => "Test infolog for #{$this->pm_id}",
			'info_des'     => 'Test element as part of the project for test ' . $this->getName(),
			'info_status'  => 'open',
			'pm_id'	=> $this->pm_id,
			'info_contact' => array('app' => 'projectmanager', 'id' => $this->pm_id)
		);
		$element_id = $bo->write($element, true, true, true, true);
		$this->elements[] = 'infolog:'.$element_id;
	}

	/**
	 * Make a projectmanager entry and add it to the project
	 */
	protected function make_projectmanager()
	{
		$bo = new \projectmanager_bo();
		$bo->data = array(
			'pm_number'         =>	'SUB-TEST',
			'pm_title'          =>	"Test project for  #{$this->pm_id}",
			'pm_status'         =>	'active',
			'pm_description'    =>	'Test project for ' . $this->getName()
		);
		$bo->save();
		$element_id = $bo->data['pm_id'];
		Api\Link::link('projectmanager',$this->pm_id,'projectmanager',$element_id);
		$this->elements[] = 'projectmanager:'.$element_id;
	}

	/**
	 * Make a timesheet entry and add it to the project
	 */
	protected function make_timesheet()
	{
		$bo = new \timesheet_bo();
		$bo->data = array(
			'ts_title'       => "Test timesheet for #{$this->pm_id}",
			'ts_description' => 'Test element as part of the project for test ' . $this->getName(),
			'ts_status'      => null,
			'ts_owner'       => $GLOBALS['egw_info']['user']['account_id'],
			'ts_start'       => \time()
		);
		$bo->save();
		$element_id = $bo->data['ts_id'];
		Api\Link::link(TIMESHEET_APP,$element_id,'projectmanager',$this->pm_id);
		$this->elements[] = 'timesheet:'.$element_id;
	}

	/**
	 * Make a tracker entry and add it to the project
	 */
	protected function make_tracker()
	{
		$bo = new \tracker_bo();
		$bo->data = array(
			'tr_summary'     => "Test tracker for #{$this->pm_id}",
			'tr_description' => 'Test element as part of the project for test ' . $this->getName(),
			'tr_status'      => \tracker_bo::STATUS_OPEN,
			'tr_owner'       => $GLOBALS['egw_info']['user']['account_id']
		);
		$bo->save();
		$element_id = $bo->data['tr_id'];
		Api\Link::link('tracker',$element_id,'projectmanager',$this->pm_id);
		$this->elements[] = 'tracker:'.$element_id;
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
		Api\Config::save_value(static::HISTORY_SETTING, '', 'projectmanager');
		$this->bo->delete($this->pm_id, true);

		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Api\Link::run_notifies();

		$this->deleteElements();
	}


	/**
	 * Delete all the elements
	 */
	protected function deleteElements()
	{
		// Delete all elements
		foreach($this->elements as $id)
		{
			list($app, $id) = explode(':',$id);

			$bo_class = "{$app}_bo";

			// Delete each entry twice to make sure it's gone
			switch($app)
			{
				case 'calendar':
					$bo = new \calendar_boupdate();
					$bo->delete($id,0,true,true);
					$bo->delete($id,0,true,true);
					break;
				case 'infolog':
					$bo = new $bo_class();
					$bo->delete($id, true, false, true);
					$bo->delete($id, true, false, true);
					break;
				case 'projectmanager':
					$bo = new $bo_class();
					$bo->delete($id);
					$bo->delete($id);
					break;
				case 'timesheet':
					$bo = new $bo_class();
					$bo->delete($id);
					// Tell Timesheet to ignore ACL to make sure it's gone
					$bo->delete($id, true);
					break;
				case 'tracker':
					$bo = new $bo_class();
					// Once is enough for tracker, it doesn't support keeping things
					// after deleting
					$bo->delete($id);
					break;
			}
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
			if ($status)
			{
				$this->assertEquals($status, $element['pe_status'], "Project element {$element['pe_title']} status was {$element['pe_status']}, expected $status");
			}
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
		$element_bo = new \projectmanager_elements_bo();
		foreach($this->elements as $id)
		{
			list($app, $id) = explode(':', $id);

			$ds = $element_bo->datasource($app);
			$element = $ds->read($id);

			if($status == 'deleted')
			{
				// Depending on app settings for deletion, it may still be there
				//$this->assertEmpty($element);
			}
			else
			{
				$this->assertNotEmpty($element);
			}
		}
	}
}