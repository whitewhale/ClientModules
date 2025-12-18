<?php
	
// PHP functionality for a redundant checkbox above the tag multiselect, providing a shortcut for users to select a specific tag. 


$_LW->REGISTERED_APPS['click_to_add_tag']=array(
	'title'=>'Click to Add Tag',
	'handlers'=>array('onLoad','onOutput')
); // configure this module

class LiveWhaleApplicationClicktoAddTag {
	public function onLoad() {
		global $_LW;
		// load custom backend CSS and JS
		$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/click_to_add_tag%5Cclick_to_add_tag.js';
	}
}

?>
