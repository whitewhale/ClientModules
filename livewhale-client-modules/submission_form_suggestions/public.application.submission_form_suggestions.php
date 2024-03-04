<?php

$_LW->REGISTERED_APPS['submission_form_suggestions']=array(
	'title'=>'Submission Form Suggestions',
	'handlers'=>array('onLoad', 'onFormatPublicSubmission'),
	'application'=>array(
		'order'=>-1
	)
); // configure this module

class LiveWhaleApplicationSubmissionFormSuggestions {

public function onLoad() { // initializes a public submissions form
global $_LW;

if ($_LW->page=='/submit/index.php') {

	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/submission_form_suggestions%5Csubmission_form_suggestions.css';
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/submission_form_suggestions%5Csubmission_form_suggestions.js';

	$GLOBALS['all_calendars']='<div id="lw_submission_form_suggestions" class="lw_selector filter">
		<div class="filter-label btn" aria-expanded="false" aria-controls="all-calendars-filter-dropdown" role="button" tabindex="0">
			<span class="filter-title" id="lw_cal_all_calendars_selector_label" lw_sr_only="Suggest events to calendars" role="label">Suggest to Calendars:</span>
			<span class="lw-icon lw-icon-angle-down filter-icon"></span>
		</div>
	<fieldset>
	<div class="filter-dropdown" id="all-calendars-dropdown" role="listbox" aria-labelledby="lw_cal_all_calendars_selector_label">

	<ul>';

	foreach($_LW->dbo->query('select', 'livewhale_groups.id, livewhale_groups.fullname as title, IF(livewhale_groups.fullname_public IS NOT NULL, livewhale_groups.fullname_public, livewhale_groups.fullname) as display_title, livewhale_groups.directory', 'livewhale_groups', 'livewhale_groups.fullname!="Public" && livewhale_groups.fullname!="Admin"', 'IF(livewhale_groups.fullname_public IS NOT NULL, livewhale_groups.fullname_public, livewhale_groups.fullname) ASC')->groupBy('livewhale_groups.id')->run() as $res2) { // fetch calendars

		$GLOBALS['all_calendars'].='<li class="filter-option"><label><input type="checkbox" name="suggest_to[]" value="' . $_LW->setFormatClean($res2['id']) .'" />' . $_LW->setFormatClean($res2['display_title']) . '</label></li>';

	};
	$GLOBALS['all_calendars'].='</ul></div></fieldset></div>';
};

}


public function onFormatPublicSubmission($type, $fields) {
global $_LW;
if (!empty($_LW->_POST['suggest_to'])) { // share to specified groups
	$fields['associated_data']['suggested']=$_LW->_POST['suggest_to'];
};

return $fields;
}


}

?>