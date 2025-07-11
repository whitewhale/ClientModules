<?php

class EMSRESTClient { // REST client for EMS
public $ems_errors=[];

function __construct($rest) { // creates a new REST server client
global $_LW;
if (substr($rest, -1, 1)=='/') {
	$rest=substr($rest, 0, -1);
};
$this->api_url=$rest; // set the API url
$this->getToken(); // get a user token
if (empty($this->token)) { // bail on missing token
	return false;
};
}

public function getToken() { // gets a client token
global $_LW;
if ($this->token=$_LW->getVariable('ems-client-token')) { // get cached token if possible
	if ($_LW->getVariable('ems-client-token-check')==1) { // and reuse until next check
		return true;
	};
	$_LW->setVariable('ems-client-token-check', 1, 300);
	if ($response=$this->getResponse('/statuses')) { // check if the token is still valid by requesting statuses
		if (!empty($response['results'])) { // if statuses received
			return true; // reuse until next check
		};
	};
};
$response=@shell_exec('curl --data "{\"clientid\":\"'.preg_replace('~[^a-zA-Z0-9_\-]~', '', $_LW->REGISTERED_APPS['ems']['custom']['username']).'\",\"secret\":\"'.preg_replace('~[^a-zA-Z0-9_\-]~', '', $_LW->REGISTERED_APPS['ems']['custom']['password']).'\"}" -H "Content-Type: application/json" '.escapeshellarg($_LW->REGISTERED_APPS['ems']['custom']['rest'].'/clientauthentication')); // get token from server and cache it
if (@$response=@json_decode($response, true)) {
	if (!empty($response['clientToken'])) {
		$this->token=$response['clientToken'];
		$_LW->setVariable('ems-client-token', $this->token, 86400);
		return true;
	};
};
$_LW->logError('EMS: Could not fetch token.', false, true);
return false;
}

public function getResponse($endpoint, $params=false, $payload=false) { // gets the response from EMS
global $_LW;
if ($endpoint=='/bookings/actions/search') { // relax memory limit for large booking responses
	ini_set('memory_limit', '1G');
};
$response=@shell_exec('curl -m 30'.(!empty($payload) ? ' --request POST --data '.escapeshellarg(@json_encode($payload)).' -H "Content-Type: application/json"' : '').' -H '.escapeshellarg('x-ems-api-token: '.$this->token).' '.escapeshellarg($_LW->REGISTERED_APPS['ems']['custom']['rest'].$endpoint.(!empty($params) ? '?'.http_build_query($params) : ''))); // request response
if (!empty($response)) { // fetch the result
	if (@$response=@json_decode($response, true)) {
		if (!empty($response['errorCode']) && strpos($endpoint, '/userdefinedfields') == false && strpos($endpoint, '/attachments') == false) { // don't log errors for /userdefinedfields or /attachments requests, since "NotFound" is an okay result for these
			$this->ems_errors[]='EMS: Error code '.$response['errorCode'].(!empty($response['message']) ? ' ('.$response['message'].')' : '');
		};
		if (empty($this->ems_errors)) { // if there were no errors
			return $response; // return the response JSON
		};
	};
};
return false;
}

public function getBookings($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false, $udf=false, $reservation_udfs=false, $booking_udfs=false, $custom_filter=false) { // fetches EMS bookings by specified parameters (Note: group_types NOT SUPPORTED UNDER REST!)
global $_LW;
$this->ems_errors=[]; // reset errors
foreach(['Buildings'=>'buildings', 'Statuses'=>'statuses', 'Groups'=>'groups', 'EventTypes'=>'event_types'] as $key=>$param) { // format other parameters
	if (!empty($$param)) {
		if (!is_array($$param)) {
			$$param=[$$param];
		};
		if (!empty($$param)) {
			foreach($$param as $id) {
				if (!preg_match('~^[0-9]+$~', $id)) {
					$this->ems_errors=['Invalid '.$param.'for bookings.'];
					return false;
				};
			};
		};
	};
};
if (!empty($groups)) { // ensure groups is an array
	if (!is_array($groups)) {
		$groups=[$groups];
	};
	if (!isset($this->groups)) { // convert any group IDs to group titles
		$this->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']);
	};
	$quotes=[ // convert smart quotes
	    "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
	    "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
	    "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
	    "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
	    "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
	    "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
	    "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
	    "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
	    "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
	    "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
	    "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
	    "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
	];
	foreach($groups as $key=>$val) {
		if (preg_match('~^[0-9]+$~', $val) && isset($this->groups[$val])) {
			$groups[$key]=$this->groups[$val]['title'];
			$groups[$key]=strtr($groups[$key], $quotes);
		};
	};
};
$params=[];
$params['pageSize']=2000;
$payload=[];
if (!empty($_LW->REGISTERED_APPS['ems']['custom']['include_cancelled'])) { // if importing cancelled events
	$payload['includeCancelled']=true; 
};
if (!empty($start_date)) {
	$payload['minReserveStartTime']=$start_date;
};
if (!empty($end_date)) {
	$payload['maxReserveStartTime']=$end_date;
};
if (!empty($statuses)) {
	$payload['statusIds']=$statuses;
};
if (!empty($buildings)) {
	$payload['buildingIds']=$buildings;
};
if (!empty($event_types)) {
	$payload['eventTypeIds']=$event_types;
};
if (!empty($groups)) {
	$payload['groupIds']=$groups;
};
if (!empty($group_id)) {
	if (!is_array($group_id)) {
		$group_id=[$group_id]; // ensure groupIds is array
	};
	$payload['groupIds']=$group_id;
	foreach($payload['groupIds'] as $key=>$val) {
		$payload['groupIds'][$key]=(int)$val; // ensure pure integers, no strings
	}
};
/*
if (!empty($udf)) {
	$payload['udfSearch']=$udf; // #FIXME: try filtering by UDF
	// Search user-defined fields for an exact match of any of the values. Values should be in the form of key:value, so in a query string, that looks like: ?udf=key:value. For cases where they key or value must itself include a colon (the key-value separator), the key and/or value may be surrounded by double-quotes. For example, when the key name is dessert:pie, this can be searched with ?udf="dessert:pie":blueberry.
};
*/
if (!empty($reservation_udfs) && !empty($_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_mappings'])) { // if there are reservation UDF mappings configured
	if (!is_array($reservation_udfs)) {
		$reservation_udfs=[$reservation_udfs];
	};
	foreach($reservation_udfs as $key=>$val) { // apply mappings from config
		if (!empty($_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_mappings'][$key])) {
			$reservation_udfs[$key]=$_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_mappings'][$key].':'.str_replace('&amp;', '&', $val[0]);
		}
		else { // and unset any unrecognized UDfs
			unset($reservation_udfs[$key]);
		};
	};
	if (!empty($reservation_udfs)) {
		$payload['reservationUDFSearch']=array_values($reservation_udfs); // #FIXME: try filtering by reservation UDF
	};
	// Search reservation level user-defined fields for an exact match of any of the values. Values should be in the form of key:value, so in a query string, that looks like: ?udf=key:value. For cases where they key or value must itself include a colon (the key-value separator), the key and/or value may be surrounded by double-quotes. For example, when the key name is dessert:pie, this can be searched with ?reservationUDF="dessert:pie":blueberry.
	// Source: https://wesleyan.emscloudservice.com/platform/api/v1/static/swagger-ui/
};
if (!empty($booking_udfs) && !empty($_LW->REGISTERED_APPS['ems']['custom']['booking_udf_mappings'])) { // if there are booking UDF mappings configured
	if (!is_array($booking_udfs)) {
		$booking_udfs=[$booking_udfs];
	};
	foreach($booking_udfs as $key=>$val) { // apply mappings from config
		if (!empty($_LW->REGISTERED_APPS['ems']['custom']['booking_udf_mappings'][$key])) {
			$booking_udfs[$key]=$_LW->REGISTERED_APPS['ems']['custom']['booking_udf_mappings'][$key];
		}
		else { // and unset any unrecognized UDfs
			unset($booking_udfs[$key]);
		};
	};
	if (!empty($booking_udfs)) {
		$payload['bookingUDFSearch']=array_values($booking_udfs); // #FIXME: try filtering by booking UDF
	};
};
if (!empty($custom_filter)) {
	$payload=array_merge($payload,$custom_filter); // Add custom filters
};
if ($response=$this->getResponse('/bookings/actions/search', $params, $payload)) { // get the response
	$output=[];
	if (!empty($response['results'])) { // fetch and format results
		$page_count=1;
		$page_max=3;
		if (empty($params['page']) && !empty($response['page']) && !empty($response['pageCount']) && $response['page']<$response['pageCount']) { // get up to $page_max more pages
			while (true) {
				$params['page']=$page_count;
				$more=$this->getResponse('bookings', $params);
				if (!empty($more['results'])) {
					$response['results']=array_merge($response['results'], $more['results']);
				};
				if (empty($more['results']) || $page_count==$page_max || $more['page']==$more['pageCount']) {
					break;
				};
				$page_count++;
			};
		};
		foreach($response['results'] as $booking) {
			if (!empty($booking)) { // sanitize result data
				if ($booking['room']['isAssociatedRoom'] == '1') {
					// skip isAssociatedRoom=1 bookings, they show up twice and the isAssociatedRoom=0 has the more detailed room info
					continue;
				};
				foreach($booking as $key=>$val) {
					switch($key) {
						case 'id':
							$booking['booking_id']=(int)$val;
							break;
						case 'eventName':
							$booking['title']=$_LW->setFormatClean($val);
							break;
						case 'group':
							$booking['group_title']=$_LW->setFormatClean($val['name']);
							$booking['group_id']=(int)$val['id'];
							break;
						case 'room':
							$booking['room']=$_LW->setFormatClean($val['description']);
							$booking['building_id']=$_LW->setFormatClean($val['building']['id']);
							$booking['location']=$_LW->setFormatClean($val['building']['description']);
							$booking['timezone']=@$val['building']['timeZone']['abbreviation']=='ET' ? 'America/New_York' : (@$val['building']['timeZone']['abbreviation']=='PT' ? 'America/Los_Angeles' : (@$val['building']['timeZone']['abbreviation']=='MT' ? 'America/Denver' : (@$val['building']['timeZone']['abbreviation']=='CT' ? 'America/Chicago' : $_LW->getTimezoneForAbbreviation(@$val['building']['timeZone']['abbreviation']))));
							if (empty($booking['timezone'])) {
								$booking['timezone']=!empty($_LW->CONFIG['TIMEZONE']) ? $_LW->CONFIG['TIMEZONE'] : ini_get('date.timezone');
							};
							break;
						case 'eventTypeId':
							$booking['event_type_id']=$val;
							$booking['event_type']=$_LW->setFormatClean($this->getEventTypeById($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $val));
							break;
						case 'eventStartTime':
							$booking['date_ts']=$_LW->toTS($val);
							$booking['date_dt']=$_LW->toDate('Y-m-d H:i:00', $booking['date_ts'], 'UTC');
							break;
						case 'eventEndTime':
							$booking['date2_ts']=$_LW->toTS($val);
							$booking['date2_dt']=$_LW->toDate('Y-m-d H:i:00', $booking['date2_ts'], 'UTC');
							break;
						case 'status':
							$booking['status_id']=(int)$val['id'];
							$booking['status']=$_LW->setFormatClean($val['description']);
							$booking['status_type']=$_LW->setFormatClean($val['statusType']);
							if ($booking['status_type']=='Canceled' || $booking['status_type']=='Cancelled' || $booking['status_type']=='Cancel') {
								$booking['canceled']=1;
							};
							break;
						case 'reservation':
							if (!empty($val['id'])) {
								$booking['reservation_id']=$val['id'];
							};
							if (!empty($val['contactName']) && $val['contactName']!=='(none)') { // use contactName if present and get email from Reservation
								$booking['contact_name']=$val['contactName'];
								if ($reservation=$this->getReservationByID($val['id'])) { // fetch email address from reservation, but only fetch once a day per unique webUserId + contactName combo (webUserId factored in, in case there are non-unique contact names)
									if (!empty($reservation['contact']['emailAddress']) && !empty($reservation['contact']['name'])) {
										$booking['contact_info']=$reservation['contact']['name'].' (<a href="mailto:'.$_LW->setFormatClean($reservation['contact']['emailAddress']).'">'.$_LW->setFormatClean($reservation['contact']['emailAddress']).'</a>)';
									};
								};
							}
							else if (!empty($val['groupName'])) { // fallback to using groupName and email
								$booking['contact_name']=$val['groupName'];
								$booking['contact_info']=$val['groupName'].' (<a href="mailto:'.$_LW->setFormatClean($booking['group']['emailAddress']).'">'.$_LW->setFormatClean($booking['group']['emailAddress']).'</a>)';
							};
							break;
					};
				};
			};
			if (!empty($booking['title']) 
					&& !empty($booking['group_title']) 
					&& (empty($group_id) || in_array($booking['group_id'],$group_id)) 
					&& (empty($groups) || (is_array($groups) && in_array($booking['group_title'], $groups)) || (!empty($_LW->REGISTERED_APPS['ems']['custom']['groups_map']) 
					&& is_array($_LW->REGISTERED_APPS['ems']['custom']['groups_map']) 
					&& in_array($booking['group_title'], $_LW->REGISTERED_APPS['ems']['custom']['groups_map']))) 
					&& (empty($buildings) || in_array($booking['building_id'], $buildings)) 
					&& (empty($statuses) || in_array($booking['status_id'], $statuses)) 
					&& (empty($event_types) || in_array($booking['event_type_id'], $event_types))
				) { // if each result is valid
				if (!empty($booking['location']) && !empty($booking['room'])) { // merge room into location
					$booking['location'].=', '.$booking['room'];
				};
				if (!empty($booking['room'])) {
					unset($booking['room']);
				};
				if (!empty($_LW->REGISTERED_APPS['ems']['custom']['enable_udfs']) && !empty($booking['id'])) { // add booking UDFs if enabled
					$booking['udfs']=$this->getUDFs($username, $password, $booking['id'], 'bookings');
				};
				if (!empty($_LW->REGISTERED_APPS['ems']['custom']['enable_reservation_udfs']) && !empty($booking['reservation']['id'])) { // add reservation UDFs if enabled
					$booking['reservation_udfs']=$this->getUDFs($username, $password, $booking['reservation']['id'], 'reservations');
				};
				if (!empty($_LW->REGISTERED_APPS['ems']['custom']['image_attachment_type']) && !empty($booking['reservation']['id']) && preg_match('~^[0-9]+$~', $_LW->REGISTERED_APPS['ems']['custom']['image_attachment_type'])) { // add first attachment of the configured type
					if ($attachments=$this->getAttachments($username, $password, $booking['reservation']['id'], 'reservations')) {
						$booking['attachment']=$attachments[0];
					};
				};
				foreach($booking as $key=>$val) { // sanitize result data
					if (!is_array($val)) {
						$booking[$key]=$_LW->setFormatSanitize($val);
					};
				};
				$output[]=$booking; // add it to the results to return
			};
		};
	};
	$hash=hash('md5', serialize([@$groups, @$buildings, @$statuses, @$event_types, @$group_types, @$group_id, @$udf, @$reservation_udfs, @$booking_udfs, @$custom_filter])); // get hash for feed
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems')) { // ensure EMS directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems');
	};
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache')) { // ensure feed_cache directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache');
	};
	@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash, serialize($output), LOCK_EX); // cache the results
	return $output; // return all results
};
$hash=hash('md5', serialize([@$groups, @$buildings, @$statuses, @$event_types, @$group_types, @$group_id])); // get hash for feed
if ($tmp=@file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash)) { // fall back on most recent cache if available (in case of EMS API failure)
	if ($tmp=@unserialize($tmp)) {
		$output=$tmp;
	};
	$_LW->logError('Failed to retrieve results from EMS ('.$_SERVER['REQUEST_URI'].'). Will fall back on: '.$_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash.' ('.(!empty($tmp) ? sizeof($tmp) : 0).')'); // record failure
	return $output;
};
return false;
}

