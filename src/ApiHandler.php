<?php
/**
 * EGroupware ProjectManager: REST API
 *
 * @link https://www.egroupware.org
 * @package projectmanager
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2025 by Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Projectmanager;

use EGroupware\Api;

/**
 * REST API for Timesheet
 */
class ApiHandler extends Api\CalDAV\Handler
{
	/**
	 * @var \projectmanager_bo
	 */
	protected \projectmanager_bo $bo;

	/**
	 * Extension to append to url/path
	 *
	 * @var string
	 */
	static $path_extension = '';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct('projectmanager', $caldav);
		self::$path_extension = '';

		$this->bo = new \projectmanager_bo();
	}

	/**
	 * Options for json_encode of responses
	 */
	const JSON_RESPONSE_OPTIONS = JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR;

	/**
	 * Handle propfind in the timesheet folder / get request on the collection itself
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id =''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		$filter = [];

		// process REPORT filters or multiget href's
		$nresults = null;
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id, $nresults))
		{
			return false;
		}
		if ($id) $path = dirname($path).'/';	// carddav_name gets added anyway in the callback

		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id) filter=".array2string($filter));

		// rfc 6578 sync-collection report: filter for sync-token is already set in _report_filters
		if ($options['root']['name'] === 'sync-collection')
		{
			// callback to query sync-token, after propfind_callbacks / iterator is run and
			// stored max. modification-time in $this->sync_collection_token
			$files['sync-token'] = array($this, 'get_sync_collection_token');
			$files['sync-token-params'] = array($path, $user);

			$this->sync_collection_token = $this->more_results = null;

			$filter['order'] = 'pm_modified ASC';	// return oldest modifications first
			$filter['sync-collection'] = true;
		}

		if (isset($nresults) && $options['root']['name'] === 'sync-collection')
		{
			$files['files'] = $this->propfind_generator($path, $filter, $files['files'], (int)$nresults);
		}
		else
		{
			// return iterator, calling ourselves to return result in chunks
			$files['files'] = $this->propfind_generator($path,$filter, $files['files']);
		}
		return true;
	}

	/**
	 * Query ctag for ProjectManager
	 *
	 * @return string
	 */
	public function getctag($path,$user)
	{
		$projects = $this->bo->search('', 'MAX(pm_modified) AS ctag', 'pm_modified ASC',
			'', '', false, 'AND', [0,1]);

		return $projects[0]['ctag'] ?? 'none';
	}

	/**
	 * Chunk-size for DB queries of profind_generator
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Generator for propfind with ability to skip reporting not found ids
	 *
	 * @param string $path
	 * @param array& $filter
	 * @param array $extra extra resources like the collection itself
	 * @param int|null $nresults option limit of number of results to report
	 * @param boolean $report_not_found_multiget_ids=true
	 * @return Generator<array with values for keys path and props>
	 */
	function propfind_generator($path, array &$filter, array $extra=[], $nresults=null, $report_not_found_multiget_ids=true)
	{
		//error_log(__METHOD__."('$path', ".array2string($filter).", ".array2string($start).", $report_not_found_multiget_ids)");
		$starttime = microtime(true);
		$filter_in = $filter;

		// yield extra resources like the root itself
		$yielded = 0;
		foreach($extra as $resource)
		{
			if (++$yielded && isset($nresults) && $yielded > $nresults)
			{
				$this->sync_collection_token = Api\DateTime::user2server($resource['modified'], 'ts')-1;
				$this->more_results = true;
				return;
			}
			yield $resource;
		}

		if (!empty($filter['order']))
		{
			$order = $filter['order'];
			unset($filter['order']);
		}
		else
		{
			$order = 'egw_pm_projects.pm_id';
		}
		// detect sync-collection report
		$sync_collection_report = $filter['sync-collection'];
		unset($filter['sync-collection']);

		// stop output buffering switched on to log the response, if we should return more than 200 entries
		if (!empty($this->requested_multiget_ids) && ob_get_level() && count($this->requested_multiget_ids) > 200)
		{
			$this->caldav->log("### ".count($this->requested_multiget_ids)." resources requested in multiget REPORT --> turning logging off to allow streaming of the response");
			ob_end_flush();
		}

		$search = $filter['search'] ?? [];
		unset($filter['search']);
		[$sync_token, $sync_token_offset] = $filter['sync_token_offset'] ?? [0, 0];
		unset($filter['sync_token_offset']);
		$inital_sync_token_offset = $sync_token_offset;
		for($chunk=0; ($projects =& $this->bo->search($search, '*', $order, '', '', False, 'AND',
			[$inital_sync_token_offset+$chunk*self::CHUNK_SIZE, $nresults ?: self::CHUNK_SIZE], $filter)); ++$chunk)
		{
			// read custom-fields
			if ($this->bo->customfields)
			{
				$id2keys = array();
				foreach($projects as $key => &$project)
				{
					$id2keys[$project['pm_id']] = $key;
				}
				if (($cfs = $this->bo->read_customfields(array_keys($id2keys))))
				{
					foreach($cfs as $id => $data)
					{
						$projects[$id2keys[$id]] += $data;
					}
				}
			}
			foreach($projects as &$project)
			{
				$content = JsObjects::JsProject($project, false);
				$project = Api\Db::strip_array_keys($project, 'pm_');

				if ($sync_token != ($modified=Api\DateTime::user2server($project['modified'], 'ts')))
				{
					$sync_token = $modified;
					$sync_token_offset = 0;
				}
				$sync_token_offset++;

				// remove timesheet from requested multiget ids, to be able to report not found urls
				if (!empty($this->requested_multiget_ids) && ($k = array_search($project[self::$path_attr], $this->requested_multiget_ids)) !== false)
				{
					unset($this->requested_multiget_ids[$k]);
				}
				// sync-collection report: deleted entry need to be reported without properties
				if ($project['status'] == \projectmanager_bo::DELETED_STATUS)
				{
					yield ['path' => $path.urldecode($this->get_path($project))];
				}
				else
				{
					$props = [
						'getcontenttype' => Api\CalDAV::mkprop('getcontenttype', 'application/json'),
						'getlastmodified' => Api\DateTime::user2server($project['modified'], 'utc'),
						'displayname' => $project['title'],
						'getcontentlength' => bytes(is_array($content) ? Api\CalDAV::json_encode(json_encode($content)) : $content),
						'data' => Api\CalDAV::mkprop('data', Api\CalDAV::isJSON() || !is_array($content) ? $content : Api\CalDAV::json_encode($content)),
					];
					yield $this->add_resource($path, $project, $props);
				}
				if (++$yielded && isset($nresults) && $yielded >= $nresults)
				{
					break 2;
				}
			}
			if ($this->bo->total <= $yielded+$inital_sync_token_offset)
			{
				break;
			}
		}
		// sync-collection report --> return modified of last timesheet as sync-token
		if ($sync_collection_report)
		{
			$this->sync_collection_token = $sync_token.'_'.$sync_token_offset;
			if ($this->bo->total > $yielded+$inital_sync_token_offset)
			{
				$this->more_results = true;
			}
		}

		// report not found multiget urls
		if ($report_not_found_multiget_ids && !empty($this->requested_multiget_ids))
		{
			foreach($this->requested_multiget_ids as $id)
			{
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					$this->more_results = true;
					return;
				}
				yield ['path' => $path.$id.self::$path_extension];
			}
		}

		if ($this->debug)
		{
			error_log(__METHOD__."($path, filter=".json_encode($filter).', extra='.json_encode($extra).
				", nresults=$nresults, report_not_found=$report_not_found_multiget_ids) took ".
				(microtime(true) - $starttime)." to return $yielded resources");
		}
	}

	/**
	 * Process filter GET parameter:
	 * - filter[<json-attribute-name>]=<value>
	 * - filter[%23<custom-field-name]=<value>
	 * - filter[search]=<pattern> with string pattern like for search in the UI
	 * - filter[search][%23<custom-field-name]=<value>
	 * - filter[search][<db-column>]=<value>
	 *
	 * @param array $filter
	 * @return array
	 */
	protected function filter2col_filter(array $filter)
	{
		$cols = [];
		foreach($filter as $name => $value)
		{
			switch($name)
			{
				case 'search':
					$cols = array_merge($cols, $this->bo->search2criteria($value));
					break;
				case 'category':
					$cols['cat_id'] = $value;
					break;
				case 'status':
					$value = array_map(function ($val) use ($value)
					{
						if (!isset($this->bo->status_labels[$val]))
						{
							throw new Api\CalDAV\JsParseException("Invalid status filter value ".json_encode($value));
						}
						return $val;
					}, (array)$value);
					$cols['pm_status'] = count($value) <= 1 ? array_pop($value) : $value;
					break;
				case 'linked':
					if (!preg_match('/^([a-z_]+):(\d+)$/i', $filter['linked'], $matches) ||
						!isset($GLOBALS['egw_info']['user']['apps'][$matches[1]]) ||
						(int)$matches[2] <= 0)
					{
						throw new Api\Exception("Invalid linked-filter '$value', should be '<app-name>:<nummeric-ID>'!", 400);
					}
					$cols['pm_id'] = Api\Link::get_links($matches[1], $matches[2], 'timesheet');
					if (!$cols['pm_id']) $cols['pm_id'] = [0];  // to return nothing and not all timesheets
					break;
				default:
					if ($name[0] === '#')
					{
						$cols[$name] = $value;
					}
					else
					{
						$cols['pm_'.$name] = $value;
					}
					break;
			}
		}
		return $cols;
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$filters
	 * @param string $id
	 * @param int &$nresult on return limit for number or results or unchanged/null
	 * @return boolean true if filter could be processed
	 */
	function _report_filters($options, &$filters, $id, &$nresults)
	{
		// in case of JSON/REST API pass filters to report
		if (Api\CalDAV::isJSON() && !empty($options['filters']) && is_array($options['filters']))
		{
			$filters = $this->filter2col_filter($options['filters']) + $filters;    // + to allow overwriting default owner filter (BO ensures ACL!)
		}
		elseif (!empty($options['filters']))
		{
			/* Example of a complex filter used by Mac Addressbook
			  <B:filter test="anyof">
			    <B:prop-filter name="FN" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="EMAIL" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="NICKNAME" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			  </B:filter>
			*/
			$filter_test = isset($options['filters']['attrs']) && isset($options['filters']['attrs']['test']) ?
				$options['filters']['attrs']['test'] : 'anyof';
			$prop_filters = array();

			$matches = $prop_test = $column = null;
			foreach($options['filters'] as $n => $filter)
			{
				if (!is_int($n)) continue;	// eg. attributes of filter xml element

				switch((string)$filter['name'])
				{
					case 'param-filter':
						$this->caldav->log(__METHOD__."(...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'prop-filter':	// can be multiple prop-filter, see example
						if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
						$matches = array();
						$prop_filter = strtoupper($filter['attrs']['name']);
						$prop_test = isset($filter['attrs']['test']) ? $filter['attrs']['test'] : 'anyof';
						if ($this->debug > 1) error_log(__METHOD__."(...) prop-filter='$prop_filter', test='$prop_test'");
						break;
					case 'is-not-defined':
						$matches[] = '('.$column."='' OR ".$column.' IS NULL)';
						break;
					case 'text-match':	// prop-filter can have multiple text-match, see example
						if (!isset($this->filter_prop2cal[$prop_filter]))	// eg. not existing NICKNAME in EGroupware
						{
							if ($this->debug || $prop_filter != 'NICKNAME') error_log(__METHOD__."(...) text-match: $prop_filter {$filter['attrs']['match-type']} '{$filter['data']}' unknown property '$prop_filter' --> ignored");
							$column = false;	// to ignore following data too
						}
						else
						{
							switch($filter['attrs']['collation'])	// todo: which other collations allowed, we are always unicode
							{
								case 'i;unicode-casemap':
								default:
									$comp = ' '.$GLOBALS['egw']->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' ';
									break;
							}
							$column = $this->filter_prop2cal[strtoupper($prop_filter)];
							if (strpos($column, '_') === false) $column = 'pm_'.$column;
							if (!isset($filters['order'])) $filters['order'] = $column;
							$match_type = $filter['attrs']['match-type'];
							$negate_condition = isset($filter['attrs']['negate-condition']) && $filter['attrs']['negate-condition'] == 'yes';
						}
						break;
					case '':	// data of text-match element
						if (isset($filter['data']) && isset($column))
						{
							if ($column)	// false for properties not known to EGroupware
							{
								$value = str_replace(array('%', '_'), array('\\%', '\\_'), $filter['data']);
								switch($match_type)
								{
									case 'equals':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value);
										break;
									default:
									case 'contains':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value.'%');
										break;
									case 'starts-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value.'%');
										break;
									case 'ends-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value);
										break;
								}
								$matches[] = ($negate_condition ? 'NOT ' : '').$sql_filter;

								if ($this->debug > 1) error_log(__METHOD__."(...) text-match: $prop_filter $match_type' '{$filter['data']}'");
							}
							unset($column);
							break;
						}
					// fall through
					default:
						$this->caldav->log(__METHOD__."(".array2string($options).",,$id) unknown filter=".array2string($filter).' --> ignored');
						break;
				}
			}
			if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
			if ($prop_filters)
			{
				$filters[] = $filter = '(('.implode($filter_test=='allof'?') AND (':') OR (', $prop_filters).'))';
				if ($this->debug) error_log(__METHOD__."(path=$options[path], ...) sql-filter: $filter");
			}
		}
		// parse limit from $options['other']
		/* Example limit
		  <B:limit>
		    <B:nresults>10</B:nresults>
		  </B:limit>
		*/
		foreach((array)$options['other'] as $option)
		{
			switch($option['name'])
			{
				case 'nresults':
					$nresults = (int)$option['data'];
					//error_log(__METHOD__."(...) options[other]=".array2string($options['other'])." --> nresults=$nresults");
					break;
				case 'limit':
					break;
				case 'href':
					break;	// from addressbook-multiget, handled below
				// rfc 6578 sync-report
				case 'sync-token':
					if (!empty($option['data']))
					{
						$parts = explode('/', $option['data']);
						$filters['sync_token_offset'] = explode(self::SYNC_TOKEN_OFFSET_DELIMITER, array_pop($parts))+[null, 0];
						$filters[] = 'pm_modified>='.(int)$filters['sync_token_offset'][0];
					}
					break;
				case 'sync-level':
					if ($option['data'] != '1')
					{
						$this->caldav->log(__METHOD__."(...) only sync-level {$option['data']} requested, but only 1 supported! options[other]=".array2string($options['other']));
					}
					break;
				default:
					$this->caldav->log(__METHOD__."(...) unknown xml tag '{$option['name']}': options[other]=".array2string($options['other']));
					break;
			}
		}
		/* there is no multiget: multiget --> fetch the url's
		$this->requested_multiget_ids = null;
		if ($options['root']['name'] == 'addressbook-multiget')
		{
			$this->requested_multiget_ids = [];
			foreach($options['other'] as $option)
			{
				if ($option['name'] == 'href')
				{
					$parts = explode('/',$option['data']);
					if (($id = urldecode(array_pop($parts))))
					{
						$this->requested_multiget_ids[] = self::$path_extension ? basename($id,self::$path_extension) : $id;
					}
				}
			}
			if ($this->requested_multiget_ids) $filters[self::$path_attr] = $this->requested_multiget_ids;
			if ($this->debug) error_log(__METHOD__."(...) addressbook-multiget: ids=".implode(',', $this->requested_multiget_ids));
		}
		else*/if ($id)
		{
			$filters[self::$path_attr] = self::$path_extension ? basename($id,self::$path_extension) : $id;
		}
		//error_log(__METHOD__."() options[other]=".array2string($options['other'])." --> filters=".array2string($filters));
		return true;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		header('Content-Type: application/json');

		if (!is_array($project = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $project;
		}

		try
		{
			// only JsProject, no *DAV
			if (($type=Api\CalDAV::isJSON()))
			{
				$options['data'] = JsObjects::JsProject($project, $type);
				$options['mimetype'] = 'application/json';

				header('Content-Encoding: identity');
				header('ETag: "'.$this->get_etag($project).'"');
				return true;
			}
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
		return '501 Not Implemented';
	}

	/**
	 * Handle exception by returning an appropriate HTTP status and JSON content with an error message
	 *
	 * @param \Throwable $e
	 * @return string
	 */
	protected function handleException(\Throwable $e) : string
	{
		_egw_log_exception($e);
		header('Content-Type: application/json');
		echo json_encode([
				'error'   => $code = $e->getCode() ?: 500,
				'message' => $e->getMessage(),
				'details' => $e->details ?? null,
				'script'  => $e->script ?? null,
			]+(empty($GLOBALS['egw_info']['server']['exception_show_trace']) ? [] : [
				'trace' => array_map(static function($trace)
				{
					$trace['file'] = str_replace(EGW_SERVER_ROOT.'/', '', $trace['file']);
					return $trace;
				}, $e->getTrace())
			]), self::JSON_RESPONSE_OPTIONS);
		return (400 <= $code && $code < 600 ? $code : 500).' '.$e->getMessage();
	}

	/**
	 * Handle put request for a timesheet
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @param string $method='PUT' also called for POST and PATCH
	 * @param ?string $content_type=null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options, $id, $user=null, $prefix=null, string $method='PUT', ?string $content_type=null)
	{
		$old = $this->_common_get_put_delete($method,$options,$id);
		if (!is_null($old) && !is_array($old))
		{
			if ($this->debug) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($old));
			return $old;
		}

		$type = null;
		$project = JsObjects::parseJsProject($options['content'], $old ?: [], $content_type, $method);

		/* uncomment to return parsed data for testing
		header('Content-Type: application/json');
		echo json_encode($project, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		return "200 Ok";
		*/

		if (is_array($old))
		{
			$id = $old['id'];
			$retval = true;
		}
		else
		{
			// new entry
			$id = -1;
			$retval = '201 Created';
		}

		if (is_array($old))
		{
			$project['pm_id'] = $old['id'];
			// don't allow the client to overwrite certain values
			$project['pm_creator'] = $old['creator'];
			$project['pm_created'] = $old['created'];
		}
		else
		{
			// only set owner, if user is explicitly specified in URL (check via prefix, NOT for /addressbook/) or sync-all-in-one!)
			if ($prefix && $user)
			{
				$project['pm_creator'] = $user;
			}
			else
			{
				$project['pm_creator'] = $GLOBALS['egw_info']['user']['account_id'];
			}
		}
		if ($this->http_if_match) $project['etag'] = self::etag2value($this->http_if_match);

		if (($err = $this->bo->save($project)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) save(".array2string($project).") failed, error=$err");
			if ($err !== true)
			{
				// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
				$this->check_return_representation($options, $id, $user);
				return '412 Precondition Failed';
			}
			return '403 Forbidden';
		}
		$project = Api\Db::strip_array_keys($this->bo->data, 'pm_');

		// send necessary response headers: Location, etag, ...
		$this->put_response_headers($project, $options['path'], $retval);

		if ($this->debug > 1) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($retval));
		return $retval;
	}

	/**
	 * Handle delete request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		if (!is_array($project = $this->_common_get_put_delete('DELETE',$options,$id)))
		{
			return $project;
		}
		if ($this->http_if_match && $this->http_if_match != self::etag($project) ||
			($ok = $this->bo->delete($project['id'])) === 0)
		{
			return '412 Precondition Failed';
		}
		return true;
	}

	/**
	 * Read an entry
	 *
	 * @param string|int $id
	 * @param string $path =null implementation can use it, used in call from _common_get_put_delete
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id /*,$path=null*/)
	{
		if (($ret = $this->bo->read($id)))
		{
			$ret = Api\Db::strip_array_keys($ret, 'pm_');
		}
		return $ret;
	}

	/**
	 * Check if user has the necessary rights on an entry
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl, $entry)
	{
		return $this->bo->check_acl($acl, is_array($entry) ? $entry+['pm_creator' => $entry['owner']] : $entry);
	}
}