<?php

$_LW->REGISTERED_APPS['training']=[
	'title'=>'Training',
	'handlers'=>['onBeforeLogin', 'onLoad']
]; // configure this module

class LiveWhaleApplicationTraining {

public function onBeforeLogin($username) { // on login
global $_LW;
if ($res2=$_LW->dbo->query('select', 'id, password', 'livewhale_users', 'username='.$_LW->escape($username).' AND last_login IS NULL')->firstRow()->run()) { // if this is the user's first login
	$_LW->setCustomFields('users', $res2['id'], ['is_training' => $res2['password']], []); // flag the user as in training
};
}

public function onLoad() { // on page load
global $_LW;
if ($_LW->isLiveWhaleUser()) { // if the user is logged in
	if (!array_key_exists('is_training', $_SESSION['livewhale']['manage'])) { // if training status not yet obtained
		$fields=$_LW->getCustomFields('users', $_SESSION['livewhale']['manage']['uid']); // get user metadata
		$_SESSION['livewhale']['manage']['is_training']=!empty($fields['is_training']) ? $fields['is_training'] : false; // set training flag for user
	};
	if (!empty($_SESSION['livewhale']['manage']['is_training'])) { // if user is in training
		if (!empty($_LW->is_private_request)) { // if user is on the backend
			if ($_LW->page!='settings' && $_LW->page!='logout' && strpos($_LW->page, 'login_')!==0) { // if not on a login/logout page or settings page
				die(header('Location: /livewhale/?settings')); // redirect to settings page
			}
			else if ($_LW->page=='settings') { // else if on settings page
				if ($_LW->dbo->query('select', '1', 'livewhale_users', 'id='.(int)$_SESSION['livewhale']['manage']['uid'].' AND password!='.$_LW->escape($_SESSION['livewhale']['manage']['is_training']))->exists()->run()) { // if user has reset their password
					$_SESSION['livewhale']['manage']['is_training']=false; // reset their training flag
					$_LW->setCustomFields('users', $_SESSION['livewhale']['manage']['uid'], ['is_training' => ''], []);
				}
				else { // else if user has not reset their password
					$_LW->REGISTERED_MESSAGES['failure'][]='In order to proceed with training, you must choose a new user password for your LiveWhale account.'; // show instructional message
				};
			};
		}
		else { // else if user is on frontend
			die(header('Location: /livewhale/?settings')); // redirect to settings page
		};
	};
};
}

}

?>