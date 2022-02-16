<?php

$_LW->REGISTERED_WIDGETS['data_source']=[ 
       'title'=>'Data Source',
       'widget'=>[
		   'cache'=>[
			   'data_source'=>3600
			]
       ],        
	   'handlers'=>['onDisplay'],
	   'ajax'=>['getDataSourceTableFields']
];

class LiveWhaleWidgetDataSource {
protected $sources=[];
protected $plugins=[];

protected function getSource($name) { // gets the specified source for a query
global $_LW;
$name=str_replace('/', '', $_LW->setFormatClean($name)); // sanitize the name
if (!isset($this->sources[$name])) { // if source not already loaded
	$path=$_LW->INCLUDES_DIR_PATH.'/client/modules/data_source/includes/sources/'.$name.'.php';
	if (file_exists($path)) { // if the source exists
		$_LW->protectedInclude($path); // load it
		if (!empty($_LW->data_source)) { // if loaded
			$source=$_LW->data_source;
			unset($_LW->data_source);
			$source['name']=$name; // add name to source
			if (!empty($source['type'])) { // if there is a type specified
				$source['type']=str_replace('/', '', $_LW->setFormatClean($source['type'])); // sanitize type
				if ($source['plugin']=$this->getPluginForSource($source)) { // if the plugin loads for this type
					if ($source['plugin']->isValidSource($source)) { // if the source validates by type
						if (empty($_LW->widget['args']['table']) || $this->isAllowedTable($source, $_LW->widget['args']['table'])) { // if the specified table is allowed for this source
							if (empty($_LW->widget['args']['table']) || !$this->isDisallowedTable($source, $_LW->widget['args']['table'])) { // if the specified table is not disallowed for this source
								if ($source['handle']=$source['plugin']->getHandle($source, $_LW->widget['args'])) { // if handle opens
									$this->sources[$name]=$source; // record initialized source
								}
								else {
									$this->error='Could not make connection to source "'.$name.'"'.(!empty($source['plugin']->error) ? ': '.$source['plugin']->error : '');
								};
							}
							else {
								$this->error='Specified table "'.$_LW->setFormatClean($_LW->widget['args']['table']).'" is not allowed for source';
							};
						}
						else {
							$this->error='Specified table "'.$_LW->setFormatClean($_LW->widget['args']['table']).'" is not allowed for source';
						};
					}
					else {
						$this->error='Source configuration is not valid'.(!empty($source['plugin']->error) ? ': '.$source['plugin']->error : '');
					};
				}
				else {
					$this->error='This server does not currently support sources of type "'.$source['type'].'"'.(!empty($source['plugin']->error) ? ': '.$source['plugin']->error : '');
				};
			}
			else {
				$this->error='Source must specify a database type';
			};
		}
		else {
			$this->error='Source could not be read from: '.$path;
		};
	}
	else {
		$this->error='Source could not be located at: '.$path;
	};
};
return !empty($this->sources[$name]) ? $this->sources[$name] : false;
}

protected function getPluginForSource($source) { // loads the plugin for a source
global $_LW;
if (isset($this->plugins[$source['type']])) { // if plugin for this type not already loaded
	return $this->plugins[$source['type']];
}
else if (file_exists($_LW->INCLUDES_DIR_PATH.'/client/modules/data_source/includes/plugins/'.$source['type'].'.php')) { // if plugin exists
	$_LW->protectedInclude($_LW->INCLUDES_DIR_PATH.'/client/modules/data_source/includes/plugins/'.$source['type'].'.php'); // load it
	$class='LiveWhaleDataSourcePlugin'.ucfirst($source['type']);
	if (class_exists($class)) { // if successfully loaded
		$plugin=new $class; // create a plugin object
		if (method_exists($plugin, 'isSupported') && method_exists($plugin, 'isValidSource') && method_exists($plugin, 'getHandle') && method_exists($plugin, 'getResults')) { // if plugin has valid methods
			if ($plugin->isSupported()) { // if plugin is supported
				$this->plugins[$source['type']]=$plugin; // record valid plugin
				return $plugin;
			};
		};
	};
};
return false;
}

public function onDisplay() { // formats and lists results
global $_LW;
$output='';
$this->error='';
$is_preview=(strpos($_SERVER['REQUEST_URI'], $_LW->CONFIG['LIVE_URL'].'/widget/preview/')===0); // flag if this is a widget preview (errors only reported there)
$args=&$_LW->widget['args']; // alias args
if (!empty($args['source'])) { // if source is defined
	if (!empty($args['table'])) { // if table is specified
		if (!empty($args['format'])) { // if format is specified
			if ($source=$this->getSource($args['source'])) { // if source loaded
				if (empty($args['max'])) { // sanitize max or set the default
					$args['max']=30;
				};
				if ($args['max']>2000) {
					$args['max']=2000;
				};
				$_LW->widgetApplyArgs(); // support core args (like header, etc.)
				if ($results=$source['plugin']->getResults($source, $args)) { // fetch the results
					foreach($results as $result) { // loop through results
						foreach($result as $key=>$val) {
							if (strpos($val, '<')!==false) {
								$result[$key]=$_LW->setFormatSanitize($val);
							};
						};
						$_LW->widgetAddResult($_LW->widgetFormat($_LW->widgetFormatVars($result))); // add each result to widget output
					};
				};
			}
			else {
				$this->error='Could not load source "'.$_LW->setFormatClean($args['source']).'" for this widget'.(!empty($this->error) ? ': '.$this->error : '').'.';
			}
		}
		else {
			$this->error='You must enter an output format for this widget.';
		};
	}
	else {
		$this->error='You must enter a table name for this widget.';
	};
}
else {
	$this->error='You must enter a data source for this widget.';
};
if (!empty($is_preview) && !empty($this->error)) { // return error if previewing
	return $this->error;
};
if (!empty($_LW->widget['results'])) { // add results to the widget
	$_LW->widgetAdd($_LW->widget['xml']->ul($_LW->widget['results']));
	$output=$_LW->widgetOutput(); // get the widget's output
};
return $output; // return output in place of widget
}

public function getDataSourceTableFields() { // gets the fields for a table
global $_LW;
$output=[];
if (!empty($_LW->_GET['source'])) { // if source is defined
	if (!empty($_LW->_GET['table'])) { // if table is specified
		if ($source=$this->getSource($_LW->_GET['source'])) { // if source loaded
			$output=$source['plugin']->getFieldsForTable($source, $_LW->_GET['table']); // get the fields
		};
	};
};
return json_encode($output);
}

protected function isAllowedTable($source, $table) { // checks if the specified table is allowed for the source
if (!isset($source['allowed_tables'])) { // table is allowed if there is no setting
	return true;
}
else if (!empty($source['allowed_tables']) && ($source['allowed_tables']=='*' || is_array($source['allowed_tables']))) { // if allowed_tables has a valid value
	if ($source['allowed_tables']=='*' || in_array($table, $source['allowed_tables'])) { // table is allowed if all tables are allowed or explicitly this table
		return true;
	};
};
return false;
}

protected function isDisallowedTable($source, $table) { // checks if the specified table is disallowed for the source
if (!isset($source['disallowed_tables'])) { // table is allowed if there is no setting
	return false;
}
else if (!empty($source['disallowed_tables']) && ($source['disallowed_tables']=='*' || is_array($source['disallowed_tables']))) { // if disallowed_tables has a valid value
	if ($source['disallowed_tables']!='*' && !in_array($table, $source['disallowed_tables'])) { // table is allowed if not disallowing all tables or explicitly this table
		return false;
	};
};
return true;
}

}

?>