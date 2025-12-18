<?php

if (!empty($_LW->exec_args['id'])) { // if linked calendar ID supplied
	$_LW->logDebug('25Live Feed refresh run for feed ' . $_LW->exec_args['id']);
	foreach($_LW->dbo->query('select', 'id', 'livewhale_events', 'subscription_pid='.(int)$_LW->exec_args['id'])->run() as $res2) { // delete all events in the specified feed
		$_LW->delete('events', $res2['id'], false);
	};
	$_LW->d_events->refreshEventsSubscription($_LW->exec_args['id'], true, true); // and recreate them via feed refresh
};

?>