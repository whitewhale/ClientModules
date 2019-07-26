<?php

class EMSSoapClient extends SoapClient { // SOAP client for EMS
public $ems_errors=array();

function __construct($wsdl, $options) { // creates a new SOAP server client
global $_LW;
parent::__construct($wsdl, $options);
$this->server=new SoapServer($wsdl, $options); // create the client
}

public function getResponse($call, $response) { // formats the response from EMS
global $_LW;
$response=(array)$response; // get the response as array
if (!empty($response[$call.'Result'])) { // fetch the result
	$xml=$_LW->getNew('xpresent'); // create the XML response object
	if ($xml->loadXML($response[$call.'Result'])) { // if the result parses
		if ($errors=$xml->query('//Error/Message')) { // if there were errors
			foreach($errors as $error) { // fetch them
				$this->ems_errors[]=$_LW->getInnerXML($xml->saveXML($error));
			};
		};
		if (empty($this->ems_errors)) { // if there were no errors
			return $xml; // return the response XML
		};
	};
};
return false;
}

public function getBookings($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false) { // fetches EMS bookings by specified parameters
global $_LW;
$this->ems_errors=array(); // reset errors
$opts=array( // set default parameters
	'UserName'=>$username,
	'Password'=>$password,
	'StartDate'=>$start_date,
	'EndDate'=>$end_date,
	'ViewComboRoomComponents'=>false
);
foreach(array('Buildings'=>'buildings', 'Statuses'=>'statuses', 'EventTypes'=>'event_types', 'GroupTypes'=>'group_types') as $key=>$param) { // format other parameters
	if (!empty($$param)) {
		if (!is_array($$param)) {
			$$param=array($$param);
		};
		if (!empty($$param)) {
			foreach($$param as $id) {
				if (!preg_match('~^[0-9]+$~', $id)) {
					$this->ems_errors=array('Invalid '.$param.'for getBookings().');
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
if (!empty($group_id)) { // if fetching events by group ID
	$call='GetGroupBookings';
	foreach(array('Statuses'=>'statuses') as $key=>$param) { // format other parameters
		if (!empty($$param)) {
			$opts[$key]=$$param;
		};
	};
	$opts['GroupID']=$group_id;
}
else { // else if fetching across multiple groups
	$call='GetBookings';
	foreach(array('Buildings'=>'buildings', 'Statuses'=>'statuses', 'EventTypes'=>'event_types', 'GroupTypes'=>'group_types') as $key=>$param) { // format other parameters
		if (!empty($$param)) {
			$opts[$key]=$$param;
		};
	};
};
try {
	$res=@$this->__soapCall($call, array('message'=>$opts)); // perform SOAP call
}
catch (Exception $e) {
	$_LW->logError('EMS: '.$e->getMessage());
}
if (!empty($res)) { // if there was a valid response
	if ($res2=$this->getResponse($call, $res)) { // if the response parses
		if ($bookings=$res2->query('/Bookings/Data')) { // fetch and format results
			$output=array();
			foreach($bookings as $booking) {
				if ($booking->hasChildNodes()) {
					$item=array();
					foreach($booking->childNodes as $node) {
						switch($node->nodeName) {
							case 'BookingID':
								$item['booking_id']=(int)$node->nodeValue;
								break;
							case 'EventName':
								$item['title']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'GroupName':
								$item['group_title']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'GroupID':
								$item['group_id']=(int)$node->nodeValue;
								break;
							case 'GroupTypeID':
								$item['group_type_id']=(int)$node->nodeValue;
								break;
							case 'Building':
								$item['location']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'BuildingID':
								$item['building_id']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'RoomDescription':
								$item['room']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'EventTypeDescription':
								$item['event_type']=$node->nodeValue;
								break;
							case 'EventTypeID':
								$item['event_type_id']=$node->nodeValue;
								break;
							case 'GMTEventStart':
								$item['date_ts']=$_LW->toTS($node->nodeValue, 'UTC');
								$item['date_dt']=$_LW->toDate('Y-m-d H:i:00', $item['date_ts'], 'UTC');
								break;
							case 'GMTEventEnd':
								$item['date2_ts']=$_LW->toTS($node->nodeValue, 'UTC');
								$item['date2_dt']=$_LW->toDate('Y-m-d H:i:00', $item['date2_ts'], 'UTC');
								break;
							case 'TimeEventStart':
									$item['time_event_start']=$node->nodeValue;
									break;
							case 'TimeEventEnd':
									$item['time_event_end']=$node->nodeValue;
									break;
							case 'TimeZone':
								$item['timezone']=$node->nodeValue=='ET' ? 'America/New_York' : ($node->nodeValue=='PT' ? 'America/Los_Angeles' : ($node->nodeValue=='MT' ? 'America/Denver' : ($node->nodeValue=='CT' ? 'America/Chicago' : $_LW->getTimezoneForAbbreviation($node->nodeValue))));
								if (empty($item['timezone'])) {
									$item['timezone']=!empty($_LW->CONFIG['TIMEZONE']) ? $_LW->CONFIG['TIMEZONE'] : 'America/New_York';
								};
								break;
							case 'StatusID':
								$item['status_id']=(int)$node->nodeValue;
								$item['status']=$this->getStatusByID($username, $password, $item['status_id']);
								break;
							case 'StatusTypeID':
								$item['status_type']=(int)$node->nodeValue;
								if ($item['status_type']=='-12') {
									$item['canceled']=1;
								};
								break;
							case 'Contact':
								$item['contact_info']=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'ContactEmailAddress':
								$item['contact_info']=!empty($item['contact_info']) ? $item['contact_info'].' (<a href="mailto:'.$_LW->setFormatClean($node->nodeValue).'">'.$_LW->setFormatClean($node->nodeValue).'</a>)' : $_LW->setFormatClean($node->nodeValue);
								break;
						};
					};
					if (!empty($item['title']) && !empty($item['group_title']) && (empty($groups) || in_array($item['group_title'], $groups)) && (empty($buildings) || in_array($item['building_id'], $buildings)) && (empty($statuses) || in_array($item['status_id'], $statuses)) && (empty($event_types) || in_array($item['event_type_id'], $event_types)) && (empty($group_types) || in_array($item['group_type_id'], $group_types))) { // if each result is valid
						if (!empty($item['location']) && !empty($item['room'])) { // merge room into location
							$item['location'].=', '.$item['room'];
						};
						if (!empty($item['time_event_start'])) { // try to use time_event_start because that is often more accurate than the default GMT entry
							$item['date_ts']=$_LW->toTS($item['time_event_start'], @$item['timezone']);
							$item['date_dt']=$_LW->toDate('Y-m-d H:i:00', $item['date_ts'], 'UTC');
						};
						if (!empty($item['time_event_end'])) {
							$item['date2_ts']=$_LW->toTS($item['time_event_end'], @$item['timezone']);
							$item['date2_dt']=$_LW->toDate('Y-m-d H:i:00', $item['date2_ts'], 'UTC');
						};
						if (!empty($item['room'])) {
							unset($item['room']);
						};
						if (!empty($_LW->REGISTERED_APPS['ems']['custom']['enable_udfs']) && !empty($item['booking_id'])) {
							$item['udfs']=$this->getUDFs($username, $password, $item['booking_id'], -42);
						};
						foreach($item as $key=>$val) { // sanitize result data
							$item[$key]=$_LW->setFormatSanitize($val);
						};
						$output[]=$item; // add it to the results to return
					};
				};
			};
			$hash=hash('md5', serialize(array(@$groups, @$buildings, @$statuses, @$event_types, @$group_types, @$group_id))); // get hash for feed
			if (sizeof($output)) { // if there were results
				if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems')) { // ensure EMS directory exists
					@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems');
				};
				if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache')) { // ensure feed_cache directory exists
					@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache');
				};
				@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash, serialize($output), LOCK_EX); // cache the results
			}
			else { // else if there were no results
				if ($tmp=@file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash)) { // fall back on most recent cache if available (in case of EMS API failure)
					if ($tmp=@unserialize($tmp)) {
						$output=$tmp;
					};
					$_LW->logError('Failed to retrieve results from EMS ('.$_SERVER['REQUEST_URI'].'). Will fall back on: '.$_LW->INCLUDES_DIR_PATH.'/data/ems/feed_cache/'.$hash.' ('.(!empty($tmp) ? sizeof($tmp) : 0).')'); // record failure
				};
			};
			return $output; // return all results
		};
	};
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
	$opts=array( // set default parameters
		'UserName'=>$username,
		'Password'=>$password
	);
	try {
		$res=@$this->__soapCall('GetStatuses', array('message'=>$opts)); // perform SOAP call
	}
	catch (Exception $e) {
		$_LW->logError('EMS: '.$e->getMessage());
	}
	if (!empty($res)) { // if there was a valid response
		if ($res2=$this->getResponse('GetStatuses', $res)) { // if the response parses
			if ($statuses=$res2->query('/Statuses/Data')) { // fetch and format results
				foreach($statuses as $status) {
					if ($status->hasChildNodes()) {
						$item=array();
						foreach($status->childNodes as $node) {
							switch($node->nodeName) {
								case 'Description':
									$item['description']=$_LW->setFormatClean($node->nodeValue);
									break;
								case 'ID':
									$item['id']=(int)$node->nodeValue;
									break;
								case 'StatusTypeID':
									$item['status_type_id']=(int)$node->nodeValue;
									break;
								case 'DisplayOnWeb':
									$item['display_on_web']=$node->nodeValue=='true' ? true : false;
									break;
							};
						};
						if (!empty($item)) { // sanitize result data
							foreach($item as $key=>$val) {
								$item[$key]=$_LW->setFormatSanitize($val);
							};
						};
						if (!empty($item['description']) && !empty($item['id'])) { // if each result is valid
							$this->statuses[$item['id']]=$item; // add it to the results to return
						};
					};
				};
			};
		};
	};
	$_LW->setVariable('ems_statuses', $this->statuses, 3600); // cache the statuses
};
}

public function getStatusByID($username, $password, $id) { // gets an EMS status by ID
global $_LW;
if (!isset($this->statuses)) { // if there are no statuses yet
	$this->getStatuses($username, $password); // get EMS statuses
};
if (isset($this->statuses[$id]['description'])) { // return cached response
	return $this->statuses[$id]['description'];
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
	$opts=array( // set default parameters
		'UserName'=>$username,
		'Password'=>$password
	);
	try {
		$res=@$this->__soapCall('GetGroups', array('message'=>$opts)); // perform SOAP call
	}
	catch (Exception $e) {
		$_LW->logError('EMS: '.$e->getMessage());
	}
	if (!empty($res)) { // if there was a valid response
		if ($res2=$this->getResponse('GetGroups', $res)) { // if the response parses
			if ($groups=$res2->query('/Groups/Data')) { // fetch and format results
				foreach($groups as $group) {
					if ($group->hasChildNodes()) {
						$item=array();
						foreach($group->childNodes as $node) {
							switch($node->nodeName) {
								case 'GroupName':
									$item['title']=$_LW->setFormatClean($node->nodeValue);
									break;
								case 'ID':
									$item['id']=(int)$node->nodeValue;
									break;
							};
						};
						if (!empty($item)) { // sanitize result data
							foreach($item as $key=>$val) {
								$item[$key]=$_LW->setFormatSanitize($val);
							};
						};
						if (!empty($item['title']) && !empty($item['id'])) { // if each result is valid
							$this->groups[$item['id']]=$item; // add it to the results to return
						};
					};
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
	$this->group_types=array();
	$opts=array( // set default parameters
		'UserName'=>$username,
		'Password'=>$password
	);
	try {
		$res=@$this->__soapCall('GetGroupTypes', array('message'=>$opts)); // perform SOAP call
	}
	catch (Exception $e) {
		$_LW->logError('EMS: '.$e->getMessage());
	}
	if (!empty($res)) { // if there was a valid response
		if ($res2=$this->getResponse('GetGroupTypes', $res)) { // if the response parses
			if ($group_types=$res2->query('/GroupTypes/Data')) { // fetch and format results
				foreach($group_types as $group_type) {
					if ($group_type->hasChildNodes()) {
						$item=array();
						foreach($group_type->childNodes as $node) {
							switch($node->nodeName) {
								case 'Description':
									$item['title']=$_LW->setFormatClean($node->nodeValue);
									break;
								case 'ID':
									$item['id']=(int)$node->nodeValue;
									break;
							};
						};
						if (!empty($item)) { // sanitize result data
							foreach($item as $key=>$val) {
								$item[$key]=$_LW->setFormatSanitize($val);
							};
						};
						if (!empty($item['title']) && !empty($item['id'])) { // if each result is valid
							$this->group_types[$item['id']]=$item; // add it to the results to return
						};
					};
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
	$opts=array( // set default parameters
		'UserName'=>$username,
		'Password'=>$password
	);
	try {
		$res=@$this->__soapCall('GetEventTypes', array('message'=>$opts)); // perform SOAP call
	}
	catch (Exception $e) {
		$_LW->logError('EMS: '.$e->getMessage());
	}
	if (!empty($res)) { // if there was a valid response
		if ($res2=$this->getResponse('GetEventTypes', $res)) { // if the response parses
			if ($event_types=$res2->query('/EventTypes/Data')) { // fetch and format results
				foreach($event_types as $event_type) {
					if ($event_type->hasChildNodes()) {
						$item=array();
						foreach($event_type->childNodes as $node) {
							switch($node->nodeName) {
								case 'Description':
									$item['title']=$_LW->setFormatClean($node->nodeValue);
									break;
								case 'ID':
									$item['id']=(int)$node->nodeValue;
									break;
							};
						};
						if (!empty($item)) { // sanitize result data
							foreach($item as $key=>$val) {
								$item[$key]=$_LW->setFormatSanitize($val);
							};
						};
						if (!empty($item['title']) && !empty($item['id'])) { // if each result is valid
							$this->event_types[$item['id']]=$item; // add it to the results to return
						};
					};
				};
			};
		};
	};
	$_LW->setVariable('ems_event_types', $this->event_types, 3600); // cache the event_types
};
}

public function getUDFs($username, $password, $parent_id, $parent_type) { // fetches EMS UDFs for a booking
global $_LW;
$output=array();
$opts=array( // set default parameters
	'UserName'=>$username,
	'Password'=>$password,
	'ParentID'=>$parent_id,
	'ParentType'=>$parent_type
);
try {
	$res=@$this->__soapCall('GetUDFs', array('message'=>$opts)); // perform SOAP call
}
catch (Exception $e) {
	$_LW->logError('EMS: '.$e->getMessage());
}
if (!empty($res)) { // if there was a valid response
	if ($res2=$this->getResponse('GetUDFs', $res)) { // if the response parses
		if ($udfs=$res2->query('/UDFs/Data')) { // fetch and format results
			foreach($udfs as $udf) {
				if ($udf->hasChildNodes()) {
					$item=array();
					$current_field='';
					foreach($udf->childNodes as $node) {
						switch($node->nodeName) {
							case 'Field':
								$current_field=$_LW->setFormatClean($node->nodeValue);
								break;
							case 'Value':
								if (!empty($current_field)) {
									if (!isset($output[$current_field])) {
										$output[$current_field]=array();
									};
									$vals=explode("\n", $node->nodeValue);
									foreach($vals as $val) {
										$output[$current_field][]=$_LW->setFormatClean($val);
									};
								};
								break;
						};
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