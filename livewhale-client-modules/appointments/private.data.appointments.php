<?php

$_LW->REGISTERED_MODULES['appointments']=[
	'title'=>'Appointments',
	'link'=>'/livewhale/?appointments',
	'revision'=>1,
	'order'=>120,
	'subnav'=>[
		['title'=>'Your Events', 'url'=>'/livewhale/?events_list', 'id'=>'page_events'],
		['title'=>'Appointment Slots', 'url'=>'/livewhale/?appointments', 'id'=>'page_appointments']
	],
	'flags'=>['has_js', 'has_css', 'is_always_authorized'],
	'requires_permission'=>['core_edit', 'core_globals'],
	'handlers'=>['onLogin'],
	'ajax'=>['saveAppointmentsTitle', 'getAppointmentsList', 'getAppointmentsListJSON'],
	'data_types'=>[
		'appointments'=>[
			'table'=>'livewhale_appointments',
			'term'=>'appointment',
			'term_plural'=>'appointments',
			'title_clause'=>'livewhale_appointments.title',
			'managers'=>[
				'appointments'=>[
					'handlers'=>[
						'onManager'=>'onManagerAppointments',
						'onManagerSubmit'=>'onManagerSubmitAppointments'
					]
				]
			],
			'fields'=>['title'],
			'fields_required'=>['title'],
			'handlers'=>[
				'onValidate'=>'onValidateAppointments'
			],
			'flags'=>['has_trash'],
			'trash'=>[
				'title'=>'title',
				'records'=>[
					['livewhale_appointments2any', 'livewhale_appointments2any.id1={id}']
				]
			]
		]
	],
	'reports'=>[
		'tables'=>['livewhale_appointments', 'livewhale_appointments2any']
	]
]; // configure this module

