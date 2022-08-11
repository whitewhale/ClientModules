<?php

$_LW->REGISTERED_APPS['appointments']=[
	'title'=>'Appointments',
	'handlers'=>['onPaymentsForm', 'onAfterRegisterEvent', 'onValidatePaymentsForm', 'onFormatMessageVars'],
    'flags'=>['no_autoload']
];

class LiveWhaleApplicationAppointments {

public function onPaymentsForm($buffer, $gateway, $gid, $description, $total, $payment_options, $check_instructions, $type, $id) { // on displaying a payments/RSVP form
global $_LW;
if ($type=='events') { // if this is an RSVP form for an event
	$has_appointments=false;
	$has_free_appointment_slots=false;
    if ($rows=$this->getAppointmentsForEvent($id)) { // get appointment slots associated with this event
		$has_appointments=true;
	    $str = '<div id="appointmentsE">' //style="display: none;"
             . '<h5>Available Appointments</h5>'
             . '<div id="appointment_selectE">';
 		usort($rows, function($a, $b){
		if ((strtotime($a['title']) !== false) && (strtotime($b['title']) !== false)) { // sort by time, if they're times
			$a = strtotime($a['title']);
			$b = strtotime($b['title']);
		} else { // if not, sort as text
			$a=preg_replace('~[^a-z0-9]~', '', strtolower($a['title']));
			$b=preg_replace('~[^a-z0-9]~', '', strtolower($b['title']));
		}
		return $a==$b ? 0 : ($a<$b) ? -1 : 1;
		});
       /* foreach($rows as $row) {
            $str .= '<span data-time="' . $row['title'] . '" data-filled="'.$row['is_filled'].'"></span>';
			if (empty($row['is_filled'])) { // flag as having a free slot if one encountered
				$has_free_appointment_slots=true;
			};
        }*/
		
        foreach($rows as $row) {
			$cls = $row['is_filled'] ? 'text-lt':'';
			
			$uid = 'slot_'.substr(hash("md5",$row['title'] ),0,12);
            $str .= '<div class="'.$cls.'">
				<label for="'.$uid.'">
					<input type="radio"  name="appointments[]" id="'.$uid.'" value="' . $row['title'] . '" '.($row['is_filled']?'disabled="disabled"':'').' > 
				' . $row['title']. '  </label>
				</div>';
            //$str .= '<input type=checkbox name="appointmentsE[]" value="' . $row['title'] . '" '.($row['is_filled']?'disabled="disabled':'').'> ' . $row['title'] . ' <br>';
			if (empty($row['is_filled'])) { // flag as having a free slot if one encountered
				$has_free_appointment_slots=true;
			};
        }
		
        $str .= '</div>'
              . '</div>
			  <style>.text-lt { color: #777}</style>
         ';//'        <script type="text/javascript" src="//'.$_LW->CONFIG['HTTP_HOST'].$_LW->CONFIG['LIVE_URL'].'/resource/js/%5Clivewhale%5Cscripts%5Clwui%5Cjquery.lw-multiselect.js/appointments%5Ccalendar.js"></script>';
        // $str .= '</div>'
        //       . '</div>
        //          <script type="text/javascript" src="//'.$_LW->CONFIG['HTTP_HOST'].$_LW->CONFIG['LIVE_URL'].'/resource/js/livewhale/scripts/lwui/jquery.lw-multiselect.min.js"></script>
        //          <script type="text/javascript" src="//'.$_LW->CONFIG['HTTP_HOST'].$_LW->CONFIG['LIVE_URL'].'/resource/js/appointments%5Ccalendar.js"></script>';
        $buffer=preg_replace('~(<form[^>]+?>)~', '\\1' . $str, $buffer); // add appointments selector
	};
	if (!empty($has_appointments) && empty($has_free_appointment_slots)) { // close registration if no free slots
		$buffer=preg_replace('~<form[^>]+?>.+?</form>~s', '<p><strong>There are no more appointment slots available for this event. Please check back later to see if one becomes available.</strong></p>', $buffer); // add appointments selector
	};
};
return $buffer;
}

public function onAfterRegisterEvent($id, $post) { // after an event registration takes place
global $_LW;
if (!empty($post['appointments'][0])) { // if an RSVP with an appointment has been submitted
	if ($appointment_id=$_LW->dbo->query('select', 'livewhale_appointments.id', 'livewhale_appointments', 'livewhale_appointments.title='.$_LW->escape($post['appointments'][0]))
	->innerJoin('livewhale_appointments2any', 'livewhale_appointments2any.id1=livewhale_appointments.id AND livewhale_appointments2any.id2='.(int)$post['lw_payments_field_id'].' AND livewhale_appointments2any.type="events"')
	->firstRow('id')->run()) { // get the appointment ID associated with the chosen slot
		$_LW->dbo->query('update', 'livewhale_appointments2any', ['registration_id'=>(int)$id], 'id1='.(int)$appointment_id.' AND id2='.(int)$post['lw_payments_field_id'].' AND type="events"')->run(); // record the chosen appointment for this registration
	};
};
}

public function onValidatePaymentsForm($post) { // validates the payment/RSVP form
global $_LW;
if (@$post['lw_payments_field_type']=='events') { // if an RSVP has been submitted
	if ($_LW->dbo->query('select', '1', 'livewhale_appointments2any', 'type="events" AND id2='.(int)@$post['lw_payments_field_id'])->exists()->run()) { // if appointments slots are attached to this event
		if (empty($post['appointments'][0])) { // if submission lacks appointment
			return 'You must choose an appointment slot.'; // return error with appointment requirement
		}
		else { // else if there is an appointment
			if ($appointment_id=$_LW->dbo->query('select', 'id', 'livewhale_appointments', 'title='.$_LW->escape($post['appointments'][0]))->firstRow('id')->run()) {
				if ($this->hasFilledAppointment(@$post['lw_payments_field_id'], $appointment_id)) {
					return 'This slot has since been taken. Please choose another appointment slot.'; // return error with appointment selection error
				};
			};
		};
	};
};
}

protected function getAppointmentsForEvent($event_id) { // gets appointments for the event
global $_LW;
$output=[];
foreach($_LW->dbo->query('select', '
livewhale_appointments.id,
livewhale_appointments.title,
IF(livewhale_appointments2any.registration_id IS NOT NULL, 1, 0) AS is_filled
', 'livewhale_appointments', '')
->innerJoin('livewhale_appointments2any', 'livewhale_appointments2any.id1=livewhale_appointments.id AND livewhale_appointments2any.id2='.(int)$event_id.' AND livewhale_appointments2any.type="events"')
->groupBy('livewhale_appointments.id')
->run() as $res2) { // fetch the appointments
	$output[]=$res2;
};
return $output;
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

public function onFormatMessageVars($vars) { // override var for RSVP notifications when an appointment is applied
global $_LW;
if (!empty($vars['subject']) && strpos($vars['subject'], 'Registration Received')!==false) { // if this is a registration received message
	if (!empty($_LW->_POST['lw_payments_field_id']) && !empty($_LW->_POST['appointments']) && is_array($_LW->_POST['appointments'])) { // if we have an event ID and appointment time
		$appointment_time=current($_LW->_POST['appointments']);
		$event_id=$_LW->_POST['lw_payments_field_id'];
		if ($res2=$_LW->dbo->query('select', 'date_dt, timezone', 'livewhale_events', 'id='.(int)$event_id)->firstRow()->run()) { // override var for event date/time to use the appointment time instead of the event time
			$vars['date_time']='<strong>your appointment is '.$_LW->toDate('l, F j, Y', $_LW->toTS($res2['date_dt'], 'UTC'), $res2['timezone']).' at '.$appointment_time . '</strong>.';
			// Temporary workaround: add a note to ical_url until we can customize it to bookmark just your appointment
			$vars['ical_url'] .= ' (Note, this saves the full event time to your calendar and doesn\'t reflect your selected appointment time.)';
		};
	};
};
return $vars;
}

}

?>