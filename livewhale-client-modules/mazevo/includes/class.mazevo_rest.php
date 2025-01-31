<?php

class MazevoRESTClient { // REST client for Mazevo
public $mazevo_errors=[];

function __construct($rest) { // creates a new REST server client
global $_LW;
if (substr($rest, -1, 1)=='/') {
	$rest=substr($rest, 0, -1);
};
$this->api_url=$rest; // set the API url
if (empty($_LW->CONFIG['MAZEVO']['API_KEY'])) { // bail on missing API key
	return false;
};
}

public function getResponse($endpoint, $params=false, $payload=false) { // gets the response from Mazevo
global $_LW;
ini_set('memory_limit', '1G');
$command='curl -m 60 --location'.(!empty($payload) ? ' --request POST --data '.escapeshellarg(@json_encode($payload)).' -H "Content-Type: application/json"' : '').' -H '.escapeshellarg('X-API-Key: '.$_LW->CONFIG['CREDENTIALS']['MAZEVO']['API_KEY']).' '.escapeshellarg($_LW->REGISTERED_APPS['mazevo']['custom']['rest'].$endpoint.(!empty($params) ? '?'.http_build_query($params) : '')); // set API command
if ($endpoint=='/PublicEvent/geteventswithquestions') { // if this is a bookings request
	$hash=[$params, $payload];
	if (isset($hash[0]['group'])) { // strip group param from cache ID because all groups share the same cache
		unset($hash[0]['group']);
	};
	$hash=hash('md5', serialize($hash));
	$response=false;
	if (@filemtime($_LW->INCLUDES_DIR_PATH.'/data/mazevo/endpoint_cache/bookings_'.$hash.'.json')>$_SERVER['REQUEST_TIME']-3600) { // if cache is valid
		$response=@file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/mazevo/endpoint_cache/bookings_'.$hash.'.json'); // get cached response
	}
	else { // else if cache has expired
		$response=@shell_exec($command); // refresh response
		if (!empty($response)) { // if there was a response
			if (@json_decode($response, true)) {
				if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/mazevo/endpoint_cache')) {
					@mkdir($_LW->INCLUDES_DIR_PATH.'/data/mazevo/endpoint_cache');
				};
				@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/mazevo/endpoint_cache/bookings_'.$hash.'.json', $response);
			};
		};
	};
}
else { // else just request response w/o caching
	$response=@shell_exec($command);
};
if (!empty($response)) { // fetch the result
	if (@$response=@json_decode($response, true)) {
		if (empty($this->mazevo_errors)) { // if there were no errors
			return $response; // return the response JSON
		};
	};
};
return false;
}

