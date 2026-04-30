<?php

// LiveURL plugin for Presence API requests.

if (!empty($LIVE_URL['REQUEST'])) { // if valid request
	require $LIVE_URL['DIR'].'/livewhale.php'; // load CMS
	if (!empty($_LW->CONFIG['CREDENTIALS']['PRESENCE']['USERNAME']) && !empty($_LW->CONFIG['CREDENTIALS']['PRESENCE']['IMAGE_PREFIX'])) { // if there is a valid configuration
		if (substr($_LW->CONFIG['CREDENTIALS']['PRESENCE']['IMAGE_PREFIX'], -1, 1)!='/') {
			$_LW->CONFIG['CREDENTIALS']['PRESENCE']['IMAGE_PREFIX'].='/';
		};
		$group=$LIVE_URL['REQUEST'][0];
		if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/presence')) {
			@mkdir($_LW->INCLUDES_DIR_PATH.'/data/presence');
		};
		$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/presence/'.rawurlencode($_LW->CONFIG['CREDENTIALS']['PRESENCE']['USERNAME']).'.json'; // set cache path
		header('X-Presence-Status: cached');
		if (@filemtime($cache_path)<$_SERVER['REQUEST_TIME']-3600) { // fetch and cache API response
			header('X-Presence-Status: recached');
			$res=@file_get_contents('https://api.presence.io/'.$_LW->CONFIG['CREDENTIALS']['PRESENCE']['USERNAME'].'/v1/events/');
			if (!empty($res)) {
				if ($res2=@json_decode($res, true)) {
					if (!empty($res2[0]['eventName']) && !empty($res2[0]['startDateTimeUtc'])) {
						@file_put_contents($cache_path, $res, LOCK_EX);
					};
				};
			};
		};
		if ($json=@file_get_contents($cache_path)) {
			if (!empty($json)) {
				if ($json=@json_decode($json, true)) { // if valid data
					if (!empty($json[0]['eventName']) && !empty($json[0]['startDateTimeUtc'])) { // if valid events
						$feed=$_LW->getNew('feed'); // get a feed object
						foreach($json as $event) { // for each event
							if (!empty($event['eventName']) && !empty($event['startDateTimeUtc']) && !empty($event['organizationUri']) && $event['organizationUri']===$group) { // filter by specified group
								if (!isset($ical)) { // init ical output
									$ical=$feed->createFeed(['title'=>'Presence Events for '.$_LW->setFormatClean(!empty($event['organizationName']) ? $event['organizationName'] : $group)], 'ical'); // create new feed
								};
								foreach($event as $key=>$val) { // sanitize data
									$event[$key]=$_LW->setFormatSanitize($val);
								};
								$arr=[ // format the event
									'summary'=>@$event['eventName'],
									'dtstart'=>@strtotime($event['startDateTimeUtc']),
									'description'=>strip_tags(@$event['description']),
									'url'=>'https://' . $_LW->CONFIG['CREDENTIALS']['PRESENCE']['USERNAME'] . '.presence.io/event/'.@$event['uri'],
									'uid'=>@$event['uri'].'@api.presence.io',
									'location'=>@$event['location'],
									'attach'=>(!empty($event['photoUri']) ? $_LW->CONFIG['CREDENTIALS']['PRESENCE']['IMAGE_PREFIX'].$event['photoUri'] : '')
								];
								foreach($arr as $key=>$val) { // strip empty values
									if (empty($val)) {
										unset($arr[$key]);
									};
								};
								$feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
							};
						};
						if (isset($ical)) {
							die($feed->showFeed($ical, 'ical')); // show the feed
						};
					};
				};
			};
		};
		$_LW->show404(); // fall back on 404
	};
};
exit;

?>