<?php

$_LW->REGISTERED_APPS['public_notifier']=array(
        'title'=>'PublicNotifier',
        'handlers' => array('onAfterPublicSubmission')
);

class LiveWhaleApplicationPublicNotifier {

  // These two arrays are for including the users and/or groups that
  // should receive notifications when public submissions are made.
  // Check that you don't include a user in the users array that is
  // also in a group in the group array as they will get duplicates
  // and hate you. ;)
  //
  // To find a user's id, edit the user you want and check for the id
  // in the browser url, e.g. /livewhale/?users_edit?id=##&gid=##
  // Note that you can also see their default group id there too, in
  // the gid parameter.
  //
  // Likewise for groups, edit the group and note the url:
  // /livewhale/?groups_edit?id=##
  //
  protected $users = array(
    1, // user id
    2, // user id
    );
  protected $groups = array(
    1, // group id
    2, // group id
    );

  // onAfterPublicSubmission
  // This the handler method; it should receive type and id as parameters.
  //
  // $type : string, the pluralized name of the datatype, e.g. news, events, blurbs
  // $id : integer, the id of the public submission just created
  //
  // In the handler, we do a redundant check for the type and id, since they
  // are required for any action. We then check for the existence of LiveWhale's
  // core messaging utility. It would normally exist if you were logged into
  // LiveWhale, but wouldn't have been loaded for a simple public submission.
  // It is added and instantiated if not present. Finally, we send the message
  // to the users and groups in the above arrays.
  //
  // LiveWhale core messaging also has the ability to put a message into a user's
  // alert section on their welcome page, but as LiveWhale already does that for
  // admins, I've skipped doing that here. Check the documentation section for a
  // more in-depth discussion of LiveWhale core messaging functionality.
  //
  public function onAfterPublicSubmission ($type, $id) {
    global $_LW;
    
    if ( empty($type) || empty($id) ) { // check that type and id are present
      return NULL;
    }
    
    if (!isset($_LW->d_message)) { // load messaging if not present
      if (!class_exists('LiveWhaleDataMessages')) {
        if (file_exists($_LW->INCLUDES_DIR_PATH.'/core/modules/messages/global.data.messages.php')) {
            require $_LW->INCLUDES_DIR_PATH.'/core/modules/messages/global.data.messages.php';
        }
        else {
            require $_LW->INCLUDES_DIR_PATH.'/core/modules/messages/private.data.messages.php';
        };
      };
      $_LW->d_messages=new LiveWhaleDataMessages;
    };

    $email_subject = "A New Public Submission Awaits You";
    $email_message = "A public submission has been made. "
                   . "You can view it at the following address:\n\n"
                   . "https://{$_SERVER['HTTP_HOST']}/livewhale/?{$type}_edit&id={$id}";
    foreach ( (array) $this->users as $uid ) {
      $_LW->d_messages->add(FALSE, $uid, FALSE, $email_subject, $email_message, FALSE, FALSE, FALSE, TRUE, FALSE);
    }
    foreach ( (array) $this->groups as $gid ) {
      $_LW->d_messages->add($gid, FALSE, FALSE, $email_subject, $email_message, FALSE, FALSE, FALSE, TRUE, FALSE);
    }
    return NULL;
  }
  
}
?>