<?php

$_LW->REGISTERED_MODULES['migration']=array(
	'title'=>'Migration',
	'revision'=>1,
	'order'=>0,
	'flags'=>array('is_always_authorized', 'has_js', 'has_css'),
	'handlers'=>array('onLogin', 'onInstall'),
	'requires_permission'=>array('core_admin'),
	'data_types'=>array(
		'migration'=>array(
			'managers'=>array(
				'migration'=>array(
					'handlers'=>array(
						'onManager'=>'onManagerMigration',
						'onManagerSubmit'=>'onManagerSubmitMigration'
					)
				)
			)
		)
	)
); // configure this module

class LiveWhaleDataMigration {

public function onManagerMigration() { // performs actions before the manager loads for this module
global $_LW, $title, $summary;
if (!$_LW->userSetting('core_admin')) { // only allow admins access
	die($_LW->redirectUrl('/livewhale/'));
};
$title='Migration'; // set title
if (!$config=$this->getConfig()) { // warn if config not obtained
	$_LW->REGISTERED_MESSAGES['failure'][]='Could not find a migration config.';
}
else { // else if valid config
	if (!empty($config['HOSTS'])) { // if there are hosts
		$summary='<h3>Configuration</h3>'; // summarize the migration configuration
		foreach($config['HOSTS'] as $key=>$host) { // for each host
			$summary.='<div class="migration_host"><p class="migration_hostname">http://'.$host['HTTP_HOST'].'/</p>';
			if (!$error=$this->isInvalidConfiguration($host)) { // if valid
				$host['LIVE_DEST_DIR']=preg_replace('~[/]{2,}~', '/', $host['LIVE_DOCROOT'].$host['LIVE_DIR']); // set full live destination directory
				$summary.='&bull; <strong>Destination:</strong> content will be migrated to '.$host['LIVE_HOST'].':<br/>'.str_replace('/', '/&#8203;', $host['LIVE_DEST_DIR']).'<br/>'.
				'&bull; <strong>Templates:</strong>'.(!empty($host['TEMPLATES']) ? ' configured' : ' not configurated').'<br/>'.
				'&bull; <strong>Editable elements:</strong>'.(!empty($host['EDITABLE']) ? ' configured' : ' not configurated').'<br/>'.
				'&bull; '.(!empty($host['STAGE_ONLY']) ? '<strong>Only staging:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['STAGE_ONLY'])) : '<strong>Staging all content</strong>').'<br/>'.
				(!empty($host['STAGE_EXCLUDE']) ? '&bull; <strong>Excluding:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['STAGE_EXCLUDE'])).'<br/>' : '').
				(!empty($host['STAGE_BUT_DONT_EXCLUDE']) ? '&bull; <strong>Not excluding:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['STAGE_BUT_DONT_EXCLUDE'])).'<br/>' : '').
				'&bull; '.(!empty($host['CLEAN_ONLY']) ? '<strong>Only cleaning:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['CLEAN_ONLY'])) : '<strong>Cleaning all content</strong>').'<br/>'.
				'&bull; '.(!empty($host['LIVE_ONLY']) ? '<strong>Only taking live:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['LIVE_ONLY'])) : '<strong>Taking all content live</strong>').'<br/>'.
				'&bull; '.(!empty($host['MIGRATE_ONLY']) ? '<strong>Only migrating:</strong> '.str_replace('/', '/&#8203;', implode(', ', $host['MIGRATE_ONLY'])) : '<strong>Migrating all content</strong>').'<br/>';
			}
			else {
				$summary.='&bull; <strong>Invalid configuration for host:</strong> '.$error.'<br/>';
			};
			$summary.='</div>';
		};
	};
};
}

public function isInvalidConfiguration($host) { // verifies a host's configuration
foreach(array('HTTP_HOST', 'FTP_HOST', 'FTP_PORT', 'FTP_USER', 'FTP_PASS', 'FTP_PUB', 'FTP_MODE', 'LIVE_HOST', 'LIVE_INCLUDES_DIR', 'LIVE_DOCROOT', 'LIVE_DIR') as $key) { // check setting value types
	if (!is_scalar($host[$key])) {
		return $key.' must be a string';
	};
};
foreach(array('STAGE_ONLY', 'STAGE_EXCLUDE', 'STAGE_BUT_DONT_EXCLUDE', 'STAGE_NO_EDITING', 'CLEAN_ONLY', 'LIVE_ONLY', 'LIVE_IGNORED_HOSTS', 'MIGRATE_ONLY', 'TEMPLATES', 'EDITABLE') as $key) {
	if (!is_array($host[$key])) {
		return $key.' must be an array';
	};
};
if (!empty($host['LIVE_DOCROOT']) && !empty($host['LIVE_HOST']) && !empty($host['LIVE_INCLUDES_DIR']) && !empty($host['HTTP_HOST']) && !empty($host['FTP_HOST']) && !empty($host['FTP_USER']) && !empty($host['FTP_PASS']) && !empty($host['FTP_PUB']) && !empty($host['FTP_MODE']) && (!empty($host['TEMPLATES']) || !empty($host['EDITABLE']))) { // check required settings
	foreach(array('FTP_PUB', 'LIVE_INCLUDES_DIR', 'LIVE_DOCROOT', 'LIVE_DIR') as $key) { // check path formating
		if (!($key=='LIVE_DIR' && empty($host[$key])) && (empty($host[$key]) || $host[$key][0]!='/')) {
			return $key.' must be an absolute path';
		};
		if (substr($host[$key], -1, 1)=='/') {
			return $key.' must not include a trailing slash';
		};
	};
	if (!empty($host['TEMPLATES'])) { // check template configuration formats
		foreach($host['TEMPLATES'] as $key=>$val) {
			if (!empty($val)) {
				if ($key[0]!='/') {
					return 'Template configuration '.$key.' must be an absolute path';
				};
				if (!is_scalar($val[0])) {
					return 'Template configuration '.$key.' must be a file path';
				};
				if (empty($val[1]) || !is_array($val[1])) {
					return 'Template configuration '.$key.' must have a map array set';
				};
				if ($val[0][0]!='/') {
					return 'Template configuration '.$key.' must specify an absolute template path';
				};
			};
		};
	};
	if (!empty($host['EDITABLE'])) { // check editable element configuration formats
		foreach($host['EDITABLE'] as $key=>$val) {
			if ($key[0]!='/') {
				return 'Editable configuration '.$key.' must be an absolute path';
			};
			if (empty($val[0]) || !is_array($val[0])) {
				return 'Editable configuration '.$key.' must specify element ids as an array';
			};
			if (!is_array($val[1])) {
				return 'Editable configuration '.$key.' must specify optional elements as an array';
			};
		};
	};
}
else {
	return $key.' must be set';
};
}

public function onManagerSubmitMigration() { // called upon manager submission
global $_LW;
if (!$_LW->userSetting('core_admin')) { // only allow admins access
	die($_LW->redirectUrl('/livewhale/'));
};
if ($this->config=$this->getConfig()) { // if config obtained
	if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration') && is_writable($_LW->INCLUDES_DIR_PATH.'/data/migration')) { // check for valid data directory
		if (!empty($_LW->_POST['mode']) && in_array($_LW->_POST['mode'], array('stage', 'clean', 'migrate', 'live'))) { // check for valid migration mode
			if (!empty($this->config['HOSTS'])) { // if there are hosts defined in the config
				ini_set('display_errors', 1); // enable error reporting
				error_reporting(-1);
				set_time_limit(3600); // change time and memory limits
				ini_set('memory_limit', -1);
				$_LW->closeSession(); // close an open session
				$GLOBALS['status']='';
				$time=microtime(true); // get start time
				$this->stats=array('staged'=>0, 'migrated'=>0, 'live'=>0, 'cleaned'=>0, 'excluded'=>0, 'skipped'=>0, 'deleted'=>0);
				foreach($this->config['HOSTS'] as $host) { // loop through valid hosts
					if (!$this->isInvalidConfiguration($host)) {
						$host['LIVE_DEST_DIR']=preg_replace('~[/]{2,}~', '/', $host['LIVE_DOCROOT'].$host['LIVE_DIR']); // set full live destination directory
						$this->host=$host; // set host
						if ($_LW->_POST['mode']=='stage') { // perform mode function on each host
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Beginning staging of '.$host['HTTP_HOST'].'.</strong></div>';
							$this->doStage();
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Staging complete.</strong></div>';
						}
						else if ($_LW->_POST['mode']=='clean') {
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Beginning cleaning of '.$host['HTTP_HOST'].'.</strong></div>';
							$this->doClean();
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Cleaning complete.</strong></div>';
						}
						else if ($_LW->_POST['mode']=='live') {
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Beginning live publishing of '.$host['HTTP_HOST'].'.</strong></div>';
							if (!empty($this->host['LIVE_HOST']) && !empty($this->host['LIVE_DEST_DIR'])) { // if a live host and directory have been specified
								$_LW->d_ftp->load($_LW->CONFIG['FTP_MODE']); // load FTP
								if ($_LW->d_ftp->connect()) { // if FTP connect successful
									if (!$_LW->d_ftp->is_dir($this->host['LIVE_DEST_DIR'])) { // if live directory doesn't exist, attempt to create it
										$_LW->d_ftp->mkdirRecursive($this->host['LIVE_DEST_DIR']);
									};
									if ($_LW->d_ftp->is_dir($this->host['LIVE_DEST_DIR'])) { // if the live directory exists
										foreach($this->host['LIVE_ONLY'] as $key=>$val) { // for each page, add the livewhale. version of it
											if (pathinfo($val, PATHINFO_EXTENSION)=='php' && strpos($val, '/livewhale.')===false) {
												$tmp=explode('/', $val);
												$tmp[sizeof($tmp)-1]='livewhale.'.$tmp[sizeof($tmp)-1];
												$this->host['LIVE_ONLY'][]=implode('/', $tmp);
											};
										};
										if (!empty($this->host['LIVE_ONLY'])) {
											foreach($this->host['LIVE_ONLY'] as $item) {
												$this->doLive($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'. $this->host['HTTP_HOST'].$item);
											};
										}
										else {
											$this->doLive($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST']);
										};
										$_LW->d_ftp->disconnect(); // disconnect from FTP
									}
									else {
										$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>The specified destination directory does not exist and could not be created.</strong> <span class="note">'.$this->host['LIVE_DEST_DIR'].'</span></div>';
									};
								}
								else {
									$_LW->REGISTERED_MESSAGES['failure'][]='Could not connect to '.($this->host['FTP_MODE']=='sftp' ? 'S' : '').'FTP host: '.$this->host['FTP_HOST'].'.';
								};
							}
							else {
								$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>You must specify a LIVE_HOST for host '.$this->host['HTTP_HOST'].'.</strong></div>';
							};
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Publishing complete.</strong></div>';
						}
						else if ($_LW->_POST['mode']=='migrate') {
							$this->whitelist_hosts=array();
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Beginning migration of '.$host['HTTP_HOST'].'.</strong></div>';
							if (!empty($this->host['MIGRATE_ONLY'])) {
								foreach($this->host['MIGRATE_ONLY'] as $item) {
									$this->doMigrate($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'. $this->host['HTTP_HOST'].$item);
								};
							}
							else {
								$this->doMigrate($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST']);
							};
							$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Migration complete.</strong></div>';
							if (!empty($this->whitelist_hosts)) {
								$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>Hosts found for whitelist on '.$host['LIVE_HOST'].'</strong>: '.implode(', ', $this->whitelist_hosts).'</div>';
							};
						};
					};
				};
				if (!empty($GLOBALS['status'])) { // format status
					$GLOBALS['status']='<h3>Status:</h3><div class="migration_status">'.$GLOBALS['status'].'</div>';
				};
				$time=microtime(true)-$time; // get total time
				$time=$time<1 ? number_format($time, 4).' seconds' : ($time<60 ? number_format($time).' seconds' : number_format($time/60, 1).' minutes');
				$_LW->REGISTERED_MESSAGES['success'][]=($_LW->_POST['mode']=='migrate' ? $this->stats['migrated'].' item'.($this->stats['migrated']!=1 ? 's' : '').' migrated' : ($_LW->_POST['mode']=='stage' ? $this->stats['staged'].' item'.($this->stats['migrated']!=1 ? 's' : '').' staged' : ($_LW->_POST['mode']=='live' ? $this->stats['live'].' item'.($this->stats['live']!=1 ? 's' : '').' brought live' : $this->stats['cleaned'].' item'.($this->stats['cleaned']!=1 ? 's' : '').' cleaned'))).', '.$this->stats['excluded'].' item'.($this->stats['excluded']!=1 ? 's' : '').' excluded, '.$this->stats['skipped'].' item'.($this->stats['skipped']!=1 ? 's' : '').' skipped'.($_LW->_POST['mode']=='stage' ? ', '.$this->stats['deleted'].' item'.($this->stats['deleted']!=1 ? 's' : '').' deleted' : '').' in '.$time.'.'; // report stats
			}
			else {
				$_LW->REGISTERED_MESSAGES['failure'][]='No hosts are defined in the migration config.';
			};
		}
		else {
			$_LW->REGISTERED_MESSAGES['failure'][]='You must choose a migration mode.';
		};
	}
	else {
		$_LW->REGISTERED_MESSAGES['failure'][]='The migration dir ('.$_LW->INCLUDES_DIR_PATH.'/data/migration) must exist and be writable by the web server.';
	};
};
}

protected function getConfig() { // get the migration config
global $_LW;
@include $_LW->INCLUDES_DIR_PATH.'/client/migration.config.php'; // attempt to load config
if (isset($config['HOSTS'])) {
	foreach(array_keys($config['HOSTS']) as $host) {
		$config['HOSTS'][$host]['LIVE_IGNORED_HOSTS'][]=$host; // ensure the primary host is ignored
		if (strpos($host, 'www.')===0 && !in_array(substr($host, 4), $config['HOSTS'][$host]['LIVE_IGNORED_HOSTS'])) { // ensure non-www version of a www host is ALSO_THIS_HOST
			$config['HOSTS'][$host]['LIVE_IGNORED_HOSTS'][]=substr($host, 4);
		};
	};
};
return isset($config) ? $config : false;
}

protected function doStage() { // stage this host
global $_LW;
if (!empty($this->host['HTTP_HOST'])) { // if HTTP_HOST given
	if (!empty($this->host['FTP_HOST']) && !empty($this->host['FTP_USER']) && !empty($this->host['FTP_PASS']) && !empty($this->host['FTP_PUB']) && !empty($this->host['FTP_MODE'])) { // if valid FTP configuration
		if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'])) { // ensure host dir exists
			@mkdir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST']);
		};
		if (is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'])) { // if host dir exists
			$_LW->d_ftp->load($this->host['FTP_MODE']); // load FTP
			if ($_LW->d_ftp->connect($this->host['FTP_HOST'], $this->host['FTP_USER'], $this->host['FTP_PASS'])) { // if FTP connect successful
				if (!empty($this->host['STAGE_ONLY'])) { // stage files for the directory
					foreach($this->host['STAGE_ONLY'] as $dir) {
						$this->stageFilesInDirectory($this->host['FTP_PUB'].$dir);
					};
				}
				else {
					$this->stageFilesInDirectory($this->host['FTP_PUB']);
				};
				$_LW->d_ftp->disconnect(); // disconnect from FTP
			}
			else {
				$_LW->REGISTERED_MESSAGES['failure'][]='Could not connect to '.($this->host['FTP_MODE']=='sftp' ? 'S' : '').'FTP host: '.$this->host['FTP_HOST'].'.'.(!empty($_LW->d_ftp->error) ? ' (FTP: '.$_LW->d_ftp->error.')' : '');
			};
		}
		else {
			$_LW->REGISTERED_MESSAGES['failure'][]='Could not create host directory at: '.$_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'];
		};
	}
	else {
		$_LW->REGISTERED_MESSAGES['failure'][]='Invalid FTP configuration for host '.$this->host['HTTP_HOST'].'.';
	};
}
else {
	$_LW->REGISTERED_MESSAGES['failure'][]='HTTP_HOST not specified for a configured host.';
};
}

protected function stageFilesInDirectory($dir) { // stages files for a directory
global $_LW;
@touch($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].'.staging'); // update last staging time
$_LW->d_ftp->cache=array('existing_dirs'=>array(), 'existing_files'=>array()); // reset FTP cache
$files=array();
if (substr($dir, -1, 1)=='/') {
	$dir=substr($dir, 0, -1);
};
$items=$_LW->d_ftp->scandir($dir); // get items in the dir
if (!empty($items)) { // ensure items returned are basenamed
	foreach($items as $key=>$val) {
		$items[$key]=basename($val);
	};
};
if (!empty($items)) { // strip ignored items from list
	foreach($items as $key=>$val) {
		if (in_array(strtolower($val), array('.', '..', 'livewhale', '_notes', '_mm', '_baks', '.ds_store', 'thumbs.db', '.viminfo', '.bash_history', '.ssh', '.lesshst', '.mysql_history', 'old', 'backup')) || in_array(strtolower(substr($val, -4, 4)), array('.lck', '.bak', '_old')) || strpos($val, 'tmp.')===0 || $val[0]=='#' || substr($val, -7, 7)=='_backup') {
			unset($items[$key]);
		};
	};
};
if (!empty($items)) { // sort items so we fetch them alphabetically
	sort($items);
};
$relative_dir=substr($dir, strlen($this->host['FTP_PUB'])); // get relative dir
$staged_items=is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir) ? scandir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir) : array(); // get previously staged items
foreach($staged_items as $key=>$val) {
	if ($val=='.' || $val=='..') {
		unset($staged_items[$key]);
	};
};
if (!empty($staged_items)) { // if there were previously staged items
	$deleted_items=array_diff($staged_items, (!empty($items) ? $items : array())); // find items that have since been deleted
	foreach($deleted_items as $key=>$val) { // strip items we created
		if (strpos($val, 'livewhale.')===0) {
			unset($deleted_items[$key]);
		};
	};
	if (!empty($deleted_items)) { // if there were deleted items, delete them
		foreach($deleted_items as $item) {
			$this->recursiveDelete($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.'/'.$item);
		};
	};
};
if (empty($relative_dir) || substr($relative_dir, -1, 1)!='/') {
	$relative_dir.='/';
};
if (!empty($items)) { // if items found
	foreach($items as $item) { // for each item
		$is_dir=$_LW->d_ftp->is_dir($dir.'/'.$item); // check if item is a dir
		$will_stage=true; // default to staging
		if (!$is_dir) { // if this isn't a dir
			if (!empty($this->host['STAGE_ONLY'])) { // if staging specific items
				$will_stage=false;
				foreach($this->host['STAGE_ONLY'] as $stage_path) { // approve this item by inclusion
					if (strpos($relative_dir.$item, $stage_path)===0 || strpos($relative_dir.$item.'/', $stage_path)===0) {
						$will_stage=true;
						break;
					};
				};
			}
			else {
				$will_stage=true;
			};
			if ($will_stage && !empty($this->host['STAGE_EXCLUDE'])) { // if excluding specific items
				foreach($this->host['STAGE_EXCLUDE'] as $stage_path) { // approve this item by exclusion
					if (strpos($relative_dir.$item, $stage_path)===0 || strpos($relative_dir.$item.'/', $stage_path)===0) {
						$will_stage=false;
						break;
					};
				};
				if (!$will_stage && !empty($this->host['STAGE_BUT_DONT_EXCLUDE'])) { // approve this item by double exclusion
					foreach($this->host['STAGE_BUT_DONT_EXCLUDE'] as $stage_path) {
						if (strpos($relative_dir.$item, $stage_path)===0 || strpos($relative_dir.$item.'/', $stage_path)===0) {
							$will_stage=true;
							break;
						};
					};
				};
			};
		};
		if ($will_stage) { // if item approved for staging
			if ($is_dir) { // if this item is a directory
				$this->stageFilesInDirectory($dir.'/'.$item); // recurse into directory
			}
			else { // else if approved for staging
				$will_stage=$_LW->callHandlersByType('application', 'onValidateStage', array('host'=>$this->host['HTTP_HOST'], 'path'=>$relative_dir.$item, 'buffer'=>true)); // apply custom staging validation rules
				if ($will_stage) { // if still staging after any custom rules
					if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$item) || filemtime($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$item)<$_LW->d_ftp->filemtime($dir.'/'.$item)) { // else if this is a file that doesn't already exist or file has been changed
						$extension=strtolower(pathinfo($item, PATHINFO_EXTENSION)); // get filename extension
						$error='';
						$_LW->d_ftp->error='';
						$source=$_LW->d_ftp->file_get_contents($dir.'/'.$item); // get contents
						if (!empty($_LW->d_ftp->error)) { // if FTP error
							$source=''; // blank response
							$error=' FTP response was: '.$_LW->d_ftp->error; // give error
						}
						else { // else if no FTP error
							if (in_array($extension, array('php', 'aspx')) && (strpos($source, '</html>')!==false || strpos($source, '<html xmlns')!==false || strpos($source, '<html>')!==false)) { // if page type that requires HTTP fetching, and file appears to be an HTML page
								$http_source=$_LW->getUrl('http://'.$this->host['HTTP_HOST'].$relative_dir.$item);
								if ($_LW->last_code==401) { // if unauthorized response
									$GLOBALS['status'].='<div class="migration_warning">&bull; Failed to fetch authenticated page via HTTP. Falling back on FTP. <span class="note">'.$this->host['FTP_HOST'].$relative_dir.$item.$error.'</span></div>'; // give error
								}
								else if ($_LW->last_code!=200) { // else if invalid response
									$source='';
									$error=' HTTP response was: '.$_LW->last_code.'.';
								}
								else { // else use the HTTP source
									$source=$http_source;
								};
							};
						};
						if (!empty($source)) { // if contents obtained
							if (!empty($source)) { // if contents obtained
								if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir)) { // recursively create parent dirs
									$tmp=$_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'];
									$tmp2=explode('/', $relative_dir);
									while (!empty($tmp2)) {
										$tmp3=array_shift($tmp2);
										if (!empty($tmp3)) {
											$tmp.='/'.$tmp3;
											if (!is_dir($tmp)) {
												@mkdir($tmp, 0775);
											};
										};
									};
								};
								@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$item, $source);
							};
							@file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$item, $source);
						};
						if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$item)) { // if file wasn't copied
							$GLOBALS['status'].='<div class="migration_warning">&bull; Failed to download file <span class="note">'.$this->host['FTP_HOST'].$relative_dir.$item.$error.'</span></div>'; // give error
						}
						else {
							$this->stats['staged']++;
						};
					}
					else {
						$this->stats['skipped']++;
					};
				}
				else {
					$this->stats['skipped']++;
				};
			};
		}
		else {
			$this->stats['excluded']++;
		};
	};
}
else if ($dir==$this->host['FTP_PUB']) { // else give error if FTP root is empty
	$_LW->REGISTERED_MESSAGES['failure'][]='Could not find any files in '.$this->host['FTP_HOST'].$relative_dir.'.';
};
}

