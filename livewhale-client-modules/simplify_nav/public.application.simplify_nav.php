<?php

/*

This application reduces code in generated LiveWhale navigations and can be used to add site-specific classes to nav elements.

*/

$_LW->REGISTERED_APPS['simplify_nav']=[ // configure this module
	'title'=>'Simplify Nav',
	'handlers'=>['onTransform'],
	'custom'=>[
		'enabled_ids'=>[ // ID => optional array of attributes to add by selector
			123=>[
				'/ul'=>['class'=>'nav-module-content_1','data-example'=>'example-value'],
				'/ul/li'=>['class'=>'nav-module-item_1'],
				'/ul/li/ul'=>['class'=>'nav-module-content-subnav_1']
			]
		],
		'enabled_attributes'=>[ // class => optional array of attributes to add by selector
			'lw-simplify-nav'=>[
				'/ul'=>['class'=>'nav-module-content_2'],
				'/ul/li'=>['class'=>'nav-module-item_2'],
				'/ul/li/ul'=>['class'=>'nav-module-content-subnav_2']
			],
			'lw-simplify-only'=>[] // example of simplification w/o attributes added
		]
	]
];

class LiveWhaleApplicationSimplifyNav {

public function onTransform($type, $xml) { // updates widget output to include custom classes on uls
global $_LW;
if ($_LW->widget['type']=='navigation' && (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids']) || !empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_attributes']))) { // if simplify nav module is configured
	$will_simplify=false;
	$add_attributes=[];
	if (!empty($_LW->widget['widget_id']) && isset($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']]) && is_array($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']])) { // if the widget's ID is enabled
		$will_simplify=true; // flag for simplification
		if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']])) { // if there are any classes to add
			foreach($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_ids'][$_LW->widget['widget_id']] as $selector=>$attributes) {
				if (is_array($attributes)) {
					if (!isset($add_attributes[$selector])) {
						$add_attributes[$selector]=[];
					};
					foreach($attributes as $attribute=>$values) { // record attributes to add
						$add_attributes[$selector][$attribute]=array_merge((!empty($add_attributes[$selector][$attribute]) ? $add_attributes[$selector][$attribute] : []), [$values]);
					};
				};
			};
		};
	};
	if (!empty($_LW->widget['args']['class'])) {
		foreach((array)$_LW->widget['args']['class'] as $class) {
			if (isset($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_attributes'][$class])) { // if any of the widget's classes are enabled
				$will_simplify=true; // flag for simplification
				if (!empty($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_attributes'][$class])) { // if there are any classes to add
					foreach($_LW->REGISTERED_APPS['simplify_nav']['custom']['enabled_attributes'][$class] as $selector=>$attributes) {
						if (is_array($attributes)) {
							if (!isset($add_attributes[$selector])) {
								$add_attributes[$selector]=[];
							};
							foreach($attributes as $attribute=>$values) { // record attributes to add
								$add_attributes[$selector][$attribute]=array_merge((!empty($add_attributes[$selector][$attribute]) ? $add_attributes[$selector][$attribute] : []), [$values]);
							};
						};
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
		if (!empty($add_attributes)) { // add custom attributes
			foreach($add_attributes as $selector=>$attributes) {
				if ($res=@$xml->elements($selector)) {
					foreach($res as $res2) {
						foreach($attributes as $attribute=>$values) {
							$existing_values=($res2->hasAttribute($attribute) ? $res2->getAttribute($attribute) : '');
							$res2->setAttribute($attribute, (!empty($existing_values) ? $existing_values.' ' : '').implode(' ', $values));
						};
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