<?php

// LiveURL plugin for EMS-to-iCAL requests.

$module=array_shift($LIVE_URL['REQUEST']); // get module
require $LIVE_URL['DIR'].'/cache.livewhale.php'; // load LiveWhale
$count=0;
$params=[];
if ($tmp=@parse_url($LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1], PHP_URL_PATH)) {
	if (!empty($tmp) && $tmp!=$LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1]) {
		$LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1]=$tmp;
	};
};

foreach($LIVE_URL['REQUEST'] as $val) { // convert request elements to args
	$val=str_replace('\\', '/', rawurldecode($val));
	if (!$count) {
		$key=$val;
	}
	else {
		if (!isset($params[$key])) {
			if (in_array($key, ['buildings', 'event_types', 'group_types', 'statuses']) || strpos($key, '_udf')!==false) {
				$params[$key]=[$_LW->setFormatClean($val)];
			}
			else {
				$params[$key]=$_LW->setFormatClean($val);
			};
		}
		else {
			if (!is_array($params[$key])) {
				$params[$key]=[$params[$key]];
			};
			$params[$key][]=$_LW->setFormatClean($val);
		};
	};
	$count=$count ? 0 : 1;
};
if (!empty($params['group'])) { // if there are (LiveWhale) groups specified, convert LiveWhale group(s) to EMS group ID
	$params['group_ids']=[];
	if (!is_array($params['group'])) {
		$params['group']=[$params['group']];
	};
	foreach($params['group'] as $key=>$val) {
		$res=$_LW->dbo->query('select', 'livewhale_custom_data.value', 'livewhale_groups', 'livewhale_groups.fullname='.$_LW->escape($val))->innerJoin('livewhale_custom_data', 'livewhale_custom_data.type="groups" AND livewhale_custom_data.name="ems_group" AND livewhale_custom_data.pid=livewhale_groups.id')->firstRow('value')->run();
		if (!empty($res) && $res[0]=='a') { // if serialized array
			// $_LW->logDebug('params groups unserializing ' . $res);
			$params['group_ids']=array_merge($params['group_ids'],unserialize($res));
		}
		else { // if single value
			$params['group_ids'][]=$res;
		}
		// $_LW->logDebug('NEW group_ids = ' . serialize($params['group_ids']));
		if (empty($params['group'][$key]) && !empty($_LW->REGISTERED_APPS['ems']['custom']['groups_map'])) {
			// Fall back to pre-set array in config, if exists
			$params['group_ids'][]=array_search(urldecode($val), $_LW->REGISTERED_APPS['ems']['custom']['groups_map']);
		}
	};
	unset($params['group']); // only use EMS group_ids from here onwards
};
$is_group_request=(!empty($params['group_ids']) ? true : false); // check if this is a group request
$reservation_udfs=[];
$booking_udfs=[];
foreach($params as $key=>$val) { // capture all reservation and booking UDFs
	if (strpos($key, 'reservation_udf_')===0) {
		$reservation_udfs[substr($key, 16)]=$val;
	}
	else if (strpos($key, 'booking_udf_')===0) {
		$booking_udfs[substr($key, 12)]=$val;
	};
};
if (!empty($params['custom_reservation_udf_request']) || !empty($reservation_udfs) || !empty($booking_udfs)) { // if this is a udf request
	if (empty($params['start_date'])) { // default to -6 months if no start_date given
		$params['start_date']=$_LW->toDate('Y-m-d', $_LW->toTS('-6 months'));
	};
	if (empty($params['end_date'])) { // default to +6 months if no end_date given
		$params['end_date']=$_LW->toDate('Y-m-d', $_LW->toTS('+6 months'));
	};
}
else if (!empty($is_group_request)) { // if this is a group request
	if (empty($params['start_date'])) { // default to -1 year if no start_date given
		$params['start_date']=$_LW->toDate('Y-m-d', $_LW->toTS('-1 year'));
	};
	if (empty($params['end_date'])) { // default to +1 year if no end_date given
		$params['end_date']=$_LW->toDate('Y-m-d', $_LW->toTS('+1 year'));
	};
}
else { // else if this is not a group request or a udf request
	if (empty($params['start_date'])) { // default to -1 month if no start_date given
		$params['start_date']=$_LW->toDate('Y-m-d', $_LW->toTS('-1 month'));
	};
	if (empty($params['end_date'])) { // default to +1 month if no end_date given
		$params['end_date']=$_LW->toDate('Y-m-d', $_LW->toTS('+1 month'));
	};
};

$custom_filter=[];
if (!empty($params['custom_reservation_udf_request']) && is_array($_LW->REGISTERED_APPS['ems']['custom']['custom_reservation_udf_request']) && array_key_exists($params['custom_reservation_udf_request'][0],$_LW->REGISTERED_APPS['ems']['custom']['custom_reservation_udf_request'])) { // use custom request if set
	$custom_filter=['reservationUDFSearch' => $_LW->REGISTERED_APPS['ems']['custom']['custom_reservation_udf_request'][$params['custom_reservation_udf_request'][0]]]; // pass configured custom filter through to request
};

if (empty($params['group_ids']) && empty($reservation_udfs) && empty($booking_udfs) && empty($custom_filter)) { // however for performance reasons, we are enforcing is_single_group or a UDF request at this time
	header('HTTP/1.0 400 Bad Request');
	die('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html>
<head>
<title>400 Bad Request</title>
</head>
<body>
<h1>Bad Request</h1>
<p>Invalid URL: A group or UDF request must be specified.</p>
</body></html>');
};

$_LW->initModule('application', 'ems'); // init EMS module
$output='BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Alex//NONSGML v1.0//EN
X-WR-CALNAME:EMS Events
END:VCALENDAR'; // set default output to empty ICAL feed
if (!empty($params['start_date']) 
	&& !empty($params['end_date']) 
	&& @strtotime($params['start_date'])!==false 
	&& @strtotime($params['end_date'])!==false) { // if required start and end dates are valid
	if ($_LW->a_ems->initEMS()) { // if EMS loaded
		if ($ical=$_LW->a_ems->getBookingsAsICAL($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS($params['start_date'])), $_LW->toDate(DATE_W3C, $_LW->toTS($params['end_date'])), (!empty($params['group']) ? $params['group'] : false), (!empty($params['buildings']) ? $params['buildings'] : false), (!empty($params['statusus']) ? $params['statuses'] : false), (!empty($params['event_types']) ? $params['event_types'] : false), (!empty($params['group_types']) ? $params['group_types'] : false), (!empty($params['group_ids']) ? $params['group_ids'] : false), false, (!empty($reservation_udfs) ? $reservation_udfs : false), (!empty($booking_udfs) ? $booking_udfs : false), (!empty($custom_filter) ? $custom_filter : false))) { // fetch and format bookings as ICAL feed
			$output=$ical;
		};
	}
	else {
		die($_LW->httpResponse(404, true));
	};
};
header('Content-Type: text/calendar'); // send content encoding header
die($output); // show iCAL

?>