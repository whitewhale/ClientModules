<?php

/*

This script is designed to import new groups in bulk.

Instructions:

- Export a groups.csv that contains your groups to import and store it in the same directory as this file. (See sample CSV.)
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=array();
if ($file=fopen('./groups.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=2) {
				die('Row found that is not 2 fields long: '.var_export($item, true));
			};
			if (empty($item[0]) || empty($item[1])) {
				die('Row found with incomplete data: '.var_export($item, true));
			};
			foreach($item as $key=>$val) {
				if ($val[0]=='/') {
					$val=substr($val, 1);
				};
				if (substr($val, -1, 1)=='/') {
					$val=substr($val, 0, -1);
				};
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=array();
			$tmp['fullname']=$item[0];
			$tmp['directory']=$item[1];
			$items[]=$tmp;
		};
		$count++;
	};
	fclose($file);
};
if (!empty($items)) {
	foreach($items as $item) {
		if ($_LW->dbo->query('select', '1', 'livewhale_groups', 'fullname='.$_LW->escape($item['fullname']))->exists()->run()) {
			die('Group '.$item['fullname'].' already exists.');
		};
	};
	foreach($items as $item) {
		if (!empty($_LW->_GET['run'])) {
			if ($id=$_LW->create('groups', array(
				'fullname'=>$item['fullname'],
				'directory'=>$item['directory']
			))) {
				echo 'Created group '.$id.'<br/>';
			}
			else {
				echo $_LW->error.'<br/>';
			};
		};
	};
};

?>