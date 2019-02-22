<?php

/* This module is optional and demonstrates how you might also want to expose custom fields to a static web page according to some logic. In this example, custom template variables are set according to the group owner of a particular page. */

$_LW->REGISTERED_APPS['custom_fields'] = array( // configure this application module
	'title' => 'Custom Fields',
	'handlers' => array('onBeforeOutput')
);

class LiveWhaleApplicationCustomFields {

public function onBeforeOutput($buffer) { // loads custom fields as frontend template vars
global $_LW;
$_LW->applyPageAndGroupVars('<xphp var="group_id"/>'); // get the group ID for this page
if (!empty($GLOBALS['group_id'])) { // if it was found
	if ($fields=$_LW->getCustomFields('groups', $GLOBALS['group_id'])) { // fetch custom vars tied to that group
		foreach($fields as $key=>$val) { // and add them to the page with a "custom_" prefix
			$GLOBALS['custom_'.$key]=$val;
		};
	};
};
return $buffer;
}

}

?>