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

		// Sidebox menu doesn't get updated in jdots when you select a project, so
		// enable / disable gantt chart link if a single project is set
		var gantt_link = egw.window.$j('#egw_fw_sidemenu, #sidebox')
			.find('div:contains('+egw.lang('GanttChart')+')')
			.last();
		// Check for project in top level, or link-to widget in element list nm header
		var pm_id = this.et2.getArrayMgr("content").getEntry('pm_id') ||
			this.et2.getArrayMgr("content").getEntry('nm[link_to][to_id]');
		if(pm_id)
		{
			$j('a',gantt_link).attr('href','#');
			gantt_link.addClass('et2_link');
			
			// Add a namespaced handler for easy removal
			gantt_link.on('click.projectmanager',jQuery.proxy(function() {
				// Fake ID to match what comes from nm action
				this.show_gantt(null,[{id: 'projectmanager::'+pm_id}]);
				return false;
			},this));
		}
		else
		{
			gantt_link.removeClass('et2_link');
			gantt_link.off('.projectmanager');
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
			var matches = split[1].match(':([0-9]+):?')
			id.push(matches ? matches[1] : split[1]);
		}
		egw.open_link(egw.link('/index.php', {
			menuaction: 'projectmanager.projectmanager_ganttchart.show',
			pm_id:id.join(','), // Server expects CSV, not array
			width: $j(app.projectmanager.et2.getDOMNode() || window).width()
		}), 'projectmanager',false,'projectmanager');
	}
});