protected function recursiveDelete($item) { // recursively deletes items
if (file_exists($item) && $item!='.' && $item!='..') { // if it exists
	if (is_file($item)) { // if it's a file
		unlink($item); // delete the file
		$this->stats['deleted']++;
	}
	else if (is_dir($item)) { // else if it's a directory
		foreach(scandir($item) as $child) { // delete each item in the directory
			if ($child!='.' && $child!='..') {
				$this->recursiveDelete($item.'/'.$child);
			};
		};
		rmdir($item); // delete the directory
		$this->stats['deleted']++;
	};
};
}

protected function doClean() { // cleans any staged files
global $_LW;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'], RecursiveDirectoryIterator::KEY_AS_PATHNAME), RecursiveIteratorIterator::CHILD_FIRST) as $item=>$info) { // recursively loop through items
	$basename=basename($item); // get item basename
	$path=$info->getPath().'/'.$basename; // get item path
	$relative_dir=substr(dirname($path), strlen($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'])); // get relative dir
	if (empty($relative_dir) || substr($relative_dir, -1, 1)!='/') {
		$relative_dir.='/';
	};
	if ($basename!='.' && $basename!='..') { // if valid file
		if (!empty($this->host['CLEAN_ONLY'])) { // if targeting specific items
			$will_clean=false;
			foreach($this->host['CLEAN_ONLY'] as $clean_path) {
				if (strpos($relative_dir.$basename, $clean_path)===0 || strpos($relative_dir.$basename.'/', $clean_path)===0) { // approve this item by inclusion
					$will_clean=true;
					break;
				};
			};
		}
		else {
			$will_clean=true;
		};
		if ($will_clean) { // if approved, remove item
			if (is_dir($path)) {
				@rmdir($path);
			}
			else {
				@unlink($path);
				$this->stats['cleaned']++;
			};
		}
		else {
			$this->stats['excluded']++;
		};
	};
};
}

protected function doLive($item) { // recursively takes items live
if (substr($item, -1, 1)=='/') {
	$item=substr($item, 0, -1);
};
if (is_dir($item)) {
	foreach(scandir($item) as $child) {
		if ($child!='.' && $child!='..') {
			if (is_dir($item.'/'.$child)) {
				$this->doLive($item.'/'.$child);
			}
			else {
				$this->liveItem($item.'/'.$child);
			};
		};
	};
}
else {
	$this->liveItem($item);
};
}

protected function liveItem($path) { // brings an item live
global $_LW;
static $live_count;
if (!isset($live_count)) { // initalize live count
	$live_count=0;
};
if ($live_count===100) { // every 100 items brought live
	@touch($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].'.live'); // update last live time
	$live_count=0; // reset live count
};
$live_count++; // increment live count
$basename=basename($path); // get item basename
$relative_dir=substr(dirname($path), strlen($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'])); // get relative dir
if (empty($relative_dir) || substr($relative_dir, -1, 1)!='/') {
	$relative_dir.='/';
};
$extension=pathinfo($basename, PATHINFO_EXTENSION); // get filename extension
if ($basename[0]!='.' || substr($basename, 0, 3)=='.ht') { // if valid file
	$will_live=true; // default to taking live
	if (!empty($this->host['LIVE_DEPTH']) && (int)$this->host['LIVE_DEPTH']!=0 && substr_count($relative_dir.$basename, '/')>(int)$this->host['LIVE_DEPTH']) { // apply a migrate depth settings
		$will_live=false;
	};
	if (!empty($this->host['LIVE_EXTENSIONS']) && is_array($this->host['LIVE_EXTENSIONS']) && !in_array(pathinfo($relative_dir.$basename, PATHINFO_EXTENSION), $this->host['LIVE_EXTENSIONS'])) { // apply extension restriction setting
		$will_live=false;
	};
	if ($will_live) { // if approved
		$live_path=$this->host['LIVE_DIR'].$relative_dir.substr($basename, 0, -1*(strlen(pathinfo($basename, PATHINFO_EXTENSION))+1)).(!empty($extension) ? '.'.(in_array($extension, array('html', 'htm', 'php', 'aspx')) ? 'php' : $extension) : ''); // set live path
		if (in_array($extension, array('html', 'htm', 'php', 'aspx'))) { // if page, make it lowercase
			$live_path=substr($live_path, 0, -1*strlen(basename($live_path))).strtolower(basename($live_path));
		};
		$live_path=$_LW->callHandlersByType('application', 'onMigrationPath', array('host'=>$this->host['LIVE_HOST'], 'buffer'=>$live_path)); // apply custom migration path rules
		$live_path=preg_replace('~[/]{2,}~', '/', $live_path); // fix redundant slashes
		$live_path_dir=dirname($live_path); // get live path directory
		$live_path_basename=basename($live_path); // get live path basename
		if (!empty($live_path) && $live_path[0]=='/') { // if valid migration path
			if ($live_path_dir!='/' && !$_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].$live_path_dir)) { // if destination directory doesn't exist
				$_LW->d_ftp->mkdirRecursive($_LW->CONFIG['FTP_PUB'].$live_path_dir, 0775); // try to create it
				if (!empty($_LW->d_ftp->error) && !$_LW->d_ftp->is_dir($_LW->CONFIG['FTP_PUB'].$live_path_dir)) {
					$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>FTP:</strong> '.$_LW->d_ftp->error.' <span class="note">'.$_LW->CONFIG['FTP_PUB'].$live_path_dir.'</span></div>';
				};
			};
			if (!(strpos($basename, 'livewhale.')!==0 && in_array($extension, array('html', 'htm', 'php', 'aspx')) && file_exists(dirname($path).'/livewhale.'.substr($basename, 0, -1*strlen($extension)).'php'))) { // for all content that is not: an original page with a LiveWhale version
				$live_path=str_replace('/livewhale.', '/', $live_path); // format live paths
				$live_path_basename=basename($live_path);
				$live_path_ftp=$_LW->CONFIG['FTP_PUB'].$live_path;
				$path_modified='';
				if (in_array($extension, array('html', 'htm', 'php', 'aspx')) && strpos($path, '/livewhale.')===false) { // if this is not a migrated page
					$source=file_get_contents($path); // get page contents
					$source=$this->rewriteUrls($relative_dir.$basename, $source); // modify urls in page contents
					$path_modified=$_LW->d_ftp->save_upload_file($source); // save modified page contents
				};
				if (!$_LW->d_ftp->file_exists($live_path_ftp) || $_LW->d_ftp->hash($live_path_ftp)!=md5_file((!empty($path_modified) ? $path_modified : $path))) { // if file doesn't exist or is changed
					$_LW->d_ftp->file_put_contents((!empty($path_modified) ? $path_modified : $path), $live_path_ftp); // upload it
					if (empty($_LW->d_ftp->error)) { // if no error
						$this->stats['live']++;
						$_LW->d_ftp->chmod($live_path_ftp, 0775);
						if ($extension=='php' && strpos($basename, 'livewhale.')===0) { // if this is a migrated page
							$id=$_LW->d_pages->addPageFromFTP($live_path, (!empty($this->host['PAGE_HOST']) ? $this->host['PAGE_HOST'] : $this->host['LIVE_HOST'])); // add page to CMS, if it hasn't been already
							if (empty($id)) { // if page not added
								$GLOBALS['status'].='<div class="migration_warning">&bull; Could not import page. <span class="note">'.$live_path_ftp.'</span></div>';
								$this->stats['skipped']++;
							}
							else { // else if page is in db
								$original=str_replace('/livewhale.', '/', $path); // find original page
								if (!file_exists($original)) {
									$original=substr($original, 0, -1*strlen(pathinfo($original, PATHINFO_EXTENSION))).'html';
									if (!file_exists($original)) {
										$original=substr($original, 0, -1*strlen(pathinfo($original, PATHINFO_EXTENSION))).'htm';
										if (!file_exists($original)) {
											$original=substr($original, 0, -1*strlen(pathinfo($original, PATHINFO_EXTENSION))).'aspx';
										};
									};
								};
								if (file_exists($original)) { // if original found
									if ($original_contents=@file_get_contents($original)) { // get contents
										$matches=array();
										preg_match('~<title>(.+?)</title>~', $original_contents, $matches); // find title
										if (!empty($matches[1])) {
											$matches[1]=preg_split('~\s*(·|•|&bull;|//)\s*~', $matches[1]); // format title to remove any prefixes and suffixes
											$matches[1]=sizeof($matches[1])>1 ? implode('', array_slice($matches[1], 0, -1)) : implode('', $matches[1]);
											if (strpos($matches[1], ':')!==false) {
												$matches[1]=trim(substr(strstr($matches[1], ':'), 1));
											};
											if (strpos($matches[1], '|')!==false) {
												$matches[1]=trim(substr(strstr($matches[1], '|'), 1));
											};
											if (strpos($matches[1], '404')!==false) {
												$matches[1]='';
											};
											if (!empty($matches[1])) {
												$_LW->dbo->query('update', 'livewhale_pages', array('short_title'=>$_LW->escape($matches[1])), 'id='.(int)$id)->run(); // update page title
											};
										};
									};
								};
							};
						};
					}
					else {
						$GLOBALS['status'].='<div class="migration_warning">&bull; <strong>FTP:</strong> '.$_LW->d_ftp->error.' <span class="note">'.$live_path_ftp.'</span></div>';
						$this->stats['skipped']++;
					};
				}
				else {
					$this->stats['skipped']++;
				};
				if (!empty($path_modified)) { // clear upload file if there is one
					unlink($path_modified);
				};
			};
		};
	}
	else {
		$this->stats['excluded']++;
	};
};
}