public function getStatuses($username, $password) { // fetches EMS statuses
global $_LW;
if (isset($this->statuses)) { // return cached response if possible
	return true;
};
$this->statuses=$_LW->getVariable('ems_statuses'); // fetch statuses from cache
if (empty($this->statuses)) { // if cached statuses not available
	$this->statuses=[];
	$params=[];
	$params['includeNonWeb']=true;
	if ($response=$this->getResponse('/statuses',$params)) { // get the response
		if (!empty($response['results'])) { // fetch and format results
			foreach($response['results'] as $status) {
				if (!empty($status)) { // sanitize result data
					foreach($status as $key=>$val) {
						if (!is_array($val)) {
							$status[$key]=$_LW->setFormatSanitize($val);
						};
					};
				};
				if (!empty($status['description']) && !empty($status['id'])) { // if each result is valid
					$status['status_type_id']=$status['statusType'];
					$status['display_on_web']=$status['displayOnWeb']=='true' ? true : false;
					$this->statuses[$status['id']]=$status; // add it to the results to return
				};
			};
		};
	};
	$_LW->setVariable('ems_statuses', $this->statuses, 3600); // cache the statuses
};
}

public function getEventTypeByID($username, $password, $id) { // gets an EMS event type by ID
global $_LW;
if (!isset($this->event_types)) { // if there are no statuses yet
	$this->getEventTypes($username, $password); // get EMS statuses
};
if (isset($this->event_types[$id]['title'])) { // return cached response
	return $this->event_types[$id]['title'];
};
return false; // else return false
}

