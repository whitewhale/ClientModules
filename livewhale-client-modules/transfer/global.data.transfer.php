<?php

/*

This module provides functionality to transfer dynamic content from one LiveWhale instance to another.

*/

$_LW->REGISTERED_MODULES['transfer']=[ // configure this module
	'title'=>'Transfer',
	'link'=>'/livewhale/?transfer',
	'revision'=>1,
	'order'=>0,
	'flags'=>['is_always_authorized'],
	'requires_permission'=>['core_admin'],
	'handlers'=>[],
	'data_types'=>[
		'transfer'=>[
			'managers'=>[
				'transfer'=>[
					'help'=>'',
					'handlers'=>[
						'onManager'=>'onManagerTransfer',
						'onManagerSubmit'=>'onManagerSubmitTransfer'
					]
				]
			]
		]
	],
	'custom'=>[
		'import_url'=>'', // source url to import from
		'gid'=>0 // group ID for the local destination group
	]
];

class LiveWhaleDataTransfer {

public function onManagerTransfer() { // performs actions before the manager loads for this module
global $_LW, $title, $msg;
$title='Transfer Content'; // set title
if (empty($_LW->_POST)) { // if not POSTing
	$msg='';
	if (empty($_LW->REGISTERED_MODULES['transfer']['custom']['import_url'])) { // give error if no import_url
		$_LW->REGISTERED_MESSAGES['failure'][]='You must specify an import url.';
	}
	else if (empty($_LW->REGISTERED_MODULES['transfer']['custom']['gid'])) { // give error if no gid
		$_LW->REGISTERED_MESSAGES['failure'][]='You must specify a group id for content which cannot otherwise be assigned to a known group.';
	}
	else { // else if valid configuration
		if ($group_title=$_LW->dbo->query('select', 'fullname', 'livewhale_groups', 'id='.(int)$_LW->REGISTERED_MODULES['transfer']['custom']['gid'])->firstRow('fullname')->run()) { // fetch group title
			$msg='<p>Importing from <strong>'.$_LW->setFormatClean($_LW->REGISTERED_MODULES['transfer']['custom']['import_url']).'</strong> to matching groups, or catch-all <strong>'.$_LW->setFormatClean($group_title).'</strong>. <input type="hidden" name="command" value="fetch"/><input type="submit" value="Go"/></p>';
		}
		else { // else give error if invalid group
			$_LW->REGISTERED_MESSAGES['failure'][]='The specified id for the destination group is invalid.';
		};
	};
};
}

public function onManagerSubmitTransfer() { // called upon manager submission
global $_LW;
if (!empty($_LW->_POST['command'])) { // handle POST commands
	switch($_LW->_POST['command']) {
		case 'fetch': // if fetching
			$this->fetchContent(); // fetch content from source server
			break;
		case 'import': // if importing
			if (!empty($_LW->_POST['action']) && in_array($_LW->_POST['action'], ['delete', 'preserve'])) {
				$this->importContent($_LW->_POST['action']); // import content from fetched content
			};
			break;
	};
};
}

public function validateRequestFromDestinationHost($host) { // validates a request from a particular destination host
global $_LW;
$res=$_LW->getUrl('http'.($_LW->hasSSL($_SERVER['REMOTE_ADDR']) ? 's' : '').'://'.(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$_SERVER['REMOTE_ADDR'].']' : $_SERVER['REMOTE_ADDR']).'/livewhale/api/', true, false, [CURLOPT_HTTPHEADER=>['Host: '.$host]]); // confirm that the destination server is the host it claims to be
if ($json=@json_decode($res, true)) {
	if (!empty($json['url']) && strpos($json['url'], '://'.$host.'/')!==false) {
		return true;
	};
};
return false;
}

protected function getImportGID($group) { // gets the group we should import to
global $_LW;
static $groups;
if (!isset($groups)) {
	$groups=[];
	foreach($_LW->dbo->query('select', 'id, fullname', 'livewhale_groups')->run() as $res2) { // get all groups
		$groups[$res2['id']]=$res2['fullname'];
	};
};
return (isset($groups[$group['id']]) && $groups[$group['id']]==$group['title']) ? $group['id'] : $_LW->REGISTERED_MODULES['transfer']['custom']['gid']; // return matching group's id or the fallback import group
}

protected function getItemStatus($table, $id, $import_item) { // checks if an item being imported is "skipped" (already exists locally as-is), "created" (does not exist locally), or "changed" (matches by ID but is changed)
global $_LW;
switch($table) {
	case 'livewhale_events_categories': // for these tables, compare title/gid because there is only 1 piece of data (title) and we can avoid false results more accurately
	case 'livewhale_news_categories':
	case 'livewhale_tags':
		if ($res2=$_LW->dbo->query('select', 'gid, title', $table, 'id='.(int)$import_item['id'])->firstRow()->run()) {
			return ($res2['title']==$import_item['title'] && $res2['gid']==$import_item['gid']) ? 'skipped' : 'changed';
		}
		else {
			return 'created';
		};
		break;
	case 'livewhale_urls': // same, but for url
		if ($res2=$_LW->dbo->query('select', 'title, url', $table, 'id='.(int)$import_item['id'])->firstRow()->run()) {
			return ($res2['title']==$import_item['title'] && $res2['url']==$import_item['url']) ? 'skipped' : 'changed';
		}
		else {
			return 'created';
		};
		break;
	default: // for all else, just check if date_created/last_modified matches
		$fields=[];
		if ($_LW->dbo->hasField($table, 'date_created', true)) {
			$fields[]='date_created';
		};
		if ($_LW->dbo->hasField($table, 'last_modified', true)) {
			$fields[]='last_modified';
		};
		if ($res2=$_LW->dbo->query('select', implode(', ', $fields), $table, 'id='.(int)$import_item['id'])->firstRow()->run()) {
			return ($res2['date_created']==$import_item['date_created'] && $res2['last_modified']==$import_item['last_modified']) ? 'skipped' : 'changed';
		}
		else {
			return 'created';
		};
		break;
};
}

protected function fetchContent() { // fetches content to import
global $_LW, $msg;
if (!empty($_LW->REGISTERED_MODULES['transfer']['custom']['import_url'])) { // if an url was supplied
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/transfer')) {
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/transfer');
	};
	$type=end(explode('/', $_LW->REGISTERED_MODULES['transfer']['custom']['import_url'])); // get the type
	if (!empty($type) && in_array($type, ['news', 'events'])) { // if valid type
		if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts')<$_SERVER['REQUEST_TIME']-300) { // if file not already being written
			@touch($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // flag as writing
			$path=$_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type;
			if ($fp=@fopen($path, 'w+')) { // fetch and stage the export
				$ch=curl_init();
				curl_setopt($ch, CURLOPT_URL, $_LW->REGISTERED_MODULES['transfer']['custom']['import_url']. '/'.$_LW->CONFIG['HTTP_HOST']);
				curl_setopt($ch, CURLOPT_TIMEOUT, 900);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 900);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
				curl_exec($ch);
				$code=curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);
				fclose($fp);
				$was_valid=false;
				if ($code===200) {
					if ($fp=@fopen($path, 'r')) {
						$line=fgets($fp, 4096);
						if ($json=@json_decode($line, true)) {
							if (isset($json['error'])) {
								$_LW->REGISTERED_MESSAGES['failure'][]=$json['error'];
							};
						}
						else if ($line=@gzinflate(base64_decode($line))) {
							$was_valid=true;
						};
						fclose($fp);
					};
				};
				if (empty($was_valid)) { // if invalid export
					$size=@filesize($path);
					@unlink($path); // delete export
					if (empty($_LW->REGISTERED_MESSAGES['failure'])) { // and give error if we don't already have one
						$_LW->REGISTERED_MESSAGES['failure'][]='Could not retrieve export from source server ('.$code.' / '.$size.').';
					};
				}
				else { // else if valid export
					$statistics=[
						'total'=>0,
						'types'=>[]
					];
					if ($fp=@fopen($path, 'r')) { // open the downloaded file to get stats
						while (($line=@fgets($fp, 4096))!==false) {
							if ($line=trim(@gzinflate(base64_decode($line)))) {
								if ($line=@unserialize($line)) {
									if (!empty($line['type']) && !empty($line['table']) && isset($line['group']) && isset($line['data'])) { // for valid lines
										if (!isset($statistics['types'][$line['type']])) {
											$statistics['types'][$line['type']]=[];
										};
										$statistics_for_type=&$statistics['types'][$line['type']];
										if ($line['type']!='lookup') { // if not a lookup table
											$statistics['total']++; // increment count
											$statistics_for_type['total']=(isset($statistics_for_type['total']) ? $statistics_for_type['total']+1 : 1); // for type also
											if (isset($line['data']['gid'])) { // set the import group
												$line['data']['gid']=$this->getImportGID($line['group']);
											};
											$status=$this->getItemStatus($line['table'], $line['data']['id'], $line['data']); // get the item status
											switch($status) { // and record a statistic for it
												case 'skipped':
													$statistics_for_type['skipped']=(isset($statistics_for_type['skipped']) ? $statistics_for_type['skipped']+1 : 1);
													break;
												case 'changed':
													$statistics_for_type['changed']=(isset($statistics_for_type['changed']) ? $statistics_for_type['changed']+1 : 1);
													break;
												case 'created':
													$statistics_for_type['created']=(isset($statistics_for_type['created']) ? $statistics_for_type['created']+1 : 1);
													break;
											};
										};
									};
								};
							};
						};
						fclose($fp);
						$msg='<p><strong>'.number_format($statistics['total']).'</strong> items found:</p><ul>'; // display statistics
						foreach($statistics['types'] as $key=>$val) {
							if ($key=='lookup') {
								continue;
							};
							$display=[];
							if (((int)@$val['created']+(int)@$val['changed'])!=0) {
								$display[]=number_format((int)@$val['created']+(int)@$val['changed']).' to import';
							};
							if ((int)@$val['skipped']) {
								$display[]=number_format((int)@$val['skipped']).' unmodified';
							};
							if ($key==$type) {
								if ($total=$_LW->dbo->query('select', 'COUNT(*) AS total', 'livewhale_'.$type)->firstRow('total')->run()) {
									$total_not_on_source=(int)@$val['total']-$total;
									if ($total_not_on_source>0) {
										$display[]=number_format($total_not_on_source).' unique to this install';
									};
								};
							};
							$msg.='<li><strong>'.str_replace(['events_', 'event subscription', 'event category', 'places', 'images_collection'], ['event ', 'linked calendars', 'event types', 'locations', 'image collections'], $key).'</strong>: '.implode(', ', $display).'</li>';
						};
						$msg.='</ul>';
						$msg.='<p>What should be done with local content?</p><p><input type="radio" name="action" value="delete"/> Delete '.$type.' unique to this install and other content being replaced by the import<br/><input type="radio" name="action" value="preserve"/> Preserve all content of the type(s) being imported</p><p><input type="submit" value="Import"/></p>'; // and prompt for import options
						$msg.='<input type="hidden" name="command" value="import"/>';
					}
					else { // else give error if file could not be read
						$_LW->REGISTERED_MESSAGES['failure'][]='Could not read exported data from source server.';
					};
				};
			};
			@unlink($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // unflag as writing
		}
		else { // else give error if request already being processed
			$_LW->REGISTERED_MESSAGES['failure'][]='A transfer request is already being processed. Please wait for it to finish.';
		};
	};
};
}

