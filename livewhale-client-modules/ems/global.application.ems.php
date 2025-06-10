<?php

$_LW->REGISTERED_APPS['ems']=[
	'title'=>'EMS',
	'handlers'=>['onSaveSuccess', 'onAfterEdit', 'onOutput', 'onGetFeedDataFilter', 'onChangeDatabaseHost', 'onBeforeLogin', 'onAfterSync'],
	'flags'=>['no_autoload'],
	'commands'=>['ems-debug'=>'debug'],
	'custom'=>[ // these settings should all be set in global.config.php, not here
		'wsdl'=>'', // url to EMS WSDL (i.e. https://####.emscloudservice.com/emsapi/service.asmx?wsdl)
		'rest'=>'', // url to EMS REST (i.e. https://####/EmsPlatform/api/v1)
		'username'=>'', // EMS username
		'password'=>'', // EMS password
		'event_types_map'=>[], // maps EMS event types to LiveWhale event types -- use format: 1=>'LiveWhale Event Type' (or an array of LiveWhale Event Types)
		'default_statuses'=>[], // default statuses (if none specified)
		'default_group_types'=>[], // default group types (if none specified)
		'default_event_types'=>[], // default event types (if none specified)
		'cafile'=>'', // specify path to .pem file to enable SSL validation (test with curl -v --cacert file.pem --capath /path/to/certs "https://<server>.emscloudservice.com/")
		'capath'=>'', // specify path to the server's certificate directory for additional certifications in the chain
		'hidden_by_default'=>false, // default to importing live events
		'enable_udfs'=>false, // set to true to capture UDFs on events
		'enable_reservation_udfs'=>false, // set to true to capture UDFs on reservations
		'udf_categories'=>'', // if capturing UDFs, set the name of the UDF cooresponding to categories that should be created/assigned to incoming EMS events
		'udf_description'=>'', // if using UDFs as event description, set the name of the UDF cooresponding to description
		'reservation_udf_description'=>'', // if using reservation UDFs as event description, set the name of the reservation UDF cooresponding to description
		'udf_tags'=>'', // if capturing UDFs, set the name of the UDF cooresponding to tags that should be created/assigned to incoming EMS events
		'sync_images'=>false // sync images on an hourly basis for both new and previously imported events
	]
]; // configure this module

