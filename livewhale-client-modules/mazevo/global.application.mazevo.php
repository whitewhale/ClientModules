<?php

$_LW->REGISTERED_APPS['mazevo']=[
	'title'=>'Mazevo',
	'handlers'=>['onSaveSuccess', 'onAfterEdit', 'onOutput', 'onGetFeedDataFilter', 'onChangeDatabaseHost'],
	'flags'=>['no_autoload'],
	'commands'=>['mazevo-debug'=>'debug'],
	'custom'=>[ // these settings should all be set in global.config.php, not here
		'rest'=>'', // url to Mazevo REST endpoint
		'event_types_map'=>[], // maps Mazevo event types to LiveWhale event types -- use format: 1=>'LiveWhale Event Type' (or an array of LiveWhale Event Types)
		'default_statuses'=>[], // default statuses (if none specified)
		'default_event_types'=>[], // default event types (if none specified)
		'hidden_by_default'=>false, // default to importing live events
		'enable_questions'=>false, // fetch questions for events
		'question_categories'=>false, // id of question that indicates event types
		'question_description'=>false, // id of question that indications description
		'question_include_in_feed'=>false // id of question to toggles inclusion in feeds
	]
]; // configure this module

class LiveWhaleApplicationMazevo { // implements Mazevo-related functionality in LiveWhale
protected $client; // the Mazevo client

public function initMazevo() { // initializes Mazevo
global $_LW;
if (!isset($this->client)) { // if client not yet created
	if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['rest'])) { // if REST endpoint configured
		if (!class_exists('MazevoRESTClient')) { // load the Mazevo class
			require $_LW->INCLUDES_DIR_PATH.'/client/modules/mazevo/includes/class.mazevo_rest.php';
		};
		$this->client=new MazevoRESTClient($_LW->REGISTERED_APPS['mazevo']['custom']['rest']); // create client
		if (!$this->client->validateLogin()) { // validate the login
			$this->client=false;
		};
	};
};
return (isset($this->client) && $this->client!==false);
}

