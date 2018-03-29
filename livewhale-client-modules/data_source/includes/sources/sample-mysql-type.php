<?php

/* Sample MySQL data source */

$_LW->data_source=array(
	'type'=>'mysql',
	'host'=>'localhost',
	'port'=>3306,
	'username'=>'my-user',
	'password'=>'my-password',
	'database'=>'my-database',
	'allowed_tables'=>array('my-excluded-table')
);

?>