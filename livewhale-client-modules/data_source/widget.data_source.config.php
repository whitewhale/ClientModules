<?php

$_LW->REGISTERED_CONFIGS['data_source']=[
	'title'=>'Data Source',
	'description'=>'The data source widget displays a list of data from an external database source.',
	'section'=>'Content',
	'args'=>[
		'header',
		'header_block',
		'max',
		'source'=>[
			'pre'=>'Show data from the following data source',
			'type'=>'select',
			'defaultval'=>'',
			'options'=>[],
			'category'=>'Basic'
		],
		'table'=>[
			'pre'=>'Show data from the table:',
			'category'=>'Basic',
			'post'=>'<a href="#" id="data_sources_list_fields" class="note">Available fields</a>'
		],
		'search'=>[
			'pre'=>'Show data matching the search pattern',
			'category'=>'Filtering &amp; sorting',
			'is_multiple'=>true
		],
		'search_mode'=>[
			'pre'=>'Require that items match',
			'type'=>'select',
			'defaultval'=>'all',
			'options'=>['all'=>'all', 'any'=>'any'],
			'category'=>'Filtering &amp; sorting',
			'post'=>'of the above search terms'
		],
		'exclude_search'=>[
			'pre'=>'But <em>not</em> items matching the search pattern',
			'category'=>'Filtering &amp; sorting',
			'is_multiple'=>true
		],
		'exclude_search_mode'=>[
			'pre'=>'Require that items <em>not</em> match',
			'type'=>'select',
			'defaultval'=>'all',
			'options'=>['all'=>'all', 'any'=>'any'],
			'category'=>'Filtering &amp; sorting',
			'post'=>'of the above search terms'
		],
		'sort_field'=>[
			'pre'=>'Sort data using the field:',
			'category'=>'Filtering &amp; sorting'
		],
		'clean_markup',
		'format',
		'columns',
		'list_order',
		'slideshow',
		'slideshow_interval',
		'slideshow_no_pausing',
		'fallback',
		'no_results',
		'class',
		'format_widget'
	],
	'format'=>[
		'options'=>[
			
		]
	]
];

?>