protected function rewriteUrls($path, $source) { // rewrites urls in a page according to migration rules
global $_LW;
static $map;
$find=array(); // init find/replace url arrays
$replace=array();
if (!isset($map['changed_hosts'])) { // if changed hosts not yet recorded
	$map['changed_hosts']=array();
	foreach($this->config['HOSTS'] as $config_host) { // record changed hosts
		if ($config_host['HTTP_HOST']!=$config_host['LIVE_HOST']) {
			$map['changed_hosts'][$config_host['HTTP_HOST']]=$config_host['LIVE_HOST'];
		};
	};
};
$matches=array();
preg_match_all('~((?:src|href)=["\'])([^"\']+?)(["\'])~', $source, $matches); // match urls
if (!empty($matches[0])) { // if there were urls
	foreach($matches[0] as $key=>$val) { // for each url
		if ($info=@parse_url($matches[2][$key])) { // parse the url
			$info['scheme']=strtolower(@$info['scheme']); // format the scheme and host
			$info['host']=strtolower(@$info['host']);
			if ($info['scheme']=='' || $info['scheme']=='http' || $info['scheme']='https') { // if valid scheme and host
				if (empty($info['scheme']) && !empty($info['path']) && $info['path'][0]!='/') { // fix non root-relative directories
					$info['path']=dirname($path).'/'.$info['path'];
				};
				$url_host=!empty($info['host']) ? $info['host'] : $this->host['HTTP_HOST']; // get actual host of url
				if (!empty($info['scheme']) && !empty($info['host'])) { // if scheme and url included in url
					if (isset($map['changed_hosts'][$info['host']])) { // swap host according to LIVE_HOST rules
						$info['host']=$map['changed_hosts'][$info['host']];
					};
					if ($info['host']==$this->host['LIVE_HOST'] || in_array($info['host'], $this->host['LIVE_IGNORED_HOSTS'])) { // if unneeded host, remove it
						$info['host']='';
					};
					$url_host=!empty($info['host']) ? $info['host'] : $this->host['HTTP_HOST']; // update the actual host of url
				};
				if ($info['host']=='' && !empty($info['path']) && $info['path']!='/') { // if local url, apply migration rules
					$relative_dir=dirname($info['path']);
					$basename=basename($info['path']);
					$extension=pathinfo($info['path'], PATHINFO_EXTENSION);
					$live_path_before=$relative_dir.'/'.$basename; // get original live path
					$live_path_after=$this->host['LIVE_DIR'].$relative_dir.'/'.(!empty($extension) ? substr($basename, 0, -1*(strlen($extension)+1)) : $basename).(!empty($extension) ? (in_array($extension, array('html', 'htm', 'php', 'aspx')) ? '.php' : '.'.$extension) : '');
					if (in_array($extension, array('html', 'htm', 'php', 'aspx'))) { // if page, make it lowercase
						$live_path_after=substr($live_path_after, 0, -1*strlen(basename($live_path_after))).strtolower(basename($live_path_after));
					};
					$live_path_after=$_LW->callHandlersByType('application', 'onMigrationPath', array('host'=>$this->host['LIVE_HOST'], 'buffer'=>$live_path_after)); // apply custom migration path rules
					$live_path_after=preg_replace('~[/]{2,}~', '/', $live_path_after); // fix redundant slashes
					$info['path']=$live_path_after;
				};
				if ($info['scheme']=='https' && empty($info['host'])) { // but restore it if SSL requires it
					$info['host']=$this->host['HTTP_HOST'];
				};
				$url=$_LW->setFormatClean(((!empty($info['scheme']) && !empty($info['host'])) ? $info['scheme'].'://'.$info['host'] : '').(!empty($info['path']) ? preg_replace('~[/]{2,}~', '/', trim($info['path'])) : '').(!empty($info['query']) ? '?'.$info['query'] : '').(!empty($info['fragment']) ? '#'.$info['fragment'] : '')); // format final url
				foreach(array('html', 'htm', 'php', 'aspx') as $ext) {
					if (substr($url, -10, 10)=='/index.'.$ext) {
						$url=substr($url, 0, -1*strlen('/index.'.$ext));
					};
					$url=str_replace('/index.'.$ext.'#', '/#', $url);
					$url=str_replace('/index.'.$ext.'?', '/?', $url);
				};
				if ($matches[2][$key]!=$url) { // if the url changed, record the find/replace pair
					$find[]=$matches[1][$key].$matches[2][$key].$matches[3][$key];
					$replace[]=$matches[1][$key].$url.$matches[3][$key];
				};
			};
		};
	};
};
if (!empty($find)) { // replace the urls
	$source=str_replace($find, $replace, $source);
};
return $source;
}

