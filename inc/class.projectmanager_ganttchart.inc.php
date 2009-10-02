<?php
/**
 * ProjectManager - Gantchart creation
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// JPGraph does not work, if that got somehow set, so unset it
if (isset($GLOBALS['php_errormsg'])) unset ($GLOBALS['php_errormsg']);

// check if the admin installed a recent JPGraph parallel to eGroupWare
if(file_exists(EGW_SERVER_ROOT . '/../jpgraph/src/jpgraph.php'))
{
	$GLOBALS['egw_info']['apps']['projectmanager']['config'] = config::read('projectmanager');

	foreach(array(
		'TTF_DIR'          => '',
		'LANGUAGE_CHARSET' => 'iso-8859-1',
		'GANTT_FONT'       => 15, //FF_ARIAL
		'GANTT_FONT_FILE'  => 'arial.ttf',
		'GANTT_STYLE'      => 9002, //FS_BOLD,
		'GANTT_CHAR_ENCODE'=> false,
	) as $name => $default)
	{
		if (isset($GLOBALS['egw_info']['apps']['projectmanager']['config'][$name]))
		{
			define($name,$GLOBALS['egw_info']['apps']['projectmanager']['config'][$name]);
		}
		elseif($name == 'TTF_DIR')
		{
			if (!($font_file = $GLOBALS['egw_info']['apps']['projectmanager']['config']['GANTT_FONT_FILE'])) $font_file = 'arial.ttf';
			// using the OS font dir if we can find it, otherwise fall back to our bundled Vera font
			foreach(array(
				'/usr/X11R6/lib/X11/fonts/truetype/',	// linux / *nix default
				'/usr/share/fonts/ja/TrueType/',		// japanese fonts
				'/usr/share/fonts/msttcorefonts/', 		// to install this fonts see http://www.aditus.nu/jpgraph/jpdownload.php
				'C:/windows/fonts/',					// windows default
				// add your location here or to egw_config.config_value for config_app='projectmanger' AND config_name='TTF_DIR'
				EGW_SERVER_ROOT.'/projectmanager/inc/ttf-bitstream-vera-1.10/',	// our bundled Vera font
			) as $dir)
			{
				if (@is_dir($dir) && (is_readable($dir.$font_file) || is_readable($dir.'Vera.ttf')))
				{
					define('TTF_DIR',$GLOBALS['egw_info']['apps']['projectmanager']['config'][$name]=$dir);
					if (!is_readable($dir.$font_file))	// fallback to our bundled Vera font
					{
						$GLOBALS['egw_info']['apps']['projectmanager']['config']['GANTT_FONT'] = 18;	// FF_VERA
						$GLOBALS['egw_info']['apps']['projectmanager']['config']['GANTT_FONT_FILE'] = 'Vera.ttf';
					}
					break;
				}
			}
		}
		elseif($default)
		{
			define($name,$GLOBALS['egw_info']['apps']['projectmanager']['config'][$name]=$default);
		}
	}
	//_debug_array($GLOBALS['egw_info']['apps']['projectmanager']['config']);
	if (!defined('MBTTF_DIR')) define('MBTTF_DIR',TTF_DIR);

	include(EGW_SERVER_ROOT . '/../jpgraph/src/jpgraph.php');
	include(EGW_SERVER_ROOT . '/../jpgraph/src/jpgraph_gantt.php');
}
else
{
	include(EGW_SERVER_ROOT . '/projectmanager/inc/jpgraph-1.5.2/src/jpgraph.php');
	include(EGW_SERVER_ROOT . '/projectmanager/inc/jpgraph-1.5.2/src/jpgraph_gantt.php');
	define('TTF_DIR',EGW_SERVER_ROOT.'/projectmanager/inc/ttf-bitstream-vera-1.10/');
	define('GANTT_FONT',FF_VERA);
	define('GANTT_STYLE',FS_BOLD);
	define('LANGUAGE_CHARSET','iso-8859-1');
	define('GANTT_CHAR_ENCODE',true);
}

/**
 * ProjectManager: Ganttchart creation
 */
