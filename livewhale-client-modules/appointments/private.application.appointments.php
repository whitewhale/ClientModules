<?php

$_LW->REGISTERED_APPS['appointments']=[
	'title'=>'Appointments',
	'handlers'=>['onAfterEdit', 'onValidateSuccess', 'onSaveSuccess', 'onBeforeSave', 'onEditorFirstLoad', 'onOutput', 'onBeforeDelete', 'onSubnavs', 'onManagerFormatResults', 'onCSVOutput']
];

class LiveWhaleApplicationAppointments {

public function onOutput($buffer) { // on page output
global $_LW;
if ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit') { // if this is the event editor
    $str = '<div class="fields">'
         . '<label class="header">Appointments</label>'
         . '<fieldset class="appointments">'
         . '  <div class="appointment_selector"></div>'
         . '</fieldset>'
         . '</div>';
	$buffer=str_replace('<!-- END REGISTRATION -->', $str . '<!-- END REGISTRATION -->', $buffer); // add the appointments element
};
return $buffer;
}

public function onAfterEdit($module, $page, $id) { // after initializing an editor
global $_LW;
if ($module=='events') { // create list of clickable appointments for events
	$_LW->d_appointments->multiSelector('events', 'appointments',  $_SESSION['livewhale']['manage']['gid']); // create list of clickable appointments
};
}

public function onEditorFirstLoad($module, $page, $id) { // after initalizing an editor without a submission
global $_LW;
if (!empty($id) && $module=='events') { // if editing an existing event
	$_LW->_POST['appointments']=[];
	foreach($_LW->dbo->query('select', 'id1', 'livewhale_appointments2any', 'id2='.(int)$id.' AND type="events"')->run() as $res2) {
		$_LW->_POST['appointments'][]=$res2['id1'];
	};
};
}

public function onBeforeSave($type, $id) { // before saving an item of any data type
global $_LW;
if (!empty($_LW->json['is_editor'])) { // if this is an editor submission
	$_LW->save_data['associated_data']['appointments']=[];
	if (!empty($_LW->_POST['appointments']) || !empty($_LW->_POST['appointments_added'])) { // convert appointments to a single standardized list for saving
		if (!empty($_LW->_POST['appointments'])) {
			foreach($_LW->dbo->query('select', 'title', 'livewhale_appointments', 'id IN ('.preg_replace('~[^0-9,]~', '', implode(', ', $_LW->_POST['appointments'])).')')->run() as $res2) {
				$_LW->save_data['associated_data']['appointments'][]=$res2['title'];
			};
		};
		if (!empty($_LW->_POST['appointments_added'])) {
			$_LW->save_data['associated_data']['appointments']=array_merge($_LW->save_data['associated_data']['appointments'], $_LW->_POST['appointments_added']);
		};
	};
};
}

