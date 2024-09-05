
<?php

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

function doFindReplace($mode) {
global $_LW;
$output='';
if (!empty($_POST['find']) && in_array($mode, ['preview', 'run'])) {
	$output.='<hr/>';
	$html_before=$_POST['find'];
	$html_after=$_POST['replace'];
	$debug_page=$_POST['debug_page'];
	$html_before=str_replace('\#\#', '##', preg_quote(trim($html_before), '~'));
	$html_before=preg_replace(['~\s+~', '~%%~', '~##~'], ['\s*?', '(.*?)', '([^\n\r]*?)'], $html_before);
	$html_after=trim($html_after);
	$count=0;
	while (strpos($html_after, '%%')!==false || strpos($html_after, '##')!==false) {
		$count++;
		$html_after=preg_replace('~(?:%%|##)~', '\\\\'.$count, $html_after, 1);
	};
	$_LW->d_ftp->load($_LW->CONFIG['FTP_MODE']);
	if ($_LW->d_ftp->connect()) {
		$total_crawled=0;
		$total_matched=0;
		$total_updated=0;
		if (!empty($debug_page)) {
			$output.='<p><strong>Searching with pattern</strong> ~'.htmlentities($html_before).'~s</p>';
		};
		foreach($_LW->dbo->query('select', 'path, elements', 'livewhale_pages', (!empty($debug_page) ? (strpos($debug_page, '.php')!==false ? 'path='.$_LW->escape($debug_page) : 'path LIKE '.$_LW->escape($debug_page.'%')) : ''))->run() as $res2) {
			$total_crawled++;
			if (preg_match('~'.$html_before.'~s', $res2['elements'])) {
				$total_matched++;
				if ($content_before=@file_get_contents($_LW->WWW_DIR_PATH.$res2['path'])) {
					if ($mode=='preview') {
						$output.='<strong>Previewing <a href="'.$res2['path'].'" target="new">'.$res2['path'].'</a></strong><br/><br/>';
						$matches=[];
						preg_match_all('~'.$html_before.'~s', $content_before, $matches);
						if (!empty($matches[0])) {
							foreach($matches[0] as $segment_before) {
								$segment_after=preg_replace('~'.$html_before.'~s', $html_after, $segment_before);
								$pos=strpos($content_before, $segment_before);
								$pre=substr($content_before, $pos-100, 100);
								$post=substr($content_before, $pos+strlen($segment_before), 100);
								$output.='<strong>BEFORE:</strong> <pre>'.str_replace("\t", '&#160;&#160;&#160;', '<em style="opacity:40%;">'.htmlentities($pre).'</em>'.htmlentities($segment_before).'<em style="opacity:40%;">'.htmlentities($post).'</em>').'</pre><br/>';
								$output.='<strong>AFTER:</strong> <pre>'.str_replace("\t", '&#160;&#160;&#160;', '<em style="opacity:40%;">'.htmlentities($pre).'</em>'.htmlentities($segment_after).'<em style="opacity:40%;">'.htmlentities($post).'</em>').'</pre><br/>';
							};
							$output.='<br/>';
						};
					}
					else if ($mode=='run') {
						$content_after=preg_replace('~'.$html_before.'~s', $html_after, $content_before);
						if ($content_after!=$content_before) {
							$output.='<strong>Updated <a href="'.$res2['path'].'" target="new">'.$res2['path'].'</a></strong><br/><br/>';
							$total_updated++;
							if ($tmp=$_LW->d_ftp->save_upload_file($content_after)) {
								$_LW->d_ftp->upload_file($tmp, $_LW->CONFIG['FTP_PUB'].$res2['path'], true);
								@unlink($tmp);
								$_LW->execute('pages_detect_revision', ['path'=>$res2['path']], 'sync', 'public');
							};
						};
					};
				};
			};
		};
		$output='<hr><p><strong>'.$total_crawled.' crawled, '.$total_matched.' matched, '.$total_updated.' updated</strong></p>'.$output;
		$_LW->d_ftp->disconnect();
	};
};
return $output;
}

?>
<h3>Find and Replace HTML</h3>
<form method="post" id="find-replace-form" style="margin-bottom: 2em;">
	
<?php
echo '<div class="row" style="margin-bottom: 1em;">
<div class="col-sm-6">
			<h4>Find code:</h4>
			<textarea name="find" class="form-control" style="height: 200px;">'.(!empty($_POST['find']) ? $_LW->indentMarkup($_POST['find']) : '').'</textarea>
		</div>
		<div class="col-sm-6">
			<h4>Replace with:</h4>
			<textarea name="replace" class="form-control" style="height: 200px;">'.(!empty($_POST['replace']) ? $_LW->indentMarkup($_POST['replace']) : '').'</textarea>
		</div>
	</div>

';

?>

<div>Run only on this (directory or page): 
		
	
	
	<xphp content="true">
		 <if var="debug_page" type="post"/>
		 <content>
			 <input type="text" class="form-control" name="debug_page" placeholder="/test/page/index.php" value="%%xphp_debug_page|POST%%"/>
		 </content>
		 <else content="true">
			 <content>
			 <input type="text" class="form-control" name="debug_page" value=""/>
			 </content>
		 </else>
	 </xphp>
		
	
	<p><em>Test page: /help/content-layouts/legacy-after/</em></p>
	<p><strong>Wildcards:</strong> <code>%%</code> (multiple lines), <code>##</code> (same line)</p>

<input type="hidden" name="mode" id="submit-mode"/>
<input type="hidden" id="submit-preview" name="preview" value="1"/>
<input type="button" class="btn btn-primary" value="Preview" onClick="submitPreview();"/>
<input type="hidden" name="run" id="submit-run" value="1"/>
<input type="button" class="btn btn-success" value="Run" onClick="submitRun();"/>
</div>

<?php 
if (!empty($_LW->_POST['mode']) && $_LW->_POST['mode']=='preview') {
	echo doFindReplace('preview');
}
if (!empty($_LW->_POST['mode']) && $_LW->_POST['mode']=='run') {
	echo doFindReplace('run');
};
echo '</div>';
?>
</form>
<script>
function submitPreview() {
$('#submit-mode').val('preview');
$('#find-replace-form').submit();
}
function submitRun() {
var approved=confirm('Are you sure you want to run this change? Be sure to preview the changes first, it will update pages across the site and cannot be undone.');
if (approved) {
	$('#submit-mode').val('run');
	$('#find-replace-form').submit();	
};
}
</script>