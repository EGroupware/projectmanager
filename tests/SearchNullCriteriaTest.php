<?php
/**
 * EGroupware Projectmanager: regression test for search() with a null $criteria
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
class SearchNullCriteriaTest extends \EGroupware\Api\AppTest
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
}
