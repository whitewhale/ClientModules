<?php

$_LW->REGISTERED_APPS['feeds_25live']=[
	'title'=>'25Live Feed Integration',
	'handlers'=>['onGetFeedData', 'onSaveSuccess', 'onLoad', 'onBeforeOutput', 'onAfterSync'],
	'flags'=>['no_autoload']
]; // configure this module

class LiveWhaleApplicationFeeds25live {

public function onGetFeedData($url, $buffer) { // on iCAL input
global $_LW;
if (!empty($buffer) && (strpos($url, '://25livepub.collegenet.com')!==false || strpos($url, 'live/25live')!==false)) { // if 25Live feed
	$mappings=[
		// event types
		// NB: Mappings must match w/ exact spelling
		// '25Live Event Type' => 'LiveWhale Calendar Event Type',
		'Academic Calendar'=>'Academic Calendar and Deadlines',
		'Ceremony'=>'Special College Events',
		'Civic Activity'=>'Civic Engagement',
		'Concert / Performance'=>'Creative and Performing Arts',
		'Deadline'=>'Academic Calendar and Deadlines',
		'Demonstration'=>'Informational',
		'Exhibition'=>'Creative and Performing Arts',
		'Fair'=>'Informational',
		'Film / Video'=>'Creative and Performing Arts',
		'Fundraiser / Drive'=>'Civic Engagement',
		'Golf Events'=>'Athletics',
		'Guided Study Session'=>'Academic Events',
		'Holiday'=>'Cultural Celebrations and Observances',
		'Intercollegiate Athletic Competition'=>'Athletics',
		'Intramural / Club Athletics Activity'=>'Athletics',
		'Panel / Discussion'=>'Academic Events',
		'Presentation / Lecture'=>'Academic Events',
		'Reading'=>'Academic Events',
		'Reception / Meal / Banquet'=>'Receptions, Meals, and Banquets',
		'Recital'=>'Creative and Performing Arts',
		'Registration'=>'Informational',
		'Religious Services / Activities'=>'Cultural Celebrations and Observances',
		'Scholars Convocation'=>'Academic Events',
		'Special Event'=>'Special College Events',
		'Student Activity'=>'Student Activities',
		'Study Break'=>'Student Activities',
		'Tabling Event'=>'Informational',
		'Wellness Program'=>'Wellness and Recreation',
		'Workshop / Training'=>'Informational',
		// audiences
		'Audience - Alumni'=>'Alumni',
		'Audience - Faculty / Staff'=>'Faculty & Staff',
		'Audience - Prospective Students'=>'Prospective Students',
		'Audience - Student Families'=>'Student Families',
		'Audience - Public'=>'General Public',
		'Audience - Students'=>'Students',
	];
	$tag_mappings=[
		// map certain categories to tags
		'Admission'=>'Admission',
		'Alum Involvement'=>'Alum Involvement',
		'Art'=>'Art',
		'Athletics'=>'Athletics',
		'Career Development'=>'Career or Professional Development',
		'Commencement Activity'=>'Commencement',
		'Community Partnership'=>'Community Partnership',
		'Community Service'=>'Community Service',
		'Multicultural'=>'Multicultural',
		'Family Weekend'=>'Family Weekend',
		'Important Dates'=>'Important Dates',
		'Met Opera'=>'Met Opera',
		'Music'=>'Music',
		'New Student Orientation'=>'New Student Orientation',
		'Political'=>'Political',
		'Public Events Series'=>'Public Events Series',
		'Religious/Spiritual'=>'Religious and Spiritual',
		'Reunion'=>'Reunion',
		'Scholars\' Convocation'=>'Scholars\' Convocation',
		'Student Activity'=>'Student Activity',
		'Theater'=>'Theater',
		// 'New 25Live Tag 1'=>'LWC tag 1',
	];
	$buffer=preg_replace('~([\n\r]){2,}~', '\\1', $buffer);
	$matches=[];
	preg_match_all('~(BEGIN:VEVENT.+?END:VEVENT)~s', $buffer, $matches); // get all events
	if (!empty($matches[1])) {
		$find=[];
		$replace=[];
		foreach($matches[1] as $before) { // for each event
			$after=$before;

			//if (strpos($url, 'artdepartment')!==false) $_LW->logDebug('checking event A1: ' . base64_encode($after));

			$count=0;
			while (true) {
				$count++;
				$after=preg_replace('~(X\-TRUMBA\-CUSTOMFIELD;[^\n\r]+?)[\n\r]+\s+?(?!(?:METHOD|SUMMARY|DESCRIPTION|URL|LOCATION|GEO|UID|DTSTART|DTEND|DTSTAMP|DURATION|STATUS|CANCELLED|CANCELED|RRULE|RDATE|RECURRENCE-ID|ORGANIZER|CONTACT|SEQUENCE|CATEGORIES|CLASS|TRANSP|LAST-MODIFIED|CREATED|PRIORITY|DUE|EXDATE|EXRULE|ATTACH|IMAGE|ATTENDEE|X\-[A-Z0-9\-]+))+~s', '\\1\\2', $after);
				if ($after==$before || $count==50) {
					break;
				};
			};

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Event Type"')!==false || strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Categories"')!==false) { // combine event types(s) with categories
				$categories_or_tags=[];
				$categories=[];
				$tags=[];
				$matches2=[];
				$matches3=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Event Type";ID=[0-9]+?;TYPE=number:([^\n\r]+?)[\n\r]~', $after, $matches2);
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Categories";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches3);
				// $_LW->logDebug('checking event types or categories');
				if (!empty($matches2[1])) {
					$categories_or_tags=array_merge($categories_or_tags, $matches2[1]);
				};
				if (!empty($matches3[1][0])) {
					$matches3[1][0]=preg_split('~\\\,\s+~', $matches3[1][0]);
					$categories_or_tags=array_merge($categories_or_tags, $matches3[1][0]);
				};
				if (!empty($categories_or_tags)) {
					// $_LW->logDebug('checking cats and tags: ' . serialize($categories_or_tags));
					foreach($categories_or_tags as $val) { // convert event types and tags via mappings
						if (isset($mappings[$val])) {
							$categories[]=$_LW->setFormatAmps($mappings[$val]);
						} else if (isset($tag_mappings[$val])) {
							$tags[]=$_LW->setFormatAmps($tag_mappings[$val]);
						};
					};
					if (!empty($categories)) {
						if (strpos($after, 'CATEGORIES:')===false) {
							$after=substr($after, 0, -10).'CATEGORIES:'.implode('|', $categories)."\nEND:VEVENT";
						}
						else {
							$after=preg_replace('~CATEGORIES:[^\n\r]+~', '\\0|'.implode('|', $categories), $after);
						};
					};
					if (!empty($tags)) {
						// $_LW->logDebug('adding tags: ' . serialize($tags));
						$after=substr($after, 0, -10).'X-LIVEWHALE-TAGS:'.implode('|',$tags)."\nEND:VEVENT";
					};
				};
			};
			
			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Hide event location on public calendar?"')!==false) {
				// convert this field to hide_location
				// $_LW->logDebug('checking after: ' . $after);
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Hide event location on public calendar\?";ID=[0-9 ]+?;TYPE=Boolean:([^\n\r]+)~', $after, $matches2);
				// $_LW->logDebug('Hide event location found 4: ' . $matches2[1][0]);
				if (!empty($matches2[1])) {
					$after=substr($after, 0, -10).'X-LIVEWHALE-CUSTOM-HIDE-LOCATION:'.ucfirst(strtolower($matches2[1][0]))."\nEND:VEVENT";
				};
			} else { // default to False
				$after=substr($after, 0, -10)."X-LIVEWHALE-CUSTOM-HIDE-LOCATION:False\nEND:VEVENT";
			};

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Organization"')!==false) { // convert this field to organization
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Organization";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches2);
				if (!empty($matches2[1])) {
					// $after=substr($after, 0, -10).'X-LIVEWHALE-CUSTOM-ORGANIZATION:'.str_replace(' ','',$matches2[1][0])."\nEND:VEVENT";
					$after=substr($after, 0, -10).'X-LIVEWHALE-CUSTOM-ORGANIZATION:'.ucwords(strtolower($matches2[1][0]))."\nEND:VEVENT";
					//$_LW->logDebug('ical adding: X-LIVEWHALE-CUSTOM-ORGANIZATION:'.$matches2[1][0]);
				};
			};

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Event Title"')!==false) { // convert this field to summary
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Event Title";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches2);
				if (!empty($matches2[1])) {
					$after=substr($after, 0, -10).'X-LIVEWHALE-SUMMARY:'.$matches2[1][0]."\nEND:VEVENT";
				};
			};

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Submitter ')!==false) { // convert name/email field to contact info
				// $_LW->logDebug('checking after 3.5: ' . $after);
				$matches2=[];
				$matches3=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Submitter Name";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches2);
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Submitter Email";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches3);
				if (!empty($matches2[1])) {
					$after=substr($after, 0, -10).'X-LIVEWHALE-CONTACT-INFO:'.$matches2[1][0].(!empty($matches3[1]) ? '<br/>'.$matches3[1][0] : '')."\nEND:VEVENT";
					// $_LW->logDebug('ical adding: X-LIVEWHALE-CONTACT-INFO:'.$matches2[1][0].(!empty($matches3[1]) ? '<br/>'.$matches3[1][0] : ''));
				};
			};
			
			//if (strpos($url, 'artdepartment')!==false) $_LW->logDebug('checking event A2: ' . base64_encode($after));

			$find[]=$before;
			$replace[]=$after;
		};
		$buffer=str_replace($find, $replace, $buffer);
	};
};
return $buffer;
}

