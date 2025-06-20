<?php

// LiveURL plugin for 25Live ICAL requests.

$events_feeds = [
	'https://25livepub.collegenet.com/calendars/lw-acad-deadlines.ics',
	'https://25livepub.collegenet.com/calendars/lw_academic.ics',
	'https://25livepub.collegenet.com/calendars/lw-athletics.ics',
	'https://25livepub.collegenet.com/calendars/lw_civic_engagement.ics',
	'https://25livepub.collegenet.com/calendars/lw_creative_performing_arts.ics',
	'https://25livepub.collegenet.com/calendars/lw_cultural.ics',
	'https://25livepub.collegenet.com/calendars/lw_informational.ics',
	'https://25livepub.collegenet.com/calendars/lw_reception_meal.ics',
	'https://25livepub.collegenet.com/calendars/lw_special_events.ics',
	'https://25livepub.collegenet.com/calendars/lw_student_org.ics',
	'https://25livepub.collegenet.com/calendars/lw_wellness_recreation.ics',
];

require $LIVE_URL['DIR'].'/cache.livewhale.php'; // load LiveWhale
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

if ($params['group']==['other']) {
	$params['group']=[]; // empty when using group/other
};

// Use FS /data/ caching instead of set/getVariable
$output='';
$cache_path = $_LW->INCLUDES_DIR_PATH.'/data/25live/';
$megafeed_filename = '25live_combined.ics'; // remove _debug when testing is complete
$megafeed_cache_ts=@filemtime($cache_path.$megafeed_filename);

if (!is_dir($cache_path)) { // init 25live directory
	@mkdir($cache_path);
};

// #FIXME: uncomment these lines once the 25live sync is satisfactory:
if (!file_exists($cache_path.$megafeed_filename) || (!empty($megafeed_cache_ts) && $megafeed_cache_ts<$_SERVER['REQUEST_TIME']-900) || isset($_LW->_GET['refresh'])) { // re-fetch calendar every 15min
	// $_LW->logDebug('25 Live (filesystem version) - refreshing mega-feed.');
	$cal='';
	foreach($events_feeds as $url) {
		$res=$_LW->getUrl($url);
		$filename=substr($url, strrpos($url, '/') + 1);
		if (strpos($res,'END:VCALENDAR')!==false) { // calendar feed loaded
			@file_put_contents($cache_path.$filename, $res, LOCK_EX); // cache this individual feed to FS
		}
		else { // calendar feed failed to load
			$_LW->logDebug('25 Live feed ' . $url . ' failed to load, falling back to cached version.');
			$res=@file_get_contents($cache_path.$filename);
		};
		// add $res to combined mega-feed $cal
		if (empty($cal)) {
			$cal=$res;
		}
		else {
			$matches=[];
			preg_match_all('~BEGIN:VEVENT.+?END:VEVENT~s', $res, $matches);
			if (!empty($matches[0])) {
				$cal=preg_replace('~END:VCALENDAR$~', implode("\n", $matches[0])."\nEND:VCALENDAR", $cal);
			};
		};
	};
	if (!empty($cal)) { // if has results
		@file_put_contents($cache_path.$megafeed_filename, $cal, LOCK_EX); // cache the megafeed
		$output=$cal;
	};
};

if (empty($output)) { // if not generated fresh for any reason
	$output=@file_get_contents($cache_path.$megafeed_filename); // use cached megafeed
}

$exclude_organizations = [];
$org_gids = [];

