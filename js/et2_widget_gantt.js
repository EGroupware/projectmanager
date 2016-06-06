/**
 * EGroupware eTemplate2 - JS widget for GANTT chart
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2014
 * @version $Id$
 */

/*egw:uses
	jsapi.jsapi;
	/vendor/bower-asset/jquery/dist/jquery.js;
	/phpgwapi/js/dhtmlxtree/codebase/dhtmlxcommon.js; // otherwise gantt breaks
	/projectmanager/js/dhtmlxGantt/codebase/dhtmlxgantt.js;
	et2_core_inputWidget;
*/

/**
 * Gantt chart
 *
 * The gantt widget allows children, which are displayed as a header.  Any child input
 * widgets are bound as live filters on existing data.  The filter is done based on
 * widget ID, such that the value of the widget must match that attribute in the task
 * or the task will not be displayed.  There is special handling for
 * date widgets with IDs 'start_date' and 'end_date' to filter as an inclusive range
 * instead of simple equality.
 *
 * @see http://docs.dhtmlx.com/gantt/index.html
 * @augments et2_valueWidget
 */
var et2_gantt = (function(){ "use strict"; return et2_inputWidget.extend([et2_IResizeable,et2_IInput],
{
	// Filters are inside gantt namespace
	createNamespace: true,

	attributes: {
		"autoload": {
			"name": "Autoload",
			"type": "string",
			"default": "",
			"description": "JSON URL or menuaction to be called for projects with no, GET parameter selected contains id"
		},
		"ajax_update": {
			"name": "AJAX update method",
			"type": "string",
			"default": "",
			"description": "AJAX menuaction to be called when the user changes a task.  The function should take two parameters: the updated element, and all template values."
		},
		"duration_unit": {
			"name": "Duration unit",
			"type": "string",
			"default": "minute",
			"description": "The unit for task duration values.  One of minute, hour, week, year."
		},
		columns: {
			name: "Columns",
			type: "any",
			default: [
				{name: "text", label: egw.lang('Title'), tree: true, width: '*'}
			],
			description: "Columns for the grid portion of the gantt chart.  An array of objects with keys name, label, etc.  See http://docs.dhtmlx.com/gantt/api__gantt_columns_config.html"
		},
		value: {type: 'any'},
		needed: {ignore: true},
		onfocus: {ignore: true},
		tabindex: {ignore: true}
	},

	// Common configuration for Egroupware/eTemplate
	gantt_config: {
		// Gantt takes a different format of date format, all the placeholders are prefixed with '%'
		api_date: '%Y-%n-%d %H:%i:%s',
		xml_date: '%Y-%n-%d %H:%i:%s',

		// Duration is a unitless field.  This is the unit.
		duration_unit: 'minute',
		duration_step: 1,

		show_progress: true,
		order_branch: false,
		min_column_width: 30,
		min_grid_column_width: 30,
		task_height: 25,
		fit_tasks: true,
		autosize: '',
		// Date rounding happens either way, but this way it rounds to the displayed grid resolution
		// Also avoids a potential infinite loop thanks to how the dates are rounded with false
		round_dnd_dates: false,
		// Round resolution
		time_step: parseInt(this.egw().preference('interval','calendar') || 15),
		min_duration: 1 * 60 * 1000, // 1 minute in ms

		scale_unit: 'day',
		date_scale: '%d',
		subscales: [
			{unit:"month", step:1, date:"%F, %Y"}
			//{unit:"hour", step:1, date:"%G"}
		],
		columns: [
			{name: "text", label: egw.lang('Title'), tree: true, width: '*'}
		]
	},

	init: function(_parent, _attrs) {
		// _super.apply is responsible for the actual setting of the params (some magic)
		this._super.apply(this, arguments);

		// Gantt instance
		this.gantt = null;

		// DOM Nodes
		this.filters = jQuery(document.createElement("div"))
			.addClass('et2_gantt_header');
		this.gantt_node = jQuery('<div style="width:100%;height:100%"></div>');
		this.htmlNode = jQuery(document.createElement("div"))
			.css('height', this.options.height)
			.addClass('et2_gantt');

		this.htmlNode.prepend(this.filters);
		this.htmlNode.append(this.gantt_node);

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(
			this.getParent().getDOMNode(this.getParent()) || this.getInstanceManager().DOMContainer,
			this.gantt_node, 300
		);

		this.setDOMNode(this.htmlNode[0]);
	},

	destroy: function() {
		if(this.gantt !== null)
		{
			// Unselect task before removing it, or we get errors later if it is accessed
			this.gantt.unselectTask();
			this.gantt.detachAllEvents();
			this.gantt.clearAll();
			this.gantt = null;

		// Destroy dynamic full-height
		if(this.dynheight) this.dynheight.free();

		this._super.apply(this, arguments);}

		this.htmlNode.remove();
		this.htmlNode = null;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		if(this.gantt != null) return false;

		var config = jQuery.extend({}, this.gantt_config);

		// Set initial values for start and end, if those filters exist
		var start_date = this.getWidgetById('start_date');
		var end_date = this.getWidgetById('end_date');
		if(start_date)
		{
			config.start_date = start_date.getValue() ? new Date(start_date.getValue() * 1000) : null;
		}
		if(end_date)
		{
			config.end_date = end_date.getValue() ? new Date(end_date.getValue() * 1000): null;
		}
		if(this.options.duration_unit)
		{
			config.duration_unit = this.options.duration_unit;
		}

		// Initialize chart
		this.gantt = this.gantt_node.dhx_gantt(config);

		if(this.options.zoom)
		{
			this.set_zoom(this.options.zoom);
		}

		if(this.options.value)
		{
			this.set_value(this.options.value);
		}
		if(this.options.columns)
		{
			this.set_columns(this.options.columns);
		}

		// Update start & end dates with chart values for consistency
		if(start_date && this.options.value.data && this.options.value.data.length)
		{
			start_date.set_value(this.gantt.getState().min_date);
		}
		if(end_date && this.options.value.data && this.options.value.data.length)
		{
			end_date.set_value(this.gantt.getState().max_date);
		}

		// Bind some events to make things nice and et2
		this._bindGanttEvents();

		// Bind filters
		this._bindChildren();

		return true;
	},

	getDOMNode: function(_sender) {
		// Return filter container for children
		if (_sender != this && this._children.indexOf(_sender) != -1)
		{
			return this.filters[0];
		}

		// Normally simply return the main div
		return this._super.apply(this, arguments);
	},

	/**
	 * Implement the et2_IResizable interface to resize
	 */
	resize: function()
	{
		if(this.dynheight)
		{
			this.dynheight.update(function(w,h) {
				if(this.gantt)
				{
					this.gantt.setSizes();
				}
			}, this);
		}
		else
		{
			this.gantt.setSizes();
		}
	},

	/**
	 * Changes the units for duration
	 * @param {string} duration_unit One of minute, hour, week, year
	 */
	set_duration_unit: function(duration_unit)
	{
		this.options.duration_unit = duration_unit;
		if(this.gantt && this.gantt.config.duration_unit != duration_unit)
		{
			this.gantt.config.duration_unit = duration_unit;
			// Clear the end date, or previous end date may break time scale
			this.gantt.config.end_date = null;
			this.gantt.refreshData();
		}
	},

	/**
	 * Set the columns for the grid (left) portion
	 *
	 * @param {Object[]} columns - A list of columns
	 *	columns[].name The column's ID
	 *	columns[].label The title for the column header
	 *	columns[].width Width of the column
	 *
	 * @see http://docs.dhtmlx.com/gantt/api__gantt_columns_config.html for full options
	 */
	set_columns: function(columns)
	{
		this.gantt_config.columns = columns;

		var displayed_columns = [];
		var gantt_widget = this;

		// Make sure there's enough room for them all
		var width = 0;
		for(var col in columns)
		{
			// Preserve original width, gantt will resize column to fit
			if(!columns[col]._width)
			{
				columns[col]._width = columns[col].width;
			}
			columns[col].width = columns[col]._width;
			if(!columns[col].template)
			{
				// Use an et2 widget to render the column value, if one was provided
				// otherwise, just display the value
				columns[col].template = function(task) {
					var value = typeof task[this.name] == 'undefined'||task[this.name] == null ? '':task[this.name];

					// No value, but there's a project title.  Try reading the project value.
					if(!value && this.name.indexOf('pe_') == 0 && task.pm_title)
					{
						var pm_col = this.name.replace('pe_','pm_');
						value = typeof task[pm_col] == 'undefined' || task[pm_col] == null ? '':task[pm_col];
					}
					if(this.widget && typeof this.widget == 'string')
					{
						var attrs = jQuery.extend({readonly:true}, this.widget_attributes||{});
						this.widget = et2_createWidget(this.widget, attrs, gantt_widget);
					}
					if (this.widget)
					{
						this.widget.set_value(value);
						value = jQuery(this.widget.getDOMNode()).html();
					}
					return '<div class="gantt_column_'+this.name+'">' + value + '</div>';
				};
			}

			// Actual hiding is available in the pro version of gantt chart
			if(!columns[col].hide)
			{
				displayed_columns.push(columns[col]);
				width += parseInt(columns[col]._width) || 0;
			}

		}
		// Add in add column
		displayed_columns.push({name: 'add', width: 26});
		width += 26;

		if(width != this.gantt_config.grid_width || typeof this.gantt_config.grid_width == 'undefined')
		{
			this.gantt_config.grid_width = Math.min(Math.max(200, width), this.htmlNode.width());
		}

		if(this.gantt == null) return;
		this.gantt.config.columns = displayed_columns;
		this.gantt.config.grid_width = this.gantt_config.grid_width;
		this.gantt.render();
	},

	/**
	 * Sets the data to be displayed in the gantt chart.
	 *
	 * Data is a JSON object with 'data' and 'links', both of which are arrays.
	 * {
	 *		data:[
	 *			{id:1, text:"Project #1", start_date:"01-04-2013", duration:18},
	 *			{id:2, text:"Task #1", start_date:"02-04-2013", duration:8, parent:1},
	 *			{id:3, text:"Task #2", start_date:"11-04-2013", duration:8, parent:1}
	 *		],
	 *		links:[
	 *			{id:1, source:1, target:2, type:"1"},
	 *			{id:2, source:2, target:3, type:"0"}
	 *		]
	 *		// Optional:
	 *		zoom: 1-4,
	 *
	 * };
	 * Any additional data can be included and used, but the above is the minimum
	 * required data.
	 *
	 * @see http://docs.dhtmlx.com/gantt/desktop__loading.html
	 *
	 * @param {type} value
	 */
	set_value: function(value) {
		if(this.gantt == null) return false;

		// Unselect task before removing it, or we get errors later if it is accessed
		this.gantt.unselectTask();

		// Clear previous value
		this.gantt.clearAll();

		// Clear the end date, or previous end date may break time scale
		this.gantt.config.end_date = null;

		if(value.duration_unit)
		{
			this.set_duration_unit(value.duration_unit);
		}

		this.gantt.showCover();

		// Set zoom to max, in case data spans a large time
		this.set_zoom(value.zoom || 5);

		// Wait until zoom is done before continuing so timescales are done
		var gantt_widget = this;
		var zoom_wait = this.gantt.attachEvent('onGanttRender', function() {
			this.detachEvent(zoom_wait);

			// Ensure proper format, no extras
			var safe_value = {
				data: value.data || [],
				links: value.links || []
			};
			this.config.start_date = value.start_date || null;
			this.config.end_date = value.end_date || null;
			this.parse(safe_value);

			gantt_widget._apply_sort();
			gantt_widget.gantt_loading = false;
			// Once we force the start / end date (below), gantt won't recalculate
			// them if the user clears the date, so we store them and use them
			// if the user clears the date.
			//gantt_widget.stored_state = jQuery.extend({},this.getState());

			// Doing this again here forces the gantt chart to trim the tasks
			// to fit the date range, rather than drawing all the dates out
			// to the start date.
			// No speed improvement, but it makes a lot more sense in the UI
			var range = this.attachEvent('onGanttRender', function() {
				this.detachEvent(range);
				if(value.start_date  || value.end_date)
				{
					// TODO: Some weirdness in this when changing dates
					// If this is done, gantt does not respond when user clears the start date
					/*
					this.refreshData();
					debugger;
					if(gantt_widget.getWidgetById('start_date') && new Date(value.start_date) > this._min_date)
					{
						gantt_widget.getWidgetById('start_date').set_value(value.start_date || null);
					}
					if(gantt_widget.getWidgetById('end_date') && new Date(value.end_date) < this._max_date)
					{
						gantt_widget.getWidgetById('end_date').set_value(value.end_date || null);
					}
					this.refreshData();
					this.render();
					*/

					this.scrollTo(this.posFromDate(new Date(value.end_date || value.start_date )),0);
				}

				// Zoom to specified or auto level
				var auto_zoom = this.attachEvent('onGanttRender', function() {
					this.detachEvent(auto_zoom);

					var old_zoom;

					// Zooming out re-scales the gantt start & end dates and
					// changes what values they can have,
					// so to zoom in we have to do it step by step
					do
					{
						this.render();
						old_zoom = gantt_widget.options.zoom;
						gantt_widget.set_zoom(value.zoom || false);
					} while(gantt_widget.options.zoom != old_zoom)
					this.hideCover();

					if(console.timeEnd) console.timeEnd("Gantt set_value");
					if(console.groupEnd) console.groupEnd();
					if(console.profile) console.profileEnd();
				});
			});
			// This render re-calculates start/end dates
			// this.render();
		});

		// This render re-sizes gantt to work at highest zoom
		this.gantt.render();
	},
	/**
	 * getValue has to return the value of the input widget
	 */
	getValue: function() {
		return jQuery.extend({}, this.value, {
			zoom: this.options.zoom,
			duration_unit: this.gantt.config.duration_unit
		});
	},

	/**
	 * Refresh given tasks for specified change
	 *
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - update: request just modified data for given tasks
	 * - edit:  same as edit
	 * - delete: just delete the given tasks clientside (no server interaction neccessary)
	 * - add: requires full reload
	 *
	 * @param {string[]|string} _task_ids tasks to refresh
	 * @param {?string} _type "update", "edit", "delete" or "add"
	 *
	 * @see jsapi.egw_refresh()
	 * @fires refresh from the widget itself
	 */
	refresh: function(_task_ids, _type) {
		// Framework trying to refresh, but gantt not fully initialized
		if(!this.gantt || !this.gantt_node || !this.options.autoload) return;

		// Sanitize arguments
		if (typeof _type == 'undefined') _type = 'edit';
		if (typeof _task_ids == 'string' || typeof _task_ids == 'number') _task_ids = [_task_ids];
		if (typeof _task_ids == "undefined" || _task_ids === null)
		{
			// Use the root
			_task_ids = this.gantt._branches[0];
		}

		id_loop:
		for(var i = 0; i < _task_ids.length; i++)
		{
			var task = this.gantt.getTask(_task_ids[i]);
			if(!task) _type = null;
			switch(_type)
			{
				case "edit":
				case "update":
					var value = this.getInstanceManager().getValues(this.getInstanceManager().widgetContainer);
					this.gantt.showCover();
					this.egw().json(this.options.autoload,
						[_task_ids[i],value,task.parent||false],
						function(data) {
							this.gantt.parse(data);
							this._apply_sort();
							this.gantt.hideCover();
						},
						this,true,this
					).sendRequest();
					break;
				case "delete":
					this.gantt.deleteTask(_task_ids[i]);
					break;
				case "add":
					var data = this.egw().dataGetUIDdata(_task_ids[i]) && data.data;
					if(data)
					{
						this.gantt.parse(data.data);
						this._apply_sort();
					}
					else
					{
						// Refresh the whole thing
						this.refresh();
						break id_loop;
					}
					break;
				default:
					// Refresh the whole thing
					this.refresh();
			}
		}

		// Trigger an event so app code can act on it
		jQuery(this).triggerHandler("refresh",[this,_task_ids,_type]);
	},

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty: function() {
		return this.value != null;
	},

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty: function() {
		this.value = null;
	},

	/**
	 * Checks the data to see if it is valid, as far as the client side can tell.
	 * Return true if it's not possible to tell on the client side, because the server
	 * will have the chance to validate also.
	 *
	 * The messages array is to be populated with everything wrong with the data,
	 * so don't stop checking after the first problem unless it really makes sense
	 * to ignore other problems.
	 *
	 * @param {String[]} messages List of messages explaining the failure(s).
	 *	messages should be fairly short, and already translated.
	 *
	 * @return {boolean} True if the value is valid (enough), false to fail
	 */
	isValid: function(messages) {return true;},

	/**
	 * Set a URL to fetch the data from the server.
	 * Data must be in the specified format.
	 * @see http://docs.dhtmlx.com/gantt/desktop__loading.html
	 *
	 * @param {string} url
	 */
	set_autoload: function(url) {
		if(this.gantt == null) return false;
		this.options.autoloading = url;

		throw new Exception('Not implemented yet - apparently loading segments is not supported automatically');
	},

	/**
	 * Sets the level of detail for the chart, which adjusts the scale(s) across the
	 * top and the granularity of the drag grid.
	 *
	 * Gantt chart needs a render() after changing.
	 *
	 * @param {int} level Higher levels show more grid, at larger granularity.
	 * @return {int} Current level
	 */
	set_zoom: function(level) {

		var subscales = [];
		var scale_unit = 'day';
		var date_scale = '%d';
		var step = 1;
		var time_step = this.gantt_config.time_step;
		var min_column_width = this.gantt_config.min_column_width;

		// No level?  Auto calculate.
		if(level > 5) level = 5;
		if(!level || level < 1) {
			// Make sure we have the most up to date info for the calculations
			// There may be a more efficient way to trigger this though
			try {
				this.gantt.refreshData();
			}
			catch (e)
			{}

			var difference = (this.gantt.getState().max_date - this.gantt.getState().min_date)/1000; // seconds
			// Spans more than 3 years
			if(difference > 94608000)
			{
				level = 5;
			}
			// Spans more than 3 months
			else if(difference > 7776000)
			{
				level = 4;
			}
			// More than 3 days
			else if(difference > 86400 * 3)
			{
				level = 3;
			}
			// More than 1 day
			else
			{
				level = 2;
			}
		}

		// Adjust Gantt settings for specified level
		switch(level)
		{
			case 5:
				// Several years
				//subscales.push({unit: "year", step: 1, date: '%Y'});
				scale_unit = 'year';
				date_scale = '%Y';
				break;
			case 4:
				// A year or more, scale in weeks
				subscales.push({unit: "month", step: 1, date: '%F %Y'});
				scale_unit = 'week';
				date_scale= '#%W';
				break;
			case 3:
				// Less than a year, several months
				subscales.push({unit: "month", step: 1, date: '%F %Y', class: 'et2_clickable'});
				break;
			case 2:
			default:
				// About a month
				subscales.push({unit: "day", step: 1, date: '%F %d'});
				scale_unit = 'hour';
				date_scale = this.egw().preference('timeformat') == '24' ? "%G" : "%g";
				break;
			case 1: // A day or two, scale in Minutes
				subscales.push({unit: "day", step: 1, date: '%F %d'});
				date_scale = this.egw().preference('timeformat') == '24' ? "%G:%i" : "%g:%i";
				step = parseInt(this.egw().preference('interval','calendar') || 15);
				time_step = 1;
				scale_unit = 'minute';
				min_column_width = 50;
				break;
		}

		// Apply settings
		this.gantt.config.subscales = subscales;
		this.gantt.config.scale_unit = scale_unit;
		this.gantt.config.date_scale = date_scale;
		this.gantt.config.step = step;
		this.gantt.config.time_step = time_step;
		this.gantt.config.min_column_width = min_column_width;

		this.options.zoom = level;

		this.gantt.refreshData();
		return level;
	},

	/**
	 * Apply user's sort preference
	 */
	_apply_sort: function()
	{
		switch(egw.preference('gantt_pm_elementbars_order','projectmanager'))
		{
			case "pe_start":
			case "pe_start,pe_end":
				this.gantt.sort('start_date',false);
				break;
			case "pe_end":
				this.gantt.sort('end_date',false);
				break;
			case 'pe_title':
				this.gantt.sort('pe_title',false);
				break;
		}
	},

	/**
	 * Bind all the internal gantt events for nice widget actions
	 */
	_bindGanttEvents: function() {
		var gantt_widget = this;

		// After the chart renders, resize to make sure it's all showing
		this.gantt.attachEvent("onGanttRender", function() {
			// Timeout gets around delayed rendering
			window.setTimeout(function() {
				gantt_widget.resize();
			},100);
		});

		// Click on scale to zoom - top zooms out, bottom zooms in
		this.gantt_node.on('click','.gantt_scale_line', function(e) {
			var current_position = e.target.offsetLeft / jQuery(e.target.parentNode).width();

			// Some crazy stuff make sure timing is OK to scroll after re-render
			// TODO: Make this more consistently go to where you click
			var id = gantt_widget.gantt.attachEvent("onGanttRender", function() {
				gantt_widget.gantt.detachEvent(id);
				gantt_widget.gantt.scrollTo(parseInt(jQuery('.gantt_task_scale',gantt_widget.gantt_node).width() *current_position),0);
				window.setTimeout(function() {
					gantt_widget.gantt.scrollTo(parseInt(jQuery('.gantt_task_scale',gantt_widget.gantt_node).width() *current_position),0);
				},100);
			});

			if(this.parentNode && this.parentNode.firstChild == this && this.parentNode.childElementCount > 1)
			{
				// Zoom out
				gantt_widget.set_zoom(gantt_widget.options.zoom + 1);
				gantt_widget.gantt.render();
			}
			else if (gantt_widget.options.zoom > 1)
			{
				// Zoom in
				gantt_widget.set_zoom(gantt_widget.options.zoom - 1);
				gantt_widget.gantt.render();
			}
			/*
			window.setTimeout(function() {
				console.log("Scroll to");
				gantt_widget.gantt.scrollTo(parseInt(jQuery('.gantt_task_scale',gantt_widget.gantt_node).width() *current_position),0);
			},50);
			*/
		});

		this.gantt.attachEvent("onGridHeaderClick", function(column_name, e) {
			if(column_name === "add")
			{
				gantt_widget._column_selection(e);
			}
		});
		this.gantt.attachEvent("onContextMenu",function(taskId, linkId, e) {
			if(gantt_widget.options.readonly) return false;
			if(taskId)
			{
				gantt_widget._link_task(taskId);
			}
			else if (linkId)
			{
				this._delete_link_handler(linkId,e);
				e.stopPropagation();
			}
			return false;
		});
		// Double click
		this.gantt.attachEvent("onBeforeLightbox", function(id) {
			gantt_widget._link_task(id);
			// Don't do gantt default actions, actions handle it
			return false;
		});

		// Update server after dragging a task
		this.gantt.attachEvent("onAfterTaskDrag", function(id, mode, e) {
			if(gantt_widget.options.readonly) return false;

			// Round to nearest 10%
			var task = this.getTask(id);
			if(mode==="progress")
			{
				task.progress = Math.round(task.progress * 10) / 10;
				this.updateTask(task.id);
			}

			var task = jQuery.extend({},this.getTask(id));

			// Gantt chart deals with dates as Date objects, format as server likes
			var date_parser = this.date.date_to_str(this.config.api_date);
			if(task.start_date) task.start_date = date_parser(task.start_date);
			if(task.end_date) task.end_date = date_parser(task.end_date);

			var value = gantt_widget.getInstanceManager().getValues(gantt_widget.getInstanceManager().widgetContainer);

			var set = true;
			if(gantt_widget.options.onchange)
			{
				e.data = {task: task, mode: mode, value: value};
				set = gantt_widget.change(e, gantt_widget);
			}
			if(gantt_widget.options.ajax_update && set)
			{
				var request = gantt_widget.egw().json(gantt_widget.options.ajax_update,
					[task, mode, value]
				).sendRequest(true);
			}
		});

		// Update server for links
		var link_update = function(id, link) {
			if(gantt_widget.options.readonly) return false;
			if(gantt_widget.options.ajax_update)
			{
				link.parent = this.getTask(link.source).parent;
				var value = gantt_widget.getInstanceManager().getValues(gantt_widget.getInstanceManager().widgetContainer);

				var request = gantt_widget.egw().json(gantt_widget.options.ajax_update,
					[link,value], function(new_id) {
						if(new_id)
						{
							link.id = new_id;
						}
					}
				).sendRequest(true);
			}
		};
		this.gantt.attachEvent("onAfterLinkAdd", link_update);
		this.gantt.attachEvent("onAfterLinkDelete", link_update);

		// Bind AJAX for dynamic expansion
		// TODO: This could be improved
		this.gantt.attachEvent("onTaskOpened", function(id, item) {
			gantt_widget.refresh(id);
		});

		// Filters
		this.gantt.attachEvent("onBeforeTaskDisplay", function(id, task) {
			var display = true;
			gantt_widget.iterateOver(function(_widget){
				switch(_widget.id)
				{
					// Start and end date are an interval.  Also update the chart to
					// display those dates.  Special handling because date widgets give
					// value in timestamp (seconds), gantt wants Date object (ms)
					case 'end_date':
						if(_widget.getValue())
						{
							display = display && ((task['start_date'].valueOf() / 1000) < (new Date(_widget.getValue()).valueOf() / 1000) + 86400 );
						}
						return;
					case 'start_date':
						// End date is not actually a required field, so accept undefined too
						if(_widget.getValue())
						{
							display = display && (typeof task['end_date'] == 'undefined' || !task['end_date'] || ((task['end_date'].valueOf() / 1000) >= (new Date(_widget.getValue()).valueOf() / 1000)));
						}
						return;
				}

				// Regular equality comparison
				if(_widget.getValue() && typeof task[_widget.id] != 'undefined')
				{
					if (task[_widget.id] != _widget.getValue())
					{
						display = false;
					}
					// Special comparison for objects, any intersection is a match
					if(!display && typeof task[_widget.id] == 'object' || typeof _widget.getValue() == 'object')
					{
						var a = typeof task[_widget.id] == 'object' ? task[_widget.id] : _widget.getValue();
						var b = a == task[_widget.id] ? _widget.getValue() : task[_widget.id];
						if(typeof b == 'object')
						{
							display = jQuery.map(a,function(x) {
								return jQuery.inArray(x,b) >= 0;
							});
						}
						else
						{
							display = jQuery.inArray(b,a) >= 0;
						}
					}
				}
			},gantt_widget, et2_inputWidget);
			return display;
		});
	},

	/**
	 * Bind onchange for any child input widgets
	 */
	_bindChildren: function() {
		var gantt_widget = this;
		this.iterateOver(function(_widget){
			if(_widget.instanceOf(et2_gantt)) return;
			// Existing change function
			var widget_change = _widget.change;

			var change = function(_node) {
				// Call previously set change function
				var result = widget_change.call(_widget,_node);

				// Update filters
				if(result) {
					// Update dirty
					_widget._oldValue = _widget.getValue();

					// Start date & end date change the display
					if(_widget.id == 'start_date' || _widget.id == 'end_date')
					{
						var start = this.getWidgetById('start_date');
						var end = this.getWidgetById('end_date');
						gantt_widget.gantt.config.start_date = start && start.getValue() ? new Date(start.getValue()) : gantt_widget.gantt.getState().min_date;
						// End date is inclusive
						gantt_widget.gantt.config.end_date = end && end.getValue() ? new Date(new Date(end.getValue()).valueOf()+86400000) : gantt_widget.gantt.getState().max_date;
						if(gantt_widget.gantt.config.end_date <= gantt_widget.gantt.config.start_date)
						{
							gantt_widget.gantt.config.end_date = null;
							if(end) end.set_value(null);
						}
						gantt_widget.set_zoom();
						gantt_widget.gantt.render();
					}

					gantt_widget.gantt.refreshData();
				}
				// In case this gets bound twice, it's important to return
				return true;
			};

			if(_widget.change != change) _widget.change = change;
		}, this, et2_inputWidget);
	},

	/**
	 * Start UI for selecting among defined columns
	 *
	 * @param {type} e
	 */
	_column_selection: function(e)
	{
		var self = this;
		var columns = [];
		var columns_selected = [];
		for (var i = 0; i < this.gantt_config.columns.length; i++)
		{
			var col = this.gantt_config.columns[i];
			columns.push({
				value: col.name,
				label: col.label
			});
			if(!col.hide)
			{
				columns_selected.push(col.name);
			}
		}

		// Build the popup
		if(!this.selectPopup)
		{
			var select = et2_createWidget("select", {
				multiple: true,
				rows: 8,
				empty_label:this.egw().lang("select columns"),
				selected_first: false
			}, this);
			select.set_select_options(columns);
			select.set_value(columns_selected);

			var okButton = et2_createWidget("buttononly", {}, this);
			okButton.set_label(this.egw().lang("ok"));
			okButton.onclick = function() {
				// Update columns
				var value = select.getValue() || [];
				for (var i = 0; i < columns.length; i++)
				{
					self.gantt_config.columns[i].hide = value.indexOf(columns[i].value) < 0 ;
				}
				self.set_columns(self.gantt_config.columns);

				// Update Implicit preference
				this.egw().set_preference(self.getInstanceManager().app, 'gantt_columns_' + self.id, value);

				// Hide popup
				self.selectPopup.toggle();
				self.selectPopup.remove();
				self.selectPopup = null;
				jQuery('body').off('click.gantt');
			};

			var cancelButton = et2_createWidget("buttononly", {}, this);
			cancelButton.set_label(this.egw().lang("cancel"));
			cancelButton.onclick = function() {
				self.selectPopup.toggle();
				self.selectPopup.remove();
				self.selectPopup = null;
				jQuery('body').off('click.gantt');
			};

			// Create and add popup
			this.selectPopup = jQuery(document.createElement("div"))
				.addClass("colselection ui-dialog ui-widget-content")
				.append(select.getDOMNode())
				.append(okButton.getDOMNode())
				.append(cancelButton.getDOMNode())
				.appendTo(this.getInstanceManager().DOMContainer);
			// Bind so if you click elsewhere, it closes
			window.setTimeout(function() {jQuery(document).one('mouseup.gantt', function(e){
				if(!self.selectPopup.is(e.target) && self.selectPopup.has(e.target).length === 0)
				{
					cancelButton.onclick();
				}
			});},1);
		}
		else
		{
			this.selectPopup.toggle();
		}
		this.selectPopup.position({my:'right top', at:'right bottom', of: e.target});
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 * Overridden to make the gantt chart a container, so it can't be selected.
	 * Because the chart handles its own AJAX fetching and parsing, for this widget
	 * we're trying dynamic binding as needed, rather than binding every single task
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions: function(actions)
	{

		this._super.apply(this, arguments);

		// Submit with most actions
		this._actionManager.setDefaultExecute(jQuery.proxy(function(action, selected) {
			var ids = [];
			for(var i = 0; i < selected.length; i++)
			{
				ids.push(selected[i].id);
			}
			this.value = {
				action: action.id,
				selected: ids
			};

			// downloads need a regular submit via POST (no Ajax)
			if (action.data.postSubmit)
			{
				this.getInstanceManager().postSubmit();
			}
			else
			{
				this.getInstanceManager().submit();
			}
		}, this));

		// Get the top level element for the tree
		var objectManager = egw_getAppObjectManager(true);
		var widget_object = objectManager.getObjectById(this.id);
		widget_object.flags = EGW_AO_FLAG_IS_CONTAINER;
	},

	/**
	 * Bind a single task as needed to the action system.  This is instead of binding
	 * every single task at the start.
	 *
	 * @param {string} taskId
	 */
	_link_task: function(taskId)
	{
		if(!taskId) return;
		var objectManager = egw_getObjectManager(this.id,false);
		var obj = null;
		if(!(obj = objectManager.getObjectById(taskId)))
		{
			obj = objectManager.addObject(taskId, this.dhtmlxGanttItemAOI(this.gantt,taskId));
			obj.data = this.gantt.getTask(taskId);
			obj.updateActionLinks(objectManager.actionLinks);
		}
		objectManager.setAllSelected(false);
		obj.setSelected(true);
		objectManager.updateSelectedChildren(obj,true);
	},

	/**
	 * ActionObjectInterface for gantt chart
	 *
	 * @param {type} gantt
	 * @param {type} task_id
	 * @returns {egwActionObjectInterface|et2_widget_gantt_L34.et2_widget_ganttAnonym$1.dhtmlxGanttItemAOI.aoi}
	 */
	dhtmlxGanttItemAOI: function(gantt, task_id)
	{
		var aoi = new egwActionObjectInterface();

		// Retrieve the actual node from the chart
		aoi.node = gantt.getTaskNode(task_id);
		aoi.id = task_id;
		aoi.doGetDOMNode = function() {
			return aoi.node;
		};

		aoi.doTriggerEvent = function(_event) {
			if (_event == EGW_AI_DRAG_OVER)
			{
				jQuery(this.node).addClass("draggedOver");
			}
			if (_event == EGW_AI_DRAG_OUT)
			{
				jQuery(this.node).removeClass("draggedOver");
			}
		};

		aoi.doSetState = function(_state) {
			if(!gantt || !gantt.isTaskExists(this.id)) return;

			if(egwBitIsSet(_state, EGW_AO_STATE_SELECTED))
			{
				gantt.selectTask(this.id);	// false = do not trigger onSelect
			}
			else
			{
				gantt.unselectTask(this.id);
			}
		};

		return aoi;
	}

});}).call(this);
et2_register_widget(et2_gantt, ["gantt","projectmanager-gantt"]);

/**
 * Common look, feel & settings for all Gantt charts
 */
// Localize to user's language - breaks if file is not there
//egw.includeJS("/phpgwapi/js/dhtmlxGantt/codebase/locale/locale_" + egw.preference('lang') + ".js");

jQuery(function()
{
	"use strict";

	// Set icon to match application
	gantt.templates.grid_file = function(item) {
		if(!item.pe_icon || !egw.image(item.pe_icon)) return "<div class='gantt_tree_icon gantt_file'></div>";
		return "<div class='gantt_tree_icon' style='background-image: url(\"" + egw.image(item.pe_icon) + "\");'/></div>";
	};

	// CSS for scale row, turns on clickable
	gantt.templates.scale_row_class = function(scale) {
		if(scale.unit != 'minute' && scale.unit != 'month')
		{
			return scale.class || 'et2_clickable';
		}
		return scale.class;
	};

	// Include progress text in the bar
	gantt.templates.progress_text = function(start, end, task) {
		return "<span>"+Math.round(task.progress*100)+ "% </span>";
	};

	// Highlight weekends
	gantt.templates.scale_cell_class = function(date){
		if(date.getDay()==0||date.getDay()==6){
			return "weekend";
		}
	};
	gantt.templates.task_cell_class = function(item,date){
		if(date.getDay()==0||date.getDay()==6){
			return "weekend";
		}
	};

	gantt.templates.leftside_text = function(start, end, task) {
		var text = '';
		if(task.planned_start)
		{
			if(typeof task.planned_start == 'string') task.planned_start = gantt.date.parseDate(task.planned_start, "xml_date");
			var p_start = gantt.posFromDate(task.planned_start) - gantt.posFromDate(start);
			text = "<div class='gantt_task_line gantt_task_planned' style='width:"+Math.abs(p_start)+"px; right:"+(p_start > 0 ? -p_start : 0)+"px;'><span>"
				+ gantt.date.date_to_str(gantt.config.api_date)(task.planned_start)
				+ "</span></div>";
		}
		return text;
	};
	gantt.templates.rightside_text = function(start, end, task) {
		var text = '';
		if(task.planned_end)
		{
			if(typeof task.planned_end == 'string') task.planned_end = gantt.date.parseDate(task.planned_end, "xml_date");
			var p_end = gantt.posFromDate(task.planned_end) - gantt.posFromDate(end);
			text = "<div class='gantt_task_line gantt_task_planned' style='left:"+(p_end > 0 ? 0 : p_end) +"px; width:"+Math.abs(p_end)+"px'><span>"
				+ gantt.date.date_to_str(gantt.config.api_date)(task.planned_end)
				+ "</span></div>";
		}
		return text;
	};

	// Link styling
	gantt.templates.link_class = function(link) {
		var link_class = '';
		var source = gantt.getTask(link.source);
		var target = gantt.getTask(link.target);
		var valid = true;

		var types = gantt.config.links;
		switch (link.type)
		{
			case types.finish_to_start:
				valid = (source.end_date <= target.start_date);
				break;
			case types.start_to_start:
				valid = (source.start_date <= target.start_date);
				break;
			case types.finish_to_finish:
				valid = (source.end_date >= target.end_date);
				break;
			case types.start_to_finish:
				valid = (source.start_date >= target.end_date);
				break;
		}

		link_class += valid ? '' : 'invalid_constraint';

		return link_class;
	};
});