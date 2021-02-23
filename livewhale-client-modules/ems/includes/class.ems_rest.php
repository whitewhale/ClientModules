<?php

class EMSRESTClient { // REST client for EMS
public $ems_errors=array();

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
$response=@shell_exec('curl -m 15'.(!empty($payload) ? ' --request POST --data '.escapeshellarg(@json_encode($payload)).' -H "Content-Type: application/json"' : '').' -H '.escapeshellarg('x-ems-api-token: '.$this->token).' '.escapeshellarg($_LW->REGISTERED_APPS['ems']['custom']['rest'].$endpoint.(!empty($params) ? '?'.http_build_query($params) : ''))); // request response
if (!empty($response)) { // fetch the result
	if (@$response=@json_decode($response, true)) {
		if (!empty($response['errorCode']) && strpos($endpoint,'userdefinedfields') == false) { // don't log errors for /userdefinedfields requests, since "NotFound" is an okay result for UDFs
			$this->ems_errors[]='EMS: Error code '.$response['errorCode'].(!empty($response['message']) ? ' ('.$response['message'].')' : '');
		};
		if (empty($this->ems_errors)) { // if there were no errors
			return $response; // return the response JSON
		};
	};
};
return false;
}

public function getBookings($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false) { // fetches EMS bookings by specified parameters (Note: group_types NOT SUPPORTED UNDER REST!)
global $_LW;
$this->ems_errors=array(); // reset errors
foreach(array('Buildings'=>'buildings', 'Statuses'=>'statuses', 'Groups'=>'groups', 'EventTypes'=>'event_types') as $key=>$param) { // format other parameters
	if (!empty($$param)) {
		if (!is_array($$param)) {
			$$param=array($$param);
		};
		if (!empty($$param)) {
			foreach($$param as $id) {
				if (!preg_match('~^[0-9]+$~', $id)) {
					$this->ems_errors=array('Invalid '.$param.'for bookings.');
					return false;
				};
			};
		};
	};
};
if (!empty($groups)) { // ensure groups is an array
	if (!is_array($groups)) {
		$groups=array($groups);
	};
	if (!isset($this->groups)) { // convert any group IDs to group titles
		$this->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']);
	};
	foreach($groups as $key=>$val) {
		if (preg_match('~^[0-9]+$~', $val) && isset($this->groups[$val])) {
			$groups[$key]=$this->groups[$val]['title'];
		};
	};
};
$params=array();
$params['pageSize']=2000;
$payload=array();
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
	$payload['groupIds']=array((int)$group_id);
};
if ($response=$this->getResponse('/bookings/actions/search', $params, $payload)) { // get the response
	$output=array();
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
			}
		};
		foreach($response['results'] as $booking) {
			if (!empty($booking)) { // sanitize result data
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
							if ($booking['status_type']=='Canceled' || $booking['status_type']=='Cancelled') {
								$booking['canceled']=1;
							};
							break;
						case 'reservation':
							if (!empty($val['id'])) {
								$booking['reservation_id']=$val['id'];
							};
							if (!empty($val['id']) && !empty($val['webUserId']) && !empty($val['contactName'])) {
								if ($reservation=$this->getReservationByID($val['id'], $val['webUserId'].'-'.$val['contactName'])) { // fetch email address from reservation, but only fetch once a day per unique webUserId + contactName combo (webUserId factored in, in case there are non-unique contact names)
									if (!empty($reservation['contact']['emailAddress']) && !empty($reservation['contact']['name'])) {
										$booking['contact_info']=$reservation['contact']['name'].' (<a href="mailto:'.$_LW->setFormatClean($reservation['contact']['emailAddress']).'">'.$_LW->setFormatClean($reservation['contact']['emailAddress']).'</a>)';
									};
								};
							};
							break;
					};
				};
			};
			if (!empty($booking['title']) && !empty($booking['group_title']) && (empty($group_id) || $booking['group_id']==$group_id) && (empty($groups) || in_array($booking['group_title'], $groups) || in_array($booking['group_title'],$_LW->REGISTERED_APPS['ems']['custom']['groups_map'])) && (empty($buildings) || in_array($booking['building_id'], $buildings)) && (empty($statuses) || in_array($booking['status_id'], $statuses)) && (empty($event_types) || in_array($booking['event_type_id'], $event_types))) { // if each result is valid
				if (!empty($booking['location']) && !empty($booking['room'])) { // merge room into location
					$booking['location'].=', '.$booking['room'];
				};
				if (!empty($booking['room'])) {
					unset($booking['room']);
				};
				if (!empty($_LW->REGISTERED_APPS['ems']['custom']['enable_udfs']) && !empty($booking['id'])) {
					$booking['udfs']=$this->getUDFs($username, $password, $booking['id'], -42);
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
	$hash=hash('md5', serialize(array(@$groups, @$buildings, @$statuses, @$event_types, @$group_types, @$group_id))); // get hash for feed
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems')) { // ensure EMS directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems');
	};
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache')) { // ensure feed_cache directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache');
	};
	@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash, serialize($output), LOCK_EX); // cache the results
	return $output; // return all results
};
$hash=hash('md5', serialize(array(@$groups, @$buildings, @$statuses, @$event_types, @$group_types, @$group_id))); // get hash for feed
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
	$this->statuses=array();
	if ($response=$this->getResponse('/statuses')) { // get the response
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
	$this->groups=array();
	$params=array();
	$params['pageSize']=2000;
	if ($response=$this->getResponse('/groups', $params)) { // get the response
		if (!empty($response['results'])) { // fetch and format results
			foreach($response['results'] as $group) {
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
	uasort($this->groups, array($this, 'sortGroups')); // sort the groups
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
	$this->event_types=array();
	$params=array();
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

public function getReservationById($id, $cache_key) { // fetches a reservation's info
global $_LW;
static $map;
if (!isset($map)) {
	$map=array();
};
if (isset($map[$id])) { // return cached response if possible
	return $map[$id];
};
$cache_key=hash('md5', $cache_key); // hash the cache key
$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$cache_key[0].$cache_key[1].'/'.$cache_key; // get the cache path
if (@filemtime($cache_path)>$_SERVER['REQUEST_TIME']-86400) { // return cached response if possible
	if ($reservation=@file_get_contents($cache_path)) {
		if ($reservation=@unserialize($reservation)) {
			$map[$id]=$reservation;
			return $reservation;
		};
	};
};
$reservation=@filemtime($cache_path)>$_SERVER['REQUEST_TIME']-86400 ? @unserialize(file_get_contents($cache_path)) : array(); // fetch reservation from cache
if (empty($reservation)) { // if cached reservation not available
	$reservation=array();
	if ($response=$this->getResponse('/reservations/'.$id)) { // get the response
		if (!empty($response['id'])) { // fetch result
			$reservation=$response;
		};
	};
	foreach(array($_LW->INCLUDES_DIR_PATH.'/data/ems', $_LW->INCLUDES_DIR_PATH.'/data/ems/reservations', $_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$cache_key[0].$cache_key[1]) as $dir) {
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

public function getUDFs($username, $password, $parent_id, $parent_type) { // fetches EMS UDFs for a booking
global $_LW;
$output=array();
$params=array('pageSize'=>2000);
if ($response=$this->getResponse('/bookings/'.(int)$parent_id.'/userdefinedfields', $params)) { // get the response
	if (!empty($response['results'])) { // fetch and format results
		foreach($response['results'] as $udf) {
			if (!empty($udf)) { // sanitize result data
				if (!empty($udf['definition']['description'])) {
					if ((!empty($_LW->REGISTERED_APPS['ems']['custom']['udf_categories']) && $udf['definition']['description'] == $_LW->REGISTERED_APPS['ems']['custom']['udf_categories']) || (!empty($_LW->REGISTERED_APPS['ems']['custom']['udf_tags']) &&  $udf['definition']['description'] == $_LW->REGISTERED_APPS['ems']['custom']['udf_tags'])) { // save categories/tags as array
						$vals=explode("\n", $udf['value']);
						foreach($vals as $val) {
							$output[$udf['definition']['description']][]=$_LW->setFormatClean($val);
						};
					}
					else { // save description and all others as HTML
						$output[$udf['definition']['description']]=nl2br($udf['value']);
					};
				};
			};
		};
	};
};
return $output;
}

}

?>
