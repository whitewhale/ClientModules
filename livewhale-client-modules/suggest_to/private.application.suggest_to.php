<?php

// PHP functionality for a redundant checkbox above the group suggest selector box, providing a shortcut for users to suggest to a specific chosen group (like the homepage or main communications team).

$_LW->REGISTERED_APPS['suggest_to']=array(
	'title' => 'suggest_to',
	'handlers' => ['onLoad','onOutput'],
	'custom' => [
		[
			'label' => 'University of Chicago homepage',  
			'group_id' => 3,
		],
// additional groups can be added here
//		[
//			'label' => 'UChicago intranet',   
//			'group_id' => 498
//		]
	]
); // configure this module

class LiveWhaleApplicationSuggestTo
{
	public function onLoad()
	{
		global $_LW;
		// load custom backend CSS and JS
		$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/suggest_to%5Csuggest_to.js';
		$_LW->json['suggest_to_groups'] = $_LW->REGISTERED_APPS['suggest_to']['custom'];
	}
	public function onOutput($buffer)
	{
		global $_LW;

		// if on the events editor pages or news editor page
		if ($_LW->page =='events_edit' || $_LW->page =='events_sub_edit') {  // define pages to target with this transformation
			$html = '';
			foreach($_LW->REGISTERED_APPS['suggest_to']['custom'] as $group) {
				$html .= '<div class="checkbox main_group_share">'
					. '<label><input type="checkbox" value="' . $group['group_id'] . '"/>' . $group['label'] . '</label>'
					. '</div>';
			}
			$buffer = str_replace(
				'<fieldset id="suggest">',
				'<fieldset id="suggest">
				<strong>Feature this event</strong> (requires approval)
				' . $html . '
				<p><strong>Suggest to other calendar groups:</strong></p>',
				$buffer
			);
			$buffer = str_replace(
				'Suggest this event to the following group(s):',
				'Share to other calendars',
				$buffer
			);
		}
		return $buffer;
	}
}

?>
