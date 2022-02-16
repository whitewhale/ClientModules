<?php

/* CSV connector for the data source widget */

class LiveWhaleDataSourcePluginCsv {
public $error='';
protected $tables=[];
protected $rows=[];

public function isSupported() { // checks if this database type is supported on the current PHP installation
return true; // CSV is always supported, due to native PHP support
}

public function isValidSource($source) { // checks if the source has all required fields defined for this type
if (!empty($source['path']) && !empty($source['separator']) && !empty($source['enclosure'])) {
	return true;
}
else {
	$this->error='Source must specify a minimum of: path, separator, enclosure. Optional: escape.';
	return false;
};
}

public function getHandle($source, $args) { // opens a CSV connection
global $_LW;
if (file_exists($source['path'])) { // if CSV exists
	ini_set('auto_detect_line_endings', true);
	if ($fp=@fopen($source['path'], 'r')) { // open CSV
		$count=0;
		while (($item=fgetcsv($fp, 0, $source['separator'], $source['enclosure'], (!empty($source['escape']) ? $source['escape'] : null)))!==false) { // get all rows
			if (!empty($item)) {
				foreach($item as $key=>$val) {
					$item[$key]=$_LW->setFormatSanitize(trim($val));
				};
				if ($count===0) {
					$this->tables[$source['name']]['csv']=array_flip($item);
				}
				else if (sizeof($this->tables[$source['name']]['csv'])===sizeof($item)) {
					$this->rows[$source['name']][]=array_combine(array_keys($this->tables[$source['name']]['csv']), $item);
				};
			};
			$count++;
		};
		fclose($fp);
		return true;
	}
	else { // else give error if CSV is not readable
		$this->error='Connection error: CSV file is not readable.';
	};
}
else { // else give error if CSV does not exist
	$this->error='Connection error: CSV file does not exist.';
};
return false;
}

protected function getTables($source) { // gets all tables for the specified database
$this->tables[$source['name']]=['csv'=>[]]; // CSV only has one virtual table
}

public function getFieldsForTable($source, $table) { // gets all fields for the specified table
if (!isset($this->tables[$source['name']])) { // confirm that the specified table is valid
	$this->getTables($source);
};
return (!empty($this->tables[$source['name']][$table]) ? $this->tables[$source['name']][$table] : []);
}

public function getResults($source, $args) { // fetches results
global $_LW;
$output=[];
if (!isset($this->tables[$source['name']])) { // confirm that the specified table is valid
	$this->getTables($source);
};
if (isset($this->tables[$source['name']][$args['table']])) {
	if (empty($this->tables[$source['name']][$args['table']])) {
		$this->getFieldsForTable($source, $args['table']);
	};
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
							if (!empty($search_terms) && !empty($search_field) && isset($this->tables[$source['name']][$args['table']][$search_field])) {
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
			if (is_scalar($args['sort_field']) && isset($this->tables[$source['name']]['csv'][$args['sort_field']])) {
				$output=$_LW->sortByChild($output, $args['sort_field']);
			};
		};
		if (!empty($args['max']) && preg_match('~^[0-9]+$~', $args['max']) && sizeof($output)>(int)$args['max']) { // apply a max
			$output=array_slice($output, 0, (int)$args['max']);
		};
	};
};
return $output;
}

}

?>