public function getGroups($username, $password) { // fetches EMS groups
global $_LW;
if (isset($this->groups)) { // return cached response if possible
	return true;
};
$this->groups=$_LW->getVariable('ems_groups'); // fetch groups from cache
if (empty($this->groups)) { // if cached groups not available
	$this->groups=[];
	$params=[];
	$params['pageSize']=2000;
	if ($response=$this->getResponse('/groups', $params)) { // get the response
		if (!empty($response['results'])) { // fetch and format results
			if (!empty($_LW->REGISTERED_APPS['ems']['custom']['default_group_types'])) { // if filtering by group type, get up to 20 pages of results
				$page_count=1;
				$page_max=20;
				if (empty($params['page']) && !empty($response['page']) && !empty($response['pageCount']) && $response['page']<$response['pageCount']) { // get up to $page_max more pages
					while (true) {
						$params['page']=$page_count;
						$more=$this->getResponse('/groups', $params);
						if (!empty($more['results'])) {
							$response['results']=array_merge($response['results'], $more['results']);
						};
						if (empty($more['results']) || $page_count==$page_max || $more['page']==$more['pageCount']) {
							break;
						};
						$page_count++;
					};
				};
			};
			foreach($response['results'] as $key=>$group) {
				if (!empty($_LW->REGISTERED_APPS['ems']['custom']['default_group_types']) && !empty($group['groupType']) && is_array($group['groupType'])) { // if filtering by group types
					if (!is_array($_LW->REGISTERED_APPS['ems']['custom']['default_group_types'])) {
						$_LW->REGISTERED_APPS['ems']['custom']['default_group_types']=[$_LW->REGISTERED_APPS['ems']['custom']['default_group_types']];
					};
					if (empty($group['groupType']['id']) || !in_array($group['groupType']['id'], $_LW->REGISTERED_APPS['ems']['custom']['default_group_types'])) { // filter out groups with invalid types
						unset($response['results'][$key]);
						continue;
					};
				};
				if (!empty($group)) { // sanitize result data
					foreach($group as $key=>$val) {
						if (!is_array($val)) {
							$group[$key]=$_LW->setFormatSanitize($val);
						};
						if ($key=='name') {
							$group[$key]=str_replace('’', '\'', $group[$key]);
						};
					};
				};
				if (!empty($group['name']) && !empty($group['id']) && !empty($group['active'])) { // if each result is valid
					$group['title']=$group['name'];
					unset($group['name']);
					$this->groups[$group['id']]=$group; // add it to the results to return
				};
			};
		};
	};
	uasort($this->groups, [$this, 'sortGroups']); // sort the groups
	$_LW->setVariable('ems_groups', $this->groups, 3600); // cache the groups
};
}

