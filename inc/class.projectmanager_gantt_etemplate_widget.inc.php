<?php
/**
 * EGroupware - eTemplate serverside gantt widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2014 Nathan Gray
 * @version $Id$
 */

use EGroupware\Api\Framework;

Framework::includeCSS('/projectmanager/js/dhtmlxGantt/codebase/dhtmlxgantt.css');

/**
 * eTemplate Gantt chart widget
 *
 * The Gantt widget accepts children, and uses them as simple filters
 */
class projectmanager_gantt_etemplate_widget extends \EGroupware\Api\Etemplate\Widget\Box
{
	// No legacy options
	protected $legacy_options = array();

	/**
	 * Validate input
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		unset($expand);	// not used, but required by function signature
		
		$value = self::get_array($content, $cname);
		$validated[$cname] = array(
			'action' => $value['action'],
			'selected' => $value['selected']
		);
	}
}