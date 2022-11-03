<?php

$_LW->REGISTERED_APPS['coming_soon']=[
	'title'=>'Coming Soon',
	'handlers'=>['onLoad']
];

class LiveWhaleApplicationComingSoon {

public function onLoad() { // on application load
global $_LW;
if (empty($_LW->is_private_request)) { // if on frontend
	
	if (strpos($_SERVER['REQUEST_URI'], '/my-site/')===0) { // if we're on any of our specified pages

		if (!$_LW->isLiveWhaleUser()) { // if you're not logged in
			die(header('Location: /my-site-coming-soon')); // redirect you to coming soon page
		}

		// otherwise let you through.

	}

};
}


}

?>