<?php

/*

This module generates navigation for handbook pages, as well as adds the corresponding anchor links to headers in the main content area.

*/

$_LW->REGISTERED_APPS['handbook']=[ // configure this module
	'title'=>'Handbook',
	'handlers'=>['onBeforeOutput'],
	'custom'=>[
		'element_id'=>'main-content-area', // ID of the element in which to consider anchors
		//'handbook_tid'=>2659 // ID of the handbook template for restricting by template usage
		'handbook_path'=>'/_ingredients/templates/pages/global/handbook.php' // path to the handbook template
	]
];

class LiveWhaleApplicationHandbook {

protected function getHandbookTemplateID() { // gets the ID of the handbook template
global $_LW;
static $tid;
if (!empty($tid)) {
	return $tid;
};
if (!empty($_LW->REGISTERED_APPS['handbook']['custom']['handbook_tid'])) {
	$tid=$_LW->REGISTERED_APPS['handbook']['custom']['handbook_tid'];
	return $tid;
};
if (!empty($_LW->REGISTERED_APPS['handbook']['custom']['handbook_path'])) {
	if ($page_id=$_LW->dbo->query('select', 'id', 'livewhale_pages', 'host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND path='.$_LW->escape($_LW->REGISTERED_APPS['handbook']['custom']['handbook_path']))->firstRow('id')->run()) {
		$tid=$page_id;
		return $tid;
	};
};
return false;
}

public function onBeforeOutput($buffer) { // on output (before XPHP)
global $_LW;
if (strpos($buffer, 'table_of_contents')!==false && preg_match('~<xphp[^>]+?var="table_of_contents"~', $buffer)) { // if the table_of_contents var is present
	if ($pages=$this->getPages()) { // if handbook pages obtained
		$data=$this->getAnchorLinks($_LW->WWW_DIR_PATH.$_SERVER['PHP_SELF'], true); // get anchors for the current page
		if (!empty($data['buffer'])) { // and apply them to the output
			$buffer=$data['buffer'];
		};
		$GLOBALS['table_of_contents']=$this->getNav($pages); // export the nav to the template
		if (!empty($_LW->_GET['debug_handbook_pages'])) { // support debugger
			$GLOBALS['table_of_contents']='<pre>Handbook debug: '.var_export($pages, true).'</pre>';
		};
	}
	else if (!empty($_LW->_GET['debug_handbook_pages'])) { // support debugger
		if ($tid=$_LW->getHandbookTemplateID()) {
			$GLOBALS['table_of_contents']='<pre>Handbook error: No pages'.($_LW->dbo->query('select', 'livewhale_pages_navs_items.pid, livewhale_pages_navs_items.depth, livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.path='.$_LW->escape($_SERVER['PHP_SELF']).' AND livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']))->exists()->run() ? ' (found in nav)' : ' (not found in nav)').($_LW->dbo->query('select', '1', 'livewhale_pages', 'host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND path='.$_LW->escape($_SERVER['PHP_SELF']).' AND tid='.(int)$tid)->exists()->run() ? ' (uses correct template)' : ' (does not use correct template)').'</pre>';
		};
	};
};
return $buffer;
}