class LiveWhaleApplicationEms { // implements EMS-related functionality in LiveWhale
protected $client; // the EMS client

public function initEMS() { // initializes EMS (via SOAP or REST)
global $_LW;
if (!empty($_LW->REGISTERED_APPS['ems']['custom']['wsdl']) && !extension_loaded('soap')) { // require SOAP if using WSDL url
	$_LW->logError('EMS: SOAP extention not loaded', false, true);
	return false;
};
if (!isset($this->client)) { // if client not yet created
	$_LW->REGISTERED_APPS['ems']['custom']['username']=@$_LW->CONFIG['CREDENTIALS']['EMS']['username']; // get EMS username
	$_LW->REGISTERED_APPS['ems']['custom']['password']=@$_LW->CONFIG['CREDENTIALS']['EMS']['password']; // get EMS password
	if (!empty($_LW->REGISTERED_APPS['ems']['custom']['wsdl'])) { // if running in SOAP mode
		if (!class_exists('EMSSoapClient')) { // load the EMS class
			require $_LW->INCLUDES_DIR_PATH.'/client/modules/ems/includes/class.ems_soap.php';
		};
		$opts=[ // set default opts
			'soap_version'=>SOAP_1_2
		];
		if (!empty($_LW->REGISTERED_APPS['ems']['custom']['cafile']) || !empty($_LW->REGISTERED_APPS['ems']['custom']['capath'])) { // enable SSL verification if certificate is specified
			//$opts['cache_wsdl']=WSDL_CACHE_NONE; // uncomment if required by EMS server
			$opts['stream_context']=stream_context_create(
				[
					'ssl'=>[
						'verify_peer'=>true,
						'verify_peer_name'=>false,
						'allow_self_signed'=>false,
						'cafile'=>!empty($_LW->REGISTERED_APPS['ems']['custom']['cafile']) ? $_LW->REGISTERED_APPS['ems']['custom']['cafile'] : false,
						'SNI_enabled'=>true,
						'disable_compression'=>true,
						'capath'=>!empty($_LW->REGISTERED_APPS['ems']['custom']['capath']) ? $_LW->REGISTERED_APPS['ems']['custom']['capath'] : false
					]
				]
			);
		};
		try {
			$this->client=new EMSSoapClient($_LW->REGISTERED_APPS['ems']['custom']['wsdl'], $opts); // create client
			$this->client->__setLocation($_LW->REGISTERED_APPS['ems']['custom']['wsdl']);
			if (!$this->client->validateLogin($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'])) { // validate the login
				$this->client=false;
			};
		}
		catch (Exception $e) {
			$_LW->logError('EMS: '.$e->getMessage(), false, true);
		}
	}
	else if (!empty($_LW->REGISTERED_APPS['ems']['custom']['rest'])) { // else if running in REST mode
		if (!class_exists('EMSRESTClient')) { // load the EMS class
			require $_LW->INCLUDES_DIR_PATH.'/client/modules/ems/includes/class.ems_rest.php';
		};
		$this->client=new EMSRESTClient($_LW->REGISTERED_APPS['ems']['custom']['rest']); // create client
		if (!$this->client->validateLogin($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'])) { // validate the login
			$this->client=false;
		};
	};
};
return (isset($this->client) && $this->client!==false);
}

public function debug() { // debug EMS connections, such as validating the login credentials after an install
global $_LW;
if (!$_LW->isLiveWhaleUser()) {
	die(header('Location: /livewhale/')); // redirect to login page
};
echo '<h1>Debugging EMS</h1>';
if ($this->initEMS()) { // if EMS loaded

	if (!empty($_LW->REGISTERED_APPS['ems']['custom']['rest'])) { // when using REST API
		echo '<h2>Look up a booking</h2>';
		if (!empty($_GET['booking_id'])) { // searching for individual booking 
			echo '<a href="?livewhale=ems-debug">&lt; back to EMS debug home</a><h2>Booking #' . $_GET['booking_id'] . '</h2>';
			if ($response=$this->client->getResponse('/bookings/'.(int)$_GET['booking_id'])) { // get the response
				echo '<pre>'.var_export($response, true).'</pre>';
			} else { // else display any errors
				print_r($this->client->ems_errors);
			};
			if ($response2=$this->client->getResponse('/bookings/'.(int)$_GET['booking_id'].'/userdefinedfields', ['pageSize'=>2000])) { // get UDFs
				echo '<h3>Booking User Defined Fields</h3><pre>'.var_export($response2, true).'</pre>';
			};
			if ($response3=$this->client->getResponse('/reservations/'.(int)$response['reservation']['id'].'/userdefinedfields', ['pageSize'=>2000])) { // get UDFs
				echo '<h3>Reservation User Defined Fields</h3><pre>'.var_export($response3, true).'</pre>';
			};
			echo '<br/><br/><a href="?livewhale=ems-debug">&lt; back to EMS debug home</a>';
			exit;
		} else { // show booking form
			echo '<form method="get"><label>Search for booking ID: <input type="hidden" name="livewhale" value="ems-debug"/><input type="text" name="booking_id" autocomplete="false" data-lpignore="true"/></label> <input type="submit" value="Go"/></form>';
		}

		echo '<h2>Look up a reservation</h2>';
		if (!empty($_GET['reservation_id'])) { // searching for individual booking 
			echo '<a href="?livewhale=ems-debug">&lt; back to EMS debug home</a><h2>Reservation #' . $_GET['reservation_id'] . '</h2>';
			if ($response=$this->client->getReservationByID((int)$_GET['reservation_id'])) { // get the response
				echo '<pre>'.var_export($response, true).'</pre>';
			} else { // else display any errors
				print_r($this->client->ems_errors);
			};
			if ($response=$this->client->getResponse('/reservations/'.(int)$_GET['reservation_id'].'/userdefinedfields', ['pageSize'=>2000])) { // get UDFs
				echo '<h3>Reservation User Defined Fields</h3><pre>'.var_export($response, true).'</pre>';
			};
			if ($response=$this->client->getResponse('/bookings/actions/search', ['pageSize'=>2000], ['reservationIds'=>[(int)$_GET['reservation_id']]])) { // get bookings associated with this reservation
				echo '<h3>Bookings Attached to this Reservation</h3><pre>'.var_export($response, true).'</pre>';
			};
			echo '<br/><br/><a href="?livewhale=ems-debug">&lt; back to EMS debug home</a>';
			exit;
		} else { // show booking form
			echo '<form method="get"><label>Search for reservation ID: <input type="hidden" name="livewhale" value="ems-debug"/><input type="text" name="reservation_id" autocomplete="false" data-lpignore="true"/></label> <input type="submit" value="Go"/></form>';
		}

		if (!empty($_GET['reservation_udf'])) { // Test searching by reservation_udf
			$date_start = (!empty($_GET['date_start']) ? $_GET['date_start'] : '-2 months');
			$date_end = (!empty($_GET['date_end']) ? $_GET['date_end'] : '+2 months');
			echo '<h3>Bookings from Reservation UDF ' . $_GET['reservation_udf'] . '</h3>';

			if ($bookings=$this->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), $_LW->toDate(DATE_W3C, $_LW->toTS($date_end)), false, false, false, false, false, false, false, $_GET['reservation_udf'])) {
				echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
			}
			else { // else display any errors
				echo '<h4>Error</h4><pre>'.var_export($this->client->ems_errors, true).'</pre>';
			};
		};
	}

	$this->client->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS groups

	if (!empty($this->client->groups)) { // if groups obtained
			$group_selector='<h2>Search bookings by group:</h2><!-- START EMS GROUP --><div id="groups_ems_wrap" class="fields ems" style="font-size: 1.5em"><select name="ems_group" onChange="window.document.location.href=this.options[this.selectedIndex].value;" style="font-size: 0.875em"><option value="?livewhale=ems-debug"></option>'; // format group selector
			foreach($this->client->groups as $group) {
				$group_selector.='<option value="?livewhale=ems-debug&group_id='.$group['id'].'"'.(@$_LW->_GET['group_id']==$group['id'] ? ' selected="selected"' : '').'>'.$group['title'].' (ID: '.$group['id'].')</option>';
			};
			$group_selector.='</select></div><!-- END EMS GROUP -->';
		echo $group_selector;
	}
	if (!empty($_GET['group_id'])) { // searching by group

		$date_start = (!empty($_GET['date_start']) ? $_GET['date_start'] : '-6 months');
		$date_end = (!empty($_GET['date_end']) ? $_GET['date_end'] : '+6 months');
		echo '<h3>Bookings from Group ' . $_GET['group_id'] . '</h3>';

		echo 'Date range: <strong>['.$date_start.']</strong> to <strong>['.$date_end.']</strong> ';

		if (!empty($_GET['date_start']) || !empty($_GET['date_end'])) {
			echo '(<a href="?livewhale=ems-debug&group_id='.$_GET['group_id'].'">reset</a>)<br/>';
		} else {
			echo '(<a href="?livewhale=ems-debug&group_id='.$_GET['group_id'].'&date_start=today&date_end=tomorrow">customize</a>)<br/>';
		}
		if (!empty($_GET['bypass_types_statuses'])) { // grab all types/statuses
			echo 'Statuses and types: <strong>all</strong> <a href="?livewhale=ems-debug&group_id='.$_GET['group_id'].'">(show only defaults)</a>';
			if ($bookings=$this->client->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), $_LW->toDate(DATE_W3C, $_LW->toTS($date_end)), false, false, false, false, false, $_GET['group_id'])) {
			echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
			}
			else { // else display any errors
				echo '<h4>Error</h4><pre>'.var_export($this->client->ems_errors, true).'</pre>';
			};
		} else { // use default types/statuses
			echo 'Statuses and types: <strong>defaults</strong> <a href="?livewhale=ems-debug&group_id='.$_GET['group_id'].'&bypass_types_statuses=true">(show all)</a>';
			if ($bookings=$this->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), $_LW->toDate(DATE_W3C, $_LW->toTS($date_end)), false, false, false, false, false, $_GET['group_id'])) {
				echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
			}
			else { // else display any errors
				echo '<h4>Error</h4><pre>'.var_export($this->client->ems_errors, true).'</pre>';
			};
		};
	} else { // if not showing group results
		if (empty($_GET['general'])) { 
			echo '<h2>Check general setup</h2>';
			echo '<a href="?livewhale=ems-debug&general=true">Show statuses, group types, groups, and event types</a>';
		} else {
			echo '<br/><br/><a href="?livewhale=ems-debug">&lt; back to EMS debug home</a>';
			$this->client->getStatuses($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS statuses
			echo '<div style="display:flex; flex-wrap: wrap;">';
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Statuses:</h2><div style="max-height:200px;overflow:scroll;border:1px solid black; padding: 10px; margin-bottom: 15px;"><pre>'.var_export($this->client->statuses, true).'</pre></div>
				<div id="ems-clean-list1" style="max-height:100px;overflow:scroll;border:1px solid black; padding: 10px;"><h3>Statuses</h3><ul>';
			foreach ($this->client->statuses as $res) {
				echo '<li>'.$res['description']. ' (id: '.$res['id'].')</li>';
			};
			echo '</ul></div></div>'; // display the statuses
			$this->client->getGroupTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS group types
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Group Types:</h2><div style="max-height:200px;overflow:scroll;border:1px solid black; padding: 10px; margin-bottom: 15px;"><pre>'.var_export($this->client->group_types, true).'</pre></div>
				<div id="ems-clean-list2" style="max-height:100px;overflow:scroll;border:1px solid black; padding: 10px;"><h3>Group Types</h3><ul>';
			foreach ($this->client->group_types as $res) {
				echo '<li>'.$res['title']. ' (id: '.$res['id'].')</li>';
			};
			echo '</ul></div></div>'; // display the group types	
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Groups:</h2><div style="max-height:200px;overflow:scroll;border:1px solid black; padding: 10px; margin-bottom: 15px;"><pre>'.var_export($this->client->groups, true).'</pre></div>
				<div id="ems-clean-list3" style="max-height:100px;overflow:scroll;border:1px solid black; padding: 10px;"><h3>Groups</h3><ul>';
			foreach ($this->client->groups as $res) {
				echo '<li>'.$res['title']. ' (id: '.$res['id'].')</li>';
			};
			echo '</ul></div></div>'; // display the groups
			$this->client->getEventTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS event types
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Event Types:</h2><div style="max-height:200px;overflow:scroll;border:1px solid black; padding: 10px; margin-bottom: 15px;"><pre>'.var_export($this->client->event_types, true).'</pre></div>
				<div id="ems-clean-list4" style="max-height:100px;overflow:scroll;border:1px solid black; padding: 10px;"><h3>Event Types</h3><ul>';
			foreach ($this->client->event_types as $res) {
				echo '<li>'.$res['title']. ' (id: '.$res['id'].')</li>';
			};
			echo '</ul></div></div>'; // display the event types	
			echo '</div><button id="copyButton">Copy clean lists to clipboard</button>
		    <script>
			        document.getElementById("copyButton").addEventListener("click", function () {
			            // Combine the rich text content of the four divs
			            const div1Content = document.getElementById("ems-clean-list1").innerHTML;
			            const div2Content = document.getElementById("ems-clean-list2").innerHTML;
			            const div3Content = document.getElementById("ems-clean-list3").innerHTML;
			            const div4Content = document.getElementById("ems-clean-list4").innerHTML;
			            const combinedContent = div1Content + div2Content + div3Content + div4Content;

			            // Create a Blob with the combined HTML
			            const blob = new Blob([combinedContent], { type: "text/html" });

			            // Use the Clipboard API to write the content
		                navigator.clipboard.write([
		                    new ClipboardItem({
		                        "text/html": blob,
		                        "text/plain": new Blob([combinedContent], { type: "text/plain" }) // Fallback to plain text
		                    })
		                ]);
			        });
			    </script>';
			echo '<br/><br/><a href="?livewhale=ems-debug">&lt; back to EMS debug home</a>';
		}
	}

} else {
	echo '<h2>EMS connection failed to initialize.</h2>';
};
exit;
}

