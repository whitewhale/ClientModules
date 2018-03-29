<?php

/* Oracle database connector for the data source widget */

class LiveWhaleDataSourcePluginOracle {
public $error='';
protected $tables=array();

public function isSupported() { // checks if this database type is supported on the current PHP installation
if (extension_loaded('oci8')) {
	return true;
}
else {
	$this->error='The OCI8 extension for PHP is not installed.';
	return false;
};
}

public function isValidSource($source) { // checks if the source has all required fields defined for this type
if (!empty($source['username']) && !empty($source['password']) && !empty($source['service'])) {
	return true;
}
else {
	$this->error='Source must specify a minimum of: username, password, service';
	return false;
};
}

public function getHandle($source, $args) { // opens an Oracle database connection
if ($handle=@oci_connect($source['username'], $source['password'], $source['service'], (!empty($source['charset']) ? $source['charset'] : NULL))) { // create database handle and open connection
	return $handle;
}
else {
	$e=oci_error();
	$this->error='Connection error: '.$e['message'];
};
}

protected function getTables($source) { // gets all tables that the user has access to
$this->tables[$source['name']]=array();
if ($res=@oci_parse($source['handle'], 'SELECT * FROM all_tables')) {
	@oci_execute($res);
	while ($res2=@oci_fetch_array($res, OCI_ASSOC+OCI_RETURN_NULLS)) {
		$this->tables[$source['name']][$res2['TABLE_NAME']]=array();
	};
	@oci_free_statement($res);
};
}

public function getFieldsForTable($source, $table) { // gets all fields for the specified table
if (!isset($this->tables[$source['name']])) { // confirm that the specified table is valid
	$this->getTables($source);
};
if (!empty($source['allowed_tables']) && ($source['allowed_tables']=='*' || is_array($source['allowed_tables']))) { // if the source has valid allowed tables
	if ($source['allowed_tables']=='*' || in_array($table, $source['allowed_tables'])) {
		if ($res=@oci_parse($source['handle'], 'SELECT column_name FROM all_tab_cols WHERE UPPER(table_name) = UPPER(\''.$table.'\')')) {
			@oci_execute($res);
			while ($res2=@oci_fetch_array($res, OCI_ASSOC+OCI_RETURN_NULLS)) {
				$this->tables[$source['name']][$table][$res2['COLUMN_NAME']]='';
			};
			@oci_free_statement($res);
		};
	};
};
return $this->tables[$source['name']][$table];
}

public function getResults($source, $args) { // fetches results
global $_LW;
$output=array();
if (!isset($this->tables[$source['name']])) { // confirm that the specified table is valid
	$this->getTables($source);
};
if (isset($this->tables[$source['name']][$args['table']])) {
	if (empty($this->tables[$source['name']][$args['table']])) {
		$this->getFieldsForTable($source, $args['table']);
	};
	$where=array();
	foreach(array('search', 'exclude_search') as $search_type) {
		if (!empty($args[$search_type])) { // build search query
			$search_mode=(!empty($args[$search_type.'_mode']) && in_array($args[$search_type.'_mode'], array('any', 'all'))) ? ((string)$args[$search_type.'_mode']=='all' ? 'AND' : 'OR') : 'AND'; // get search mode
			$search_clauses=array();
			if (!is_array($args[$search_type])) {
				$args[$search_type]=array($args[$search_type]);
			};
			foreach($args[$search_type] as $search_terms) { // construct each search clause
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
						if ($search_cmp=='=') {
							$search_clauses[]=strpos($search_terms, '*')!==false ? $search_field.($search_type=='exclude_search' ? 'NOT ' : '').'LIKE '.$_LW->escape(str_replace('*', '%', $search_terms)) : $search_field.($search_type=='exclude_search' ? '!' : '').'='.$_LW->escape($search_terms);
						}
						else {
							$search_clauses[]=$search_field.($search_type=='exclude_search' ? '!' : '').$search_cmp.$_LW->escape($search_terms);
						};
					};
				};
			};
			if (!empty($search_clauses)) { // construct final search clause
				$where[]=sizeof($search_clauses)>1 ? '('.implode(' '.$search_mode.' ', $search_clauses).')' : $search_clauses[0];
			};
		};
	};
	$order_by=''; // validate order by clause
	if (!empty($args['sort_field'])) {
		$args['sort_field']=explode(',', $args['sort_field']);
		foreach($args['sort_field'] as $key=>$val) {
			$args['sort_field'][$key]=trim($val);
			if (!isset($this->tables[$source['name']][$args['table']][str_replace(array(' ASC', ' DESC'), '', $args['sort_field'][$key])])) {
				unset($args['sort_field'][$key]);
			};
		};
		$args['sort_field']=implode(',', $args['sort_field']);
		$order_by=$args['sort_field'];
	};
	if ($res=@oci_parse($source['handle'], 'SELECT * FROM '.$args['table'].(!empty($where) ? ' WHERE '.implode(' AND ', $where) : '').(!empty($order_by) ? ' ORDER BY '.$order_by : '').' FETCH FIRST '.(int)$args['max'].' ROWS ONLY')) { // fetch results
		@oci_execute($res);
		while ($res2=@oci_fetch_array($res, OCI_ASSOC+OCI_RETURN_NULLS)) {
			$res2=$_LW->utf8Encode($res2); // enforce utf8
			$output[]=$res2;
		};
		@oci_free_statement($res);
	};
};
return $output;
}

}

?>