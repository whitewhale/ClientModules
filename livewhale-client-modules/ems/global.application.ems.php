<?php

$_LW->REGISTERED_APPS['ems']=array(
	'title'=>'EMS',
	'handlers'=>array('onSaveSuccess', 'onAfterEdit', 'onOutput', 'onGetFeedDataFilter', 'onChangeDatabaseHost', 'onBeforeLogin'),
	'flags'=>array('no_autoload'),
	'commands'=>array('ems-debug'=>'debug'),
	'custom'=>array( // these settings should all be set in global.config.php, not here
		'wsdl'=>'', // url to EMS WSDL (i.e. https://####.emscloudservice.com/emsapi/service.asmx?wsdl)
		'rest'=>'', // url to EMS REST (i.e. https://####/EmsPlatform/api/v1)
		'username'=>'', // EMS username
		'password'=>'', // EMS password
		'event_types_map'=>array(), // maps EMS event types to LiveWhale event types -- use format: 1=>'LiveWhale Event Type'
		'default_statuses'=>array(), // default statuses (if none specified)
		'default_group_types'=>array(), // default group types (if none specified)
		'default_event_types'=>array(), // default event types (if none specified)
		'cafile'=>'', // specify path to .pem file to enable SSL validation (test with curl -v --cacert file.pem --capath /path/to/certs "https://<server>.emscloudservice.com/")
		'capath'=>'', // specify path to the server's certificate directory for additional certifications in the chain
		'hidden_by_default'=>false, // default to importing live events
		'enable_udfs'=>false, // set to true to capture UDFs on events
		'udf_tags'=>'' // if capturing UDFs, set the name of the UDF cooresponding to tags that should be created/assigned to incoming EMS events
	)
); // configure this module

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
		$opts=array( // set default opts
			'soap_version'=>SOAP_1_2
		);
		if (!empty($_LW->REGISTERED_APPS['ems']['custom']['cafile']) || !empty($_LW->REGISTERED_APPS['ems']['custom']['capath'])) { // enable SSL verification if certificate is specified
			//$opts['cache_wsdl']=WSDL_CACHE_NONE; // uncomment if required by EMS server
			$opts['stream_context']=stream_context_create(
				array(
					'ssl'=>array(
						'verify_peer'=>true,
						'verify_peer_name'=>false,
						'allow_self_signed'=>false,
						'cafile'=>!empty($_LW->REGISTERED_APPS['ems']['custom']['cafile']) ? $_LW->REGISTERED_APPS['ems']['custom']['cafile'] : false,
						'SNI_enabled'=>true,
						'disable_compression'=>true,
						'capath'=>!empty($_LW->REGISTERED_APPS['ems']['custom']['capath']) ? $_LW->REGISTERED_APPS['ems']['custom']['capath'] : false
					)
				)
			);
		};
		try {
			$this->client=new EMSSoapClient($_LW->REGISTERED_APPS['ems']['custom']['wsdl'], $opts); // create client
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
	};
};
return (isset($this->client) && $this->client!==false);
}