protected function getImageWidgetIDs($str) { // extracts IDs from image widgets
if (strpos($str, '<widget type="image"')!==false) {
	$matches=[];
	preg_match_all('~<widget type="image"[^>]*?>\s*?<arg id="id">\s*?([0-9]+?)\s*?</arg>~s', $str, $matches);
	if (!empty($matches[1])) {
		return $matches[1];
	};
};
return [];
}

protected function updateImageWidgetIDs($str, $map) { // updates IDs in image widgets via ID map
if (empty($map) || !is_array($map)) {
	return $str;
};
$find=[];
$replace=[];
if (strpos($str, '<widget type="image"')!==false) {
	$matches=[];
	preg_match_all('~<widget type="image"[^>]*?>\s*?<arg id="id">([0-9]+?)</arg>.+?</widget>~s', $str, $matches);
	if (!empty($matches[1])) {
		foreach($matches[1] as $key=>$id) {
			if (isset($map[$id]) && is_array($map[$id]) && sizeof($map[$id])===2) {
				$find[]=$matches[0][$key];
				$replace[]=preg_replace('~/gid/[0-9]+?/~', '/gid/'.$map[$id][1].'/', str_replace(['<arg id="id">'.$id.'</arg>', '/'.$id.'_', 'lw_image'.$id], ['<arg id="id">'.$map[$id][0].'</arg>', '/'.$map[$id][0].'_', 'lw_image'.$map[$id][0]], $matches[0][$key]));
			};
		};
	};
};
if (!empty($find)) {
	$str=str_replace($find, $replace, $str);
};
return $str;
}

