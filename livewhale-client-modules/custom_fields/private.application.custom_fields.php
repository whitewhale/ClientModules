<?php

$_LW->REGISTERED_APPS['custom_fields'] = array( // configure this application module
	'title' => 'Custom Fields',
	'handlers' => array('onLoad', 'onAfterValidate', 'onSaveSuccess', 'onAfterEdit', 'onOutput')
);

class LiveWhaleApplicationCustomFields {

/* The onLoad() handler allows you to load in additional resources for the page when this application first loads. */

public function onLoad() {
global $_LW;
if ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit') { // if on the events editor page
	//$_LW->REGISTERED_CSS[]='/path/to/custom/stylesheet.css'; // load in some custom CSS for styling the new field (optional)
	$_LW->ENV->input_filter['events_edit']['sample_textarea']=array('tags'=>'*', 'wysiwyg'=>1); // configure the input filter to present the textarea custom field as a WYSIWYG field (omit this line entirely for no HTML allowed, or change "wysiwyg" to "wysiwyg_limited" for the limited set of toolbar options)
};
}

/* The onAfterValidate() handler allows you to add additional validation checks after clicking the save button on a backend editor. */

public function onAfterValidate() {
global $_LW;
if ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit') { // if saving from the events editor page
	/*
	if (!empty($_LW->_POST['sample_textarea']) && stripos($_LW->_POST['sample_textarea'], 'supercalifragilisticexpialidocious') !== false) { // optionally disallow the word "supercalifragilisticexpialidocious" from this custom field
		$_LW->REGISTERED_MESSAGES['failure'][] = 'Custom Field (Textarea) cannot contain the word supercalifragilisticexpialidocious.'; // register error
	};
	*/
};
}

/* The onAfterEdit() handler allows you to load additional custom data from the database after the default editor data is loaded in. */

public function onAfterEdit($module, $page, $id) {
global $_LW;
if ($page=='events_edit' || $page=='events_sub_edit') { // if loading data for the events editor form
	if (!empty($_LW->is_first_load) && !empty($id)) { // if loading the editor for the first time for an existing item
		if ($fields=$_LW->getCustomFields($module, $id)) { // getCustomFields($module, $id) gets any previously saved custom data for the item of this $module and $id
			foreach($fields as $key=>$val) { // add previously saved data to POST data so it prepopulates in the editor form
				$_LW->_POST[$key]=$val;
			};
		};
	};
};
if ($page=='profiles_edit' && $_LW->_GET['tid']==1) { // if loading the profiles editor, but only for profile type with ID = 1
	// do something for this type only
};
}

/* The onSaveSuccess() handler allows you to store the custom data after the item has successfully saved its default set of data. */

public function onSaveSuccess($type, $id) {
global $_LW;
if ($type=='events' && ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit')) { // if saving an event from the editor
	$_LW->setCustomFields($type, $id, array('sample_textarea'=>@$_LW->_POST['sample_textarea']), array()); // store the value entered for sample_textarea, allowing the sample_textarea field full visibility (on details pages, in widget results, and /live/* requests such as /live/json)
	/*
	Note:
 	To optionally hide the field (i.e. store it in the database but not expose it to the public on the frontend web site or API requests, add "sample_textarea" to the empty array above, registering it as a hidden field).
	Non-hidden fields may be added to a details template via <xphp var="details_custom_sample_textarea"/> or to a widget format arg via {custom_sample_textarea}.
	*/
	$_LW->setCustomFields($type, $id, array('sample_text'=>@$_LW->_POST['sample_text']), array()); // store the value
	$_LW->setCustomFields($type, $id, array('sample_select'=>@$_LW->_POST['sample_select']), array()); // store the value
	$_LW->setCustomFields($type, $id, array('sample_checkbox'=>@$_LW->_POST['sample_checkbox']), array()); // store the value
	$_LW->setCustomFields($type, $id, array('sample_radio'=>@$_LW->_POST['sample_radio']), array()); // store the value
};
}

/* The onOutput() hander allows you to add the custom form element to the editor. */

public function onOutput($buffer) {
global $_LW;
if ($_LW->page=='events_edit' || $_LW->page=='events_sub_edit') { // if on the events editor page
	$new_field=$_LW->displayCustomField('textarea', 'sample_textarea', @$_LW->_POST['sample_textarea'], false); // create the new element (type, element id, preset value, options - for multivalue fields)
	// use <!-- START or END ELEMENT --> comments in template to position and inject your new field:
	$buffer=str_replace('<!-- END LOCATION -->', '<!-- END LOCATION -->
		<!-- START SAMPLE_TEXTAREA -->
		<div class="fields sample_textarea">
			<label class="header" for="sample_textarea">Custom Field (sample_textarea)</label>
			<fieldset>
				'.$new_field.'
			</fieldset>
		</div>
		<!-- END SAMPLE_TEXTAREA -->', 
	$buffer);
	$new_field=$_LW->displayCustomField('text', 'sample_text', @$_LW->_POST['sample_text'], false); // create the new element
	$buffer=str_replace('<!-- END LOCATION -->', '<!-- END LOCATION -->
		<!-- START SAMPLE_TEXT -->
		<div class="fields sample_text">
			<label class="header" for="sample_text">Custom Field (sample_text)</label>
			<fieldset>
				'.$new_field.'
			</fieldset>
		</div>
		<!-- END SAME_FIELD_TEXT -->', 
	$buffer);
	$new_field=$_LW->displayCustomField('select', 'sample_select', @$_LW->_POST['sample_select'], array('one', 'two', 'three')); // create the new element
	$buffer=str_replace('<!-- END LOCATION -->', '<!-- END LOCATION -->
		<!-- START SAMPLE_SELECT -->
		<div class="fields sample_select">
			<label class="header" for="sample_select">Custom Field (sample_select)</label>
			<fieldset>
				'.$new_field.'
			</fieldset>
		</div>
		<!-- END SAMPLE_SELECT -->', 
	$buffer);
	$new_field=$_LW->displayCustomField('checkbox', 'sample_checkbox', @$_LW->_POST['sample_checkbox'], array('a', 'b', 'c')); // create the new element
	$buffer=str_replace('<!-- END LOCATION -->', '<!-- END LOCATION -->
		<!-- START SAMPLE_CHECKBOX -->
		<div class="fields sample_checkbox">
			<label class="header" for="sample_checkbox">Custom Field (sample_checkbox)</label>
			<fieldset>
				'.$new_field.'
			</fieldset>
		</div>
		<!-- END SAMPLE_CHECKBOX -->', 
	$buffer);
	$new_field=$_LW->displayCustomField('radio', 'sample_radio', @$_LW->_POST['sample_radio'], array('yes', 'no'), 'yes'); // create the new element
	$buffer=str_replace('<!-- END LOCATION -->', '<!-- END LOCATION -->
		<!-- START SAMPLE_RADIO -->
		<div class="fields sample_radio">
			<label class="header" for="sample_radio">Custom Field (sample_radio)</label>
			<fieldset>
				'.$new_field.'
			</fieldset>
		</div>
		<!-- END SAMPLE_RADIO -->', 
	$buffer);
};
return $buffer;
}

}

?>