public function sortGroups($a, $b) { // sorts groups
global $_LW;
$a=$a['title'];
$b=$b['title'];
return ($a==$b) ? 0 : ($a<$b ? -1 : 1);
}

public function getGroupTypes($username, $password) { // fetches EMS group types
global $_LW;
if (isset($this->group_types)) { // return cached response if possible
	return true;
};
$this->group_types=$_LW->getVariable('ems_group_types'); // fetch group types from cache
if (empty($this->group_types)) { // if cached group types not available
	if ($response=$this->getResponse('/grouptypes')) { // get the response
		if (!empty($response['results'])) { // fetch and format results
			foreach($response['results'] as $group_type) {
				if (!empty($group_type)) { // sanitize result data
					foreach($group_type as $key=>$val) {
						if (!is_array($val)) {
							$group_type[$key]=$_LW->setFormatSanitize($val);
						};
					};
				};
				if (!empty($group_type['description']) && !empty($group_type['id'])) { // if each result is valid
					$group_type['title']=$group_type['description'];
					unset($group_type['description']);
					$this->group_types[$group_type['id']]=$group_type; // add it to the results to return
				};
			};
		};
	};
	$_LW->setVariable('ems_group_types', $this->group_types, 3600); // cache the group_types
};
}

public function getEventTypes($username, $password) { // fetches EMS event types
global $_LW;
if (isset($this->event_types)) { // return cached response if possible
	return true;
};
$this->event_types=$_LW->getVariable('ems_event_types'); // fetch event types from cache
if (empty($this->event_types)) { // if cached event types not available
	$this->event_types=[];
	$params=[];
	$params['pageSize']=2000;
	if ($response=$this->getResponse('/eventtypes', $params)) { // get the response
		if (!empty($response['results'])) { // fetch and format results
			foreach($response['results'] as $event_type) {
				if (!empty($event_type)) { // sanitize result data
					foreach($event_type as $key=>$val) {
						if (!is_array($val)) {
							$event_type[$key]=$_LW->setFormatSanitize($val);
						};
					};
				};
				if (!empty($event_type['description']) && !empty($event_type['id'])) { // if each result is valid
					$event_type['title']=$event_type['description'];
					unset($event_type['description']);
					$this->event_types[$event_type['id']]=$event_type; // add it to the results to return
				};
			};
		};
	};
	$_LW->setVariable('ems_event_types', $this->event_types, 3600); // cache the event_types
};
}

