<?php

$_LW->REGISTERED_APPS['noindex']=[
	'title'=>'NoIndex',
	'handlers'=>['onOutput'],
	'custom'=>[
		'types'=>[
			'profiles'=>[89], // array of which profile IDs to apply this to
			'pages'=>[
				'/path/to/page.php',
				'/path/to/directory'
			]
		]
	]
];

class LiveWhaleApplicationNoindex {

public function onOutput($buffer) { // on page output
global $_LW;
if (!empty($_LW->REGISTERED_APPS['noindex']['custom']['types']) && is_array($_LW->REGISTERED_APPS['noindex']['custom']['types'])) { // if noindex types are configured
	$config=$_LW->REGISTERED_APPS['noindex']['custom']['types'];
	if (!empty($_LW->details_module) && isset($config[$_LW->details_module]) && is_array($config[$_LW->details_module]) && !empty($GLOBALS[$_LW->details_module.'_tid']) && in_array($GLOBALS[$_LW->details_module.'_tid'], $config[$_LW->details_module])) { // if on a details page for one of the noindex types
		
		// Option 1: append noindex, nofollow
		// $_LW->appendMetaTag(['name'=>'robots', 'content'=>'noindex, nofollow']);
		
		// Option 2: return a 404
		// header('HTTP/'.$_LW->protocol_version.' 404 Not Found'); // send headers
		// return $_LW->show404(true);
	}
	else if (!empty($config['pages']) && is_array($config['pages'])) { // else if on a page
		foreach($config['pages'] as $path) {
			if (strpos($path, '.php')!==false && $_LW->page==$path) {
				
			}
			else if (strpos($_LW->page, $path)===0) {
				
			};
		};
	};
};
return $buffer;
}

}

?>