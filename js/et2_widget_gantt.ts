/**
 * EGroupware eTemplate2 - JS widget for GANTT chart
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package projectmanager
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2014-21
 */

/*egw:uses
	jsapi.jsapi;
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/npm-asset/dhtmlx-gantt/codebase/dhtmlxgantt.js;
	/vendor/npm-asset/dhtmlx-gantt/codebase/ext/dhtmlxgantt_marker.js;
	et2_core_inputWidget;
*/
import "../../vendor/npm-asset/dhtmlx-gantt/codebase/dhtmlxgantt.js";
import "../../vendor/npm-asset/dhtmlx-gantt/codebase/ext/dhtmlxgantt_marker.js";
import {et2_inputWidget} from "../../api/js/etemplate/et2_core_inputWidget";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_IInput, et2_IPrint, et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_dynheight} from "../../api/js/etemplate/et2_widget_dynheight";
import {et2_date} from "../../api/js/etemplate/et2_widget_date";
import {egw} from "../../api/js/jsapi/egw_global";
import {EGW_AO_FLAG_IS_CONTAINER, EGW_AO_STATE_SELECTED} from "../../api/js/egw_action/egw_action_constants.js";
import {
	egw_getAppObjectManager,
	egw_getObjectManager,
	egwActionObjectInterface
} from "../../api/js/egw_action/egw_action.js";
import {egwBitIsSet} from "../../api/js/egw_action/egw_action_common";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";


