<?php

$_LW->REGISTERED_APPS['tag_selector']=array(
	'title'=>'Tag Selector',
	'handlers'=>array('onLoad','onFormatPublicSubmission')
); // configure this module

class LiveWhaleApplicationTagSelector {

public function onLoad() { // on frontend request
global $_LW;
if ($_LW->page=='/submit/index.php') { // if on public submissions page
	if ($_LW->page=='/submit/index.php') { // if on public submissions page
		$GLOBALS['global_tags']=$this->getGlobalTagSelector(); // Populate $GLOBALS['global_tags'] with form elements.
	};
};
}


protected function getGlobalTagSelector() { // gets the global tag selector for the event submission form
global $_LW;
$key='events_global_tags_submission'; // get hash key
if (!$global_tags=$_LW->getVariable($key)) { // if value not cached
	$global_tags='';
	$tags=array();
	if ($res=$_LW->dbo->query('select', 'id, title', 'livewhale_tags', 'gid IS NULL AND is_starred IS NOT NULL', 'title ASC')->run()) {
		while ($res2=$res->next()) {
			$tags[]=array('title'=>$_LW->setFormatClean($res2['title']), 'id'=>$res2['id']);
		};
	};
	if (!empty($tags)) { // if tags found
		$arr=array(0=>array(), 1=>array()); // distribute tags into 2 columns
		$half=ceil(sizeof($tags)/2);
		$count=1;
		foreach($tags as $val) {
			if ($count<=$half) {
				$arr[0][]=$val;
			}
			else {
				$arr[1][]=$val;
			};
			$count++;
		};
		$xml1=$_LW->getNew('xpresent'); // create XHTML output
		$xml2=$_LW->getNew('xpresent'); // create XHTML output
		$ul1=$xml1->insert($xml1->ul('', array('class' => 'first')));
		$ul2=$xml2->insert($xml2->ul());
		foreach($arr[0] as $key=>$val) { // assign categories to table
		$ul1->insert($xml1->li('<label><input type="checkbox" name="global_tags[]" value="'.str_replace('"', '', $val['title']).'"/> '.$val['title'].'</label>', array('id'=>'tag'.$val['id'])));
			if (isset($arr[1][$key])) {
				$ul2->insert($xml2->li('<label><input type="checkbox" name="global_tags[]" value="'.str_replace('"', '', $arr[1][$key]['title']).'"/> '.$_LW->setFormatClean($arr[1][$key]['title']).'</label>', array('id'=>'tag'.$arr[1][$key]['id'])));
			};
		};
		$global_tags='<div class="categories global_tags">'.$xml1->toXHTML().$xml2->toXHTML().'</div>'; // export interface to template
	};
	$_LW->setVariable($key, $global_tags, 300);
};
return $global_tags;
}

public function onFormatPublicSubmission($data_type, $buffer) {
global $_LW;
if ($data_type=='events') { // on before submission of an event
	if (!empty($_LW->_POST['global_tags'])) { // if global tags were checked off, assign to event tags
		if (is_array($_LW->_POST['global_tags'])) {
			$buffer['associated_data']['tags'] = $_LW->setFormatClean($_LW->_POST['global_tags']);
		}
	}
};
return $buffer;
}

}

?>