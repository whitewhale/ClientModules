<?php

/*

This script is designed to import new LiveWhale locations in bulk.

Instructions:

1)   Export a locations.csv that contains your locations to import. The CSV should contain 4 columns as follows:

- Title
- Public Name (LiveWhale custom field: public_name) or blank field
- Street Address (LiveWhale custom field: street_address) or blank field
- Keywords

Note: Additional fields can be added, but the script must be modified to accommodate.

2)   Upload locations.csv into the same directory as this file. (See sample CSV.)

3)   Load this URL in a web browser to run script and validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.


*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=array();
$tags=array();
$errors=[];
if ($file=fopen('./locations.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=4) {
				$errors[]='Row found that is not 4 fields long: '.var_export($item, true);
			};
			if (empty($item[0])) {
				$errors[]='Row found with incomplete data: '.var_export($item, true);
			};
			foreach($item as $key=>$val) {
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=array();
			$tmp['title']=$item[0];
			$tmp['latitude']=0;
			$tmp['longitude']=0;
			if (!empty($item[2])) {
				if ($coords=$_LW->geocodeAddress($item[2])) {
					$tmp['latitude']=$coords['latitude'];
					$tmp['longitude']=$coords['longitude'];
				}
				else {
					$errors[]='Row found with non-parsable address coordinates: '.var_export($item, true);
				};
			};

			$tmp['custom_public_name']=$item[1];
			$tmp['custom_street_address']=$item[2];
			$tmp['keywords']=$item[3];
			if (!empty($item[0])) {
				$items[]=$tmp;
			};
		};
		$count++;
	};
	fclose($file);
};
if (!empty($errors)) {
	echo 'Errors:<br/>'.implode('<br/>', $errors);
};
if (!empty($items) && empty($errors)) {
	foreach($items as $item) {
		if (!empty($_LW->_GET['run'])) {
			if ($id=$_LW->create('places', array(
				'title'=>$item['title'],
				'gid'=>'',
				'latitude'=>$item['latitude'],
				'longitude'=>$item['longitude'],
				'keywords'=>$item['keywords'],
				'custom_public_name'=>$item['custom_web_address'],
				'custom_street_address'=>$item['custom_street_address']
			))) {
				echo 'Created location '.$id.'<br/>';
			}
			else {
				echo $_LW->error.'<br/>';
			};
		};
	};
};

?>