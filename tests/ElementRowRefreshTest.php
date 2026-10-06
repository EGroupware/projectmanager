<?php
/**
 * Single-row refresh of an element that lives in another project than the one being viewed.
 *
 * @package projectmanager
 * @subpackage tests
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;
use EGroupware\Api\Link;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * The nextmatch refreshes single rows (after a push) by asking for their row-id, "elem_id",
 * together with the filters of the list, which carry the viewed project as pm_id. A row shown
 * in an expanded sub-project belongs to that sub-project, so the project filter has to follow
 * the requested elements, or the row comes back empty.
 */
class ElementRowRefreshTest extends \EGroupware\Api\AppTest
{
	protected $bo;
	protected $viewed;
	protected $sub;
	protected $pe_id;
	protected $info_id;
	protected $pref_project;

	protected function setUp() : void
	{
		Link::run_notifies();
		$this->pref_project = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] ?? null;
		$this->bo = new \projectmanager_bo();
		$this->purgeStale();
		$this->viewed = $this->makeProject('TEST-ROW-REFRESH-A');
		$this->sub = $this->makeProject('TEST-ROW-REFRESH-B');

		$infolog = new \infolog_bo();
		$values = array(
			'info_type'    => 'task',
			'info_subject' => 'Element for ' . $this->name(),
			'info_status'  => 'ongoing',
		);
		$this->info_id = $infolog->write($values, false, false, true, true);
		$this->assertNotFalse($this->info_id, 'could not make the linked infolog entry');
		Link::link('infolog', $this->info_id, 'projectmanager', $this->sub);
		Link::run_notifies();

		$elements = new \projectmanager_elements_bo($this->sub);
		$elements->sync_all($this->sub);
		foreach($elements->search(array('pm_id' => $this->sub), false) as $row)
		{
			$this->pe_id = (int)$row['pe_id'];
			break;
		}
		$this->assertNotEmpty($this->pe_id, 'no project element was created');
		$this->assertNotEquals($this->viewed, $this->sub, 'the fixture needs two different projects');
	}

	protected function tearDown() : void
	{
		$problems = array();
		foreach(array($this->viewed, $this->sub) as $pm_id)
		{
			if (!$pm_id) continue;
			try
			{
				$this->bo->delete(array('pm_id' => $pm_id), true);
			}
			catch (\Exception $e)
			{
				$problems[] = get_class($e) . ': ' . $e->getMessage();
			}
			if ($GLOBALS['egw']->db->select('egw_pm_projects', 'pm_number', array('pm_id' => $pm_id),
				__LINE__, __FILE__, false, '', 'projectmanager')->fetchColumn() !== false)
			{
				$problems[] = "fixture project #$pm_id is still in egw_pm_projects";
			}
		}
		if ($this->info_id)
		{
			try
			{
				// soft-deletes first, a second call purges where history allows
				$infolog = new \infolog_bo();
				$infolog->delete($this->info_id, false, false, true);
				$infolog->delete($this->info_id, false, false, true);
			}
			catch (\Exception $e)
			{
				$problems[] = get_class($e) . ': ' . $e->getMessage();
			}
			if ($GLOBALS['egw']->db->select('egw_infolog', 'info_status', array('info_id' => $this->info_id),
				__LINE__, __FILE__, false, '', 'infolog')->fetchColumn() !== false)
			{
				// history keeps the row, it only exists because setUp() made it
				$GLOBALS['egw']->db->delete('egw_infolog', array('info_id' => $this->info_id),
					__LINE__, __FILE__, 'infolog');
			}
		}
		// the unlinks of the cleanup above are otherwise only notified on shutdown, when the db is gone
		Link::run_notifies();
		unset($GLOBALS['projectmanager_elements_bo']);
		unset($_REQUEST['pm_id']);
		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $this->pref_project;
		$this->viewed = $this->sub = $this->pe_id = $this->info_id = null;
		if ($problems)
		{
			throw new \RuntimeException(implode('; ', $problems));
		}
	}

	protected function purgeStale() : void
	{
		foreach(array('TEST-ROW-REFRESH-A', 'TEST-ROW-REFRESH-B') as $number)
		{
			$project = (new \projectmanager_so())->read(array('pm_number' => $number));
			if ($project && $project['pm_id'])
			{
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
	}

	protected function makeProject(string $number) : int
	{
		// a bo keeps the project it saved last in ->data, and would update it instead of adding a new one
		$bo = new \projectmanager_bo();
		$this->assertFalse((bool)$bo->save(array(
			'pm_number' => $number,
			'pm_title'  => 'Auto-test for ' . $this->name(),
			'pm_status' => 'active',
		), true, false), 'Error making test project');
		return (int)$bo->data['pm_id'];
	}

	/**
	 * Rows the way the nextmatch asks for a single refreshed row of the list showing $pm_id
	 */
	protected function refresh(int $pm_id, string $elem_id) : array
	{
		$_REQUEST['pm_id'] = $pm_id;
		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $pm_id;
		$ui = new \projectmanager_elements_ui();
		$query = array(
			'start'      => 0,
			'num_rows'   => 10,
			'filter'     => 'all',
			'filter2'    => 0,
			'col_filter' => array('pm_id' => $pm_id, 'elem_id' => array($elem_id)),
			'order'      => 'pe_modified',
			'sort'       => 'DESC',
		);
		$rows = $readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);
		// the first page also carries the viewed project itself as a pseudo-row without a pe_id
		return array_values(array_filter($rows, static function($row)
		{
			return is_array($row) && !empty($row['pe_id']) && !empty($row['elem_id']);
		}));
	}

	public function testElementOfAnotherProjectIsFoundWhileViewingTheParent()
	{
		$elem_id = 'infolog:' . $this->info_id . ':' . $this->pe_id;
		$rows = $this->refresh($this->viewed, $elem_id);

		$this->assertCount(1, $rows, 'the refreshed row of the sub-project must not come back empty');
		$this->assertSame($elem_id, $rows[0]['elem_id']);
	}

	public function testElementOfTheViewedProjectIsStillFound()
	{
		$elem_id = 'infolog:' . $this->info_id . ':' . $this->pe_id;

		$this->assertCount(1, $this->refresh($this->sub, $elem_id));
	}

	public function testUnknownElementMatchesNothing()
	{
		$this->assertSame(array(), $this->refresh($this->viewed, 'infolog:1:999999999'));
	}
}