public function exportContent($type) { // exports content for the specified type
global $_LW;
if (!empty($type) && in_array($type, ['news', 'events'])) { // if valid type
	$_LW->disable_request_notice=true; // disable slow request notice
	set_time_limit(3600); // lessen time limit
	ini_set('memory_limit', '1G'); // set high memory limit
	header('Cache-Control: no-store, no-cache, must-revalidate');
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/transfer')) {
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/transfer');
	};
	if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts')<$_SERVER['REQUEST_TIME']-300) { // if file not already being written
		@touch($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // flag as writing
		$path=$_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type; // set path to write
		if (file_exists($path)) { // reset export
			@unlink($path);
		};
		$group_titles=[];
		foreach($_LW->dbo->query('select', 'id, fullname', 'livewhale_groups', '', 'id ASC')->run() as $res2) { // get all groups
			$group_titles[$res2['id']]=$res2['fullname'];
		};
		$unique_items=[];
		if ($fp=@fopen($path, 'w+')) { // open file for writing
			foreach($_LW->dbo->query('select', '*', 'livewhale_'.$type, false, 'id ASC')->run() as $res2) { // for each item of this type
				$line=[ // add the main item (must do this first, because if ID is changed at import time we need to update it in all subsequent imported items)
					'type'=>$type,
					'table'=>'livewhale_'.$type,
					'group'=>[
						'id'=>(!empty($res2['gid']) ? $res2['gid'] : false),
						'title'=>(!empty($res2['gid']) ? $group_titles[$res2['gid']] : false)
					],
					'data'=>$res2
				];
				@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
				if ($type!='images') { // add any images that are WYSIWYG-added
					foreach($res2 as $val) {
						if ($image_ids=$this->getImageWidgetIDs($val)) {
							foreach($image_ids as $image_id) { // match all inline image IDs
								if (!isset($unique_items['livewhale_images-'.$image_id])) {
									if ($res4=$_LW->dbo->query('select', '*', 'livewhale_images', 'id='.(int)$image_id)->firstRow()->run()) { // and add them
										$line=[
											'type'=>'images',
											'table'=>'livewhale_images',
											'group'=>[
												'id'=>(!empty($res4['gid']) ? $res4['gid'] : false),
												'title'=>(!empty($res4['gid']) ? $group_titles[$res4['gid']] : false)
											],
											'data'=>$res4
										];
										@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
										$unique_items[$linked_table.'-'.$res4['id']]='';
										if (!empty($res4['collection_id'])) { // handle old style collection_id
											if (!isset($unique_items['livewhale_images_collections-'.$res4['collection_id']])) { // if we haven't copied the image collection yet
												if ($res6=$_LW->dbo->query('select', '*', 'livewhale_images_collections', 'id='.(int)$res4['collection_id'])->firstRow()->run()) { // fetch the image collection and add it
													$line=[
														'type'=>'images_collection',
														'table'=>'livewhale_images_collections',
														'group'=>[
															'id'=>(!empty($res6['gid']) ? $res6['gid'] : false),
															'title'=>(!empty($res6['gid']) ? $group_titles[$res6['gid']] : false)
														],
														'data'=>$res6
													];
													@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
													$unique_items['livewhale_images_collections-'.$res6['id']]='';
												};
											};
											if (isset($unique_items['livewhale_images_collections-'.$res4['collection_id']])) { // if we did copy the image collection
												$line=[ // add all the image collection linkages
													'type'=>'lookup',
													'table'=>'livewhale_images2images_collections',
													'group'=>[
														'id'=>false,
														'title'=>false
													],
													'data'=>['id1'=>$res4['id'], 'id2'=>$res4['collection_id']]
												];
												@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
											};
										};
										if ($_LW->dbo->hasTable('livewhale_images2images_collections', true)) {
											foreach($_LW->dbo->query('select', 'id2', 'livewhale_images2images_collections', 'id1='.(int)$res4['id'])->run() as $res5) { // get all image collection linkages
												if (!isset($unique_items['livewhale_images_collections-'.$res5['id2']])) { // if we haven't copied the image collection yet
													if ($res6=$_LW->dbo->query('select', '*', 'livewhale_images_collections', 'id='.(int)$res5['id2'])->firstRow()->run()) { // fetch the image collection and add it
														$line=[
															'type'=>'images_collection',
															'table'=>'livewhale_images_collections',
															'group'=>[
																'id'=>(!empty($res6['gid']) ? $res6['gid'] : false),
																'title'=>(!empty($res6['gid']) ? $group_titles[$res6['gid']] : false)
															],
															'data'=>$res6
														];
														@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
														$unique_items['livewhale_images_collections-'.$res6['id']]='';
													};
												};
												if (isset($unique_items['livewhale_images_collections-'.$res5['id2']])) { // if we did copy the image collection
													$line=[ // add all the image collection linkages
														'type'=>'lookup',
														'table'=>'livewhale_images2images_collections',
														'group'=>[
															'id'=>false,
															'title'=>false
														],
														'data'=>$res5
													];
													@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
												};
											};
										};
									};
								};
							};
						};
					};
				};
				if ($type==='events' && !empty($res2['subscription_pid'])) { // add any linked calendars
					if (!isset($unique_items['livewhale_events_subscriptions-'.$res2['subscription_pid']])) {
						if ($res3=$_LW->dbo->query('select', '*', 'livewhale_events_subscriptions', 'id='.(int)$res2['subscription_pid'])->firstRow()->run()) {
							$line=[
								'type'=>'events_subscription',
								'table'=>'livewhale_events_subscriptions',
								'group'=>[
									'id'=>(!empty($res3['gid']) ? $res3['gid'] : false),
									'title'=>(!empty($res3['gid']) ? $group_titles[$res3['gid']] : false)
								],
								'data'=>$res3
							];
							@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
							foreach($_LW->dbo->query('select', '*', 'livewhale_custom_data', 'pid='.(int)$res3['id'].' AND type="events_subscriptions"')->run() as $res4) { // get custom data
								$line=[
									'type'=>'lookup',
									'table'=>'livewhale_custom_data',
									'group'=>[
										'id'=>false,
										'title'=>false
									],
									'data'=>$res4
								];
								@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
							};
						};
						$unique_items['livewhale_events_subscriptions-'.$res2['subscription_pid']]='';
					};
				};
				foreach($_LW->getLookupTables() as $lookup_table) { // add lookup data and associated content
					if (substr($lookup_table, -4, 4)!='2any') {
						continue;
					};
					$linked_table=substr($lookup_table, 0, -4);
					foreach($_LW->dbo->query('select', '*', $lookup_table, 'type="'.$type.'" AND id2='.(int)$res2['id'])->run() as $res3) { // get links TO the item being imported from other content types
						if ($res4=$_LW->dbo->query('select', '*', $linked_table, 'id='.(int)$res3['id1'])->firstRow()->run()) {
							if (!isset($unique_items[$linked_table.'-'.$res4['id']])) { // and fetch the item linking to it because we need to import that tag/location/etc.
								$linked_type=$this->getDataTypeForTable($linked_table);
								if (empty($linked_type)) {
									$linked_type=substr($linked_table, 10);
								};
								$line=[
									'type'=>$linked_type,
									'table'=>$linked_table,
									'group'=>[
										'id'=>(!empty($res4['gid']) ? $res4['gid'] : false),
										'title'=>(!empty($res4['gid']) ? $group_titles[$res4['gid']] : false)
									],
									'data'=>$res4
								];
								@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
								$unique_items[$linked_table.'-'.$res4['id']]='';
								if (substr($linked_table, 10)=='images') { // if it is an image coming over
									if (!empty($res4['collection_id'])) { // handle old style collection_id
										if (!isset($unique_items['livewhale_images_collections-'.$res4['collection_id']])) { // if we haven't copied the image collection yet
											if ($res6=$_LW->dbo->query('select', '*', 'livewhale_images_collections', 'id='.(int)$res4['collection_id'])->firstRow()->run()) { // fetch the image collection and add it
												$line=[
													'type'=>'images_collection',
													'table'=>'livewhale_images_collections',
													'group'=>[
														'id'=>(!empty($res6['gid']) ? $res6['gid'] : false),
														'title'=>(!empty($res6['gid']) ? $group_titles[$res6['gid']] : false)
													],
													'data'=>$res6
												];
												@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
												$unique_items['livewhale_images_collections-'.$res6['id']]='';
											};
										};
										if (isset($unique_items['livewhale_images_collections-'.$res4['collection_id']])) { // if we did copy the image collection
											$line=[ // add all the image collection linkages
												'type'=>'lookup',
												'table'=>'livewhale_images2images_collections',
												'group'=>[
													'id'=>false,
													'title'=>false
												],
												'data'=>['id1'=>$res4['id'], 'id2'=>$res4['collection_id']]
											];
											@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
										};
									};
									if ($_LW->dbo->hasTable('livewhale_images2images_collections', true)) {
										foreach($_LW->dbo->query('select', 'id2', 'livewhale_images2images_collections', 'id1='.(int)$res4['id'])->run() as $res5) { // get all image collection linkages
											if (!isset($unique_items['livewhale_images_collections-'.$res5['id2']])) { // if we haven't copied the image collection yet
												if ($res6=$_LW->dbo->query('select', '*', 'livewhale_images_collections', 'id='.(int)$res5['id2'])->firstRow()->run()) { // fetch the image collection and add it
													$line=[
														'type'=>'images_collection',
														'table'=>'livewhale_images_collections',
														'group'=>[
															'id'=>(!empty($res6['gid']) ? $res6['gid'] : false),
															'title'=>(!empty($res6['gid']) ? $group_titles[$res6['gid']] : false)
														],
														'data'=>$res6
													];
													@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
													$unique_items['livewhale_images_collections-'.$res6['id']]='';
												};
											};
											if (isset($unique_items['livewhale_images_collections-'.$res5['id2']])) { // if we did copy the image collection
												$line=[ // add all the image collection linkages
													'type'=>'lookup',
													'table'=>'livewhale_images2images_collections',
													'group'=>[
														'id'=>false,
														'title'=>false
													],
													'data'=>$res5
												];
												@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
											};
										};
									};
								};
							};
							$line=[
								'type'=>'lookup',
								'table'=>$lookup_table,
								'group'=>[
									'id'=>false,
									'title'=>false
								],
								'data'=>$res3
							];
							@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
						};
					};
				};
				if ($_LW->dbo->hasTable('livewhale_'.$type.'2any')) {
					$lookup_table='livewhale_'.$type.'2any';
					foreach($_LW->dbo->query('select', '*', $lookup_table, 'id1='.(int)$res2['id'])->run() as $res3) { // get links FROM the item being imported to other content types, but we don't need to import those items because it might not be a content type we import
						$line=[
							'type'=>'lookup',
							'table'=>$lookup_table,
							'group'=>[
								'id'=>false,
								'title'=>false
							],
							'data'=>$res3
						];
						@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
					};
				};
				foreach($_LW->dbo->query('select', '*', 'livewhale_custom_data', 'pid='.(int)$res2['id'].' AND type='.$_LW->escape($type))->run() as $res3) { // get custom data
					$line=[
						'type'=>'lookup',
						'table'=>'livewhale_custom_data',
						'group'=>[
							'id'=>false,
							'title'=>false
						],
						'data'=>$res3
					];
					@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
				};
			};
			fclose($fp);
			unset($unique_items);
		};
		if (@filemtime($path)>$_SERVER['REQUEST_TIME']-3) { // if file was written
			if ($fp=@fopen($path, 'r')) { // if file open
				while (!feof($fp)) { // loop until EOF
					echo fread($fp, 1024*1024); // transfer 1MB at a time
					@ob_flush(); // flush output
					flush();
					if (function_exists('gc_collect_cycles')) { // run GC after each 1MB
						gc_collect_cycles();
					};
				};
				fclose($fp); // close file
			};
		};
		@unlink($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // unflag as writing
	}
	else { // else give error if request already being processed
		json_encode(['error'=>'A transfer request is already being processed. Please wait for it to finish.']);
	};
}
else { // else give error on invalid type validation
	return json_encode(['error'=>'Invalid content type. Only news and events are currently supported.']);
};
}

