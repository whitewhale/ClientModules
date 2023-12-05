<?php
require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

if (!empty($LIVE_URL['REQUEST'])) { // if valid request
	$request=array_shift($LIVE_URL['REQUEST']); // get command name
	switch($request) {
		case 'debug-sync':
		
			$_LW->a_airtable->getAirtable();

		break;

		case 'debug-all':
			echo '<h1>Airtable debug</h1>';
			foreach (['books','chapters','articles','other'] as $type) {
				$full_list = $_LW->getVariable('airtable_'.$type);

				echo 'Full list of '.$type.': <pre>';
				print_r($full_list);
				echo '</pre><br>';
			};

		break;


		case 'debug-results':
		
			$type = 'articles';
			$email = 'rshuldiner@law.upenn.edu';

			// $full_list = $_LW->getVariable('airtable_'.$type);
			$full_list=json_decode(@file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/airtable/airtable_'.$type.'.json'));

			echo 'Full list: <pre>';
			print_r($full_list);
			echo '</pre><br><br>Results:<br>';

			$faculty_results = [];
			$debug = '';

			foreach ($full_list as $item) {
				// echo '<br>Checking ' . print_r($item);
				if (in_array($email,$item->fields->{'Email (from Penn Author(s))'})) {
					$key = (property_exists($item->fields,'Publication date') ? $item->fields->{'Publication date'} : '9999-99-99'); // use sort_by field
					$key .= $item->id; // add as fallback, but also to cover duplicate dates
					$faculty_results[$key] = $item;
					echo '<br/>Found  '.$key . ': ' .print_r($item,true) . '<br><br>';
				};
			};
			
			echo '<pre>';
			print_r($faculty_results);
			// ksort($faculty_results); // sorts by date chronologically
			// $reversed_chronological = array_reverse($faculty_results); // reverses ordering




		break;

	};
};
exit;

?>