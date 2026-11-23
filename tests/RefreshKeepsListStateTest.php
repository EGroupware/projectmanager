<?php
/**
 * EGroupware Projectmanager: refreshing single rows of the pricelist does not replace its stored state
 *
 * @link https://www.egroupware.org
 * @package projectmanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * The pricelist stores the query of every request in the session, and restores it when the list is
 * opened again. A refresh of single rows (after a push, or a save in a popup) is a query for just
 * those rows: the filters of the list plus col_filter[pl_id], with csv_export='refresh'. Stored,
 * the list showed only the refreshed rows the next time it was opened.
 */
class RefreshKeepsListStateTest extends LoggedInTest
{
	const STORED = ['filter' => 'stored state of the list', 'col_filter' => []];

	protected $before;

	protected function setUp() : void
	{
		parent::setUp();
		$this->before = Api\Cache::getSession('projectmanager', 'pricelist');
		Api\Cache::setSession('projectmanager', 'pricelist', self::STORED);
	}

	protected function tearDown() : void
	{
		Api\Cache::setSession('projectmanager', 'pricelist', $this->before);
		parent::tearDown();
	}

	protected function getRows(array $query) : void
	{
		$query += ['start' => 0, 'num_rows' => 1, 'order' => 'pl_title', 'sort' => 'ASC', 'filter' => '', 'search' => '',
			'col_filter' => []];
		$rows = $readonlys = [];
		(new \projectmanager_pricelist_ui())->get_rrows($query, $rows, $readonlys);
	}

	public function testRefreshOfSingleRowsIsNotStored()
	{
		$this->getRows(['csv_export' => 'refresh', 'col_filter' => ['pl_id' => [0]]]);

		$this->assertSame(self::STORED, Api\Cache::getSession('projectmanager', 'pricelist'));
	}

	public function testQueryOfTheListIsStored()
	{
		$this->getRows(['col_filter' => ['pl_id' => '']]);

		$this->assertNotSame(self::STORED, Api\Cache::getSession('projectmanager', 'pricelist'));
		$this->assertSame('pl_title', Api\Cache::getSession('projectmanager', 'pricelist')['order'] ?? null);
	}
}
