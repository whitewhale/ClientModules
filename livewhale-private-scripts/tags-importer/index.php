<?php

/*

This script is designed to import new places in bulk.

Instructions:

- Export a tags.csv that contains your tags to import and store it in the same directory as this file. (See sample CSV.)
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=array();

// Get gids for comparing to group titles
$gids=array();
$groups=$_LW->read('groups');
foreach ($groups as $group) {
	$gids[$group['id']] = $group['title'];
}

if ($file=fopen('./tags.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=3) {
				die('Row found that is not 3 fields long: '.var_export($item, true));
			};
			if (empty($item[0])) {
				die('Row found with incomplete data: '.var_export($item, true));
			};
			foreach($item as $key=>$val) {
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=array();
			$tmp['title']=$item[0];
			$tmp['is_starred']=(strtolower($item[1]) == 'true' || $item[1] == '1') ? '1' : false; 
			$tmp['gid']=(!empty($item[2])) ? array_search($item[2], $gids) : '';
			$items[]=$tmp;
		};
		$count++;
	};
	fclose($file);
};

if (!empty($items)) {
	foreach($items as $item) {
		if (!empty($_LW->_GET['run'])) {
			if ($id=$_LW->create('tags', array(
				'title'=>$item['title'],
				'is_starred'=>$item['is_starred'],
				'gid'=>$item['gid']
			))) {
				echo 'Created tag '.$id.' (' . $item['title'] . ', ' . ((!empty($item['gid'])) ? 'gid: ' . $item['gid'] : 'Global Tag') . ')<br/>';
			}
			else {
				echo '<br /><b>'.$_LW->error.'</b><br/>' . print_r($item) . '<br /><br />';
			};
		} else {
			echo 'Ready to import: ' . $item['title'] . ((!empty($item['gid'])) ? ' (gid: ' . $item['gid'] . ')' : ' (Global Tag)') . '<br/>';
		}
	};
};

?>