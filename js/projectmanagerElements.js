/**
 * Projectmanager - JavaScript for elemenents view
 *
 * @link http://www.egroupware.org
 * @author Christian Binder <christian.binder@freakmail.de>
 * @package projectmanager
 * @copyright (c) 2010-11 by Christian Binder <christian@jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: projectmanagerElements.js 29752 2010-12-19 20:15:00Z jaytraxx $
 */

function do_action(selbox)
{
	if (selbox.value != "") {
		selbox.form.submit();
		selbox.value = "";
	}
}
