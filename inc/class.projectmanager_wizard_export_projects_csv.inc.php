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
		// Field mapping
		$bo = new projectmanager_bo();
		$this->export_fields = $bo->field2label;
		$custom = config::get_customfields('projectmanager', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}

	}

	
}
