<?php

$_LW->REGISTERED_APPS['airtable']=[
	'title'=>'Airtable',
	'handlers'=>['onBeforeOutput'],

	// Access token goes in core/config.php as ['CREDENTIALS']['AIRTABLE']['ACCESS_TOKEN']
	
	'custom'=>[
		'api_base' => 'https://api.airtable.com/v0/appzvj1WsjgZKAHAn/',
		'tables' => [
			'books' => 'tblU1fY4gSeTaxoPT',
			'chapters' => 'tblWxvBkl57MSskhh',
			'articles' => 'tblRpzdh8MQT7xQXz',
			'other' => 'tblDJdvZaGSj4L1ur'
		],
		'fields' => [
			'books' => ['fld6MML2fMXy8RsQl','fldlkgTPZs4aCVWsd','fldflhNhuXgJew8HR','flddiYEPoglrRLNw0','fldJc4L7mWs84LgwC', 'fldH7TrIGeB9H5wBA', 'fldW0Wx9vASMQDcaN'],
			'chapters' => ['fldtmBZnydmW4oCrv','fldLyGK2Ej4fPys0F','fldFmOxhLSPHuENRu','fld8JxxNoOG7BoRVB','fldnazsfk7fL2MJbt'],
			'articles' => ['fldjFKtD1ttTT0997','fldWn9LkdY3sKOanj','fldkS28kN8AyF3AKM','fld4ePmhb5iYeHHb3','fldUR7afBA1n1LpGl','fldAtJs1LxOeqXBaV'],
			'other' => ['fldFwvrgjw6P7oqcc','fld9uFn3zaEVEjL8A','fldIQjJLuIAmADbPC','fldi3JIPnibW7c6QG','fld1lvBPov6pSqhdD','fld9M1wlENGLzPORT']
		],
		'filter_by' => [ // The Airtable Field we request results based on
			'books' => 'Email (from Author Email)',
			'chapters' => 'Email (from Faculty Email)',
			'articles' => 'Email (from Penn Author(s))',
			'other' => 'Email (from Faculty)'
		],
		'sort_by' => [ // The Airtable Field we sort results based on
			'books' => 'Publication Year',
			'chapters' => 'Publication Date',
			'articles' => 'Publication date',
			'other' => 'Date Posted'
		]

	]
]; // configure this module

