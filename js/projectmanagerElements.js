/**
 * Projectmanager - JavaScript for elemenents view
 *
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 * @package projectmanager
 * @copyright (c) 2010-11 by Christian Binder <christian@jaytraxx.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: projectmanagerElements.js 29752 2010-12-19 20:15:00Z jaytraxx $
 */

function do_action(selbox)
{
	if (selbox.value != "")
	{
		selbox.form.submit();
		selbox.value = "";
	}
}

$j(document).ready(function()
{
	$j('table.egwLinkMoreOptions').parent('div').parent('div').css('position', 'relative');
	$j('table.egwLinkMoreOptions').parent('div').parent('div').css('z-index', '999');
	$j('table.egwLinkMoreOptions').parent('div').css('position', 'absolute');
	$j('table.egwLinkMoreOptions').parent('div').css('right', '0');
	$j('table.egwLinkMoreOptions').parent('div').css('width', '240px');
	$j('table.egwLinkMoreOptions').parent('div').css('opacity', '.9');
});
