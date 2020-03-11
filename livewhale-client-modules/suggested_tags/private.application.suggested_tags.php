<?php

/*

This is a backend application which suggests tags based on event type or story category and allows management of assignments via the global tags manager.

*/

$_LW->REGISTERED_APPS['suggested_tags']=[ // configure this module
	'title'=>'Suggested Tags',
	'handlers'=>['onLaunch', 'onManagerSubmit'],
	'application'=>[
		'order'=>-1
	],
	'custom'=>[
		'is_enabled'=>false
	]
];

class LiveWhaleApplicationSuggestedTags {

public function onLaunch() { // main application controller
global $_LW;
if (empty($_LW->REGISTERED_APPS['suggested_tags']['custom']['is_enabled'])) {
	return false;
};
if ($_LW->page === 'events_categories' || $_LW->page === 'events_edit' || $_LW->page === 'events_sub_edit') { // on events editors
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/suggested_tags%5Csuggested_tags.css'; // load custom CSS
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/suggested_tags%5Csuggested_tags.js'; // load custom JS
	$_LW->json['suggested_event_tags']=[];
	foreach($_LW->dbo->query('select', 'id, title', 'livewhale_events_categories')->run() as $res2) { // for each event type
		$custom_fields=$_LW->getCustomFields('events_category', $res2['id']);
		if (!empty($custom_fields['suggested_tags'])) { // fetch all suggested_tags associated with it
			foreach($custom_fields['suggested_tags'] as $tag_id) {
				if (!isset($_LW->json['suggested_event_tags'][$res2['id']])) {
					$_LW->json['suggested_event_tags'][$res2['id']]=[];
				};
				if ($tag_title=$_LW->dbo->query('select', 'title', 'livewhale_tags', 'id='.(int)$tag_id)->firstRow('title')->run()) {
                    if ($_LW->page === 'events_categories') {
                        $_LW->json['suggested_event_tags'][$res2['id']][]=[
                            'id' => $res2['id'] . '-' . $tag_id,
                            'title' => $tag_title
						]; // and set it in the JSON
                    }
					else {
                        $_LW->json['suggested_event_tags'][$res2['title']][]=$tag_title;
                    }
				};
			};
		};
	};
	// add global tags to JSON
	if ($_LW->page === 'events_categories') {
	    $tags=[];
	    // loop through and add tags
	    foreach($_LW->dbo->query('select', 'id, title', 'livewhale_tags', 'gid IS NULL', 'title ASC')->run() as $res2) {
	        $tags[]=['id'=>$res2['id'], 'title'=>$res2['title']];
	    }
	    $_LW->json['global_tags'] = $tags;
	}
}
else if ($_LW->page === 'news_categories' || $_LW->page === 'news_edit') { // else on news editors
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/suggested_tags%5Csuggested_tags.css'; // load custom CSS
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/suggested_tags%5Csuggested_tags.js'; // load custom JS
	$_LW->json['suggested_news_tags']=[];
	foreach($_LW->dbo->query('select', 'id, title', 'livewhale_news_categories')->run() as $res2) { // for each story category
		$custom_fields=$_LW->getCustomFields('news_category', $res2['id']);
		if (!empty($custom_fields['suggested_tags'])) { // fetch all suggested_tags associated with it
			foreach($custom_fields['suggested_tags'] as $tag_id) {
				if (!isset($_LW->json['suggested_news_tags'][$res2['id']])) {
					$_LW->json['suggested_news_tags'][$res2['id']]=[];
				};
				if ($tag_title=$_LW->dbo->query('select', 'title', 'livewhale_tags', 'id='.(int)$tag_id)->firstRow('title')->run()) {
                    if ($_LW->page === 'news_categories') {
                        $_LW->json['suggested_news_tags'][$res2['id']][]=[
                            'id' => $res2['id'] . '-' . $tag_id,
                            'title' => $tag_title
						]; // and set it in the JSON
                    }
					else {
                        $_LW->json['suggested_news_tags'][$res2['title']][]=$tag_title;
                    }
				};
			};
		};
	};
	// add global tags to JSON
	if ($_LW->page === 'news_categories') {
	    $tags=[];
	    // loop through and add tags
	    foreach($_LW->dbo->query('select', 'id, title', 'livewhale_tags', 'gid IS NULL', 'title ASC')->run() as $res2) {
	        $tags[]=['id'=>$res2['id'], 'title'=>$res2['title']];
	    }
	    $_LW->json['global_tags'] = $tags;
	}
};
}

public function onManagerSubmit($module, $page) { // on manager submission
global $_LW;
if (empty($_LW->REGISTERED_APPS['suggested_tags']['custom']['is_enabled'])) {
	return false;
};
if ($page=='events_categories') { // if this was the event type manager
	$type=(!empty($_LW->_GET['type']) && in_array($_LW->_GET['type'], $_LW->REGISTERED_MODULES['events']['data_types']['events_category']['types'])) ? array_search($_LW->_GET['type'], $_LW->REGISTERED_MODULES['events']['data_types']['events_category']['types']) : 1;
	foreach($_LW->dbo->query('select', 'id', 'livewhale_events_categories', 'type='.(int)$type)->run() as $res2) { // for each item
		$suggested_tags=[];
		if (!empty($_LW->_POST['suggested_tags'])) { // get any suggested tags for this item
			foreach($_LW->_POST['suggested_tags'] as $val) {
				$val=explode('-', $val);
				if ($val[0]==$res2['id']) {
					$suggested_tags[]=$val[1];
				};
			};
		};
		if (!empty($_LW->_POST['suggested_tags_added'])) { // get any suggested tags added for this item
			foreach($_LW->_POST['suggested_tags_added'] as $val) {
				$val=explode('-', $val);
				if ($val[0]==$res2['id']) {
					if ($tag_id=$_LW->create('tags', ['gid'=>'', 'title'=>$val[1]])) {
						$suggested_tags[]=$tag_id;
					};
				};
			};
		};
		$_LW->setCustomFields('events_category', $res2['id'], ['suggested_tags'=>$suggested_tags], []); // set (or clear) the tag assignments for this item
	};
}
if ($page=='news_categories') { // else if this was the news category manager
	foreach($_LW->dbo->query('select', 'id', 'livewhale_news_categories')->run() as $res2) { // for each item
		$suggested_tags=[];
		if (!empty($_LW->_POST['suggested_tags'])) { // get any suggested tags for this item
			foreach($_LW->_POST['suggested_tags'] as $val) {
				$val=explode('-', $val);
				if ($val[0]==$res2['id']) {
					$suggested_tags[]=$val[1];
				};
			};
		};
		if (!empty($_LW->_POST['suggested_tags_added'])) { // get any suggested tags added for this item
			foreach($_LW->_POST['suggested_tags_added'] as $val) {
				$val=explode('-', $val);
				if ($val[0]==$res2['id']) {
					if ($tag_id=$_LW->create('tags', ['gid'=>'', 'title'=>$val[1]])) {
						$suggested_tags[]=$tag_id;
					};
				};
			};
		};
		$_LW->setCustomFields('news_category', $res2['id'], ['suggested_tags'=>$suggested_tags], []); // set (or clear) the tag assignments for this item
	};
};
}

}

?>