<?php

/*

This script is designed to import new redirects in bulk.

Instructions:

- Export a redirects.csv that contains your redirects (from/to) to import and store it in the same directory as this file.
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);
$items=array();
$tags=array();
if ($file=fopen('./redirects.csv', 'r')) {
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
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=array();
			$tmp['from']=$item[0];
			$tmp['to']=$item[1];
			$items[]=$tmp;
		};
		$count++;
	};
	fclose($file);
};
if (!empty($items)) {
	foreach($items as $item) {
		if (!empty($_LW->_GET['run'])) {
			echo '<strong>Importing redirect for '.$item['from'].'</strong><br/>';
			if ($res=$_LW->d_redirects->saveRedirect(false, $item['from'], $item['to'])) {
				if ($res=@json_decode($res, true)) {
					if (!empty($res['error'])) {
						echo $res['error'].'<br/>';
					};
				};
			};
		};
	};
};

?>