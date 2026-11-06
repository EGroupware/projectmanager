/**
 * EGroupware - Projectmanager - Element role select
 *
 * @link https://www.egroupware.org
 * @package projectmanager
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import {Et2SelectReadonly} from "../../api/js/etemplate/Et2Select/Select/Et2SelectReadonly";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * @summary Picks one or more element roles, the options coming from the server.
 *
 * An element role describes what an entry does inside a project ("the customer", "the invoice",
 * ...).  They are per-installation, so there is nothing to pick from until an administrator turns
 * them on and defines some; the server sends whatever is available as select options for this
 * widget's own id, which is all this class needs from it.  Several roles can apply to one element,
 * and the database column holds them as one comma-separated list.
 *
 * This exists as a real custom element purely so the tag works inside an Et2Datagrid row.  The
 * server has always rewritten the tag to a plain `et2-select` through an eTemplate widget
 * transformation, which travels to the browser as a `type` entry in the modifications array - and
 * only the legacy widget tree reads that.  Et2Nextmatch's row provider builds a row by cloning the
 * row template's elements by tag name and never consults modifications, so in a row the untouched
 * tag used to reach `document.createElement()` as an unknown element: present in the DOM, inert,
 * rendering nothing at all, with no warning anywhere.  Registering the tag is enough to fix that;
 * the transformation stays, so everywhere outside a row this still resolves to `et2-select` exactly
 * as before.
 */
@customElement("projectmanager-select-erole")
export class ProjectmanagerSelectErole extends Et2Select
{
	constructor()
	{
		super();

		// One element can hold several roles
		this.multiple = true;
	}
}

/**
 * @summary Read-only element roles, as shown in a project's element list.
 *
 * Et2Select renders poorly read-only inside a datagrid row, so the row provider swaps any row
 * widget for a `<tag>_ro` variant when one is registered - this is that variant.  It needs no
 * behaviour of its own: Et2SelectReadonly already splits the stored comma-separated list into
 * individual values and looks each one up in the select options.
 */
@customElement("projectmanager-select-erole_ro")
export class ProjectmanagerSelectEroleReadonly extends Et2SelectReadonly
{
}
