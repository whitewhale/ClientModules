<?php

$_LW->REGISTERED_APPS['list_all_calendars']=array(
	'title'=>'List All Calendars',
	'handlers'=>array('onBeforeOutput'),
	'application'=>array(
		'order'=>-1
	)
); // configure this module

class LiveWhaleApplicationListAllCalendars {

public function onBeforeOutput($buffer) {
global $_LW;

if (strpos($buffer, '<xphp var="all_calendars"')!==false) { // // Populate the group calendar variable if it's used
	$GLOBALS['all_calendars']='<div id="lw_all_calendars">';
	foreach($_LW->dbo->query('select', 'livewhale_groups.fullname as title, IF(livewhale_groups.fullname_public IS NOT NULL, livewhale_groups.fullname_public, livewhale_groups.fullname) as display_title, livewhale_groups.directory', 'livewhale_groups', 'livewhale_groups.directory IS NOT NULL AND livewhale_groups.fullname!="Public"', 'IF(livewhale_groups.fullname_public IS NOT NULL, livewhale_groups.fullname_public, livewhale_groups.fullname) ASC')->groupBy('livewhale_groups.id')->run() as $res2) { // fetch calendars

		$GLOBALS['all_calendars'].='<h3 class="calendar_group">' . $_LW->setFormatClean($res2['display_title']) . '</h3>
			<ul class="feed_source">
				<li><a href="' . $_LW->setFormatClean($res2['directory']) . '">Link</a></li>
				<li><a href="https://calendar.tamu.edu/live/ical/events/group/' . $_LW->setFormatClean($res2['title']) . '">iCal</a></li>
				<li><a href="https://calendar.tamu.edu/live/rss/events/group/' . $_LW->setFormatClean($res2['title']) . '">RSS</a></li>
				<li><a href="https://calendar.tamu.edu/live/json/events/group/' . $_LW->setFormatClean($res2['title']) . '">JSON</a></li>
			</ul>';

	};
	$GLOBALS['all_calendars'].='</div>';
}

return $buffer;
}


}

?>