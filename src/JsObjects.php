<?php
/**
 * EGroupware ProjectManager - JsProject
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package projectmanager
 * @copyright (c) 2025 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;
use projectmanager_bo;

/**
 * Rendering events as JSON using new JsCalendar format
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8984
 * @link https://jmap.io/spec-calendars.html
 */
class JsObjects extends Api\CalDAV\JsBase
{
	const APP = 'projectmanager';

	const TYPE_PROJECT = 'project';
	const TYPE_PROJECT_MEMBER = 'projectMember';

	protected static $bo;

	/**
	 * Get JsProject for given project
	 *
	 * @param int|array $project
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @return string|array
	 * @throws Api\Exception\NotFound|\Exception
	 */
	public static function JsProject($project, $encode=true)
	{
		if (is_scalar($project) && !($project = self::$bo->read($project)))
		{
			throw new Api\Exception\NotFound();
		}
		if (isset($project['pm_id']))
		{
			$project = Api\Db::strip_array_keys($project, 'pm_');
		}

		$data = array_filter([
			self::AT_TYPE => self::TYPE_PROJECT,
			//'uid' => self::uid($project['uid']),
			'id' => (int)$project['id'],
			'number' => $project['number'],
			'title' => $project['title'],
			'description' => $project['description'],
			'plannedStart' => self::UTCDateTime($project['planned_start'], true),
			'realStart' => self::UTCDateTime($project['real_start'], true),
			'plannedEnd' => self::UTCDateTime($project['planned_end'], true),
			'realEnd' => self::UTCDateTime($project['real_end'], true),
			'plannedTime' => (int)$project['planed_time'],
			'usedTime' => (int)$project['used_time'],
			'replannedTime' => (int)$project['replanned_time'],
			'plannedBudget' => $project['planned_budget'],
			'usedBudget' => $project['used_budget'],
			'category' => self::categories($project['cat_id']),
			'creator' => self::account($project['creator']),
			'created' => self::UTCDateTime($project['created'], true),
			'modified' => self::UTCDateTime($project['modified'], true),
			'modifier' => self::account($project['modifier']),
			'access' => $project['access'],
			'priority' => (int)$project['priority'],
			'status' => $project['status'] ?? null,
			'completion' => (int)$project['completion'],
			'overwrite' => self::getOverwrite($project['overwrite']),
			'accountingType' => $project['accounting_type'],
			//'readable' => $project['readable'] ? self::account($project['readable'], true) : null,
			'members' => self::JsProjectMembers($project['members']),
			'egroupware.org:customfields' => self::customfields($project),
			'etag' => ApiHandler::etag($project)
		]);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	/**
	 * Parse JsProject
	 *
	 * @param string $json
	 * @param array $old=[] existing contact for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array with "ts_" prefix
	 */
	public static function parseJsProject(string $json, array $old=[], ?string $content_type=null, $method='PUT')
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH')
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::JsProject($old, false) : [], !$old);
			}

			//if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// check required fields
			if (!$old || $method !== 'PATCH')
			{
				static $required = ['title'];
				if (($missing = array_diff_key(array_filter(array_intersect_key($data, array_flip($required))), array_flip($required))))
				{
					throw new Api\CalDAV\JsParseException("Required field(s) ".implode(', ', $missing)." missing");
				}
			}
			if ($method === 'PATCH')
			{
				$project = $old;
			}
			else
			{
				// setting some reasonable defaults
				$project = [
					'pm_overwrite' => 0,
					'pm_number' => self::$bo->generate_pm_number(false),
					'pm_status' => key(\projectmanager_bo::$status_labels),
					'pm_access' => key(self::$bo->access_labels),
					'pm_accounting_type' => key(self::$bo->config['accounting_types']),
				];
			}
			// make sure we parse account-type first
			if (array_key_exists('accountingType', $data))
			{
				$data = ['accountingType' => $data['accountingType']] + $data;
			}

			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'number':
						if (($old && $old['pm_number'] !== $value || !$old) && self::$bo->not_unique())
						{
							throw new Api\CalDAV\JsParseException("Invalid value '$value' for $name: already exist, choose an other one or have one generated by NOT setting it!");
						}
						// fall-through
					case 'title':
					case 'description':
						$project['pm_'.$name] = $value;
						break;

					case 'realStart':
					case 'plannedStart':
					case 'realEnd':
					case 'plannedEnd':
						// ToDo: set overwrite
						$project[$key='pm_'.(str_starts_with($name, 'real') ? 'real_' : 'planned_').(str_ends_with($name, 'Start') ? 'start' : 'end')] =
							self::parseDateTime($value);
						self::setOverwrite($key, $project, $old);
						break;

					case 'plannedTime':
					case 'usedTime':
					case 'replannedTime':
						if ($project['pm_accounting_type'] === 'status')
						{
							throw new Api\CalDAV\JsParseException("accountingType '{$project['pm_accounting_type']}' does not allow to set a value for $name");
						}
						// ToDo: set overwrite
						$project[$key='pm_'.substr($name, 0, -4).'_time'] = isset($value) ? self::parseInt($value) : null;
						self::setOverwrite($key, $project, $old);
						break;

					case 'plannedBudget':
					case 'usedBudget':
						if (!in_array($project['pm_accounting_type'], ['budget', 'pricelist']))
						{
							throw new Api\CalDAV\JsParseException("accountingType '{$project['pm_accounting_type']}' does not allow to set a value for $name");
						}
						// ToDo: set overwrite
						if (isset($value) && !preg_match('/^\d+(\.\d+)?$/', (string)$value))
						{
							throw new Api\CalDAV\JsParseException("Invalid $name format: not an decimal value or null!");
						}
						$project[$key='pm_'.substr($name, 0, -6).'_budget'] = $value;
						self::setOverwrite($key, $project, $old);
						break;

					case 'category':
						$project['cat_id'] = self::parseCategories($value, false);
						self::setOverwrite('cat_id', $project, $old);
						break;

					case 'status':
						$project['pm_status'] = self::parseStatus($value);
						break;

					case 'access':
						if (!isset(self::$bo->access_labels[$value]))
						{
							throw new Api\CalDAV\JsParseException("Invalid $name value '$value', allowed values: '".implode("', '", self::$bo->access_labels)."'!");
						}
						$project['pm_access'] = $value;
						break;

					case 'accountingType':
						if (!isset(self::$bo->config['accounting_types'][$value]))
						{
							throw new Api\CalDAV\JsParseException("Invalid $name value '$value', allowed values: '".implode("', '", self::$bo->config['accounting_types'])."'!");
						}
						$project['pm_accounting_type'] = $value;
						break;

					case 'priority':
						if (isset($value))
						{
							$value = self::parseInt($value);
							if ($value < 1 || $value > 9)
							{
								throw new Api\CalDAV\JsParseException("Invalid $name value '$value', allowed values: null or integers between 1 and 9!");
							}
						}
						$project['pm_'.$name] = $value;
						break;

					case 'completion':
						if (isset($value))
						{
							$value = self::parseInt($value);
							if ($value < 0 || $value > 100)
							{
								throw new Api\CalDAV\JsParseException("Invalid $name value '$value', allowed values: null or integers between 0 and 100!");
							}
						}
						$project['pm_'.$name] = $value;
						break;

					case 'overwrite':   // readonly, we don't allow setting it direct
						throw new Api\CalDAV\JsParseException("Attribute $name can NOT be set directly, just (un)set the respective attributes!");
						break;

					case 'members':
						self::parseJsProjectMembers($value);
						break;

					case 'egroupware.org:customfields':
						$project = array_merge($project, self::parseCustomfields($value));
						break;

					case 'creator':
					case 'created':
					case 'modified':
					case 'modifier':
					case self::AT_TYPE:
					case 'id':
					case 'etag':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsProject', $name, $value);
		}

		return $project;
	}

	/**
	 * Get JsProjectMembers object
	 *
	 * @param ?array $members
	 * @return array
	 */
	public static function JsProjectMembers(?array $members)
	{
		return array_map(static fn($member) => [
			self::AT_TYPE => self::TYPE_PROJECT_MEMBER,
			'member' => self::account($member['member_uid']),
			'role' => $member['role_title'],
			'roleDescription' => $member['role_description'],
			'roleId' => (int)$member['role_id'],
			'roleAcl' => (int)$member['role_acl'],
			'availability' => (int)$member['member_availibility'],
			'accountID' => (int)$member['member_uid'],
		], $members ?? []);
	}

	/**
	 * Parse project-members
	 *
	 * @param array $members
	 * @return mixed
	 */
	public static function parseJsProjectMembers(array $members)
	{
		throw new Api\CalDAV\JsParseException("Attribute 'members' is (not yet) updatable, use the EGroupware UI to change it!");
	}

	/**
	 * Set / maintain pm_overwrite for $key
	 *
	 * If the value is unchanged ($old && $project[$key] == $old[$key]), then we assume overwrite is unchanged too!
	 *
	 * @param string $key
	 * @param array& $project
	 * @param array $old
	 * @return void
	 */
	protected static function setOverwrite(string $key, array& $project, array $old)
	{
		if ($project[$key] != ($old[$key]??null) || !isset($project[$key]))
		{
			$mask = self::$bo->pe_name2id[str_replace('pm_', 'pe_', $key)] ?? throw new \InvalidArgumentException("Invalid parameter key='$key'!");

			if (!isset($project[$key]))
			{
				$project['pm_overwrite'] &= ~$mask;
				$project[$key] = $old[$key] ?? null;
			}
			else
			{
				$project['pm_overwrite'] |= $mask;
			}
		}
	}

	/**
	 * Convert integer overwrite mask to an array of overwritten field-names
	 *
	 * @param int $overwrite
	 * @return string[]
	 */
	protected static function getOverwrite(int $overwrite) : array
	{
		$names = [];
		foreach(self::$bo->pe_name2id as $name => $mask)
		{
			if ($mask & $overwrite)
			{
				$parts = explode('_', $name);
				array_shift($parts);
				$names[] = $parts[0].ucfirst($parts[1]);
			}
		}
		return $names;
	}

	/**
	 * Parse a status label into it's numerical ID
	 *
	 * @param string $value
	 * @return string|null
	 * @throws Api\CalDAV\JsParseException
	 */
	protected static function parseStatus(string $value)
	{
		if (!isset(\projectmanager_bo::$status_labels[$value]))
		{
			throw new Api\CalDAV\JsParseException("Invalid status value '$value', allowed '".implode("', '", \projectmanager_bo::$status_labels)."'");
		}
		return $value;
	}

	/**
	 * Initialize our static variables
	 *
	 * @return void
	 */
	public static function init()
	{
		self::$bo = new projectmanager_bo();

		// we also need the PM_ id's and $bo->pe_name2id
		include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');
		self::$bo->pe_name2id = (new \datasource())->name2id;
	}
}
JsObjects::init();