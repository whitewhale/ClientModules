<?php

/*

This script is designed to import new places in bulk.

Instructions:

- Export a quickaccess.csv that contains your links to import and store it in the same directory as this file. (See sample CSV.)
- Run the script to validate all data. If data validates properly, then perform the final import by adding ?run=1 to the url.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

ini_set('auto_detect_line_endings', true);

$pages=array();


// LOAD SAVED QA RESULTS

if ($saved_pages=@file_get_contents($_LW->LIVEWHALE_DIR_PATH.'/content/quickaccess/quickaccess.json')) {
	if ($saved_pages=@json_decode($saved_pages)) {
		// start with existing QA results; comment out to overwrite
		foreach ($saved_pages->pages as $page) {
			$pages[]=[
				'title'=>$page->title,
				'link'=>$page->link,
				'keywords'=>$page->keywords,
				'thumbnail'=>''
			];
		}
	}
}


// ADD QA RESULTS FROM CSV

if ($file=fopen('./quickaccess.csv', 'r')) {
	$count=0;
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) {
		if (!empty($item) && !empty($count)) {
			if (sizeof($item)!=3) {
				die('Row found that is not 3 fields long: '.var_export($item, true));
			};
			if (empty($item[0]) || empty($item[1])) {
				die('Row found with incomplete data: '.var_export($item, true));
			};
			foreach($item as $key=>$val) {
				$item[$key]=$_LW->setFormatSanitize(trim($val));
			};
			

			$item[0]=ucwords($item[0]); // always capitalize the link name
			if (@parse_url($item[1], PHP_URL_HOST)=='') { // always format urls with host
				$item[1]='http://'.$_LW->CONFIG['HTTP_HOST'].(substr($item[1], 0, 1)!='/' ? '/' : '').$item[1];
			};
			$item[2]=preg_replace(['~\s+~', '~[,]{2,}~'], [', ', ','], $item[2]); // format keywords with commas
			
			$pages[]=[
				'title'=>$item[0],
				'link'=>$item[1],
				'keywords'=>str_replace('&quot;', '', $_LW->setFormatClean($item[2])),
				'thumbnail'=>''
			];
		};
		$count++;
	};
	fclose($file);
};
if (!empty($pages)) {
	usort($pages, function($a, $b) {return $a['title']==$b['title'] ? 0 : ($a['title']<$b['title'] ? -1 : 1);}); // order pages by title

	if (empty($_LW->_GET['run'])) {
	
		// Test run
		echo "<h1>Add ?run=1 to URL to import:</h1>";
		echo '<pre>'.print_r($pages,true).'</pre>';
	
	} else {
		
		// Actual run; copied from core/modules/quickaccess/private.data.quickaccess.php
		
		echo "<h1>Running import...</h1>";
		$data=json_encode(['pages'=>$pages]); // encode the QA content
		@file_put_contents($_LW->LIVEWHALE_DIR_PATH.'/content/quickaccess/quickaccess.json', $data); // save the QA content
		$_LW->dbo->query('insert', 'livewhale_revisions', ['id'=>'NULL', 'pid'=>1, 'uid'=>(int)$_SESSION['livewhale']['manage']['uid'], 'type'=>'"quickaccess"', 'date'=>'NOW()', 'revision'=>$_LW->escape($data), 'search'=>'NULL'])->run(); // save the revision
		$_LW->dbo->sql('SET @ids:=(SELECT GROUP_CONCAT(id) FROM livewhale_revisions WHERE pid=1 AND type="quickaccess" ORDER BY date DESC LIMIT '.$_LW->CONFIG['MAX_REVISIONS'].');'); // get all revisions older than MAX_REVISIONS copies
		$_LW->dbo->query('delete', 'livewhale_revisions', 'pid=1 AND type="quickaccess" AND NOT FIND_IN_SET(id, @ids)')->flags('quick')->run(); // remove old revisions
		$output='<div class="lw_qa_curated_results">'."\n";
		foreach($pages as $page) {
			$output.="\t".'<a href="'.$page['link'].'"'.(!empty($page['keywords']) ? ' data-keywords="'.$page['keywords'].'"' : '').'>'.$page['title'].'</a>'."\n";
		};
		$output.='</div>';
		@file_put_contents($_LW->LIVEWHALE_DIR_PATH.'/content/quickaccess/quickaccess.html', $output); // save the QA content
		$_LW->dbo->query('insert', 'livewhale_revisions', ['id'=>'NULL', 'pid'=>2, 'uid'=>(int)$_SESSION['livewhale']['manage']['uid'], 'type'=>'"quickaccess"', 'date'=>'NOW()', 'revision'=>$_LW->escape($output), 'search'=>'NULL'])->run(); // save the revision
		$_LW->dbo->sql('SET @ids:=(SELECT GROUP_CONCAT(id) FROM livewhale_revisions WHERE pid=2 AND type="quickaccess" ORDER BY date DESC LIMIT '.$_LW->CONFIG['MAX_REVISIONS'].');'); // get all revisions older than MAX_REVISIONS copies
		$_LW->dbo->query('delete', 'livewhale_revisions', 'pid=2 AND type="quickaccess" AND NOT FIND_IN_SET(id, @ids)')->flags('quick')->run(); // remove old revisions
		if (!empty($_LW->CONFIG['LB_MASTER'])) { // if this is load balanced
			$_LW->refreshENV(true, true, false); // refresh the public ENV to sync all uploads
		};

		echo "<h2>Done.</h2>";

	};
};

?>