public function getBookings($start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_id=false) { // fetches Mazevo bookings by specified parameters
global $_LW;
$this->mazevo_errors=[]; // reset errors
foreach(['Buildings'=>'buildings', 'Statuses'=>'statuses', 'Groups'=>'groups', 'EventTypes'=>'event_types'] as $key=>$param) { // format other parameters
	if (!empty($$param)) {
		if (!is_array($$param)) {
			$$param=[$$param];
		};
		if (!empty($$param)) {
			foreach($$param as $id) {
				if (!preg_match('~^[0-9]+$~', $id)) {
					$this->mazevo_errors=['Invalid '.$param.'for bookings.'];
					return false;
				};
			};
		};
	};
};
$params=[];
$payload=[];
if (!empty($start_date)) {
	$payload['start']=$start_date;
};
if (!empty($end_date)) {
	$payload['end']=$end_date;
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
if ($response=$this->getResponse('/PublicEvent/geteventswithquestions', $params, $payload)) { // get the response
	$output=[];
	if (!empty($response) && is_array($response)) { // fetch and format results
		foreach($response as $collection) {
			if (!empty($collection['bookings']) && is_array($collection['bookings'])) {
				$question_categories='';
				if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['question_categories']) && preg_match('~^[0-9]+$~', $_LW->REGISTERED_APPS['mazevo']['custom']['question_categories'])) { // if enabling question for categories
					if (!empty($collection['eventQuestions']) && is_array($collection['eventQuestions'])) { // get the value of the associated question for this collection
						foreach($collection['eventQuestions'] as $event_question) {
							if ($event_question['eventQuestionId']==$_LW->REGISTERED_APPS['mazevo']['custom']['question_categories']) {
								$question_categories=$event_question['answer'];
								$question_categories=implode('|', array_filter(preg_split('~\s*?\([A-Z0-9]+?\)(?:,\s*|$)~', $question_categories)));
								break;
							};
						};
					};
					
				};
				$question_description='';
				if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['question_description']) && preg_match('~^[0-9]+$~', $_LW->REGISTERED_APPS['mazevo']['custom']['question_description'])) { // if enabling question for description
					if (!empty($collection['eventQuestions']) && is_array($collection['eventQuestions'])) { // get the value of the associated question for this collection
						foreach($collection['eventQuestions'] as $event_question) {
							if ($event_question['eventQuestionId']==$_LW->REGISTERED_APPS['mazevo']['custom']['question_description']) {
								$question_description=$event_question['answer'];
								$question_description=preg_replace('~<br\s?/>\s*<br\s?/>~i', "\n</p>\n<p>", nl2br($question_description, true));
								break;
							};
						};
					};
				};
				foreach($collection['bookings'] as $booking) {
					if (!empty($booking)) { // sanitize result data
						foreach(['collectionId', 'userId', 'setupMinutes', 'teardownMinutes', 'customerAccessMinutes', 'statusColor', 'setupStyle', 'setupCount', 'setupNotes', 'dateChanged', 'componentRoomIds', 'hasDiagram', 'externalData'] as $key) { // clear unused data
							unset($booking[$key]);
						};
						foreach($booking as $key=>$val) {
							switch($key) {
								case 'bookingId':
									$booking['booking_id']=(int)$val;
									break;
								case 'eventName':
									$booking['title']=$_LW->setFormatClean($val);
									break;
								case 'organizationId':
									$booking['group_id']=(int)$val;
									break;
								case 'organizationName':
									$booking['group_title']=$_LW->setFormatClean($val);
									break;
								case 'roomId':
									$booking['room']=$_LW->setFormatClean($booking['roomDescription']);
									$booking['building_id']=$_LW->setFormatClean($booking['buildingId']);
									$booking['location']=$_LW->setFormatClean($booking['buildingDescription']);
									$booking['timezone']=$_LW->getSupportedTimezone($booking['timeZone']);
									if (empty($booking['timezone'])) {
										$booking['timezone']=!empty($_LW->CONFIG['TIMEZONE']) ? $_LW->CONFIG['TIMEZONE'] : ini_get('date.timezone');
									};
									break;
								case 'eventTypeId':
									$booking['event_type_id']=$val;
									$booking['event_type']=$_LW->setFormatClean($this->getEventTypeById($val));
									break;
								case 'dateTimeStart':
									$booking['date_ts']=$_LW->toTS($val);
									$booking['date_dt']=$_LW->toDate('Y-m-d H:i:00', $booking['date_ts'], 'UTC');
									break;
								case 'dateTimeEnd':
									$booking['date2_ts']=$_LW->toTS($val);
									$booking['date2_dt']=$_LW->toDate('Y-m-d H:i:00', $booking['date2_ts'], 'UTC');
									break;
								case 'statusId':
									$booking['status_id']=(int)$val;
									$booking['status']=(int)$booking['statusId'];
									$booking['status_type']=$_LW->setFormatClean($booking['statusDescription']);
									if (in_array($booking['status_id'], [45, 50])) {
										$booking['canceled']=1;
									};
									break;
							};
						};
					};
					if (!empty($booking['title'])
						&& !empty($booking['group_title'])
						&& (empty($groups) || (is_array($groups) && in_array($booking['group_id'], $groups))) 
						&& (empty($group_id) || $booking['group_id']==$group_id) 
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
						if (!empty($question_description)) { // and apply to all bookings in the collection
							foreach($output as $key=>$val) {
								$booking['description']=$question_description;
							};
						};
						if (!empty($question_categories)) { // and apply to all bookings in the collection
							foreach($output as $key=>$val) {
								$booking['categories']=$question_categories;
							};
						};
						foreach($booking as $key=>$val) { // sanitize result data
							if (!is_array($val)) {
								$booking[$key]=$_LW->setFormatSanitize($val);
							};
						};
						$output[$booking['bookingId']]=$booking; // add it to the results to return
					};
				};
				
			};
		};
	};
	$hash=hash('md5', serialize([@$groups, @$buildings, @$statuses, @$event_types])); // get hash for feed
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/mazevo')) { // ensure Mazevo directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/mazevo');
	};
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/mazevo/feed_cache')) { // ensure feed_cache directory exists
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/mazevo/feed_cache');
	};
	$output=array_values($output);
	@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/mazevo/feed_cache/'.$hash, serialize($output), LOCK_EX); // cache the results
	return $output; // return all results
};
$hash=hash('md5', serialize([@$groups, @$buildings, @$statuses, @$event_types])); // get hash for feed
if ($tmp=@file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/mazevo/feed_cache/'.$hash)) { // fall back on most recent cache if available (in case of Mazevo API failure)
	if ($tmp=@unserialize($tmp)) {
		$output=$tmp;
	};
	$_LW->logError('Failed to retrieve results from Mazevo ('.$_SERVER['REQUEST_URI'].'). Will fall back on: '.$_LW->INCLUDES_DIR_PATH.'/data/mazevo/feed_cache/'.$hash.' ('.(!empty($tmp) ? sizeof($tmp) : 0).')'); // record failure
	return $output;
};
return false;
}