public function onValidateSuccess($type, $id) { // after successfully validating an item of any data type
global $_LW;
$last_appointments=[];
if ($type=='events') { // if saving an event
	if (!empty($id)) { // if updating, get current appointments before save
		foreach($_LW->dbo->query('select', 'livewhale_appointments.id, livewhale_appointments.title', 'livewhale_appointments2any', 'livewhale_appointments2any.id2='.(int)$id.' AND livewhale_appointments2any.type="events"')->innerJoin('livewhale_appointments', 'livewhale_appointments.id=livewhale_appointments2any.id1')->run() as $res2) {
			$last_appointments[$res2['id']]=$res2['title'];
			if (!isset($_LW->save_data['associated_data']['appointments']) || !in_array($res2['title'], $_LW->save_data['associated_data']['appointments'])) { // disallow removal of any appointment slots that are already filled
				if ($this->hasFilledAppointment($id, $res2['id'])) {
					$_SESSION['livewhale']['manage']['messages']['failure'][]='Removal of appointment time "'.$res2['title'].'" ignored, because this slot is currently filled.';
					if (!isset($_LW->save_data['associated_data']['appointments'])) {
						$_LW->save_data['associated_data']['appointments']=[];
					};
					$_LW->save_data['associated_data']['appointments'][]=$res2['title'];
				};
			};
		};
	};
	if (isset($_LW->save_data['associated_data']['appointments']) && is_array($_LW->save_data['associated_data']['appointments'])) { // if appointments are being set
		$gid=!empty($_LW->save_data['gid']) ? $_LW->save_data['gid'] : $_SESSION['livewhale']['manage']['gid']; // get gid for destination of appointments being applied
		if (!empty($gid)) {
			foreach($_LW->save_data['associated_data']['appointments'] as $key=>$appointment) { // for each appointment
				if (!in_array($appointment, $last_appointments)) { // if appointment has been added with this save
					if (!$_LW->dbo->query('select', '1', 'livewhale_appointments', 'title='.$_LW->escape($appointment))->exists()->run()) { // if appointment does not yet exist
						$appointment_id=$_LW->create('appointments', ['gid'=>$gid, 'title'=>$appointment]); // create the appointment
						if (empty($appointment_id)) { // if appointment could not be created
							unset($_LW->save_data['associated_data']['appointments'][$key]); // remove this appointment from the array of appointments being saved (so that we don't attempt to create a lookup table entry for it)
						}
						else { // else if successfully created
							if (!empty($_LW->json['is_editor'])) { // if we're on an editor
								if (isset($_LW->_POST['appointments_added'])) { // move the appointment from appointments_added to appointments
									$appointments_added_key=array_search($appointment, $_LW->_POST['appointments_added']);
									if ($appointments_added_key!==false) {
										unset($_LW->_POST['appointments_added'][$appointments_added_key]);
									};
								};
								if (isset($_LW->_POST['appointments'])) {
									$_LW->_POST['appointments'][]=$appointment_id;
								};
							};
						};
					};
				};
			};
		};
	};
};
$_LW->save_data['associated_data']['lw_last_appointments']=$last_appointments; // set last appointments for save()
}

public function onSaveSuccess($type, $id) { // after successfully saving an item of any data type
global $_LW;
if ($type=='events') { // if appointments supported
	if (isset($_LW->save_data['associated_data']['appointments']) && is_array($_LW->save_data['associated_data']['appointments'])) { // if appointments are being saved
		$type_escaped=$_LW->escape($type); // escape type
		$appointment_ids=[];
		if (!empty($_LW->save_data['associated_data']['appointments'])) { // for each appointment that was attached to this item
			foreach($_LW->save_data['associated_data']['appointments'] as $appointment) {
				if (!empty($_LW->save_data['associated_data']['lw_last_appointments']) && in_array($appointment, $_LW->save_data['associated_data']['lw_last_appointments'])) { // get the appointment ID from last_appointments (not restricting by group, to preserve appointment assignments from admins in another group)
					$appointment_ids[]=array_search($appointment, $_LW->save_data['associated_data']['lw_last_appointments']);
				}
				else if ($res2=$_LW->dbo->query('select', 'id', 'livewhale_appointments', $_SESSION['livewhale']['manage']['username']=='livewhale' ? '1' : 'title='.$_LW->escape($appointment), 'id ASC')->firstRow()->run()) { // else get the appointment ID from the db (restricting by group, unless user is "livewhale" b/c that may be an event feed refreshing)
					$appointment_ids[]=$res2['id'];
				};
			};
		};
		if (!empty($appointment_ids)) { // insert appointment linkages, if any
			foreach($appointment_ids as $appointment_id) {
				$_LW->dbo->query('insert', 'livewhale_appointments2any', ['id1'=>(int)$appointment_id, 'id2'=>(int)$id, 'type'=>$type_escaped], true)->run();
			};
		};
		$_LW->dbo->query('delete', 'livewhale_appointments2any', 'id2='.(int)$id.' AND type='.$type_escaped.(!empty($appointment_ids) ? ' AND id1 NOT IN ('.preg_replace('~[^0-9,]~', '', implode(',', $appointment_ids)).')' : ''))->flags('quick')->run(); // delete any appointment linkages no longer needed
	};
};
}