public function debug() { // debug EMS connections, such as validating the login credentials after an install
global $_LW;
echo '<h2>Debugging EMS</h2><hr/>';
if ($this->initEMS()) { // if EMS loaded
	$this->client->getStatuses($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS statuses
	echo '<h3>Statuses:</h3><div style="max-height:300px;overflow:scroll;border:1px solid black;"><pre>'.var_export($this->client->statuses, true).'</pre></div>'; // display the statuses
	$this->client->getGroupTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS group types
	echo '<h3>Group Types:</h3><div style="max-height:300px;overflow:scroll;border:1px solid black;"><pre>'.var_export($this->client->group_types, true).'</pre></div>'; // display the group types
	$this->client->getGroups($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS groups
	echo '<h3>Groups:</h3><div style="max-height:300px;overflow:scroll;border:1px solid black;"><pre>'.var_export($this->client->groups, true).'</pre></div>'; // display the groups
	$this->client->getEventTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']); // get the EMS event types
	echo '<h3>Event Types:</h3><div style="max-height:300px;overflow:scroll;border:1px solid black;"><pre>'.var_export($this->client->event_types, true).'</pre></div>'; // display the event types
	//if ($bookings=$this->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS('12-01-2015')), $_LW->toDate(DATE_W3C, $_LW->toTS('12-07-2016')), false, false, array(1, 7), false, false, 14)) { // if there are bookings (status = 1/7, group id = 14)
	//if ($bookings=$this->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS('04-01-2016')), $_LW->toDate(DATE_W3C, $_LW->toTS('12-30-2016')), false, false, array(1, 7), false, false, 364)) { // if there are bookings (status = 1/7, group id = 364)
	if ($bookings=$this->getBookings($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password'], $_LW->toDate(DATE_W3C, $_LW->toTS('01-04-2016')), $_LW->toDate(DATE_W3C, $_LW->toTS('12-30-2016')), false, false, array(1), false, false, 85)) { // if there are bookings (status = 1, group id = 85)
		echo '<h3>Bookings:</h3><div style="max-height:300px;overflow:scroll;border:1px solid black;"><pre>'.var_export($bookings, true).'</pre></div>'; // display the events
	}
	else { // else display any errors
		print_r($this->client->ems_errors);
	};
};
exit;
}

public function getBookings($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false) { // accesses the EMS API GetBookings or GetGroupBookings API call
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
return $this->client->getBookings($username, $password, $start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_types, $group_id); // perform the API call
}

public function getBookingsAsICAL($username, $password, $start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_types=false, $group_id=false) { // formats bookings as ICAL feed (note: do not change parameters or format of parameters -- these affect the hash that sets the per-event uid to track ongoing changes to the same event and preserve native metadata)
global $_LW;
$feed=$_LW->getNew('feed'); // get a feed object
$ical=$feed->createFeed(array('title'=>'EMS Events'), 'ical'); // create new feed
$hostname=@parse_url((!empty($_LW->REGISTERED_APPS['ems']['custom']['wsdl']) ? $_LW->REGISTERED_APPS['ems']['custom']['wsdl'] : (!empty($_LW->REGISTERED_APPS['ems']['custom']['rest']) ? $_LW->REGISTERED_APPS['ems']['custom']['rest'] : '')), PHP_URL_HOST);
if ($bookings=$this->getBookings($username, $password, $start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_types, $group_id)) { // if bookings obtained
	foreach($bookings as $booking) { // for each booking
		$arr=array( // format the event
			'summary'=>$booking['title'],
			'dtstart'=>$booking['date_ts'],
			'dtend'=>(!empty($booking['date2_ts']) ? $booking['date2_ts'] : ''),
			'description'=>'',
			'uid'=>$booking['booking_id'].'@'.$hostname,
			'categories'=>$booking['event_type'],
			'location'=>$booking['location'],
			'X-LIVEWHALE-TYPE'=>'event',
			'X-LIVEWHALE-TIMEZONE'=>@$booking['timezone'],
			'X-LIVEWHALE-CANCELED'=>@$booking['canceled'],
			'X-LIVEWHALE-CONTACT-INFO'=>@$booking['contact_info'],
			'X-EMS-STATUS-ID'=>@$booking['status_id'],
			'X-EMS-EVENT-TYPE-ID'=>@$booking['event_type_id']
		);
		if (@$booking['status_id']==5 || @$booking['status_id']==17) { // if this is a pending event, skip syncing (creation of events and updating if already existing)
			$arr['X-LIVEWHALE-SKIP-SYNC']=1;
		};
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
		foreach($arr as $key=>$val) { // clear empty entries
			if (empty($val)) {
				unset($arr[$key]);
			};
		};
		$feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
	};
};
return $feed->showFeed($ical, 'ical'); // show the feed
}

public function onGetFeedDataFilter($buffer) { // on parsing of a feed
global $_LW;
static $event_types;
if (!empty($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'])) { // if there is an event type map
	if (!empty($buffer) && @$buffer['type']=='ical' && strpos(@$buffer['url'], $_LW->CONFIG['LIVE_URL'].'/ems/')!==false && !empty($buffer['items']['default'])) { // if the feed is an EMS ICAL feed with items
		if ($this->initEMS()) { // if EMS loaded
			if (!isset($this->client->event_types)) { // get the EMS event types
				$this->client->getEventTypes($_LW->REGISTERED_APPS['ems']['custom']['username'], $_LW->REGISTERED_APPS['ems']['custom']['password']);
			};
			if (!isset($event_types)) { // get the LiveWhale event types
				$event_types=array();
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
								if (strtolower($val3['title'])==strtolower($val2)) {
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id])) { // if the EMS event type was found in map
								$new_category=$_LW->setFormatClean($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id]); // format the translated category
								if (in_array($new_category, $event_types)) { // if the translated category is a known LiveWhale event type
									$buffer['items']['default'][$key]['categories'][$key2]=array_search($new_category, $event_types); // translate the EMS event type to the corresponding LiveWhale event type
								};
							};
						};
					};
					if (!empty($val['unknown_categories'])) { // if there are unknown categories
						foreach($val['unknown_categories'] as $key2=>$val2) { // for each unknown category
							$val2=$_LW->setFormatClean($val2);
							$val2_id='';
							foreach($this->client->event_types as $key3=>$val3) { // convert the unknown category to the EMS event type ID
								if (strtolower($val3['title'])==strtolower($val2)) {
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id])) { // if the EMS event type was found in map
								if (!is_array($buffer['items']['default'][$key]['categories'])) { // ensure that there is an array of known categories
									$buffer['items']['default'][$key]['categories']=array();
								};
								$new_category=$_LW->setFormatClean($_LW->REGISTERED_APPS['ems']['custom']['event_types_map'][$val2_id]); // format the translated category
								if (in_array($new_category, $event_types)) { // if the translated category is a known LiveWhale event type
									if (!in_array($new_category, $buffer['items']['default'][$key]['categories'])) { // add it as a known category if not already present
										$buffer['items']['default'][$key]['categories'][]=array_search($new_category, $event_types);
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
	};
};
if ($page=='groups_edit' || $page=='events_subscriptions_edit') { // add CSS for these pages
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/ems%5Cems.css';
};
}

public function onSaveSuccess($type, $id) { // after saving any type
global $_LW;
if ($_LW->page=='groups_edit') { // if on the group editor page
	if ($type=='groups') { // if saving a group
		if ($this->initEMS()) { // if EMS loaded
			$_LW->setCustomFields($type, $id, array('ems_group'=>@$_LW->_POST['ems_group']), array()); // store the value entered for ems_group
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
			$group_selector='<!-- START EMS GROUP --><div id="groups_ems_wrap" class="fields ems"><label class="header" for="groups_ems_group" id="groups_ems_group_label">EMS Group</label><fieldset><select name="ems_group"><option></option>'; // format group selector
			foreach($this->client->groups as $group) {
				$group_selector.='<option value="'.$group['id'].'"'.(@$_LW->_POST['ems_group']==$group['id'] ? ' selected="selected"' : '').'>'.$group['title'].' (ID: '.$group['id'].')</option>';
			};
			$group_selector.='</select></fieldset></div><!-- END EMS GROUP -->';
			$pos=strpos($buffer, '<!-- START METADATA -->')!==false ? 'METADATA' : 'STATUS';
			$buffer=str_replace('<!-- START '.$pos.' -->', $group_selector.'<!-- START '.$pos.' -->', $buffer); // inject the group selector
		};
	};
}
else if ($_LW->page=='events_subscriptions_edit') { // if on the linked calendar editor page
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
if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations')) {
	if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check')<$ts) { // once a month
		touch($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations/last_check'); // mark as cleaned
		if ($res_dirs=@scandir($_LW->INCLUDES_DIR_PATH.'/data/ems/reservations')) { // clear cached reservations not updated within the last 1 month
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
}

}

?>
