<?php

$_LW->REGISTERED_APPS['form_grader']=array(
	'title'=>'Form Grader',
	'handlers'=>array('onLoad', 'onBeforeEditor', 'onOutput', 'onFormsShowElement', 'onAfterEdit', 'onSaveSuccess', 'onBeforeValidate'),
	'flags'=>array('no_autoload')
); // configure this module

class LiveWhaleApplicationFormGrader {

public function onLoad() {
global $_LW;
if ($_LW->page=='forms_edit') { // if on forms editor
	$_LW->REGISTERED_CSS[]=$_LW->CONFIG['LIVE_URL'].'/resource/css/form_grader%5Cform_grader.css'; // add stylesheet
	$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/form_grader%5Cform_grader.js'; // add script
	$_LW->ENV->input_filter['forms_edit']['answer_message_correct']=array('tags'=>'*', 'wysiwyg'=>1); // allow HTML in these fields
	$_LW->ENV->input_filter['forms_edit']['answer_message_incorrect']=array('tags'=>'*', 'wysiwyg'=>1);
	$_LW->ENV->input_filter['forms_edit']['score_message_pass']=array('tags'=>'*', 'wysiwyg'=>1);
	$_LW->ENV->input_filter['forms_edit']['score_message_fail']=array('tags'=>'*', 'wysiwyg'=>1);
};
}

public function onBeforeEditor($module, $page, $id) {
global $_LW;
if ($page=='forms_edit') { // if on forms editor
	$_LW->REGISTERED_MODULES['forms']['custom']['element_prototype']=str_replace('<div class="extra2">', '<div class="extra2">
		<div class="element_correctanswer">
			<h6>Correct answer:</h6>
			<input type="text" name="correct_answer[]" class="form-control" value="" placeholder="Correct answer (optional)"/>
		</div>
		<div class="element_messages">
			<h6>Message if correct:</h6>
			<textarea class="form-control" name="answer_message_correct[]"></textarea>
			<h6>Message if incorrect:</h6>
			<textarea class="form-control" name="answer_message_incorrect[]"></textarea>
		</div>', $_LW->REGISTERED_MODULES['forms']['custom']['element_prototype']); // add the correct answer fields
};
}

public function onBeforeValidate($type, $id) { // when validating an item of any data type
global $_LW;
if ($type=='forms') {
	if (!empty($_LW->_POST['score_pass']) || !empty($_LW->_POST['correct_answers_pass'])) {
		if (!empty($_LW->_POST['score_pass'])) {
			$_LW->_POST['score_pass']=str_replace('%', '', $_LW->_POST['score_pass']);
			if (!preg_match('~^[0-9]+$~', $_LW->_POST['score_pass'])) {
				$_LW->REGISTERED_MESSAGES['failure'][]='Passing score as a percentage must be a numeric value.';
			};
		};
		if (!empty($_LW->_POST['correct_answers_pass'])) {
			if (!preg_match('~^[0-9]+$~', $_LW->_POST['correct_answers_pass'])) {
				$_LW->REGISTERED_MESSAGES['failure'][]='Passing score as a minimum of correct answers must be a numeric value.';
			};
		};
		if (!empty($_LW->_POST['score_pass']) && !empty($_LW->_POST['correct_answers_pass'])) {
			$_LW->REGISTERED_MESSAGES['failure'][]='Passing score must be indicated as a minimum of correct answers or a percentage, but not both.';
		};
	};
};
}

public function onOutput($buffer) {
global $_LW;
if ($_LW->page=='forms_edit') { // if on forms editor
	$buffer=str_replace('<!-- END CAPTCHA -->', '<!-- END CAPTCHA -->
		<!-- START GRADED -->
		<fieldset class="option forfullitem">
			<label for="is_graded" class="inline"><strong>Graded Quiz</strong></label><br/>
			<label class="inline">
				<input type="checkbox" id="is_graded" name="is_graded" value="1"'.(!empty($_LW->_POST['is_graded']) ? ' checked="checked"' : '').'/> Add correct answers to form fields for grading purposes.
			</label>
		</fieldset>
		<!-- END GRADED -->', $buffer); // add the grading toggle
	$buffer=str_replace('<!-- END SECTION ELEMENTS -->', '<!-- END SECTION ELEMENTS -->
		<!-- START SECTION GRADED -->
		<section id="grading">
			<header class="grading">
				<h2>Grading options</h2>
			</header>
			<div class="section_content">
				<div class="sidebar">
					<div class="fields">
						<label class="header" for="score_pass">Passing score</label>
						<fieldset>
							<div class="form-inline">
								<p><input type="text" class="form-control" id="correct_answers_pass" name="correct_answers_pass" value="'.@$_LW->_POST['correct_answers_pass'].'"/> out of correct answers to pass <em>—or—</p>
								<p><input type="text" class="form-control" id="score_pass" name="score_pass" value="'.@$_LW->_POST['score_pass'].'"/> % of answers correct to pass</em></p>
							</div>
						</fieldset>
					</div>
				</div>
				<div class="body">
					<div class="fields option forfullitem">
						<label class="header">Messages</label>
						<fieldset id="score_message_pass">
							<label>Message if correct:</label>
							<textarea class="form-control" rows="8" id="forms_score_message_pass" name="score_message_pass">'.$_LW->setFormatClean(@$_LW->_POST['score_message_pass']).'</textarea>
							<label>Message if incorrect:</label>
							<textarea class="form-control" rows="8" id="forms_score_message_fail" name="score_message_fail">'.$_LW->setFormatClean(@$_LW->_POST['score_message_fail']).'</textarea>
						</fieldset>
					</div>
				</div>
			</div>
		</section>
		<!-- END SECTION GRADED -->', $buffer); // add the grading section
};
return $buffer;
}

public function onFormsShowElement($form_id, $element_id, $buffer) {
global $_LW;
$this->getFormGraderFields($form_id); // get custom fields
if (!empty($element_id)) {
	if (!empty($_LW->_POST['correct_answer'][$element_id])) { // restore correct answer values
		$buffer=$_LW->d_forms->updateValueForInput('correct_answer', $_LW->_POST['correct_answer'][$element_id], $buffer);
	};
	if (!empty($_LW->_POST['answer_message_correct'][$element_id])) { // restore correct answer msg values
		$buffer=$_LW->d_forms->updateValueForTextarea('answer_message_correct', $_LW->_POST['answer_message_correct'][$element_id], $buffer);
	};
	if (!empty($_LW->_POST['answer_message_incorrect'][$element_id])) { // restore incorrect answer msg values
		$buffer=$_LW->d_forms->updateValueForTextarea('answer_message_incorrect', $_LW->_POST['answer_message_incorrect'][$element_id], $buffer);
	};
};
return $buffer;
}

public function onAfterEdit($type, $page, $id) {
global $_LW;
if ($page=='forms_edit') { // if on forms editor
	if (!empty($_LW->is_first_load) && !empty($id)) { // get custom fields for editor
		$this->getFormGraderFields($id);
	};
};
}

public function onSaveSuccess($type, $id) {
global $_LW;
if ($type=='forms' && $_LW->page=='forms_edit') { // if saving a form from the editor
	if (!empty($_LW->_POST['is_graded']) || (!empty($_LW->_POST['correct_answer']) && implode('', $_LW->_POST['correct_answer'])!='')) {
		$fields=array(
			'is_graded'=>@$_LW->_POST['is_graded'],
			'correct_answer'=>$_LW->setFormatSanitize(@$_LW->_POST['correct_answer']),
			'answer_message_correct'=>$_LW->setFormatSanitize(@$_POST['answer_message_correct']),
			'answer_message_incorrect'=>$_LW->setFormatSanitize(@$_POST['answer_message_incorrect']),
			'score_pass'=>(int)@$_LW->_POST['score_pass'],
			'correct_answers_pass'=>(int)@$_LW->_POST['correct_answers_pass'],
			'score_message_pass'=>$_LW->setFormatSanitize(@$_POST['score_message_pass']),
			'score_message_fail'=>$_LW->setFormatSanitize(@$_POST['score_message_fail'])
		);
	}
	else {
		$fields=array(
			'is_graded'=>'',
			'correct_answer'=>'',
			'answer_message_correct'=>'',
			'answer_message_incorrect'=>'',
			'score_pass'=>'',
			'correct_answers_pass'=>'',
			'score_message_pass'=>'',
			'score_message_fail'=>''
		);
	};
	$_LW->setCustomFields($type, $id, $fields, array()); // save the graded toggle
};
}

protected function getFormGraderFields($id) {
global $_LW;
static $was_fetched;
if (!isset($was_fetched)) { // only fetch custom fields once (since we fetch them from 2 possible contexts)
	if ($fields=$_LW->getCustomFields('forms', $id)) {
		foreach($fields as $key=>$val) {
			$_LW->_POST[$key]=$val;
		};
	};
	$was_fetched=true;
};
}

}

?>