public function getStatuses() { // fetches Mazevo statuses
global $_LW;
if (isset($this->statuses)) { // return cached response if possible
	return true;
};
$this->statuses=$_LW->getVariable('mazevo_statuses'); // fetch statuses from cache
if (empty($this->statuses)) { // if cached statuses not available
	$this->statuses=[];
	if ($response=$this->getResponse('/PublicConfiguration/Statuses')) { // get the response
		if (!empty($response)) { // fetch and format results
			foreach($response as $status) {
				if (!empty($status)) { // sanitize result data
					foreach($status as $key=>$val) {
						if (!is_array($val)) {
							$status[$key]=$_LW->setFormatSanitize($val);
						};
					};
				};
				if (!empty($status['description']) && !empty($status['statusId'])) { // if each result is valid
					$status['status_type_id']=$status['statusType'];
					$this->statuses[$status['statusId']]=$status; // add it to the results to return
				};
			};
		};
	};
	$_LW->setVariable('mazevo_statuses', $this->statuses, 3600); // cache the statuses
};
}

public function getEventTypeByID($id) { // gets an Mazevo event type by ID
global $_LW;
if (!isset($this->event_types)) { // if there are no event types yet
	$this->getEventTypes(); // get Mazevo event types
};
if (isset($this->event_types[$id]['title'])) { // return cached response
	return $this->event_types[$id]['title'];
};
return false; // else return false
}

public function getGroups() { // fetches Mazevo groups
global $_LW;
if (isset($this->groups)) { // return cached response if possible
	return true;
};
$this->groups=$_LW->getVariable('mazevo_groups'); // fetch groups from cache
if (empty($this->groups)) { // if cached groups not available
	$this->groups=[];
	$params=[];
	$payload=[];
	$payload['organizationId']=0;
	if ($response=$this->getResponse('/PublicOrganization/Organizations', $params, $payload)) { // get the response
		if (!empty($response)) { // fetch and format results
			foreach($response as $group) {
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
				if (!empty($group['name']) && !empty($group['organizationId']) && !empty($group['active'])) { // if each result is valid
					$group['title']=$group['name'];
					unset($group['name']);
					$this->groups[$group['organizationId']]=$group; // add it to the results to return
				};
			};
		};
	};
	uasort($this->groups, [$this, 'sortGroups']); // sort the groups
	$_LW->setVariable('mazevo_groups', $this->groups, 3600); // cache the groups
};
}

public function sortGroups($a, $b) { // sorts groups
global $_LW;
$a=$a['title'];
$b=$b['title'];
return ($a==$b) ? 0 : ($a<$b ? -1 : 1);
}

public function getEventTypes() { // fetches Mazevo event types
global $_LW;
if (isset($this->event_types)) { // return cached response if possible
	return true;
};
$this->event_types=$_LW->getVariable('mazevo_event_types'); // fetch event types from cache
if (empty($this->event_types)) { // if cached event types not available
	$this->event_types=[];
	$params=[];
	if ($response=$this->getResponse('/PublicConfiguration/EventTypes', $params)) { // get the response
		if (!empty($response)) { // fetch and format results
			foreach($response as $event_type) {
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
	$_LW->setVariable('mazevo_event_types', $this->event_types, 3600); // cache the event_types
};
}

public function validateLogin() { // validates the Mazevo login
global $_LW;
if ($response=$this->getResponse('/PublicConfiguration/Statuses')) {
	if (!empty($response)) {
		return true;
	};
};
return false;
}

}

?>