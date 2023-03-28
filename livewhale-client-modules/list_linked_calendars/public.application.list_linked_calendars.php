<?php

$_LW->REGISTERED_APPS['list_linked_calendars']=[
	'title'=>'List Linked Calendars',
	'handlers'=>['onLoad'],
	'custom'=>[
		'page_path'=>'/help/index.php', // URL of where <xphp var="linked_calendars"/> should be generated
	]
];

class LiveWhaleApplicationListLinkedCalendars {

public function onLoad() { // on application load
global $_LW;
if ($_LW->page==$_LW->REGISTERED_APPS['list_linked_calendars']['custom']['page_path']) { // if on the help page
	$GLOBALS['linked_calendars']='';
	foreach($_LW->dbo->query('select', 'livewhale_events_subscriptions.title, livewhale_events_subscriptions.url, livewhale_groups.fullname, GROUP_CONCAT(DISTINCT livewhale_tags.title SEPARATOR ", ") AS tags, GROUP_CONCAT(DISTINCT livewhale_events_categories.title) AS event_types, GROUP_CONCAT(DISTINCT livewhale_events_categories_audience.title SEPARATOR ", ") AS event_audiences', 'livewhale_events_subscriptions', 'livewhale_events_subscriptions.status=1', 'livewhale_events_subscriptions.title ASC') // loop through the calendars
	->innerJoin('livewhale_groups', 'livewhale_groups.id=livewhale_events_subscriptions.gid')
	->leftJoin('livewhale_tags2any', 'livewhale_tags2any.type="events_subscription" AND livewhale_tags2any.id2=livewhale_events_subscriptions.id')
	->leftJoin('livewhale_tags', 'livewhale_tags.id=livewhale_tags2any.id1')
	->leftJoin('livewhale_events_categories2any', 'livewhale_events_categories2any.type="events_subscription" AND livewhale_events_categories2any.id2=livewhale_events_subscriptions.id')
	->leftJoin('livewhale_events_categories', 'livewhale_events_categories.id=livewhale_events_categories2any.id1 AND livewhale_events_categories.type=1')
	->leftJoin('livewhale_events_categories2any AS livewhale_events_categories2any_audience', 'livewhale_events_categories2any_audience.type="events_subscription" AND livewhale_events_categories2any_audience.id2=livewhale_events_subscriptions.id')
	->leftJoin('livewhale_events_categories AS livewhale_events_categories_audience', 'livewhale_events_categories_audience.id=livewhale_events_categories2any_audience.id1 AND livewhale_events_categories_audience.type=2')
	->groupBy('livewhale_events_subscriptions.id')
	->run() as $res2) {
		$GLOBALS['linked_calendars'].='<li><a href="'.$_LW->setFormatClean($res2['url']).'">'.$_LW->setFormatClean($res2['title']).'</a> (Group: '.$_LW->setFormatClean($res2['fullname']).(!empty($res2['event_types']) ? ' | Categories: '.$_LW->setFormatClean($res2['event_types']) : '').(!empty($res2['event_audiences']) ? ' | Audiences: '.$_LW->setFormatClean($res2['event_audiences']) : '').(!empty($res2['tags']) ? ' | Tags: '.$_LW->setFormatClean($res2['tags']) : '').')</li>'; // and display them as rows
	};
	if (!empty($GLOBALS['linked_calendars'])) { // export calendar list to template
		$GLOBALS['linked_calendars']="<ul>\n".$GLOBALS['linked_calendars']."\n</ul>";
	};
};
}

}

?>