public function debug() { // debug Mazevo connections, such as validating the login credentials after an install
global $_LW;
if (!$_LW->isLiveWhaleUser()) {
	die(header('Location: /livewhale/')); // redirect to login page
};
echo '<h1>Debugging Mazevo</h1>';
if ($this->initMazevo()) { // if Mazevo loaded
	if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['rest'])) { // when using REST API
		echo '<h2>Look up a booking</h2>';
		if (!empty($_GET['booking_id'])) { // searching for individual booking 
			echo '<a href="?livewhale=mazevo-debug">&lt; back to Mazevo debug home</a><h2>Booking ' . $_LW->setFormatClean($_GET['booking_id']) . '</h2>';
			if ($response=$this->client->getResponse('/PublicEvent/EventInfo', [], ['eventId'=>$_GET['booking_id']])) { // get the response
				echo '<pre>'.var_export($response, true).'</pre>';
			}
			else { // else display any errors
				print_r($this->client->mazevo_errors);
			};
			echo '<br/><br/><a href="?livewhale=mazevo-debug">&lt; back to Mazevo debug home</a>';
			exit;
		}
		else { // show booking form
			echo '<form method="get"><label>Search for booking ID: <input type="hidden" name="livewhale" value="mazevo-debug"/><input type="text" name="booking_id" autocomplete="false" data-lpignore="true"/></label> <input type="submit" value="Go"/></form>';
		}
	};
	$this->client->getGroups(); // get the Mazevo groups
	if (!empty($this->client->groups)) { // if groups obtained
		$group_selector='<h2>Search bookings by group:</h2><!-- START MAZEVO GROUP --><div id="groups_mazevo_wrap" class="fields mazevo" style="font-size: 1.5em"><select name="mazevo_group" onChange="window.document.location.href=this.options[this.selectedIndex].value;" style="font-size: 0.875em"><option value="?livewhale=mazevo-debug"></option>'; // format group selector
		foreach($this->client->groups as $group) {
			$group_selector.='<option value="?livewhale=mazevo-debug&group_id='.$group['organizationId'].'"'.(@$_LW->_GET['group_id']==$group['organizationId'] ? ' selected="selected"' : '').'>'.$group['title'].' (ID: '.$group['organizationId'].')</option>';
		};
		$group_selector.='</select></div><!-- END MAZEVO GROUP -->';
		echo $group_selector;
	};
	if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['enable_questions'])) { // format question selector
		echo '<h2>Search bookings by question:</h2><!-- START MAZEVO QUESTION --><div id="questions_mazevo_wrap" class="fields mazevo" style="font-size: 1.5em"><select name="mazevo_question" onChange="window.document.location.href=this.options[this.selectedIndex].value;" style="font-size: 0.875em"><option value="?livewhale=mazevo-debug"></option>'.(!empty($_LW->REGISTERED_APPS['mazevo']['custom']['question_categories']) ? '<option value="?livewhale=mazevo-debug&amp;question='.(int)$_LW->REGISTERED_APPS['mazevo']['custom']['question_categories'].'"'.(@$_LW->_GET['question']==$_LW->REGISTERED_APPS['mazevo']['custom']['question_categories'] ? ' selected="selected"' : '').'>'.$_LW->REGISTERED_APPS['mazevo']['custom']['question_categories'].'</option>' : '').(!empty($_LW->REGISTERED_APPS['mazevo']['custom']['question_description']) ? '<option value="?livewhale=mazevo-debug&amp;question='.(int)$_LW->REGISTERED_APPS['mazevo']['custom']['question_description'].'"'.(@$_LW->_GET['question']==$_LW->REGISTERED_APPS['mazevo']['custom']['question_description'] ? ' selected="selected"' : '').'>'.$_LW->REGISTERED_APPS['mazevo']['custom']['question_description'].'</option>' : '').(!empty($_LW->REGISTERED_APPS['mazevo']['custom']['question_include_in_feed']) ? '<option value="?livewhale=mazevo-debug&amp;question='.(int)$_LW->REGISTERED_APPS['mazevo']['custom']['question_include_in_feed'].'"'.(@$_LW->_GET['question']==$_LW->REGISTERED_APPS['mazevo']['custom']['question_include_in_feed'] ? ' selected="selected"' : '').'>'.$_LW->REGISTERED_APPS['mazevo']['custom']['question_include_in_feed'].'</option>' : '').'</select></div><!-- END MAZEVO QUESTION -->';
	};
	if (!empty($_GET['group_id'])) { // searching by group
		$date_start = (!empty($_GET['date_start']) ? $_GET['date_start'] : '-6 months');
		$date_end = (!empty($_GET['date_end']) ? $_GET['date_end'] : '+6 months');
		echo '<h3>Bookings from Group ' . (int)$_GET['group_id'] . '</h3>';
		echo 'Date range: <strong>['.$date_start.']</strong> to <strong>['.$date_end.']</strong> ';
		if (!empty($_GET['date_start']) || !empty($_GET['date_end'])) {
			echo '(<a href="?livewhale=mazevo-debug&group_id='.(int)$_GET['group_id'].'">reset</a>)<br/>';
		}
		else {
			echo '(<a href="?livewhale=mazevo-debug&group_id='.(int)$_GET['group_id'].'&date_start=today&date_end=tomorrow">customize</a>)<br/>';
		};
		if (!empty($_GET['bypass_types_statuses'])) { // grab all types/statuses
			echo 'Statuses and types: <strong>all</strong> <a href="?livewhale=mazevo-debug&group_id='.(int)$_GET['group_id'].'">(show only defaults)</a>';
			if ($bookings=$this->client->getBookings($_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), $_LW->toDate(DATE_W3C, $_LW->toTS($date_end)), [$_GET['group_id']])) {
			echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
			}
			else { // else display any errors
				echo '<h4>Error</h4><pre>'.var_export($this->client->mazevo_errors, true).'</pre>';
			};
		}
		else { // use default types/statuses
			echo 'Statuses and types: <strong>defaults</strong> <a href="?livewhale=mazevo-debug&group_id='.(int)$_GET['group_id'].'&bypass_types_statuses=true">(show all)</a>';
			if ($bookings=$this->getBookings($_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), $_LW->toDate(DATE_W3C, $_LW->toTS($date_end)), [$_GET['group_id']])) {
				echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
			}
			else { // else display any errors
				echo '<h4>Error</h4><pre>'.var_export($this->client->mazevo_errors, true).'</pre>';
			};
		};
	}
	else if (!empty($_GET['question'])) { // else if searching by question
		$date_start = (!empty($_GET['date_start']) ? $_GET['date_start'] : '-6 months');
		$date_end = (!empty($_GET['date_end']) ? $_GET['date_end'] : '+6 months');
		echo '<h3>Bookings from Question ' . (int)$_GET['question'] . '</h3>';
		echo 'Date range: <strong>['.$date_start.']</strong> to <strong>['.$date_end.']</strong> ';
		if (!empty($_GET['date_start']) || !empty($_GET['date_end'])) {
			echo '(<a href="?livewhale=mazevo-debug&question='.(int)$_GET['question'].'">reset</a>)<br/>';
		}
		else {
			echo '(<a href="?livewhale=mazevo-debug&question='.(int)$_GET['question'].'&date_start=today&date_end=tomorrow">customize</a>)<br/>';
		};
		if ($bookings=$this->client->getBookingsByQuestion([], ['start'=>$_LW->toDate(DATE_W3C, $_LW->toTS($date_start)), 'end'=>$_LW->toDate(DATE_W3C, $_LW->toTS($date_end))], $_GET['question'])) {
			echo '<pre>'.var_export($bookings, true).'</pre>'; // display the events
		}
		else { // else display any errors
			echo '<h4>Error</h4><pre>'.var_export($this->client->mazevo_errors, true).'</pre>';
		};
	}
	else { // if not showing group results
		if (empty($_GET['general'])) { 
			echo '<h2>Check general setup</h2>';
			echo '<a href="?livewhale=mazevo-debug&general=true">Show statuses, groups, and event types</a>';
		}
		else {
			echo '<br/><br/><a href="?livewhale=mazevo-debug">&lt; back to Mazevo debug home</a>';
			$this->client->getStatuses(); // get the Mazevo statuses
			echo '<div style="display:flex; flex-wrap: wrap;">';
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Statuses:</h2><div style="max-height:300px;overflow:scroll;border:1px solid black; padding: 10px;"><pre>'.var_export($this->client->statuses, true).'</pre></div></div>'; // display the statuses
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Groups:</h2><div style="max-height:300px;overflow:scroll;border:1px solid black; padding: 10px;"><pre>'.var_export($this->client->groups, true).'</pre></div></div>'; // display the groups
			$this->client->getEventTypes(); // get the Mazevo event types
			echo '<div style="flex: 1 0; padding: 10px;"><h2>Event Types:</h2><div style="max-height:300px;overflow:scroll;border:1px solid black; padding: 10px;"><pre>'.var_export($this->client->event_types, true).'</pre></div></div>'; // display the event types
			echo '</div>';
			echo '<br/><br/><a href="?livewhale=mazevo-debug">&lt; back to Mazevo debug home</a>';
		};
	};

	echo '<h2>Testing combined query:</h2>';
	if ($response=$this->client->getResponse('/PublicEvent/geteventswithquestions', false, ["start"=>"2024-10-01T00:00:00-06:00",
    "end"=>"2024-10-11T00:00:00-06:00"])) { // get the response
		echo '<div style="flex: 1 0; padding: 10px;"><div style="max-height:300px;overflow:scroll;border:1px solid black; padding: 10px;"><pre>'.var_export($response, true).'</pre></div></div>'; // display the event types
	};

}
else {
	echo '<h2>Mazevo connection failed to initialize.</h2>';
};
exit;
}