protected function getPages() { // gets all the pages in the currently-viewed handbook
global $_LW;
$pages=[];
if ($tid=$this->getHandbookTemplateID()) {
	// If page is tagged "Single Page Handbook", don't traverse page
	$single_page_handbook=($_LW->dbo->query('select', '1', 'livewhale_pages', 'livewhale_pages.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages.path='.$_LW->escape($_SERVER['PHP_SELF']))
	->innerJoin('livewhale_tags2any', 'livewhale_tags2any.id2=livewhale_pages.id AND livewhale_tags2any.type="pages"')
	->innerJoin('livewhale_tags', 'livewhale_tags.title="Single Page Handbook" AND livewhale_tags.id=livewhale_tags2any.id1')->exists()->run() ? true : false);
	if (!empty($single_page_handbook)) {
		$pages[]=$_SERVER['PHP_SELF']; // use only the current page
		return $pages;
	};
	// Otherwise, traverse nav to find pages
	if ($res2=$_LW->dbo->query('select', 'livewhale_pages_navs_items.pid, livewhale_pages_navs_items.depth, livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.path='.$_LW->escape($_SERVER['PHP_SELF']).' AND livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']))
	->innerJoin('livewhale_pages_navs', 'livewhale_pages_navs.id=livewhale_pages_navs_items.pid')
	->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_navs_items.host AND livewhale_pages.path=livewhale_pages_navs_items.path AND livewhale_pages.gid=livewhale_pages_navs.gid AND livewhale_pages.tid='.(int)$tid)
	->firstRow()->run()) { // select handbook page from a navigation
		$pages[$res2['position']]=$res2['path']; // add the current page
		$position=(int)$res2['position'];
		$nav=$res2['pid'];
		// add preceding pages at the same depth that use the handbook template
		if ($position>0) {
			for ($i=$position-1;$i>=0;$i--) {
				if ($res3=$_LW->dbo->query('select', 'livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.pid='.$nav.' AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages_navs_items.path IS NOT NULL AND livewhale_pages_navs_items.position='.$i)
				->innerJoin('livewhale_pages_navs', 'livewhale_pages_navs.id=livewhale_pages_navs_items.pid')
				->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_navs_items.host AND livewhale_pages.path=livewhale_pages_navs_items.path AND livewhale_pages.gid=livewhale_pages_navs.gid AND livewhale_pages.tid='.(int)$tid)
				->firstRow()->run()) {
					$pages[$res3['position']]=$res3['path'];
					if (!empty($_LW->_GET['test'])) $_LW->logDebug('Adding '.$res3['path'].' ('.$i.')');
				}
				else {
					break;
				};
			};
		};
		// add subsequent pages at the same depth that use the handbook template
		for ($i=$position+1,$max=$position+30;$i<=$max;$i++) {
			if ($res3=$_LW->dbo->query('select', 'livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.pid='.$nav.' AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages_navs_items.path IS NOT NULL AND livewhale_pages_navs_items.position='.$i)
			->innerJoin('livewhale_pages_navs', 'livewhale_pages_navs.id=livewhale_pages_navs_items.pid')
			->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_navs_items.host AND livewhale_pages.path=livewhale_pages_navs_items.path AND livewhale_pages.gid=livewhale_pages_navs.gid AND livewhale_pages.tid='.(int)$tid)
			->firstRow()->run()) {
				$pages[$res3['position']]=$res3['path'];
			}
			else {
				break;
			};
		};
		// add any children that use the handbook template in case this is a parent
		for ($i=$position+1,$max=$position+30;$i<=$max;$i++) {
			if ($res3=$_LW->dbo->query('select', 'livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.pid='.$nav.' AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages_navs_items.depth='.$_LW->escape($res2['depth']+1).' AND livewhale_pages_navs_items.path IS NOT NULL AND livewhale_pages_navs_items.position='.$i)
			->innerJoin('livewhale_pages_navs', 'livewhale_pages_navs.id=livewhale_pages_navs_items.pid')
			->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_navs_items.host AND livewhale_pages.path=livewhale_pages_navs_items.path AND livewhale_pages.gid=livewhale_pages_navs.gid AND livewhale_pages.tid='.(int)$tid)
			->firstRow()->run()) {
				$pages[$res3['position']]=$res3['path'];
			}
			else {
				break;
			};
		};
		// add parent if there may be one that uses the handbook template
		if ($res2['depth']!=0) {
			if ($res3=$_LW->dbo->query('select', 'livewhale_pages_navs_items.position, livewhale_pages_navs_items.path', 'livewhale_pages_navs_items', 'livewhale_pages_navs_items.pid='.(int)$res2['pid'].' AND livewhale_pages_navs_items.status=1 AND livewhale_pages_navs_items.pid='.$nav.' AND livewhale_pages_navs_items.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages_navs_items.depth='.$_LW->escape($res2['depth']-1).' AND livewhale_pages_navs_items.position<'.(int)$res2['position'])
			->innerJoin('livewhale_pages_navs', 'livewhale_pages_navs.id=livewhale_pages_navs_items.pid')
			->innerJoin('livewhale_pages', 'livewhale_pages.host=livewhale_pages_navs_items.host AND livewhale_pages.path=livewhale_pages_navs_items.path AND livewhale_pages.gid=livewhale_pages_navs.gid AND livewhale_pages.tid='.(int)$tid)
			->orderBy('livewhale_pages_navs_items.position DESC')
			->firstRow()
			->run()) {
				$pages[$res3['position']]=$res3['path'];
			};
		};
	}
	else { // else if the page is not found in any nav
		if ($_LW->dbo->query('select', '1', 'livewhale_pages', 'host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND path='.$_LW->escape($_SERVER['PHP_SELF']).' AND livewhale_pages.tid='.(int)$tid)
		->exists()
		->run()) {
			$pages[]=$_SERVER['PHP_SELF'];
		};
	};
	if (!empty($pages)) { // sort the handbook pages
		ksort($pages);
	};
};
return $pages;
}

