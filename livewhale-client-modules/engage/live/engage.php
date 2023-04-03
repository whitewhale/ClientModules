<?php

// LiveURL plugin for Engage ICAL requests.

// This plugin lets you add $_LW->CONFIG['ENGAGE_ICAL_URL'] to your config pointing to your all-in-one .ics file from engage (e.g. https://myschool.campuslabs.com/engage/events.ics)

// Then you can add linked calendars to /live/engage/host/My Event Host which will crawl through events.ics and only show events matching X-HOSTS: My Event Host


require $LIVE_URL['DIR'].'/cache.livewhale.php'; // load LiveWhale
header('Content-Type: text/calendar'); // send content encoding header
$count=0;
$params=[];
foreach($LIVE_URL['REQUEST'] as $val) { // convert request elements to args
	$val=str_replace('\\', '/', rawurldecode($val));
	if (!$count) {
		$key=$val;
	}
	else {
		if (!isset($params[$key])) {
			$params[$key]=[];
		};
		$params[$key][]=$_LW->setFormatAmps($val);
	};
	$count=$count ? 0 : 1;
};
$output=$_LW->getVariable('engage_calendar'); // get calendar output
if (!$_LW->hasVariable('engage_calendar') || $_LW->getVariableMTime('engage_calendar')<$_SERVER['REQUEST_TIME']-3600) { // re-fetch calendar hourly
	$cal=$_LW->getUrl($_LW->CONFIG['ENGAGE_ICAL_URL']); // fetch it
	if (empty($cal)) { // if failed
		$cal=$output; // use previously cached output
	};
	$_LW->setVariable('engage_calendar', $cal, 0); // cache the calendar
};
if (!empty($output) && !empty($params['host'])) { // if there is calendar output and there are hosts specified
	$matches=[];
	preg_match_all('~\nBEGIN:VEVENT.+?X\-HOSTS:([^\n]+).+?END:VEVENT~s', $output, $matches); // match all events w/ X-HOSTS
	if (!empty($matches[1])) {
		foreach($matches[1] as $key=>$val) {
			$val=trim($_LW->setFormatAmps(str_replace('\\', '', $val))); // format the host
			if (!in_array($val, $params['host'])) { // filter out any events that don't match the specified hosts
				$output=str_replace($matches[0][$key], '', $output);
			};
		};
	};
};

if (empty($output)) { // set empty ical when no events found
	$output = 'BEGIN:VCALENDAR
PRODID:-//github.com/rianjs/ical.net//NONSGML ical.net 4.0//EN
VERSION:2.0
END:VCALENDAR';
}

die($output);

?>