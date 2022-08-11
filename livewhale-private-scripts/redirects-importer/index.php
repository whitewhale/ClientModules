<?php

/*

This script is designed to import new redirects in bulk.

Instructions:

- Export a redirects.csv that contains your redirects (from/to) to import and store it in the same directory as this file.
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

echo '<div class="main no-sidebar"><h1>Import Redirects</h1>';

ini_set('auto_detect_line_endings', true);
$items=[];
$tags=[];
if ($file=fopen('./redirects.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) ) {
			if (sizeof($item)!=2) {
    			$errorTable = '<table class="table table-bordered"><tr>';
    			for ($i=0; $i<sizeof($item);$i++) {
        			$errorTable = $errorTable . '<td>' . $item[$i] . '</td>';
    			}
    			$errorTable = $errorTable . '</tr></table>';
				die('<div class="alert alert-danger"><strong>Error:</strong> Row '.($count + 1).' has more than 2 fields</div>'.$errorTable);
			};
			if (empty($item[0]) || empty($item[1])) {
    			$errorTable = '<table class="table table-bordered"><tr><td>'.$item[0].'</td><td>'.$item[1].'</td></tr></table>';
				die('<div class="alert alert-danger"><strong>Error:</strong> Row '.($count + 1).' contains incomplete data</div>'.$errorTable);
			};
			foreach($item as $key=>$val) {
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			$tmp=[];
			$tmp['from']=$item[0];
			$tmp['to']=$item[1];
			$items[]=$tmp;
		};
		$count++;
	};
	fclose($file);
} else {
    echo '<h2>No redirects file found</h2>';
    echo '<p>Please upload a redirects.csv file to /livewhale/private/. The file should contain a row for each redirect. Each row should contain two fields - the first for the "from" URL and the second for the "to" URL.</p>';
};
if (!empty($items)) {
	if (!empty($_LW->_GET['run'])) {
    	$importTable = '<table class="table table-bordered"><thead><tr><th>From</th><th>To</th><th>Status</th></tr></thead>';
	    foreach($items as $item) {
    	    $importTable = $importTable . '<tr><td>'.$item['from'].'</td>';
    	    $importTable = $importTable . '<td>'.$item['to'].'</td>';
			//echo '<p><strong>Importing redirect for '.$item['from'].'</strong><br/>';
			if ($res=$_LW->d_redirects->saveRedirect(false, $item['from'], $item['to'])) {
				if ($res=@json_decode($res, true)) {
					if (!empty($res['error'])) {
						$importTable = $importTable . '<td><span class="fa fa-times text-danger"></span> Skipped: '.$res['error'].'</td></tr>';
					} else {
    					$importTable = $importTable . '<td><span class="fa fa-check text-success"></span> Imported</td></tr>';
					};
				};
			};
		};
		$importTable = $importTable . '</table>';
		echo '<div class="alert alert-success">Import Complete</div>';
		echo $importTable;
	} else {
    	$importTable = '<table class="table table-bordered"><thead><tr><th>From</th><th>To</th></tr></thead>';
    	foreach($items as $item) {
        	$importTable = $importTable . '<tr><td>'.$item['from'].'</td><td>'.$item['to'].'</td></tr>';
        }
    	$importTable = $importTable . '</table>';
    	echo $importTable;
        echo '<p><a href="?run=1" class="btn btn-primary">Import data</a></p>';
	};
};

echo '</p></div>';

?>