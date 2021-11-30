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
		'import_url'=>'https://events.susqu.edu/live/transfer/export/events', // source url to import from
		'gid'=>25 // group ID for the local destination group
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
				curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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
					@unlink($path); // delete export
					if (empty($_LW->REGISTERED_MESSAGES['failure'])) { // and give error if we don't already have one
						$_LW->REGISTERED_MESSAGES['failure'][]='Could not retrieve export from source server ('.$code.').';
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
							$msg.='<li><strong>'.str_replace(['events_', 'event subscriptions', 'event categories', 'places'], ['event ', 'linked calendars', 'event types', 'locations'], $key).'</strong>: '.implode(', ', $display).'</li>';
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
			foreach($_LW->dbo->query('select', '*', 'livewhale_'.$type, false, 'id ASC')->run() as $res2) { // add each item of this type
				if ($type==='events' && !empty($res2['subscription_pid'])) { // and any linked calendars
					if (!isset($unique_items['livewhale_events_subscriptions-'.$res2['subscription_pid']])) {
						if ($res3=$_LW->dbo->query('select', '*', 'livewhale_events_subscriptions', 'id='.(int)$res2['subscription_pid'])->firstRow()->run()) {
							$line=[
								'type'=>'events_subscriptions',
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
								$line=[
									'type'=>substr($linked_table, 10),
									'table'=>$linked_table,
									'group'=>[
										'id'=>(!empty($res4['gid']) ? $res4['gid'] : false),
										'title'=>(!empty($res4['gid']) ? $group_titles[$res4['gid']] : false)
									],
									'data'=>$res4
								];
								@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n");
								$unique_items[$linked_table.'-'.$res4['id']]='';
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
				$line=[ // add the main item
					'type'=>$type,
					'table'=>'livewhale_'.$type,
					'group'=>[
						'id'=>(!empty($res2['gid']) ? $res2['gid'] : false),
						'title'=>(!empty($res2['gid']) ? $group_titles[$res2['gid']] : false)
					],
					'data'=>$res2
				];
				@fwrite($fp, @base64_encode(gzdeflate(serialize($line)))."\n"); // write the record
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
			};
			fclose($fp); // close file
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
					if ($line['type']!='lookup') { // if not a lookup record
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
					$_LW->dbo->sql('UPDATE '.$table.' SET id='.(int)$new_id.' WHERE id='.(int)$old_id.';'); // update the main table
					if ($table=='livewhale_events_categories') { // if preserving an event type, update the ID in the authorized event types per group
						foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="event_types"')->run() as $res3) {
							$res3['value']=explode(',', $res3['value']);
							if (in_array((int)$old_id, $res3['value'])) {
								$res3['value'][array_search((int)$old_id, $res3['value'])]=(int)$new_id;
								$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res3['value']))], 'gid='.(int)$res3['gid'].' AND name='.$_LW->escape($res3['name']))->run();
							};
						};
					};
					if ($table=='livewhale_news_categories') { // if preserving a news category, update the ID in the authorized news categories per group
						foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="news_categories"')->run() as $res3) {
							$res3['value']=explode(',', $res3['value']);
							if (in_array((int)$old_id, $res3['value'])) {
								$res3['value'][array_search((int)$old_id, $res3['value'])]=(int)$new_id;
								$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res3['value']))], 'gid='.(int)$res3['gid'].' AND name='.$_LW->escape($res3['name']))->run();
							};
						};
					};
					if ($_LW->dbo->hasTable($table.'2any')) { // update primary lookup table
						$_LW->dbo->sql('UPDATE '.$table.'2any SET id1='.(int)$new_id.' WHERE id1='.(int)$old_id.';');
					};
					foreach($_LW->getLookupTables() as $lookup_table) { // update all lookup tables
						if (substr($lookup_table, -4, 4)!='2any') {
							continue;
						};
						$_LW->dbo->sql('UPDATE '.$lookup_table.' SET id2='.(int)$new_id.' WHERE id2='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					};
					// update additional tables
					$_LW->dbo->sql('UPDATE livewhale_autosaves SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND data_type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_custom_data SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_languages_fields SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_messages SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND data_type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_payments_orders SET product_id='.(int)$new_id.' WHERE product_id='.(int)$old_id.' AND product_type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_public_submissions SET submission_id='.(int)$new_id.' WHERE submission_id='.(int)$old_id.' AND submission_type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_revisions SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_search SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_trash SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					$_LW->dbo->sql('UPDATE livewhale_uploads SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.' AND type='.$_LW->escape($type).';');
					if ($type=='events') { // if main type is events, update registrations
						$_LW->dbo->sql('UPDATE livewhale_events_registrations SET pid='.(int)$new_id.' WHERE pid='.(int)$old_id.';');
					}
					else if ($type=='events_subscriptions') { // else if main type is linked calendar
						$_LW->dbo->sql('UPDATE livewhale_events SET subscription_pid='.(int)$new_id.' WHERE subscription_pid='.(int)$old_id.';'); // update events that are in this linked calendar
					};
				};
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
		if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/transfer')) {
			@mkdir($_LW->INCLUDES_DIR_PATH.'/data/transfer');
		};
		if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts') || filemtime($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts')<$_SERVER['REQUEST_TIME']-300) { // if file not already being processed
			@touch($_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type.'.ts'); // flag as processing
			$path=$_LW->INCLUDES_DIR_PATH.'/data/transfer/'.$type; // set path to read
			if ($action=='preserve') { // if preserve option was chosen
				$this->preserveContent($path); // preserve all existing content types impacted by the import by ID-shifting their data
				if ($type=='events') { // clear caches
					$_LW->d_events->clearEventsCategoriesCache();
				}
				else if ($type=='news') {
					$_LW->d_news->clearNewsCategoriesCache();
				};
			};
			$imported_ids=[];
			if ($fp=@fopen($path, 'r')) { // open the file
				while (($line=@fgets($fp, 4096))!==false) {
					if ($line=trim(@gzinflate(base64_decode($line)))) {
						if ($line=@unserialize($line)) {
							if (!empty($line['type']) && !empty($line['table']) && isset($line['group']) && isset($line['data'])) { // for each valid line
								if ($line['type']!='lookup') { // if not a lookup record
									$_LW->dbo->query('delete', 'livewhale_trash', 'id='.(int)$line['data']['id'])->run(); // always delete an existing trash entry for the imported item, because an item can be in the trash or not, but not both
									if (isset($line['data']['gid'])) { // override destination group if necessary
										$line['data']['gid']=$this->getImportGID($line['group']);
									};
									$status=$this->getItemStatus($line['table'], $line['data']['id'], $line['data']); // get status of import
									if ($status=='created' || $status=='changed') { // for created or changed items (but not skipped)
										if ($status=='changed' && $action=='delete') { // if item was changed and delete option was chosen
											$_LW->delete($line['type'], $line['data']['id'], false); // delete an existing item first but do not trash
										};
										$line['data_insert']=$line['data'];
										foreach($line['data_insert'] as $key=>$val) {
											$line['data_insert'][$key]=$_LW->escape($val);
										};
										if ($line['table']=='livewhale_events_categories' || $line['table']=='livewhale_news_categories') { // for these types
											if ($res2=$_LW->dbo->query('select', 'id', $line['table'], 'title='.$_LW->escape($line['data']['title']))->firstRow()->run()) { // check if there is still another by the same title but with a different ID (after preserving existing items or deletion of same-ID)
												$_LW->dbo->query('delete', $line['table'], 'id='.(int)$res2['id'])->run(); // delete it
												$_LW->dbo->query('update', $line['table'].'2any', ['id1'=>(int)$line['data']['id']], 'id1='.(int)$res2['id'])->run(); // and relink its assignments to the one we're importing
											};
											if ($line['table']=='livewhale_events_categories') { // add the ID to the authorized event types per group
												foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="event_types"')->run() as $res2) {
													$res2['value']=explode(',', $res2['value']);
													if (!in_array((int)$line['data']['id'], $res2['value'])) {
														$res2['value'][]=(int)$line['data']['id'];
														$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
													};
												};
											}
											else if ($line['table']=='livewhale_news_categories') { // add the ID to the authorized news categories per group
												foreach($_LW->dbo->query('select', 'gid, name, value', 'livewhale_groups_settings', 'name="news_categories"')->run() as $res2) {
													$res2['value']=explode(',', $res2['value']);
													if (!in_array((int)$line['data']['id'], $res2['value'])) {
														$res2['value'][]=(int)$line['data']['id'];
														$_LW->dbo->query('update', 'livewhale_groups_settings', ['value'=>$_LW->escape(implode(',', $res2['value']))], 'gid='.(int)$res2['gid'].' AND name='.$_LW->escape($res2['name']))->run();
													};
												};
											};
										};
										$_LW->dbo->query('insert', $line['table'], $line['data_insert'])->run(); // insert the item
										if (!isset($imported_ids[$line['type']])) {
											$imported_ids[$line['type']]=[];
										};
										$imported_ids[$line['type']][]=$line['data']['id']; // keep track of imported IDs for deletion of unaffected content on destination server
										if ($_LW->hasHandler('data_type', $line['type'], 'onCreate')) { // trigger post-creation steps
											$_LW->callHandler('data_type', $line['type'], 'onCreate', [$line['data']['id']]);
											$_LW->callHandlersByType('application', 'onAfterCreate', [$line['type'], $line['data']['id']]);
										};
										if ($line['table']=='livewhale_images' || $line['table']=='livewhale_files') { // if creating an image or file
											if (!isset($url_info)) {
												$url_info=@parse_url($_LW->REGISTERED_MODULES['transfer']['custom']['import_url']);
											};
											$image_data=[
												'url'=>$url_info['scheme'].'://'.$url_info['host'].'/live/'. ($line['table']=='livewhale_images'  ? 'images' : 'files').'/'.$line['data']['id']
											];
											$last_modified=$_LW->dbo->query('select', 'last_modified', $line['table'], 'id='.(int)$line['data']['id'])->firstRow('last_modified')->run(); // get before last_modified
											$_LW->update('images', $line['data']['id'], $image_data); // import the file itself
											$_LW->dbo->query('update', $line['table'], ['last_modified'=>$_LW->escape($last_modified)], 'id='.(int)$line['data']['id'])->run(); // restore last_modified (so that it can be skipped next time)
										};
									};
								}
								else { // else for lookup records
									$line['data_insert']=$line['data'];
									foreach($line['data_insert'] as $key=>$val) {
										$line['data_insert'][$key]=$_LW->escape($val);
									};
									$_LW->dbo->query('insert', $line['table'], $line['data_insert'])->run(); // insert the item
								};
							};
						};
					};
				};
				fclose($fp);
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

}

?>