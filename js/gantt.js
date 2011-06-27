/**
 * JS for active gantt chart
 */

dojo.require('dojox.gantt.GanttChart');
dojo.ready(function(){
var ganttChart = new dojox.gantt.GanttChart({
	readOnly: true,				//optional: determine if gantt chart is editable
	dataFilePath: "gantt_default.json",	//optional: json data file path for load and save, default is "gantt_default.json"
	height: $('#divSubContainer').height(),	//optional: chart height in pixel, default is 400px
	width: $('#divAppbox').width()-50,	//optional: chart width in pixel, default is 600px
	withResource: true,			//optional: display the resource chart or not
	autoCorrectError: true,			// ?
}, "gantt");				//"gantt" is the node container id of gantt chart widget

// Set slightly larger names
ganttChart.maxWidthPanelNames = 200;	// pixels
ganttChart.maxWidthTaskNames = 200;	// pixels

// Set according to user preferences
ganttChart.hsPerDay = gantt_hours_per_day;

// Fetch data
var req = new egw_json_request('projectmanager.projectmanager_gantt.ajax_gantt_project', [gantt_project_ids]);
req.sendRequest(true, ganttLoadProject, ganttChart);
});

function ganttLoadProject(data) {
console.log(data);
	for(var i = 0; i < data.length; i++) {
		var project = ganttAddProject(this, data[i]);
		
		this.addProject(project);
	}
	this.init();
}

function ganttAddProject(graph, data) {
	var project = new dojox.gantt.GanttProjectItem({
		id: data.pm_id,
		name: data.name,
		startDate: new Date(data.pm_real_start*1000 || data.pm_planned_start*1000 || new Date().getTime())
	});
	for(var j = 0; j < data.elements.length; j++) {
		if(data.elements[j].pe_id == undefined) {
			graph.addProject(ganttAddProject(graph,data.elements[j]));
		} else {
			var task = ganttAddElement(data,data.elements[j]);
			// Gantt fails if dates don't work out
		//	if(task.startTime < project.startDate) project.startDate = task.startTime;
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
					if(previous.pe_start > data.pe_start) {
						console.warn('Constraint does not match times. ' + data.pe_title + ' after ' + previous.pe_title);
					}
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
	var task = new dojox.gantt.GanttTaskItem(task_data);
	return task;
}
