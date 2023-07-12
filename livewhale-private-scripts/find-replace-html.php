<?php

/*

This script does a site-wide find/replace of content layouts that have become outdated. To use, set your before and after HTML blocks in the two variables below.

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

// Set to the HTML block you want to match. Use "%%" in any place where content may vary and should be preserved through the find and replace.

$html_before='<div class="content_layout section__block-text mceNonEditable" data-type="-two-columns" data-type-name="Two Columns, equal width">
	<h2 class="h1 mceEditable">%%</h2>
	<div class="section__cols row">
		<div class="section__col col mceEditable">
			%%
		</div>
		<div class="section__col col mceEditable">
			%%
		</div>
	</div>
</div>';

// Set to the HTML block you want to replace the above with. If you used "%%" in the above, there must be a corresponding one here, and in the same order.

$html_after='<div class="content_layout section__block-text mceNonEditable" data-type="two-columns" data-type-name="Two Columns, equal width">
	<h2 class="h1 mceEditable">%%</h2>
	<div class="section__cols row">
		<div class="section__col col-sm-6 mceEditable">
			%%
		</div>
		<div class="section__col col-sm-6 mceEditable">
			%%
		</div>
	</div>
</div>';

$html_before=preg_quote(trim($html_before), '~'); // convert before block to a regex
$html_before=preg_replace(['~\s+~', '~%%~'], ['\s+?', '(.*?)'], $html_before);

$html_after=trim($html_after); 
$count=0;
while (strpos($html_after, '%%')!==false) { // handle wildcards via the %% placeholders
	$count++;
	$html_after=preg_replace('~%%~', '\\\\'.$count, $html_after, 1);
};

/*
Can't regexp for matches within MySQL itself due to "Timeout exceeded in regular expression match" even with "SET GLOBAL regexp_time_limit=1024;"
i.e. REGEXP_LIKE(elements, '.$_LW->escape(str_replace(['\\s+?', '.*?'], ['[[:space:]]+', '.*'], $html_before)).', "inm")
*/

$_LW->d_ftp->load($_LW->CONFIG['FTP_MODE']); // connect to SFTP
if ($_LW->d_ftp->connect()) {
	foreach($_LW->dbo->query('select', 'path, elements', 'livewhale_pages', 'elements LIKE "%content_layout%"')->run() as $res2) { // find all pages with "content_layout"
		if (preg_match('~'.$html_before.'~s', $res2['elements'])) { // for each page containing the before block
			echo '<strong>'.$res2['path'].'</strong><br/><br/>'; // denote the page
			if (!empty($_LW->_GET['preview']) || !empty($_LW->_GET['run'])) { // if previewing or running the find/replace
				if ($content_before=@file_get_contents($_LW->WWW_DIR_PATH.$res2['path'])) { // get the before page content
					$content_after=preg_replace('~'.$html_before.'~s', $html_after, $content_before); // do the find/replace
					if ($content_after!=$content_before) { // if something was changed
						if (!empty($_LW->_GET['preview'])) { // if previewing, show the before/after page source
							echo 'BEFORE: <pre>'.htmlentities($content_before).'</pre><br/><br/>';
							echo 'AFTER: <pre>'.htmlentities($content_after).'</pre><br/><br/>';
						};
						if (!empty($_LW->_GET['run'])) { // if running the find/replace
							if ($tmp=$_LW->d_ftp->save_upload_file($content_after)) { // save the updated page
								$_LW->d_ftp->upload_file($tmp, $_LW->CONFIG['FTP_PUB'].$res2['path'], true); // and swap it into place
								@unlink($tmp);
							};
						};
					};
				};
			};
		};
	};
	$_LW->d_ftp->disconnect();
};

?>