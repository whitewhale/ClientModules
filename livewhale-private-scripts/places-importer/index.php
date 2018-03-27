<?php

/*

This script is designed to import new places in bulk.

Instructions:

- Export a places.csv that contains your places to import and store it in the same directory as this file. (See sample CSV.)
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=array();
$tags=array();
if ($file=fopen('./places.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=4) {
				die('Row found that is not 4 fields long: '.var_export($item, true));
			};
			if (empty($item[0]) || empty($item[1]) || empty($item[2])) {
				die('Row found with incomplete data: '.var_export($item, true));
			};
			foreach($item as $key=>$val) {
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=array();
			$tmp['title']=$item[0];
			$tmp['latitude']=$item[1];
			$tmp['longitude']=$item[2];
			$tmp['keywords']=$item[3];
			$items[]=$tmp;
		};
		$count++;
	};
	fclose($file);
};
if (!empty($items)) {
	foreach($items as $item) {
		if (!empty($_LW->_GET['run'])) {
			if ($id=$_LW->create('places', array(
				'title'=>$item['title'],
				'gid'=>'',
				'latitude'=>$item['latitude'],
				'longitude'=>$item['longitude'],
				'keywords'=>$item['keywords']
			))) {
				echo 'Created place '.$id.'<br/>';
			}
			else {
				echo $_LW->error.'<br/>';
			};
		};
	};
};

?>