public function getBookings($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false, $udf=false, $reservation_udfs=false, $booking_udfs=false, $custom_filter=false) { // accesses the EMS API GetBookings or GetGroupBookings API call
global $_LW;
if (!isset($this->client)) { // require the client
	return false;
};
if (empty($statuses)) { // use default statuses if none specified
	$statuses=$_LW->REGISTERED_APPS['ems']['custom']['default_statuses'];
};
if (empty($group_types)) { // use default group types if none specified
	$group_types=$_LW->REGISTERED_APPS['ems']['custom']['default_group_types'];
};
if (empty($event_types)) { // use default event types if none specified
	$event_types=$_LW->REGISTERED_APPS['ems']['custom']['default_event_types'];
};
return $this->client->getBookings($username, $password, $start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_types, $group_id, $udf, $reservation_udfs, $booking_udfs, $custom_filter); // perform the API call
}

public function getBookingsAsICAL($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false, $udf=false, $reservation_udfs=false, $booking_udfs=false, $custom_filter=false) { // formats bookings as ICAL feed (note: do not change parameters or format of parameters -- these affect the hash that sets the per-event uid to track ongoing changes to the same event and preserve native metadata)
global $_LW;
$feed=$_LW->getNew('feed'); // get a feed object
$ical=$feed->createFeed(['title'=>'EMS Events'], 'ical'); // create new feed
$hostname=@parse_url((!empty($_LW->REGISTERED_APPS['ems']['custom']['wsdl']) ? $_LW->REGISTERED_APPS['ems']['custom']['wsdl'] : (!empty($_LW->REGISTERED_APPS['ems']['custom']['rest']) ? $_LW->REGISTERED_APPS['ems']['custom']['rest'] : '')), PHP_URL_HOST);
if ($bookings=$this->getBookings($username, $password, $start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_types, $group_id, $udf, $reservation_udfs, $booking_udfs, $custom_filter)) { // if bookings obtained
	foreach($bookings as $booking) { // for each booking
		$arr=[ // format the event
			'summary'=>$booking['title'],
			'dtstart'=>$booking['date_ts'],
			'dtend'=>(!empty($booking['date2_ts']) ? $booking['date2_ts'] : ''),
			'description'=>'',
			'uid'=>$booking['booking_id'].'@'.$hostname,
			'categories'=>$booking['event_type'],
			'location'=>$booking['location'],
			'X-LIVEWHALE-TYPE'=>'event',
			'X-LIVEWHALE-TIMEZONE'=>(!empty($booking['timezone']) ? $booking['timezone'] : ''),
			'X-LIVEWHALE-CANCELED'=>(!empty($booking['canceled']) ? $booking['canceled'] : ''),
			'X-LIVEWHALE-CONTACT-INFO'=>(!empty($booking['contact_info']) ? $booking['contact_info'] : ''),
			'X-EMS-STATUS-ID'=>(!empty($booking['status_id']) ? $booking['status_id'] : ''),
			'X-EMS-EVENT-TYPE-ID'=>(!empty($booking['event_type_id']) ? $booking['event_type_id'] : '')
		];
		if (!empty($booking['attachment']['id']) && !empty($booking['attachment']['filename']) && !empty($booking['attachment']['data'])) { // if there is an attachment
			$cache_key=hash('md5', $booking['attachment']['data']); // hash the cache key
			$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/ems/attachments/'.$cache_key[0].$cache_key[1].'/'.$cache_key; // get the cache path
			if (!file_exists($cache_path)) {
				if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/attachments')) {
					@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems/attachments');
				};
				if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/attachments/'.$cache_key[0].$cache_key[1])) {
					@mkdir($_LW->INCLUDES_DIR_PATH.'/data/ems/attachments/'.$cache_key[0].$cache_key[1]);
				};
				@file_put_contents($cache_path, $booking['attachment']['filename']."\n".$booking['attachment']['data']); // save the attachment
			};
			if (file_exists($cache_path)) { // add the attachment to the feed
				$arr['X-EMS-ATTACHMENT-ID']=$booking['attachment']['id'];
				$arr['X-EMS-ATTACHMENT-HASH']=$cache_key;
			};
		};
		// if (@$booking['status_id']==5 || @$booking['status_id']==17) { // if this is a pending event, skip syncing (creation of events and updating if already existing)
		// 	$arr['X-LIVEWHALE-SKIP-SYNC']=1;
		// };
		if (!empty($_LW->REGISTERED_APPS['ems']['custom']['hidden_by_default'])) { // if importing hidden events, flag them
			$arr['X-LIVEWHALE-HIDDEN']=1;
		};
		if (!empty($booking['udfs']) && !empty($_LW->REGISTERED_APPS['ems']['custom']['udf_tags']) && !empty($booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_tags']])) { // if assigning UDF values as event tags
			$arr['X-LIVEWHALE-TAGS']=implode('|', $booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_tags']]); // add them to output
		};
		if (!empty($booking['udfs']) && !empty($_LW->REGISTERED_APPS['ems']['custom']['udf_categories']) && !empty($booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_categories']])) { // if assigning UDF values as event categories, implode array
			$arr['categories']=implode('|', $booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_categories']]); // add them to output
		};
		if (!empty($booking['udfs']) && !empty($_LW->REGISTERED_APPS['ems']['custom']['udf_description']) && !empty($booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_description']])) { // if assigning UDF value as event description
			$arr['description']=$booking['udfs'][$_LW->REGISTERED_APPS['ems']['custom']['udf_description']];
		};
		if (!empty($booking['reservation_udfs']) && !empty($_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_description']) && !empty($booking['reservation_udfs'][$_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_description']])) { // if assigning reservation UDF value as event description
			$arr['description']=$booking['reservation_udfs'][$_LW->REGISTERED_APPS['ems']['custom']['reservation_udf_description']];
		};
		if (!empty($booking['contact_info'])) { // add contact info if available
			$arr['X-EMS-CONTACT-INFO']=$booking['contact_info'];
		};
		if (!empty($booking['contact_name'])) { // add contact name if available
			$arr['X-EMS-CONTACT-NAME']=$booking['contact_name'];
		};
		$arr=$_LW->callHandlersByType('application', 'onBeforeEMSFeed', ['buffer'=>$arr, 'booking'=>$booking]); // call handlers
		foreach($arr as $key=>$val) { // clear empty entries
			if (empty($val)) {
				unset($arr[$key]);
			};
		};
		$feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
	};
};
$feed->disable_content_length=true;
return $feed->showFeed($ical, 'ical'); // show the feed
}

public function onGetFeedDataFilter($buffer) { // on parsing of a feed
global $_LW;
static $event_types;
if (!empty($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'])) { // if there is an event type map
	if (!empty($buffer) && @$buffer['type']=='ical' && strpos(@$buffer['url'], $_LW->CONFIG['LIVE_URL'].'/ems')!==false && !empty($buffer['items']['default'])) { // if the feed is an EMS ICAL feed with items
		if ($this->initEMS()) { // if EMS loaded
			if (!isset($this->client->event_types)) { // get the EMS event types
				$this->client->getEventTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']);
			};
			if (!isset($event_types)) { // get the LiveWhale event types
				$event_types=[];
				foreach($_LW->dbo->query('select', 'id, title', 'livewhale_events_categories', false, 'title ASC')->run() as $event_type) {
					$event_types[$event_type['id']]=$_LW->setFormatClean($event_type['title']);
				};
			};
			foreach($buffer['items']['default'] as $key=>$val) { // for each feed item
				if (!empty($val['categories']) || !empty($val['unknown_categories'])) { // if there are categories or unknown categories
					if (!empty($val['categories'])) { // if there are categories
						foreach($val['categories'] as $key2=>$val2) { // for each category
							$val2=$_LW->setFormatClean($val2);
							$val2_id='';
							foreach($this->client->event_types as $key3=>$val3) { // convert the category to the EMS event type ID
								if (strtolower($val3['title'])==strtolower($event_types[$val2])) { // since categories are saved as LWC IDs, check against $event_types map
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id])) { // if the EMS event type was found in map
								$new_categories=$_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id];
								if (!is_array($new_categories)) {
									$new_categories=[$new_categories];
								};
								foreach($new_categories as $val3) { // format the translated categories
									$val3=$_LW->setFormatClean($val3);
									if ($val3==='Open to the Public') {
										$val3=' Open to the Public';
									};
									if (in_array($val3, $event_types)) { // if the translated category is a known LiveWhale event type
										$val4=array_search($val3, $event_types); // translate the EMS event type to the corresponding LiveWhale event type ID
										if (!in_array($val4, $buffer['items']['default'][$key]['categories'])) {
											$buffer['items']['default'][$key]['categories'][]=$val4;
										};
									};
								};
							};
						};
					};
					if (!empty($val['unknown_categories'])) { // if there are unknown categories
						foreach($val['unknown_categories'] as $key2=>$val2) { // for each unknown category
							$val2=$_LW->setFormatClean($val2);
							$val2_id='';
							foreach($this->client->event_types as $key3=>$val3) { // convert the unknown category to the EMS event type ID
								if (strtolower($val3['title'])==strtolower($val2)) { // since unknown_categories are saved as strings, check against string directly
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id])) { // if the EMS event type was found in map
								if (!is_array($buffer['items']['default'][$key]['categories'])) { // ensure that there is an array of known categories
									$buffer['items']['default'][$key]['categories']=[];
								};
								$new_categories=$_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id];
								if (!is_array($new_categories)) {
									$new_categories=[$new_categories];
								};
								foreach($new_categories as $val3) { // format the translated categories
									$val3=$_LW->setFormatClean($val3);
									if ($val3==='Open to the Public') {
										$val3=' Open to the Public';
									};
									if (in_array($val3, $event_types)) { // if the translated category is a known LiveWhale event type
										$val4=array_search($val3, $event_types); // translate the EMS event type to the corresponding LiveWhale event type ID
										if (!in_array($val4, $buffer['items']['default'][$key]['categories'])) {
											$buffer['items']['default'][$key]['categories'][]=$val4;
										};
									};
								};
								unset($buffer['items']['default'][$key]['unknown_categories'][$key2]); // remove the unknown category
							};
						};
					};
				};
			};
		};
	};
};
return $buffer;
}

