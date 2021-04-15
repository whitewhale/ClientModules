<?php

$_LW->REGISTERED_APPS['lockout']=[
	'title'=>'Lockout',
	'handlers'=>['onLoad'],
	'custom'=>[
		'is_enabled'=>false, // toggle this lockout on/off
		'is_backend_only'=>false, // set to true if the lockout impacts backend access only
		'other_ips'=>[], // add any manually whitelisted IPs that can access the site
		'other_ips_ranges'=>[], // add arrays of start and end IPs for whitelisting by range
		'relocate_unknown_users'=>true, // set to true to redirect authenticated users with no LiveWhale user to the homepage
		'allowable_urls'=>['/index.php'], // relative paths to urls that should uniquely not trigger a login prompt
		'approved_editors'=>[] // if this array contains usernames, then only users matching those usernames will be able to access the backend interfaces
	]
];

class LiveWhaleApplicationLockout {

public function onLoad() { // on application load
global $_LW;
if (!empty($_LW->is_private_request)) { // if on backend
	if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['relocate_unknown_users']) && $_LW->page=='login_unknown_user') { // relocate unknown users if configured (i.e. redirect SSO-only users to the homepage)
		die(header('Location: /'));
	};
	if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors']) && is_array($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if backend request and there are approved_editors
		if ($_LW->isLiveWhaleUser() || $_LW->isSSOAuthOnlyUser()) { // if there is an active login
			if (!in_array($_LW->d_login->getAuthenticatedUser($_LW->CONFIG['LOGIN_MODE']), $_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if user is a non-approved editor
				die(header('Location: /')); // redirect to frontend
			};
		};
	};
}
else { // else if on frontend
	switch(true) {
		case (empty($_LW->REGISTERED_APPS['lockout']['custom']['is_enabled'])): // if lockout is toggled off
			return true;
			break;
		case (!empty($_LW->REGISTERED_APPS['lockout']['custom']['is_backend_only'])): // if backend access restricted only
			return true;
			break;
		case (strpos($_SERVER['REQUEST_URI'], $_LW->CONFIG['LIVE_URL'].'/')!==false): // allow LiveURL requests
			return true;
			break;
		case (strpos($_SERVER['REQUEST_URI'], '/livewhale/')!==false): // allow /livewhale requests
			if (!empty($_LW->is_private_request) && !empty($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors']) && is_array($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if backend request and there are approved_editors
				if (($_LW->isLiveWhaleUser() || $_LW->isSSOAuthOnlyUser()) && !in_array($_LW->d_login->getAuthenticatedUser($_LW->CONFIG['LOGIN_MODE']), $_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if there is an active login by a non-approved editor
					die(header('Location: /')); // redirect to homepage
				};
			};
			return true;
			break;
		case (strpos(@$_SERVER['HTTP_USER_AGENT'], 'LiveWhale')!==false): // allow internal LiveWhale requests
			return true;
			break;
		case (strpos(@$_SERVER['HTTP_USER_AGENT'], 'White Whale')!==false): // allow White Whale monitor requests
			return true;
			break;
		case (in_array($_LW->page, $_LW->REGISTERED_APPS['lockout']['custom']['allowable_urls'])): // allow allowable_urls
			return true;
			break;
		case (!empty($_SERVER['REMOTE_ADDR']) && $this->isLocalIP($_SERVER['REMOTE_ADDR'])): // allow requests from localhost or White Whale servers
			return true;
			break;
		case ($_LW->isLiveWhaleUser() || $_LW->isSSOAuthOnlyUser()): // allow requests from authenticated users
			if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors']) && is_array($_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if front-end request and there are approved_editors
				if (!empty($_SESSION['livewhale']['manage']['toolbar'])) { // if there is a toolbar
					if (!in_array($_LW->d_login->getAuthenticatedUser($_LW->CONFIG['LOGIN_MODE']), $_LW->REGISTERED_APPS['lockout']['custom']['approved_editors'])) { // if there is an active login by a non-approved editor
						unset($_SESSION['livewhale']['manage']['toolbar']); // remove toolbar for non-approved editor
					};
				};
			};
			return true;
			break;
		case (!empty($_LW->_GET['lw_accessibility_check'])): // allow accessibility checks
			return true;
			break;
		case (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome-Lighthouse')!==false): // allow Google Lighthouse
			return true;
			break;
	};
	die(header('Location: /livewhale/?login&url='.rawurlencode($_SERVER['REQUEST_URI']))); // else redirect to login
};
}

protected function isLocalIP($ip) { // checks if an IP points to the localhost
global $_LW;
$ips=$_LW->getVariable('lockout_ips'); // get cached IPs for the localhost
if (empty($ips)) { // if no cached IPs
	$ips=['172.0.0.1', '::1']; // set base IPs
	if ($ip=shell_exec('dig +short '.preg_replace('~[^a-zA-Z0-9\-_\.]~', '', $_LW->CONFIG['HTTP_HOST']).' A')) { // add public A record IP
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$ips[]=$ip;
		};
	};
	if ($ip=shell_exec('dig +short '.preg_replace('~[^a-zA-Z0-9\-_\.]~', '', $_LW->CONFIG['HTTP_HOST']).' AAAA')) { // add public AAAA record IP
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$ips[]=$ip;
		};
	};
	$_LW->setVariable('lockout_ips', $ips, 86400); // cache localhost IPs
};
if (!empty($ips) && is_array($ips) && in_array($ip, $ips)) { // return true for matches on any automatically whitelisted individual IPs
	return true;
};
if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['other_ips'])) { // return true for any matches on manually whitelisted individual IPs
	foreach($_LW->REGISTERED_APPS['lockout']['custom']['other_ips'] as $other_ip) {
		if ($other_ip==$ip) {
			return true;
		};
	};
};
if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['other_ips_ranges'])) { // return true for any matches on manually whitelisted IP ranges
	foreach($_LW->REGISTERED_APPS['lockout']['custom']['other_ips_ranges'] as $other_ip_range) {
		if (!empty($other_ip_range) && is_array($other_ip_range) && sizeof($other_ip_range)===2) {
			$low_ip=ip2long($other_ip_range[0]);
			$high_ip=ip2long($other_ip_range[1]);
			$ip=ip2long($ip);
			if ($ip<=$high_ip && $low_ip<=$ip) {
				return true;
			};
		};
	};
};
return false; // default to non-local IP
}

}

?>