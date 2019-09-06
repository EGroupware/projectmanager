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

/*egw:uses
	/etemplate/js/etemplate2.js;
	/projectmanager/js/et2_widget_gantt.js;
*/

/**
 * UI for projectmanager
 *
 * @augments AppJS
 */
app.classes.projectmanager = AppJS.extend(
{
	appname: 'projectmanager',

	// Variables for having all templates loaded & switching
	view: 'list',
	views: {
		// Name = key, etemplate is filled in when loaded, sidemenu is untranslated text from hooks
		list: {name: 'list', etemplate: null, sidemenu: 'Projectlist'},
		elements: {name: 'elements', etemplate: null, sidemenu: 'Elementlist'},
		gantt: {name: 'gantt', etemplate: null, sidemenu: 'Ganttchart'},
		prices: {name: 'prices', etemplate: null, sidemenu: 'Pricelist'}
	},

	// Reference of all sub-templates
	etemplates: {
		"projectmanager.list": "list",
		"projectmanager.elements.list": "elements",
		"projectmanager.gantt": "gantt",
		"projectmanager.pricelist.list": "prices"
	},

	/**
	 * Constructor
	 *
	 * @memberOf app.projectmanager
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);

		register_app_refresh('projectmanager',jQuery.proxy(this.linkHandler,this));
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// Release sidebox from views
		if(this.sidebox)
		{
			this.sidebox.parent().parent().find('a').off('.projectmanager');
		}

		// Remove reference to etemplates
		for(var view in this.views)
		{
			this.views[view].etemplate = null;
		}

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

		// Add to list, but only ones we care about (no edit popups)
		if(typeof this.views[this.etemplates[et2.name]] != 'undefined')
		{
			var view = this.views[this.etemplates[et2.name]];
			view.etemplate = et2;

			// Take over sidebox menu
			this._bind_sidebox(view.sidemenu, function() {app.projectmanager.show(view.name);return false;});

			// If one template disappears, we want to release it
			jQuery(et2.DOMContainer).one('clear',function() {
				if(app.projectmanager && app.projectmanager.sidebox)
				{
					app.projectmanager.sidebox.off('.projectmanager');
				}
				view.etemplate = null;
			});

			// Start hidden, except for project list
			if(jQuery(et2.DOMContainer).siblings('.et2_container').length && !et2.widgetContainer.getArrayMgr('content').getEntry('project_tree'))
			{
				jQuery(et2.DOMContainer).hide();
			}

			if(view.name == 'list')
			{
				// First load, bind filemanager too
				var list = view;
				this._bind_sidebox('filemanager', function() {
					app.projectmanager.show_filemanager(null, [{id:
						window.app.projectmanager.views.list.etemplate.widgetContainer.getWidgetById('project_tree').getValue()||'projectmanager::'
					}]);
					return false;
				});
				// First load, framework could not use our link handler since it wasn't loaded
				var fw = egw_getFramework();
				if(fw && !app.projectmanager.linkHandler(fw.getApplicationByName('projectmanager').browser.currentLocation))
				{
					this.show('list');
				}
			}
			else if (this.view)
			{
				this.show(this.view);
			}
		}
	},

	/**
	 * Switch the view
	 *
	 * ProjectManager is comprised of several views, Project list, element list,
	 * gantt chart and price list.  We load them all at once, then switch between
	 * them as needed.
	 *
	 * @param {string} what one of 'list', 'elements', 'gantt' or 'prices'
	 * @param {string|Numeric} project_id Project ID to show.  If not provided,
	 *	it will be pulled from the project tree
	 * @returns {undefined}
	 */
	show: function(what, project_id)
	{
		var current_project = project_id && isNaN(project_id) ? (project_id[0] ? project_id[0] : null) : project_id;
		if(!current_project)
		{
			if(this.views.list.etemplate)
			{
				var node_id = this.views.list.etemplate.widgetContainer.getWidgetById('project_tree').getValue()||'';
				var split = node_id.split('::');
				if(split.length > 1 && split[1]) current_project = split[1];
			}
			if(!current_project)
			{
				current_project = egw.preference('current_project','projectmanager');
			}
		}
		current_project = parseInt(current_project) || '';

		// Store preference
		if(this.egw.preference('current_project','projectmanager') != current_project)
		{
			this.egw.set_preference('projectmanager', 'current_project', current_project);
		}

		// Update with current project
		if(current_project)
		{
			var et2 = this.views[what].etemplate.widgetContainer;
			switch(what)
			{
				case 'elements':
					et2.getWidgetById('nm').applyFilters({col_filter:{'pm_id': current_project}});
					if (et2.getWidgetById('link_add')) et2.getWidgetById('link_add').options.value.to_id = current_project;
					break;
				case 'gantt':
					var gantt = et2.getWidgetById('gantt');
					gantt.gantt.showCover();
					// Re-set dates for different project
					var values = gantt.getInstanceManager().getValues(gantt)[gantt.id];
					delete values.start_date;
					delete values.end_date;
					delete values.duration_unit;

					// Support multiple projects
					if(!project_id || !project_id.map)
					{
						project_id = [current_project];
					}
					project_id = project_id.map(function(id) {return typeof id == 'string' && id.indexOf('projectmanager::') == 0 ? id : 'projectmanager::'+id;});

					if(console.profile) console.profile('Gantt');
					if(console.group) console.group("Gantt loading PM_ID " + project_id);
					if(console.time) console.time("Gantt fetch");
					this.egw.json('projectmanager_gantt::ajax_gantt_project',[project_id,values], function(data) {

						if(console.time) console.timeEnd("Gantt fetch");
						gantt.set_value(data);
					}).sendRequest(true);
					break;
				case 'prices':
					// Pricelist is not valid for all projects.  If we have the data, adjust accordingly
					var data = this.egw.dataGetUIDdata('projectmanager::'+current_project);
					var pricelist_project = '0';
					if(data && data.data && data.data.pm_accounting_type)
					{
						pricelist_project = (data.data.pm_accounting_type == 'pricelist' ? current_project : '0');
					}
					else if(et2.getWidgetById('pm_id'))
					{
						// Unknown project, try the filter options
						var options = et2.getWidgetById('pm_id').options.select_options || [];
						for(var i = 0; i < options.length; i++)
						{
							if(parseInt(options[i].value) == parseInt(current_project))
							{
								pricelist_project = current_project;
								break;
							}
						}
					}
					et2.getWidgetById('nm').applyFilters({col_filter:{'pm_id': pricelist_project}});
			}
		}
		else if (what == 'prices')
		{
			// Price list doesn't need a project
			var et2 = this.views[what].etemplate.widgetContainer;
			et2.getWidgetById('nm').applyFilters({col_filter:{'pm_id': '0'}});
		}
		else if (what != 'list')
		{
			this.egw.message(this.egw.lang('You need to select a project first'));
			what = 'list';
		}

		// Update internal variable for current view
		this.view = what;

		// Update tree
		if(this.views.list.etemplate)
		{
			this.views.list.etemplate.widgetContainer.getWidgetById('project_tree').set_value(current_project? 'projectmanager::'+current_project : null);
		}

		// Show selected sub-template
		if(this.views[what].etemplate)
		{
			jQuery(this.views[what].etemplate.DOMContainer).show();
		}

		// Set header
		this.egw.app_header(this.egw.lang(this.views[what].sidemenu),'projectmanager');

		// Hide other views
		for(var view in this.views)
		{
			if(what != view && this.views[view].etemplate)
			{
				jQuery(this.views[view].etemplate.DOMContainer).hide();
			}
		}
	},

	/**
	 * Set the application's state to the given state.
	 *
	 * The default implementation works with the favorites to apply filters to a nextmatch.
	 * Re-implemented to support view
	 *
	 * @param {{name: string, state: object}|string} state Object (or JSON string) for a state.
	 *	Only state is required, and its contents are application specific.
	 *
	 * @return {boolean} false - Returns false to stop event propagation
	 */
	setState: function(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		if(typeof state != "object")
		{
			egw.debug('error', 'Unable to set state to %o, needs to be an object',state);
			return;
		}
		if(state == null)
		{
			state = {};
		}

		// Check for egw.open() parameters
		if(state.state && state.state.id && state.state.app)
		{
			return egw.open(state.state,undefined,undefined,{},'_self');
		}

		if(state.state && state.state.view)
		{
			this.show(state.state.view, state.state.pm_id||false);
			// Avoid any potential conflicts when setting others, below
			delete state.state.view;
		}
		else
		{
			this.show(this.view || 'list');
		}
		// Try and find a nextmatch widget, and set its filters
		var nextmatched = false;
		var et2 = this.views[this.view].etemplate;
		switch(this.view)
		{
			case 'gantt':
				for(var id in state.state)
				{
					var filter = et2.widgetContainer.getWidgetById(id);
					if(filter && filter.set_value)
					{
						filter.set_value(state.state[id]);
					}
				}
				return false;
			default:
				// Blank filters reset any/all nextmatches
				if(jQuery.isEmptyObject(state.state))
				{
					et2 = etemplate2.getByApplication(this.appname);
				}
				else
				{
					et2 = [et2];
				}
				for(var i = 0; i < et2.length; i++)
				{
					et2[i].widgetContainer.iterateOver(function(_widget) {
						// Firefox has trouble with spaces in search
						if(state.state && state.state.search) state.state.search = unescape(state.state.search);

						// Apply
						_widget.applyFilters(state.state || state.filter || {});
						nextmatched = true;
					}, this, et2_nextmatch);
				}
				if(nextmatched) return false;
		}

		// call parent
		var state = this._super.apply(this, arguments);
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Overwritten from the parent to support view
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState: function()
	{
		var state = {};

		// Try and find a nextmatch widget, and set its filters
		var et2 = this.views[this.view].etemplate;
		if(et2)
		{
			if(this.view == 'gantt')
			{
				et2.widgetContainer.iterateOver(function(gantt) {
					state = gantt.getInstanceManager().getValues(gantt)[gantt.id];

					// Gantt also needs the current PM ID stored
					var current_project = false;
					if(this.views.list.etemplate)
					{
						var node_id = this.views.list.etemplate.widgetContainer.getWidgetById('project_tree').getValue()||'';
						var split = node_id.split('::');
						if(split.length > 1 && split[1]) current_project = split[1];
					}
					if(!current_project)
					{
						current_project = egw.preference('current_project','projectmanager');
					}
					state.pm_id = current_project;
				}, this, et2_gantt);
			}
			else
			{
				et2.widgetContainer.iterateOver(function(_widget) {
					state = _widget.getValue();

					// These aren't considered for state
					delete state.link_add;
					delete state.link_addapp;
				}, this, et2_nextmatch);
			}
		}

		state.view = this.view || null;
		return state;
	},

	/**
	 * Handle links for projectmanager using JS instead of reloading
	 *
	 * @param {string} url
	 * @returns {boolean} True if PM could handle the link internally, false to let framework handle it
	 */
	linkHandler: function(url)
	{
		var match = url.match(/projectmanager(?:_elements)?_ui\.index&(pm_id)=(\d+)/);
		if(match && match.length > 2 && match[1] == 'pm_id')
		{
			if(this.views.elements.etemplate)
			{
				this.show('elements', match[2]);
			}
			else
			{
				// Still loading
				window.setTimeout(function() {app.projectmanager.linkHandler(url);},100);
			}
			return true;
		}
		return false;
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 */
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
	{
		switch (_app)
		{
			case 'projectmanager':
				var tree = this.views.list.etemplate.widgetContainer.getWidgetById('project_tree');
				var itemId = _id != 'undefined'?_app+"::"+_id:0;
				if (tree && itemId)
				{
					var node = tree.getNode(itemId);
					// Not in tree.  Either parent node not expanded, or a new project
					if(_type != 'delete' && node == null && typeof _links.projectmanager != 'undefined' && _links.projectmanager.length > 0)
					{
						// First one should be parent
						for(var i = 0; i < _links.projectmanager.length && node == null; i++)
						{
							node = tree.getNode(_app+"::"+_links.projectmanager[i]);
						}
						if(node !== null)
						{
							itemId = node.id;
						}
					}
					switch(_type)
					{
						case 'add':
						case 'update':
						case 'edit':
							tree.refreshItem(tree.input.getParentId(itemId)||0);
							break;
						case 'delete':
							if (node)
							{
								tree.deleteItem(itemId);
							}
							// Currently viewing that project - go back to list
							if(this.view === 'elements' && this.getState().col_filter.pm_id == _id)
							{
								this.show('list');
							}
					}
				}
				// Fall through to try the element list too
			default:
				var appList = egw.link_app_list('query');
				var nm = this.views.elements.etemplate ? this.views.elements.etemplate.widgetContainer.getWidgetById('nm') : null;

				if (typeof appList[_app] != 'undefined')
				{
					if (typeof _links != 'undefined')
					{
						if (typeof _links.projectmanager != 'undefined')
						{
							if (nm) nm.refresh();
						}
						else if (nm)
						{
							var rex = RegExp("projectmanager_elements::"+_app+":"+_id+".*");
							egw.dataRefreshUIDs(rex,'delete');
						}
					}
				}
		}

		// Update current view with new info
		switch (this.view)
		{
			case 'list':
				var nm = this.views.list.etemplate ? this.views.list.etemplate.widgetContainer.getWidgetById('nm') : null;
				if(nm)
				{
					nm.refresh(_id,_type);
				}
				return false;
			case 'elements':
				var nm = this.views.elements.etemplate ? this.views.elements.etemplate.widgetContainer.getWidgetById('nm') : null;
				if(nm)
				{
					// Element list has totals that probably need refreshed, so do a
					// full refresh, not just intelligent by type refresh.
					nm.refresh(_id);
				}
				return false;
			case 'gantt':
				var ids = [];
				var gantt = this.views.gantt.etemplate.widgetContainer.getWidgetById('gantt');
				if(_type == 'add' && _links.projectmanager)
				{
					// Refresh the parent(s)
					for(var i = 0; i < _links.projectmanager.length; i++)
					{
						ids.push('projectmanager::'+_links.projectmanager[i]);
					}
					_type == 'update';
				}
				else if (_id)
				{
					ids.push(_app+"::"+_id);
				}
				gantt.refresh(ids,_type);
				return false;
		}
	},

	/**
	 * Change the selected project
	 *
	 * This is a callback for the tree, either on click (node_id is a string) or
	 * context menu
	 *
	 * Crazy parameters thanks to action system.
	 * @param {string|egwAction} node_id Either the selected leaf, or a context-menu action
	 * @param {et2_tree|egwActionObject[]} tree_widget Either the tree widget, or the selected leaf.
	 * @param {string|egwAction} old_node_id Either the selected leaf, or a context-menu action
	 */
	set_project: function(node_id, tree_widget, old_node_id)
	{
		if(node_id == old_node_id)
		{
			return false;
		}
		var same_view = (this.view != 'list');
		if(typeof node_id == 'object' && tree_widget[0])
		{
			same_view = false;
			node_id = tree_widget[0].id;
		}

		if(node_id)
		{
			var split = node_id.split('::');
			if(split.length > 1 && split[1]) node_id = split[1];
			if(this.views['elements'].etemplate != null)
			{
				// Change the current view to the new project, or element list
				// if current view is project list
				this.show(same_view ? this.view : 'elements',node_id);
			}
			else
			{
				// Somehow we don't have the element list
				this.egw.open(node_id, 'projectmanager', 'view',{},'projectmanager','projectmanager');
			}
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
				delete: id
			});
		egw(window).close();
	},

	/**
	 * Limits constraint target link-entry widget to current project
	 *
	 * @param {object} request
	 * @param {et2_widget_entry} widget
	 * @returns {boolean} true to do the search
	 */
	element_constraint_pre_query: function(request,widget)
	{
		if(!request.options) request.options = {};
		request.options.pm_id = this.et2.getInstanceManager().widgetContainer.getArrayMgr('content').getEntry('pm_id');

		// Return true to proceed with the search
		return true;
	},

	/**
	 * Refresh the multi select box of eroles list
	 *
	 * @param {string} action name of action
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
			egw(elemEditWind).refresh('','projectmanager');
		}
	},

	/**
	 * Toggles display of a div
	 *
	 *  Used in erole list in element list, maybe others?
	 *  @param {egw_event object} event
	 *  @param {wiget object} widget
	 *  @param {string} target jQuery selector
	 */
	toggleDiv: function(event, widget, target)
	{
		var element = jQuery(target).closest('div').parent('div').find('table.egwLinkMoreOptions');
		if(jQuery(element).css('display') == 'none')
		{
			jQuery(element).fadeIn('medium');
		}
		else
		{
			jQuery(element).fadeOut('medium');
		}
	},

	/**
	 * Action callback to show gantt chart for a selected project
	 *
	 * @param {egwAction object} action
	 * @param {object} selected
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
		if(this.views['gantt'].etemplate != null)
		{
			// Just update the existing gantt
			this.show('gantt',id);
		}
		else
		{
			// Somehow we don't have the gantt template loaded
			egw.open_link(egw.link('/index.php', {
				menuaction: 'projectmanager.projectmanager_ui.index',
				pm_id:id.join(','), // Server expects CSV, not array
				ajax: 'true'
			}), 'projectmanager',false,'projectmanager');
		}
	},

	/**
	 * Handler for open action (double click) on the gantt chart
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	gantt_open_action: function(action,selected)
	{
		var task = {};
		if(selected[0].data)
		{
			task = selected[0].data;
		}
		// Project element
		if(task.pe_app)
		{
			this.egw.open(task.pe_app_id, task.pe_app);
		}
		else if (task.type && task.type == 'milestone')
		{
			egw.open_link(egw.link('/index.php',{
				menuaction: 'projectmanager.projectmanager_milestones_ui.edit',
				pm_id: task.pm_id,
				ms_id: task.ms_id
			}), false, '680x450', 'projectmanager');
		}
		// Project
		else
		{
			this.egw.open(task.pm_id, 'projectmanager');
		}
	},

	gantt_edit_element: function(action,selected)
	{
		var task = {};
		if(selected[0].data)
		{
			task = selected[0].data;
		}
		// Project element
		if(task.pe_id)
		{
			this.egw.open(task.pe_id, 'projectelement', 'edit', {pm_id: task.pe_app == 'projectmanager' ? task.parent : task.pm_id});
		}
	},

	/**
	 * Action callback to show the pricelist for a selected project
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
		if(this.views['prices'].etemplate != null)
		{
			// Just update the existing template
			this.show('prices',id[0]);
		}
		else
		{
			egw.open_link(egw.link('/index.php', {
				menuaction: 'projectmanager.projectmanager_ui.index',
				pm_id:id.join(','), // Server expects CSV, not array
				ajax: 'true'
			}), 'projectmanager',false,'projectmanager');
		}
	},

	/**
	 * Action callback to edit a price on the price list
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	edit_price: function(action, selected)
	{
		var extra = {
			menuaction: 'projectmanager.projectmanager_pricelist_ui.edit',
			pm_id: this.getState().col_filter.pm_id
		};
		if(selected[0] && selected[0].id)
		{
			var data = this.egw.dataGetUIDdata(selected[0].id);
			if(data && data.data)
			{
				extra.pl_id = data.data.pl_id;
			}
		}
		egw.openPopup(egw.link('/index.php', extra),600,450,'','filemanager');
	},

	/**
	 * Add a new price to a pricelist
	 *
	 * Used by the add button on the pricelist index
	 *
	 * @param {et2_widget} widget
	 */
	add_price: function add_price(widget)
	{
		var extras = {
			menuaction: 'projectmanager.projectmanager_pricelist_ui.edit',
			pm_id: 0
		}
		var pm_filter = widget.getRoot().getWidgetById('pm_id');
		if(pm_filter)
		{
			extras.pm_id = pm_filter.get_value();
		}
		window.open(this.egw.link('/index.php',extras),'_blank','dependent=yes,width=600,height=450,scrollbars=yes,status=yes');
		return false;
	},

	/**
	 * Action callback to show the filemanager for a selected project
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	show_filemanager: function(action,selected)
	{
		var app = '';
		var id = '';
		for(var i = 0; i < selected.length && id == ''; i++)
		{
			// Data was provided, just read from there
			if(selected[i].data && selected[i].data.pe_app)
			{
				app = selected[i].data.pe_app;
				id = selected[i].data.pe_app_id;
			}
			else
			{
				// IDs look like projectmanager::#, or projectelement::app:app_id:element_id
				var split = selected[i].id.split('::');
				if(split.length > 1)
				{
					var matches = split[1].match('([_a-z]+):([0-9]+):?');
					if(matches != null)
					{
						app = matches[1];
						id = matches[2];
					}
					else
					{
						app = split[0];
						id = split[1];
					}
				}
			}
		}
		egw.open_link(egw.link('/index.php', {
			menuaction: 'filemanager.filemanager_ui.index',
			path: '/apps/'+app+'/'+id,
			ajax: 'true'
		}), 'filemanager',false,'filemanager');
	},

	/**
	 * Enabled check for erole action, used by context menu
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	is_erole_allowed: function(action,selected)
	{
		var allowed = true;

		// Some eroles can only be assigned to a single element.  If already assigned,
		// they won't be an action, but we'll prevent setting multiple elements
		if(action.data && !action.data.role_multi && selected.length > 1)
		{
			allowed = false;
		}

		// Erole is limited to only these apps, from projectmanager_elements_bo
		var erole_apps = ['addressbook','calendar','infolog'];

		for(var i = 0; i < selected.length && allowed; i++)
		{
			var data = selected[i].data || egw.dataGetUIDdata(selected[i].id);
			if(data && data.data) data = data.data;
			if(!data)
			{
				allowed = false;
				continue;
			}
			if(erole_apps.indexOf(data.pe_app) < 0)
			{
				allowed = false;
			}
		}

		return allowed;
	},

	/**
	 * Is the selected entry ignored?
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 * @returns {Boolean}
	 */
	is_ignored: function(action, selected)
	{
		var ignored = false;

		for(var i = 0; i < selected.length; i++)
		{
			var data = egw.dataGetUIDdata(selected[i].id);
			ignored = ignored || !!(data && data.data && data.data.ignored);
		}

		return ignored;
	},

	/**
	 * Toggle the ignore flag on the selected entries
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	ignore_action: function(action, selected)
	{
		var ids = [];
		for(var i = 0; i < selected.length; i++)
		{
			ids.push(selected[i].id);
		}
		egw.json('projectmanager_elements_ui::ajax_action', [action.id, ids, action.checked],
			false, this, true, this
		).sendRequest(true);
	},

	/**
	 * Enabled check for project element action, used by context menu
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	gantt_edit_enabled: function(action,selected)
	{
		var allowed = true;
		for(var i = 0; i < selected.length && allowed; i++)
		{
			var data = selected[i].data || egw.dataGetUIDdata(selected[i].id);
			if(data && data.data) data = data.data;
			if(!data)
			{
				allowed = false;
				continue;
			}
			// No milestones, no top-level tasks
			if(selected[i].id.indexOf('pm_milestone')==0 || !data.parent)
			{
				allowed = false;
			}
		}

		return allowed;
	},

	/**
	 * Add new record's apps to a project
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	add_new: function (action, selected)
	{
		var tree = this.views.list.etemplate.widgetContainer.getWidgetById('project_tree');
		var pm_id = '';
		if (tree)
		{
			pm_id = tree.getValue();

			// Gantt chart can have multiple selected
			if(jQuery.isArray(pm_id)) pm_id = pm_id[0];

			pm_id = pm_id.replace('::',':');
		}
		// No tree, or could not find project there
		if(!pm_id && selected[0] && egw.dataGetUIDdata(selected[0].id))
		{
			var data = egw.dataGetUIDdata(selected[0].id);
			if(data && data.data && data.data.pm_id)
			{
				pm_id = 'projectmanager:'+data.data.pm_id;
			}
		}
		if (typeof action !== 'undefined')
		{
			return this.egw.open(pm_id, action.id.replace('act-',''), 'add');
		}
	},

	/**
	 * Bind the provided click handler to the sidebox menu item that matches
	 * the label for fast switching between views
	 *
	 * @param {string} label
	 * @param {function} click
	 */
	_bind_sidebox: function(label, click)
	{
		if(!app.projectmanager.sidebox) return false;
		var sidebox = jQuery('a:contains("' +app.projectmanager.egw.lang(label)+'")',app.projectmanager.sidebox.parentsUntil('#egw_fw_sidemenu,#tdSidebox').last());
		sidebox.off('click.projectmanager');
		sidebox.on('click.projectmanager', click);
	},

	/**
	 * Get title in order to set it as document title
	 * @returns {string}
	 */
	getWindowTitle: function()
	{
		var widget = this.et2.getWidgetById('pm_title');
		if(widget) return widget.options.value;
	}
});
