<?php

// PHP functionality for a redundant checkbox above the group suggest selector box, providing a shortcut for users to suggest to a specific chosen group (like the homepage or main communications team).

$_LW->REGISTERED_APPS['suggest_to']=array(
	'title' => 'suggest_to',
	'handlers' => ['onLoad','onOutput'],
	'custom' => [
		'label' => 'Recommend this event to main UoM calendar',   // Customize checkbox label as appropriate
		'group_id' => 3
	]
); // configure this module

class LiveWhaleApplicationSuggestTo
{
	public function onLoad()
	{
		global $_LW;
		// load custom backend CSS and JS
		$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/suggest_to%5Csuggest_to.js';
		$_LW->json['suggest_to_group_id'] = $_LW->REGISTERED_APPS['suggest_to']['custom']['group_id'];
	}
	public function onOutput($buffer)
	{
		global $_LW;

		// if on the events editor pages or news editor page
		if ($_LW->page =='events_edit' || $_LW->page =='events_sub_edit') {  // define pages to target with this transformation
			$buffer = str_replace(
				'<fieldset id="suggest">',
				'<fieldset id="suggest">
				<div class="checkbox" id="main_group_share">
				<label><input type="checkbox" value="1"/>'.$_LW->REGISTERED_APPS['suggest_to']['custom']['label'].'</label>
				</div>',
				$buffer
			);
		}
		return $buffer;
	}
}

?>
