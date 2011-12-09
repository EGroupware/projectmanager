/**
 * JS for active gantt chart
 */

var gantt_id = 'exec[gantt]';
var ganttChart = null;
var changes = {};

jQuery(document).ready(function(){
	ganttChart = new GanttChart();
	ganttChart.setImagePath(egw.webserverUrl+"/phpgwapi/js/dhtmlxGantt/codebase/imgs/");
	ganttChart.correctError = true;
	ganttChart.showNewProject(false);

	// Set according to user preferences
	ganttChart.hoursInDay = gantt_hours_per_day;
	ganttChart.hourInPixelsWork = ganttChart.dayInPixels / ganttChart.hoursInDay;

	// Events
	// Prevent editing tasks with no edit rights
	ganttChart.attachEvent("onTaskStartDrag", function(task) {
		return task.TaskInfo.egw_data.edit;
	});
	ganttChart.attachEvent("onTaskStartResize", function(task) {
		return task.TaskInfo.egw_data.edit;
	});

	// Change of start or duration
	ganttChart.attachEvent("onTaskEndDrag", function(task) {
		jQuery('.save > input').removeAttr('disabled');
		var egw_data = task.TaskInfo.egw_data;
		var id = egw_data.pe_id;
		if(task.TaskInfo.EST != egw_data.startDate) {
			if(!changes[id]) changes[id] = {}
			changes[id].start = task.TaskInfo.EST.valueOf() / 1000;
		}
	});
	ganttChart.attachEvent("onTaskEndResize", function(task) {
		jQuery('.save > input').removeAttr('disabled');
		var egw_data = task.TaskInfo.egw_data;
		var id = egw_data.pe_id;
		if(task.TaskInfo.Duration != egw_data.duration) {
			if(!changes[id]) changes[id] = {}
			changes[id].duration = task.TaskInfo.Duration;
		}
	});

	// Form filters
	jQuery(':input', document.forms[0]).not('.save').change(getGanttData);

	// Save
	jQuery('.save > input').click(function() {
		console.info(changes);
		jQuery(this).attr('disabled',true);
		var req = new egw_json_request(
			'projectmanager.projectmanager_gantt.ajax_update', 
			[changes, egw_json_getFormValues(document.forms[0])]
		);
		req.sendRequest(true, ganttLoadProject, ganttChart);
		changes = {};
	}).attr('disabled', 'true');
	
	// Fetch data
	getGanttData();
});

function getGanttData() {
	if(jQuery.isEmptyObject(changes) || confirm(egw.lang('Discard changes?')))
	{
		var req = new egw_json_request(
			'projectmanager.projectmanager_gantt.ajax_gantt_project', 
			[gantt_project_ids, egw_json_getFormValues(document.forms[0])]
		);
		req.sendRequest(true, ganttLoadProject, ganttChart);
		changes = {};
	}
}

/**
 * Load data from eGW style format into gantt chart, then set up 
 * eGW's UI actions.
 */
function ganttLoadProject(data) {
	if(this.arrProjects.length > 0)
	{
		for(var i = 0; i < this.arrProjects.length; i++) {
			var project = this.arrProjects[i];
			if(project.Project) this.deleteProject(project.Project.Id);
		}
		this.clearAll();
		jQuery('.ganttContent').empty();
	}

	var editable = false;

	// Load data
	for(var i = 0; i < data.length; i++) {
		var project = ganttAddProject(this, data[i]);
		editable = editable || data[i].edit;
		this.addProject(project);
	}

	this.setEditable(editable);

	this.create(gantt_id);

	// Change events
	for(var i = 0; i < this.arrProjects.length; i++) {
		var project = this.arrProjects[i];

		if(project.projectNameItem) {
			jQuery(project.projectNameItem).click(project,function(e) {
				if(e.data.Project) {
					egw_open(e.data.Project.Id, 'projectmanager');
				}
			});
			// Tasks
			for(var j = 0; j < project.arrTasks.length; j++)
			{
				var task = project.arrTasks[j];
				jQuery(task.cTaskNameItem[0]).click(task.TaskInfo, function(e) {
					egw_open(e.data.egw_data.pe_app_id, e.data.egw_data.pe_app);
				});
			}
		}
	}
}

function ganttAddProject(graph, data) {
	var project = new GanttProjectInfo(
		data.pm_id,
		data.name,
		new Date(data.start*1000 || new Date().getTime())
	);
	if(!data.elements) return project;
	for(var j = 0; j < data.elements.length; j++) {
		if(data.elements[j].pe_id == undefined) {
			// List sub-projects as separate projects
			graph.addProject(ganttAddProject(graph,data.elements[j]));
		} else {
			var task = ganttAddElement(data,data.elements[j]);
			project.addTask(task);
		}
	}
	return project;
}
function ganttAddElement(project_data, data) {

	var task_data = {
		id: data.pe_id,
		name: data.pe_title,
		startTime: new Date(data.pe_start*1000),
		duration: data.duration || 1,
		percentage: data.pe_completion ? parseInt(data.pe_completion.substr(0,data.pe_completion.length-1)) : 0,
	};
	// Chart can only handle single, correct constraints
	if(data.pe_constraint) {
		var previous;
		for(var i = 0; i < data.pe_constraint.length; i++) {
			for(var j = 0; j < project_data.elements.length; j++) {
				if(project_data.elements[j].pe_id == data.pe_constraint[i]) {
					previous = project_data.elements[j];
					task_data.previousTaskId = data.pe_constraint[i];
					break;
				}
			}
		}
	}
	// Chart can only handle one owner / task
	if(data.pe_resources && project_data.pm_members) {
		for(var i = 0; i < data.pe_resources.length; i++) {
			if(project_data.pm_members[data.pe_resources[i]]) {
				task_data.taskOwner = project_data.pm_members[data.pe_resources[i]].name;
				break;
			}
		}
	}
	var task = new GanttTaskInfo(
		task_data.id,
		task_data.name,
		task_data.startTime,
		task_data.duration,
		task_data.percentage,
		task_data.previousTaskId
	);
	data.startTime = task_data.startTime;
	task.egw_data = data;
	return task;
}