protected function preserveContent($path) { // preserves existing content on the destination server by ID-shifting it
global $_LW;
$tables=[];
if ($fp=@fopen($path, 'r')) { // open the file
	while (($line=@fgets($fp, 4096))!==false) {
		if ($line=trim(@gzinflate(base64_decode($line)))) {
			if ($line=@unserialize($line)) {
				if (!empty($line['type']) && !empty($line['table']) && isset($line['group']) && isset($line['data'])) { // for each valid line
					if ($line['type']=='news' || $line['type']=='events') { // if supported type
						if (!isset($tables[$line['table']])) { // record each unique table encountered, and track the highest ID found in the import for that table
							$tables[$line['table']]=$line['data']['id'];
						}
						else {
							if ($line['data']['id']>$tables[$line['table']]) {
								$tables[$line['table']]=$line['data']['id'];
							};
						};
					};
				};
			};
		};
	};
	fclose($fp);
};
if (!empty($tables)) { // if tables were found
	foreach($tables as $table=>$highest_import_id) { // for each table and highest ID
		$lowest_local_id=$_LW->dbo->query('select', 'id', $table, false, 'id ASC')->limit(1)->firstRow('id')->run(); // get the highest and lowest local IDs on the destination server for this table
		$highest_local_id=$_LW->dbo->query('select', 'id', $table, false, 'id DESC')->limit(1)->firstRow('id')->run();
		if (!empty($lowest_local_id) && !empty($highest_local_id)) { // get the lowest ID on the destination server for this table
			$starting_id=($highest_import_id>$highest_local_id ? $highest_import_id : $highest_local_id)+1; // get the starting ID for preserved content (the next ID after either the highest existing ID or import ID, or else where would be conflicts either with the import IDs or the existing content being shifted locally)
			$offset=$starting_id-$lowest_local_id; // get the offset to increase every existing ID by
			if ($offset>0) {
				$type=substr($table, 10); // get the type associated with this table
				foreach($_LW->dbo->query('select', 'id', $table, false, 'id DESC')->run() AS $res2) { // loop through all items of this type
					$old_id=$res2['id'];
					$new_id=$old_id+$offset;
					$this->changeIDForContent($type, $old_id, $new_id); // update the ID
				};
				$this->resetAutoIncrement($table); // reset the auto-increment ID for each table
			};
		};
	};
};
}

