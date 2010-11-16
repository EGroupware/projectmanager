<?php
/**
 * ProjectManager - Pricelist user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package projectmanager
 * @copyright (c) 2005-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Pricelist user interface of the projectmanager
 */
class projectmanager_pricelist_ui extends projectmanager_pricelist_bo
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var unknown_type
	 */
	var $public_functions = array(
		'index' => true,
		'view'  => true,
		'edit'  => true,
	);
	var $billable_lables = array('bookable','billable');
	/**
	 * Constructor, calls the constructor of the extended class
	 *
	 * @param int $pm_id=null project to use
	 * @return projectmanager_pricelist_ui
	 */
	function __construct($pm_id=null)
	{
		if (!is_null($pm_id) || isset($_REQUEST['pm_id']))
		{
			if (is_null($pm_id)) $pm_id = (int) $_REQUEST['pm_id'];
			$GLOBALS['egw']->session->appsession('pm_id','projectmanager',$pm_id);
		}
		else
		{
			$pm_id = (int) $GLOBALS['egw']->session->appsession('pm_id','projectmanager');
		}
		parent::__construct($pm_id);	// sets $this->pm_id
	}

	function view()
	{
		return $this->edit(null,true);
	}

	function edit($content=null,$view=false,$msg='')
	{
		$tpl = new etemplate('projectmanager.pricelist.edit');

		if (!is_array($content))
		{
			if (($pl_id = (int) $_GET['pl_id']) && $this->read(array(
				'pl_id' => $pl_id,
				'pm_id' => $this->pm_id ? array($this->pm_id,0) : 0,
			)))
			{
				// perms are checked later, see view_...prices
			}
			else	// add new price
			{
				$pl_id = 0;
				$view = false;
				$this->data = array(
					'prices' => array(),
					'project_prices' => array(),
					'pm_id' => $this->pm_id,
				);
			}
			// no READ or EDIT/ADD rights ==> close the popup
			if (!$this->check_acl($view ? EGW_ACL_READ : EGW_ACL_EDIT) &&
				!($this->pm_id && $this->check_acl($view ? EGW_ACL_READ : EGW_ACL_EDIT,$this->pm_id)))
			{
				$js = "alert('".lang('Permission denied !!!')."'); window.close();";
				common::egw_header();
				echo "<script>\n$js\n</script>\n";
				common::egw_exit();
			}
			if (count($this->data['project_prices'])) $content['tabs'] = 'project';	// open project tab
			$pm_id = count($this->data['project_prices']) ? $this->data['project_prices'][0]['pm_id'] : $this->pm_id;
		}
		else
		{
			$this->data = $content;
			foreach(array('view','button','delete_price','delete_project_price','tabs') as $key)
			{
				unset($this->data[$key]);
			}
			$pl_id = $content['pl_id'];
			$pm_id = $content['pm_id'];
			// copy only non-empty and not deleted prices to $this->data[*prices]
			foreach(array('prices' => 1,'project_prices' => 3) as $name => $row)
			{
				$this->data[$name] = array();
				$delete =& $content[$name == 'prices' ? 'delete_price' : 'delete_project_price'];
				while (isset($content[$name][$row]))
				{
					$price = $content[$name][$row];
					if ($price['pl_price'] && !$delete[$row])
					{
						$this->data[$name][] = $price;
					}
					++$row;
				}
			}
			if (!$this->data['pl_unit']) $this->data['pl_unit'] = lang('h');

			list($button) = @each($content['button']);
			switch($button)
			{
				case 'save':
				case 'apply':
					if (!($err = $this->save()))
					{
						$msg = lang('Price saved');
					}
					else
					{
						$msg = lang('Error: saving the price (%1) !!!',$err);
						$button = 'apply';	// dont close the window
					}
					$js = "window.opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
						'menuaction' => 'projectmanager.projectmanager_pricelist_ui.index',
						'msg' => $msg,
					))."';";
					if ($button == 'apply') break;
					// fall through
				case 'cancel':
					$js .= 'window.close();';
					echo '<html><body onload="'.$js.'"></body></html>';
					common::egw_exit();
					break;

				case 'edit':
					$view = false;	// acl is ensured later, see $view_*prices
					break;
			}
		}
		$view_prices = $view || count($this->data['prices']) && !$this->check_acl(EGW_ACL_EDIT);
		$view_project_prices = $view || !$this->check_acl(EGW_ACL_EDIT,$this->pm_id);
		$view = $view || $view_prices && $view_project_prices;	// nothing to edit => no rights

		$content = $this->data + array(
			'msg' => $msg,
			'js'  => $js ? "<script>\n".$js."\n</script>" : '',
			'view' => $view,
			'view_prices' => $view_prices,
			'view_project_prices' => $view_project_prices,
			'tabs' => $content['tabs'],
		);
		// adjust index and add empty price-lines for adding new prices
		$content['prices'] = array_merge(array(1),$view_prices ? array() : array(array('pl_price'=>'')),$this->data['prices']);
		$content['project_prices'] = array_merge(array(1,2,3),$view_project_prices ? array() : array(array('pl_price'=>'')),$this->data['project_prices']);

		$preserv = $this->data + array(
			'view'  => $view,
		);
		if (!$this->data['pm_id']) $preserv['pm_id'] = $this->pm_id;

		$readonlys = array(
			'button[delete]' => !$pl_id || !$this->check_acl(EGW_ACL_EDIT_BUDGET,$pl_id),
		);
		// preserv the "real" prices, with there new keys
		foreach(array('prices','project_prices') as $name)
		{
			unset($preserv[$name]);
			foreach($content[$name] as $key => $price)
			{
				if (is_array($price) && count($price) > 1)
				{
					$preserv[$name][$key] = $price;
				}
			}
		}
		// set general data and price readonly, if $view or price belongs to general pricelist and no edit there
		if ($view || count($this->data['prices']) && !$this->check_acl(EGW_ACL_EDIT))
		{
			foreach($this->db_cols as $name => $data)
			{
				$readonlys[$name] = true;
			}
			for($n = 0; $n <= count($this->data['prices']); ++$n)
			{
				$readonlys['prices['.(1+$n).'][pl_price]'] =
				$readonlys['prices['.(1+$n).'][pl_validsince]'] = true;
			}
		}
		// set project-spez. prices readonly, if view or no edit-rights there
		if ($view || !$this->check_acl(EGW_ACL_EDIT,$this->pm_id))
		{
			foreach(array('pl_billable','pl_customertitle') as $name)
			{
				$readonlys[$name] = true;
			}
			for($n = 0; $n <= count($this->data['project_prices']); ++$n)
			{
				$readonlys['project_prices['.(3+$n).'][pl_price]'] =
				$readonlys['project_prices['.(3+$n).'][pl_validsince]'] = true;
			}
		}
		$readonlys['button[save]'] = $readonlys['button[apply]'] = $view;
		$readonlys['button[edit]'] = !$view || !$this->check_acl(EGW_ACL_EDIT) && !$this->check_acl(EGW_ACL_EDIT,$this->pm_id);

		if (!$this->pm_id)	// no project tab for the general pricelist
		{
			$readonlys['tabs']['project'] = true;
		}
		// no general price tab, if there are none and no rights to edit the general pricelist
		if (!count($this->data['prices']) && !$this->check_acl(EGW_ACL_EDIT))
		{
			$readonlys['tabs']['price'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.
			($view ? lang('View price') :  ($pl_id ? lang('Edit price') : lang('Add price'))) .
			($this->pm_id ? ': ' . $this->project->data['pm_number'] . ': ' .$this->project->data['pm_title'] : '');

		//_debug_array($content);
		//_debug_array($readonlys);
		return $tpl->exec('projectmanager.projectmanager_pricelist_ui.edit',$content,array(
			'pl_billable' => $this->billable_lables,
			'gen_pl_billable' => $this->billable_lables,
		),$readonlys,$preserv,2);
	}

	/**
	 * query pricelist for nextmatch
	 *
	 * reimplemented from so_sql to disable action-buttons based on the acl and make some modification on the data
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('pricelist','projectmanager',$query);

		if ($query['cat_id'])
		{
			$query['col_filter']['cat_id'] = $query['cat_id'];
		}
		if ($query['col_filter']['pm_id'] === '' || !$this->check_acl(EGW_ACL_READ,$query['col_filter']['pm_id']))
		{
			unset($query['col_filter']['pm_id']);
		}
		elseif ($query['col_filter']['pm_id'] != $this->pm_id)
		{
			$this->__construct($query['col_filter']['pm_id']);
		}
		if ($query['col_filter']['pl_billable'] === '') unset($query['col_filter']['pl_billable']);

		$total = parent::get_rows($query,$rows,$readonlys,true);

		$readonlys = array();
		foreach($rows as $n => $val)
		{
			$row =& $rows[$n];
			if (!$this->check_acl(EGW_ACL_EDIT) && !($this->pm_id && $this->check_acl(EGW_ACL_EDIT,$this->pm_id)))
			{
				$readonlys["edit[$row[pl_id]]"] = true;
			}
			// we only delete prices from the shown pricelist, not inhirited ones or onces from the general list
			if ($row['pm_id'] != $this->pm_id || !$this->check_acl(EGW_ACL_EDIT,$this->pm_id))
			{
				$readonlys["delete[$row[pm_id]:$row[pl_id]]"] = true;
			}
		}
		return $total;
	}

	function index($content=null,$msg='')
	{
		while (!$this->check_acl(EGW_ACL_READ,$this->pm_id))
		{
			if ($this->pm_id)	// try falling back to the general pricelist
			{
				$GLOBALS['egw']->session->appsession('pm_id','projectmanager',$_REQUEST['pm_id'] = $this->pm_id = 0);
			}
			else
			{
				$GLOBALS['egw']->redirect_link('/index.php',array(
					'menuaction' => 'projectmanager.projectmanager_ui.index',
					'msg' => lang('Permission denied !!!'),
				));
			}
		}
		$tpl = new etemplate('projectmanager.pricelist.list');

		if (!is_array($content))
		{
			$content = array();
		}
		elseif($content['nm']['rows']['delete'])
		{
			list($id) = @each($content['nm']['rows']['delete']);
			list($pm_id,$pl_id) = explode(':',$id);

			if ($pl_id && $this->delete(array('pm_id' => $pm_id,'pl_id' => $pl_id)))
			{
				$msg = lang('Price deleted');
			}
			else
			{
				$msg = lang('Permission denied !!!');
			}
		}
		$content['msg'] = $msg ? $msg : $_GET['msg'];
		$content['nm'] = $GLOBALS['egw']->session->appsession('pricelist','projectmanager');
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'projectmanager.projectmanager_pricelist_ui.get_rows',
				'no_filter'      => true,
				'no_filter2'     => true,
				'order'          =>	'pl_title',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
			);
		}
		$content['nm']['col_filter']['pm_id'] = $this->pm_id;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('projectmanager').' - '.($this->pm_id ?
			lang('Pricelist') .': ' . $this->project->data['pm_number'] . ': ' .$this->project->data['pm_title'] :
			lang('General pricelist'));

		$projects = array();
		foreach((array)$this->project->search(array(
			'pm_status' => 'active',
			'pm_id'     => $this->pm_id,		// active or the current one
		),$this->project->table_name.'.pm_id AS pm_id,pm_number,pm_title','pm_number','','',False,'OR',false,array('pm_accounting_type' => 'pricelist')) as $project)
		{
			$projects[$project['pm_id']] = $project['pm_number'].': '.$project['pm_title'];
		}
		$projects[0] = lang('General pricelist');

		$readonlys = array(
			// show add button only, if user has rights to add a new price
			'add' => !$this->check_acl(EGW_ACL_EDIT,$this->pm_id),
		);
		return $tpl->exec('projectmanager.projectmanager_pricelist_ui.index',$content,array(
			'pl_billable' => $this->billable_lables,
			'pm_id' => $projects,
		),$readonlys);
	}
}