public function onAfterEdit($type, $page, $id) { // after editor load
global $_LW;
if ($page=='groups_edit') { // if loading data for the group editor form
	if ($this->initEMS()) { // if EMS loaded
		if (empty($_LW->_POST['ems_group'])) { // if loading the editor for the first time (as opposed to a failed submission)
			if (!empty($id)) { // and loading a previously saved group
				if ($fields=$_LW->getCustomFields($type, $id)) { // getCustomFields($type, $id) gets any previously saved custom data for the item of this $type and $id
					foreach($fields as $key=>$val) { // add previously saved data to POST data so it prepopulates in the editor form
						$_LW->_POST[$key]=$val;
					};
				};
			};
		};
		
		$this->client->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get groups
		if (!empty($this->client->groups)) { // if groups obtained
			$selected_ems_groups=(!empty($_LW->_POST['ems_group']) ? (!is_array($_LW->_POST['ems_group']) ? explode(',', $_LW->_POST['ems_group']) : $_LW->_POST['ems_group']) : []);
			$_LW->json['editor']['values']['ems_group']=[]; // init array of form values
			$_LW->json['ems_groups']=[]; // init array for multisuggest
			foreach($this->client->groups as $group) {
				$ems_group=['id'=>$group['id'], 'title'=>$group['title'] . ' (ID: ' . $group['id'] . ')', 'size'=>1];
				$_LW->json['ems_groups'][]=$ems_group; // add ems group to multisuggest options
				if (in_array($group['id'],$selected_ems_groups)) { // if already selected
					$_LW->json['editor']['values']['ems_group'][]=$ems_group; // add to preselected values on multisuggest
				};
			};
		};
		
	};
};
if ($page=='groups_edit' || $page=='events_subscriptions_edit') { // add CSS/JS for these pages
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/ems%5Cems.css';
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/ems%5Cems.js';
};
}

