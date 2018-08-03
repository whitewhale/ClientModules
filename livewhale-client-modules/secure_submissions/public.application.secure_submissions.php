<?php

$_LW->REGISTERED_APPS['secure_submissions']=array(
	'title'=>'Secure Submissions',
	'handlers'=>array('onLoad')
); // configure this module

class LiveWhaleApplicationSecureSubmissions {

public function onLoad() { // on frontend request
global $_LW;
if ($_LW->page=='/submit/index.php') { // if on public submissions page
	if (!$_LW->isLiveWhaleUser() && !$_LW->isSSOAuthOnlyUser()) { // require authentication
		die(header('Location: /livewhale/?login&url=/submit/'));
	};
};
}

}

?>