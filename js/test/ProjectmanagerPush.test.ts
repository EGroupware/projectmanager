import {assert} from "@open-wc/testing";
import "./ProjectmanagerAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same way
// infolog/js/test/InfologPushGrantCheck.test.ts does, before app.ts pulls it in
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path - see
 * addressbook/js/test/MailVcardMessage.test.ts's docblock for why.
 */
const APP_SOURCE = '/projectmanager/js/app.ts';


/**
 * Coverage for ProjectmanagerApp.push().
 *
 * Project elements are entries of other applications, so this is the rare push handler that has
 * to care about more than its own application.  What it does with each kind of push is worth
 * pinning down, because two of the decisions are easy to get subtly wrong and invisible in the
 * UI until a row goes stale:
 *
 * - the id handed to nextmatch.refresh() has to be the element list's row-id,
 *   pe_app:pe_app_id:pe_id.  It reaches the server as col_filter[elem_id], where
 *   projectmanager_elements_ui::get_rrows() splits it on ":" for the pe_id - and a push names
 *   none of that directly, the three parts have to be built from its acl plus its id.
 * - a push for an entry of any other application may only ever touch rows we already hold.  The
 *   push goes to every connected client, for every entry of every application, so anything that
 *   asks the server on spec would turn one person saving an infolog into a request from everyone.
 *
 * push() only reads this.egw, this.views and this.view, so it is exercised on the prototype with
 * a minimal `this` rather than through a constructed app.
 */