class projectmanager_ganttchart extends projectmanager_elements_bo
{
	/**
	 * @var array $public_functions Functions to call via menuaction
	 */
	var $public_functions = array(
		'create' => true,
		'show'   => true,
	);
	/**
	 * true if JPGraph version > 1.13
	 *
	 * @var boolean
	 */
	var $modernJPGraph;
	/**
	 * Charset used internaly by eGW, $GLOBALS['egw']->translation->charset()
	 *
	 * @var string
	 */
	var $charset;
	/**
	 * Font used for the Gantt Chart, in the form used by JPGraphs SetFont method
	 *
	 * @var string
	 */
	var $gantt_font = GANTT_FONT;
	/**
	 * Charset used by the above font
	 *
	 * @var string
	 */
	var $gantt_charset = LANGUAGE_CHARSET;
	/**
	 * Should non-ascii chars be encoded
	 *
	 * @var boolean
	 */
	var $gantt_char_encode = GANTT_CHAR_ENCODE;

	var $debug;
	var $scale_start,$scale_end;
	var $tmpl;
	var $prefs;

	/**
	 * Constructor, calls the constructor of the extended class
	 */
	function __construct()
	{
		$this->tmpl = new etemplate();

		if (!check_load_extension($php_extension='gd') || !function_exists('imagecopyresampled'))
		{
			$this->tmpl->Location(array(
				'menuaction' => 'projectmanager.uiprojectmanager.index',
				'msg'        => lang("Necessary PHP extentions %1 not loaded and can't be loaded !!!",$php_extension),
			));
		}
		$this->modernJPGraph = version_compare('1.13',JPG_VERSION) < 0;
		//echo "version=".JPG_VERSION.", modernJPGraph=".(int)$this->modernJPGraph; exit;
		if ($debug) error_log("JPG_VERSION=".JPG_VERSION.", modernJPGraph=".(int)$this->modernJPGraph);

		if (isset($_REQUEST['pm_id']))
		{
			$pm_id = (int) $_REQUEST['pm_id'];
			$GLOBALS['egw']->session->appsession('pm_id','projectmanager',$pm_id);
		}
		else
		{
			$pm_id = $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
		}
		if (!$pm_id)
		{
			$this->tmpl->Location(array(
				'menuaction' => 'projectmanager.uiprojectmanager.index',
				'msg'        => lang('You need to select a project first'),
			));
		}
		parent::__construct($pm_id);

		// check if we have at least read-access to this project
		if (!$this->project->check_acl(EGW_ACL_READ))
		{
			$GLOBALS['egw']->redirect_link('/index.php',array(
				'menuaction' => 'projectmanager.uiprojectmanager.index',
				'msg'        => lang('Permission denied !!!'),
			));
		}
		$this->charset = $GLOBALS['egw']->translation->charset();
		$this->prefs =& $GLOBALS['egw_info']['user']['preferences'];

		// check if a arial font is availible and set FF_VERA (or bundled font) if not
		if (!is_readable((FF_MINCHO <= GANTT_FONT && GANTT_FONT <= FF_PGOTHI ? MBTTF_DIR : TTF_DIR).GANTT_FONT_FILE))
		{
			$this->gantt_font = FF_VERA;
		}
	}

	/**
	 * Converts text from eGW's internal encoding to something understood by JPGraph / GD
	 *
	 * The only working thing I found so far is numeric html-entities from iso-8859-1.
	 * If you found other encoding do work, please let mit know: RalfBecker-AT-outdoor-training.de
	 * It would be nice if we could use the full utf-8 charset, if supported by the used font.
	 *
	 * @param string $text
	 * @return string
	 */
	function text_encode($text)
	{
		// convert to the charset used for the gantchart
		$text = $GLOBALS['egw']->translation->convert($text,$this->charset,$this->gantt_charset);

		// convert everything above ascii to nummeric html entities
		// not sure if this is necessary for non iso-8859-1 charsets, try to comment it out if you have problems
		if ($this->gantt_char_encode) $text = preg_replace('/[^\x00-\x7F]/e', '"&#".ord("$0").";"',$text);

		return $text;
	}