protected function doMigrate($item) { // recursively migrate items
if (substr($item, -1, 1)=='/') {
	$item=substr($item, 0, -1);
};
if (is_dir($item)) {
	foreach(scandir($item) as $child) {
		if ($child!='.' && $child!='..') {
			if (is_dir($item.'/'.$child)) {
				$this->doMigrate($item.'/'.$child);
			}
			else {
				$this->migrateItem($item.'/'.$child);
			};
		};
	};
}
else {
	$this->migrateItem($item);
};
}

protected function migrateItem($path) { // migrates an item
global $_LW;
static $migrate_count;
if (!isset($migrate_count)) { // initalize migration count
	$migrate_count=0;
};
if ($migrate_count===100) { // every 100 migrated pages
	@touch($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].'.migrating'); // update last migration time
	$migrate_count=0; // reset migration count
};
$migrate_count++; // increment migration count
$basename=basename($path); // get item basename
if (strpos($basename, 'livewhale.')!==0) { // if not a migrated page
	$relative_dir=substr(dirname($path), strlen($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'])); // get relative dir
	if (empty($relative_dir) || substr($relative_dir, -1, 1)!='/') {
		$relative_dir.='/';
	};
	$extension=pathinfo($basename, PATHINFO_EXTENSION); // get filename extension
	$will_migrate=true; // default to migrating
	if ($will_migrate && !empty($this->host['MIGRATE_EXCLUDE'])) { // if excluding specific items
		foreach($this->host['MIGRATE_EXCLUDE'] as $migrate_path) { // approve this item by exclusion
			if (strpos($relative_dir.$basename, $migrate_path)===0 || strpos($relative_dir.$basename.'/', $migrate_path)===0) {
				$will_migrate=false;
				break;
			};
		};
	};
	if ($will_migrate && !empty($this->host['MIGRATE_DEPTH']) && (int)$this->host['MIGRATE_DEPTH']!=0 && substr_count($relative_dir.$basename, '/')>(int)$this->host['MIGRATE_DEPTH']) { // apply a migrate depth settings
		$will_migrate=false;
	};
	$live_path_before=$relative_dir.$basename; // get original live path
	$live_path_after=$this->host['LIVE_DIR'].$relative_dir.(!empty($extension) ? substr($basename, 0, -1*(strlen($extension)+1)) : $basename).(!empty($extension) ? '.'. (($will_migrate && in_array($extension, array('html', 'htm', 'php', 'aspx'))) ? 'php' : $extension) : '');
	if (in_array($extension, array('html', 'htm', 'php', 'aspx'))) { // if page, make it lowercase
		$live_path_after=substr($live_path_after, 0, -1*strlen(basename($live_path_after))).strtolower(basename($live_path_after));
	};
	$live_path_after=$_LW->callHandlersByType('application', 'onMigrationPath', array('host'=>$this->host['LIVE_HOST'], 'buffer'=>$live_path_after)); // apply custom migration path rules
	$live_path_after=preg_replace('~[/]{2,}~', '/', $live_path_after); // fix redundant slashes
	if (!empty($live_path_after) && $live_path_after[0]=='/') { // if valid migration path
		if (($basename[0]!='.' || substr($basename, 0, 3)=='.ht') && strpos($basename, 'livewhale.')!==0 && in_array($extension, array('html', 'htm', 'php', 'aspx'))) { // if valid file
			if ($will_migrate) { // if approved
				$was_migrated=false; // default to unmigrated status
				$migrate_path=$relative_dir.'livewhale.'.substr($basename, 0, -1*(strlen(pathinfo($basename, PATHINFO_EXTENSION))+1)).'.php'; // set migration path
				$source=file_get_contents($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$relative_dir.$basename); // get content to be migrated
				if (!preg_match('~<\?php.+?\.livewhale\.php[\'"];.*?\?>~', $source)) { // check if page is already migrated to LiveWhale
					if (strpos($source, '</html>')!==false) { // check if file is an HTML page
						if (!(strpos($source, '#if ')!==false && preg_match('~<!\-\-\s*#if\s+~', $source))) { // check if SSI conditionals found
							if (!preg_match('~<meta\s+name=["]*ProgId["]*\s+content=["]*(?:Word\.Document|PowerPoint\.Slide)["]*~i', $source) && strpos($source, 'xmlns:o="urn:schemas-microsoft-com:"')===false && stripos($source, '<meta name="GENERATOR" content="Microsoft FrontPage')===false && strpos($source, 'xmlns:o="urn:schemas-microsoft-com:office:office"')===false) { // if page is not generated by Word/PowerPoint/FrontPage/Office
								$will_migrate=$_LW->callHandlersByType('application', 'onValidateMigrate', array('host'=>$this->host['HTTP_HOST'], 'path'=>$relative_dir.$basename, 'source'=>$source, 'buffer'=>true)); // apply custom validation rules
								if ($will_migrate) { // if validated
									$source=$this->migratePageContent($relative_dir.$basename, $source); // migrate page contents
									if (strpos($source, '</html>')!==false) { // if page content is valid
										if (!file_exists($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$migrate_path) || hash('md5', $source)!=md5_file($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$migrate_path)) { // if file is new/changed
											file_put_contents($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$migrate_path, $source); // save file
											$this->stats['migrated']++;
											$was_migrated=true; // flag as having been migrated
										}
										else {
											$this->stats['skipped']++;
											$was_migrated=true;
										};
									}
									else {
										$GLOBALS['status'].='<div class="migration_warning">&bull; Skipped a page that is no longer valid after migration. <span class="note">'.$relative_dir.$basename.'</span></div>';
										$this->stats['skipped']++;
									};
								}
								else {
									$this->stats['skipped']++;
								};
							}
							else {
								$GLOBALS['status'].='<div class="migration_warning">&bull; Skipped a third party generated page. <span class="note">'.$relative_dir.$basename.'</span></div>';
								$this->stats['skipped']++;
							};
						}
						else {
							$GLOBALS['status'].='<div class="migration_warning">&bull; Skipped a page with SSI conditional(s). <span class="note">'.$relative_dir.$basename.'</span></div>';
							$this->stats['skipped']++;
						};
					}
					else {
						$GLOBALS['status'].='<div class="migration_warning">&bull; Skipped a non-HTML page. <span class="note">'.$relative_dir.$basename.'</span></div>';
						$this->stats['skipped']++;
					};
				}
				else {
					$GLOBALS['status'].='<div class="migration_warning">&bull; Skipped a page already migrated to LiveWhale. <span class="note">'.$relative_dir.$basename.'</span></div>';
					$this->stats['skipped']++;
				};
				if (!$was_migrated) { // if page wasn't migrated delete a previous migration entry
					@unlink($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].$migrate_path);
				};
			}
			else {
				$this->stats['excluded']++;
			};
		};
	}
	else {
		$GLOBALS['status'].='<div class="migration_warning">&bull; Live path for item invalid after migration rules applied. <span class="note">'.$relative_dir.$basename.' -&gt; '.$live_path_after.'</span></div>';
		$this->stats['skipped']++;
	};
};
}

