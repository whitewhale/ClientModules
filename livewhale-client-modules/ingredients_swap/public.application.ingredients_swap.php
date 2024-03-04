<?php

/*

How to use:

- Add the staging /_ingredients to your instance as /_ingredients.new
- Visit your site with ?lw_preview_ingredients=1 to enable a preview of the swap
- If preview looks good, visit /livewhale/?lw_launch_ingredients=1 to take your staged ingredients live (the old version will become ingredients.old)

*/

$_LW->REGISTERED_APPS['ingredients_swap']=[
	'title'=>'Ingredients Swap',
	'handlers'=>['onOutput']
]; // configure this module

class LiveWhaleApplicationIngredientsSwap {

public function onOutput($buffer) { // after XPHP output
global $_LW;
if (!empty($_LW->is_html) && is_dir($_LW->WWW_DIR_PATH.'/_ingredients.new')) { // if ingredients are staged
	if (!empty($_LW->_GET['lw_preview_ingredients'])) { // if previewing ingredients
		$buffer=str_replace([ // swap in preview
			'/resource/css/_ingredients/',
			'/resource/css/_i/',
			'/resource/css/%5C_ingredients%',
			'/resource/css/%5C_i%',
			'/resource/css/%5C_i/',
			'/resource/js/_ingredients/',
			'/resource/js/_i/',
			'/resource/js/%5C_ingredients%',
			'/resource/js/%5C_i%',
			'/resource/js/%_i/'
		],
		[
			'/resource/css/_ingredients.new/',
			'/resource/css/_ingredients.new/',
			'/resource/css/%5C_ingredients.new%',
			'/resource/css/%5C_ingredients.new%',
			'/resource/css/%5C_ingredients.new/',
			'/resource/js/_ingredients.new/',
			'/resource/js/_ingredients.new/',
			'/resource/js/%5C_ingredients.new%',
			'/resource/js/%5C_ingredients.new%',
			'/resource/js/%5C_ingredients.new/'
		], $buffer);
	}
	else if (!empty($_LW->_GET['lw_launch_ingredients'])) { // else if launching ingredients
		$was_launched=false; // default to not launched
		$_LW->d_ftp->load($_LW->CONFIG['FTP_MODE']);
		if ($_LW->d_ftp->connect()) { // if connected
			if ($_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].'/_ingredients') && $_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].'/_ingredients.new')) { // if dirs exist
				$_LW->d_ftp->rename($_LW->CONFIG['FTP_PUB'].'/_ingredients', $_LW->CONFIG['FTP_PUB'].'/_ingredients.old'); // move out old dir
				if ($_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].'/_ingredients.old')) { // if successful
					$_LW->d_ftp->rename($_LW->CONFIG['FTP_PUB'].'/_ingredients.new', $_LW->CONFIG['FTP_PUB'].'/_ingredients'); // move in new dir
					if ($_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].'/_ingredients')) { // if successful
						$was_launched=true; // mark as successful
					};
				};
			};
			$_LW->d_ftp->disconnect(); // disconnect
		};
		return '<html><body>Ingredients '.(!empty($was_launched) ? 'have been' : 'have NOT been').' launched. <a href="'.str_replace('lw_launch_ingredients=1', '', $_SERVER['REQUEST_URI']).'">Refresh page</a></body></html>'; // give status
	};
};
return $buffer;
}

}

?>