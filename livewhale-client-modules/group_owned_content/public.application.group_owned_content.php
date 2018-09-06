<?php

$_LW->REGISTERED_APPS['group_owned_content']=array(
	'title'=>'Group Owned Content',
	'handlers'=>array('onLoad')
); // configure this module

class LiveWhaleApplicationGroupOwnedContent {

public function onLoad() {
global $_LW, $LIVE_URL;
if (!empty($LIVE_URL['IS_DETAILS_REQUEST']) && !empty($LIVE_URL['DETAILS_TYPE']) && !empty($LIVE_URL['DETAILS_ID'])) { // if this is a valid details request
	if ($_LW->hasFlag('data_type', $LIVE_URL['DETAILS_TYPE'], 'is_group_owned')) { // for a group-owned item
		if ($table=$_LW->getTableForDataType($LIVE_URL['DETAILS_TYPE'])) {
			if ($directory=$_LW->dbo->query('select', 'livewhale_groups.directory', 'livewhale_groups', 'livewhale_groups.directory IS NOT NULL')
			->innerJoin($table, $table.'.id='.(int)$LIVE_URL['DETAILS_ID'].' AND '.$table.'.gid=livewhale_groups.id')
			->firstRow('directory')
			->run()) { // get the owner group's directory
				if ($host=@parse_url($directory, PHP_URL_HOST)) {
					if ($host!=$_LW->CONFIG['HTTP_HOST']) { // switch to the group owner's host if not already there
						header('X-Test: http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$host.$LIVE_URL['REQUEST_URI']);
						$_LW->redirectUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$host.$LIVE_URL['REQUEST_URI'], 301);
						exit;
					};
				};
			};
		};
	};
};
}

}

?>