public function onSaveSuccess($type, $id) { // after saving any type
global $_LW;
if ($_LW->page=='groups_edit') { // if on the group editor page
	if ($type=='groups') { // if saving a group
		if ($this->initEMS()) { // if EMS loaded
			$_LW->setCustomFields($type, $id, ['ems_group'=>@$_LW->_POST['ems_group']], []); // store the value entered for ems_group
		};
	};
};
}

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='groups_edit') { // if on the group editor page
	if ($this->initEMS()) { // if EMS loaded
		$this->client->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get groups
		if (!empty($this->client->groups)) { // if groups obtained
			$group_selector='<!-- START EMS GROUP --><div id="groups_ems_wrap" class="fields ems"><h3 class="header">EMS Groups</h3><div class="fieldset"><p>Events from these EMS group(s) will be included in the corresponding Linked Calendar for this LiveWhale group.</p><div class="ems_group_suggest"></div></div></div><!-- END EMS GROUP -->'; // format group selector
			$pos=strpos($buffer, '<!-- START METADATA -->')!==false ? 'METADATA' : 'STATUS';
			$buffer=str_replace('<!-- START '.$pos.' -->', $group_selector.'<!-- START '.$pos.' -->', $buffer); // inject the group selector
		};
	};
}
else if ($_LW->page=='events_subscriptions_edit' && !empty($_SESSION['livewhale']['manage']['gid'])) { // if on the linked calendar editor page
	if ($this->initEMS()) { // if EMS loaded
		$ems_group=$_LW->dbo->query('select', 'livewhale_custom_data.value', 'livewhale_custom_data', 'livewhale_custom_data.type="groups" AND livewhale_custom_data.pid='.(int)$_SESSION['livewhale']['manage']['gid'].' AND livewhale_custom_data.name="ems_group"')->firstRow('value')->run(); // get the EMS group for the current LiveWhale group
		if (!empty($ems_group)) { // get the EMS groups
			$this->client->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']);
		};
		$ems_url='<!-- START EMS URL --><div class="fields ems"><label class="header" for="groups_ems_url" id="groups_ems_url_label">EMS URL</label><fieldset>'.(!empty($ems_group) ? 'To import events from EMS'.(!empty($this->client->groups[$ems_group]) ? ' ('.$this->client->groups[$ems_group]['title'].')' : '').', you may use the following url:<br/><br/><strong id="ems_url">http'.($_LW->hasSSL() ? 's' : '').'://'.$_LW->CONFIG['HTTP_HOST'].$_LW->CONFIG['LIVE_URL'].'/ems/events/group/'.rawurlencode($_SESSION['livewhale']['manage']['grouptitle']).'</strong> <span style="font-size:0.8em;">(<a href="#" id="ems_use_feed">Use this feed</a>)</span><br/><br/><span class="note">To customize your EMS feed, such as pulling from specific event types only, please contact an administrator for assistance.</span>' : 'Your calendar group has not yet been assigned to an EMS group. Please contact an administrator for assistance.').'</fieldset></div><!-- END EMS URL -->';
		$buffer=str_replace('<!-- START CATEGORIES -->', $ems_url.'<!-- START CATEGORIES -->', $buffer); // inject the EMS url
		$buffer=str_replace('<!-- END FOOTER SCRIPTS -->', '<script type="text/javascript">
			$(function() { // on DOM ready
				$(\'#ems_use_feed\').click(function() {
					$(\'#events_subscriptions_url\').val($(\'#ems_url\').text());
					return false;
				});
			});
			</script><!-- END FOOTER SCRIPTS -->', $buffer);
	};
};
return $buffer;
}

