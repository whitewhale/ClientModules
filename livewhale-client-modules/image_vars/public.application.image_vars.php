<?php

/* Adds support for custom image_2, image_3, etc. args to access non-cover images in list widgets as well as details_image_1, etc. in details templates. */

$_LW->REGISTERED_APPS['image_vars']=[
	'title'=>'Image Vars',
	'handlers'=>['onWidgetFormat', 'onAfterWidgetDetails'],
]; // configure this module

class LiveWhaleApplicationImageVars {

public function onWidgetFormat($module, $handler, $vars) { // post-processes widget vars
global $_LW;
if (!empty($_LW->widget['format']) && strpos($_LW->widget['format'], 'image_')!==false) { // if there may be image vars
	$matches=[];
	preg_match_all('~{image_([0-9]+)(?:_src)*}~', $_LW->widget['format'], $matches);
	if (!empty($matches[1])) {
		if ($table=$_LW->getTableForDataType($module)) {
			foreach($_LW->dbo->query('select', 'CONCAT(livewhale_images.gid,"<,>",livewhale_images.filename,"<,>",livewhale_images.extension,"<,>",IF(livewhale_images2any.thumb_crop IS NULL,"",livewhale_images2any.thumb_crop),"<,>",IF(livewhale_images2any.thumb_src_region IS NULL,"",livewhale_images2any.thumb_src_region),"<,>",IF(livewhale_images2any.caption IS NULL,"",livewhale_images2any.caption),"<,>",IF(livewhale_images.credit IS NULL,"",livewhale_images.credit),"<,>",IF(livewhale_images2any.is_decoration IS NULL,"",livewhale_images2any.is_decoration)) AS image', $table, $table.'.id='.(int)$vars['id'], 'livewhale_images2any.position ASC')
			->leftJoin('livewhale_images2any', 'livewhale_images2any.id2=IF('.$table.'.gallery_id IS NULL,'.$table.'.id,'.$table.'.gallery_id) AND livewhale_images2any.type=IF('.$table.'.gallery_id IS NULL,'.$_LW->escape($module).',"galleries")')
			->leftJoin('livewhale_images', 'livewhale_images.id=livewhale_images2any.id1')
			->run() as $image_key=>$image_data) { // for each attached image
				if (in_array($image_key, $matches[1]) && !empty($image_data['image'])) { // if it was used in the format arg and there is data
					$image_data=explode('<,>', $image_data['image']); // convert image data to array
					$image_caption=$image_data[5];
					$image_is_decoration=$image_data[7];
					$image_src=$_LW->getImage($image_data[0], $image_data[1], $image_data[2], $_LW->widget['args']['thumb_width'], $_LW->widget['args']['thumb_height'], ((isset($_LW->widget['args']['thumb_crop']) && (string)$_LW->widget['args']['thumb_crop']=='false') ? false : ((!empty($image_data[3]) && !empty($_LW->widget['args']['thumb_width']) && !empty($_LW->widget['args']['thumb_height'])) ? $image_data[3] : false)), $image_data[4], false, false, ((isset($_LW->widget['args']['ignore_cropper']) && $_LW->widget['args']['ignore_cropper']=='true') ? true : false));
					$vars['image_'.$image_key]='<img src="'.$image_src.'" alt="'.(empty($image_is_decoration) ? $_LW->setFormatAltText($image_caption) : '').'" class="lw_image"'.(!empty($_LW->widget['args']['thumb_width']) ? ' width="'.$_LW->widget['args']['thumb_width'].'"' : '').(!empty($_LW->widget['args']['thumb_height']) ? ' height="'.$_LW->widget['args']['thumb_height'].'"' : '').'/>'; // create image tag
					$vars['image_'.$image_key.'_src']=$image_src; // create image src
				};
			};
		};
	};
};
return $vars;
}

public function onAfterWidgetDetails() { // on details page load
global $_LW;
if (!empty($GLOBALS['details_image'])) { // if there is an image slideshow
	$matches=[];
	preg_match_all('~<li[^>]*?>(.+?)</li>~s', $GLOBALS['details_image'], $matches); // fetch all entries
	if (!empty($matches[1])) {
		$GLOBALS['details_image_all']='';
		foreach($matches[1] as $key=>$val) {
			$GLOBALS['details_image_'.($key+1)]=trim(preg_replace('~<div class="lw_[a-z\-_]+?_caption">.*?</div>~s', '', $val)); // and add a corresponding global var (sans caption)
			$GLOBALS['details_image_all'].=$GLOBALS['details_image_'.($key+1)];
		};
	};
};
}

}

?>