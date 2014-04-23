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
app.classes.projectmanager = AppJS.extend(
{
	appname: 'projectmanager',

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
	 * Change the selected project
	 *
	 * @param {string|egwAction} 
	 */
	set_project: function(node_id, tree_widget, old_node_id)
	{
		if(node_id == old_node_id)
		{
			return false;
		}

		if(node_id)
		{
			var split = node_id.split('::');
			if(split.length > 1 && split[1]) node_id = split[1];
			this.egw.open(node_id, 'projectmanager', 'view',{},'projectmanager','projectmanager');
		}
		else
		{
			this.egw.open('','projectmanager','list',{},'projectmanager','projectmanager');
		}
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
		
		// Refresh element edit so it knows about the new role
		var elemEditWind = window.opener;
		if(elemEditWind)
		{
			elemEditWind.location.reload();
			
			// Refresh list so it knows about the new role
			if (elemEditWind.opener)
			{
				elemEditWind.opener.egw_appWindow('projectmanager').location.reload();
			}
		}
	},

	/**
	 * Toggles display of a div
	 *
	 *  Used in erole list in element list, maybe others?
	 */
	toggleDiv: function(target)
	{
		element = $j(target).closest('div').parent('div').find('table.egwLinkMoreOptions');
		if($j(element).css('display') == 'none')
		{
			$j(element).fadeIn('medium');
		}
		else
		{
			$j(element).fadeOut('medium');
		}
	},

	/**
	 * Show a jpgraph gantt chart.
	 *
	 * The gantt chart is a single image of static size.  The size must be known
	 * in advance, so we include it in the GET request.
	 */
	show_gantt: function(action,selected)
	{
		var id = [];
		for(var i = 0; i < selected.length; i++)
		{
			// IDs look like projectmanager::#, or projectmanager_elements::projectmanager:#:#
			// gantt wants just #
			var split = selected[i].id.split('::');
			if(split.length > 1)
			{
				var matches = split[1].match(':([0-9]+):?');
				id.push(matches ? matches[1] : split[1]);
			}
		}
		egw.open_link(egw.link('/index.php', {
			menuaction: 'projectmanager.projectmanager_gantt.chart',
			pm_id:id.join(','), // Server expects CSV, not array
			width: $j(app.projectmanager.et2.getDOMNode() || window).width(),
			ajax: 'true'
		}), 'projectmanager',false,'projectmanager');
	},

	/**
	 * Handler for double clicking on a task in the gantt chart
	 *
	 * @param {Object} task Task information, as sent to the gantt
	 * @param {et2_gantt} gantt_widget Gantt widget
	 */
	gantt_open: function(task, gantt_widget)
	{
		// Project element
		if(task.pe_app)
		{
			this.egw.open(task.pe_app_id, task.pe_app);
		}
		// Project
		else
		{
			this.egw.open(task.id, 'projectmanager');
		}
	},

	/**
	 * Show the pricelist for a selected project
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	show_pricelist: function(action,selected)
	{
		var id = [];
		for(var i = 0; i < selected.length; i++)
		{
			// IDs look like projectmanager::#, or projectmanager_elements::projectmanager:#:#
			// pricelist wants just #
			var split = selected[i].id.split('::');
			if(split.length > 1)
			{
				var matches = split[1].match(':([0-9]+):?');
				id.push(matches ? matches[1] : split[1]);
			}
		}
		egw.open_link(egw.link('/index.php', {
			menuaction: 'projectmanager.projectmanager_pricelist_ui.index',
			pm_id:id.join(','), // Server expects CSV, not array
			ajax: 'true'
		}), 'projectmanager',false,'projectmanager');
	}
});
