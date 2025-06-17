<?php

$_LW->REGISTERED_APPS['feeds_25live']=[
	'title'=>'25Live Feed Integration',
	'handlers'=>['onGetFeedData', 'onSaveSuccess', 'onLoad', 'onBeforeOutput', 'onBeforeSync', 'onOutput'],
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
		'Admissions Interview'	=>	'Private Event',
		'Chalk Talk - Education'	=>	'Seminar & Lecture',
		'Chalk Talk - Research'	=>	'Seminar & Lecture',
		'Conference/Symposium-Administr'	=>	'Conference & Symposium',
		'Conference/Symposium-Clinical'	=>	'Conference & Symposium',
		'Conference/Symposium-Education'	=>	'Conference & Symposium',
		'Conference/Symposium-Research'	=>	'Conference & Symposium',
		'Grand Rounds - Clinical'	=>	'Grand Rounds',
		'Grand Rounds - Education'	=>	'Grand Rounds',
		'Grand Rounds - Research'	=>	'Grand Rounds',
		'Journal Club - Education'	=>	'Journal Club',
		'Journal Club - Research'	=>	'Journal Club',
		'Meeting - Administrative'	=>	'Meeting',
		'Meeting - Clinical'	=>	'Meeting',
		'Meeting - Education'	=>	'Meeting',
		'Meeting - Research'	=>	'Meeting',
		'Reception - Administrative'	=>	'Reception',
		'Reception - Clinical'	=>	'Reception',
		'Reception - Education'	=>	'Reception',
		'Reception - Research'	=>	'Reception',
		'Renovation/Maint - Non-Capital'	=>	'Private Event',
		'Seminar - Administrative'	=>	'Seminar & Lecture',
		'Seminar - Clinical'	=>	'Seminar & Lecture',
		'Seminar - Education'	=>	'Seminar & Lecture',
		'Seminar - Research'	=>	'Seminar & Lecture',
		'SPO Administration'	=>	'Private Event',
		'SPO Capital Project Meeting'	=>	'Private Event',
		'Student Orientation'	=>	'Student Event',
		'Thesis Defense'	=>	'Thesis Defense',
		'Training/Workshop - Administr'	=>	'Compliance & Training',
		'Training/Workshop - Clinical'	=>	'Compliance & Training',
		'Training/Workshop - Education'	=>	'Compliance & Training',
		'Training/Workshop - Research'	=>	'Compliance & Training',
	];
	$tag_mappings=[
		// map certain categories to tags

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
			
			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Organization"')!==false) { // convert this field to organization
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Organization";ID=[0-9]+?;TYPE=Enumeration:([^\n\r]+?)[\n\r]~', $after, $matches2);
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

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Organizer"')!==false) { // convert name/email field to contact info
				// $_LW->logDebug('checking after 3.5: ' . $after);
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Organizer";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches2);
				if (!empty($matches2[1])) {
					$after=substr($after, 0, -10).'X-LIVEWHALE-CONTACT-INFO:'.$matches2[1][0]."\nEND:VEVENT";
					$_LW->logDebug('ical adding: X-LIVEWHALE-CONTACT-INFO:'.$matches2[1][0].(!empty($matches3[1]) ? '<br/>'.$matches3[1][0] : ''));
				};
			};

			if (strpos($after, 'X-TRUMBA-CUSTOMFIELD;NAME="Organizer"')!==false) { // convert name/email field to contact info
				// $_LW->logDebug('checking after 3.5: ' . $after);
				$matches2=[];
				preg_match_all('~X\-TRUMBA\-CUSTOMFIELD;NAME="Organizer";ID=[0-9]+?;TYPE=SingleLine:([^\n\r]+?)[\n\r]~', $after, $matches2);
				if (!empty($matches2[1])) {
					$after=substr($after, 0, -10).'X-LIVEWHALE-CONTACT-INFO:'.$matches2[1][0]."\nEND:VEVENT";
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

public function onBeforeSync($type, $subscription_id, $buffer) { 
    global $_LW;
        
    if ($type == 'events') {

    	$_LW->logDebug('onbeforesync 1');
        // example processing of a feed $buffer:
        if (strpos(@$_LW->linked_calendar_url, '://25livepub.collegenet.com')!==false || strpos(@$_LW->linked_calendar_url, 'live/25live')!==false) { // if 25Live feed
            // empty DESCRIPTION field
            // $_LW->logDebug('onbeforesync 2: ' . serialize($buffer));
            $buffer['description'] = '';
        };
        
    }

    return $buffer;
}

/*
public function onAfterSync($type, $subscription_id, $event_id, $mode, $item) { 
global $_LW;
if ($mode !== 'create') { // for updates or unchanged, sync the other custom fields
	$data_to_save=[
        'summary'=>(!empty($item['summary']) ? $item['summary'] : ''),
        'categories'=>(!empty($item['categories']) ? $item['categories'] : ''),
        'associated_data'=>(!empty($item['tags']) ? ['tags'=>$item['tags']] : []),
        'custom_organization'=>(!empty($item['custom_organization']) ? $item['custom_organization'] : ''),
        // 'custom_hide_location'=>(!empty($item['custom_hide_location']) ? $item['custom_hide_location'] : 'False'),
    ];
    $_LW->update($type, $event_id, $data_to_save);
};
}
*/

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

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='events_subscriptions_edit') { // if on the linked calendar editor page
	$help_text='<!-- START 25LIVE HELP --><div class="fields ems"><h3 class="header">25Live Feeds</h3><div class="fieldset"><p>To use a 25Live feed, set your ICAL url above as <strong>https://'.$_LW->CONFIG['HTTP_HOST'].'/live/25live/group/{ORG NAME}</strong>.</p><p>Replace &amp; with "and" and remove spaces/slashes when formatting the Organization name. For example, the 25Live Organization "Pathology & Laboratory Medicine" can be used in the URL as "/live/25live/group/PathologyandLaboratoryMedicine".</p><p>If no /group/ is specified, the feed will include all 25Live events that haven\'t been captured by another /25live/group/ Linked Calendar.</p></div></div><!-- END 25LIVE HELP -->';
	$buffer=str_replace('<!-- START INSTRUCTIONS -->', $help_text.'<!-- START INSTRUCTIONS -->', $buffer); // inject the EMS url

};
return $buffer;
}


}

?>