class LiveWhaleApplicationAirTable {

public function onBeforeOutput() {
	global $_LW;
	// if we're on a faculty profile page, check for Airtable results
	if (($_LW->page=='/_ingredients/templates/details/facultyprofiles.php' || $_LW->page=='/_ingredients/templates/details/facultyprofiles-old.php') && !empty($_LW->_GET['id'])) {
		$contact_info=$_LW->dbo->query('select', 'contact_info', 'livewhale_profiles', 'id='.(int)$_LW->_GET['id'])->firstRow('contact_info')->run();
		if (!empty($contact_info)) {
			$matches=[];
			preg_match('~[a-zA-Z0-9\-_\.]+?@law.upenn.edu~', $contact_info, $matches);
			if (!empty($matches[0])) {
				$email=$matches[0];
				$GLOBALS['airtable_books'] = $this->getFacultyResults($email,'books');
				$GLOBALS['airtable_chapters'] = $this->getFacultyResults($email,'chapters');
				$GLOBALS['airtable_articles'] = $this->getFacultyResults($email,'articles');
				$GLOBALS['airtable_other'] = $this->getFacultyResults($email,'other');
			};
		};
	};
}

private function getFacultyResults($email,$type) {
	global $_LW;
	
	$full_list = $_LW->getVariable('airtable_'.$type);
	$faculty_results = [];
	$html = '';
	$html_show_more = ''; // extra results to go under pagination controls
	$debug = '';

	if (!empty($full_list)) { // regenerate if something has gone wrong
		$_LW->logDebug('Airtable results empty: attempting to regenerate');
		$this->getAirtableResults();
		$full_list = $_LW->getVariable('airtable_'.$type);
	};

	foreach ($full_list as $item) {
		// $faculty_result .= '<br>Checking ' . print_r($item->fields->{$_LW->REGISTERED_APPS['airtable']['custom']['filter_by'][$type]},true);
		if (in_array($email,$item->fields->{$_LW->REGISTERED_APPS['airtable']['custom']['filter_by'][$type]})) {
			$key = (property_exists($item->fields,$_LW->REGISTERED_APPS['airtable']['custom']['sort_by'][$type]) ? $item->fields->{$_LW->REGISTERED_APPS['airtable']['custom']['sort_by'][$type]} : '9999-99-99'); // use sort_by field
			$key .= $item->id; // add as fallback, but also to cover duplicate years
			$faculty_results[$key] = $item;
			// $debug .= 'Found  '.$key . ': ' .print_r($item,true) . '<br><br>';
		};
	};
	ksort($faculty_results); // sorts by date chronologically
	$reversed_chronological = array_reverse($faculty_results); // reverses ordering

	$results_max = 9; // max number of results to show in design
	$results_total = 0;
	$pagination_number = 12;

	foreach ($reversed_chronological as $item) { // format as HTML to display
		$format = $this->formatFacultyResult($item,$type);
		if (!empty($format)) { // skip empty results
			$results_total++; // increment total
			if ($results_total <= $results_max) {
				$html .= '<div class="flex-3 other-post">' . $format . '</div>';
			} else { // after max_results put everythign into html_show_more
				$html_show_more .= '<div class="flex-3 other-post">' . $format . '</div>';
			};
		};
	};
	if (!empty($html_show_more)) {
		$html .= '<div class="lw_hidden additional-results">' . $html_show_more . '</div>';
		$html .= '<a href="#" class="publications-show-more">Show ' . ($results_total - $results_max > $pagination_number ? $pagination_number : $results_total - $results_max) . ' more...</a>';
		$html .= '<a href="#" class="publications-show-all">View all ' . $results_total . '</a>';
	};

	return $html;
}


private function formatFacultyResult($item,$type) {
	$html = '';
	switch ($type) {
		case 'books':
			// $html .= 'Result: <pre>'.print_r($item,true).'</pre><br/>';
			$html .= '<div class="faculty-book">';
			if (!empty($item->fields->{'IR URL'})) {
				$html .= '<a href="' . $item->fields->{'IR URL'} . '" target="_blank">' . $item->fields->{'Book Title'} . '</a>';
			} else if (!empty($item->fields->{'biddle catalog'})) {
				$html .= '<a href="' . $item->fields->{'biddle catalog'} . '" target="_blank">' . $item->fields->{'Book Title'} . '</a>';
			} else {
				$html .= $item->fields->{'Book Title'};
			};
			if (!empty($item->fields->{'Name (from Publishers)'})) {
				$html .= ', ' . $item->fields->{'Name (from Publishers)'}[0];
			};
			if (!empty($item->fields->{'Publication Year'})) {
				$html .= ' (' . $item->fields->{'Publication Year'} . ')';
			} else {
				$html .= ' (forthcoming)'; // when no Publication date
			};
			$html .= '</div>';
		break;
		case 'chapters':
			// $html .= 'Result: <pre>'.print_r($item,true).'</pre><br/>';
			$html .= '<div class="faculty-chapter">';
			if (!empty($item->fields->{'IR URL'})) {
				$html .= '<a href="' . $item->fields->{'IR URL'} . '" target="_blank">' . $item->fields->{'Chapter Title'} . '</a>';
			} else {
				$html .= $item->fields->{'Chapter Title'};
			};
			if (!empty($item->fields->{'Book Title'})) {
				$html .= ' in <em>' . $item->fields->{'Book Title'} . '</em>';
			};
			if (!empty($item->fields->{'Publication Date'})) {
				$html .= ' (' . substr($item->fields->{'Publication Date'},0,4) . ')';
			} else {
				$html .= ' (forthcoming)'; // when no Publication date
			};
			$html .= '</div>';
		break;
		case 'articles':
			// $html .= 'Result: <pre>'.print_r($item,true).'</pre><br/>';
			if (empty($item->fields->{'Journal Title'})) { // skip when empty
				return false;
			};
			$html .= '<div class="faculty-article">';
			if (!empty($item->fields->{'IR URL'})) {
				$html .= '<a href="' . $item->fields->{'IR URL'} . '" target="_blank">' . $item->fields->{'Article Title'} . '</a>';
			} else if (!property_exists($item->fields,'Publication date') && !empty($item->fields->{'SSRN Link'})) { // use SSRN Link for forthcoming
				$html .= '<a href="' . $item->fields->{'SSRN Link'} . '" target="_blank">' . $item->fields->{'Article Title'} . '</a>';
			} else {
				$html .= $item->fields->{'Article Title'};
			};
			if ($item->fields->{'Journal Title'} == "None") {
				$html .= ', <em>Working Paper</em>';
			} else if (!empty($item->fields->{'Journal Title'})) {
				$html .= ', <em>' . $item->fields->{'Journal Title'} . '</em>';
			};
			if (!empty($item->fields->{'Publication date'})) {
				$html .= ' (' . substr($item->fields->{'Publication date'},0,4) . ')';
			} else {
				$html .= ' (forthcoming)'; // when no Publication date
			}
			$html .= '</div>';
		break;
		case 'other':
			// $html .= 'Result: <pre>'.print_r($item,true).'</pre><br/>';
			$html .= '<div class="faculty-other">';
			$html .= $item->fields->{'Type of Work'} . ', ';
			if (!empty($item->fields->{'Link to Source'})) {
				$html .= '<a href="' . $item->fields->{'Link to Source'} . '" target="_blank">' . $item->fields->{'Name'} . '</a>';
			} else {
				$html .= $item->fields->{'Name'};
			};
			if (!empty($item->fields->{'Source Title'})) {
				$html .= ', ' . $item->fields->{'Source Title'};
			};
			if (!empty($item->fields->{'Date Posted'})) {
				$html .= ' (' . substr($item->fields->{'Date Posted'},0,4) . ')';
			};
			$html .= '</div>';
		break;
	};
	return $html;
}



public function getAirtable() { // requests and caches all Airtable values we'll be using
	global $_LW;

	// $_LW->logDebug("getAirtable 1 - " . serialize($_LW->REGISTERED_APPS['airtable']['custom']));

	foreach($_LW->REGISTERED_APPS['airtable']['custom']['tables'] as $name => $table) {

		$key = 'airtable_'.$name;
		
		$result = $this->getAirtableResults($this->getAirtableQuery($name));

		// $_LW->logDebug("getAirtable 2 - Got results for " . $key);

		echo '<h1>'.$key.'</h1>';
		echo '<pre style="display: block; overflow: auto; height: 300px; border: 1px solid black">' . print_r($result, true) . '</pre>';

		if ($result) {
			/* If new query was successful, cache the $result indefinitely */
			$_LW->setVariable($key, $result, 0, true);
			echo '<h2>Saving new ' . $key . '</h2>'; 
		} else {
			/* In all other cases, fall back to the cached variable */
			echo '<h2>Wants to fall back to cached ' . $key . '</h2>';
			$result=$_LW->getVariable($key);
			/* Re-cache that result to reset the 60min clock, so as not to run failing code over and over again */
			$_LW->setVariable($key, $result, 0, true);
		};
		
		
	};

}


public function getAirtableQuery($type) { // assemble a query based on the specified type
	global $_LW;
	$query = $_LW->REGISTERED_APPS['airtable']['custom']['api_base'];
	$query .= $_LW->REGISTERED_APPS['airtable']['custom']['tables'][$type] . '/?';
	foreach ($_LW->REGISTERED_APPS['airtable']['custom']['fields'][$type] as $field) {
		$query .= 'fields%5B%5D='.$field.'&';
	};
	// $_LW->logDebug('getAirtableQuery - ' . $query);
	return $query;
}



public function getAirtableResults($query,$offset='') { // Gets Airtable results using pagination if necessary
	global $_LW;

	if ($results=$_LW->getUrl($query.(!empty($offset) ? 'offset='.$offset : ''), true, false, [CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$_LW->CONFIG['CREDENTIALS']['AIRTABLE']['ACCESS_TOKEN']]])) { // fetch events from API

		$json = @json_decode($results);
		
		if (!empty($json->offset)) { // Check for pagination, request more if needed
			$more_results = $this->getAirtableResults($query,$json->offset);
			$json->records = array_merge($json->records,$more_results);
		};
		
		return $json->records;

	};

	return '';
}



}

?>