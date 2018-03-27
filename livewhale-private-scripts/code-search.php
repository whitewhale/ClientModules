<?php

/*

This script presents a search form to search for HTML markup strings across web pages.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

echo '<form method="post"><input type="text" name="q" value="'.$_LW->setFormatClean(@$_POST['q']).'"/> <input type="submit" value="Search"/> <input type="checkbox" value="1" name="search_current"'.(!empty($_POST['search_current']) ? ' checked="checked"' : '').'/> Search current revisions only <input type="checkbox" value="1" name="search_deleted"'.(!empty($_POST['search_deleted']) ? ' checked="checked"' : '').'/> Search deleted pages too</form><br/>'; // add search form

if (!empty($_POST['q'])) { // if there was a search
	if ($results=$_LW->dbo->query('select', 'livewhale_pages_revisions.id, livewhale_pages_revisions.path, livewhale_pages_revisions.last_modified, livewhale_pages_revisions.content, livewhale_pages.is_deleted, IF(livewhale_pages_revisions.last_modified=livewhale_pages.last_modified,1,0) AS is_current_revision', 'livewhale_pages_revisions', 'livewhale_pages_revisions.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).(empty($_POST['search_deleted']) ? ' AND livewhale_pages.is_deleted IS NULL' : '').' AND livewhale_pages_revisions.last_modified>NOW() - INTERVAL 1 YEAR'.(!empty($_POST['search_current']) ? ' AND livewhale_pages_revisions.last_modified=livewhale_pages.last_modified' : ''), 'livewhale_pages_revisions.path ASC, livewhale_pages_revisions.last_modified DESC')->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_revisions.host AND livewhale_pages.path=livewhale_pages_revisions.path')->run()) { // fetch all page revisions modified within the last year
		if ($results->hasResults()) {
			echo '<h3>Search results for "'.$_LW->setFormatClean($_POST['q']).'":</h3>';
			$last_path='';
			$last_match='';
			while ($res2=$results->next()) { // for each revision
				$is_current_revision=!empty($res2['is_current_revision']); // check if current revision
				$is_deleted=!empty($res2['is_deleted']); // check if current revision is deleted
				if ($last_match!=$res2['path']) { // only find most recent match per page
					if ($res2['content']=@gzinflate($res2['content'])) { // decompress page content
						if (preg_match('~'.str_replace(array("\n", "\r", ' ', '>\<'), array(' ', ' ', '\s+', '>\s*\<'), preg_quote($_POST['q'], '~')).'~s', $res2['content'])) { // if code matched
							echo '<p><a href="http://'.$_LW->CONFIG['HTTP_HOST'].$res2['path'].'">http://'.$_LW->CONFIG['HTTP_HOST'].$res2['path'].'</a> <span style="font-size:0.8em;">'.($is_deleted ? '(now deleted)' : ($is_current_revision ? '(current revision)' : '(<a href="'.$_LW->CONFIG['LIVE_URL'].'/pages/revision/'.$res2['id'].'" target="blank">revision '.$res2['id'].'</a> @ '.date('m/d/y g:ia', strtotime($res2['last_modified'])).')')).'</span></p>'; // add result
							$last_match=$res2['path']; // increment last matched path
						};
					};
				};
				$last_path=$res2['path']; // increment last analyzed path
			};
		};
	};
};

?>