public function getReservationById($id) { // fetches a reservation's info
global $_LW;
static $map;
if (!isset($map)) {
	$map=[];
};
if (isset($map[$id])) { // return cached response if possible
	return $map[$id];
};
$cache_key=hash('md5', $id); // hash the cache key
$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$cache_key[0].$cache_key[1].'/'.$cache_key; // get the cache path
if (@filemtime($cache_path)>$_SERVER['REQUEST_TIME']-86400) { // return cached response if possible
	if ($reservation=@file_get_contents($cache_path)) {
		if ($reservation=@unserialize($reservation)) {
			$map[$id]=$reservation;
			return $reservation;
		};
	};
};
$reservation=(@filemtime($cache_path)>$_SERVER['REQUEST_TIME']-86400 ? @unserialize(file_get_contents($cache_path)) : []); // fetch reservation from cache
if (empty($reservation)) { // if cached reservation not available
	$reservation=[];
	if ($response=$this->getResponse('/reservations/'.$id)) { // get the response
		if (!empty($response['id'])) { // fetch result
			$reservation=$response;
			if (!empty($_LW->REGISTERED_APPS['ems']['custom']['image_attachment_type']) && !empty($reservation['id']) && preg_match('~^[0-9]+$~', $_LW->REGISTERED_APPS['ems']['custom']['image_attachment_type'])) { // add first attachment of the configured type
				$username=$_LW->REGISTERED_APPS['ems']['custom']['username'];
				$password=$_LW->REGISTERED_APPS['ems']['custom']['password'];
				if ($attachments=$this->getAttachments($username, $password, $reservation['id'], 'reservations')) {
					$reservation['attachment']=$attachments[0];
				};
			};
		};
	};
	foreach([$_LW->INCLUDES_DIR_PATH.'/data/ems', $_LW->INCLUDES_DIR_PATH.'/data/ems/reservations', $_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$cache_key[0].$cache_key[1]] as $dir) {
		if (!is_dir($dir)) {
			@mkdir($dir);
		};
	};
	if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$cache_key[0].$cache_key[1])) {
		@file_put_contents($cache_path, serialize($reservation), LOCK_EX); // file cache the reservation
	};
	$map[$id]=$reservation; // static cache the reservation
};
return $reservation;
}