public function getBookings($start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_id=false) { // accesses the Mazevo API GetBookings or GetGroupBookings API call
global $_LW;
if (!isset($this->client)) { // require the client
	return false;
};
if (empty($statuses)) { // use default statuses if none specified
	$statuses=$_LW->REGISTERED_APPS['mazevo']['custom']['default_statuses'];
};
if (empty($event_types)) { // use default event types if none specified
	$event_types=$_LW->REGISTERED_APPS['mazevo']['custom']['default_event_types'];
};
return $this->client->getBookings($start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_id); // perform the API call
}

public function getBookingsAsICAL($start_date, $end_date, $groups=false, $buildings=false, $statuses=false, $event_types=false, $group_id=false) { // formats bookings as ICAL feed (note: do not change parameters or format of parameters -- these affect the hash that sets the per-event uid to track ongoing changes to the same event and preserve native metadata)
global $_LW;
$feed=$_LW->getNew('feed'); // get a feed object
$ical=$feed->createFeed(['title'=>'Mazevo Events'], 'ical'); // create new feed
$hostname=@parse_url((!empty($_LW->REGISTERED_APPS['mazevo']['custom']['wsdl']) ? $_LW->REGISTERED_APPS['mazevo']['custom']['wsdl'] : (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['rest']) ? $_LW->REGISTERED_APPS['mazevo']['custom']['rest'] : '')), PHP_URL_HOST);
if ($bookings=$this->getBookings($start_date, $end_date, $groups, $buildings, $statuses, $event_types, $group_id)) { // if bookings obtained
	foreach($bookings as $booking) { // for each booking
		$arr=[ // format the event
			'summary'=>$booking['title'],
			'dtstart'=>$booking['date_ts'],
			'dtend'=>(!empty($booking['date2_ts']) ? $booking['date2_ts'] : ''),
			'description'=>(!empty($booking['description']) ? $booking['description'] : ''),
			'uid'=>$booking['booking_id'].'@'.$hostname,
			'categories'=>(!empty($booking['categories']) ? $booking['categories'] : (!empty($booking['event_type']) ? $booking['event_type'] : '')),
			'location'=>(!empty($booking['location']) ? $booking['location'] : ''),
			'X-LIVEWHALE-TYPE'=>'event',
			'X-LIVEWHALE-TIMEZONE'=>(!empty($booking['timezone']) ? $booking['timezone'] : ''),
			'X-LIVEWHALE-CANCELED'=>(!empty($booking['canceled']) ? $booking['canceled'] : ''),
			'X-LIVEWHALE-CONTACT-INFO'=>(!empty($booking['contactName']) ? $booking['contactName'] : ''),
			'X-MAZEVO-STATUS-ID'=>(!empty($booking['status_id']) ? $booking['status_id'] : ''),
			'X-MAZEVO-EVENT-TYPE-ID'=>(!empty($booking['event_type_id']) ? $booking['event_type_id'] : ''),
		];
		/*
		if (@$booking['status_id']==53) { // if this is a pending event, skip syncing (creation of events and updating if already existing)
			$arr['X-LIVEWHALE-SKIP-SYNC']=1;
		};
		*/
		if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['hidden_by_default'])) { // if importing hidden events, flag them
			$arr['X-LIVEWHALE-HIDDEN']=1;
		};
		if (!empty($booking['contact_info'])) { // add contact info if available
			$arr['X-MAZEVO-CONTACT-INFO']=$booking['contactName'];
		};
		if (!empty($booking['contact_name'])) { // add contact name if available
			$arr['X-MAZEVO-CONTACT-NAME']=$booking['contact_name'];
		};
		$arr=$_LW->callHandlersByType('application', 'onBeforeMazevoFeed', ['buffer'=>$arr, 'booking'=>$booking]); // call handlers
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
if (!empty($_LW->REGISTERED_APPS['mazevo']['custom']['event_types_map'])) { // if there is an event type map
	if (!empty($buffer) && @$buffer['type']=='ical' && strpos(@$buffer['url'], $_LW->CONFIG['LIVE_URL'].'/mazevo/')!==false && !empty($buffer['items']['default'])) { // if the feed is a Mazevo ICAL feed with items
		if ($this->initMazevo()) { // if Mazevo loaded
			if (!isset($this->client->event_types)) { // get the Mazevo event types
				$this->client->getEventTypes();
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
							foreach($this->client->event_types as $key3=>$val3) { // convert the category to the Mazevo event type ID
								if (strtolower($val3['title'])==strtolower($event_types[$val2])) { // since categories are saved as LWC IDs, check against $event_types map
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['mazevo']['custom']['event_types_map'][$val2_id])) { // if the Mazevo event type was found in map
								$new_categories=$_LW->REGISTERED_APPS['mazevo']['custom']['event_types_map'][$val2_id];
								if (!is_array($new_categories)) {
									$new_categories=[$new_categories];
								};
								foreach($new_categories as $val3) { // format the translated categories
									$val3=$_LW->setFormatClean($val3);
									if ($val3==='Open to the Public') {
										$val3=' Open to the Public';
									};
									if (in_array($val3, $event_types)) { // if the translated category is a known LiveWhale event type
										$val4=array_search($val3, $event_types); // translate the Mazevo event type to the corresponding LiveWhale event type ID
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
							foreach($this->client->event_types as $key3=>$val3) { // convert the unknown category to the Mazevo event type ID
								if (strtolower($val3['title'])==strtolower($val2)) { // since unknown_categories are saved as strings, check against string directly
									$val2_id=$val3['id'];
									break;
								};
							};
							if (!empty($val2_id) && isset($_LW->REGISTERED_APPS['mazevo']['custom']['event_types_map'][$val2_id])) { // if the Mazevo event type was found in map
								if (!is_array($buffer['items']['default'][$key]['categories'])) { // ensure that there is an array of known categories
									$buffer['items']['default'][$key]['categories']=[];
								};
								$new_categories=$_LW->REGISTERED_APPS['mazevo']['custom']['event_types_map'][$val2_id];
								if (!is_array($new_categories)) {
									$new_categories=[$new_categories];
								};
								foreach($new_categories as $val3) { // format the translated categories
									$val3=$_LW->setFormatClean($val3);
									if ($val3==='Open to the Public') {
										$val3=' Open to the Public';
									};
									if (in_array($val3, $event_types)) { // if the translated category is a known LiveWhale event type
										$val4=array_search($val3, $event_types); // translate the Mazevo event type to the corresponding LiveWhale event type ID
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
	if ($this->initMazevo()) { // if Mazevo loaded
		if (empty($_LW->_POST['mazevo_group'])) { // if loading the editor for the first time (as opposed to a failed submission)
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
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/mazevo%5Cmazevo.css';
};
}

public function onSaveSuccess($type, $id) { // after saving any type
global $_LW;
if ($_LW->page=='groups_edit') { // if on the group editor page
	if ($type=='groups') { // if saving a group
		if ($this->initMazevo()) { // if Mazevo loaded
			$_LW->setCustomFields($type, $id, ['mazevo_group'=>@$_LW->_POST['mazevo_group']], []); // store the value entered for mazevo_group
		};
	};
};
}

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='groups_edit') { // if on the group editor page
	if ($this->initMazevo()) { // if Mazevo loaded
		$this->client->getGroups(); // get groups
		if (!empty($this->client->groups)) { // if groups obtained
			$group_selector='<!-- START MAZEVO GROUP --><div id="groups_mazevo_wrap" class="fields mazevo"><label class="header" for="groups_mazevo_group" id="groups_mazevo_group_label">Mazevo Group</label><fieldset><select name="mazevo_group"><option></option>'; // format group selector
			foreach($this->client->groups as $group) {
				$group_selector.='<option value="'.$group['organizationId'].'"'.(@$_LW->_POST['mazevo_group']==$group['organizationId'] ? ' selected="selected"' : '').'>'.$group['title'].' (ID: '.$group['organizationId'].')</option>';
			};
			$group_selector.='</select></fieldset></div><!-- END MAZEVO GROUP -->';
			$pos=strpos($buffer, '<!-- START METADATA -->')!==false ? 'METADATA' : 'STATUS';
			$buffer=str_replace('<!-- START '.$pos.' -->', $group_selector.'<!-- START '.$pos.' -->', $buffer); // inject the group selector
		};
	};
}
else if ($_LW->page=='events_subscriptions_edit') { // if on the linked calendar editor page
	if ($this->initMazevo()) { // if Mazevo loaded
		$mazevo_group=$_LW->dbo->query('select', 'livewhale_custom_data.value', 'livewhale_custom_data', 'livewhale_custom_data.type="groups" AND livewhale_custom_data.pid='.(int)$_SESSION['livewhale']['manage']['gid'].' AND livewhale_custom_data.name="mazevo_group"')->firstRow('value')->run(); // get the Mazevo group for the current LiveWhale group
		if (!empty($mazevo_group)) { // get the Mazevo groups
			$this->client->getGroups();
		};
		$mazevo_url='<!-- START MAZEVO URL --><div class="fields mazevo"><label class="header" for="groups_mazevo_url" id="groups_mazevo_url_label">Mazevo URL</label><fieldset>'.(!empty($mazevo_group) ? 'To import events from Mazevo'.(!empty($this->client->groups[$mazevo_group]) ? ' ('.$this->client->groups[$mazevo_group]['title'].')' : '').', you may use the following url:<br/><br/><strong id="mazevo_url">http'.($_LW->hasSSL() ? 's' : '').'://'.$_LW->CONFIG['HTTP_HOST'].$_LW->CONFIG['LIVE_URL'].'/mazevo/events/group/'.rawurlencode($_SESSION['livewhale']['manage']['grouptitle']).'</strong> <span style="font-size:0.8em;">(<a href="#" id="mazevo_use_feed">Use this feed</a>)</span><br/><br/><span class="note">To customize your Mazevo feed, such as pulling from specific event types only, please contact an administrator for assistance.</span>' : 'Your calendar group has not yet been assigned to an Mazevo group. Please contact an administrator for assistance.').'</fieldset></div><!-- END MAZEVO URL -->';
		$buffer=str_replace('<!-- START CATEGORIES -->', $mazevo_url.'<!-- START CATEGORIES -->', $buffer); // inject the Mazevo url
		$buffer=str_replace('<!-- END FOOTER SCRIPTS -->', '<script type="text/javascript">
			$(function() { // on DOM ready
				$(\'#mazevo_use_feed\').click(function() {
					$(\'#events_subscriptions_url\').val($(\'#mazevo_url\').text());
					return false;
				});
			});
			</script><!-- END FOOTER SCRIPTS -->', $buffer);
	};
};
return $buffer;
}

public function getGroups() { // accesses the Mazevo API groups API call
global $_LW;
if (!isset($this->client)) { // require the client
	return false;
};
$this->client->getGroups(); // perform the API call
return @$this->client->groups;
}

public function onChangeDatabaseHost($before_host, $after_host) { // switches hostname for Mazevo calendars
global $_LW;
$_LW->dbo->sql('UPDATE livewhale_events_subscriptions SET url=REPLACE(url, '.$_LW->escape('://'.$before_host.'/').', '.$_LW->escape('://'.$after_host.'/').') WHERE url LIKE "%/mazevo/events/%";');
$_LW->dbo->sql('UPDATE livewhale_events SET subscription_id=REPLACE(subscription_id, '.$_LW->escape('@'.$before_host).', '.$_LW->escape('@'.$after_host).') WHERE subscription_id LIKE '.$_LW->escape('%@'.$before_host).';');
}

}

?>