public function importContent($action) { // imports content for the specified type
global $_LW, $msg;
if (!empty($_LW->REGISTERED_MODULES['transfer']['custom']['import_url'])) { // if an url was supplied
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/transfer')) {
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/transfer');
	};
	$type=end(explode('/', $_LW->REGISTERED_MODULES['transfer']['custom']['import_url']));
	if (!empty($type) && in_array($type, ['news', 'events']) && !empty($action) && in_array($action, ['delete', 'preserve'])) { // if valid type
		$_LW->disable_request_notice=true; // disable slow request notice
		set_time_limit(3600); // lessen time limit
		ini_set('memory_limit', '256M'); // set high memory limit
		header('Cache-Control: no-store, no-cache, must-revalidate');
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
		if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/transfer')) {
			@mkdir($_LW->INCLUDES_DIR_PATH.'/data/transfer');
		};
		if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts')<$_SERVER['REQUEST_TIME']-300) { // if file not already being processed
			@touch($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // flag as processing
			$path=$_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type; // set path to read
			if ($action=='preserve') { // if preserve option was chosen
				$this->preserveContent($path); // preserve all existing supported content types impacted by the import by ID-shifting their data
			};
			$imported_ids=[];
			$translated_ids=[];
			$image_widgets=[];
			$image_widget_map=[];
			if ($fp=@fopen($path, 'r')) { // open the file
				while (($line=@fgets($fp, 4096))!==false) {
					if ($line=trim(@gzinflate(base64_decode($line)))) {
						if ($line=@unserialize($line)) {
							if (!empty($line['type']) && !empty($line['table']) && isset($line['group']) && isset($line['data'])) { // for each valid line
								if ($line['type']!='lookup') { // if not a lookup record
									$last_insert_id=$line['data']['id'];
									$_LW->dbo->query('delete', 'livewhale_trash', 'id='.(int)$line['data']['id'])->run(); // always delete an existing trash entry for the imported item, because an item can be in the trash or not, but not both
									if (isset($line['data']['gid'])) { // override destination group if necessary
										$line['original_gid']=$line['data']['gid'];
										$line['data']['gid']=$this->getImportGID($line['group']);
									};
									$status=$this->getItemStatus($line['table'], $line['data']['id'], $line['data']); // get status of import
									if ($status!='skipped') { // if not skipping the item
										if ($status=='changed') { // if the item is changed
											if ($action=='delete') { // if deleting
												$_LW->delete($line['type'], $line['data']['id'], false); // delete an existing item first but do not trash
												$status='created'; // change status to created so it gets recreated
											}
											else { // else if preserving
												if (in_array($line['type'], ['news', 'events'])) { // if one of the types we ID shifted
													$status='created'; // switch to created
												};
											};
										};
										if ($status=='changed') { // if item is still changed (and therefore neither deleted, nor one of the ID shifted content types)
											if ($import_id=$_LW->dbo->query('select', 'value', 'livewhale_custom_data', 'type='.$_LW->escape($line['type']).' AND pid='.(int)$line['data']['id'].' AND name="import_id"')->firstRow('value')->run()) { // if there was a previous import ID
												$line2=$line;
												$line2['data']['id']=$import_id;
												if ($status2=$this->getItemStatus($line2['table'], $line2['data']['id'], $line2['data'])) { // check the status of that ID
													if ($status2=='changed') { // if still changed, import it again at auto_increment
														$translated_ids[$line['type'].'-'.$line['data']['id']]=0;
													}
													else { // else use the previous import ID
														$translated_ids[$line['type'].'-'.$line['data']['id']]=(int)$import_id;
													};
												}
												else { // else use the previous import ID
													$translated_ids[$line['type'].'-'.$line['data']['id']]=(int)$import_id;
												};
											}
											else { // else if no previous import ID
												$translated_ids[$line['type'].'-'.$line['data']['id']]=0; // import it at auto_increment
											};
										};
										$line['data_insert']=$line['data'];
										if (isset($translated_ids[$line['type'].'-'.$line['data']['id']])) { // if the ID is translated
											if (empty($translated_ids[$line['type'].'-'.$line['data']['id']])) { // if using auto-increment
												$line['data_insert']['id']=''; // blank the ID
												$last_insert_id='';
											}
											else { // else if using a different ID
												$line['data_insert']['id']=$translated_ids[$line['type'].'-'.$line['data']['id']]; // set it
												$last_insert_id=$translated_ids[$line['type'].'-'.$line['data']['id']];
											};
										};
										// at this point, all "created" items have no ID conflict and all "changed" items will be created using a translated ID, so also no ID conflict
										foreach($line['data_insert'] as $key=>$val) {
											$line['data_insert'][$key]=$_LW->escape($val);
										};
										if (isset($line['data_insert']['rank'])) { // convert rank if source is older version of LiveWhale
											$line['data_insert']['balloons']=$line['data_insert']['rank'];
											unset($line['data_insert']['rank']);
										};
										if (isset($line['data_insert']['collection_id'])) { // handle old style collection ID
											unset($line['data_insert']['collection_id']);
										};
										$_LW->dbo->query('insert', $line['table'], $line['data_insert'])->run(); // insert the item
										$last_insert_id=(empty($last_insert_id) ? (int)$_LW->dbo->lastInsertID() : $last_insert_id); // get the last insert ID
										if ($line['type']=='images' && $line['data']['gid']!=$line['original_gid']) { // build image widget map if gid changed
											$image_widget_map[$last_insert_id]=[$last_insert_id, $line['data']['gid']];
										};
										foreach($line['data'] as $key=>$val) { // track all fields with an image widget
											if (is_scalar($val) && strpos($val, '<widget type="image"')!==false) {
												$image_widgets[$line['table']][$last_insert_id][$key]=$this->getImageWidgetIDs($val);
											};
										};
										if (isset($translated_ids[$line['type'].'-'.$line['data']['id']]) && empty($translated_ids[$line['type'].'-'.$line['data']['id']])) { // update translated ID if it was created at auto_increment
											$translated_ids[$line['type'].'-'.$line['data']['id']]=$last_insert_id;
										};
										if ($line['table']=='livewhale_events_categories' || $line['table']=='livewhale_news_categories') { // for these types
											if ($res2=$_LW->dbo->query('select', 'id', $line['table'], 'title='.$_LW->escape($line['data']['title']).' AND id!='.(int)$last_insert_id)->firstRow()->run()) { // check if there is still another by the same title but with a different ID (we can't have duplicates)
												$_LW->dbo->query('delete', $line['table'], 'id='.(int)$res2['id'])->run(); // delete it
												$_LW->dbo->query('update', $line['table'].'2any', ['id1'=>(int)$last_insert_id], 'id1='.(int)$res2['id'])->run(); // and relink its assignments to the one we just imported
											};
											if ($line['table']=='livewhale_events_categories') { // add the ID to the authorized event types per group
												foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="event_types"')->run() as $res2) {
													$res2['value']=explode(',', $res2['value']);
													if (!in_array((int)$last_insert_id, $res2['value'])) {
														$res2['value'][]=(int)$last_insert_id;
														$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
													};
												};
											}
											else if ($line['table']=='livewhale_news_categories') { // add the ID to the authorized news categories per group
												foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="news_categories"')->run() as $res2) {
													$res2['value']=explode(',', $res2['value']);
													if (!in_array((int)$last_insert_id, $res2['value'])) {
														$res2['value'][]=(int)$last_insert_id;
														$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
													};
												};
											};
										};
										if ($line['type']==$type) { // for the main import type only
											if (!isset($imported_ids[$line['type']])) {
												$imported_ids[$line['type']]=[];
											};
											$imported_ids[$line['type']][]=$last_insert_id; // keep track of imported IDs for deletion of unaffected content on destination server
										};
										if ($_LW->hasHandler('data_type', $line['type'], 'onCreate')) { // trigger post-creation steps
											$_LW->callHandler('data_type', $line['type'], 'onCreate', [$last_insert_id]);
											$_LW->callHandlersByType('application', 'onAfterCreate', [$line['type'], $last_insert_id]);
										};
									}
									else { // else if skipped
										if ($line['type']==$type) { // for the main import type only
											if (!isset($imported_ids[$line['type']])) {
												$imported_ids[$line['type']]=[];
											};
											$imported_ids[$line['type']][]=$line['data']['id']; // keep track of imported IDs for deletion of unaffected content on destination server
										};
										if ($line['type']=='images' && $line['data']['gid']!=$line['original_gid']) { // build image widget map if gid changed
											$image_widget_map[$last_insert_id]=[$last_insert_id, $line['data']['gid']];
										};
									};
									if ($line['table']=='livewhale_images' || $line['table']=='livewhale_files') { // if image or file
										if (empty($last_insert_id)) { // make sure we have a last insert ID for skipped items
											$last_insert_id=$line['data']['id'];
										};
										if ($res2=$_LW->dbo->query('select', 'gid, filename, extension', 'livewhale_'.($line['table']=='livewhale_images'  ? 'images' : 'files'), 'id='.(int)$last_insert_id)->firstRow()->run()) { // if item info obtained
											$path=$_LW->LIVEWHALE_DIR_PATH.'/content/'.($line['table']=='livewhale_images'  ? 'images' : 'files').'/'.$res2['gid'].'/'.$res2['filename'].'.'.$res2['extension'];
											if (!is_dir($_LW->LIVEWHALE_DIR_PATH.'/content/'.($line['table']=='livewhale_images'  ? 'images' : 'files').'/'.$res2['gid'])) {
												@mkdir($_LW->LIVEWHALE_DIR_PATH.'/content/'.($line['table']=='livewhale_images'  ? 'images' : 'files').'/'.$res2['gid']);
											};
											if (!file_exists($path)) { // if the item does not exist in the FS yet
												if (!isset($url_info)) {
													$url_info=@parse_url($_LW->REGISTERED_MODULES['transfer']['custom']['import_url']);
												};
												$item_data=[
													'url'=>$url_info['scheme'].'://'.$url_info['host'].'/live/image/gid/'.$line['original_gid'].'/'.$line['data']['filename'].'.'.$line['data']['extension']
												];
												if ($fp_write=@fopen($_LW->INCLUDES_DIR_PATH.'/data/transfer/image_sync', 'w+')) { // write the local file from remote url
													$ch=curl_init($item_data['url']);
													curl_setopt($ch, CURLOPT_TIMEOUT, 300);
													curl_setopt($ch, CURLOPT_FILE, $fp_write);
													curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
													curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
													curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
													curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
													curl_setopt($ch, CURLOPT_USERAGENT, 'LiveWhale');
													curl_exec($ch);
													curl_close($ch);
													fclose($fp_write);
													if (file_exists($_LW->INCLUDES_DIR_PATH.'/data/transfer/image_sync')) { // if file was written
														if (@filesize($_LW->INCLUDES_DIR_PATH.'/data/transfer/image_sync')!=0) {
															@rename($_LW->INCLUDES_DIR_PATH.'/data/transfer/image_sync', $path);
														};
														@unlink($_LW->INCLUDES_DIR_PATH.'/data/transfer/image_sync');
													};
												};
												
											};
										};
									};
								}
								else { // else for lookup records
									$line['data_insert']=$line['data'];
									foreach($line['data_insert'] as $key=>$val) {
										$line['data_insert'][$key]=$_LW->escape($val);
									};
									$_LW->dbo->query('insert', $line['table'], $line['data_insert'], true)->run(); // insert the item
								};
							};
						};
					};
				};
				fclose($fp);
				if (!empty($translated_ids)) { // process all translated IDs
					$lookup_tables=$_LW->getLookupTables();
					foreach($translated_ids as $key=>$val) {
						$key=explode('-', $key);
						$translated_type=$key[0];
						$old_id=$key[1];
						$new_id=$val;
						$_LW->dbo->sql('REPLACE INTO livewhale_custom_data VALUES('.$_LW->escape($translated_type).', '.(int)$old_id.', "import_id", '.(int)$new_id.', 1);'); // record the ID we translated it to
						if ($translated_type=='images') { // build image widget map
							if ($translated_gid=$_LW->dbo->query('select', 'gid', 'livewhale_images', 'id='.(int)$new_id)->firstRow('gid')->run()) {
								$image_widget_map[$old_id]=[$new_id, $translated_gid];
							};
						};
						foreach($lookup_tables as $lookup_table) { // update all lookup tables
							if (substr($lookup_table, -4, 4)!='2any') {
								continue;
							};
							if ($lookup_table=='livewhale_'.$translated_type.'2any') {
								$_LW->dbo->query('update', $lookup_table, ['id1'=>(int)$new_id], 'id1='.(int)$old_id.' AND type='.$_LW->escape($type))->run();
							}
							else {
								$_LW->dbo->query('update', $lookup_table, ['id2'=>(int)$new_id], 'type='.$_LW->escape($translated_type).' AND id2='.(int)$old_id)->run();
							};
						};
						if ($translated_type=='images') {
							$_LW->dbo->query('update', 'livewhale_images2images_collections', ['id1'=>(int)$new_id], 'id1='.(int)$old_id)->run();
						}
						else if ($translated_type=='images_collections') {
							$_LW->dbo->query('update', 'livewhale_images2images_collections', ['id2'=>(int)$new_id], 'id2='.(int)$old_id)->run();
						};
					};
				};
				if (!empty($image_widgets) && !empty($image_widget_map)) { // if there were image widgets and an image widget map
					foreach(array_keys($image_widgets) as $iw_table) {
						foreach(array_keys($image_widgets[$iw_table]) as $iw_id) {
							foreach(array_keys($image_widgets[$iw_table][$iw_id]) as $iw_field) {
								$image_ids=$image_widgets[$iw_table][$iw_id][$iw_field];
								foreach($image_ids as $image_id) {
									if (isset($image_widget_map[$image_id])) { // if any of the image widget IDs appear in the map
										if ($field_value=$_LW->dbo->query('select', $iw_field, $iw_table, 'id='.(int)$iw_id)->firstRow($iw_field)->run()) { // get the field
											$_LW->dbo->query('update', $iw_table, [$iw_field=>$_LW->escape($this->updateImageWidgetIDs($field_value, $image_widget_map))], 'id='.(int)$iw_id)->run(); // and update it via the map
										};
									};
									break;
								};
							};
						};
					};
				};
				if ($action=='delete' && !empty($imported_ids)) { // if delete option chosen
					foreach($imported_ids as $import_type=>$import_ids) {
						$where=($import_type=='events' ? 'subscription_pid IS NULL' : false); // don't delete linked calendar events unique to this install because we haven't deleted their parent linked calendar so they'd just be recreated
						foreach($_LW->dbo->query('select', 'id', 'livewhale_'.$import_type, $where, 'id ASC')->run() as $res2) { // for each item of each type
							if (!in_array($res2['id'], $import_ids)) { // if it was not imported
								$_LW->delete($import_type, $res2['id'], false); // delete it and do not trash
							};
						};
					};
				};
			};
			if ($type=='events') { // clear caches
				$_LW->d_events->clearEventsCategoriesCache();
			}
			else if ($type=='news') {
				$_LW->d_news->clearNewsCategoriesCache();
			};
			@unlink($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // unflag as processing
			$msg='<p>Import was successful.</p>';
		}
		else { // else give error if request already being processed
			$_LW->REGISTERED_MESSAGES['failure'][]='A transfer request is already being processed. Please wait for it to finish.';
		};
	}
	else { // else give error on invalid type validation
		$_LW->REGISTERED_MESSAGES['failure'][]='Invalid content type. Only news and events are currently supported.';
	};
};
}

