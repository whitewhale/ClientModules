<?php

$_LW->REGISTERED_APPS['lockout']=array(
	'title'=>'Lockout',
	'handlers'=>array('onLoad'),
	'custom'=>array(
		'is_enabled'=>false, // toggle this lockout on/off
		'other_ips'=>array(), // add any manually whitelisted IPs that can access the site
		'relocate_unknown_users'=>true // set to true to redirect authenticated users with no LiveWhale user to the homepage
	)
);

class LiveWhaleApplicationLockout {

public function onLoad() { // on application load
global $_LW;
if (!empty($_LW->is_private_request)) { // if on backend
	if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['relocate_unknown_users']) && $_LW->page=='login_unknown_user') { // relocate unknown users if configured (i.e. redirect SSO-only users to the homepage)
		die(header('Location: /'));
	};
}
else { // else if on frontend
	switch(true) {
		case empty($_LW->REGISTERED_APPS['lockout']['custom']['is_enabled']): // if lockout is toggled off
			return true;
			break;
		case strpos($_SERVER['REQUEST_URI'], $_LW->CONFIG['LIVE_URL'].'/')!==false: // allow LiveURL requests
			return true;
			break;
		case strpos($_SERVER['REQUEST_URI'], '/livewhale/')!==false: // allow /livewhale requests
			return true;
			break;
		case strpos(@$_SERVER['HTTP_USER_AGENT'], 'LiveWhale')!==false: // allow internal LiveWhale requests
			return true;
			break;
		case strpos(@$_SERVER['HTTP_USER_AGENT'], 'White Whale')!==false: // allow White Whale monitor requests
			return true;
			break;
		case $this->isLocalIP($_SERVER['REMOTE_ADDR']): // allow requests from localhost or White Whale servers
			return true;
			break;
		case $_LW->isLiveWhaleUser(): // allow requests from logged-in users
			return true;
			break;
		case !empty($_LW->_GET['lw_accessibility_check']): // allow accessibility checks
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
	$ips=array('172.0.0.1', '::1'); // set base IPs
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
	if (!empty($_LW->REGISTERED_APPS['lockout']['custom']['other_ips'])) { // add any explicitly whitelisted IPs
		foreach($_LW->REGISTERED_APPS['lockout']['custom']['other_ips'] as $ip) {
			if (!in_array($ip, $ips)) {
				$ips[]=$ip;
			};
		};
	};
	$_LW->setVariable('lockout_ips', $ips, 86400); // cache localhost IPs
};
return ((is_array($ips) && in_array($ip, $ips))? true : false); // return true if IP is among localhost IPs
}

}

?>