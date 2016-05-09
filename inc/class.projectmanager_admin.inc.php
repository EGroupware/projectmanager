<?php
/**
 * ProjectManager - Adminstration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

/**
 * ProjectManager: Administration
 */
class projectmanager_admin
{
	/**
	 * @var array $public_functions Functions to call via menuaction
	 */
	var $public_functions = array(
		'config' => true,
	);
	var $accounting_types;
	var $duration_units;
	/**
	 * Instance of Api\Config class for projectmanager
	 *
	 * @var config
	 */
	var $config;

	/**
	 * Constructor, calls the constructor of the extended class
	 */
	function __construct()
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		$this->config = new Api\Config('projectmanager');
		$this->config->read_repository();

		$this->accounting_types = array(
			'status' => lang('No accounting, only status'),
			'times'  => lang('No accounting, only times and status'),
			'budget' => lang('Budget (no pricelist)'),
			'pricelist' => lang('Budget and pricelist'),
		);
		$this->duration_units = array(
			'd' => 'days',
			'h' => 'hours',
		);
	}

	/**
	 * Edit the site configuration
	 *
	 * @param array $content=null
	 */
	function config($content=null)
	{
		$tpl = new Etemplate('projectmanager.config');

		if ($content['save'] || $content['apply'])
		{
			foreach(array('duration_units','hours_per_workday','accounting_types','allow_change_workingtimes',
				'enable_eroles','ID_GENERATION_FORMAT','ID_GENERATION_FORMAT_SUB') as $name)
			{
				$this->config->config_data[$name] = $content[$name];
			}
			$this->config->save_repository();
			$msg = lang('Site configuration saved');
		}
		if ($content['cancel'] || $content['save'])
		{
			Egw::redirect_link('/index.php', array(
				'menuaction' => 'admin.admin_ui.index',
				'ajax' => 'true'
			), 'admin');
		}

		$content = $this->config->config_data;
		if (!$content['duration_units']) $content['duration_units'] = array_keys($this->duration_units);
		if (!$content['hours_per_workday']) $content['hours_per_workday'] = 8;
		if (!$content['accounting_types']) $content['accounting_types'] = array_keys($this->accounting_types);

		if(!$content['ID_GENERATION_FORMAT']) $content['ID_GENERATION_FORMAT'] = 'P-%Y-%04ix';
		if(!$content['ID_GENERATION_FORMAT_SUB']) $content['ID_GENERATION_FORMAT_SUB'] = '%px/%04ix';
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.lang('Site configuration');
		$tpl->exec('projectmanager.projectmanager_admin.config',$content,array(
			'duration_units'   => $this->duration_units,
			'accounting_types' => $this->accounting_types,
			'enable_eroles' => array('no','yes'),
			'allow_change_workingtimes' => array('no','yes'),
		));
	}
}