public function onBeforeDelete($type, $id) { // before deleting an item of any data type
global $_LW;
if ($type=='events_registration') { // if deleting an event registration
	$_LW->dbo->query('update', 'livewhale_appointments2any', ['registration_id'=>'NULL'], 'registration_id='.(int)$id.' AND type="events"')->run(); // reset the association of an appointment slot with this registration
}
else if ($type=='events') { // if deleting an event
	$_LW->dbo->query('delete', 'livewhale_appointments2any', 'id2='.(int)$id.' AND type="events"')->flags('quick')->run(); // delete associations of appointment slots with this event
};
}

protected function hasFilledAppointment($event_id, $appointment_id) { // checks if the event has a particular appointment slot full
global $_LW;
if ($_LW->dbo->query('select', '1', 'livewhale_appointments2any', 'livewhale_appointments2any.id1='.(int)$appointment_id.' AND livewhale_appointments2any.id2='.(int)$event_id.' AND livewhale_appointments2any.type="events"')
->innerJoin('livewhale_events_registrations', 'livewhale_events_registrations.pid=livewhale_appointments2any.id2 AND livewhale_events_registrations.id=livewhale_appointments2any.registration_id')
->exists()->run()) {
	return true;
};
return false;
}

public function onSubnavs($module, $subnavs) { // override subnavs
global $_LW;
if (strpos($_LW->page, 'events')===0 && $_LW->userSetting('core_admin')) { // if on an events page and user is an admin
	$subnavs[]=['title'=>'Appointments', 'url'=>'/livewhale/?appointments', 'id'=>'page_appointments']; // add subnav item
};
return $subnavs;
}

public function onManagerFormatResults($handler, $data) { // modifies the results of managers
global $_LW;
if ($handler=='managerQueryRegistrationsList') { // if this is the event registrations list
	foreach($data as $key=>$val) {
		$event_id=$_LW->_GET['id'];
		$matches=[];
		preg_match_all('~<tr[^>]*?>.+?</tr>~s', $data[$key]['attendees'], $matches);
		if (!empty($matches)) {
			foreach($matches[0] as $tr) {
				$matches2=[];
				preg_match('~<input type="hidden" name="registrations\[\]" value="([0-9]+?)"/>~', $tr, $matches2);
				if (!empty($matches2[1])) {
					$registration_id=$matches2[1];
					if ($appointment_title=$_LW->dbo->query('select', 'livewhale_appointments.title', 'livewhale_appointments')
					->innerJoin('livewhale_appointments2any', 'livewhale_appointments.id=livewhale_appointments2any.id1 AND livewhale_appointments2any.type="events" AND livewhale_appointments2any.id2='.(int)$event_id.' AND livewhale_appointments2any.registration_id='.(int)$registration_id)
					->firstRow('title')->run()) {
						$data[$key]['attendees']=str_replace($tr, preg_replace('~<td>(Registered.+?)</td>~s', '<td>\\1<br/><br/>&#128336; Scheduled for:<br/><br/>'.$_LW->setFormatClean($appointment_title).'</td>', $tr), $data[$key]['attendees']);
					};
				};
			};
		};
	};
};
return $data;
}

public function onCSVOutput($type, $rows) { // formats CSV output
global $_LW;
if ($type=='event_registrations') { // if these are event RSVPs
	foreach($rows as $key=>$val) {
		if ($key===0) { // add appointment time header row
			$rows[$key][]='Appointment Time';
			$appointment_key=sizeof($rows[$key])-1;
		}
		else { // add appointment time
			$rows[$key][$appointment_key]=$_LW->dbo->query('select', 'livewhale_appointments.title', 'livewhale_appointments')->innerJoin('livewhale_appointments2any', 'livewhale_appointments.id=livewhale_appointments2any.id1 AND livewhale_appointments2any.type="events" AND livewhale_appointments2any.id2='.(int)$val[0].' AND livewhale_appointments2any.registration_id='.(int)$val[14])->firstRow('title')->run();
		};
	};
};
return $rows;
}

}

?>