	/**
	 * create a new JPGraph Ganttchart object and setup a fitting scale
	 *
	 * @param string $title
	 * @param string $subtile
	 * @param int $start startdate of the ganttchart
	 * @param int $end enddate of the ganttchart
	 * @return object GantGraph
	 */
	function &new_gantt($title,$subtitle,$start,$end,$width=940)
	{
		//error_log(__METHOD__."($title,$subtitle,$start=".date('Y-m-d H:i:s',$start).",$end=".date('Y-m-d H:i:s',$end).",$width)");
		// create new graph object
		$graph = new GanttGraph($width,-1,'auto');

		$graph->SetShadow();
		$graph->SetBox();

		// set the start and end date
		$graph->SetDateRange(date('Y-m-d',$start), date('Y-m-d',$end));

		// some localizations
		if ($this->modernJPGraph)
		{
			$graph->scale->SetDateLocale(common::setlocale());
		}
		elseif ($this->prefs['common']['lang'] == 'de')
		{
			$graph->scale->SetDateLocale(LOCALE_DE);	// others use english
		}
		// set start-day of the week from the cal-pref weekdaystarts
		$weekdaystarts2day = array(
			'Sunday'   => 0,
			'Monday'   => 1,
			'Saturday' => 6,
		);
		$weekdaystarts = $this->prefs['calendar']['weekdaystarts'];
		if ($this->modernJPGraph && isset($weekdaystarts2day[$weekdaystarts]))
		{
			$graph->scale->SetWeekStart($weekdaystarts2day[$weekdaystarts]);
		}
		// get and remember the real start- & enddates, to clip the bar for old JPGraph versions
		$this->scale_start = $graph->scale->iStartDate;
		$this->scale_end   = $graph->scale->iEndDate;

		$days = round(($this->scale_end - $this->scale_start) / 86400);
		$month = $days / 31;
		//echo date('Y-m-d',$this->scale_start).' - '.date('Y-m-d',$this->scale_end).' '.$month;
		// 2 weeks and less: day (weekday date) and hour headers, only possible with modern JPGraph
		if($this->modernJPGraph && $days <= 1 && $width > 600)
		{
			$graph->ShowHeaders(GANTT_HHOUR | GANTT_HMIN);
			$graph->scale->hour->SetStyle($this->prefs['common']['timeformat'] == 12 ? HOURSTYLE_HAMPM : HOURSTYLE_H24);
			$graph->scale->hour->SetIntervall(1);
			if ($width < 900) $graph->scale->minute->SetIntervall(30);
		}
		elseif($this->modernJPGraph && $days <= 14)
		{
			$graph->ShowHeaders(GANTT_HDAY | GANTT_HHOUR);
			$graph->scale->day->SetStyle($days < 7 ? DAYSTYLE_LONGDAYDATE1 : DAYSTYLE_SHORTDATE3);
			$graph->scale->hour->SetStyle($this->prefs['common']['timeformat'] == 12 ? HOURSTYLE_HAMPM : HOURSTYLE_H24);
			foreach(array(8=>6,5=>4,3=>2,2=>1) as $max => $int)
			{
				if ($days >= $max) break;
			}
			//echo "days=$days => $int ($max)";
			$graph->scale->hour->SetIntervall($int);
		}
		// 1.5 month and less: month (with year), week and day (date) headers
		elseif($month <= 1.5)
		{
			$graph->ShowHeaders(GANTT_HMONTH | GANTT_HWEEK | GANTT_HDAY);
			$graph->scale->month->SetStyle(MONTHSTYLE_LONGNAMEYEAR4);
			if ($this->modernJPGraph) $graph->scale->day->SetStyle(DAYSTYLE_SHORTDATE4);
		}
		// 2.5 month and less: month (with year), week (week and startday) and day headers
		elseif($month <= 2.5)
		{
			$graph->ShowHeaders(GANTT_HMONTH | GANTT_HWEEK | GANTT_HDAY);
			if ($this->modernJPGraph) $graph->scale->week->SetStyle(WEEKSTYLE_FIRSTDAYWNBR);
			$graph->scale->month->SetStyle(MONTHSTYLE_LONGNAMEYEAR4);
		}
		// 6 month and less: year, month and week (with weeknumber) headers
		elseif($month <= 6) // half year
		{
			$graph->ShowHeaders(GANTT_HYEAR | GANTT_HMONTH | GANTT_HWEEK);
			$graph->scale->month->SetStyle(MONTHSTYLE_LONGNAME);
		}
		// more then 6 month: only year and month headers
		else
		{
			$graph->ShowHeaders(GANTT_HYEAR | GANTT_HMONTH);
		}
		// Change the font scale
		$graph->scale->week->SetFont($this->gantt_font,FS_NORMAL,8);
		$graph->scale->year->SetFont($this->gantt_font,GANTT_STYLE,10);

		// Title & subtitle
		$graph->title->Set($this->text_encode($title));
		$graph->title->SetFont($this->gantt_font,GANTT_STYLE,12);
		$graph->subtitle->Set($this->text_encode($subtitle));
		$graph->subtitle->SetFont($this->gantt_font,FS_NORMAL,10);

		return $graph;
	}

