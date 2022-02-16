<?php

/* MySQL database connector for the data source widget */

class LiveWhaleDataSourcePluginMysql {
public $error='';
protected $tables=[];

public function isSupported() { // checks if this database type is supported on the current PHP installation
if (extension_loaded('mysqli')) {
	return true;
}
else {
	$this->error='The mysqli extension for PHP is not installed.';
	return false;
};
}

public function isValidSource($source) { // checks if the source has all required fields defined for this type
if ($source['name']=='livewhale') {
	return true;
};
if (!empty($source['host']) && !empty($source['username']) && !empty($source['password']) && !empty($source['database'])) {
	return true;
}
else {
	$this->error='Source must specify a minimum of: host, username, password, database';
	return false;
};
}

public function getHandle($source, $args) { // opens a MySQL database connection
if ($source['name']=='livewhale') {
	return $source['handle'];
};
$handle=mysqli_init(); // create database handle
$handle->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3); // set 3 second MySQL connect timeout
if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) { // we support SSL but don't currently support cert validation
	$handle->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
};
@$handle->real_connect($source['host'], $source['username'], $source['password'], $source['database'], (!empty($source['port']) ? $source['port'] : false), false, false); // connect to database
if (!mysqli_connect_errno()) { // if db connected
	$handle->set_charset(!empty($source['charset']) ? $source['charset'] : 'utf8'); // set the charset for this connection (defaulting to utf8)
	return $handle;
}
else {
	$this->error='Connection error: '.mysqli_connect_error();
};
}

protected function getTables($source) { // gets all tables for the specified database
$this->tables[$source['name']]=[];
if ($res=$source['handle']->query('SHOW TABLES;')) {
	if (!empty($res->num_rows)) {
		while ($res2=$res->fetch_row()) {
			$this->tables[$source['name']][$res2[0]]=[];
		};
		$res->close();
	};
};
if ($source['name']=='livewhale' && isset($this->tables[$source['name']]['livewhale_users'])) { // disallow access to livewhale_users if using LiveWhale as the source
	unset($this->tables[$source['name']]['livewhale_users']);
};
}

public function getFieldsForTable($source, $table) { // gets all fields for the specified table
if (!isset($this->tables[$source['name']])) { // confirm that the specified table is valid
	$this->getTables($source);
};
if (!empty($source['allowed_tables']) && ($source['allowed_tables']=='*' || is_array($source['allowed_tables']))) { // if the source has valid allowed tables
	if ($source['allowed_tables']=='*' || in_array($table, $source['allowed_tables'])) {
		if ($res=$source['handle']->query('SHOW COLUMNS FROM `'.$table.'`;')) {
			if (!empty($res->num_rows)) {
				while ($res2=$res->fetch_row()) {
					$this->tables[$source['name']][$table][$res2[0]]='';
				};
				$res->close();
			};
		};
	};
};
return $this->tables[$source['name']][$table];
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
	$where=[];
	foreach(['search', 'exclude_search'] as $search_type) {
		if (!empty($args[$search_type])) { // build search query
			$search_mode=(!empty($args[$search_type.'_mode']) && in_array($args[$search_type.'_mode'], ['any', 'all'])) ? ((string)$args[$search_type.'_mode']=='all' ? 'AND' : 'OR') : 'AND'; // get search mode
			$search_clauses=[];
			if (!is_array($args[$search_type])) {
				$args[$search_type]=[$args[$search_type]];
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
							$search_clauses[]=strpos($search_terms, '*')!==false ? '`'.$search_field.'` '.($search_type=='exclude_search' ? 'NOT ' : '').'LIKE '.$_LW->escape(str_replace('*', '%', $search_terms)) : '`'.$search_field.'`'.($search_type=='exclude_search' ? '!' : '').'='.$_LW->escape($search_terms);
						}
						else {
							$search_clauses[]='`'.$search_field.'`'.($search_type=='exclude_search' ? '!' : '').$search_cmp.$_LW->escape($search_terms);
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
			if (!isset($this->tables[$source['name']][$args['table']][str_replace([' ASC', ' DESC'], '', $args['sort_field'][$key])])) {
				unset($args['sort_field'][$key]);
			};
		};
		$args['sort_field']=implode(',', $args['sort_field']);
		$order_by=$args['sort_field'];
	};
	if ($res=$source['handle']->query('SELECT * FROM `'.$args['table'].'`'.(!empty($where) ? ' WHERE '.implode(' AND ', $where) : '').(!empty($order_by) ? ' ORDER BY '.$order_by : '').' LIMIT '.(int)$args['max'].';')) { // fetch results
		if (!empty($res->num_rows)) {
			while ($res2=$res->fetch_assoc()) {
				$output[]=$res2;
			};
			$res->close();
		};
	};
};
return $output;
}

}

?>