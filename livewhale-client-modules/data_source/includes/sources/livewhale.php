<?php

// Plugin for accessing LiveWhale database data directly.

$_LW->data_source=array(
	'type'=>'mysql',
	'host'=>'',
	'port'=>'',
	'username'=>'',
	'password'=>'',
	'database'=>'',
	'allowed_tables'=>'*',
	'disallowed_tables'=>array('livewhale_users', 'livewhale_profiles_data_sources'), // not safe to include these!
	'handle'=>$_LW->db
);

?>