class LiveWhaleDataAppointments {

public function onLogin() { // execute any login actions
global $_LW;
$module_config=&$_LW->REGISTERED_MODULES['appointments']; // get the config for this module
if (!$revision=$_LW->isInstalledModule('appointments')) { // if module is not installed
	$_LW->dbo->sql('CREATE TABLE IF NOT EXISTS livewhale_appointments (id int(11) NOT NULL auto_increment, title varchar(255) NOT NULL default "", date_created datetime NOT NULL, last_modified datetime NOT NULL, last_user int(11) NOT NULL, created_by int(11) default NULL, PRIMARY KEY (id), KEY title (title)) ENGINE=INNODB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;'); // install module tables
	$_LW->dbo->sql('CREATE TABLE IF NOT EXISTS livewhale_appointments2any (id1 int(11) NOT NULL, id2 int(11) NOT NULL, type varchar(255) NOT NULL, registration_id int(11) default NULL, PRIMARY KEY (id1,id2,type), KEY id1 (id1), KEY id2 (id2)) ENGINE=INNODB DEFAULT CHARSET=utf8;');
	if ($_LW->hasTables(['livewhale_appointments', 'livewhale_appointments2any'])) { // register module in modules table
		$_LW->dbo->sql('INSERT INTO livewhale_modules VALUES(NULL,"appointments",'.(float)$module_config['revision'].');');
	};
}
else { // else if module is installed
	if ($revision!=$module_config['revision'] && empty($_LW->_GET['lw_auth'])) { // upgrade previous revisions
		/*
		include $_LW->INCLUDES_DIR_PATH.'/core/modules/appointments/includes/upgrade.php';
		$_LW->dbo->sql('UPDATE livewhale_modules SET revision='.(float)$module_config['revision'].' WHERE name="appointments";');
		$_LW->logError('Upgraded appointments module', false, true);
		*/
	};
};
}

public function onManagerSubmitAppointments() { // called upon manager submission
global $_LW;
if (!empty($_LW->_POST['dropdown_checked']) && !empty($_LW->_POST['items'])) { // handle checked item requests
	$this->saveChecked($_LW->_POST['dropdown_checked'], $_LW->_POST['items']);
};
}

public function formatManager($input) { // formats data for the appointments manager
global $_LW;
usort($input, [$this, 'sortAppointmentsList']); // sort appointments
foreach($input as $key=>$val) { // for each result	
	$input[$key]['checkbox']='<input class="with_this" type="checkbox" name="items[]" value="'.$input[$key]['id'].'"/>';
	$input[$key]['appointments']='<input type="hidden" name="appointments[]" value="'.$input[$key]['id'].'"/>';
	$input[$key]['title']='<a href="#">'.$input[$key]['title'].'</a>';
};
return $input;
}

public function sortAppointmentsList($a, $b) { // sort appointments in appointment list
if ((strtotime($a['title']) !== false) && (strtotime($b['title']) !== false)) { // sort by time, if they're times
	$a = strtotime($a['title']);
	$b = strtotime($b['title']);
} else { // if not, sort as text
	$a=preg_replace('~[^a-z0-9]~', '', strtolower($a['title']));
	$b=preg_replace('~[^a-z0-9]~', '', strtolower($b['title']));
}
return $a==$b ? 0 : ($a<$b) ? -1 : 1;
}

public function managerQuery() { // returns query info
global $_LW;
$q=$_LW->dbo->query('select', '
	livewhale_appointments.id,
	livewhale_appointments.title
', 'livewhale_appointments', false, 'livewhale_appointments.title ASC')->groupBy('livewhale_appointments.id');
return $q;
}

public function multiSelector($module, $name) { // returns multiSelector for appointments, preselecting currently selected ones
global $_LW;
if (!isset($_LW->json['appointments'])) { // if there are no appointments set yet
	$_LW->json['appointments']=[]; // init arrays
	$_LW->dbo->query('select',
		'livewhale_appointments.id,
		livewhale_appointments.title',
	'livewhale_appointments', false, 'livewhale_appointments.title ASC')
	->groupBy('livewhale_appointments.title');
	$res=$_LW->dbo->run(); // get appointments
	if ($res->hasResults()) {
		while ($res2=$res->next()) { // loop through appointments
			$_LW->json['appointments'][]=['id'=>$res2['id'], 'title'=>$res2['title']]; // add appointment
		};
	};
};

// sort appointments as times
function date_compare($a, $b) {
    $t1 = strtotime($a['title']);
    $t2 = strtotime($b['title']);
    return $t1 - $t2;
}    
usort($_LW->json['appointments'], 'date_compare');

$_LW->json['editor']['values'][$name]=[]; // init array of form values
if (!empty($_LW->json['appointments'])) { // if there are appointments, loop through appointments, add value to field if there's a preselect value that exists as a appointment
	foreach($_LW->json['appointments'] as $val) {
		if (!empty($_LW->_POST[$name]) && is_array($_LW->_POST[$name]) && in_array($val['id'], $_LW->_POST[$name])) {
			$_LW->json['editor']['values'][$name][]=$val;
		};
	};
};
if (!empty($_LW->_POST['appointments_added'])) { // loop through added appointments and add values
	foreach($_LW->_POST['appointments_added'] as $val) {
		$_LW->json['editor']['values'][$name][]=['title'=>$val];
	};
};
}

public function getAppointmentsListJSON() { // returns multiSelector for Appointments, preselecting currently selected ones
global $_LW;
$output=[];
foreach($_LW->dbo->query('select', 'id, title', 'livewhale_appointments', false, 'title ASC')->run() as $res2) { // loop through and add appointments
	$output[]=['id'=>$res2['id'], 'title'=>$res2['title']];
};
return json_encode($output);
}

public function getAppointmentsList() { // AJAX function for obtaining a fresh appointment list
global $_LW;
include $_LW->INCLUDES_DIR_PATH.'/core/modules/core/includes/class.livewhale_manager.php'; // include manager class
$_LW->module='appointments';
$GLOBALS['manager_appointments']=$_LW->showManager('<ul id="manager_appointments" class="manager simple list-group"/>', '<li id="item{id}" class="{class} list-group-item">
	{checkbox}
	<h5>{title}</h5>
	{appointments}
</li>', 'managerQuery', 'formatManager');
return $_LW->xphp->parseString('<xphp var="manager_appointments"/>');
}

public function saveAppointmentsTitle($id, $title) { // sets the title of a appointment
global $_LW;
$data=['title'=>$title];
if (!empty($id)) { // if updating an existing appointment
	$output=$_LW->update('appointments', $id, $data); // update the appointment
}
else { // else if creating a appointment
	$output=$_LW->create('appointments', $data); // create the appointment
};
$output=!empty($output) ? ['id'=>$output] : ['error'=>$_LW->error];
return json_encode($output);
}

public function onValidateAppointments() { // on validation of appointments
global $_LW;
if (!empty($_LW->save_data['title'])) { // format title
	$_LW->save_data['title']=rawurldecode($_LW->save_data['title']); // decode title
	$_LW->save_data['title']=preg_replace('~[^a-zA-Z0-9 \-\.,&;:\'"“”‘’]~', '', $_LW->save_data['title']); // strip disallowed chars from title
	$_LW->save_data['title']=preg_replace('~&([^\s])|&$~', '\\1', $_LW->save_data['title']); // force amps to be followed by a space
	$_LW->save_data['title']=$_LW->setFormatSanitize($_LW->save_data['title']); // sanitize title
	$_LW->save_data['title']=str_replace(['’', '”', '‘', '“'], ["'", '"', "'", '"'], $_LW->save_data['title']); // convert smart quotes
	if ($_LW->save_mode=='update') { // if updating appointment
		if ($_LW->dbo->query('select', '1', 'livewhale_appointments', 'id!='.(int)$_LW->save_id.' AND title='.$_LW->escape($_LW->save_data['title']))->exists()->run()) { // if there is already an appointment slot by that name
			$_LW->REGISTERED_MESSAGES['failure'][]='An appointment slot by that name already exists.';
		};
	}
	else if ($_LW->save_mode=='create') { // else if creating appointment
		if ($_LW->dbo->query('select', '1', 'livewhale_appointments', 'title='.$_LW->escape($_LW->save_data['title']))->exists()->run()) { // if there is already an appointment slot by that name
			$_LW->REGISTERED_MESSAGES['failure'][]='An appointment slot by that name already exists.';
		};
	};
};
}

public function saveChecked($command, $items) { // post-processes checked items
global $_LW;
$command=explode(':', $command); // split command
$queries=[]; // init query array
switch($command[0]) { // handle different command cases
	case 'appointments_delete':
		$count_deleted=0;
		$count_skipped=0;
		foreach($items as $id) {
			if (!$_LW->dbo->query('select', '1', 'livewhale_appointments2any', 'livewhale_appointments2any.id1='.(int)$id.' AND livewhale_appointments2any.type="events" AND livewhale_appointments2any.registration_id IS NOT NULL')
			->innerJoin('livewhale_events', 'livewhale_events.id=livewhale_appointments2any.id2 AND livewhale_events.date_dt>NOW()')
			->exists()->run()) { // if the appointment slot isn't filled for future events
				$_LW->delete('appointments', $id);
				$count_deleted++;
			}
			else {
				$count_skipped++;
			};
		};
		if (!empty($count_deleted)) { // if there were appointment slots deleted
			$_LW->REGISTERED_MESSAGES['success'][]=$count_deleted.' appointment'.($count_deleted==1 ? '' : 's').' deleted.'; // give success msg
		};
		if (!empty($count_skipped)) { // if there were appointment slots skipped
			$_LW->REGISTERED_MESSAGES['failure'][]=$count_skipped.' appointment'.($count_skipped==1 ? '' : 's').' skipped. You may not delete appointment slots that are currently filled for future events.'; // give success msg
		};
		break;
};
if (!empty($queries)) { // execute queries
	foreach($queries as $query) {
		$_LW->dbo->sql($query);
	};
};
if (!empty($items)) { // loop through updated items
	if ($command[0]!='appointments_delete') {
		foreach($items as $id) {
			$_LW->callHandler('data_type', 'appointments', 'onUpdate', [$id]); // call handler
			$_LW->callHandlersByType('application', 'onAfterUpdate', ['appointments', $id]); // call handlers
		};
	};
};
}

public function onManagerAppointments() { // on the manager load
global $_LW, $title;
if (!$_LW->userSetting('core_globals')) { // disallow access for non-global perm
	die(header('Location: /livewhale/'));
};
$GLOBALS['dropdown_checked']=$this->dropdown('checked_appointments'); // create checked items dropdown menu
$title='Appointment Slots'; // set title
$GLOBALS['manager_appointments']=$_LW->showManager('<ul id="manager_appointments" class="manager simple list-group"/>', '<li id="item{id}" class="list-group-item">
	<input class="with_this" type="checkbox" name="items[]" value="{id}"/>
	<h5>{title}</h5>
	<input type="hidden" name="appointments[]" value="{id}"/>
</li>', 'managerQuery', 'formatManager');
}

public function dropdown($id, $preselect='') { // shows a dropdown
global $_LW;
$output='';
switch($id) { // get data for dropdown
	case 'checked_appointments':
		$id='dropdown_checked'; // make all checked menus use same id
		$empty_val='With checked items...';
		$arr[]=['Delete', 'appointments_delete'];
		break;
};
if (!empty($arr)) { // if there are items to show
	$output='<select class="form-control input-sm" name="'.$id.'" id="'.$id.'">'."\n\t".(isset($empty_val) ? '<option value="">'.$empty_val.'</option>' : ''); // create select element
	foreach($arr as $val) { // add options
		$output.="\n\t".'<option value="'.$val[1].'"'.(($val[1]==$preselect && $preselect) ? ' selected="selected"' : '').'>'.$val[0].'</option>';
	};
	$output.="\n</select>";
};
return $output;
}

}

?>