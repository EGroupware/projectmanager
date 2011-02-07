<?php
/**
 * ProjectManager - eTemplate widgets
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
		'post_process' => True,
	);
	/**
	 * @var array $human_name availible extensions and there names for the editor
	 */
	var $human_name = array(
		'projectmanager-select'			=> 'Select Project',
		'projectmanager-pricelist'		=> 'Select Price',
		'projectmanager-select-erole'  	=> 'Select Element role',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function __construct($ui)
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

		$readonly = $cell['readonly'] || $readonlys;
		switch ($cell['type'])
		{
			case 'projectmanager-select':
				if (!is_object($GLOBALS['projectmanager_bo']))
				{
					$GLOBALS['projectmanager_bo'] = new projectmanager_bo();
				}
				$cell['sel_options'] = $GLOBALS['projectmanager_bo']->link_query('');
				if ($value && !isset($cell['sel_options'][$value]) && ($title = $GLOBALS['projectmanager_bo']->link_title($value)))
				{
					$cell['sel_options'][$value] = $title;
				}
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
							$this->pricelist = new projectmanager_pricelist_bo();
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
				
			case 'projectmanager-select-erole': // $type2: extraStyleMultiselect
				list($rows,$type,$type2) = explode(',',$cell['size']);
				$eroles = new projectmanager_eroles_bo();
				if ($readonly)
				{
					$cell['no_lang'] = True;
					if ($value)
					{
						if (!is_array($value)) $value = explode(',',$value);
						foreach($value as $key => $id)
						{
							if ($id && ($name = $eroles->id2title($id)))
							{
								$cell['sel_options'][$id] = $name.$eroles->get_info($id);
							}
							else
							{
								unset($value[$key]);	// remove not (longer) existing or inaccessible eroles
							}
						}
					}
					break;
				}
				
				foreach($eroles->get_free_eroles() as $id => $data)
				{
					$cell['sel_options'][$data['role_id']] = array(
						'label' => $data['role_title'].$eroles->get_info($data['role_id']),
						'title' => $data['role_description'],
					);
				}
				
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				$cell['no_lang'] = True;
				unset($rows,$type,$type2);
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
	
	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param object &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		switch ($extension_data['type'])
		{
			case 'projectmanager-select-erole':
				$value = null;
				if(is_array($value_in)) $value = implode(',',$value_in);
				break;
			// fall through
			default:
				$value = $value_in;
				break;
		}
		//echo "<p>select_widget::post_process('$name',,'$extension_data',,,'$value_in'): value='$value', is_null(value)=".(int)is_null($value)."</p>\n";
		return true;
	}

}