protected function getAnchorLinks($path, $get_buffer=false) { // gets the anchor links for a page and applies them to the page contents
global $_LW;
static $map;
if (!isset($map)) {
	$map=[];
};
if (isset($map[$path])) {
	return $map[$path];
};
$find=[];
$replace=[];
$anchors=[];
$buffer='';
$len=strlen($_LW->WWW_DIR_PATH);
if ($buffer=@file_get_contents($path)) { // if file contents obtained
	if ($xml=$_LW->getDOMFromPageSource($buffer)) {
		$nodes=$xml->elements(($_LW->REGISTERED_APPS['handbook']['custom']['element_id'][0]!='#' ? '#' : '').$_LW->REGISTERED_APPS['handbook']['custom']['element_id']); // get content within element_id only
		if (isset($nodes[0])) {
			$buffer2=$nodes[0]->toXHTML();
			$use_h4=($_LW->dbo->query('select', '1', 'livewhale_pages', 'livewhale_pages.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages.path='.$_LW->escape($_SERVER['PHP_SELF']))->innerJoin('livewhale_tags2any', 'livewhale_tags2any.id2=livewhale_pages.id AND livewhale_tags2any.type="pages"')->innerJoin('livewhale_tags', 'livewhale_tags.title="Include H4" AND livewhale_tags.id=livewhale_tags2any.id1')->exists()->run() ? true : false);			
			$matches=[];
			preg_match_all('~<h([23'.(!empty($use_h4) ? '4' : '').'])[^>]*?>((?:(?!</h\\1>).)+?)</h\\1>~s', $buffer2, $matches); // get all headers
			if (!empty($matches[1])) {
				foreach($matches[1] as $key=>$val) {
					if (strpos($matches[0][$key], ' data-title')!==false) { // if a data-title was used
						$matches2=[];
						preg_match('~ data-title="([^"]+?)"~', $matches[0][$key], $matches2);
						if (!empty($matches2[1])) {
							$matches[2][$key]=$matches2[1]; // use the alternate short title
						};
					};
					$anchor=[ // create anchor entries
						'header'=>(int)$val,
						'title'=>strip_tags(trim($matches[2][$key])),
						'path'=>substr($path, $len)
					];
					if (substr($anchor['path'], -10, 10)==='/index.php') {
						$anchor['path']=substr($anchor['path'], 0, -9);
					};
					$matches2=[];
					preg_match('~<a[^>]+?id="([^"]+?)"~', $matches[0][$key], $matches2);
					if (!empty($matches2[1])) { // if there is already an anchor
						$anchor['id']=$matches2[1]; // get the ID
					}
					else { // else if there is no anchor yet
						$anchor['id']=preg_replace(['~[\s_]~', '~[^a-z0-9\-]~', '~[\-]{2,}~', '-amp-'], ['-', '', '-', '-'], strtolower($anchor['title'])); // set the ID
						if (!empty($get_buffer)) { // if getting page buffer
							$find[]=$matches[0][$key]; // add each anchor to the page contents
							$replace[]=substr($matches[0][$key], 0, -5).'<a id="'.$anchor['id'].'"></a>'.substr($matches[0][$key], -5, 5);
						};
					};
					if (!isset($anchors[$anchor['id']])) { // require unique anchors
						$anchors[$anchor['id']]=$anchor;
					};
				};
			};
			if (!empty($find)) { // swap all anchors into page contents
				foreach($find as $key=>$val) {
					$find[$key]=preg_replace('~(<h[23][^>]*?>)\s*(.+?)\s*(</h[23]>)~s', '\\1\\2\\3', $val);
				};
				$buffer=preg_replace('~(<h[23][^>]*?>)\s*(.+?)\s*(</h[23]>)~s', '\\1\\2\\3', $buffer);
				$buffer=str_replace($find, $replace, $buffer);
				if (!empty($_LW->_GET['debug_handbook'])) { // support debugger
					$buffer='<pre>'.htmlentities(var_export([$find, $replace], true)).'</pre>';
				};
			};
		};
	}
	else if (!empty($_LW->_GET['debug_handbook'])) { // support debugger
		$buffer='(Unable to parse page for anchors)';
	};
};
$map[$path]=['anchors'=>$anchors, 'buffer'=>(!empty($get_buffer) ? $buffer : '')];
return $map[$path]; // return both anchor entries and page contents
}