protected function migratePageContent($path, $source) { // migrates a page
global $_LW;
static $cb;
if (!isset($cb)) { // create callback
	$cb=@create_function('$matches', 'return "<widget type=\"file\"><arg id=\"path\">".$GLOBALS["_LW"]->setFormatClean($matches[1])."</arg></widget>";');
};
$source=$_LW->callHandlersByType('application', 'onBeforeMigrate', array('host'=>$this->host['HTTP_HOST'], 'path'=>$path, 'buffer'=>$source)); // apply custom migration rules
$source=preg_replace('~<\?.+?\?>~s', '', $source); // strip PHP
$source=preg_replace('~<%.+?%>~s', '', $source); // strip ASP
$source=str_replace(array('href="http://'.$_LW->CONFIG['HTTP_HOST'].'"', 'href="http://'.$_LW->CONFIG['HTTP_HOST'].'/"'), 'href="/"', $source); // format homepage links
$source=preg_replace('~<html[^>]*>~', '<html>', $source); // standardize HTML tag
$source=$this->formatComments($source); // format comments
$source=preg_replace('~http:/([^/])~', 'http://\\1', $source); // fix http with missing slash
$source=$_LW->setFormatPage($source); // format page for LiveWhale
if (strpos($source, '#include ')!==false) { // convert Apache SSI includes
	$source=preg_replace_callback('~<!\-\-\s*#include\s+virtual=["\'](.+?)["\']\s*\-\->~', $cb, $source);
};
$xml=new DOMDocument;
if (@$xml->loadXML(str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $source))) { // if page parses as XML
	$xml=$_LW->callHandlersByType('application', 'onBeforeMigrateDOM', array('host'=>$this->host['HTTP_HOST'], 'path'=>$path, 'buffer'=>$xml)); // apply custom migration rules
	$source=trim(str_replace('<'.'?xml version="1.0"?'.'>', '', $xml->saveXML())); // get page source from DOM
	if (!empty($source) && strpos($source, '</html>')!==false) { // if source valid after DOM parsing
		$source=preg_replace('~(<script type="text/javascript">\s*<!\[CDATA\[)~s', '<script type="text/javascript">/* <![CDATA[ */', $source); // fix DOM's CDATA nodes
		$source=preg_replace('~(\]\]>\s*</script>)~s', '/* ]]> */</script>', $source);
		$is_no_editing=false; // default to allowing editing
		if (!empty($this->host['STAGE_NO_EDITING']) && is_array($this->host['STAGE_NO_EDITING'])) { // check if page should not have editing enabled
			foreach($this->host['STAGE_NO_EDITING'] as $spath) {
				if (strpos($path, $spath)===0) {
					$is_no_editing=true;
					break;
				};
			};
		};
		if ($is_no_editing) { // if not editing page
			$matches=array();
			preg_match('~(<body.*?>)~', $source, $matches);
			if (!empty($matches[1])) { // flag it as such
				$source=strpos($matches[1], 'class=')!==false ? str_replace($matches[1], preg_replace('~class="(.*?)"~', 'class="\\1 lw_no_editing"', $matches[1]), $source) : str_replace($matches[1], substr($matches[1], 0, -1).' class="lw_no_editing">', $source);
			};
		};
		$had_template=false;
		$had_editable=false;
		if (!empty($this->host['TEMPLATES'])) { // if templates configured
			foreach($this->host['TEMPLATES'] as $prefix=>$template_info) { // find most specific template to use for this page
				if (strpos($path, $prefix)===0 || strpos($path.'/', $prefix)===0) {
					$template=$template_info;
				};
			};
			if (!empty($template)) { // if a template was chosen
				$had_template=true; // flag as having a template
				if (!empty($template[0]) && is_scalar($template[0]) && !empty($template[1]) && is_array($template[1])) { // check template path and map
					$tmp_path=$_LW->INCLUDES_DIR_PATH.'/data/migration/hosts/'.$this->host['HTTP_HOST'].dirname($path).'/tmp.'.basename($path); // get tmp path
					$extension=pathinfo($tmp_path, PATHINFO_EXTENSION); // get extension
					$tmp_path=substr($tmp_path, 0, -1*strlen($extension)).'php'; // change extension to php
					file_put_contents($tmp_path, $source); // save current page source
					$template_error=$this->changeTemplateForPage($tmp_path, $template[0], $template[1]); // apply template
					if (empty($template_error)) { // if no error thrown by template change
						$template_applied=true; // flag as having been applied
						$source=file_get_contents($tmp_path); // get back page source
					};
					unlink($tmp_path); // clear the tmp file
				};
			};
		};
		if (!$is_no_editing && !empty($this->host['EDITABLE'])) { // if editable regions configured and allowing editing of this page
			foreach($this->host['EDITABLE'] as $prefix=>$editable_info) { // find most specific editable regions to use for this page
				if (strpos($path, $prefix)===0 || strpos($path.'/', $prefix)===0) {
					$editable=$editable_info;
				};
			};
			if (!empty($editable)) { // if editable regions were chosen
				$had_editable=true; // flag as having editable regions to apply
				$ids=array();
				$optional=array();
				if (!empty($editable[0]) && is_array($editable[0])) { // get ids and optional elements
					$ids=array_merge($ids, $editable[0]);
				};
				if (!empty($editable[1]) && is_array($editable[1])) {
					$optional=array_merge($optional, $editable[1]);
				};
				if (!empty($ids)) { // if there are editable regions to apply
					$source=$this->addEditableElements($source, $ids, $optional); // apply them
					if (preg_match('~class=".*?editable.*?"~', $source)) { // flag as having successful editable region application if so
						$editable_applied=true;
					};
				};
			};
		};
		if (!empty($had_template) && empty($template_applied)) { // warn if template failed to apply
			$GLOBALS['status'].='<div class="migration_warning">&bull; Page failed to have template applied. It has been migrated, but will be uneditable.'.(!empty($template_error) ? ' Problem was: '.$template_error : ' <span class="note">'.$path.'</span></div>').'';
		};
		if (!empty($had_editable) && empty($editable_applied)) { // warn if editable regions failed to apply
			$GLOBALS['status'].='<div class="migration_warning">&bull; Page failed to have editable regions applied. It has been migrated, but will be uneditable. <span class="note">'.$path.'</span></div>';
		};
		if (@$xml->loadXML(str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $source))) { // if page parses as XML
			if (preg_match('~class=".*?editable.*?"~', $source)) { // strip inline JS, if there are editable elements in the page
				$xml=$this->stripInlineJS($path, $xml);
			};
			$xml=$_LW->callHandlersByType('application', 'onAfterMigrateDOM', array('host'=>$this->host['HTTP_HOST'], 'path'=>$path, 'buffer'=>$xml)); // apply custom migration rules
			$source=trim(str_replace('<'.'?xml version="1.0"?'.'>', '', $xml->saveXML())); // get page source from DOM
			if (!empty($source) && strpos($source, '</html>')!==false) { // if source valid after DOM parsing
				$source=preg_replace('~<style[^>]*>.*?</style>~s', '', $source); // strip inline style tags
				if (!preg_match('~<\?php.+?\.livewhale\.php\';~', $source)) { // add LiveWhale include, if it doesn't exist from a template application
					$source='<?php require \''.preg_replace('~[/]{2,}~', '/', $this->host['LIVE_INCLUDES_DIR'].'/cache.livewhale.php').'\';?>'."\n".$source;
				};
				$source=$this->rewriteUrls($path, $source); // modify urls in page contents
				$source=$_LW->callHandlersByType('application', 'onAfterMigrate', array('host'=>$this->host['HTTP_HOST'], 'path'=>$path, 'buffer'=>$source)); // apply custom migration rules
				$source=$_LW->setFormatPage($source); // format page for LiveWhale again
				if (@$xml->loadXML(str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $source))) { // if page parses as XML
					$this->getWhitelistHosts($source); // get hosts for whitelist
				}
				else {
					$GLOBALS['status'].='<div class="migration_warning">&bull; Page skipped because onAfterMigrate produced invalid XML. <span class="note">'.$path.'</span></div>';
					$this->stats['skipped']++;
					$source=''; // clear source so that it isn't migrated
				};
			}
			else {
				$GLOBALS['status'].='<div class="migration_warning">&bull; Page skipped because onAfterMigrateDOM produced invalid XML. <span class="note">'.$path.'</span></div>';
				$this->stats['skipped']++;
				$source=''; // clear source so that it isn't migrated
			};
		}
		else {
			$GLOBALS['status'].='<div class="migration_warning">&bull; Page skipped because template/editable element application produced invalid XML. <span class="note">'.$path.'</span></div>';
			$this->stats['skipped']++;
			$source=''; // clear source so that it isn't migrated
		};
	}
	else {
		$GLOBALS['status'].='<div class="migration_warning">&bull; Page skipped because onBeforeMigrateDOM produced invalid content. <span class="note">'.$path.'</span></div>';
		$this->stats['skipped']++;
		$source=''; // clear source so that it isn't migrated
	};
}
else {
	$this->stats['skipped']++;
	$GLOBALS['status'].='<div class="migration_warning">&bull; Page skipped because unable to parse as XML. <span class="note">'.$path.'</span></div>';
	$GLOBALS['status'].='<div class="migration_warning">XML errors were:</div>';
	libxml_use_internal_errors(true); // don't display errors
	$errors=array();
	if (!$sxe=simplexml_load_string($source)) { // if page fails to parse as XML, record each unique error
		foreach(libxml_get_errors() as $error) {
			if ($error=htmlentities($error->message)) {
				if (!in_array($error, $errors)) {
					$GLOBALS['status'].='<div class="migration_warning">'.$error.'</div>';
				};
			};
		};
	};
	$source=''; // clear source so that it isn't migrated
};
return $source;
}

