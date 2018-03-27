<?php

/*

This script lists editable IDs in use by pages on the current web host and presents them in chart form.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

$editable_ids=array();
foreach($_LW->dbo->query('select', 'path, elements', 'livewhale_pages', 'host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND is_deleted IS NULL')->run() as $res2) {
	$matches=array();
	preg_match_all('~<element id="([^"]+?)">.+?</element>~s', $res2['elements'], $matches);
	if (!empty($matches[1])) {
		foreach($matches[1] as $element_key=>$element_id) {
			if (!isset($editable_ids[$element_id])) {
				$editable_ids[$element_id]=array('pages'=>array(), 'has_content'=>false);
			};
			if (!in_array($res2['path'], $editable_ids[$element_id]['pages'])) {
				$editable_ids[$element_id]['pages'][]=$res2['path'];
			};
			if (!preg_match('~<element id="'.$element_id.'">\s*?</element>~s', $matches[0][$element_key])) {
				$editable_ids[$element_id]['has_content']=true;
			};
		};
	};
};
echo '<table class="table" style="margin-top: 40px;"><tr><thead><th>ID of editable region</th><th>Pages used:</th><th>Non-empty?</th></thead></tr><tbody>';
ksort($editable_ids);
foreach($editable_ids as $id=>$info) {
	echo '<tr><td>'.$id.'</td><td>'.sizeof($info['pages']).' <a href="#" onclick="$(this).next().removeClass(\'lw_hidden\');$(this).remove();return false;">(show)</a><div class="lw_hidden">'.implode('<br/>', $info['pages']).'</div></td><td>'.(!empty($info['has_content']) ? 'Y' : 'N').'</td></tr>';
};
echo '</tbody></table>';

?>