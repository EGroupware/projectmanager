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
	}
});