if (empty($params['group'])) { // fallback case, check saved 25live/ feeds
	foreach($_LW->dbo->query('select', 'id,gid,url', 'livewhale_events_subscriptions', 'url LIKE "%live/25live%"', '')->run() as $res2) { // fetch calendars
		$orgname = str_replace(['#calendar_subscription',' ','%20'],['','',''],substr($res2['url'], strrpos($res2['url'], '/') + 1)); // extracts orgname from /live/25live/organization/orgname#calendar_subscription
		$exclude_organizations[] = $orgname;
		$org_gids[$orgname] = ['id'=>$res2['id'], 'gid'=>$res2['gid']]; // save feed id and gid for auto-sharing later
	};
	$_LW->setVariable('25live_calendar_gids', $org_gids, 0); // cache the org_gids list for auto-sharing
	if (isset($_LW->_GET['test'])) {print_r($exclude_organizations);}
	if (isset($_LW->_GET['test2'])) {echo '<pre>'; print_r($org_gids);}
}
else { // group(s) specified
	foreach ($params['group'] as $key=>$val) { // remove all spaces from group names,since we won't use them in checking
		$params['group'][$key] = strtolower(str_replace(' ','',$val));
	};
};


if (!empty($output)) { // if there is calendar output
	$matches=[];
	preg_match_all('~(BEGIN:VEVENT.+?END:VEVENT)~s', $output, $matches); // get all events
	if (!empty($matches[1])) {		
		foreach($matches[1] as $key=>$val) { // for each event
			$organization='';
			if (strpos($val, 'X-TRUMBA-CUSTOMFIELD;NAME="Organization"')!==false) { // convert organization(s) to categories
				$val=preg_replace('~(X\-TRUMBA\-CUSTOMFIELD;NAME="Organization"[^\n\r]+?)[\n\r]\s+?(?!(?:METHOD|SUMMARY|DESCRIPTION|URL|LOCATION|GEO|UID|DTSTART|DTEND|DTSTAMP|DURATION|STATUS|CANCELLED|CANCELED|RRULE|RDATE|RECURRENCE-ID|ORGANIZER|CONTACT|SEQUENCE|CATEGORIES|CLASS|TRANSP|LAST-MODIFIED|CREATED|PRIORITY|DUE|EXDATE|EXRULE|ATTACH|IMAGE|ATTENDEE|X\-[A-Z0-9\-]+))+~s', '\\1\\2', $val);
				$val=str_replace([' ','/','&amp\;'],['','','and'],$val); // remove all spaces and slashes from Organization name and replace & with AND
				$matches2=[];
				// echo '<pre>'.$val.'</pre><br><br><br>';
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Organization";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $val, $matches2);
				if (!empty($matches2[1])) {
					$organization = $matches2[1][0];
				};
				if (strpos($organization, '\,')) { // split by commas if present
					$all_organizations = explode('\,',$organization);
					$organization = $all_organizations[0]; // keep first name as Primary Organization
				}
				if (isset($_LW->_GET['test'])) {echo 'org = ' . $organization . '<br>' . $matches[1][$key] . '<br><br>';}
			};
			if (!empty($params['group']) && (empty($organization) || !in_array(strtolower($organization),$params['group']))) {
				// if we've specified an organization, and this event ISN'T in that org, remove it
				if (isset($_LW->_GET['test'])) {echo 'EVENT REMOVED: org = ' . $organization . '<br>';}
				$output=str_replace($matches[1][$key], '', $output);
			};
			if (empty($params['group']) && (empty($organization) || in_array(strtolower($organization),$exclude_organizations))) {
				// when no organization specified, exclude events already included in another saved feed
				if (isset($_LW->_GET['test'])) {echo 'EVENT REMOVED: org = ' . $organization . '<br>';}
				$output=str_replace($matches[1][$key], '', $output);
			};
		};
	};
};

// replace 2+ line breaks to compress output
// $output = preg_replace("/([\r\n]{4,}|[\n]{2,}|[\r]{2,})/", "\n", $output);

// DEBUG:
if (isset($_LW->_GET['test'])) {
	echo 'TEST:'.print_r($output,true); exit;
}


// exit;

if (empty($output)) { // set empty ical when no events found for these settings
$output = 'BEGIN:VCALENDAR
PRODID:-//github.com/rianjs/ical.net//NONSGML ical.net 4.0//EN
VERSION:2.0
END:VCALENDAR';
}

header('Content-Type: text/calendar'); // send content encoding header
die($output); // show the output

?>