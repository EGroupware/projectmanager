/**
 * Globals that have to exist BEFORE projectmanager/js/app.ts (and the api/ import chain it pulls
 * in) is evaluated - import this module ahead of the app, ESM evaluates imports in declaration
 * order.
 *
 * Mirrors infolog/js/test/InfologAppImportStub.ts; see its docblock for why each one is needed -
 * the requirements come from egw.js's bootstrap and the etemplate2/Et2* import chain, not from any
 * one app.  The only projectmanager-specific addition is register_app_refresh(), a real global set
 * by api/js/jsapi/jsapi.js that app.ts's constructor calls.
 */
const globals : any = window;

globals.app = globals.app || {classes: {}};
globals.app.classes = globals.app.classes || {};
globals.framework = globals.framework || {setSidebox: () => {}};
globals.register_app_refresh = globals.register_app_refresh || (() => {});

if(!globals.jQuery)
{
	const fn : any = {};
	const chainable : any = new Proxy(function() { return chainable; }, {
		get: (target, prop) =>
		{
			if(prop === 'length') return 0;
			if(prop === 'fn') return fn;
			if(prop === 'attr') return () => undefined;
			return () => chainable;
		}
	});
	globals.jQuery = globals.$ = chainable;
}

if(globals.egw)
{
	globals.egw.prefsOnly = true;
	globals.egw.registerJSONPlugin = globals.egw.registerJSONPlugin ?? (() => {});
	globals.egw.user = globals.egw.user ?? ((_field : string) => _field === 'account_id' ? 1 : null);
}

export {};
