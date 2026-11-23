<?php
/**
 * The element list shows the elements of the project whose read access it checked
 *
 * @package projectmanager
 * @subpackage tests
 */

namespace EGroupware\Projectmanager;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

/**
 * The list of project elements gets its project as col_filter[pm_id] from the client. It checked
 * read access for that value cast to an integer, but then queried with the value as sent. An
 * array cast to integer is 1, whatever it contains: a list naming only projects the user may not
 * read was checked as project 1, and returned their elements.
 *
 * Expanding the row of a sub-project asks for its elements with the row id
 * "projectmanager:<pm_id>:<pe_id>" as parent, which reaches get_rrows() as col_filter[parent_id],
 * together with csv_export='children'. That skipped the check of the project, and the elements of
 * the project named in the row id were listed, whatever it was.
 *
 * Read access is given by a stub here, only to project 1, so no projects are needed. The stub
 * records which projects were checked for read access.
 */
class ElementListProjectAclTest extends \EGroupware\Api\AppTest
{
	const READABLE = 1;
	const NOT_READABLE = 999999999;

	protected $pref_project;

	/**
	 * @var int[] projects checked for read access
	 */
	protected $checked = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->pref_project = $GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] ?? null;
	}

	protected function tearDown() : void
	{
		unset($GLOBALS['projectmanager_elements_bo']);
		$GLOBALS['egw_info']['user']['preferences']['projectmanager']['current_project'] = $this->pref_project;
		parent::tearDown();
	}

	/**
	 * Query the element list with the given col_filter[pm_id]
	 *
	 * @param mixed $pm_id
	 * @param ?int &$total =null on return the total get_rrows() returned
	 * @return array the query as get_rrows() leaves it
	 */
	protected function query($pm_id, ?int &$total=null, array $extra=[]) : array
	{
		$ui = new \projectmanager_elements_ui();
		$ui->project = $this->createStub(\projectmanager_bo::class);
		$this->checked = [];
		$ui->project->method('check_acl')->willReturnCallback(function($required, $data=0)
		{
			if ($required === \EGroupware\Api\Acl::READ) $this->checked[] = $data;
			return $data === self::READABLE;
		});
		$query = $extra + [
			'start'      => 1,	// not the first page, which adds the project itself as row
			'num_rows'   => 10,
			'filter'     => '',
			'filter2'    => 0,
			'cat_id'     => '',
			'col_filter' => ['pm_id' => $pm_id],
			'order'      => 'pe_modified',
			'sort'       => 'DESC',
		];
		// state of the list is already the stored one, so no preferences are saved
		$ui->prefs['pe_index_state'] = serialize(array_intersect_key($query, array_flip(['filter', 'filter2', 'cat_id', 'order', 'sort'])));
		$rows = $readonlys = [];
		$total = (int)$ui->get_rrows($query, $rows, $readonlys);
		foreach($rows as $row)
		{
			if (is_array($row) && isset($row['pm_id']))
			{
				$this->assertEquals(self::READABLE, $row['pm_id'], 'element of another project listed');
			}
		}
		return $query;
	}

	public function testListOfProjectsIsRefused()
	{
		$this->query([(string)self::NOT_READABLE], $total);
		$this->assertSame(0, $total, 'a list of projects must not be checked as project 1');

		$this->query([(string)self::READABLE, (string)self::NOT_READABLE], $total);
		$this->assertSame(0, $total);
	}

	public function testProjectNotReadableIsRefused()
	{
		$this->query((string)self::NOT_READABLE, $total);
		$this->assertSame(0, $total);

		$this->query(self::READABLE.' OR 1=1', $total);
		$this->assertSame(0, $total);
	}

	public function testReadableProjectIsListed()
	{
		$query = $this->query((string)self::READABLE, $total);

		$this->assertSame(self::READABLE, $query['col_filter']['pm_id']);
		$this->assertGreaterThan(0, $total, 'the project itself counts as a row');
	}

	/**
	 * Elements of an expanded sub-project, the way the nextmatch asks for them
	 */
	protected function children($parent, ?int &$total=null) : array
	{
		return $this->query(null, $total, ['csv_export' => 'children', 'start' => 0,
			'col_filter' => ['parent_id' => $parent]]);
	}

	public function testSubProjectNotReadableIsRefused()
	{
		$this->children('projectmanager:'.self::NOT_READABLE.':0', $total);
		$this->assertSame(0, $total);
		$this->assertContains(self::NOT_READABLE, $this->checked, 'project of the expanded row not checked');
	}

	public function testRowOtherThanASubProjectIsRefused()
	{
		foreach(['infolog:'.self::READABLE.':0', 'projectmanager:'.self::READABLE.' OR 1=1:0', ['projectmanager', self::READABLE]] as $parent)
		{
			$this->children($parent, $total);
			$this->assertSame(0, $total);
			$this->assertNotContains(self::READABLE, $this->checked, 'checked and listed project 1 for '.json_encode($parent));
		}
	}

	public function testReadableSubProjectIsChecked()
	{
		$this->children('projectmanager:'.self::READABLE.':0', $total);
		// the rows themselves may check further projects, eg. the sub-projects among them
		$this->assertSame(self::READABLE, $this->checked[0] ?? null, 'project of the expanded row not checked first');
	}
}