public function getUDFs($username, $password, $parent_id, $parent_type) { // fetches EMS UDFs
global $_LW;
$output=[];
$params=['pageSize'=>2000];
if (!in_array($parent_type,['bookings', 'reservations'])) {
	return false; // only permit for supported types
};
if ($response=$this->getResponse('/'.$parent_type.'/'.(int)$parent_id.'/userdefinedfields', $params)) { // get the response
	if (!empty($response['results'])) { // fetch and format results
		foreach($response['results'] as $udf) {
			if (!empty($udf)) { // sanitize result data
				if (!empty($udf['definition']['description'])) {
					if ($udf['definition']['fieldType']=='Text' || $udf['definition']['fieldType']=='Date') {
						if ((!empty($_LW->REGISTERED_APPS['ems']['custom']['udf_categories']) && $udf['definition']['description'] == $_LW->REGISTERED_APPS['ems']['custom']['udf_categories']) || (!empty($_LW->REGISTERED_APPS['ems']['custom']['udf_tags']) &&  $udf['definition']['description'] == $_LW->REGISTERED_APPS['ems']['custom']['udf_tags'])) { // save categories/tags as array
							$vals=explode("\n", $udf['value']);
							foreach($vals as $val) {
								$output[$udf['definition']['description']][]=$_LW->setFormatClean($val);
							};
						}
						else { // save description and all others as HTML
							$output[$udf['definition']['description']]=nl2br($udf['value']);
						};
					}
					else if ($udf['definition']['fieldType']=='List') { // translate option from picklist
						foreach ($udf['definition']['options'] as $udf_option) {
							if ($udf_option['id']==$udf['value']) {
								$output[$udf['definition']['description']]=$udf_option['description']; // take first matching value
							};
						};
					};
				};
			};
		};
	};
};
return $output;
}

