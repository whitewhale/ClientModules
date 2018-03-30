<?php

$_LW->REGISTERED_APPS['form_grader']=array(
	'title'=>'Form Grader',
	'handlers'=>array('onLoad', 'onFormsSuccess', 'onFormSubmissionEmailFormat', 'onFormsOutput')
); // configure this module

class LiveWhaleApplicationFormGrader {

public function onLoad() {
global $_LW;
if (!empty($_LW->_POST['lw_form_id'])) { // if this is a posted form
	if ($fields=$_LW->getCustomFields('forms', $_LW->_POST['lw_form_id'])) { // get custom fields
		if (!empty($fields['is_graded']) && !empty($fields['correct_answer'])) { // if this is a graded form
			if ($template=$this->getTemplate()) { // get the template for the score
				$score=$this->getScore($fields, $template); // get the score
				$GLOBALS['lw_form_'.$_LW->_POST['lw_form_id'].'_score']=$score; // export score to template
			};
		};
	};
};
}

public function onFormsOutput($buffer, $id) {
global $_LW;
if ($forms=$buffer->query('//form')) { // add class to form and each graded fieldset
	foreach($forms as $form) {
		if ($form->hasAttribute('id')) {
			$id=$form->getAttribute('id');
			if (preg_match('~^lw_form_[0-9]+$~', $id)) {
				$id=substr($id, 8);
				if ($fields=$_LW->getCustomFields('forms', $id)) { // get custom fields
					if (!empty($fields['is_graded']) && !empty($fields['correct_answer'])) { // if this is a graded form
						$class=$form->hasAttribute('class') ? $form->getAttribute('class') : '';
						$class=(!empty($class) ? $class.' ' : '').'graded_quiz';
						$form->setAttribute('class', $class);
						foreach($fields['correct_answer'] as $key=>$val) {
							if (!empty($val)) {
								if ($fieldsets=$buffer->query('//fieldset[contains(@class, "lw_forms_field_'.$id.'_'.$key.'")]')) {
									foreach($fieldsets as $fieldset) {
										$class=$fieldset->hasAttribute('class') ? $fieldset->getAttribute('class') : '';
										$class=(!empty($class) ? $class.' ' : '').'graded';
										$fieldset->setAttribute('class', $class);
									};
								};
							};
						};
						if (!isset($_LW->d_widgets)) {
							$_LW->initModule('data', 'widgets');
						};
						$form->insert($buffer->xhtml('<div class="lw_widget_metadata lw_hidden"><a href="/live/resource/js/form_grader%5Cform_grader_frontend.js" class="lw_widget_resources_js" rel="nofollow"></a><a href="/live/resource/css/form_grader%5Cform_grader_frontend.css" class="lw_widget_resources_css" rel="nofollow"></a></div>')); // add frontend JS/CSS
					};
				};
			};
		};
	};
};
return $buffer;
}

public function onFormsSuccess($buffer, $form_id) {
global $_LW;
if (!empty($_LW->_POST['lw_form_id'])) { // if this is a posted form
	if ($fields=$_LW->getCustomFields('forms', $_LW->_POST['lw_form_id'])) { // get custom fields
		if (!empty($fields['is_graded']) && !empty($fields['correct_answer'])) { // if this is a graded form
			if ($template=$this->getTemplate()) { // get the template for the score
				$score=$this->getScore($fields, $template); // get the score
				$buffer.=$score; // add it to success message
			};
		};
	};
};
return $buffer;
}

public function onFormSubmissionEmailFormat($form_id, $buffer) {
global $_LW;
if (!empty($_LW->_POST['lw_form_id']) && $form_id==$_LW->_POST['lw_form_id']) { // if we have the submission
	if ($fields=$_LW->getCustomFields('forms', $form_id)) { // get custom fields
		if (!empty($fields['is_graded']) && !empty($fields['correct_answer'])) { // if this is a graded form
			if ($template=$this->getTemplate()) { // get the template for the score
				$score=$this->getScore($fields, $template); // get the score
				$buffer.=$score; // add it to success message
			};
		};
	};
};
return $buffer;
}

protected function getTemplate() {
global $_LW;
$theme=$_LW->theme=='core' ? 'global' : $_LW->theme; // get the theme to check first
if (file_exists($_LW->WWW_DIR_PATH.'/_ingredients/themes/'.$theme.'/templates/form_grader.html')) { // get the template for the score
	return file_get_contents($_LW->WWW_DIR_PATH.'/_ingredients/themes/'.$theme.'/templates/form_grader.html');
}
else {
	return file_get_contents($_LW->INCLUDES_DIR_PATH.'/client/modules/form_grader/templates/form_grader.html');
};
}

protected function getScore($fields, $template) {
global $_LW;
if ($form=$_LW->dbo->query('select', 'title, structure', 'livewhale_forms', 'id='.(int)$_LW->_POST['lw_form_id'])->firstRow()->run()) { // get the form details
	if ($form['structure']=@unserialize($form['structure'])) { // decode form structure
		$matches=array();
		preg_match_all('~<li[^>]*?>.+?</li>~s', $template, $matches);
		if (!empty($matches[0]) && sizeof($matches[0])===1) { // get <li> from template
			$li=$matches[0][0];
			$template=str_replace($li, '<xphp var="form_grader_results"/>', $template); // swap variable in for li
			$questions=array();
			$count_correct=0;
			foreach($fields['correct_answer'] as $key=>$val) {
				if (!empty($val) && $form['structure']['header'][$key]) { // if there is a correct answer configured and this is an actual question element
					$question=array();
					$question['correct_answer']=$val; // get correct answer
					$question['actual_answer']=@$_LW->_POST['lw_form_'.$_LW->_POST['lw_form_id'].'_'.$key]; // get actual answer
					if (is_array($question['actual_answer'])) {
						$question['actual_answer']=implode('', $question['actual_answer']);
					};
					$question['correct_answer_comp']=strtolower(preg_replace('~[^a-zA-Z0-9]~', '', $question['correct_answer'])); // format correct answer for comparison
					$question['actual_answer_comp']=strtolower(preg_replace('~[^a-zA-Z0-9]~', '', $question['actual_answer'])); // format actual answer for comparison
					$question['is_correct']=($question['actual_answer_comp']==$question['correct_answer_comp']); // check if answer was correct
					if (!empty($question['is_correct'])) { // if answer was correct
						$count_correct++; // increment correct answers count
					};
					$GLOBALS['form_grader_question']=$_LW->setFormatClean($form['structure']['header'][$key]); // set values for result
					$GLOBALS['form_grader_actual_answer']=$_LW->setFormatClean($question['actual_answer']);
					$GLOBALS['form_grader_correct_answer']=$_LW->setFormatClean($question['correct_answer']);
					$GLOBALS['form_grader_answer_message_correct']=!empty($question['is_correct']) ? $_LW->setFormatClean(@$fields['answer_message_correct'][$key]) : '';
					$GLOBALS['form_grader_answer_message_incorrect']=empty($question['is_correct']) ? $_LW->setFormatClean(@$fields['answer_message_incorrect'][$key]) : '';
					$question['li']=$_LW->xphp->parseString($li); // create result for question
					$questions[]=$question; // record scored question
				};
			};
			if (!empty($questions)) { // if there were scored questions
				$GLOBALS['form_grader_results']='';
				foreach($questions as $question) { // set value for all results
					$GLOBALS['form_grader_results'].=$question['li']."\n";
				};
				$GLOBALS['form_grader_form_name']=$form['title']; // set form name
				$GLOBALS['form_grader_number_correct']=$count_correct; // set count for correct answers
				$GLOBALS['form_grader_number_scored']=sizeof($questions); // set count of scored answers
				$GLOBALS['form_grader_score']=round($GLOBALS['form_grader_number_correct']/$GLOBALS['form_grader_number_scored']*100); // set value for score
				$GLOBALS['form_grader_score_message_pass']=(!empty($fields['score_pass']) && $GLOBALS['form_grader_score']>=$fields['score_pass']) ? @$fields['score_message_pass'] : ''; // set value for pass message
				$GLOBALS['form_grader_score_message_fail']=(!empty($fields['score_pass']) && $GLOBALS['form_grader_score']<$fields['score_pass']) ? @$fields['score_message_fail'] : ''; // set value for fail message
				$GLOBALS['form_grader_required_score']=$fields['score_pass']; // add value for a required score
				$GLOBALS['form_grader_score_passed']=(!empty($fields['score_pass']) && $GLOBALS['form_grader_score']>=$fields['score_pass']) ? 1 : ''; // set flag for score pass
				return $_LW->xphp->parseString($template); // return score
			};
	
		};
	};
};
return '';
}

}