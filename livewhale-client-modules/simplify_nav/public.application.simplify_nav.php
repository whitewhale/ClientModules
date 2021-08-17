<?php

/*

This application reduces code in generated LiveWhale navigations and can be used to add site-specific classes to nav elements.

*/

$_LW->REGISTERED_APPS['simplify_nav']=[ // configure this module
	'title'=>'Simplify Nav',
	'handlers'=>['onTransform'],
	'custom'=>[
		'enabled_ids'=>[ // ID => optional array of classes to add by selector
			123=>[
				'/ul'=>['nav-module-content_1'],
				'/ul/li'=>['nav-module-item_1'],
				'/ul/li/ul'=>['nav-module-content-subnav_1']
			]
		],
		'enabled_classes'=>[ // class => optional array of classes to add by selector
			'lw-simplify-nav'=>[
				'/ul'=>['nav-module-content_2'],
				'/ul/li'=>['nav-module-item_2'],
				'/ul/li/ul'=>['nav-module-content-subnav_2']
			],
			'lw-simplify-only'=>[] // example of simplification w/o classes added
		]
	]
];

class LiveWhaleApplicationSimplifyNav {

public function onTransform($type, $xml) { // updates widget output to include custom classes on uls
global $_LW;
if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids']) || !empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'])) { // if simplify nav module is configured
	$will_simplify=false;
	$add_classes=[];
	if (!empty($_LW->widget['widget_id']) && isset($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']]) && is_array($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']])) { // if the widget's ID is enabled
		$will_simplify=true; // flag for simplification
		if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']])) { // if there are any classes to add
			foreach($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']] as $selector=>$classes) {
				if (!isset($add_classes[$selector])) {
					$add_classes[$selector]=[];
				};
				$add_classes[$selector]=array_merge($add_classes[$selector], $classes); // record classes to add
			};
		};
	};
	if (!empty($_LW->widget['args']['class'])) {
		foreach((array)$_LW->widget['args']['class'] as $class) {
			if (isset($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'][$class])) { // if any of the widget's classes are enabled
				$will_simplify=true; // flag for simplification
				if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'][$class])) { // if there are any classes to add
					foreach($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_classes'][$class] as $selector=>$classes) {
						if (!isset($add_classes[$selector])) {
							$add_classes[$selector]=[];
						};
						$add_classes[$selector]=array_merge($add_classes[$selector], $classes); // record classes to add
					};
				};
			};
		};
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
		if (!empty($add_classes)) { // add custom classes
			foreach($add_classes as $selector=>$classes) {
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