protected function formatComments($source) { // formats comments
static $cb;
if (!isset($cb)) { // create callback
	$cb=@create_function('$matches', 'return (strpos($matches[1], "endif")!==false) ? substr($matches[1], 0, -1)."-->" : "<!--".substr($matches[1], 2);');
};
$source=preg_replace('~\-\->\s*<!(\[[^\]]+\]>.+?)<!\[endif\]>~s', '--><!--\\1<![endif]-->', $source); // fix bad comments
$source=preg_replace('~(<span.*?>)<!\[endif\]>(.*?</span>)~s', '\\1\\2', $source);
$matches=array(); // init array of comments
preg_match_all('~<!--.+?-->~s', $source, $matches); // match comments
foreach($matches[0] as $key=>$val) { // swap in comment placeholders
	$source=str_replace($val, '%%comment_'.$key.'%%', $source);
};
$source=preg_replace_callback('~(<!\[[^\]]+\]>)~', $cb, $source); // fix ifs
foreach($matches[0] as $key=>$val) { // swap comments back in
	$source=str_replace('%%comment_'.$key.'%%', $val, $source);
};
return $source;
}

protected function getWhitelistHosts($source) { // detects whitelist hosts in a page
$matches=array();
preg_match_all('~<(?:iframe|script).+?src\s*=\s*["\']([^"\']+?)["\'][^>]*?>~', $source, $matches); // match src and href urls in iframes and scripts
if (!empty($matches[1])) { // if there were matches
	foreach($matches[1] as $key=>$val) { // loop through hosts
		if ($host=@parse_url($val, PHP_URL_HOST)) {
			if (!empty($host) && $host!=$this->host['LIVE_HOST'] && !preg_match('~^.*?'.preg_quote(strstr($this->host['LIVE_HOST'], '~')).'$~', $host)) { // loop through unique hosts
				if (!in_array($host, $this->whitelist_hosts)) {
					$this->whitelist_hosts[]=$host;
				};
			};
		};
	};
};
}