public function getGroups($username, $password) { // accesses the EMS API groups API call
global $_LW;
if (!isset($this->client)) { // require the client
	return false;
};
$this->client->getGroups($username, $password); // perform the API call
return @$this->client->groups;
}

public function onChangeDatabaseHost($before_host, $after_host) { // switches hostname for EMS calendars
global $_LW;
$_LW->dbo->sql('UPDATE livewhale_events_subscriptions SET url=REPLACE(url, '.$_LW->escape('://'.$before_host.'/').', '.$_LW->escape('://'.$after_host.'/').') WHERE url LIKE "%/ems/events/%";');
$_LW->dbo->sql('UPDATE livewhale_events SET subscription_id=REPLACE(subscription_id, '.$_LW->escape('@'.$before_host).', '.$_LW->escape('@'.$after_host).') WHERE subscription_id LIKE '.$_LW->escape('%@'.$before_host).';');
}

public function onBeforeLogin() { // on before login
global $_LW;
$ts=strtotime('-1 month'); // set TS for 1 month ago
foreach(['reservations', 'attachments'] as $type) {
	if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations')) {
		if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check')<$ts) { // once a month
			touch($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check'); // mark as cleaned
			if ($res_dirs=@scandir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations')) { // clear cached reservations and attachments not updated within the last 1 month
				foreach($res_dirs as $dir) {
					if ($dir[0]!='.') {
						if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$dir)) {
							if ($files=@scandir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$dir)) {
								foreach($files as $file) {
									if ($file[0]!='.') {
										if ($file_ts=@filemtime($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$dir.'/'.$file)) {
											if ($file_ts<$ts) {
												@unlink($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/'.$dir.'/'.$file);
											};
										};
									};
								};
							};
						};
					};
				};
			};
		};
	};
};
}

