<?php

/*

This script is designed to import new groups in bulk.

Instructions:

- Export a groups.csv that contains your groups to import and store it in the same directory as this file. (See sample CSV.)
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=[];
if ($file=fopen('./groups.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=4) {
				die('Row found that is not 4 fields long: '.var_export($item, true));
			};
			if (empty($item[0])) {
				die('Row found with incomplete data: '.var_export($item, true));
			};
			foreach($item as $key=>$val) {
				if ($key===2) {
					if ($val[0]=='/') {
						$val=substr($val, 1);
					};
					if (substr($val, -1, 1)=='/') {
						$val=substr($val, 0, -1);
					};
				};
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=[];
			$tmp['fullname']=$item[0];
			$tmp['fullname_public']=$item[1];
			$tmp['directory']=$item[2];
			$tmp['template_path']=$item[3];
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
			if ($id=$_LW->create('groups', [
				'fullname'=>$item['fullname'],
				'fullname_public'=>$item['fullname_public'],
				'directory'=>$item['directory'],
				'template_path'=>$item['template_path']
			])) {
				echo 'Created group '.$id.'<br/>';
			}
			else {
				echo $_LW->error.'<br/>';
			};
		};
	};
};

?>