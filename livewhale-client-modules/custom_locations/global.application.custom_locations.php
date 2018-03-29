<?php

$_LW->REGISTERED_APPS['custom_locations'] = array( // configure this application module
	'title' => 'Custom Locations',
	'handlers' => array('onSaveSuccess', 'onAfterEdit', 'onOutput', 'onBeforeSync')
);

class LiveWhaleApplicationCustomLocations {

public function onAfterEdit($type, $page, $id) { // after editor loads
global $_LW;
if ($page=='places_edit') { // if loading data for the location editor form
	if (!empty($_LW->is_first_load) && !empty($id)) { // if loading the editor for the first time for an existing item
		if ($fields=$_LW->getCustomFields($type, $id)) { // getCustomFields($type, $id) gets any previously saved custom data for the item of this $type and $id
			foreach($fields as $key=>$val) { // add previously saved data to POST data so it prepopulates in the editor form
				$_LW->_POST[$key]=$val;
			};
		};
	};
};
}

public function onSaveSuccess($type, $id) { // after saving an item
global $_LW;
if ($type=='places') { // if saving a location
	$_LW->setCustomFields($type, $id, array('short_name'=>@$_LW->_POST['short_name']), array()); // store the value
};
}

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='places_edit') { // if on the location editor page
	$new_field=$_LW->displayCustomField('text', 'short_name', @$_LW->_POST['short_name'], false); // create the new element
	$buffer=str_replace('<!-- START LOCATION -->', '<!-- START SHORT_NAME -->
		<div class="fields short_name">
			<label class="header">Short Name (optional)</label>
			<fieldset>
				'.$new_field.'
			<div class="note">This field is checked against incoming event feeds for improved location matching.</div>
			</fieldset>
		</div>
		<!-- END SHORT_NAME -->
		<!-- START LOCATION -->', 
	$buffer);
};
return $buffer;
}

public function onBeforeSync($type, $calendar_id, $buffer) { // on syncing an item
global $_LW;
static $map;
// if ($calendar_id!=10) { // temporary, for testing -- restricts all subsequent code to test feed until launch
// 	return $buffer;
// };
if ($type=='events') { // if syncing an event
	if (!isset($map[$calendar_id])) { // if feed info not yet obtained
		$map[$calendar_id]['locations']=array(); // fetch locations usable by this feed owner
		foreach($_LW->dbo->query('select', 'livewhale_places.title, livewhale_custom_data.value AS short_name', 'livewhale_places', 'livewhale_places.gid IS NULL OR livewhale_places.gid=livewhale_events_subscriptions.gid')->innerJoin('livewhale_events_subscriptions', 'livewhale_events_subscriptions.id='.(int)$calendar_id)->leftJoin('livewhale_custom_data', 'livewhale_custom_data.type="places" AND livewhale_custom_data.name="short_name" AND livewhale_custom_data.pid=livewhale_places.id')->groupBy('livewhale_places.id')->run() as $res2) {
			$map[$calendar_id]['locations'][]=array($res2['title'], $res2['title']);
			if (!empty($res2['short_name'])) {
				$map[$calendar_id]['locations'][]=array($res2['short_name'], $res2['title']);
			};
		};
		$map[$calendar_id]['gid']=$_LW->dbo->query('select', 'gid', 'livewhale_events_subscriptions', 'id='.(int)$calendar_id)->firstRow('gid')->run(); // fetch feed owner
	};
	if (empty($buffer['location']) && !empty($buffer['description']) && !empty($map[$calendar_id]['locations'])) { // if event has no location but has a description, and there are locations to match
		$locations_matched=array();
		foreach($map[$calendar_id]['locations'] as $location) { // match up to 2 locations in the description, case-insensitive
			if (stripos($buffer['description'], $location[0])) {
				$locations_matched[$location[1]]='';
			};
			if (sizeof($locations_matched)===2) {
				break;
			};
		};
		if (sizeof($locations_matched)===1) { // if only 1 match
			$buffer['location']=key($locations_matched); // set it as the location (the importer will later assign the corresponding saved location)
		};
	};
};
return $buffer;
}

}

?>