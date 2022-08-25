<?php

// LiveURL plugin for importing a new image via url.

require $LIVE_URL['DIR'].'/livewhale.php'; // load LiveWhale
header('Content-Type: application/json; charset=UTF-8'); // send content encoding header
$output=[];
if ($_LW->isLiveWhaleUser()) { // if valid LiveWhale login
	if (!empty($_LW->_POST['url'])) { // if url supplied
		if ($_LW->isValidImageUrl($_LW->_POST['url'], true)) { // if valid image url
			sleep(1); // throttle
			if ($id=$_LW->create('images', [
				'gid'=>$_SESSION['livewhale']['manage']['gid'],
				'description'=>basename(parse_url($_LW->_POST['url'], PHP_URL_PATH)),
				'date'=>$_LW->toDate('m/d/Y'),
				'url'=>$_LW->_POST['url']
			])) {
				if ($json=$_LW->getUrl('https://'.$_LW->CONFIG['HTTP_HOST'].'/live/json/images/id/'.(int)$id.'?is_library_search=1')) {
					if ($json=@json_decode($json, true)) {
						if (!empty($json['data'][0])) {
							$output['success']=$json['data'][0];
						}
						else {
							$output['error']='Failed to select new image after import.';
						};
					}
					else {
						$output['error']='Failed to parse new image after import.';
					};
				}
				else {
					$output['error']='Failed to obtain new image after import.';
				};
			}
			else {
				$output['error']='Failed to import image: '.$_LW->error;
			};
		}
		else {
			$output['error']='The specified image url was not valid.';
		};
	}
	else {
		$output['error']='You must specify an image url via POST data.';
	};
}
else {
	$output['error']='You must be logged into LiveWhale to perform this function.';
};
die(json_encode($output));

?>