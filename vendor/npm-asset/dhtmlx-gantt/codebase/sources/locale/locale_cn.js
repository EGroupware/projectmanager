/*
@license

dhtmlxGantt v.6.2.5 Standard

This version of dhtmlxGantt is distributed under GPL 2.0 license and can be legally used in GPL projects.

To use dhtmlxGantt in non-GPL projects (and get Pro version of the product), please obtain Commercial/Enterprise or Ultimate license on our site https://dhtmlx.com/docs/products/dhtmlxGantt/#licensing or contact us at sales@dhtmlx.com

(c) XB Software Ltd.

*/
(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(window, function() {
return /******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/codebase/sources/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./sources/locale/locale_cn.js");
/******/ })
/************************************************************************/
/******/ ({

/***/ "./sources/locale/locale_cn.js":
/*!*************************************!*\
  !*** ./sources/locale/locale_cn.js ***!
  \*************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/*
Translation by FreezeSoul

Update 26/10/2015:
Translation of new labels by zwh8800
 https://github.com/DHTMLX/gantt/pull/7

*/
gantt.config.day_date = "%M %d日 %D";
gantt.config.default_date = "%Y年 %M %d日";
gantt.config.month_date = "%Y年 %M";

gantt.locale = {
	date: {
		month_full: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
		month_short: ["1月", "2月", "3月", "4月", "5月", "6月", "7月", "8月", "9月", "10月", "11月", "12月"],
		day_full: ["星期日", "星期一", "星期二", "星期三", "星期四", "星期五", "星期六"],
		day_short: ["日", "一", "二", "三", "四", "五", "六"]
	},
	labels: {
		new_task: "新任務",
		dhx_cal_today_button: "今天",
		day_tab: "日",
		week_tab: "周",
		month_tab: "月",
		new_event: "新建日程",
		icon_save: "保存",
		icon_cancel: "关闭",
		icon_details: "详细",
		icon_edit: "编辑",
		icon_delete: "删除",
		confirm_closing: "请确认是否撤销修改!", //Your changes will be lost, are your sure?
		confirm_deleting: "是否删除日程?",
		section_description: "描述",
		section_time: "时间范围",
		section_type: "类型",

		/* grid columns */

		column_wbs: "工作分解结构",
		column_text: "任务名",
		column_start_date: "开始时间",
		column_duration: "持续时间",
		column_add: "",

		/* link confirmation */

		link: "关联",
		confirm_link_deleting: "将被删除",
		link_start: " (开始)",
		link_end: " (结束)",

		type_task: "任务",
		type_project: "项目",
		type_milestone: "里程碑",

		minutes: "分钟",
		hours: "小时",
		days: "天",
		weeks: "周",
		months: "月",
		years: "年",

		/* message popup */
		message_ok: "OK",
		message_cancel: "关闭",

		/* constraints */
		section_constraint: "Constraint",
		constraint_type: "Constraint type",
		constraint_date: "Constraint date",
		asap: "As Soon As Possible",
		alap: "As Late As Possible",
		snet: "Start No Earlier Than",
		snlt: "Start No Later Than",
		fnet: "Finish No Earlier Than",
		fnlt: "Finish No Later Than",
		mso: "Must Start On",
		mfo: "Must Finish On",

		/* resource control */
		resources_filter_placeholder: "type to filter",
		resources_filter_label: "hide empty"
	}
};



/***/ })

/******/ });
});