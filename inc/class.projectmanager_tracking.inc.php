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

/**
 * Projectmanager - tracking object for the tracker
 */
class projectmanager_tracking extends bo_tracking
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
	var $assigned_field = 'pm_modified';
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
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'sender' string send email address
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old=null)
	{
		$projectmanager = $data['pm_id'];

		//$config = $this->projectmanager->notification[$projectmanager][$name] ? $this->projectmanager->notification[$projectmanager][$name] : $this->$projectmanager->notification[0][$name];
		//no nitify configert (ToDo)
		return $config;
	}

	/**
	 * Get the subject for a given entry, reimplementation for get_subject in bo_tracking
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_subject($data,$old)
	{
		return '#'.$data['pm_id'].' - '.$data['pm_title'];
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
		if (!$data['pm_modified'] || !$old)
		{
			return lang('New Project submitted by %1 at %2',
				common::grab_owner_name($data['pm_creator']),
				$this->datetime($data['pm_created']));
		}
		return lang('Project modified by %1 at %2',
			$data['pm_modifier'] ? common::grab_owner_name($data['pm_modifier']) : lang('Projectmanager'),
			$this->datetime($data['pm_modified']));
	}
}