public function onAfterSync($type, $subscription_id, $event_id, $mode, $event) { // on linked calendar sync
global $_LW;
if ($type=='events' && !empty($event['X-EMS-ATTACHMENT-ID']) && !empty($event['X-EMS-ATTACHMENT-HASH'])) { // if event is being synced with an attachment
	if ($mode=='update' && empty($_LW->REGISTERED_APPS['ems']['custom']['sync_images'])) { // don't sync images for existing events unless the setting is enabled
		return false;
	};
	$cache_key=$event['X-EMS-ATTACHMENT-HASH']; // get the cache key
	$cache_path=$_LW->INCLUDES_DIR_PATH.'/data/ems/attachments/'.$cache_key[0].$cache_key[1].'/'.$cache_key; // get the cache path
	if ($gid=$_LW->dbo->query('select', 'gid', 'livewhale_events', 'id='.(int)$event_id)->firstRow('gid')->run()) { // if gid for event obtained
		if (file_exists($cache_path)) { // if the attachment exists
			if ($data=@file_get_contents($cache_path)) { // if attachment data obtained
				$pos=strpos($data, "\n");
				$filename=substr($data, 0, $pos); // get the tmp filename
				$content=substr($data, $pos+1); // get the tmp content
				if ($content=@base64_decode($content)) { // if valid tmp content
					$tmp_path=$_LW->INCLUDES_DIR_PATH.'/data/uploads/'.$filename; // set the tmp path
					if (@file_put_contents($tmp_path, $content)) { // if tmp file created
						$hash=hash('md5', $contents); // get hash of image data
						$image_id=$_LW->dbo->query('select', 'id', 'livewhale_images', 'hash='.$_LW->escape($hash))->firstRow('id')->run(); // if the image already exists in the library, use it
						if ($mode=='update' && !empty($image_id)) { // if this is an existing event
							$existing_image_id=$_LW->dbo->query('select', 'id1', 'livewhale_images2any', 'livewhale_images2any.type="events" AND livewhale_images2any.id2='.(int)$event_id)->firstRow('id1')->run(); // get any existing image used by this event
							if (!empty($existing_image_id)) { // if this event has an existing attached image
								if ($existing_image_id==$image_id) { // if the current image from the feed is the same as the previously imported one
									return false; // skip
								}
								else { // else if replacing the existing image with the current image from the feed
									if (!$_LW->dbo->query('select', '1', 'livewhale_images2any', 'livewhale_images2any.type="events" AND livewhale_images2any.id1='.(int)$existing_image_id.' AND livewhale_images2any.id2!='.(int)$event_id)->exists()->run()) { // if the image being replaced is not used by any other events
										$_LW->delete('events', $existing_image_id); // delete it as unused
									};
								};
							};
						};
						if (empty($image_id)) { // if image doesn't already exist
							$image_id=$_LW->create('images', [
								'gid'=>(int)$gid,
								'description'=>$filename,
								'date'=>$_LW->toDate('m/d/Y'),
								'path'=>$tmp_path
							]); // create the image
							if (strpos($_LW->error, 'already exists in the file library')!==false) {
								$image_id=$_LW->save_duplicate_id;
							};
						};
						if (!empty($image_id)) { // if the image was created
							$_LW->update('events', $event_id, [ // attach image to event
								'associated_data'=>[
									'images'=>[
										[
											'id'=>$image_id,
											'caption'=>'',
											'is_thumb'=>1,
											'only_thumb'=>'',
											'full_crop'=>'',
											'full_src_region'=>'',
											'thumb_crop'=>1,
											'thumb_src_region'=>'',
											'is_decoration'=>1
										]
									]
								]
							]);
						};
						@unlink($tmp_path); // delete tmp file
					};
				};
			};
		};
	};
};
}

}

?>