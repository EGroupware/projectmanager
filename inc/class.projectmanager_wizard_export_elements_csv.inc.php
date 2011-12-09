<?php
/**
 * eGroupWare - Wizard for Project CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class projectmanager_wizard_export_elements_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();
		$this->steps['wizard_step50'] = lang('Select export options');
		$this->step_templates['wizard_step50'] = 'projectmanager.export_element_options';

		// Field mapping
		$this->export_fields = array(
			'pm_title'	=> lang('Project Title'),
			'pm_id'		=> lang('Projectid'),
			'pe_id'		=> lang('Element ID'),
			'pe_app'		=> lang('Application'),
			'pe_title'		=> lang('Element Title'),
			'pe_completion'	=> lang('Completion'),
			'pe_planned_time'	=> lang('Planned time'),
			'pe_replanned_time'=> lang('Re-planned time'),
			'pe_used_time'	=> lang('Time'),
			'pe_planned_budget'=> lang('Planned budget'),
			'pe_used_budget'	=> lang('Used budget'),
			'pe_planned_start'	=> lang('Planned start'),
			'pe_real_start'	=> lang('Startdate'),
			'pe_planned_end'	=> lang('Planned end'),
			'pe_real_end'	=> lang('Enddate'),
			'pe_synced'	=> lang('synced'),
			'pe_modified'	=> lang('Last modified'),
			'pe_modifier'	=> lang('Modified by'),
			'pe_status'	=> lang('Status'),
			'pe_unitprice'	=> lang('Unitprice'),
			'cat_id'	=> lang('Category'),
			'pe_share'		=> lang('Share'),
			'pe_resources'	=> lang('Resources'),
			'pe_details'	=> lang('Details'),
			'pe_planned_quantity'=> lang('Planned quantity'),
			'pe_used_quantity'	=> lang('Used quantity')
		);
	}

	/** 
	 * Override to remove 'All custom fields' field that parent adds automatically.
	 * Project elements don't have custom fields - they come from element's app, not PM
	 */
	public function wizard_step30(&$content, &$sel_options, &$readonlys, &$preserv) {
		$result = parent::wizard_step30($content, $sel_options, $readonlys, $preserv);
		unset($this->export_fields['all_custom_fields']);
		unset($sel_options['field']);
		foreach($content['fields'] as $row => $field)
		{
			if($field['field'] == 'all_custom_fields') unset($content['fields'][$row]);
		}
		return $result;
	}

	public function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv) {
		if($this->debug) error_log(get_class($this) . '::wizard_step50->$content '.print_r($content,true));
		// return 
		if ($content['step'] == 'wizard_step50')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step50($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step
		else
		{
			$content['step'] = 'wizard_step50';
			$content['msg'] = $this->steps[$content['step']];
			$preserv = $content;
			unset ($preserv['button']);
			$fields = array('pe_used_time', 'pe_planned_time', 'pe_replanned_time');
			$sel_options = array_fill_keys($fields, array('h' => lang('hours'), 'd' => lang('days')));
			foreach($fields as $field) {
				$content[$field] = $content[$field] ? $content[$field] : $content['plugin_options'][$field];
			}
		}
		return $this->step_templates[$content['step']];
	}
}
