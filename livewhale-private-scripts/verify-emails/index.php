<?php

/* This script attempts to verify all email addresses tied to LiveWhale users to assist with a purge of expired users. This is NOT necessarily accurate, it is merely a guide that can be used as a first pass. */

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';
require './verify_email.php';
$dir=sys_get_temp_dir();
if (is_dir($dir)) {
	if (!is_dir($dir.'/verify_emails')) {
		mkdir($dir.'/verify_emails');
	};
	if (is_dir($dir.'/verify_emails')) {
		$invalid_addresses=array();
		foreach($_LW->dbo->query('select', 'username, email', 'livewhale_users', 'email NOT LIKE "%@whitewhale.net"')->run() as $res2) {
			echo 'User: '.$res2['username'].'<br/>Email: '.$res2['email'].'<br/>Valid?: ';
			$cache_path=$dir.'/verify_emails/'.hash('md5', $res2['email']);
			if (file_exists($cache_path) && filemtime($cache_path)>86400) {
				$is_valid=(file_get_contents($cache_path)==1);
			}
			else {
				$ve=new hbattat\VerifyEmail($res2['email'], 'do-not-reply@livewhale.net');
				//var_dump($ve->get_debug());
				$is_valid=$ve->verify();
				file_put_contents($cache_path, (!empty($is_valid) ? 1 : 0), LOCK_EX);
				usleep(1000000/4);
			};
			if (empty($is_valid)) {
				$invalid_addresses[]=$res2['email'];
				echo '<strong>No</strong>';
			}
			else {
				echo 'Yes';
			};
			echo '<hr/>';
		};
		if (!empty($invalid_addresses)) {
			$invalid_addresses=array_unique($invalid_addresses);
			echo '<h3>Invalid email addresses</h3>';
			foreach($invalid_addresses as $address) {
				echo $address.'<br/>';
			};
		};
	};
};

?>