protected function getNav($pages) { // gets the nav for the current handbook
global $_LW;
$will_recache=false; // default to cached response
$use_h4=($_LW->dbo->query('select', '1', 'livewhale_pages', 'livewhale_pages.host='.$_LW->escape($_LW->CONFIG['HTTP_HOST']).' AND livewhale_pages.path='.$_LW->escape($_SERVER['PHP_SELF']))->innerJoin('livewhale_tags2any', 'livewhale_tags2any.id2=livewhale_pages.id AND livewhale_tags2any.type="pages"')->innerJoin('livewhale_tags', 'livewhale_tags.title="Include H4" AND livewhale_tags.id=livewhale_tags2any.id1')->exists()->run() ? true : false);
$hash='handbook_'.hash('md5', serialize($pages).'_'.(!empty($use_h4) ? 'h4' : 'noh4'));
$mtime=(int)$_LW->getVariableMTime($hash);
foreach($pages as $path) { // for each page
	if ($mtime2=@filemtime($_LW->WWW_DIR_PATH.$path)) {
		if ($mtime2>$mtime) { // recache only if a page is modified since last cache
			$will_recache=true;
			break;
		};
	};
};
if (!empty($_LW->_GET['recache_handbook'])) {
	$will_recache=true;
};
if (empty($will_recache)) { // return cached response until any of the pages become modified
	if ($output=$_LW->getVariable($hash)) {
		return $output;
	};
};
$output='<div class="table_of_contents">'."\n\t".'<h2>Table of Contents</h2>'."\n\t".'<ul class="table_of_contents link-list">';
foreach($pages as $path) { // for each page
	$data=$this->getAnchorLinks($_LW->WWW_DIR_PATH.$path, false); // get anchors
	if (!empty($data['anchors'])) {
		foreach($data['anchors'] as $anchor) { // and build nav
			$output.="\n\t\t".'<li class="anchor-h'.$anchor['header'].'"><a href="'.$anchor['path'].'#'.$anchor['id'].'">'.$anchor['title'].'</a></li>';
		};
	};
};
$output.="\n\t".'</ul>'."\n".'</div>';
$_LW->setVariable($hash, $output, 3600); // cache response
return $output;
}

}

?>