protected function changeIDForContent($type, $old_id, $new_id) { // changes the ID for a piece of content and associated records
global $_LW;
static $map_tables;
static $map_types;
if (!isset($map_tables)) { // init tables map
	$map_tables=[];
};
if (!isset($map_tables[$type])) { // get table for type
	if ($table=$_LW->getTableForDataType($type)) {
		$map_tables[$type]=$table;
	};
};
if (!isset($map_types)) { // if type map not created yet
	$map_types=[];
	foreach($_LW->getLookupTables() as $lookup_table) { // update all lookup tables
		if (substr($lookup_table, -4, 4)!='2any') {
			continue;
		};
		$map_types[$lookup_table]=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', $lookup_table)->firstRow('types')->run());
	};
	$map_types['livewhale_autosaves']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT data_type SEPARATOR "|") types', 'livewhale_autosaves')->firstRow('types')->run());
	$map_types['livewhale_custom_data']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', 'livewhale_custom_data')->firstRow('types')->run());
	$map_types['livewhale_languages_fields']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', 'livewhale_languages_fields')->firstRow('types')->run());
	$map_types['livewhale_messages']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT data_type SEPARATOR "|") types', 'livewhale_messages')->firstRow('types')->run());
	$map_types['livewhale_payments_orders']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT product_type SEPARATOR "|") types', 'livewhale_payments_orders')->firstRow('types')->run());
	$map_types['livewhale_public_submissions']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT submission_type SEPARATOR "|") types', 'livewhale_public_submissions')->firstRow('types')->run());
	$map_types['livewhale_revisions']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', 'livewhale_revisions')->firstRow('types')->run());
	$map_types['livewhale_search']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', 'livewhale_search')->firstRow('types')->run());
	$map_types['livewhale_trash']=explode('|', $_LW->dbo->query('select', 'GROUP_CONCAT(DiSTINCT type SEPARATOR "|") types', 'livewhale_trash')->firstRow('types')->run());
};
if (!empty($map_tables[$type])) { // if we have a table for the type
	$table=$map_tables[$type]; // get the table
	if (!$_LW->dbo->query('select', '1', $table, 'id='.(int)$new_id)->exists()->run()) { // if there isn't already an item with this ID
		$_LW->dbo->sql('UPDATE '.$table.' SET id='.(int)$new_id.' WHERE id='.(int)$old_id.';'); // update the main table
		switch($table) {
			case 'livewhale_events_categories':
				foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="event_types"')->run() as $res2) { // if preserving an event type, update the ID in the authorized event types per group
				$res2['value']=explode(',', $res2['value']);
					if (in_array((int)$old_id, $res2['value'])) {
						$res2['value'][array_search((int)$old_id, $res2['value'])]=(int)$new_id;
						$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
					};
				};
				break;
			case 'livewhale_news_categories':
				foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="news_categories"')->run() as $res2) { // if preserving a news category, update the ID in the authorized news categories per group
				$res2['value']=explode(',', $res2['value']);
					if (in_array((int)$old_id, $res2['value'])) {
						$res2['value'][array_search((int)$old_id, $res2['value'])]=(int)$new_id;
						$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
					};
				};
				break;
			case 'livewhale_images':
				$_LW->dbo->sql('UPDATE livewhale_images2images_collections SET id1='.(int)$new_id.' WHERE id1='.(int)$old_id.';'); // preserve image collection assignment
				if ($res2=$_LW->dbo->query('select', 'gid, filename, extension', 'livewhale_images', 'id='.(int)$new_id)->firstRow()->run()) { // update the filename and rename in the FS
					if (strpos($res2['filename'], $old_id.'_')===0 && file_exists($_LW->LIVEWHALE_DIR_PATH.'/content/images/'.(int)$res2['gid'].'/'.$res2['filename'].'.'.$res2['extension'])) {
						$new_filename=$new_id.substr($res2['filename'], strlen($old_id));
						$_LW->dbo->sql('UPDATE livewhale_images SET filename='.$_LW->escape($new_filename).' WHERE id='.(int)$new_id.';');
						$_LW->dbo->sql('UPDATE livewhale_uploads SET pid='.(int)$new_id.', filename='.$_LW->escape($new_filename).' WHERE type="images" AND pid='.(int)$old_id.';');
						$path_old=$_LW->LIVEWHALE_DIR_PATH.'/content/images/'.(int)$res2['gid'].'/'.$res2['filename'].'.'.$res2['extension'];
						$path_new=$_LW->LIVEWHALE_DIR_PATH.'/content/images/'.(int)$res2['gid'].'/'.$new_filename.'.'.$res2['extension'];
						@rename($path_old, $path_new);
						if (file_exists($path_old.'.transparent')) {
							@rename($path_old.'.transparent', $path_new.'.transparent');
						};
						if (file_exists($path_old.'.animated')) {
							@rename($path_old.'.animated', $path_new.'.animated');
						};
						if (is_dir($path_old.'.sizes')) {
							@rename($path_old.'.sizes', $path_new.'.sizes');
							foreach(@scandir($path_new.'.sizes') as $file) {
								$file=basename($file);
								if ($file[0]!='.' && strpos($file, $old_id.'_')===0) {
									$new_filename2=$new_id.substr($file, strlen($old_id));
									@rename($path_new.'.sizes/'.$file, $path_new.'.sizes/'.$new_filename2);
								};
							};
						};
						$path_old=$_LW->LIVEWHALE_DIR_PATH.'/content/images/'.(int)$res2['gid'].'/'.$res2['filename'].'.webp';
						$path_new=$_LW->LIVEWHALE_DIR_PATH.'/content/images/'.(int)$res2['gid'].'/'.$new_filename.'.webp';
						if (is_dir($path_old.'.sizes')) {
							@rename($path_old.'.sizes', $path_new.'.sizes');
							foreach(@scandir($path_new.'.sizes') as $file) {
								$file=basename($file);
								if ($file[0]!='.' && strpos($file, $old_id.'_')===0) {
									$new_filename2=$new_id.substr($file, strlen($old_id));
									@rename($path_new.'.sizes/'.$file, $path_new.'.sizes/'.$new_filename2);
								};
							};
						};
					};
				};
				break;
			case 'livewhale_files':
				if ($res2=$_LW->dbo->query('select', 'gid, filename, extension', 'livewhale_files', 'id='.(int)$new_id)->firstRow()->run()) { // update the filename and rename in the FS
					if (strpos($res2['filename'], $old_id.'_')===0 && file_exists($_LW->LIVEWHALE_DIR_PATH.'/content/files/'.(int)$res2['gid'].'/'.$res2['filename'].'.'.$res2['extension'])) {
						$new_filename=$new_id.substr($res2['filename'], strlen($old_id));
						$_LW->dbo->sql('UPDATE livewhale_files SET filename='.$_LW->escape($new_filename).' WHERE id='.(int)$new_id.';');
						$_LW->dbo->sql('UPDATE livewhale_uploads SET pid='.(int)$new_id.', filename='.$_LW->escape($new_filename).' WHERE type="files" AND pid='.(int)$old_id.';');
						@rename($_LW->LIVEWHALE_DIR_PATH.'/content/files/'.(int)$res2['gid'].'/'.$res2['filename'].'.'.$res2['extension'], $_LW->LIVEWHALE_DIR_PATH.'/content/files/'.(int)$res2['gid'].'/'.$new_filename.'.'.$res2['extension']);
					};
				};
				break;
			case 'livewhale_events':
				$_LW->dbo->sql('UPDATE livewhale_events_registrations SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.';'); // update event registrations
				break;
			case 'livewhale_events_subscriptions':
				$_LW->dbo->sql('UPDATE livewhale_events SET subscription_pid='.(int)$new_id.' WHERE subscription_pid='.(int)$old_id.';'); // update events that are in this linked calendar
				break;
			case 'livewhale_forms':
				$_LW->dbo->sql('UPDATE livewhale_forms_data SET fid='.(int)$new_id.' WHERE fid='.(int)$old_id.';'); // update form data and rename in the FS
				@rename($_LW->INCLUDES_DIR_PATH.'/data/forms/submissions/'.(int)$old_id, $_LW->INCLUDES_DIR_PATH.'/data/forms/submissions/'.(int)$new_id);
				break;
		};
		if ($_LW->dbo->hasTable($table.'2any')) { // update primary lookup table
			$_LW->dbo->sql('UPDATE '.$table.'2any SET id1='.(int)$new_id.' WHERE id1='.(int)$old_id.';');
		};
		foreach($_LW->getLookupTables() as $lookup_table) { // update all lookup tables
			if (substr($lookup_table, -4, 4)!='2any') {
				continue;
			};
			if (isset($map_types[$lookup_table]) && in_array($type, $map_types[$lookup_table])) {
				$_LW->dbo->sql('UPDATE '.$lookup_table.' SET id2='.(int)$new_id.' WHERE id2='.(int)$old_id.' AND type='.$_LW->escape($type).';');
			};
		};
		// update additional tables
		if (isset($map_types['livewhale_autosaves']) && in_array($type, $map_types['livewhale_autosaves'])) {
			$_LW->dbo->sql('UPDATE livewhale_autosaves SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND data_type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_custom_data']) && in_array($type, $map_types['livewhale_custom_data'])) {
			$_LW->dbo->sql('UPDATE livewhale_custom_data SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_languages_fields']) && in_array($type, $map_types['livewhale_languages_fields'])) {
			$_LW->dbo->sql('UPDATE livewhale_languages_fields SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_messages']) && in_array($type, $map_types['livewhale_messages'])) {
			$_LW->dbo->sql('UPDATE livewhale_messages SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND data_type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_payments_orders']) && in_array($type, $map_types['livewhale_payments_orders'])) {
			$_LW->dbo->sql('UPDATE livewhale_payments_orders SET product_id='.(int)$new_id.' WHERE product_id='.(int)$old_id.' AND product_type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_public_submissions']) && in_array($type, $map_types['livewhale_public_submissions'])) {
			$_LW->dbo->sql('UPDATE livewhale_public_submissions SET submission_id='.(int)$new_id.' WHERE submission_id='.(int)$old_id.' AND submission_type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_revisions']) && in_array($type, $map_types['livewhale_revisions'])) {
			$_LW->dbo->sql('UPDATE livewhale_revisions SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_search']) && in_array($type, $map_types['livewhale_search'])) {
			$_LW->dbo->sql('UPDATE livewhale_search SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
		};
		if (isset($map_types['livewhale_trash']) && in_array($type, $map_types['livewhale_trash'])) {
			$_LW->dbo->sql('UPDATE livewhale_trash SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
		};
		return true;
	};
};
return false;
}

protected function resetAutoIncrement($table) { // resets the auto-increment ID for a table
global $_LW;
if ($id=$_LW->dbo->query('select', 'id+1 AS next_id', $table, false, 'id DESC')->limit(1)->firstRow('next_id')->run()) {
	$_LW->dbo->sql('ALTER TABLE '.$table.' AUTO_INCREMENT='.(int)$id.';');
};
}

protected function getDataTypeForTable($table) { // gets the data type for a table
global $_LW;
static $map;
if ($table=='livewhale_events_registrations') { // special case alias
	return 'events_rsvp';
};
if (isset($map[$table])) { // return cached response if possible
	return $map[$table];
};
if (!isset($map)) { // init cache
	$map=[];
};
$tables=$_LW->ENV->tables;
$map[$table]=false;
$key=array_search($table, $tables);
if ($key!==false) {
	$map[$table]=$key;
};
return $map[$table];
}

}

?>