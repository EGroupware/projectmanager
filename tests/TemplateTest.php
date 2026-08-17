<?php


namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api\Etemplate;
use EGroupware\Api\Link;


/**
 * Test creating a project from a template
 *
 */
class TemplateTest extends \EGroupware\Api\AppTest
{
	protected $debug = false;

	protected $ui;
	protected $bo;

	// Template ID, so we can check if it gets deleted
	protected $pm_id;
	// Project made using using template
	protected $cloned_id;

	// List of element IDs so we can check if they get deleted
	protected $elements = array();

	protected function setUp() : void
	{
		// Make sure a 'TEST'/'SUB-TEST' fixture left behind by a previous test (eg. an earlier
		// test class whose own cleanup got interrupted) doesn't collide with makeProject()'s
		// insert below - same guard as DeleteTest::setUp().
		$cleanup_bo = new \projectmanager_bo();
		foreach(array('TEST', 'SUB-TEST') as $number)
		{
			$project = $cleanup_bo->read(Array('pm_number' => $number));
			if($project && $project['pm_id'])
			{
				$cleanup_bo->history = '';
				$cleanup_bo->delete($project['pm_id']);
			}
		}

		$this->ui = new \projectmanager_ui();
		// I have no idea why this has to be after the call to new \projectmanager_ui(),
		// but it fails to find the Etemplate class otherwise
		$this->ui->template = $this->etemplate = $this->createPartialMock(Etemplate::class, array('exec','read'));

		$this->bo = $this->ui;

		$this->mockTracking($this->bo, 'projectmanager_tracking');

		$this->makeProject('template');
	}

	protected function tearDown() : void
	{
		$this->bo = new \projectmanager_bo();

		// Nested try/finally: if deleting the template throws, the clone must still get a
		// cleanup attempt, and the global unset at the end must still run either way -
		// otherwise a stuck $GLOBALS bo silently breaks unrelated tests running later in
		// the same PHPUnit process.
		try
		{
			// Delete template
			$this->deleteProject($this->pm_id);
		}
		finally
		{
			try
			{
				// Delete clone
				$this->deleteProject($this->cloned_id);
			}
			finally
			{
				$this->bo = null;

				// Projectmanager sets a lot of global stuff
				unset($GLOBALS['projectmanager_bo']);
				unset($GLOBALS['projectmanager_elements_bo']);
			}
		}
	}

	public function testCreateFromTemplate()
	{
		$this->bo->tracking->expects($this->any())
				->method('track');


		// Force links to run notification now so we get valid testing - it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		// Mock the etemplate call
		// First time so UI can set up the content array
		$this->etemplate->expects($this->exactly(2))
			->method('exec')
			->willReturnCallback(function ($method, $content)
			{
				$_content = $content;
				return is_array($content) && count($content) > 0;
			});

		// Create new from template
		$_GET['template'] = $this->pm_id;
		$this->ui->edit();

		// Could maybe do some checks here...

		// Save
		$content = $this->bo->data;
		$content['apply'] = true;
		$content['template'] = $this->pm_id;
		$content['pm_title'] = 'Created from template';

		// Mock the etemplate call to get ID
		$this->ui->edit($content);

		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		// Template contains a sub-project, which pushes pm_id up by 1 more
		$this->cloned_id = $this->pm_id + 2;
		if (in_array("projectmanager:{$this->cloned_id}", $this->elements))
		{
			$this->fail("Could not find clone ID.  Got sub-project's ID instead.");
		}
		$this->assertNotEquals(-1, $this->cloned_id);
		$this->assertNotEquals($this->pm_id, $this->cloned_id);

		if ($this->debug)
		{
			echo "Original ID: {$this->pm_id} Cloned ID: {$this->cloned_id}\n";
			echo "Original Project: " . Link::title("projectmanager", $this->pm_id) . "\n";
			echo "Copy project: " . Link::title("projectmanager", $this->cloned_id) . "\n";
		}

		// Check that elements are there
		$this->checkClonedElements($this->cloned_id);

		// Check datasources are there
		$this->checkDatasources('open');
	}

	/**
	 * Make a project so we can test with it
	 */
	protected function makeProject($status = 'active')
	{
		$project = array(
			'pm_number'         =>	'TEST',
			'pm_title'          =>	'Auto-test for ' . $this->name(),
			'pm_status'         =>	$status,
			'pm_description'    =>	'Test project for ' . $this->name()
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
		// Accept int or numeric string: Storage\Base::read() never casts DB columns (they come
		// back as strings from mysqli), and any intervening read of this project - eg. via
		// notification processing - re-hydrates pm_id as a string. Only the numeric value matters.
		$this->assertTrue(is_numeric($this->bo->data['pm_id']) && $this->bo->data['pm_id'] > 0,
			'pm_id is not a positive number: '.var_export($this->bo->data['pm_id'], true));
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
			if (method_exists($this, "make_$app"))
			{
				$this->{"make_$app"}();
			}
			else
			{
				$this->markTestIncomplete("$app has a datasource, but cannot be tested - add a make_$app() function to " . get_class());
			}
		}

		// We got this far, there should be elements
		$this->assertGreaterThan(0, count($this->elements), "No project elements created");

		if ($this->debug)
		{
			echo __METHOD__ . " Created test elements: \n";
			print_r($this->elements);
		}

		// Force links to run notification now, or we won't get elements since it
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->pm_id);
		$elements->sync_all($this->pm_id);

