<?php

// LiveURL plugin for Localist API requests.

//if (!empty($LIVE_URL['REQUEST'])) { // if valid request
	//$_LW->logDebug('localist: valid request');
	require $LIVE_URL['DIR'].'/livewhale.php'; // load CMS
	if ($res=$_LW->getUrl('https://calendar.college.harvard.edu/api/2/events/?days=30')) { // fetch API response
		//$_LW->logDebug($res);
		if ($res=@json_decode($res, true)) { // if valid data
			if (isset($res['events'])) {
				//$_LW->logDebug('localist: events exist');
				$feed=$_LW->getNew('feed'); // get a feed object
				$ical=$feed->createFeed(array('title'=>'Localist Events'), 'ical'); // create new feed
				foreach($res['events'] as $event) { // for each event
					$event=$event['event'];
					foreach($event as $key=>$val) { // sanitize data
						$event[$key]=$_LW->setFormatSanitize($val);
					};
					$arr=array( // format the event
						'title'=>@$event['title'],
						'dtstart'=>@strtotime($event['first_date']),
						'description'=>@$event['description'],
						'url'=>@$event['localist_url'],
						'uid'=>@$event['id'].'@api.localist.io',
						'location'=>@$event['location'],
						'attach'=>@$event['photo_url']
					);
					foreach($arr as $key=>$val) { // strip empty values
						if (empty($val)) {
							unset($arr[$key]);
						};
					};
					$feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
				};
				die($feed->showFeed($ical, 'ical')); // show the feed
			};
		};
	};
	$_LW->show404(); // fall back on 404
//};
exit;

?>