/* import dhtml-gantt, need to use commented out import statement, as egw:uses is not considered, if we have import(s)
import "../../vendor/npm-asset/dhtmlx-gantt/codebase/dhtmlxgantt.js";
import "../../vendor/npm-asset/dhtmlx-gantt/codebase/ext/dhtmlxgantt_marker.js";
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
export class et2_gantt extends et2_inputWidget implements et2_IResizeable, et2_IInput, et2_IPrint
{
	static readonly _attributes : any = {
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
	};

	// Common configuration for Egroupware/eTemplate
	gantt_config: any = {
		// Gantt takes a different format of date format, all the placeholders are prefixed with '%'
		api_date: '%Y-%n-%d %H:%i:%s',
		date_format: '%Y-%n-%d %H:%i:%s',
		time_picker: '%Y-%n-%d %H:%i:%s',

		// Duration is a unitless field.  This is the unit.
		duration_unit: 'minute',
		duration_step: 1,

		show_progress: true,
		order_branch: false,
		min_column_width: 30,
		min_grid_column_width: 30,
		grid_width: 300,
		task_height: 25,
//		fit_tasks: true,
//		autosize: '',
		// Date rounding happens either way, but this way it rounds to the displayed grid resolution
		// Also avoids a potential infinite loop thanks to how the dates are rounded with false
		round_dnd_dates: false,
		// Round resolution
		time_step: parseInt(''+this.egw().preference('interval','calendar')) || 15,
		min_duration: 1 * 60 * 1000, // 1 minute in ms

		columns: [
			{name: "text", label: egw.lang('Title'), tree: true, width: '*'}
		],
		autofit: true,
		autosize: "y"
	};

	// Gantt will handle most zooming, here we configure the zoom levels & headings
	zoomConfig: any = {
		levels: [
		{
			name: "minutes",
			min_column_width: 50,
			scales: [
				{unit: "day", step: 1, format: "%F %d"},
				{
					unit: "minute",
					step: parseInt(""+this.egw().preference('interval','calendar')) || 15,
					format: this.egw().preference('timeformat') == '24' ? "%G:%i" : "%g:%i"
				}
			]
		},
		{
			name: "day",
			min_column_width:50,
			scales: [
				{unit: "day", step: 1, format: "%F %d"},
				{unit: "hour", format: this.egw().preference('timeformat') == '24' ? "%G:%i" : "%g:%i"}
			]
		},
		{
		  name:"week",
		  min_column_width:80,
		  scales:[
			  {unit: "month", format: "%M"},
			  {unit: "day", step: 1, format: "%d"}
		  ]
		},
		{
		   name:"month",
		   scale_height: 50,
		   min_column_width:120,
		   scales:[
			  {unit: "month", format: "%F, %Y"},
			  {unit: "week", format: "#%W"}
		   ]
		},
		{
		   name:"quarter",
		   height: 50,
		   min_column_width:60,
		   scales:[
				{unit: "year", step: 1, format: "%Y"},
				{unit: "month", step: 1, format: "%M"},
			]
		},
		{
			name:"year",
			scale_height: 50,
			min_column_width: 30,
			scales:[
				{unit: "year", step: 1, format: "%Y"},
				{
				 unit: "quarter", step: 1, format: function (date) {
				  var dateToStr = gantt.date.date_to_str("%M");
				  var endDate = gantt.date.add(gantt.date.add(date, 3, "month"), -1, "day");
				  return dateToStr(date) + " - " + dateToStr(endDate);
				 }
			   }
			]
		}
	  ]
	};

	// Gantt instance, except they changed it to static
	private gantt: GanttStatic = null;
	private filters: JQuery;
	private gantt_node: JQuery;
	private htmlNode: JQuery;
	private dynheight: et2_dynheight;
	private selectPopup: JQuery;

	private value: any;
	private gantt_loading : boolean = false;
	private stored_state : JQuery;

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_gantt._attributes, _child || {}));

		// DOM Nodes
		this.filters = jQuery(document.createElement("div"))
			.addClass('et2_gantt_header');
		this.gantt_node = jQuery('<div style="width:100%;height:100%" id="gantt_here"></div>');
		this.htmlNode = jQuery(document.createElement("div"))
			.css('height', this.options.height)
			.addClass('et2_gantt');

		this.htmlNode.prepend(this.filters);
		this.htmlNode.append(this.gantt_node);

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(
			(<et2_DOMWidget>this.getParent()).getDOMNode(this.getParent()) || this.getInstanceManager().DOMContainer,
			this.gantt_node, 300
		);

		this.setDOMNode(this.htmlNode[0]);
	}

	destroy()
	{
		if(this.gantt !== null)
		{
			// Unselect task before removing it, or we get errors later if it is accessed
			this.gantt.unselectTask();
			this.gantt.detachAllEvents();
			this.gantt._onLinkIdChange = null;
			this.gantt._onTaskIdChange = null;
			this.gantt.clearAll();
			this.gantt.$container = null;
			this.gantt = null;
		}

		// Destroy dynamic full-height
		if(this.dynheight) this.dynheight.destroy();
		this.dynheight = null;

		super.destroy();

		this.htmlNode.remove();
		this.htmlNode = null;
		this.gantt_node = null;
	}

	doLoadingFinished()
	{
		super.doLoadingFinished();
		if(this.gantt != null) return false;

		var config = jQuery.extend({}, gantt.config, this.gantt_config);

		// Set initial values for start and end, if those filters exist
		var start_date = <et2_date>this.getWidgetById('start_date');
		var end_date = <et2_date>this.getWidgetById('end_date');
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
		this.gantt = gantt;
		this.gantt.config = config;
		this.gantt.ext.zoom.init(this.zoomConfig);
		this.gantt.init("gantt_here");

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
	}

	_createNamespace() {
		return true;
	}
	getDOMNode(_sender)
	{
		// Return filter container for children
		if (_sender != this && this._children.indexOf(_sender) != -1)
		{
			return this.filters[0];
		}

		// Normally simply return the main div
		return super.getDOMNode(_sender);
	}

	/**
	 * Implement the et2_IResizable interface to resize
	 */
	resize()
	{
		if(this.dynheight)
		{
			this.dynheight.update(function(w,h) {
				if(this.gantt)
				{
	//				this.gantt.setSizes();
				}
			}, this);
		}
		else if (this.gantt)
		{
			this.gantt.setSizes();
		}
	}

	/**
	 * Changes the units for duration
	 * @param {string} duration_unit One of minute, hour, week, year
	 */
	set_duration_unit(duration_unit)
	{
		this.options.duration_unit = duration_unit;
		if(this.gantt && this.gantt.config.duration_unit != duration_unit)
		{
			this.gantt.config.duration_unit = duration_unit;
			// Clear the end date, or previous end date may break time scale
			this.gantt.config.end_date = null;
			this.gantt.refreshData();
		}
	}

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
	set_columns(columns)
	{
		this.options.columns = columns;

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
				width += parseInt(columns[col]._width) || 300;
			}

		}
		// Add in add column
		displayed_columns.push({name: 'add', width: 26, _width:26});
		width += 26;

		this._set_grid_width(width);

		this.gantt_config.columns = displayed_columns;

		if(this.gantt == null) return;
		this.gantt.config.columns = displayed_columns;

		this.gantt.render();
	}

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
	set_value(value?)
	{
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


		// Wait until zoom is done before continuing so timescales are done
		var gantt_widget = this;
		var zoom_wait = this.gantt.ext.zoom.attachEvent('onAfterZoom', function() {
			this.detachEvent(zoom_wait);

			// Ensure proper format, no extras
			var safe_value = {
				data: value.data || [],
				links: value.links || []
			};
			gantt.config.start_date = value.start_date || null;
			gantt.config.end_date = value.end_date || null;
			gantt.parse(safe_value);

			gantt_widget._apply_sort();
			gantt_widget.gantt_loading = false;

			// Once we force the start / end date (below), gantt won't recalculate
			// them if the user clears the date, so we store them and use them
			// if the user clears the date.
			gantt_widget.stored_state = jQuery.extend({},gantt.getState());

			// Doing this again here forces the gantt chart to trim the tasks
			// to fit the date range, rather than drawing all the dates out
			// to the start date.
			// No speed improvement, but it makes a lot more sense in the UI

			var range = gantt.attachEvent('onGanttRender', function() {
				this.detachEvent(range);

				// Auto-zoom
				gantt_widget.set_zoom();
				gantt.hideCover();
			});

		});

		// Set zoom to max, in case data spans a large time
		this.set_zoom(value.zoom || 5);

		var markerId = this.gantt.addMarker({
			start_date: new Date(), //a Date object that sets the marker's date
			css: "today", //a CSS class applied to the marker
			text: this.egw().lang("Today") //the marker title
		});

		// This render re-sizes gantt to work at highest zoom
		this.gantt.render();
	}
	/**
	 * getValue has to return the value of the input widget
	 */
	getValue()
	{
		return jQuery.extend({}, this.value, {
			zoom: this.options.zoom,
			duration_unit: this.gantt.config.duration_unit
		});
	}

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
	refresh(_task_ids?, _type?)
	{
		// Framework trying to refresh, but gantt not fully initialized
		if(!this.gantt || !this.gantt_node || !this.options.autoload) return;

		// Sanitize arguments
		if (typeof _type == 'undefined') _type = 'edit';
		if (typeof _task_ids == 'string' || typeof _task_ids == 'number') _task_ids = [_task_ids];
		if (typeof _task_ids == "undefined" || _task_ids === null)
		{
			// Use the root
			_task_ids = this.gantt.$data.tasksStore._branches[0];
		}

		id_loop:
		for(var i = 0; i < _task_ids.length; i++)
		{
			var update_id = _task_ids[i];
			var task = this.gantt.getTask(update_id);
			if(!task && update_id)
			{
				task = this.gantt.getTaskBy(function(task) {
					var app_id = update_id.split('::');
					return task.pe_app === app_id[0] && task.pe_app_id === app_id[1];
				});
				if(task.length)
				{
					// Get the parent project, not the actual element since we can
					// only update projects with autoload
					task = task[0];
					update_id = task.parent;
				}
			}
			if(!task)
			{
				_type = null;
			}
			switch(_type)
			{
				case "edit":
				case "update":
					var value = this.getInstanceManager().getValues(this.getInstanceManager().widgetContainer);
					this.gantt.showCover();
					this.egw().json(this.options.autoload,
						[update_id,value,task.parent||false],
						function(data) {
							this.gantt.parse(data);
							this._apply_sort();
							this.gantt.hideCover();
						},
						this,true,this
					).sendRequest();
					break;
				case "delete":
					this.gantt.deleteTask(update_id);
					break;
				case "add":
					var data = this.egw().dataGetUIDdata(update_id) && data.data;
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
	}

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty()
	{
		return this.value != null;
	}

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty()
	{
		this.value = null;
	}

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
	isValid(messages) {
		return true;
	}

	/**
	 * Set a URL to fetch the data from the server.
	 * Data must be in the specified format.
	 * @see http://docs.dhtmlx.com/gantt/desktop__loading.html
	 *
	 * @param {string} url
	 */
	set_autoload(url)
	{
		if(this.gantt == null) return false;
		this.options.autoloading = url;

		throw new Exception('Not implemented yet - apparently loading segments is not supported automatically');
	}

	/**
	 * Sets the level of detail for the chart, which adjusts the scale(s) across the
	 * top and the granularity of the drag grid.
	 *
	 * Gantt chart needs a render() after changing.
	 *
	 * @param {int} level Higher levels show more grid, at larger granularity.
	 * @return {int} Current level
	 */
	set_zoom(level?)
	{

		// No level?  Auto calculate.
		if(!level || level < 1) {
			// Make sure we have the most up to date info for the calculations
			// There may be a more efficient way to trigger this though
			try {
				this.gantt.refreshData();
			}
			catch (e)
			{}

			var max_date = 0;
			var min_date = Infinity;
			var tasks = this.gantt.getTaskByTime();
			for(var i = 0; i < tasks.length; i++)
			{
				if(tasks[i].start_date && tasks[i].start_date > max_date) max_date = tasks[i].start_date;
				if(tasks[i].start_date && tasks[i].start_date < min_date) min_date = tasks[i].start_date;
				if(tasks[i].end_date && tasks[i].end_date > max_date) max_date = tasks[i].end_date;
				if(tasks[i].end_date && tasks[i].end_date < min_date) min_date = tasks[i].end_date;
			}
			var difference = (max_date - min_date)/1000; // seconds
			// Spans more than 2 years
			if(difference > 63113904)
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
		gantt.ext.zoom.setLevel(level);

		this.gantt.refreshData();
		return level;
	}

	/**
	 * Apply user's sort preference
	 */
	_apply_sort()
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
	}

	/**
	 * Bind all the internal gantt events for nice widget actions
	 */
	_bindGanttEvents()
	{
		var gantt_widget = this;

		// Click on scale to zoom - top zooms out, bottom zooms in
		this.gantt_node.on('click','.gantt_scale_line', function(e) {
			var current_position = e.target.offsetLeft / jQuery(e.target.parentNode).width();
			var date = new Date(gantt_widget.gantt.getState().min_date.getTime() + (gantt_widget.gantt.getState().max_date.getTime() - gantt_widget.gantt.getState().min_date.getTime()) * current_position);

			// Make it more consistently go to where you click, instead of the middle
			// of the range
			var id = gantt_widget.gantt.ext.zoom.attachEvent("onAfterZoom", function() {
				gantt_widget.gantt.detachEvent(id);
				gantt_widget.gantt.showDate(date);
			});

			if(this.parentNode && this.parentNode.firstChild === this && this.parentNode.childElementCount > 1)
			{
				// Zoom out
				gantt.ext.zoom.zoomOut();
			}
			else // if (gantt_widget.options.zoom > 1)
			{
				// Zoom in
				gantt.ext.zoom.zoomIn();
			}
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
				gantt_widget.delete_link_handler(linkId,e);
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
				var send_values = jQuery.extend({}, link);
				send_values.parent = this.getTask(link.source).parent;

				// Make sure we send the element ID, sub-projects don't have that in their ID
				var source = this.getTask(link.source);
				if(source.pe_app === "projectmanager")
				{
					send_values.source = source.pe_app + ":" + source.pe_app_id + ':'+source.pe_id;
				}
				var target = this.getTask(link.target);
				if(target.pe_app === "projectmanager")
				{
					send_values.target = target.pe_app + ":" + target.pe_app_id + ':'+target.pe_id;
				}

				var value = gantt_widget.getInstanceManager().getValues(gantt_widget.getInstanceManager().widgetContainer);

				var request = gantt_widget.egw().json(gantt_widget.options.ajax_update,
					[send_values,'link',value], function(new_id) {
						if(new_id)
						{
							gantt_widget.gantt.changeLinkId(link.id, new_id);
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
	}

	/**
	 * Confirm & delete link
	 */
	delete_link_handler(link_id, event)
	{
		let gantt_widget = this;
		let dialog = Et2Dialog.show_dialog(function(button)
			{
				if(button == Et2Dialog.YES_BUTTON)
				{
					gantt_widget.gantt.deleteLink(link_id);
				}
			},
			'delete link?',
			'delete'
		);
	}

	/**
	 * Bind onchange for any child input widgets
	 */
	_bindChildren()
	{
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

					gantt_widget.gantt.refreshData();
					// Start date & end date change the display
					if(_widget.id == 'start_date' || _widget.id == 'end_date')
					{
						var start = this.getWidgetById('start_date');
						var end = this.getWidgetById('end_date');
						gantt_widget.gantt.config.start_date = start && start.getValue() ? new Date(start.getValue()) : null;
						// End date is inclusive
						gantt_widget.gantt.config.end_date = end && end.getValue() ? new Date(new Date(end.getValue()).valueOf()+86400000) : null;
						if(gantt_widget.gantt.config.end_date <= gantt_widget.gantt.config.start_date)
						{
							gantt_widget.gantt.config.end_date = null;
							if(end) end.set_value(null);
						}
						gantt_widget.set_zoom();
						gantt_widget.gantt.render();
					}

				}
				// In case this gets bound twice, it's important to return
				return true;
			};

			if(_widget.change != change) _widget.change = change;
		}, this, et2_inputWidget);
	}

	/**
	 * Start UI for selecting among defined columns
	 *
	 * @param {type} e
	 */
	_column_selection(e)
	{
		var self = this;
		var columns = [];
		var columns_selected = [];
		for (var i = 0; i < this.options.columns.length; i++)
		{
			var col = this.options.columns[i];
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
					self.options.columns[i].hide = value.indexOf(columns[i].value) < 0 ;
				}
				self.set_columns(self.options.columns);

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
	}

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 * Overridden to make the gantt chart a container, so it can't be selected.
	 * Because the chart handles its own AJAX fetching and parsing, for this widget
	 * we're trying dynamic binding as needed, rather than binding every single task
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions(actions)
	{

		super._link_actions(actions);

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
	}

	/**
	 * Bind a single task as needed to the action system.  This is instead of binding
	 * every single task at the start.
	 *
	 * @param {string} taskId
	 */
	_link_task(taskId)
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
	}

	/**
	 * ActionObjectInterface for gantt chart
	 *
	 * @param {type} gantt
	 * @param {type} task_id
	 * @returns {egwActionObjectInterface|et2_widget_gantt_L34.et2_widget_ganttAnonym$1.dhtmlxGanttItemAOI.aoi}
	 */
	dhtmlxGanttItemAOI(gantt, task_id)
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

	_set_grid_width(width?)
	{
		if(typeof width === "undefined")
		{
			width = 0;

			for(var col in this.gantt_config.columns)
			{
				width += parseInt(this.gantt_config.columns[col]._width) || 300;
			}
		}

		if(width != this.gantt_config.grid_width || typeof this.gantt_config.grid_width == 'undefined')
		{
			this.gantt_config.grid_width = Math.min(Math.max(200, width), Math.max(300,this.htmlNode.width()));
		}

		if(this.gantt == null) return;
		this.gantt.config.grid_width = this.gantt_config.grid_width;
	}

	resize()
	{
		// Update column size
		this._set_grid_width();
	}

	// Printing
	/**
	 * Prepare for printing
	 *
	 * Since the gantt chart tends to be large and browsers cannot handle printing
	 * pages wider than a piece of paper, we rotate the gantt to fit.
	 *
	 */
	beforePrint()
	{
		// Add the class, if needed
		this.htmlNode.addClass('print');

		var max_width = Math.max(
			jQuery(this.gantt.$grid).width() + jQuery(this.gantt.$task_scale).width(),
			jQuery(this.gantt.$container).width()
		);
		var max_height = Math.max(
			jQuery(this.gantt.$grid).height(),
			jQuery(this.gantt.$container).height()
		);

		var pref = 'gantt_columns_print';
		var app = this.getInstanceManager().app;

		var columns = [];
		var columns_selected = [];

		// Column preference exists?  Set it now
		if(this.egw().preference(pref,app))
		{
			var value = jQuery.extend([], this.egw().preference(pref,app));
			for (var i = 0; i < this.options.columns.length; i++)
			{
				this.options.columns[i].hide = value.indexOf(this.options.columns[i].name) < 0 ;
			}
			this.set_columns(this.options.columns);
		}

		// Make gantt chart "full size"
		this.gantt_node.width(max_width)
				.height(max_height);

		this.gantt.render();

		this.gantt_node.css({
			width: Math.max(max_width, max_height) + 'px !important',
			height: Math.max(max_width, max_height) + 'px !important'
		});
		// Force layout
		this.egw().getHiddenDimensions(this.gantt_node);

		// Defer the printing to ask about columns and orientation
		var defer = jQuery.Deferred();

		for (var i = 0; i < this.options.columns.length; i++)
		{
			var col = this.options.columns[i];
			columns.push({
				value: col.name,
				label: col.label
			});
			if(!col.hide)
			{
				columns_selected.push(col.name);
			}
		}

		var callback = function(button, value)
		{
			if(button === Et2Dialog.CANCEL_BUTTON)
			{
				// Give dialog a chance to close, or it will be in the print
				window.setTimeout(function() {defer.reject();}, 0);
				return;
			}
			debugger;
			// Columns
			for(var i = 0; i < columns.length; i++)
			{
				this.options.columns[i].hide = value.columns.indexOf(columns[i].value) < 0;
			}
			this.set_columns(this.options.columns);
			this.egw().set_preference(app,pref,value.columns);

			if(value.orientation === 'vertical')
			{
				this.gantt_node.height(max_width);
				jQuery(this.gantt.$container).css({
					transform: 'rotate(-90deg) translateX(-' + max_width + 'px)',
					'transform-origin': 'top left'
				});
			}
			// Give dialog a chance to close, or it will be in the print
			window.setTimeout(function() {defer.resolve();}, 0);

		}.bind(this);

		var base_url = this.getInstanceManager().template_base_url;
		if(base_url.substr(base_url.length - 1) === '/')
		{
			base_url = base_url.slice(0, -1);	// otherwise we generate a url //api/templates, which is wrong
		}
		let dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: callback,	// return false to prevent dialog closing
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			title: 'Print',
			template: this.egw().link(base_url + '/projectmanager/templates/default/gantt_print_dialog.xet'),
			value: {
				content: {columns: this.egw().preference(pref, app) || columns_selected},
				sel_options: {columns: columns}
			}
		});
		document.body.appendChild(<HTMLElement><unknown>dialog);

		return defer;
	}

	/**
	 * Try to clean up the mess we made getting ready for printing
	 * in beforePrint()
	 */
	afterPrint( )
	{
		jQuery(this.gantt.$container).css({
			transform: '',
			'transform-origin': '',
			'margin-left': '',
		});
		// Column preference exists?  Set it now
		var value = jQuery.extend([], this.egw().preference('gantt_columns_gantt',this.getInstanceManager().app));
		if(value)
		{
			for (var i = 0; i < this.options.columns.length; i++)
			{
				this.options.columns[i].hide = value.indexOf(this.options.columns[i].name) < 0 ;
			}
			this.set_columns(this.options.columns);
		}
		this.resize();
	}
}
et2_register_widget(et2_gantt, ["gantt","projectmanager-gantt"]);

/**
 * Common look, feel & settings for all Gantt charts
 */
// Make sure the locale js file exists before including it otherwise it breaks the loading
jQuery.get(egw.webserverUrl+"/vendor/npm-asset/dhtmlx-gantt/codebase/locale/locale" + (egw.preference('lang') != "en" ? "_" +egw.preference('lang') : "") + ".js", '', function(){
	// Localize to user's language
	import(this.url);
}).fail(function(e){console.log(e)});

jQuery(function()
{
	"use strict";

	gantt.templates.api_date = gantt.date.date_to_str(gantt.config.api_date|| '%Y-%n-%d %H:%i:%s');

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

	// Label for task bar
	gantt.templates.task_text = function(start, end, task) {
		switch(task.type)
		{
			case 'milestone':
				return '';
			default:
				return task.text;
		}
	}

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
	gantt.templates.timeline_cell_class = function(item,date){
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

	// Task styling
	gantt.templates.task_class = function(start, end, task) {
		var classes = [];
		if(task.type)
		{
			classes.push(task.type);
		}
		return classes.join(" ");
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