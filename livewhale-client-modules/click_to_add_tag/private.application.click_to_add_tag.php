<?php
	
// PHP functionality for a redundant checkbox above the tag multiselect, providing a shortcut for users to select a specific tag. 
$_LW->REGISTERED_APPS['click_to_add_tag']=array(
	'title'=>'Click to Add Tag',
	'handlers'=>['onLoad','onOutput'],
  'custom' => [
  [
    'label' => 'Creative Campus',  
    'tag_id' => 336,
  ],
  [
    'label' => 'Dance',  
    'tag_id' => 139,
  ],
  [
    'label' => 'Design',  
    'tag_id' => 337,
  ],
  [
    'label' => 'Music',  
    'tag_id' => 170,
  ],
  [
    'label' => 'Theater',  
    'tag_id' => 186,
  ],
  [
    'label' => 'Visual Arts',  
    'tag_id' => 338,
  ]
 ]
); // configure this module

class LiveWhaleApplicationClicktoAddTag {
	public function onLoad() {
		global $_LW;
		// load custom backend CSS and JS
		$_LW->REGISTERED_JS[]=$_LW->CONFIG['LIVE_URL'].'/resource/js/click_to_add_tag%5Cclick_to_add_tag.js';
	}
  public function onOutput($buffer)
  {
    global $_LW;
  
    // if on the events editor pages of CFA group (#20) or Arts and Culture (#9)
    if (($_LW->page =='events_edit' || $_LW->page =='events_sub_edit') && (@$_SESSION['livewhale']['manage']['gid']==20 || @$_SESSION['livewhale']['manage']['gid']==9 ) {  // define pages to target with this transformation
      $html = '';
      foreach($_LW->REGISTERED_APPS['click_to_add_tag']['custom'] as $tag) {
        $html .= '<li><label>
          <input type="checkbox" name="tags[]" data-name="'.$tag['label'].'" value="'.$tag['tag_id'].'">
          <span class="lw-name">'.$tag['label'].'</span>
          </label></li>';
      }
      $buffer = str_replace(
        '<fieldset class="categories type">',
        '<fieldset class="categories cfa"><legend>CFA Event Types (Select one)</legend>
        <ul style="column-count: 2; width: 96%;">
        ' . $html .'</ul></fieldset><fieldset class="categories type">',
        $buffer
      );
      $buffer = str_replace(
        '<legend class="lw_sr_only">Event type(s)</legend>',
        '<legend>Wesleyan Event Types</legend>',
        $buffer
      );

    }
    return $buffer;
  }
}

?>
