<?php

$_LW->REGISTERED_APPS['salesforce']=[
	'title'=>'Salesforce',
	'handlers'=>['onChangeDatabaseHost'],
	'flags'=>['no_autoload'],
	'commands'=>['salesforce-debug'=>'debug'],
	'custom'=>[ // set in config.php:
		'api_base_url'=>'', // url to Salesforce REST API
		'username'=>'', // Salesforce username
		'password'=>'', // Salesforce password
		'client_id'=>'', // Salesforce client ID
		'client_secret'=>'' // Salesforce client secret
	]
]; // configure this module

class LiveWhaleApplicationSalesforce { // implements Salesforce-related functionality in LiveWhale

public function initSalesforce() { // initializes Salesforce
global $_LW;
static $status;
if (isset($status)) { // return cached response
	return $status;
};
$status=false;
foreach(['api_base_url', 'username', 'password', 'client_id', 'client_secret'] as $key) { // get authentication values
	$_LW->REGISTERED_APPS['salesforce']['custom'][$key]=@$_LW->CONFIG['CREDENTIALS']['SALESFORCE'][strtoupper($key)];
};
if (!empty($_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url']) && substr($_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'], -1, 1)==='/') { // trim trailing slash from api_base_url
	$_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url']=substr($_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'], 0, -1);
};
if ($this->isAuthenticated()) { // if authenticated
	$status=true; // flag as authenticated
};
return $status;
}

protected function isAuthenticated() { // attempts an API authentication
global $_LW;
foreach(['api_base_url', 'username', 'password', 'client_id', 'client_secret'] as $key) { // validate configuration
	if (empty($_LW->REGISTERED_APPS['salesforce']['custom'][$key])) {
		return false;
	};
};
$this->token=$_LW->getVariable('salesforce_access_token'); // get a cached token
if (empty($this->token)) { // if no or invalid token
	$this->token=$this->getAccessToken(); // get a new one
	if (!empty($this->token)) { // if obtained
		$_LW->setVariable('salesforce_access_token', $this->token, 0, true); // cache the token
	};
};
return (!empty($this->token) ? $this->token : false); // return true if valid token
}

protected function getAccessToken() { // gets a Salesforce access token
global $_LW;
$json=$_LW->getUrl('https://login.salesforce.com/services/oauth2/token', true, [
	'grant_type'=>'password',
	'client_id'=>$_LW->REGISTERED_APPS['salesforce']['custom']['client_id'],
	'client_secret'=>$_LW->REGISTERED_APPS['salesforce']['custom']['client_secret'],
	'username'=>$_LW->REGISTERED_APPS['salesforce']['custom']['username'],
	'password'=>$_LW->REGISTERED_APPS['salesforce']['custom']['password']
]); // fetch token
if ($json=@json_decode($json, true)) { // if valid response
	if (!empty($json['error'])) { // if error returned by endpoint
		header('X-Salesforce-Error: Failed to obtain access token ('.$json['error'].')'); // report error
	}
	else if (!empty($json['access_token'])) { // else return access token
		return $json['access_token'];
	};
}
else { // else give error
	header('X-Salesforce-Error: Failed to obtain access token (HTTP '.$_LW->last_code.')');
};
return false;
}