protected function addEditableElements($source, $ids, $optional=array()) { // makes elements editable
$source=str_replace(' class=""', '', $source); // clear any blank classes
foreach($ids as $id) {
	if (strpos($source, 'id="'.$id.'"')!==false) { // if element is in the document
		$find=array(); // init arrays
		$replace=array();
		$matches=array();
		$class=in_array($id, $optional) ? 'editable optional' : 'editable'; // choose the class to set
		preg_match_all('~<[a-zA-Z0-9]+\s+.*?id="'.$id.'"[^>]*>~', $source, $matches); // match all elements with this id
		if (!empty($matches[0])) {
			foreach($matches[0] as $val) { // loop through elements
				$find[]=$val; // add find entry
				$replace[]=strpos($val, 'class=')!==false ? preg_replace('~class=["\'](.*?)["\']~', 'class="\\1 '.$class.'"', $val) : substr($val, 0, -1).' class="'.$class.'">'; // format and add replace entry
			};
		};
		if (!empty($find)) { // swap in replacements
			$source=str_replace($find, $replace, $source);
		};
	};
};
return $source;
}

protected function changeTemplateForPage($path, $template_path, $map) { // changes a page's template
global $_LW;
if (pathinfo($path, PATHINFO_EXTENSION)!='php' || pathinfo($template_path, PATHINFO_EXTENSION)!='php') { // return false if either path fails extension check
	return false;
};
if (file_exists($path)) { // if both files exist
	if (file_exists($template_path)) {
		if ($contents_src=file_get_contents($path)) { // if file contents obtained
			if ($contents_dest=file_get_contents($template_path)) {
				$xml_src=new DOMDocument; // init new DOM document
				if ($xml_src->loadXML($contents_src)) { // if page parses
					$xpath_src=new DOMXPath($xml_src); // init XPath
					$xml_dest=new DOMDocument; // init new DOM document
					if (@$xml_dest->loadXML($contents_dest)) { // if page parses
						$xpath_dest=new DOMXPath($xml_dest); // init XPath
						$did_copy=false; // init success flag
						if ($dest_nodes=$xpath_dest->query('//*[string(@id) and contains(@class,"editable") and not(./ancestor::*[contains(@class,"editable")])]')) foreach($dest_nodes as $dest_node) { // loop through dest's editable regions
							if ($dest_id=$dest_node->getAttribute('id')) {
								$ids=array();
								foreach($map as $key=>$val) { // get elements from src that should be copied
									if ($val==$dest_id) {
										$ids[]=$key;
									};
								};
								if (!empty($ids)) { // copy content from src to dest
									foreach($ids as $src_id) {
										if ($src_nodes=$xpath_src->query('//*[@id="'.$src_id.'"]')) {
											if ($src_nodes->length==1) {
												while ($dest_node->hasChildNodes()) { // blank the dest element
													$dest_node->removeChild($dest_node->firstChild);
												};
												if ($src_node_data=$xml_src->saveXML($src_nodes->item(0))) { // copy the content
													$src_node_data=$_LW->getInnerXML($src_node_data);
													$did_copy=true;
													if (!empty($src_node_data)) {
														$frag=$xml_dest->createDocumentFragment();
														if ($append=@$frag->appendXML($src_node_data)) {
															$dest_node->appendChild($frag);
														};
													};
												};
											};
										};
									};
								};
							};
						};
						if (!empty($did_copy)) { // if anything was copied
							$contents_new=$xml_dest->saveXML(); // get new page contents
							if (strpos($contents_new, '<'.'?xml version="1.0"?'.'>')===0) {
								$contents_new=trim(substr($contents_new, 21));
							};
							$xml_new=new DOMDocument; // init new DOM document
							if (!$xml_new->loadXML($contents_new)) { // ensure that page still parses
								unset($contents_new);
							};
							if (!empty($contents_new)) { // if we have new page contents
								if ($contents_src==$contents_new) { // if nothing changed in the src page
									$was_successful=true; // flag as successful, and skip save
								}
								else { // else if writing file, write file and flag as successful
									if (file_put_contents($path, $contents_new)) {
										$was_successful=true;
									};
								};
							}
							else {
								return 'Page being migrated did not survive template application. <span class="note">'.$path.'</span><br/>';
							};
						}
						else {
							return 'Map used for migration to template did not apply. <span class="note">'.str_replace('/tmp.', '/', $path).'</span><br/>';
						};
					}
					else {
						return 'Template used for migration could not be parsed as XML. <span class="note">'.$template_path.'</span><br/>';
					};
				}
				else {
					return 'Page being migrated could not be parsed as XML. <span class="note">'.$path.'</span><br/>';
				};
			}
			else {
				return 'Could not read template. <span class="note">'.$template_path.'</span><br/>';
			};
		}
		else {
			return 'Could not read page. <span class="note">'.$path.'</span><br/>';
		};
	}
	else {
		return 'Template used for migration does not exist. <span class="note">'.$template_path.'</span><br/>';
	};
}
else {
	return 'Page being migrated does not exist. <span class="note">'.$path.'</span><br/>';
};
}