		// Make sure all elements are created
		$this->checkOriginalElements(false, count($this->elements), "Unable to create all project elements");
	}

	/**
	 * Make an infolog entry and add it to the project
	 */
	protected function make_calendar()
	{
		$bo = new \calendar_boupdate();
		$start = new \EGroupware\Api\DateTime('now');
		$end = clone $start;
		$end->modify('+1 minute');
		$element = array(
			'title' => "Test calendar for #{$this->pm_id}",
			'des'   => 'Test element as part of the project for test ' . $this->name(),
			'start'        => $start,
			'end'          => $end,
			'owner'        => $GLOBALS['egw_info']['user']['account_id'],
			'participants' => [$GLOBALS['egw_info']['user']['account_id'] => 'A'],
			'pm_id'	=> $this->pm_id,
		);
		$element_id = $bo->save($element);
		Link::link('calendar',$element_id,'projectmanager',$this->pm_id);
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
			'info_des'     => 'Test element as part of the project for test ' . $this->name(),
			'info_status'  => 'open',
			'pm_id'	=> $this->pm_id
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
			'pm_description'    =>	'Test project for ' . $this->name()
		);
		$bo->save();
		$element_id = $bo->data['pm_id'];
		Link::link('projectmanager',$this->pm_id,'projectmanager',$element_id);
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
			'ts_description' => 'Test element as part of the project for test ' . $this->name(),
			'ts_status'      => null,
			'ts_owner'       => $GLOBALS['egw_info']['user']['account_id'],
			'ts_start'       => \time()
		);
		$bo->save();
		$element_id = $bo->data['ts_id'];
		Link::link(TIMESHEET_APP,$element_id,'projectmanager',$this->pm_id);
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
			'tr_description' => 'Test element as part of the project for test ' . $this->name(),
			'tr_status'      => \tracker_bo::STATUS_OPEN,
			'tr_owner'       => $GLOBALS['egw_info']['user']['account_id']
		);
		$bo->save();
		$element_id = $bo->data['tr_id'];
		Link::link('tracker',$element_id,'projectmanager',$this->pm_id);
		$this->elements[] = 'tracker:'.$element_id;
	}

	/**
	 * Fully delete a project and its elements, no matter what state or settings
	 */
	protected function deleteProject($pm_id)
	{
		// Reset, or it'll just return its data instead of reading from DB
		$this->bo->data = array();

		if(!$pm_id)
		{
			$pm_id = $this->pm_id;
		}
		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

		try
		{
			$this->bo->delete($pm_id, true);
			// Delete again to purge
			$this->bo->delete($pm_id, true);
		}
		finally
		{
			// deleteElements() must still run even if the project delete itself threw -
			// otherwise directly-created fixture elements (timesheet, tracker, ...) never
			// get their own explicit double-delete below and leak permanently.
			// Force links to run notification now, or elements might stay
			// usually waits until Egw::on_shutdown();
			Link::run_notifies();

			$this->deleteElements();
		}
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

			// Each case is wrapped so one element's delete failure (eg. an ACL check, or
			// any other exception) can't abort the loop and leave later elements - notably
			// timesheet/tracker, last in iteration order - permanently orphaned.
			try
			{
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
			catch (\Throwable $e)
			{
				error_log(__METHOD__."() failed to delete $app:$id: ".$e);
			}
		}
	}

	/**
	 * Check that the project elements are present, and have the provided status.
	 *
	 * @param String $status
	 */
	protected function checkOriginalElements($status = '', $expected_count = 0)
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
	 * Check that the project elements are present, and have the provided status.
	 *
	 * @param String $status
	 */
	protected function checkClonedElements($clone_id)
	{
		$element_bo = new \projectmanager_elements_bo();
		$element_bo->pm_id = $clone_id;
		$indexed_elements = array();
		$unmatched_elements = $this->elements;

		foreach ((array)$element_bo->search(array('pm_id' => $clone_id), false, 'pe_id ASC') as $element)
		{
			if ($this->debug)
			{
				echo "\tPM:" . $element['pm_id'] . ' ' . $element['pe_id'] . "\t" . $element['pe_app'] . ':' . $element['pe_app_id'] . "\t" . $element['pe_title'] . "\n" . Link::title($element['pe_app'], $element['pe_app_id']) . "\n";
			}
			$indexed_elements[$element['pe_app']][] = $element;
		}
		foreach ($this->elements as $key => $_id)
		{
			list($app, $id) = explode(':', $_id);

			$copied = is_array($indexed_elements[$app]) ? array_shift($indexed_elements[$app]) : null;

			if ($this->debug)
			{
				echo "$_id:\tCopied element - PM:" . $copied['pm_id'] . ' ' . $copied['pe_app'] . ':' . $copied['pe_app_id'] . "\t" . $copied['pe_title'] . "\n";
			}
			switch ($app)
			{
				case 'timesheet':
					// Timesheet does not support copying, so won't be there
					$this->assertNull($copied, "$app entry $_id got linked");
					unset($unmatched_elements[$key]);
					continue 2;
				case 'calendar':
					// Calendar does not copy, but it does link to the original event
					$this->assertNotNull($copied, "$app entry $_id is missing");
					unset($unmatched_elements[$key]);
					continue 2;
				case 'infolog':
					$this->assertNotNull($copied, "$app entry $_id did not get copied");
					// Also check pm_id & info_from
					$info_bo = new \infolog_bo();
					$entry = $info_bo->read($copied['pe_app_id']);
					$this->assertEquals($clone_id, $entry['pm_id']);

					// Make sure ID is actually different - copied, not linked
					$this->assertNotEquals($id, $copied['pe_app_id']);

					unset($unmatched_elements[$key]);
					break;
				default:
					$this->assertNotNull($copied, "$app entry $_id did not get linked");
					unset($unmatched_elements[$key]);
					break;
			}
		}

		// Check that we found them all
		$this->assertEmpty($unmatched_elements, 'Missing copied elements ' . \array2string($unmatched_elements));
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