public function onAfterSync($type, $subscription_id, $event_id, $mode, $item) { 
global $_LW;
if ($mode == 'create') {
	if (isset($item['custom_organization']) && strpos($item['custom_organization'],',')!==false) { // if multiple organizations are present, auto-share to other groups if configured
		$org_gids = $_LW->getVariable('25live_calendar_gids');
		if (!empty($org_gids)) {
			$event_orgs = explode(',',$item['custom_organization']);
			foreach ($event_orgs as $event_org) {
				$key = strtolower(str_replace(' ','',$event_org));
				if (isset($org_gids[$key])) { // linked calendar found for org
					if ($org_gids[$key]['id'] !== $subscription_id) { // skip the current group since the event is already here
						// $_LW->logDebug('onAfterSync 5 - unique gid found for organization ' . $key . ' - ' . $org_gids[$key]['gid']);
						$destination_gid = $org_gids[$key]['gid'];
						$_LW->callHandler('data_type', $type, 'onCopyToGroup', [$event_id, $destination_gid, 1, 'link']);
					}
				};
			};
		};
	};
}
else { // for updates or unchanged, sync the other custom fields
	// $_LW->logDebug('syncing event ' . $event_id . ' - ' . serialize($item));
	$data_to_save=[
        'summary'=>(!empty($item['summary']) ? $item['summary'] : ''),
        'categories'=>(!empty($item['categories']) ? $item['categories'] : ''),
        'associated_data'=>(!empty($item['tags']) ? ['tags'=>$item['tags']] : []),
        'custom_organization'=>(!empty($item['custom_organization']) ? $item['custom_organization'] : ''),
        'custom_hide_location'=>(!empty($item['custom_hide_location']) ? $item['custom_hide_location'] : 'False'),
    ];
    // $_LW->logDebug('saving fields: ' . serialize($data_to_save));
    $_LW->update($type, $event_id, $data_to_save);
};
}

public function onSaveSuccess($type, $id) { // on save of linked calendar
global $_LW;
if (!empty($id) && $type=='events_subscription' && !empty($_LW->_GET['reset_events']) && $_LW->userSetting('core_admin')) { // if admin attempts to reset the linked calendar upon save
	$_LW->execute('feeds_25live_reset', ['id'=>$id], 'async', 'private'); // delete all events in the feed and recreate them via feed refresh
};
}

public function onBeforeOutput($buffer) { // before output is returned
global $_LW;
if (!empty($_LW->_GET['lw_debug']) && $_LW->_GET['lw_debug']==1 && !empty($_LW->_GET['reset_all_linked_calendars']) && $_LW->userSetting('core_admin')) { // if admin attempts to reset all linked calendars
	foreach($_LW->dbo->query('select', 'id', 'livewhale_events_subscriptions')->run() as $res2) {
		$_LW->execute('feeds_25live_reset', ['id'=>$res2['id']], 'async', 'private'); // delete all events in the feed and recreate them via feed refresh
	};
	//$_LW->logDebug('Reset all linked calendars triggered.');
};
return $buffer;
}

}

?>