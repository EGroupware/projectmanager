<?php
/**
 * ProjectManager - eTemplate widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * ProjectManager: eTemplate widgets
 *
 * The Select Price Widget show the pricelist of the project with pm_id=$content['pm_id']!!!
 */
class projectmanager_widget
{
	/** 
	 * @var array $public_functions exported methods of this class
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * @var array $human_name availible extensions and there names for the editor
	 */
	var $human_name = array(
		'projectmanager-select'    => 'Select Project',
		'projectmanager-pricelist' => 'Select Price',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function projectmanager_widget($ui)
	{
		$this->ui = $ui;
	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets 
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		static $pricelist = array();
		// check if user has rights to run projectmanager
		if (!$GLOBALS['egw_info']['user']['apps']['projectmanager'])
		{
			$cell = $tmpl->empty_cell();
			$value = '';
			return false;
		}
		$extension_data['type'] = $cell['type'];

		switch ($cell['type'])
		{
			case 'projectmanager-select':
				if (!is_object($GLOBALS['boprojectmanager']))
				{
					CreateObject('projectmanager.boprojectmanager');	// assigns itselft to $GLOBALS['boprojectmanager']
				}
				$cell['sel_options'] = $GLOBALS['boprojectmanager']->link_query('');
				if (!$cell['help']) $cell['help'] = /*lang(*/ 'Select a project' /*)*/;
				break;

			case 'projectmanager-pricelist':			// rows, pm_id-var, price-var
				list($rows,$pm_id_var,$price_var) = explode(',',$cell['size']);
				if (!$pm_id_var) $pm_id_var = 'pm_id';	// where are the pm_id(s) storered
				$pm_ids = $tmpl->content[$pm_id_var];
				$cell['sel_options'] = array();
				foreach((array) $pm_ids as $pm_id)
				{
					// some caching for the pricelist, in case it's needed multiple times
					if (!isset($pricelist[$pm_id]))
					{
						if (!is_object($this->pricelist))
						{
							require_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.bopricelist.inc.php');
							$this->pricelist =& new bopricelist();
						}
						$pricelist[$pm_id] = $this->pricelist->pricelist($pm_id);
					}
					if (!is_array($pricelist[$pm_id])) continue;

					foreach($pricelist[$pm_id] as $pl_id => $label) 
					{
						if (!isset($cell['sel_options'][$pl_id]))
						{
							$cell['sel_options'][$pl_id] = $label;
						}
						// if pl_id already used as index, we use pl_id-price as index
						elseif (preg_match('/\(([0-9.,]+)\)$/',$label,$matches) && 
								!isset($cell['sel_options'][$pl_id.'-'.$matches[1]]))
						{
							$cell['sel_options'][$pl_id.'-'.$matches[1]] = $label;
						}
					}
				}
				// check if we have a match with pl_id & price --> use it
				if ($price_var && ($price = $tmpl->content[$price_var]) && isset($cell['sel_options'][$value.'-'.$price]))
				{
					$value .= '-'.$price;
				}
				$cell['size'] = $rows;	// as the other options are not understood by the select-widget

				if (!$cell['help']) $cell['help'] = /*lang(*/ 'Select a price' /*)*/;
				break;
		}
		$cell['no_lang'] = True;
		$cell['type'] = 'select';
		if ($rows > 1)
		{
			unset($cell['sel_options']['']);
		}
		return True;	// extra Label Ok
	}
}
