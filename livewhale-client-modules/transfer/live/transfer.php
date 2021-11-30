<?php

// LiveURL plugin for content transfers.

if (!empty($LIVE_URL['REQUEST'][0])) {
	$command=array_shift($LIVE_URL['REQUEST']);
	switch($command) {
		case 'export':
			$type=(!empty($LIVE_URL['REQUEST']) ? array_shift($LIVE_URL['REQUEST']) : '');
			$host=(!empty($LIVE_URL['REQUEST']) ? array_shift($LIVE_URL['REQUEST']) : '');
			if (!empty($host) && !empty($type)) {
				require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';
				if ($_LW->d_transfer->validateRequestFromDestinationHost($host)) { // if request was from a valid destination host
					die($_LW->d_transfer->exportContent($type)); // export the content
				}
				else { // else give error on invalid authentication
					die(json_encode(['error'=>'Invalid authentication.']));
				};
			};
			break;
	};
};
exit;

?>