public function getEventsAsICAL($type, $params) { // fetches events by type and query parameters
global $_LW;
if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]) && !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['object_name']) && !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']) &&  !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']['title']) && ((!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']['date']) && !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']['time'])) || !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']['datetime'])) &&  !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']['uid'])) {
	$fields=array_unique(array_values($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields']));
	$key=array_search('', $fields);
	if ($key!==false) {
		unset($fields[$key]);
	};
	$mappings=[];
	if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['mappings']) && is_array($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['mappings'])) { // if there are mappings
		foreach($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['mappings'] as $key=>$val) { // for each mapping
			if (!empty($params[$key])) { // if there is a cooresponding param
				if (is_scalar($params[$key])) { // add it if single value
					$mappings[]=$val.($key=='start_date' ? '>' : ($key=='end_date' ? '<' : '=')).((!in_array($params[$key], ['true', 'false']) && !preg_match('~^[0-9]+$~', $params[$key]) && !preg_match('~^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$~', $params[$key])) ? (strtolower($params[$key])=='null' ? '""' : $_LW->escape($params[$key])) : $params[$key]);
				}
				else { // else use OR for multiple values
					$tmp=[];
					foreach($params[$key] as $val2) {
						$tmp[]=$val.'='.((!in_array($val2, ['true', 'false']) && !preg_match('~^[0-9]+$~', $val2) && !preg_match('~^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$~', $val2)) ? $_LW->escape($val2) : $val2);
					};
					$mappings[]='('.implode(' OR ', $tmp).')';
				};
				
			};
		};
	};
	$where=[];
	if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters']) || !empty($mappings)) { // construct where clause
		if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters'])) {
			$where[]=$_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters'];
		};
		if (!empty($mappings)) {
			$where=array_merge($where, $mappings);
		};
	};
	$where=implode(' AND ', $where);
	$order_by=(!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['order_by']) ? ' ORDER BY ' . $_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['order_by'] : ''); 
	$query=str_replace(['%2C', '%3D', '%21', '%2B', '%22', '%27', '%3E', '%3C'], [',', '=', '!', '+', '\'', '\'', '>', '<'], urlencode('SELECT '.implode(',', $fields).' FROM '.$_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['object_name'].(!empty($where) ? ' WHERE '.$where  : '').$order_by)); // build query
	// $_LW->logDebug('Salesforce SOQL query = ' . $query);
	header('X-Salesforce-Query: '.$query);
	if ($json=$_LW->getUrl($_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'].'/query/?q='.$query, true, false, [CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token]])) { // fetch events from API
		if ($json=@json_decode($json, true)) { // if valid response
			if (empty($json['error'])) { // if no errors
				$feed=$_LW->getNew('feed'); // get a feed object
				$ical=$feed->createFeed(['title'=>'Salesforce Events'], 'ical'); // create new feed
				if (!empty($json['records'])) { // if records obtained
					if (!isset($event_types)) { // get the LiveWhale event types
						$event_types=[];
						foreach($_LW->dbo->query('select', 'id, title', 'livewhale_events_categories', false, 'title ASC')->run() as $event_type) {
							$event_types[$event_type['id']]=$_LW->setFormatClean($event_type['title']);
						};
					};
					foreach($json['records'] as $record) { // for each record
						$event=[];
						foreach($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields'] as $key=>$val) { // convert record to event
							if (!empty($record[$val]) && is_scalar($record[$val])) {
								$event[$key]=$_LW->setFormatSanitize($record[$val]);
							};
						};
						if (!empty($event['title']) && !empty($event['uid']) && ((!empty($event['date']) && !empty($event['time'])) || !empty($event['datetime']))) { // if valid event
							if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['ignore_utc'])) { // if ignoring UTC timezone
								foreach(['time', 'end_time', 'datetime', 'end_datetime'] as $key) { // strip Z from times
									if (!empty($event[$key]) && substr($event[$key], -1, 1)==='Z') {
										$event[$key]=substr($event[$key], 0, -1);
									};
								};
							};
							// replace Salesforce event types with configured LW event types
							if (!empty($event['categories']) && isset($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['event_types'][$event['categories']])) { // if the Salesforce event type was found in map
								$new_categories=$_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['event_types'][$event['categories']];
								if (!is_array($new_categories)) {
									$new_categories=[$new_categories];
								};
								$new_val='';
								foreach($new_categories as $val2) { // format the translated categories
									$val2=$_LW->setFormatClean($val2);
									if ($val2==='Open to the Public') {
										$val2=' Open to the Public';
									};
									if (in_array($val2, $event_types)) { // if the translated category is a known LiveWhale event type
										$new_val.=(!empty($new_val) ? '|' : '').$val2; // if multiples, separate with |
									};
								};
								$event['categories']=$new_val;
							};

							// translate Salesforce Timezone "(GMT-07:00) Pacific Daylight Time (America/Los_Angeles)" to "America/Los_Angeles"
							if (!empty($event['timezone'])) {
								$matches=[];
								preg_match('~\(([^\)]+?)\)\s*?$~', $event['timezone'], $matches);
								if (!empty($matches[1])) {
									$event['timezone'] = $matches[1]; // use final () value for timezone
								};
								if ($event['timezone']=='Europe/GMT') { // fix invalid TZ
									$event['timezone']='Europe/London';
								};
							};

							$arr=[ // format the event for ICAL
								'summary'=>$event['title'],
								'dtstart'=>$_LW->toTS(!empty($event['datetime']) ? $event['datetime'] : (!empty($event['time']) ? $event['time'].' ' : '').$event['date'], (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['use_utc']) ? 'UTC' : false)),
								'dtend'=>((!empty($event['end_date']) || !empty($event['end_datetime'])) ? $_LW->toTS(!empty($event['end_datetime']) ? $event['end_datetime'] :  (!empty($event['end_time']) ? $event['end_time'].' ' : '').$event['end_date'], (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['use_utc']) ? 'UTC' : false)) : ''),
								'description'=>(!empty($event['description']) ? $event['description'] : ''),
								'uid'=>$event['uid'],
								'categories'=>(!empty($event['categories']) ? $event['categories'] : ''),
								'location'=>(!empty($event['location']) ? $event['location'] : ''),
								'X-LIVEWHALE-TYPE'=>'event'
							];
							if (!empty($event['tags'])) {
								$arr['X-LIVEWHALE-TAGS']=$event['tags'];
							};
							if (empty($event['time'])) {
								$arr['X-LIVEWHALE-IS-ALL-DAY']=1;
							};
							if (!empty($event['timezone'])) {
								$arr['X-LIVEWHALE-TIMEZONE']=$event['timezone'];
							};
							foreach($event as $key=>$val) {
								if (strpos($key, 'custom_')===0) {
									$arr['X-LIVEWHALE-'.str_replace('_', '-', strtoupper($key))]=$val;
								};
							};

							$feed->addFeedItem($ical, $arr, 'ical'); // add event to feed
						};
					};
				};
				$feed->disable_content_length=true;
				return $feed->showFeed($ical, 'ical'); // show the feed
			}
			else { // else if error returned by endpoint
				header('X-Salesforce-Error: Failed to obtain event results ('.$json['error'].')'); // report error
			};
		};
	}
	else { //  else if bad request or token expired
		if ($_LW->last_code===401) { // if token expired
			$_LW->removeVariable('salesforce_access_token', true); // remove the token
		};
		header('X-Salesforce-Error: Failed to obtain event results ('.$_LW->last_code.')'); // report error
	};
}
else { // else if invalid  type
	header('X-Salesforce-Error: Invalid type ('.$type.')'); // report error
};
return false; // default to returning false in case of error so we issue a 404
}

