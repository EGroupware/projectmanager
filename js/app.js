/**
 * EGroupware - Projectmanager - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package projectmanager
 * @author Nahtan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for projectmanager
 *
 * @augments AppJS
 */
app.projectmanager = AppJS.extend(
{
	appname: 'projectmanager',
	/**
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */

	/**
	 * Constructor
	 *
	 * @memberOf app.projectmanager
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);


	},
	/**
	 * Handles delete button in edit popup
	 *
	 */
	p_element_delete: function()
	{
		var template = this.et2._inst;
		if (template)
		{
			var content = template.widgetContainer.getArrayMgr('content');
			var id = content.data['pe_id'];
		}
		console.log('I am element delete');
		opener.location.href= egw.link('/index.php', {
				menuaction: (content.data['caller'])? content.data['caller'] :'projectmanager.projectmanager_elements_ui.index',
				delete: id,
			});
		window.close();
	},

	/**
	 *
	 *
	 */
	calc_budget: function(form)
	{
		form['exec[pe_used_budget]'].value = form['exec[pe_used_quantity]'].value.replace(/,/,'.') * form['exec[pe_unitprice]'].value.replace(/,/,'.');
		if (form['exec[pe_used_budget]'].value == '0')
		{
			form['exec[pe_used_budget]'].value = '';
		}
		form['exec[pe_planned_budget]'].value = form['exec[pe_planned_quantity]'].value.replace(/,/,'.') * form['exec[pe_unitprice]'].value.replace(/,/,'.');
		if (form['exec[pe_planned_budget]'].value == '0')
		{
			form['exec[pe_planned_budget]'].value = '';
		}
	},
	/**
	 *
	 *
	 */


	/**
	 * Open window for a new project using link system, and pass on the
	 * template if one is selected.
	 *
	 * @param {etemplate_widget} widget The button, gives us access to the widget
	 *	context without needing to store a reference.
	 */
	new_project: function(widget)
	{
		// Find the template
		var template = '';
		if(typeof widget != 'undefined')
		{
			var templ_widget = widget.getRoot().getWidgetById('template_id');
			if(templ_widget)
			{
				template = templ_widget.getValue();
			}
		}
		else if (document.getElementById(et2_form_name('nm','template_id')))
		{
			template = document.getElementById(et2_form_name('nm','template_id')).value;
		}

		// Open the popup
		egw.open('','projectmanager','add',{'template': template},'_blank');
		return false;
	},

	/**
	 * Refresh the multi select box of eroles list
	 */
	erole_refresh: function(action)
	{
		var elemEditWind = window.opener;
		if (elemEditWind)
		{
			elemEditWind.location.reload();
		}
		switch (action)
		{
			case 'delete':
				return confirm("Delete this role?");
				break;
			case 'edit'	:
				break;
			default:
				this.et2._inst.submit();
		}
	},
});
