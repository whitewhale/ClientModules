<?php

/*

This application reduces code in generated LiveWhale navigations and can be used to add site-specific classes to nav elements.

*/

$_LW->REGISTERED_APPS['simplify_nav']=[ // configure this module
	'title'=>'Simplify Nav',
	'handlers'=>['onTransform'],
	'custom'=>[
		'enabled_ids'=>[],
		'enabled_classes'=>['lw-simplify-nav'],
		'add_classes'=>[
			'/ul'=>['nav-module-content'],
			'/ul/li'=>['nav-module-item'],
			'/ul/li/ul'=>['nav-module-content-subnav']
		]
	]
];

class LiveWhaleApplicationSimplifyNav {

public function onTransform($type, $xml) { // updates widget output to include custom classes on uls
global $_LW;
if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids']) || !empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'])) { // if simplify nav module is configured
	foreach(['enabled_ids', 'enabled_classes'] as $key) { // ensure proper format of settings
		if (!is_array($_LW->REGISTERED_APPS['simplify_nav']['custom'][$key])) {
			$_LW->REGISTERED_APPS['simplify_nav']['custom'][$key]=[$_LW->REGISTERED_APPS['simplify_nav']['custom'][$key]];
		};
	};
	$will_simplify=false;
	switch(true) { // check if this nav will be simplified
		case (!empty($_LW->widget['widget_id']) && !empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids']) && in_array($_LW->widget['widget_id'], $_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'])):
			$will_simplify=true;
			break;
		case (!empty($_LW->widget['args']['class']) && !empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes']) && sizeof(array_diff($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'], (array)$_LW->widget['args']['class']))!=sizeof($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'])):
			$will_simplify=true;
			break;
	};
	if (!empty($will_simplify)) { // if nav will be simplified
		foreach(array_merge($xml->elements('//ul'), $xml->elements('//li')) as $node) { // filter ul and li classes
			if ($classes=$node->getAttribute('class')) {
				$classes=explode(' ', $node->getAttribute('class'));
				foreach($classes as $key=>$val) {
					if (!in_array($val, ['lw_current', 'lw_active', 'lw_has_subnav'])) {
						unset($classes[$key]);
					};
				};
				$classes=implode(' ', $classes);
				if (!empty($classes)) {
					$node->setAttribute('class', $classes);
				}
				else {
					$node->removeAttribute('class');
				};
			};
		};
		$xml->elements(0)->unwrap(); // remove wrapper
		if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['add_classes'])) { // add custom classes
			foreach($_LW->REGISTERED_APPS['simplify_nav']['custom']['add_classes'] as $selector=>$classes) {
				if ($res=@$xml->elements($selector)) {
					foreach($res as $res2) {
						$existing_classes=($res2->hasAttribute('class') ? $res2->getAttribute('class') : '');
						$res2->setAttribute('class', (!empty($existing_classes) ? $existing_classes.' ' : '').implode(' ', $classes));
					};
				};
			};
		};
	};
};
return $xml;
}

}

?>