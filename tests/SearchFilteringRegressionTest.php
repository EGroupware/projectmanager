<?php
/**
 * EGroupware Projectmanager: regression tests for search()'s SqlFragment-related filtering bugs
 *
 * @link https://www.egroupware.org
 * @package projectmanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * Ticket #127681: projectmanager_so::search()'s RAG-search branch leaves $criteria as null once
 * Rag\Embedding::search2criteria() has already added its own filter condition - that's a "nothing
 * more to add" signal, not a condition in its own right. The unconditional `$filter[] = $criteria`
 * right after it used to splice that null straight into the filter array regardless, which
 * eventually reached Storage::is_cf()/the SqlFragment enforcement with a value neither was built to
 * accept. A caller passing null directly (exactly what that RAG branch leaves behind) reproduces
 * the same shape without needing RAG itself configured.
 */
class SearchFilteringRegressionTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \projectmanager_bo
	 */
	protected $bo;

	protected function setUp() : void
	{
		$this->bo = new \projectmanager_bo();
	}

	/**
	 * null and '' both mean "no search criteria" - they must behave identically. Comparing
	 * against the empty-string case (which never took the RAG branch, so was never at risk)
	 * pins the behaviour without depending on whatever default status/ACL scope search() applies.
	 */
	public function testSearchWithNullCriteriaDoesNotThrow()
	{
		$withEmptyString = $this->bo->search('', false);
		$withNull = $this->bo->search(null, false);

		$this->assertIsArray($withNull, 'search(null) must return rows, not throw');
		$this->assertSame(
			array_column($withEmptyString, 'pm_id'),
			array_column($withNull, 'pm_id'),
			'null criteria must return exactly the same rows as an empty-string criteria'
		);
	}

	/**
	 * Ticket #127681's other symptom: on an install without RAG/fulltext search available (the
	 * common case), a free-text search term takes the legacy search2criteria()/parse_search()
	 * path instead. parse_search() collapses its result into a single Api\Db\SqlFragment whenever
	 * the search op isn't 'AND' (which searching across several columns always sets it to) - not a
	 * plain string or array any more. The append right after it only checked is_string()/is_array(),
	 * so that SqlFragment matched neither, and the whole search condition was silently dropped -
	 * every project matched instead of just the searched-for one, with no error to notice.
	 */
	public function testLegacyFreeTextSearchActuallyFilters()
	{
		$apps = $GLOBALS['egw_info']['apps'];
		unset($GLOBALS['egw_info']['apps']['rag']);	// force the legacy search path

		try
		{
			$this->bo->save(array(
				'pm_number'      => 'TEST-LEGACYSEARCH-MATCH-'.uniqid(),
				'pm_title'       => 'Paetzold GmbH website relaunch',
				'pm_status'      => 'active',
				'pm_description' => 'Auto-test for ' . $this->name(),
			), true, false);
			$match_id = $this->bo->data['pm_id'];

			$bo2 = new \projectmanager_bo();
			$bo2->save(array(
				'pm_number'      => 'TEST-LEGACYSEARCH-OTHER-'.uniqid(),
				'pm_title'       => 'Completely unrelated project',
				'pm_status'      => 'active',
				'pm_description' => 'Auto-test for ' . $this->name(),
			), true, false);
			$other_id = $bo2->data['pm_id'];

			$ids = array_map('intval', array_column((array)$this->bo->search('paetzold', false), 'pm_id'));

			$this->assertContains($match_id, $ids, 'the matching project must be found');
			$this->assertNotContains($other_id, $ids, 'an unrelated project must NOT be found - the search must actually filter');
		}
		finally
		{
			$GLOBALS['egw_info']['apps'] = $apps;
			if (isset($match_id)) $this->bo->delete(array('pm_id' => $match_id), true);
			if (isset($other_id)) $this->bo->delete(array('pm_id' => $other_id), true);
		}
	}
}
