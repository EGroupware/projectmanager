<?php
/**
 * Projectmanager - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Stefa Becker <sb-AT-stylite.de>
 * @package tracker
 * @copyright (c) 2009-9 by Stefan Becker <sb-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.projectmanager_tracking.inc.php 26515 2009-03-24 11:50:16Z leithoff $
 */

use EGroupware\Api;

/**
 * Projectmanager - tracking object for the tracker
 */
class projectmanager_tracking extends Api\Storage\Tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'projectmanager';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'pm_id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = 'pm_creator';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field = '';
	/**
	 * Translate field-name to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array();
	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = false;
	/**
	 * Reference to projectmanager_bo class calling us
	 *
	 * @access private
	 * @var procjectmanager_bo
	 */
	var $bo;

	/**
	 * Constructor
	 *
	 * @param projectmanager_bo $bo
	 * @return projectmanager_tracking
	 */
	function __construct(projectmanager_bo $bo)
	{
		$this->bo = $bo;

		//set fields for tracking
		$this->field2history = array_keys($this->bo->db_cols);
		$this->field2history = array_diff(array_combine($this->field2history,$this->field2history),array(
			'pm_modified',
		));

		parent::__construct('projectmanager');	// adding custom fields for projectmanager
	}

	/**
	 * Get a notification-config value
	 *
	 * @param string $what
	 *  - 'assigned' Who the project is assigned to
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old=null)
	{
		$projectmanager = $data['pm_id'];

		unset($old);	// not used, but required function signature
		switch($name)
		{
			case 'assigned':
				// Here we filter assigned to only those who want the notification
				$config = array();
				if(!is_array($data['pm_members'])) break;
				foreach($data['pm_members'] as $member)
				{
					$prefs_obj = new Api\Preferences($member['member_uid']);
					$prefs = $prefs_obj->read();
					$assigned = $prefs['projectmanager']['notify_assigned'];
					if(!is_array($assigned))
					{
						$assigned = explode(',',$assigned);
					}

					if(in_array($member['role_id'], $assigned))
					{
						$config[] = $member['member_uid'];
					}
				}
				break;
			case self::CUSTOM_NOTIFICATION:
				$config = Api\Config::read('projectmanager');
				if(!$config[self::CUSTOM_NOTIFICATION])
				{
					return '';
				}
				// Per-type notification
				$type_config = array();//$config[self::CUSTOM_NOTIFICATION][$data['info_type']];
				$global = $config[self::CUSTOM_NOTIFICATION]['~global~'];

				// Disabled
				if(!$type_config['use_custom'] && !$global['use_custom']) return '';

				// Type or globabl
				$config = trim(strip_tags($type_config['message'])) != '' && $type_config['use_custom'] ? $type_config['message'] : $global['message'];
				break;
		}
		return $config;
	}

	/**
	 * Get the subject for a given entry, reimplementation for get_subject in Api\Storage\Tracking
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_subject($data,$old, $deleted = NULL, $receiver = NULL)
	{
		if ($data['prefix'])
		{
			$prefix = $data['prefix'];	// async notification
		}
		return  ($prefix ? $prefix . ' ' : ''). '#'.$data['pm_id'].' - '.$data['pm_title'];
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_message($data,$old)
	{
		if ($data['message']) return $data['message'];	// async notification

		if (!$data['pm_modified'] || !$old)
		{
			return lang('New Project submitted by %1 at %2',
				Api\Accounts::username($data['pm_creator']),
				$this->datetime($data['pm_created']));
		}
		return lang('Project modified by %1 at %2',
			$data['pm_modifier'] ? Api\Accounts::username($data['pm_modifier']) : lang('Projectmanager'),
			$this->datetime($data['pm_modified']));
	}

	/**
	 * Get the details of an entry
	 *
	 * @param array|object $data
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data,$receiver=null)
	{
		$members = array();
		if ($data['pm_members'])
		{
			foreach($data['pm_members'] as $uid => $member)
			{
				$members[] = Api\Accounts::username($uid);
			}
		}

		// Use export conversion to make human friendly values
		$entry = new projectmanager_egw_record_project();
		$entry->set_record($data);
		importexport_export_csv::convert($entry, projectmanager_egw_record_project::$types, 'projectmanager');
		$converted = $entry->get_record_array();

		// Format the known fields to what Tracking needs
		foreach($converted as $name => $value)
		{
			if($this->bo->field2label[$name])
			{
				$details[$name] = array(
					'label' => lang($this->bo->field2label[$name]),
					'value' => $value,
				);
			}
		}

		// Don't want these
		foreach(array('pm_overwrite') as $remove)
		{
			unset($details[$remove]);
		}

		$details['pm_title']['type'] = 'summary';
		$details['pm_description'] = array(
			'value' => $data['pm_description'],
			'type'  => 'multiline',
		);
		$details['pm_members'] = array(
			'label' => lang('Members'),
			'value' => implode(', ', $members)
		);

		// add custom fields
		$details += $this->get_customfields($data, null, $receiver);

		return $details;
	}
}
