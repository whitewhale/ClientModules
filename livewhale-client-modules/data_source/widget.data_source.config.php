<?php

$_LW->REGISTERED_CONFIGS['data_source']=array(
	'title'=>'Data Source',
	'description'=>'The data source widget displays a list of data from an external database source.',
	'section'=>'Content',
	'args'=>array(
		'header',
		'header_block',
		'max',
		'source'=>array(
			'pre'=>'Show data from the following data source',
			'type'=>'select',
			'defaultval'=>'',
			'options'=>array(),
			'category'=>'Basic'
		),
		'table'=>array(
			'pre'=>'Show data from the table:',
			'category'=>'Basic',
			'post'=>'<a href="#" id="data_sources_list_fields" class="note">Available fields</a>'
		),
		'search'=>array(
			'pre'=>'Show data matching the search pattern',
			'category'=>'Filtering &amp; sorting',
			'is_multiple'=>true
		),
		'search_mode'=>array(
			'pre'=>'Require that items match',
			'type'=>'select',
			'defaultval'=>'all',
			'options'=>array('all'=>'all', 'any'=>'any'),
			'category'=>'Filtering &amp; sorting',
			'post'=>'of the above search terms'
		),
		'exclude_search'=>array(
			'pre'=>'But <em>not</em> items matching the search pattern',
			'category'=>'Filtering &amp; sorting',
			'is_multiple'=>true
		),
		'exclude_search_mode'=>array(
			'pre'=>'Require that items <em>not</em> match',
			'type'=>'select',
			'defaultval'=>'all',
			'options'=>array('all'=>'all', 'any'=>'any'),
			'category'=>'Filtering &amp; sorting',
			'post'=>'of the above search terms'
		),
		'sort_field'=>array(
			'pre'=>'Sort data using the field:',
			'category'=>'Filtering &amp; sorting'
		),
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
	),
	'format'=>array(
		'options'=>array(
			
		)
	)
);

?>