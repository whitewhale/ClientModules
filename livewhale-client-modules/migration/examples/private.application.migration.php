<?php

$_LW->REGISTERED_APPS['migration']=array(
	'title'=>'Migration',
	'handlers'=>array('onValidateStage', 'onValidateMigrate', 'onBeforeMigrate', 'onAfterMigrate', 'onBeforeMigrateDOM', 'onAfterMigrateDOM', 'onMigrationPath')
);

class LiveWhaleApplicationMigration {

public function onValidateStage($from_host, $path) { // approves/denies this file for staging, if denied, page will not be copied to the new site
global $_LW;
return true; // return true/false depending on whether the file at $path should be staged
}

public function onValidateMigrate($from_host, $path, $source) { // approves/denies this file for migration, if denied, page will be left unaltered when brought live
global $_LW;
return true; // return true/false depending on whether the file at $path with HTML source $source should be migrated
}

public function onBeforeMigrate($from_host, $path, $source) { // pre-processes page content before migration (typically additional changes to ensure it cleanly parses as XML)
global $_LW;
return $source; // make any changes and then return the updated source
}

public function onAfterMigrate($from_host, $path, $source) { // post-processes page content after migration (last minute changes before final migrated page is saved)
global $_LW;
return $source; // make any changes and then return the updated source
}

public function onBeforeMigrateDOM($from_host, $path, $xml) { // pre-processes page content before migration, as DOM (like onBeforeMigrate but as a DOM object)
global $_LW;
return $xml; // make any changes and then return the updated XML
}

public function onAfterMigrateDOM($from_host, $path, $xml) { // post-processes page content after migration, as DOM (like onAfterMigrate but as a DOM object)
global $_LW;
return $xml; // make any changes and then return the updated XML
}

public function onMigrationPath($to_host, $path) { // post-processes the new live path for all content (includes any LIVE_DIR prefixes)
global $_LW;
return $path; // make any changes and then return the updated path
}

}

?>