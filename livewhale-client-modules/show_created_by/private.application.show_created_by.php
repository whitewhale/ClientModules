<?php

/*

Show "Created by" for events

*/

$_LW->REGISTERED_APPS['show_created_by']=array( // configure this module
	'title'=>'Show Created By',
	'handlers'=>array('onOutput')
);

class LiveWhaleApplicationShowCreatedBy {

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit') { // else if on the events editor
	if (!empty($_LW->_GET['id'])) { // if editing an existing event
		if ($res2=$_LW->dbo->query('select', '
			livewhale_events.date_created,
			livewhale_events.created_by,
			IF(livewhale_events.parent IS NOT NULL AND livewhale_events.url IS NOT NULL, livewhale_events.parent, NULL) AS link_parent,
			livewhale_users.name,
			livewhale_users.email
		', 'livewhale_events', 'livewhale_events.id='.(int)$_LW->_GET['id'])
		->innerJoin('livewhale_users', 'livewhale_users.id=livewhale_events.created_by')
		->firstRow()
		->run()) { // add created by beneath last modified by
			if (!empty($res2['link_parent'])) { // but override name/email for a link parent
				if ($res3=$_LW->dbo->query('select', 'livewhale_users.name, livewhale_users.email', 'livewhale_users')
				->innerJoin('livewhale_events', 'livewhale_events.id='.(int)$res2['link_parent'].' AND livewhale_events.created_by=livewhale_users.id')
				->firstRow()
				->run()) {
					$res2['name']=$res3['name'];
					$res2['email']=$res3['email'];
				};
			};
			$created='<div class="created">Created '.$_LW->setFormatDate($_LW->toTS($res2['date_created'])).' by <span class="lw_user">'.$res2['name'].'</span> <span class="lw_user_email">(<a href="mailto:'.$res2['email'].'" target="_blank">email</a>)</span>.</div>';
			$buffer=preg_replace('~(<a .+?id="link_restore">)~', $created."\n".'\\1', $buffer);
		};
	};
};
return $buffer;
}

// public function onManagerQuery($handler, $q) { // modifies the manager queries
// global $_LW;
// if ($_LW->page=='events_list' && $handler=='managerQuerySuggested') { // if suggestions on events manager
// 	$q->leftJoin(false); // replace the left join with an alternate one that uses the created_by user
// 	$q->leftJoin('livewhale_users', 'livewhale_events.created_by=livewhale_users.id');
// };
// }

}

?>