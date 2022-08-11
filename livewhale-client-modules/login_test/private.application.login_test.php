<?php

$_LW->REGISTERED_APPS['login_test']=[
	'title'=>'Login Test',
	'handlers'=>['onLoad'],
	'custom'=>[ // customize the login settings to override the defaults here
		'LOGIN_MODE'=>'CAS', // for example...
		'CAS_HOST'=>'xxx.host.edu',
		'CAS_PORT'=>8443,
		'CAS_CONTEXT'=>'/cas',
		'CAS_CERTIFICATE_PATH'=>''
	]
]; // configure this module

class LiveWhaleApplicationLoginTest {

public function onLoad() { // on application load
global $_LW;
if ($_LW->page=='login') { // if on the login page
	if (!empty($_LW->_GET['login_toggle'])) { // if toggling the login paramters
		if (empty($_COOKIE['lw_'.$_LW->uuid.'_use_login_overrides'])) {
			setcookie('lw_'.$_LW->uuid.'_use_login_overrides', 1, $_SERVER['REQUEST_TIME']+3600, '/', $_SERVER['HTTP_HOST'], false, false);
			echo '<h2>Login Toggle</h2>Now using overrides.';
		}
		else {
			setcookie('lw_'.$_LW->uuid.'_use_login_overrides', false, $_SERVER['REQUEST_TIME']+3600, '/', $_SERVER['HTTP_HOST'], false, false);
			echo '<h2>Login Toggle</h2>No longer using overrides.';
		};
		exit;
	};
	if (!empty($_LW->_GET['login_status'])) { // if displaying login status
		echo '<h2>Login Status</h2><pre>';
		echo 'Using overrides?: '.(!empty($_COOKIE['lw_'.$_LW->uuid.'_use_login_overrides']) ? 'Yes' : 'No')."\n\n";
		print_r(array_keys($_SERVER));
		echo '</pre>';
		exit;
	};
	if (!empty($_COOKIE['lw_'.$_LW->uuid.'_use_login_overrides'])) { // if the overrides cookie is set
		if (!empty($_LW->REGISTERED_APPS['login_test']['custom']) && is_array($_LW->REGISTERED_APPS['login_test']['custom'])) { // override the settings
			foreach($_LW->REGISTERED_APPS['login_test']['custom'] as $key=>$val) {
				$_LW->CONFIG[$key]=$val;
			};
		};
	};
};
}

}

?>