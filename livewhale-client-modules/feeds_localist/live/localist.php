<?php

// LiveURL plugin for Localist API requests.


if (!empty($LIVE_URL['REQUEST'])) { // if valid request
  require $LIVE_URL['DIR'].'/livewhale.php'; // load CMS
  if !empty($_LW->CONFIG['CREDENTIALS']['LOCALIST']['URL']) { // if there is a valid configuration
	// if (substr($_LW->CONFIG['CREDENTIALS']['LOCALIST']['IMAGE_PREFIX'], -1, 1)!='/') {
	// 	$_LW->CONFIG['CREDENTIALS']['LOCALIST']['IMAGE_PREFIX'].='/';
	// };
	// $group=$LIVE_URL['REQUEST'][0];
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/localist')) {
	  @mkdir($_LW->INCLUDES_DIR_PATH.'/data/localist');
	};
	$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/localist/'.rawurlencode($_LW->CONFIG['CREDENTIALS']['LOCALIST']['URL']).'.json'; // set cache path
	header('X-Localist-Status: cached');
	if (@filemtime($cache_path)<$_SERVER['REQUEST_TIME']-3600) { // fetch and cache API response
	  header('X-Localist-Status: recached');
	  $res=$_LW->getUrl('https://'.$_LW->CONFIG['CREDENTIALS']['LOCALIST']['URL'].'/api/2/events/?days=30');
	  if (!empty($res)) {
		if ($res2=@json_decode($res, true)) {
		  if (!empty($res2[0]['title']) && !empty($res2[0]['first_date'])) {
			@file_put_contents($cache_path, $res, LOCK_EX);
		  };
		};
	  };
	};
	if ($json=@file_get_contents($cache_path)) {
	  if (!empty($json)) {
		if ($json=@json_decode($json, true)) { // if valid data
		  if (!empty($json[0]['title']) && !empty($json[0]['first_date'])) { // if valid events
			$feed=$_LW->getNew('feed'); // get a feed object
			foreach($json as $event) { // for each event
			  if (!isset($ical)) { // init ical output
				$ical=$feed->createFeed(['title'=>'Localist Events, 'ical'); // create new feed
			  };
			  foreach($event as $key=>$val) { // sanitize data
				$event[$key]=$_LW->setFormatSanitize($val);
			  };
			  $arr=[ // format the event
				'title'=>@$event['title'],
				'dtstart'=>@strtotime($event['first_date']),
				'description'=>@$event['description'],
				// 'url'=>'https://' . $_LW->CONFIG['CREDENTIALS']['LOCALIST']['USERNAME'] . '.localist.io/event/'.@$event['uri'],
				'uid'=>@$event['id'].'@api.localist.io',
				'location'=>@$event['location'],
				'attach'=>@$event['photo_url']
			  ];
			  foreach($arr as $key=>$val) { // strip empty values
				if (empty($val)) {
				  unset($arr[$key]);
				};
			  };
			  $feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
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