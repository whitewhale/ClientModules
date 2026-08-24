<?php

// LiveURL plugin for demo requests.

if (!empty($LIVE_URL['REQUEST'][0])) {
	switch($LIVE_URL['REQUEST'][0]) {
		case 'regenerate':
			require $LIVE_URL['DIR'].'/livewhale.php'; // load LiveWhale
			if (!$_LW->isLiveWhaleUser()) { // require a logged-in user
				$_LW->httpResponse(404);
				exit;
			};
			// Run via the same private-context exec mechanism the weekly cron uses, rather than calling
			// populateEvents() directly from this live/public request — otherwise every single create()/delete()
			// it makes has to round-trip through its own backend.php loopback request (since they're not private
			// requests), which is slow and, across ~150+ writes, unreliable under load
			$res = $_LW->d_framework->execute('demo_weekly', [], 'sync', 'private', $_SESSION['livewhale']['manage']['uid']);
			$result = @json_decode($res, true);
			if (!is_array($result)) {
				$result = ['created' => 0, 'failed' => ['Regeneration did not return a result: '.(is_string($res) ? $res : '(no response)')]];
			};
			header('Content-Type: text/plain');
			echo (int)$result['created'].' sample event(s) created.'."\n";
			if (!empty($result['failed'])) {
				echo sizeof($result['failed']).' failed:'."\n".implode("\n", $result['failed'])."\n";
			};
			break;
	};
};
exit;

?>