public function getAttachments($username, $password, $parent_id, $parent_type) { // fetches EMS attachments
global $_LW;
$output=[];
$params=['pageSize'=>100];
if (!in_array($parent_type,['bookings', 'reservations'])) {
	return false; // only permit for supported types
};
if ($response=$this->getResponse('/'.$parent_type.'/'.(int)$parent_id.'/attachments', $params)) { // get the response
	if (!empty($response['results'])) { // fetch and format results
		foreach($response['results'] as $attachment) {
			if (!empty($attachment['id']) && !empty($attachment['fileName']) && !empty($attachment['attachment'])) {
				if (empty($attachment['attachmentType']['id']) || $attachment['attachmentType']['id']!=$_LW->REGISTERED_APPS['ems']['custom']['image_attachment_type']) { // require configured attachment type
					continue;
				};
				if (empty($attachment['displayOnWeb'])) { // require displayOnWeb
					continue;
				};
				$attachment=[ // restrict to fields we need
					'id'=>$attachment['id'],
					'filename'=>$attachment['fileName'],
					'data'=>$attachment['attachment']
				];
				array_walk_recursive($attachment, function($item, $key) { // sanitize result data
					global $_LW;
					if (is_scalar($item)) {
						$item=$_LW->setFormatClean($item);
					};
					return $item;
				});
				$output[]=$attachment;
			};
		};
	};
};
return $output;
}

public function validateLogin() { // validates the EMS login
global $_LW;
if ($response=$this->getResponse('/grouptypes')) {
	if (!empty($response['results'])) {
		return true;
	};
};
return false;
}

}

?>