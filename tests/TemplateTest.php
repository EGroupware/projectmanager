<?php


namespace EGroupware\Projectmanager;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api\Config;
use Egroupware\Api\Etemplate;
use Egroupware\Api\Link;


/**
 * Test creating a project from a template
 *
 */
class TemplateTest extends \EGroupware\Api\AppTest
{

	protected $ui;
	protected $bo;

	// Template ID, so we can check if it gets deleted
	protected $pm_id;
	// Project made using using template
	protected $cloned_id;

	// List of element IDs so we can check if they get deleted
	protected $elements = array();


	public function setUp()
	{
		$this->ui = new \projectmanager_ui();
		// I have no idea why this has to be after the call to new \projectmanager_ui(),
		// but it fails to find the Etemplate class otherwise
		$this->ui->template = $this->etemplate = $this->createPartialMock(Etemplate::class, array('exec','read'));

		$this->bo = $this->ui;

		$this->mockTracking($this->bo, 'projectmanager_tracking');

		$this->makeProject('template');
	}

	public function tearDown()
	{
		$this->bo = new \projectmanager_bo();

		// Delete template
		$this->deleteProject($this->pm_id);
		// Delete clone
		$this->deleteProject($this->cloned_id);

		$this->bo = null;

		// Projectmanager sets a lot of global stuff
		unset($GLOBALS['projectmanager_bo']);
		unset($GLOBALS['projectmanager_elements_bo']);
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
			->will($this->returnCallback(function($method, $content) {
				$_content = $content;
					return is_array($content) && count($content) > 0;
				}));

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

		// Template contains a sub-project, which pushes pm_id up by 1 more
		$this->cloned_id = ((int)$this->bo->data['pm_id'])-1;
		$this->assertNotEquals(-1, $this->cloned_id);
		$this->assertNotEquals($this->pm_id, $this->cloned_id);

		//echo "Original ID: {$this->pm_id} Cloned ID: {$this->cloned_id}\n";

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
			'pm_title'          =>	'Auto-test for ' . $this->getName(),
			'pm_status'         =>	$status,
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
		$this->assertGreaterThan(0, count($GLOBALS['egw_info']['apps']),
			'No apps found to use as projectmanager elements'
		);

		// Make one with a custom from
		$this->make_infolog(true);

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
		Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->bo);
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
		$element = array(
			'title' => "Test calendar for #{$this->pm_id}",
			'des'   => 'Test element as part of the project for test ' . $this->getName(),
			'start' => \time(),
			'end'   => \time() + 60,
			'pm_id'	=> $this->pm_id,
		);
		$element_id = $bo->save($element);
		Link::link('calendar',$element_id,'projectmanager',$this->pm_id);
		$this->elements[] = 'calendar:'.$element_id;
	}

	/**
	 * Make an infolog entry and add it to the project
	 */
	protected function make_infolog($custom_from = false)
	{
		$bo = new \infolog_bo();
		$element = array(
			'info_subject' => "Test infolog for #{$this->pm_id}",
			'info_des'     => 'Test element as part of the project for test ' . $this->getName(),
			'info_status'  => 'open',
			'pm_id'	=> $this->pm_id
		);

		if($custom_from)
		{
			$element['info_des'] .= "\nCustom from";
			$element += array(
				'info_from' => 'Custom from',
				'info_contact' => array('search' => 'Custom from')
			);
		}

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
			'ts_description' => 'Test element as part of the project for test ' . $this->getName(),
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
			'tr_description' => 'Test element as part of the project for test ' . $this->getName(),
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

		$this->bo->delete($pm_id, true);
		// Delete again to purge
		$this->bo->delete($pm_id, true);

		// Force links to run notification now, or elements might stay
		// usually waits until Egw::on_shutdown();
		Link::run_notifies();

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
		$element_count = 0;
		$indexed_elements = array();
		$unmatched_elements = $this->elements;
		// First infolog has a custom from
		$first_infolog = true;

		foreach($element_bo->search(array('pm_id' => $clone_id), false) as $element)
		{
			//echo "\tPM:".$element['pm_id'] . ' '.$element['pe_app'] . ':'.$element['pe_app_id'] . "\t".$element['pe_title']."\n";
			$indexed_elements[$element['pe_app']][] = $element;
		}
		foreach($this->elements as $key => $_id)
		{
			list($app, $id) = explode(':', $_id);
			$copied = array_shift($indexed_elements[$app]);

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
					$this->assertNotNull($copied, "$app entry $_id did not get cloned");
					// Also check pm_id & info_from
					$info_bo = new \infolog_bo();
					$info = $info_bo->read($copied['pe_app_id']);
					$this->assertEquals($clone_id, $info['pm_id']);

					if($first_infolog)
					{
						$this->assertNotEquals(Link::title('projectmanager', $clone_id), $info['info_from'], 'Custom from got lost');
						$first_infolog = false;
					}
					else
					{
						$this->assertEquals(Link::title('projectmanager', $clone_id), $info['info_from']);
					}
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

		$this->assertCount(0, $unmatched_elements, "Incorrect number of elements");
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

			switch ($app)
			{
				case 'calendar':
					// Calendar doesn't really have a status
					$check_status = $status != 'deleted' ? '' : $status;
					break;
				case 'projectmanager':
					// PM is active, not open
					$check_status = $status == 'open' || $status == 'not-started' ? 'active' : $status;
					break;
				case 'tracker':
					$check_status = $status == 'open' || $status == 'not-started' ? 'Open(status)' : $status;
					break;
				case 'timesheet':
					// Timesheet is almost always active
					$check_status = $status != 'deleted' ? 'active' : $status;
					break;
				default:
					$check_status = $status;
					break;
			}
			$ds = $element_bo->datasource($app);
			$element = $ds->read($id);

			$this->assertEquals($check_status, $element['pe_status'],
				"$app datasource status was {$element['pe_status']}, expected $status" . ($check_status == $status ? '' : " / $check_status")
			);
		}
	}
}