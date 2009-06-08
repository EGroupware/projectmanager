<?php
/**
 * ProjectManager - Adminstration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * ProjectManager: Administration
 */
class projectmanager_admin
{
	/**
	 * @var array $public_functions Functions to call via menuaction
	 */
	var $public_functions = array(
		'config' => true,
	);
	var $accounting_types;
	var $duration_units;
	var $fonts;
	/**
	 * Instance of config class for projectmanager
	 *
	 * @var config
	 */
	var $config;

	/**
	 * Constructor, calls the constructor of the extended class
	 */
	function __construct()
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$GLOBALS['egw']->common->redirect_link('/index.php',array(
				'menuaction' => 'projectmanager.uiprojectmanger.index',
				'msg'        => lang('Permission denied !!!'),
			));
		}
		$this->config = new config('projectmanager');
		$this->config->read_repository();

		$this->accounting_types = array(
			'status' => lang('No accounting, only status'),
			'times'  => lang('No accounting, only times and status'),
			'budget' => lang('Budget (no pricelist)'),
			'pricelist' => lang('Budget and pricelist'),
		);
		$this->duration_units = array(
			'd' => 'days',
			'h' => 'hours',
		);
		$this->fonts = $this->get_fonts();
	}

	/**
	 * Edit the site configuration
	 *
	 * @param array $content=null
	 */
	function config($content=null)
	{
		$tpl = new etemplate('projectmanager.config');

		if ($content['save'] || $content['apply'])
		{
			$content['GANTT_FONT_FILE'] = $this->get_font_file($content['GANTT_FONT'],$content['GANTT_STYLE']);
			foreach(array('duration_units','hours_per_workday','accounting_types','allow_change_workingtimes',
				'GANTT_FONT','LANGUAGE_CHARSET','GANTT_STYLE','GANTT_CHAR_ENCODE','GANTT_FONT_FILE') as $name)
			{
				$this->config->config_data[$name] = $content[$name];
			}
			$this->config->save_repository();
			$msg = lang('Site configuration saved');
		}
		if ($content['cancel'] || $content['save'])
		{
			$tpl->location(array(
				'menuaction' => 'projectmanager.uiprojectmanager.index',
				'msg' => $msg,
			));
		}
		include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.projectmanager_ganttchart.inc.php');

		$content = $this->config->config_data;
		if (!$content['duration_units']) $content['duration_units'] = array_keys($this->duration_units);
		if (!$content['hours_per_workday']) $content['hours_per_workday'] = 8;
		if (!$content['accounting_types']) $content['accounting_types'] = array_keys($this->accounting_types);

		// Ganttchart config
		$content['jpg_msg'] = projectmanager_ganttchart::msg_install_new_jpgraph();
		if (!$content['GANTT_FONT']) $content['GANTT_FONT'] = GANTT_FONT;
		if (!$content['LANGUAGE_CHARSET']) $content['LANGUAGE_CHARSET'] = LANGUAGE_CHARSET;
		if (!$content['GANTT_STYLE']) $content['GANTT_STYLE'] = GANTT_STYLE;
		if (!isset($content['GANTT_CHAR_ENCODE'])) $content['GANTT_CHAR_ENCODE'] = GANTT_CHAR_ENCODE;
		if (is_readable(TTF_DIR.$content['GANTT_FONT_FILE']))
		{
			$content['font_msg'] = TTF_DIR.$content['GANTT_FONT_FILE'];
			$content['font_msg_class'] = '';
		}
		else
		{
			$content['font_msg'] = lang("Fontfile '%1' not found!!!",$content['GANTT_FONT_FILE']);
			$content['font_msg_class'] = 'redItalic';
		}
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Site configuration');
		$tpl->exec('projectmanager.projectmanager_admin.config',$content,array(
			'duration_units'   => $this->duration_units,
			'accounting_types' => $this->accounting_types,
			'allow_change_workingtimes' => array('no','yes'),
			'GANTT_FONT' => $this->get_fonts(),
			'GANTT_STYLE' => array(
				FS_NORMAL => lang('normal'),
				FS_BOLD   => lang('bold'),
				FS_ITALIC => lang('italic'),
				FS_BOLDITALIC => lang('bold').' & '.lang('italic'),
			),
			'GANTT_CHAR_ENCODE' => array(
				1  => 'yes',
				'0' => 'No',
			)
		));
	}

	/**
	 * Get font id - name pairs the installed jpgraph understands
	 *
	 * @return array
	 */
	function get_fonts()
	{
		$fonts = array();
		foreach(array(
			// MS web iniative
			FF_ARIAL     => 'Arial (MS)',
			FF_COMIC     => 'Comic (MS)',
			FF_COURIER   => 'Courier (MS)',
			FF_GEORGIA   => 'Georgia (MS)',
			FF_TIMES     => 'Times (MS)',
			FF_TREBUCHE  => 'Trebuche (MS)',
			FF_VERDANA   => 'Verdana (MS)',

			// Gnome Vera font
			// Available from http://www.gnome.org/fonts/
			FF_VERA      => 'Vera (Gnome)',
			FF_VERAMONO  => 'Veramono (Gnome)',
			FF_VERASERIF => 'Veraserif (Gnome)',

			// Chinese font
			FF_SIMSUN    => 'Simsun (Chinese)',
			FF_CHINESE   => 'Chinese (Chinese)',
			FF_BIG5      => 'Big5 (Chinese)',

			// Japanese font
			FF_MINCHO    => 'Mincho (Japanese)',
			FF_PMINCHO   => 'PMincho (Japanese)',
			FF_GOTHIC    => 'Gothic (Japanese)',
			FF_PGOTHIC   => 'PGothic (Japanese)',

			// Hebrew fonts
			FF_DAVID     => 'David (Hebrew)',
			FF_MIRIAM    => 'Miriam (Hebrew)',
			FF_AHRON     => 'Ahron (Hebrew)',

			// Extra fonts
			// Download fonts from
			// http://www.webfontlist.com
			// http://www.webpagepublicity.com/free-fonts.html

			FF_SPEEDO    => 'Speedo',		// This font is also known as Bauer (Used for gauge fascia)
			FF_DIGITAL   => 'Digital',		// Digital readout font
			FF_COMPUTER  => 'Computer',		// The classic computer font
			FF_CALCULATOR=> 'Calculator',	// Triad font
		) as $font => $label)
		{
			if (is_int($font))
			{
				$fonts[$font] = $label;
			}
		}
		return $fonts;
	}

	/**
	 * Get the path of the given font or an error-message
	 *
	 * @param int $font
	 * @param int $style
	 * @return string
	 */
	function get_font_file($font,$style)
	{
		if(file_exists(EGW_SERVER_ROOT . '/../jpgraph/src/jpg-config.inc.php'))
		{
			//echo "including jpg-config.inc.php";
			require_once(EGW_SERVER_ROOT . '/../jpgraph/src/jpg-config.inc.php');
			if(file_exists(EGW_SERVER_ROOT . '/../jpgraph/src/jpgraph_ttf.inc.php'))
			{
				//echo "including jpgraph_ttf.inc.php";
				require_once(EGW_SERVER_ROOT . '/../jpgraph/src/jpgraph_ttf.inc.php');
			}
		}
		elseif(file_exists(EGW_SERVER_ROOT . '/../jpgraph/src/jpg-config.inc'))
		{
			//echo "including jpg-config.inc";
			require_once(EGW_SERVER_ROOT . '/../jpgraph/src/jpg-config.inc');
		}
		else
		{
			//echo "falling back to vera";
			return 'Vera.ttf';
		}
		if (!defined('FF_ARIAL'))
		{
			DEFINE("FF_COURIER",10);
			DEFINE("FF_VERDANA",11);
			DEFINE("FF_TIMES",12);
			DEFINE("FF_COMIC",14);
			DEFINE("FF_ARIAL",15);
			DEFINE("FF_GEORGIA",16);
			DEFINE("FF_TREBUCHE",17);

			// Gnome Vera font
			// Available from http://www.gnome.org/fonts/
			DEFINE("FF_VERA",18);
			DEFINE("FF_VERAMONO",19);
			DEFINE("FF_VERASERIF",20);

			// Chinese font
			DEFINE("FF_SIMSUN",30);
			DEFINE("FF_CHINESE",31);
			DEFINE("FF_BIG5",31);

			// Japanese font
			DEFINE("FF_MINCHO",40);
			DEFINE("FF_PMINCHO",41);
			DEFINE("FF_GOTHIC",42);
			DEFINE("FF_PGOTHIC",43);

			// TTF Font styles
			DEFINE("FS_NORMAL",9001);
			DEFINE("FS_BOLD",9002);
			DEFINE("FS_ITALIC",9003);
			DEFINE("FS_BOLDIT",9004);
			DEFINE("FS_BOLDITALIC",9004);
		}
		// File names for available fonts from jpgraph_ttf
		$font_files=array(
		    FF_COURIER => array(FS_NORMAL	=>'cour.ttf',
					FS_BOLD		=>'courbd.ttf',
					FS_ITALIC	=>'couri.ttf',
					FS_BOLDITALIC	=>'courbi.ttf' ),
		    FF_GEORGIA => array(FS_NORMAL	=>'georgia.ttf',
					FS_BOLD		=>'georgiab.ttf',
					FS_ITALIC	=>'georgiai.ttf',
					FS_BOLDITALIC	=>'' ),
		    FF_TREBUCHE	=>array(FS_NORMAL	=>'trebuc.ttf',
					FS_BOLD		=>'trebucbd.ttf',
					FS_ITALIC	=>'trebucit.ttf',
					FS_BOLDITALIC	=>'trebucbi.ttf' ),
		    FF_VERDANA 	=> array(FS_NORMAL	=>'verdana.ttf',
					FS_BOLD		=>'verdanab.ttf',
					FS_ITALIC	=>'verdanai.ttf',
					FS_BOLDITALIC	=>'' ),
		    FF_TIMES =>   array(FS_NORMAL	=>'times.ttf',
					FS_BOLD		=>'timesbd.ttf',
					FS_ITALIC	=>'timesi.ttf',
					FS_BOLDITALIC	=>'timesbi.ttf' ),
		    FF_COMIC =>   array(FS_NORMAL	=>'comic.ttf',
					FS_BOLD		=>'comicbd.ttf',
					FS_ITALIC	=>'',
					FS_BOLDITALIC	=>'' ),
		    FF_ARIAL =>   array(FS_NORMAL	=>'arial.ttf',
					FS_BOLD		=>'arialbd.ttf',
					FS_ITALIC	=>'ariali.ttf',
					FS_BOLDITALIC	=>'arialbi.ttf' ) ,
		    FF_VERA =>    array(FS_NORMAL	=>'Vera.ttf',
					FS_BOLD		=>'VeraBd.ttf',
					FS_ITALIC	=>'VeraIt.ttf',
					FS_BOLDITALIC	=>'VeraBI.ttf' ),
		    FF_VERAMONO	=> array(FS_NORMAL	=>'VeraMono.ttf',
					 FS_BOLD	=>'VeraMoBd.ttf',
					 FS_ITALIC	=>'VeraMoIt.ttf',
					 FS_BOLDITALIC	=>'VeraMoBI.ttf' ),
		    FF_VERASERIF=> array(FS_NORMAL	=>'VeraSe.ttf',
					  FS_BOLD	=>'VeraSeBd.ttf',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ) ,

		    /* Chinese fonts */
		    FF_SIMSUN 	=>  array(FS_NORMAL	=>'simsun.ttc',
					  FS_BOLD	=>'simhei.ttf',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
		    FF_CHINESE 	=>   array(FS_NORMAL	=>CHINESE_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),

		    /* Japanese fonts */
	 	    FF_MINCHO 	=>  array(FS_NORMAL	=>MINCHO_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_PMINCHO 	=>  array(FS_NORMAL	=>PMINCHO_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_GOTHIC  	=>  array(FS_NORMAL	=>GOTHIC_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_PGOTHIC 	=>  array(FS_NORMAL	=>PGOTHIC_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_MINCHO 	=>  array(FS_NORMAL	=>PMINCHO_TTF_FONT,
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),

		    /* Hebrew fonts */
		    FF_DAVID 	=>  array(FS_NORMAL	=>'DAVIDNEW.TTF',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),

		    FF_MIRIAM 	=>  array(FS_NORMAL	=>'MRIAMY.TTF',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),

		    FF_AHRON 	=>  array(FS_NORMAL	=>'ahronbd.ttf',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),

		    /* Misc fonts */
	 	    FF_DIGITAL =>   array(FS_NORMAL	=>'DIGIRU__.TTF',
					  FS_BOLD	=>'Digirtu_.ttf',
					  FS_ITALIC	=>'Digir___.ttf',
					  FS_BOLDITALIC	=>'DIGIRT__.TTF' ),
	 	    FF_SPEEDO =>    array(FS_NORMAL	=>'Speedo.ttf',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_COMPUTER  =>  array(FS_NORMAL	=>'COMPUTER.TTF',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
	 	    FF_CALCULATOR => array(FS_NORMAL	=>'Triad_xs.ttf',
					  FS_BOLD	=>'',
					  FS_ITALIC	=>'',
					  FS_BOLDITALIC	=>'' ),
		);
		return $font_files[$font][$style];
	}
}
