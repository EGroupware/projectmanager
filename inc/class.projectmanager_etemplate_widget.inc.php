<?php
/**
 * EGroupware - eTemplate serverside date widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate2 project manager widgets
 */
class projectmanager_etemplate_widget extends etemplate_widget_transformer
{
	protected static $transformation = array(
		'type' => array(
			'projectmanager-select' => 'menupopup',
			'projectmanager-pricelist' => 'menupopup',
			'projectmanager-select-erole' => 'menupopup',
		)
	);

	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = '';

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);
		if (!is_array(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = array();

		if ($this->type)
		{
			$pm_widget = new projectmanager_widget();
			if($this->is_readonly($cname, $form_name))
			{
				// Go direct to get full erole list
				$eroles = new projectmanager_eroles_bo();
				foreach((array)$eroles->search(array(),false,'role_title ASC','','',false,'AND',false,array('pm_id'=>array(0,$eroles->pm_id))) as $erole)
				{
					self::$request->sel_options[$form_name][$erole['role_id']] = array(
						'label' => $erole['role_description'],
						'title' => lang('Element role title').': '.$erole['role_title'].$eroles->get_info($erole['role_id']),
					);
				}
			}
			$cell = $this->attrs;
			$cell['type']=$this->type;
			$cell['readonly'] = false;
			$template = self::$request;
			$pm_widget->pre_process($form_name, self::get_array(self::$request->content, $form_name),
				$cell,
				$garbage,
				$extension,
				$template
			);
			self::$request->sel_options[$form_name] += (array)$cell['sel_options'];

			// if no_lang was modified, forward modification to the client
			if ($cell['no_lang'] != $this->attr['no_lang'])
			{
				self::setElementAttribute($form_name, 'no_lang', $no_lang);
			}
		}

		parent::beforeSendToClient($cname);
	}

	/**
	 * Validate input
	 *
	 * @todo
	 * @param string $cname current namespace
	 * @param array $content
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			switch($this->type)
			{
				case 'projectmanager-select-erole':
					$value = null;
					if(is_array($value_in)) $value = implode(',',$value_in);
					break;
				default:
					$value = $value_in;
					break;
			}
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $value;
		}
	}
}