protected function stripInlineJS($path, $xml) { // strips inline JS inside editable regions, and warns when JS is stripped
$xpath=new DOMXPath($xml);
if ($nodes=$xpath->query('//*[contains(@class,"editable")]//script')) { // find JS inside editable regions
	if ($nodes->length) { // if JS found
		foreach($nodes as $node) { // remove scripts with no src attribute
			if (!$node->hasAttribute('src')) {
				$node->parentNode->removeChild($node);
				$found_inline_js=true;
			};
		};
	};
};
if (!empty($found_inline_js)) {
	$GLOBALS['status'].='<div class="migration_warning">&bull; Inline JS inside editable region stripped from page. <span class="note">'.$path.'</span></div>';
};
return $xml;
}

public function onInstall() { // installs the module
global $_LW;
$module_config=&$_LW->REGISTERED_MODULES['migration']; // get the config for this module
$_LW->dbo->sql('INSERT INTO livewhale_modules VALUES(NULL, "migration", '.(float)$module_config['revision'].');'); // register module in modules table
}

public function onLogin() { // execute any login actions
global $_LW;
$module_config=&$_LW->REGISTERED_MODULES['migration']; // get the config for this module
if (!$revision=$_LW->isInstalledModule('migration')) { // if module is not installed
	$this->onInstall(); // install this module
}
else { // else if module is installed
	if ($revision!=$module_config['revision']) { // if an upgrade is needed
		$_LW->upgradeModule('migration'); // upgrade the module
	};
};
if (empty($_LW->_GET['lw_auth']) || !empty($_LW->_GET['lw_auth_full_login'])) {
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration')) { // ensure migration dirs exist
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/migration');
	};
	if (!is_dir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts')) {
		@mkdir($_LW->INCLUDES_DIR_PATH.'/data/migration/hosts');
	};
};
}

}

?>