	/**
	 * Ganttbar for a project
	 *
	 * @param array $pm project or project-element data array
	 * @param int $level hierarchy level, 0=main project
	 * @param int $line line-number of the gantchart, starting with 0
	 * @param boolean $planned_times=false show planned or real start- and end-dates
	 * @return object GanttBar
	 */
	function &project2bar($pm,$level,$line,$planned_times=false)
	{
		if ($pm['pe_id'])
		{
			$pe =& $pm;
		}
		else
		{
			$pe = array(
				'pm_id' => $pm['pm_id'],
				'pe_id' => 0,
			);
			foreach($pm as $key => $val)
			{
				if ($key != 'pm_id') $pe[str_replace('pm_','pe_',$key)] =& $pm[$key];
			}
		}
		$bar =& $this->element2bar($pe,$level,$line,$planned_times);

		// set project-specific attributes: bold, solid bar, ...
		$bar->title->SetFont($this->gantt_font,GANTT_STYLE,!$level ? 9 : 8);
		$bar->SetPattern(BAND_SOLID,"#9999FF");

		if ($this->modernJPGraph && !$pe['pe_id'])	// main-project
		{
			$link = $GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'projectmanager.uiprojectmanager.view',
				'pm_id'      => $pe['pm_id'],
			));
			$title = lang('View this project');