describe('ProjectmanagerApp.push()', () =>
{
	let ProjectmanagerApp : any;
	let original_egw : any;

	const PM_ID = 7;
	const OTHER_PM_ID = 99;
	const PREFIX = 'projectmanager_elements';

	// what the stubbed egw was asked to do
	let refreshedUIDs : {uid : string | RegExp, type : string}[];
	let knownUIDs : string[];
	// the projectmanager.current_project preference, where the project usually comes from
	let currentProject : number | string;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		ProjectmanagerApp = (<any>window).app.classes.projectmanager;
	});

	beforeEach(() =>
	{
		refreshedUIDs = [];
		knownUIDs = [];
		currentProject = PM_ID;
		original_egw = (<any>window).egw;
		(<any>window).egw = Object.assign(function() {return (<any>window).egw;}, {
			...(original_egw || {}),
			dataRefreshUIDs: (uid : string | RegExp, type : string) => refreshedUIDs.push({uid, type}),
			dataHasUID: (uid : string) => knownUIDs.indexOf(uid) >= 0,
			preference: (name : string, app : string) =>
				name === 'current_project' && app === 'projectmanager' ? currentProject : undefined
		});
	});

	afterEach(() =>
	{
		(<any>window).egw = original_egw;
	});

	/**
	 * A nextmatch that records what it was asked to do.  Its filters carry no pm_id unless one is
	 * given - that is the shape a live element list actually has, see _elementListProject().
	 */
	function fakeNm(pm_id : number | string = undefined)
	{
		return {
			activeFilters: {col_filter: {pm_id: pm_id}},
			refreshed: <any[]>[],
			appliedFilters: 0,
			refresh: function(ids, type) {this.refreshed.push({ids, type});},
			applyFilters: function() {this.appliedFilters++;}
		};
	}

	function fakeTree(nodes : string[] = [])
	{
		return {
			refreshed: <string[]>[],
			deleted: <string[]>[],
			getNode: (id : string) => nodes.indexOf(id) >= 0 ? {id} : null,
			refreshItem: function(id) {this.refreshItem_calls = (this.refreshItem_calls || 0) + 1; this.refreshed.push(id);},
			deleteItem: function(id) {this.deleted.push(id);}
		};
	}

	/**
	 * An app instance without running the constructor - push() needs nothing it sets up
	 */
	function makeApp({elements = <any>null, list = <any>null, tree = <any>null, view = 'elements'} = {})
	{
		const app : any = Object.create(ProjectmanagerApp.prototype);
		app.egw = (<any>window).egw;
		app.view = view;
		app.shown = [];
		app.show = (name) => app.shown.push(name);
		app.views = {
			list: {etemplate: list || tree ? {widgetContainer: {getWidgetById: (id) => id === 'nm' ? list : tree}} : null},
			elements: {etemplate: elements ? {widgetContainer: {getWidgetById: () => elements}} : null},
			gantt: {etemplate: null},
			prices: {etemplate: null}
		};
		return app;
	}

	function elementPush(overrides = {})
	{
		return Object.assign({
			app: 'projectelement',
			id: 42,
			type: 'update-in-place',
			account_id: 1,
			acl: {pm_id: PM_ID, pe_id: 42, pe_app: 'infolog', pe_app_id: 5}
		}, overrides);
	}

	describe('an element of the project we are showing', () =>
	{
		it('refreshes the row by its unprefixed pe_app:pe_app_id:pe_id', () =>
		{
			const nm = fakeNm();
			knownUIDs = [`${PREFIX}::infolog:5:42`];
			const app = makeApp({elements: nm});

			app.push(elementPush());

			assert.deepEqual(nm.refreshed, [{ids: 'infolog:5:42', type: 'update-in-place'}],
				'the row-id the server filters on, built from the push\'s acl and id');
		});

		it('adds a row we do not have yet', () =>
		{
			const nm = fakeNm();
			const app = makeApp({elements: nm});

			app.push(elementPush());

			assert.lengthOf(nm.refreshed, 1, 'a new element of this project has to reach the list');
			assert.equal(nm.refreshed[0].ids, 'infolog:5:42');
		});

		it('drops the row on delete without asking the server', () =>
		{
			const nm = fakeNm();
			knownUIDs = [`${PREFIX}::infolog:5:42`];
			const app = makeApp({elements: nm});

			app.push(elementPush({type: 'delete'}));

			assert.deepEqual(refreshedUIDs, [{uid: `${PREFIX}::infolog:5:42`, type: 'delete'}]);
			assert.isEmpty(nm.refreshed, 'a delete needs no row data');
		});
	});

	describe('finding the project the element list shows', () =>
	{
		it('takes it from the current_project preference when the filters have none', () =>
		{
			const nm = fakeNm();
			const app = makeApp({elements: nm});

			app.push(elementPush());

			assert.lengthOf(nm.refreshed, 1,
				'a list reached through the tree or a favourite has no pm_id in its filters');
		});

		it('prefers a pm_id the nextmatch does filter on', () =>
		{
			const nm = fakeNm(OTHER_PM_ID);
			currentProject = PM_ID;
			const app = makeApp({elements: nm});

			app.push(elementPush({acl: {pm_id: OTHER_PM_ID, pe_id: 42, pe_app: 'infolog', pe_app_id: 5}}));

			assert.lengthOf(nm.refreshed, 1, 'a favourite naming a project overrides the preference');
		});
	});

	describe('an element of some other project', () =>
	{
		it('is ignored when we do not have the row', () =>
		{
			const nm = fakeNm();
			const app = makeApp({elements: nm});

			app.push(elementPush({acl: {pm_id: OTHER_PM_ID, pe_id: 42, pe_app: 'infolog', pe_app_id: 5}}));

			assert.isEmpty(nm.refreshed, 'nothing to show, and we can not see the project');
			assert.isEmpty(refreshedUIDs);
		});

		it('is refreshed when we do have the row, so sub-project elements stay current', () =>
		{
			const nm = fakeNm();
			knownUIDs = [`${PREFIX}::infolog:5:42`];
			const app = makeApp({elements: nm});

			app.push(elementPush({acl: {pm_id: OTHER_PM_ID, pe_id: 42, pe_app: 'infolog', pe_app_id: 5}}));

			assert.lengthOf(nm.refreshed, 1, 'showing sub-elements pulls in other projects\' elements');
		});

		it('is dropped on delete whichever project it was in', () =>
		{
			const nm = fakeNm();
			const app = makeApp({elements: nm});

			app.push(elementPush({type: 'delete', acl: {pm_id: OTHER_PM_ID, pe_id: 42, pe_app: 'infolog', pe_app_id: 5}}));

			assert.lengthOf(refreshedUIDs, 1, 'dataRefreshUIDs only ever touches rows we hold anyway');
		});
	});

	describe('an entry of another application', () =>
	{
		/**
		 * The cumulate case: a timesheet rolling up into the infolog it is linked to changes the
		 * infolog's row, but the timesheet itself is filtered out of the list, so no element push
		 * can point at the row that has to change.
		 */
		it('matches its row without knowing the pe_id', () =>
		{
			const app = makeApp({elements: fakeNm()});

			app.push({app: 'infolog', id: 5, type: 'update', account_id: 1, acl: {}});

			assert.lengthOf(refreshedUIDs, 1);
			const pattern = <RegExp>refreshedUIDs[0].uid;
			assert.instanceOf(pattern, RegExp);
			assert.isTrue(pattern.test(`${PREFIX}::infolog:5:42`), 'has to find the row for infolog 5');
			assert.isFalse(pattern.test(`${PREFIX}::infolog:55:42`), 'must not match a different entry');
			assert.isFalse(pattern.test(`${PREFIX}::timesheet:5:42`), 'must not match another application');
			assert.isFalse(pattern.test(`infolog::5`), 'must not reach outside the element list');
		});

		it('never asks the server for a row it does not have', () =>
		{
			const nm = fakeNm();
			const app = makeApp({elements: nm});

			app.push({app: 'addressbook', id: 123, type: 'add', account_id: 1, acl: {}});

			assert.isEmpty(nm.refreshed,
				'every client gets every entry of every application - asking on spec would flood the server');
		});

		it('survives an id that means something in a regular expression', () =>
		{
			const app = makeApp({elements: fakeNm()});

			// a recurring calendar event's id is "<cal_id>:<recur_date>"; "." is the one that bites
			app.push({app: 'calendar', id: '12.4', type: 'update', account_id: 1, acl: {}});

			const pattern = <RegExp>refreshedUIDs[0].uid;
			assert.isTrue(pattern.test(`${PREFIX}::calendar:12.4:42`));
			assert.isFalse(pattern.test(`${PREFIX}::calendar:1204:42`), 'an unescaped "." would match this');
		});

		it('does nothing at all without an element list', () =>
		{
			const app = makeApp({elements: null});

			app.push({app: 'infolog', id: 5, type: 'update', account_id: 1, acl: {}});

			assert.isEmpty(refreshedUIDs);
		});
	});

	describe('a project', () =>
	{
		it('refreshes its tree node and its row in the project list', () =>
		{
			const list = fakeNm();
			const tree = fakeTree([`projectmanager::${PM_ID}`]);
			const app = makeApp({list, tree, view: 'list'});

			app.push({app: 'projectmanager', id: PM_ID, type: 'edit', account_id: 1, acl: null});

			assert.deepEqual(tree.refreshed, [`projectmanager::${PM_ID}`]);
			assert.deepEqual(list.refreshed, [{ids: PM_ID, type: 'edit'}]);
		});

		it('reloads the whole element list when it is the project we are showing', () =>
		{
			const elements = fakeNm(PM_ID);
			const app = makeApp({elements});

			app.push({app: 'projectmanager', id: PM_ID, type: 'edit', account_id: 1, acl: null});

			assert.equal(elements.appliedFilters, 1,
				'the project is the list\'s first row and where its totals come from');
			assert.isEmpty(elements.refreshed, 'one row would leave the totals stale');
		});

		it('leaves the element list alone for any other project', () =>
		{
			const elements = fakeNm(PM_ID);
			const app = makeApp({elements});

			app.push({app: 'projectmanager', id: OTHER_PM_ID, type: 'edit', account_id: 1, acl: null});

			assert.equal(elements.appliedFilters, 0);
		});

		it('goes back to the project list when the one we are showing is deleted', () =>
		{
			const elements = fakeNm(PM_ID);
			const tree = fakeTree([`projectmanager::${PM_ID}`]);
			const app = makeApp({elements, tree, view: 'elements'});

			app.push({app: 'projectmanager', id: PM_ID, type: 'delete', account_id: 1, acl: null});

			assert.deepEqual(tree.deleted, [`projectmanager::${PM_ID}`]);
			assert.deepEqual(app.shown, ['list'], 'there is nothing left to show');
			assert.equal(elements.appliedFilters, 0);
		});
	});
});
