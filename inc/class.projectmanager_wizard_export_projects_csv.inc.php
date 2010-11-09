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

class projectmanager_wizard_export_projects_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		$this->steps['wizard_step50'] = lang('Select export options');
		$this->step_templates['wizard_step50'] = 'projectmanager.export_project_options';

		// Field mapping
		$bo = new projectmanager_bo();
		$this->export_fields = $bo->field2label;
		unset($this->export_fields['pm_overwrite']);

		// Add in roles
		$this->export_fields['roles'] = lang('Roles');

		// Add in element summary
		$this->export_fields += array(
			'pe_sum_completion_shares'	=> lang('Element list') . ' ' . lang('Total completion shares'),
			'pe_total_shares'		=> lang('Element list') . ' ' . lang('Total shares'),
			'pe_used_time'			=> lang('Element list') . ' ' . $this->export_fields['pm_used_time'],
			'pe_planned_time'		=> lang('Element list') . ' ' . $this->export_fields['pm_planned_time'],
			'pe_replanned_time'		=> lang('Element list') . ' ' . $this->export_fields['pm_replanned_time'],
			'pe_used_budget'		=> lang('Element list') . ' ' . $this->export_fields['pm_used_budget'],
			'pe_planned_budget'		=> lang('Element list') . ' ' . $this->export_fields['pm_planned_budget'],
			'pe_real_start'			=> lang('Element list') . ' ' . $this->export_fields['pm_real_start'],
			'pe_planned_start'		=> lang('Element list') . ' ' . $this->export_fields['pm_planned_start'],
			'pe_real_end'			=> lang('Element list') . ' ' . $this->export_fields['pm_real_end'],
			'pe_planned_end'		=> lang('Element list') . ' ' . $this->export_fields['pm_planned_end'],
			'pe_completion'			=> lang('Element list') . ' ' . lang('Completion')
		);

		// Custom fields
		unset($this->export_fields['customfields']); // Heading, not a real field
		$custom = config::get_customfields('projectmanager', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}

	public function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv) {
		if($this->debug || true) error_log(get_class($this) . '::wizard_step50->$content '.print_r($content,true));
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
			$fields = array('pm_used_time', 'pm_planned_time', 'pm_replanned_time');
			$sel_options = array_fill_keys($fields, array('h' => lang('hours'), 'd' => lang('days')));
			foreach($fields as $field) {
				$content[$field] = $content[$field] ? $content[$field] : $content['plugin_options'][$field];
			}
		}
		return $this->step_templates[$content['step']];
	}
}
