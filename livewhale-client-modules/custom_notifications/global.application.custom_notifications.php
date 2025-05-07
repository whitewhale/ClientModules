<?php

$_LW->REGISTERED_APPS['custom_notifications']=[
	'title'=>'Custmo Notifications',
	'handlers'=>['onAfterPublicSubmission', 'onAfterCreate', 'onValidateSuccess'],
	'custom'=>[
		'email_list'=>[],
	],
]; // configure this module


/*

IN THIS MODULE:

Starter blocks of code that you can adapt to add custom notifications for:

	onAfterCreate
		- Emails when new items are created
		-- Example code is for "new live Event created, not from Linked Calendar"

	onValidateSuccess
		- Emails whenever an item is saved meeting certain criteria
		-- Example code is when Hidden changed to Live

	onAfterPublicSubmission
		- Emails whenever public submission form is successfully submitted
		-- Example code includes "always email" and "email for certain group" blocks

*/


class LiveWhaleApplicationCustomNotifications {

// To send custom notification every time a new Live Event gets created, uncomment and
// add array of emails to $_LW->REGISTERED_APPS['custom_notifications']['custom']['email_list']

	/*

    public function onAfterCreate($type, $id) {
        global $_LW;

		// New live event, not from a linked calendar
        if($type == 'events' && $_LW->save_data['status']== "1" && empty($_LW->save_data['subscription_pid'])) {

            $cols='livewhale_events.id, livewhale_events.title, livewhale_events.status';
            $table='livewhale_events';
            $where='id='.(int)$id.' AND parent IS NULL';

            //make sure event is not a suggestion
            foreach($_LW->dbo->query('select', $cols, $table, $where)->run() as $res) {

                $event_title = $res['title'];
                $event_url = $_SERVER['HTTP_ORIGIN'] . '/live/events/' . $id;
                $event_date = $_LW->save_data['date'];
                $event_id = $res['id'];    

                $subject = 'New Event Added - ID ' . $event_id;
                $message = "A new live event has been added to the calendar:
                \n" . "<b>Event Title:</b> " . $event_title . "\n" . "</b>Event Date:</b> ". $event_date . "\n Click here to view the event details: " . $event_url;
              
                foreach($_LW->REGISTERED_APPS['event_notification']['custom']['email_list'] as $to) {
                    $_LW->d_messages->sendMail($to, $subject, $message);

                }  
            }     
        }
    }

    */



// To send custom notification every time an existing event gets changed from Hidden to Live, uncomment and
// add array of emails to $_LW->REGISTERED_APPS['custom_notifications']['custom']['email_list']

	/*

    public function onValidateSuccess($type, $id) { 
        global $_LW;

            // Existing event with status as live
            if($type==='events' && !(empty($id)) && $_LW->save_data['status']== '1') {

                $cols='livewhale_events.id, livewhale_events.title, livewhale_events.status';
                $table = 'livewhale_events';
                $where ='id='.(int)$id.' AND status != 1';

                foreach($_LW->dbo->query('select', $cols, $table, $where)->run() as $res) {
                    $event_title = $_LW->save_data['title'];
                    $event_url = $_SERVER['HTTP_ORIGIN'] . '/live/events/' . $id;
                    $event_date = $_LW->save_data['date'];
    
                    $subject = 'Event Set Live - ID ' . $id;
                    $message = "An event has been set to live:
                    \n" . "Event Title: " . $event_title . "\n" . "Event Date: ". $event_date . "\n Click here to view the event details: " . $event_url;
                    
                    foreach($_LW->REGISTERED_APPS['event_notification']['custom']['email_list'] as $to) {
                        $_LW->d_messages->sendMail($to, $subject, $message);
                    }
                };   
            }
    }         

	*/




// To send custom notifications for public submissions, uncomment and 
// add individual emails to the $_LW->d_messages->sendMail lines

	/*

	public function onAfterPublicSubmission($type, $id) { // after a story/event is submitted
		global $_LW;

		// Assemble message vars for custom email notifications
		$message_vars=[
			'submitter_name'	=>$_LW->_POST['name'],
			'submitter_email'	=>$_LW->_POST['email'],
			'type'				=>$type,
			'title'				=>($type == 'news' ? $_LW->_POST['news_headline'] : ($type=='events' ? $_LW->_POST['event_title'] : $_LW->setFormatSummarize($_LW->_POST['image_caption'], 5))),
			'summary'			=>($type == 'news' ? @$_LW->_POST['news_summary'] : ($type=='events' ? @$_LW->_POST['event_summary'] : '')),
			'description'		=>($type == 'news' ? @$_LW->_POST['news_body'] : ($type=='events' ? @$_LW->_POST['event_description'] : '')),
			'contact_info'		=>($type == 'news' ? @$_LW->_POST['news_contact_info'] : ($type == 'events' ? @$_LW->_POST['event_contact_info'] : '')),
			'event_cost'		=>@$_LW->_POST['event_cost'],
			'event_time'		=>@$_LW->_POST['event_time'],
			'event_time2'		=>@$_LW->_POST['event_time2'],
			'event_date'		=>@$_LW->_POST['event_date'],
			'event_date2'		=>@$_LW->_POST['event_date2'],
			'location'			=>@$_LW->_POST['event_location'],
			'url'				=>@$_LW->_POST[$type.'_url'],
			'edit_url'			=>($type == 'news' ? 'http'.($_LW->hasSSL() ? 's' : '').'://'.$_LW->CONFIG['HTTP_HOST'].'/livewhale/?news_edit&amp;id='.$id : ($type=='events' ? 'http'.($_LW->hasSSL() ? 's' : '').'://'.$_LW->CONFIG['HTTP_HOST'].'/livewhale/?events_edit&amp;id='.$id : '')),
			'submission_group'	=> @$_LW->_POST['submission_group'],
		];

		if ($type == 'events') {
			$message_vars['url'] = @$_LW->_POST['event_url'];
		};

		
		// **** Send notification for all submissions to email **** 
		
		// $_LW->d_messages->sendMail('email@myschool.edu', 'public_submission_notification_subject', 'public_submission_notification_body', false, false, $message_vars);


		// ****  Send notifications for specific group to email **** 

		// if ($_LW->_POST['submission_group'] == 67) {
		// 	$_LW->d_messages->sendMail('email@myschool.edu', 'public_submission_notification_subject', 'public_submission_notification_body', false, false, $message_vars);
		// };

	}

	*/




}

?>
