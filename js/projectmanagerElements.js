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

function toggleMoreOptions(button)
{
	element = $(button).closest('div').parent('div').find('table.egwLinkMoreOptions');
	if($(element).css('display') == 'none')
	{
		$(element).fadeIn('medium');
	}
	else
	{
		$(element).fadeOut('medium');
	}
}

$(document).ready(function()
{
	$('table.egwLinkMoreOptions').parent('div').parent('div').css('position', 'relative');
	$('table.egwLinkMoreOptions').parent('div').parent('div').css('z-index', '999');
	$('table.egwLinkMoreOptions').parent('div').css('position', 'absolute');
	$('table.egwLinkMoreOptions').parent('div').css('right', '0');
	$('table.egwLinkMoreOptions').parent('div').css('width', '240px');
	$('table.egwLinkMoreOptions').parent('div').css('opacity', '.9');
});
