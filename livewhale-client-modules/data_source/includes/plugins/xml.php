<?php

/* XML connector for the data source widget */

class LiveWhaleDataSourcePluginXml {
public $error='';
protected $fields=[];
protected $rows=[];

public function isSupported() { // checks if this database type is supported on the current PHP installation
return true; // XML is always supported, due to native PHP support
}

public function isValidSource($source) { // checks if the source has all required fields defined for this type
if ((!empty($source['path']) || !empty($source['url'])) && !empty($source['xpath'])) {
	return true;
}
else {
	$this->error='Source must specify an XPath query for results and either a path or URL to an XML file.';
	return false;
};
}

public function getHandle($source, $args) { // opens a XML connection
global $_LW;
$contents=(!empty($source['path']) ? @file_get_contents($source['path']) : $_LW->getUrl($source['url']));
if (!empty($contents)) { // if XML exists
	$xml=$_LW->getNew('xpresent');
	if ($xml->loadXML($contents)) { // load XML
		$count=0;
		foreach($xml->elements((string)$source['xpath']) as $result) { // for each result
			if (!empty($result)) {
				$arr=[];
				if ($result->hasChildNodes()) {
					foreach($result->children as $field=>$value) { // for each key/val pair
						$field=$_LW->setFormatSanitize(trim((string)$field)); // format the key and val
						$value=$_LW->setFormatSanitize(trim($_LW->getInnerXML($value->toXHTML())));
						if (!empty($count) && !in_array($field, $this->fields[$source['name']])) { // skip any fields that aren't present in the 1st entry
							continue;
						};
						$arr[$field]=$value;
					};
					$this->rows[$source['name']][]=$arr;
					if (!empty($arr) && empty($count)) { // when the first entry is encountered, use that to track the field names
						$this->fields[$source['name']]=array_flip(array_keys($arr));
					};
				};
			};
		};
		return true;
	}
	else { // else give error if XML is not readable
		$this->error='Connection error: XML file is not in the correct format.';
	};
}
else { // else give error if XML does not exist
	$this->error='Connection error: XML file does not exist.';
};
return false;
}

public function getResults($source, $args) { // fetches results
global $_LW;
$output=[];
if (!isset($args['search']) && !isset($args['exclude_search'])) { // if not filtering
	$output=$this->rows[$source['name']]; // add all rows to output
}
else { // else if filtering
	foreach(['search', 'exclude_search'] as $search_type) {
		if (!empty($args[$search_type])) { // build search query
			$search_mode=(!empty($args[$search_type.'_mode']) && in_array($args[$search_type.'_mode'], ['any', 'all'])) ? ((string)$args[$search_type.'_mode']=='all' ? 'AND' : 'OR') : 'AND'; // get search mode
			if (!is_array($args[$search_type])) {
				$args[$search_type]=[$args[$search_type]];
			};
			foreach($this->rows[$source['name']] as $row) { // for each row
				$is_match=($search_mode==='OR' ? false : true); // set match default based on search_mode
				foreach($args[$search_type] as $search_terms) { // for each search term
					$search_terms=(string)$search_terms;
					$search_field='';
					$search_cmp='';
					if (strpos($search_terms, '=')!==false) {
						$search_cmp='=';
					}
					else if (strpos($search_terms, '&gt;')!==false) {
						$search_terms=preg_replace('~&gt;~', '>', $search_terms, 1);
						$search_cmp='>';
					}
					else if (strpos($search_terms, '&lt;')!==false) {
						$search_terms=preg_replace('~&lt;~', '<', $search_terms, 1);
						$search_cmp='<';
					};
					if (!empty($search_cmp)) { // if there was a search operator
						$pos=strpos($search_terms, $search_cmp);
						$search_field=substr($search_terms, 0, $pos);
						$search_terms=substr($search_terms, $pos+1);
						if (!empty($search_terms) && !empty($search_field) && isset($this->fields[$source['name']][$search_field])) {
							if ($search_mode==='AND') { // if any not satisfied, declare not a match
								if ($search_cmp=='=') { // if equals
									if (strpos($search_terms, '*')===false) { // and no wildcard
										if ($row[$search_field]!=$search_terms) { // compare
											$is_match=false;
											break;
										};
									}
									else { // else do regex if wildcard comparison
										$pattern='~^'.preg_quote(str_replace('*', '.*?', $search_terms), '~').'$~';
										if (!preg_match($pattern, $$row[$search_field])) {
											$is_match=false;
											break;
										};
									};
								}
								else if ($search_cmp=='<' && !((float)$row[$search_field]<(float)$search_terms)) { // else do < comparison
									$is_match=false;
									break;
								}
								else if ($search_cmp=='>' && !((float)$row[$search_field]>(float)$search_terms)) { // else do > comparison
									$is_match=false;
									break;
								};
							}
							else if ($search_mode==='OR') { // if any satisfied, declare a match
								if ($search_cmp=='=') { // if equals
									if (strpos($search_terms, '*')===false) { // and no wildcard
										if ($row[$search_field]==$search_terms) { // compare
											$is_match=true;
											break;
										};
									}
									else { // else do regex if wildcard comparison
										$pattern='~^'.preg_quote(str_replace('*', '.*?', $search_terms), '~').'$~';
										if (preg_match($pattern, $$row[$search_field])) {
											$is_match=true;
											break;
										};
									};
								}
								else if ($search_cmp=='<' && (float)$row[$search_field]<(float)$search_terms) { // else do < comparison
									$is_match=true;
									break;
								}
								else if ($search_cmp=='>' && (float)$row[$search_field]>(float)$search_terms) { // else do > comparison
									$is_match=true;
									break;
								};
							};
						};
					};
				};
				if ((!empty($is_match) && $search_type==='search') || (empty($is_match) && $search_type==='exclude_search')) { // if it was a match, add to results
					$output[]=$row;
				};
			};
		};
	};
};
if (!empty($output)) { // if there were results
	if (!empty($args['sort_field'])) { // sort by a specified sort field
		if (is_array($args['sort_field'])) {
			$args['sort_field']=current($args['sort_field']);
		};
		if (is_scalar($args['sort_field']) && isset($this->fields[$source['name']][$args['sort_field']])) {
			$output=$_LW->sortByChild($output, $args['sort_field']);
		};
	};
	if (!empty($args['max']) && preg_match('~^[0-9]+$~', $args['max']) && sizeof($output)>(int)$args['max']) { // apply a max
		$output=array_slice($output, 0, (int)$args['max']);
	};
};
return $output;
}

}

?>