public function debug() { // debug Salesforce connections
global $_LW;
if (!$_LW->isLiveWhaleUser()) {
	die(header('Location: /livewhale/')); // redirect to login page
};
echo '<div style="max-width: 1400px; margin: 1em auto; padding: 1em;">
<style>
label {display: block; margin: 0.25em 0;}
</style>
<h1>Debugging Salesforce</h1>';
if ($this->initSalesforce()) { // if Salesforce loaded


	echo '<h3>Example Queries:</h3><p>Click a query name to copy the full syntax into the form, or build your own using Salesforce Object Query Language (<a href="https://developer.salesforce.com/docs/atlas.en-us.240.0.soql_sosl.meta/soql_sosl/sforce_api_calls_soql.htm">SOQL documentation</a>).</p>';
	$queries=[];

	foreach ($_LW->CONFIG['SALESFORCE']['OBJECTS'] as $type=>$object) {

		$queries['All ' . $type . ' fields']='FIELDS(ALL) FROM '.$object['object_name'].' LIMIT 200';

		$fields=array_unique(array_filter(array_values($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['fields'])));
		$where=[];
		if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters']) || !empty($mappings)) { // construct where clause
			if (!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters'])) {
				$where[]=$_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['default_filters'];
			};
			if (!empty($mappings)) {
				$where=array_merge($where, $mappings);
			};
		};
		$where=implode(' AND ', $where);
		$order_by=(!empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['order_by']) ? ' ORDER BY ' . $_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['order_by'] : ''); 
		$query=str_replace(['%2C', '%3D', '%21', '%2B', '%22', '%27', '%3E', '%3C'], [',', '=', '!', '+', '\'', '\'', '>', '<'], urlencode(implode(', ', $fields).' FROM '.$_LW->CONFIG['SALESFORCE']['OBJECTS'][$type]['object_name'].(!empty($where) ? ' WHERE '.$where  : '').$order_by)); // build query

		$queries['Default ' . $type . ' request']=$query;

	}

	echo '<ul class="sample_queries">';
	foreach ($queries as $name => $query) {
		echo '<li><a href="#" class="example_query" data-query="' . $query . '"><strong>' . $name . '</strong></a> – ' . ((strlen($query) > 50) ? (substr($query,0,50).'...') : $query) . '</li>';
	}
	echo '</ul>';

	echo '<br/><form method="get"><input type="hidden" name="livewhale" value="salesforce-debug"/>';
	echo '<label for="query" style="font-size: 120%; font-weight: bold;">Salesforce Query:</label>
	SELECT <textarea id="query" name="query" style="width: 800px; height: 120px; margin: 0 0 0.5em; vertical-align: top;">' . (!empty($_GET['query']) ? $_GET['query'] : '') . '</textarea>';
	echo '<br/><input type="submit" value="Run Query"/></form>';


	if (!empty($_GET['query'])) {

		$_GET['query'] = str_replace('ORDERBY','ORDER BY',$_GET['query']); // get past WAF

		echo '<!-- <h2>Running: SELECT '.$_GET['query'].'</h2> -->';

		if ($json=$_LW->getUrl($_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'].'/query/?q=SELECT+'.urlencode($_GET['query']), true, false, [CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token]], true)) { // fetch events from API

			echo '<h2>Results: SELECT '.$_GET['query'].'</h2>';

			$json_decode = json_decode($json);

			echo '<textarea style="width: 100%; height: 800px">';
			print_r($json_decode);
			echo '</textarea>';
		}
		//  else {
		// 	echo 'Query failed: ' . $_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'].'/query/?q=SELECT+'.urlencode($_GET['query']);
		// 	echo '<br><br>To test from command line:<br/>
		// 	<code>curl "'.$_LW->REGISTERED_APPS['salesforce']['custom']['api_base_url'].'/query/?q=SELECT+'.urlencode($_GET['query']).'" -H "Authorization: Bearer ' . $this->token . '"</code>';
		// }

	}

	echo "<!-- load jQuery -->
	<script src=\"/live/resource/js/livewhale/thirdparty/backend.js\"></script>
	<script>
	function urldecode(str) {
	   return decodeURIComponent((str+'').replace(/\+/g, '%20'));
	}

	$('.example_query').on('click',function(e) {
		e.preventDefault();
		var query = $(this).attr('data-query');
		$('textarea#query').val(urldecode(query));
	});</script>
	<style>
	.sample_queries li {margin: 0.5em 0;}
	</style>";
	



}
else {
	echo '<h2>Salesforce connection failed to initialize.</h2>';
};
echo '</div>';
exit;
}

public function onChangeDatabaseHost($before_host, $after_host) { // switches hostname for Salesforce calendars
global $_LW;
$_LW->dbo->sql('UPDATE livewhale_events_subscriptions SET url=REPLACE(url, '.$_LW->escape('://'.$before_host.'/').', '.$_LW->escape('://'.$after_host.'/').') WHERE url LIKE "%/salesforce/%";');
$_LW->dbo->sql('UPDATE livewhale_events SET subscription_id=REPLACE(subscription_id, '.$_LW->escape('@'.$before_host).', '.$_LW->escape('@'.$after_host).') WHERE subscription_id LIKE '.$_LW->escape('%@'.$before_host).';');
}

}

?>