			$bar->SetCSIMTarget($link,$title);
			$bar->title->SetCSIMTarget($link,$title);
		}
		return $bar;
	}

	/**
	 * Ganttbar for a project-element
	 *
	 * @param array $pe projectelement-data array
	 * @param int $level hierarchy level, 0=main project
	 * @param int $line line-number of the gantchart, starting with 0
	 * @param boolean $planned_times=false show planned or real start- and end-dates
	 * @return object GanttBar
	 */
	function &element2bar($pe,$level,$line,$planned_times=false)
	{
		// create a shorter title (removes dates from calendar-titles and project-numbers from sub-projects
		if ($pe['pe_app'] == 'calendar' || $pe['pe_app'] == 'projectmanager')
		{
			list(,$title) = explode(': ',$pe['pe_title'],2);
		}
		if (!$title) $title = $pe['pe_title'];

		if ((int) $this->debug >= 1)
		{
			echo "<p>GanttBar($line,'".($level ? str_repeat(' ',$level) : '').
				$this->text_encode($title).'   '."','".
				date('Y-m-d H:i',$pe['pe_start'])."','".date('Y-m-d H:i',$pe['pe_end'])."','".
				round($pe['pe_completion']).'%'."',0.5)</p>\n";
		}
		if (!$this->modernJPGraph)	// for an old JPGraph we have to clip the bar ourself
		{
			if ($pe['pe_start'] < $this->scale_start) $pe['pe_start'] = $this->scale_start;
			if ($pe['pe_end'] > $this->scale_end) $pe['pe_end'] = $this->scale_end-1;
		}
		$bar = new GanttBar($line,($level ? str_repeat(' ',$level) : '').
			$this->text_encode($title).
			($level ? '  ' : ''),	// fix for wrong length calculation in JPGraph
			date('Y-m-d'.($this->modernJPGraph ? ' H:i' : ''),$pe['pe_start']),
			date('Y-m-d'.($this->modernJPGraph ? ' H:i' : ''),$pe['pe_end']),
			round($pe['pe_completion']).'%',0.5);

		$bar->progress->Set($pe['pe_completion']/100);
		$bar->progress->SetHeight(0.5);

		if ($this->modernJPGraph && $pe['pe_id'])
		{
			$bar->SetCSIMTarget('@600x450'.$GLOBALS['egw']->link('/index.php',array(	// @ = popup
				'menuaction' => 'projectmanager.uiprojectelements.view',
				'pm_id'      => $pe['pm_id'],
				'pe_id'      => $pe['pe_id'],
			)),$pe['pe_remark'] ? $pe['pe_remark'] : lang('View this project-element'));

			if (($popup = egw_link::is_popup($pe['pe_app']))) $popup = '@'.$popup;
			$bar->title->SetCSIMTarget($popup.$GLOBALS['egw']->link('/index.php',egw_link::view($pe['pe_app'],$pe['pe_app_id'])),
				lang('View this element in %1',lang($pe['pe_app'])));
		}
		$bar->title->SetFont($this->gantt_font,FS_NORMAL,!$level ? 9 : 8);

		return $bar;
	}

	/**
	 * Milestone
	 *
	 * @param array $milestone data-array
	 * @param int $level hierarchy level, 0=main project
	 * @param int $line line-number of the gantchart, starting with 0
	 * @return object MileStone
	 */
	function &milestone2bar($milestone,$level,$line)
	{
		if ((int) $this->debug >= 1)
		{
		 	echo "<p>MileStone($line,'$milestone[ms_title],".
		 		date('Y-m-d',$milestone['ms_date']).','.
				date($this->prefs['common']['dateformat'],$milestone['ms_date']).")</p>\n";
		}
		$ms = new MileStone($line,($level ? str_repeat(' ',$level) : '').
			$this->text_encode($milestone['ms_title']),
			date('Y-m-d',$milestone['ms_date']),
			date($this->prefs['common']['dateformat'],$milestone['ms_date']));

		$ms->title->SetFont($this->gantt_font,FS_ITALIC,8);
		$ms->title->SetColor('blue');
		$ms->mark->SetColor('black');
		$ms->mark->SetFillColor('blue');

		if ($this->modernJPGraph)
		{
			$link = $GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'projectmanager.uimilestones.view',
				'pm_id'      => $milestone['pm_id'],
				'ms_id'      => $milestone['ms_id'],
			));
			$title = lang('View this milestone');
			$ms->SetCSIMTarget('@600x450'.$link,$title);	// @ = popup
			$ms->title->SetCSIMTarget('@600x450'.$link,$title);
		}
		return $ms;
	}

	/**
	 * Adds all elements of project $pm_id to the ganttchart, calls itself recursive for subprojects
	 *
	 * @param int $pm_id project-id
	 * @param array $params
	 * @param int &$line line-number of the gantchart, starting with 0, gets incremented
	 * @param array &$bars bars are added here, with their pe_id as key
	 * @param int $level hierarchy level starting with 1, function stops if $level > $params['deepth']
	 */
	function add_elements($pm_id,$params,&$line,&$bars,$level=1)
	{
		static $filter=false;
		static $extra_cols;

		if (!$filter)	// we do this only once for all shown projects
		{
			// defining start- and end-times depending on $params['planned_times'] and the availible data
			foreach(array('start','end') as $var)
			{
				if ($params['planned_times'])
				{
					$$var = "CASE WHEN pe_planned_$var IS NULL THEN pe_real_$var ELSE pe_planned_$var END";
				}
				else
				{
					$$var = "CASE WHEN pe_real_$var IS NULL THEN pe_planned_$var ELSE pe_real_$var END";
				}
			}
			$filter = array(
				"pe_status != 'ignore'",
				"$start IS NOT NULL",
				"$end IS NOT NULL",
				"$start <= ".(int)$this->scale_end,	// starts befor the end of our period AND
				"$end >= ".(int)$this->scale_start,	// ends after the start of our period
				'cumulate' => true,
			);
			switch ($params['filter'])
			{
				case 'not':
					$filter['pe_completion'] = 0;
					break;
				case 'ongoing':
					$filter[] = 'pe_completion!=100';
					break;
				case 'done':
					$filter['pe_completion'] = 100;
					break;
			}
			if ($params['pe_resources'])
			{
				$filter['pe_resources'] = $params['pe_resources'];
			}
			$extra_cols = array(
				$start.' AS pe_start',
				$end.' AS pe_end',
			);
			if ($params['cat_id'])
			{
				$filter['cat_id'] = $params['cat_id'];
			}
			else
			{
				unset($filter['cat_id']);
			}
		}
		$filter['pm_id'] = $pm_id;	// this is NOT static

		$pe_id2line = array();
		foreach((array) $this->search(array(),false,'pe_start,pe_end',$extra_cols,
			'',false,'AND',false,$filter) as $pe)
		{
			//echo "$line: ".print_r($pe,true)."<br>\n";
			if (!$pe) continue;

			$pe_id = $pe['pe_id'];
			$pe_id2line[$pe_id] = $line;	// need to remember the line to draw the constraints
			$pes[$pe_id] = $pe;

			if ($pe['pe_app'] == 'projectmanager')
			{
				$bars[$pe_id] =& $this->project2bar($pe,$level,$line++,$params['planned_times']);
			}
			else
			{
				$bars[$pe_id] =& $this->element2bar($pe,$level,$line++,$params['planned_times']);
			}
			// if we should display further levels, we call ourself recursive
			if ($pe['pe_app'] == 'projectmanager' && $level < $params['depth'])
			{
				$this->add_elements($pe['pe_app_id'],$params,$line,$bars,$level+1);
			}
		}
		if ($params['constraints'] && $this->modernJPGraph)		// the old jpgraph does not support constrains
		{
			// adding milestones
			foreach((array)$this->milestones->search(array(),'pm_id,ms_id,ms_title,ms_date','ms_date','','',false,'AND',false,array(
				'pm_id' => $pm_id,
				(int)$this->scale_start.' <= ms_date',
				'ms_date <= '.(int)$this->scale_end,
			)) as $milestone)
			{
				if (!$milestone || !($ms_id = $milestone['ms_id'])) continue;

				$ms_id2line[$ms_id] = $line;
				$milestones[$ms_id] = $milestone;
				$bars[-$ms_id] =& $this->milestone2bar($milestone,$level,$line++);
			}
			// adding the constraints to the bars
			foreach((array)$this->constraints->search(array('pm_id'=>$pm_id)) as $constraint)
			{
				$pe_id = $constraint['pe_id_end'];	// start of the array at the end of this pe
				if (isset($bars[$pe_id]))
				{
					$bar =& $bars[$pe_id];

					if ($constraint['pe_id_start'] && isset($pe_id2line[$constraint['pe_id_start']]))
					{
						// show violated constrains in red
						$color = $pes[$constraint['pe_id_start']]['pe_start'] >= $pes[$pe_id]['pe_end'] ? 'black' : 'red';
						$bar->SetConstrain($pe_id2line[$constraint['pe_id_start']],CONSTRAIN_ENDSTART,$color);
					}
					if ($constraint['ms_id'] && isset($ms_id2line[$constraint['ms_id']]))
					{
						// show violated constrains in red
						$color = $milestones[$constraint['ms_id']]['ms_date'] >= $pes[$pe_id]['pe_end'] ? 'black' : 'red';
						$bar->SetConstrain($ms_id2line[$constraint['ms_id']],CONSTRAIN_ENDSTART,$color);
					}
				}
			}
		}
	}

	/**
	 * Create a ganttchart
	 *
	 * @param array $params=null params, if (default) null, use them from the URL
	 * @param string $filename='' filename to store the chart or (default) empty to send it direct to the browser
	 */
	function create($params=null,$filename='',$imagemap='ganttchar')
	{
		if (!$params) $params = $this->url2params($params);

		$title = lang('project overview').': '.(is_numeric($params['pm_id']) ? $this->project->data['pm_title'] : '');
		$subtitle = lang('from %1 to %2',date($this->prefs['common']['dateformat'],$params['start']),date($this->prefs['common']['dateformat'],$params['end']));
		// create new graph-object and set scale_{start|end}
		$graph =& $this->new_gantt($title,$subtitle,$params['start'],$params['end'],$params['width']);

		if ($params['start'] < $this->now_su && $this->now_su < $params['end'])
		{
			// add a vertical line to mark today
			$graph->add(new GanttVLine($this->now_su-24*60*60,
				date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],$this->now_su)));
		}
		$line = 0;
		$bars = array();
		foreach(explode(',',$params['pm_id']) as $pm_id)
		{
			if ($pm_id != $this->project->data['pm_id'])
			{
				if (!$this->project->read($pm_id) || !$this->project->check_acl(EGW_ACL_READ))
				{
					continue;
				}
				// set used start- and end-times of the project
				self::_set_start_end($this->project->data,$params['planned_times']);
			}
			$graph->Add($this->project2bar($this->project->data,0,$line++,$params['planned_times']));

			if ($params['depth'] > 0)
			{
				$this->add_elements($this->project->data['pm_id'],$params,$line,$bars);
			}
		}
		foreach($bars as $pe_id => $bar)
		{
			$graph->Add($bar);
		}
		if (!$this->debug) $graph->Stroke($filename);

		if ($filename && $imagemap) return $graph->GetHTMLImageMap($imagemap);
	}

	/**
	 * read the ganttchart params from the URL
	 *
	 * @param array $params already set parameters, default empty array
	 * @return array with params
	 */
	function url2params($params = array())
	{
		if ((int) $this->debug >= 1) echo "<p>ganttchart::url2params(".print_r($params,true).")</p>\n";

		if (!count($params))
		{
			if (!($params = $GLOBALS['egw']->session->appsession('ganttchart','projectmanager')))
			{
				$params = array(				// some defaults, if called the first time
					'constraints' => true,
				);
			}
			// check if project changed => not use start and end
			if ((int)$params['pm_id'] != $this->project->data['pm_id'])
			{
				$params['pm_id'] = $this->project->data['pm_id'];
				unset($params['start']);
				unset($params['end']);
			}
		}
		$data =& $this->project->data;
		// set used start- and end-times of the project
		self::_set_start_end($data,$params['planned_times']);

		foreach(array('start','end') as $var)
		{
			// set start- and end-times of the ganttchart
			if (isset($_GET[$var]))
			{
				$params[$var] = $_GET[$var];
			}
			elseif (isset($params[$var]) && $params[$var])
			{
				// already set
			}
			elseif ($data['pm_id'] && $data['pm_'.$var])
			{
				$params[$var] = $data['pm_'.$var];
			}
			else
			{
				$params[$var] = $var == 'start' ? date('Y-m-1') : date('Y-m-1',time()+61*24*60*60);
			}
			$params[$var] = is_numeric($params[$var]) ? (int) $params[$var] : strtotime($params[$var]);

			if ((int) $this->debug >= 1) echo "<p>$var=".$params[$var].'='.date('Y-m-d',$params[$var])."</p>\n";
		}
		if ((int) $_GET['width'])
		{
			$params['width'] = (int) $_GET['width'];
		}
		else
		{
			$params['width'] = $this->tmpl->innerWidth -
				($this->prefs['common']['auto_hide_sidebox'] ? 60 : 245);
		}
		if (!isset($params['pm_id']) && $this->project->data['pm_id'])
		{
			$params['pm_id'] = $this->project->data['pm_id'];
		}
		if (!isset($params['depth'])) $params['depth'] = 1;

		if ($_GET['pm_id'] && !is_numeric($_GET['pm_id']))
		{
			$params['pm_id'] = $_GET['pm_id'];
		}
		$GLOBALS['egw']->session->appsession('ganttchart','projectmanager',$params);
		if ((int) $this->debug >= 1) _debug_array($params);

		return $params;
	}

	/**
	 * set used start- and end-times of the project
	 *
	 * @param array &$data
	 * @param boolean $planned_times use planned or real times, fallback to the other if not set
	 */
	private function _set_start_end(&$data,$planned_times)
	{
		foreach(array('start','end') as $var)
		{
			if ($planned_times && $data['pe_planned_'.$var] || !$data['pe_real_'.$var])
			{
				$data['pm_'.$var] = $data['pm_planned_'.$var];
			}
			else
			{
				$data['pm_'.$var] = $data['pm_real_'.$var];
			}
		}
	}

	/**
	 * Return message to install a new jpgraph version
	 *
	 * @static
	 * @return string/boolean message or false if a new version is installed
	 */
	function msg_install_new_jpgraph()
	{
		return version_compare('1.13',JPG_VERSION) < 0 ? false :
			lang('You are using the old version of JPGraph, bundled with eGroupWare. It has only limited functionality.').
			"<br />\n".lang('Please download a recent version from %1 and install it in %2.',
			'<a href="http://www.aditus.nu/jpgraph/jpdownload.php" target="_blank">www.aditus.nu/jpgraph</a>',
			realpath(EGW_SERVER_ROOT.'/..').SEP.'jpgraph');
	}

	/**
	 * Shows a ganttchart
	 *
	 * As ganttcharts contain an image-map and the image, we save the image as a temporary file.
	 * This for performance reasons, it saves a second creation / script-run.
	 * projectmanager/ganttchart.php reads and output the temporary file/image and unlinks it after.
	 */
	function show($content=array(),$msg='')
	{
		if ($content['sync_all'] && $this->project->check_acl(EGW_ACL_ADD))
		{
			$msg = lang('%1 element(s) updated',$this->sync_all());
			unset($content['sync_all']);
		}
		// run $_GET[msg] through htmlspecialchars, as we output it raw, to allow the link in the jpgraph message.
		if (!$msg) $msg = html::htmlspecialchars($_GET['msg']);

		if (!$GLOBALS['egw']->session->appsession('ganttchart','projectmanager') && !$this->modernJPGraph)
		{
			$msg .= $this->msg_install_new_jpgraph();
		}
		unset($content['update']);
		$content = $this->url2params($content);

		$tmp = $GLOBALS['egw_info']['server']['temp_dir'];
		if (!is_dir($tmp) || !is_writable($tmp))
		{
			$tmp = '';
		}
		$img = tempnam($tmp,'ganttchart');
		$img_name = basename($img);

		$map = $this->create($content,$img,'ganttchart');
		// replace the regular links with popups
		$map = preg_replace('/href="@(\d+)x(\d+)([^"]+)"/i','href="#" onclick="egw_openWindowCentered2(\'\\3\',\'_blank\',\'dependent=yes,width=\\1,height=\\2,scrollbars=yes,status=yes\'); return false;"'
		,$map);

		$content['ganttchart'] = $GLOBALS['egw']->link('/projectmanager/ganttchart.php',$content+array(
			'img'   => $img_name,
		));
		$content['map'] = $map;
		$content['msg'] = $msg;

		$sel_options = array(
			'depth' => array(
				0  => '0: '.lang('Mainproject only'),
				1  => '1: '.lang('Project-elements'),
				2  => '2: '.lang('Elements of elements'),
				3  => '3: '.lang('Elements of elements'),
				99 => lang('Everything recursive'),
			),
			'filter' => array(
				''        => lang('All'),
				'not'     => lang('Not started (0%)'),
				'ongoing' => lang('0ngoing (0 < % < 100)'),
				'done'    => lang('Done (100%)'),
			),
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Ganttchart').': '.$this->project->data['pm_title'];;
		$this->tmpl->read('projectmanager.ganttchart');
		return $this->tmpl->exec('projectmanager.projectmanager_ganttchart.show',$content,$sel_options,'',array('pm_id'=>$content['pm_id']));
	}
}
