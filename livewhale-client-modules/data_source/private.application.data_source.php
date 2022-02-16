<?php

$_LW->REGISTERED_APPS['data_source']=[
	'title'=>'Data Source',
	'handlers'=>['onWidgetConfig', 'onLoad'],
	'flags'=>['no_autoload']
];

class LiveWhaleApplicationDataSource {

public function onLoad() { // on page load
global $_LW;
if ($_LW->page=='widgets_edit') { // load extra JS on the widget editor
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/data_source%5Cdata_source.js';
};
}

public function onWidgetConfig($widget, $config, $is_shared) { // customizes options for the widget editor
global $_LW;
if ($widget=='data_source') {
	$config['args']['source']['options']['']='';
	if ($sources=@scandir($_LW->INCLUDES_DIR_PATH.'/client/modules/data_source/includes/sources')) {
		foreach($sources as $source) {
			$source=basename($source);
			if ($source[0]!='.') {
				$source=pathinfo($source, PATHINFO_FILENAME);
				$config['args']['source']['options'][$source]=$source;
			};
		};
	};
};
return $config;
}

}

?>