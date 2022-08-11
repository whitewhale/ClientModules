<?php

ini_set('display_errors', 0);

// Configuration for this server

$HTTP_HOST='';
$SFTP_HOST='';
$SFTP_PORT=22;
$SFTP_USER='';
$SFTP_PASSWORD='';
$SFTP_PUBLIC_KEY_PATH='';
$SFTP_PATH_HOME='';
$SFTP_PATH_WWW='';
$DB_HOST='';
$DB_USER='';
$DB_PASSWORD='';
$DB_DATABASE='';

require './get_url.php';

function formatCommandLine($buffer) { // formats the page output for command line
$buffer=preg_replace(['~<style>.+?</style>~s', '~<title>.+?</title>~s'], '', $buffer);
$buffer=str_replace('</td><td>', ' ', $buffer);
$buffer=str_replace('<td', '<br/><td', $buffer);
$buffer=strip_tags($buffer, '<br/><br>');
$buffer=str_replace('<br/>', "\n\n", $buffer);
$buffer=preg_replace('~[\n]{3,}~', "\n\n", $buffer);
$buffer=preg_replace('~(SUCCESS|FAIL|WARNING) ~', '\\1: ', $buffer);
$buffer=html_entity_decode($buffer);
return "\n".trim($buffer)."\n\n";
};

if (strpos(trim(@$_SERVER['HTTP_USER_AGENT']), 'curl')===0 || empty($_SERVER['HTTP_HOST'])) { // format output for command line if curl user agent detected
	ob_start('formatCommandLine');
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>LiveWhale Readiness Test</title>
	<style>
	.success { color:#14B03B; }
	.failure { color:#B01419; }
	.warning { color:#D0D001; }
	.results td { vertical-align:top; padding:0px 40px 20px 0px; }
	</style>
</head>
<body>

<h2>LiveWhale Readiness Test</h2>
<p>Hi there! I'm performing a series of tests to determine how ready this server is for a new LiveWhale installation.</p>
<hr/>
<table class="results">
<?php

if (!empty($SFTP_HOST) && !empty($SFTP_PORT) && !empty($SFTP_USER) && (!empty($SFTP_PASSWORD) || !empty($SFTP_PUBLIC_KEY_PATH))) { // if SFTP info provided
	// SSH2/SFTP Access

	if (extension_loaded('ssh2')) {
		$methods=[];
		if (!empty($SFTP_PUBLIC_KEY_PATH)) {
			if ($contents=@file_get_contents($SFTP_PUBLIC_KEY_PATH.(substr($SFTP_PUBLIC_KEY_PATH, -1, 1)!='/' ? '/' : '').'key.public')) {
				if (strpos($contents, 'ssh-dss')===0) {
					$methods=['hostkey'=>'ssh-dss'];
				}
				else if (strpos($contents, 'ssh-rsa')===0) {
					$methods=['hostkey'=>'ssh-rsa'];
				};
			};
		};
		if ($ssh=@ssh2_connect($SFTP_HOST, $SFTP_PORT, $methods)) {
			if (!empty($SFTP_PUBLIC_KEY_PATH)) {
				if ($SFTP_PUBLIC_KEY_PATH[0]!='/' || !file_exists($SFTP_PUBLIC_KEY_PATH.(substr($SFTP_PUBLIC_KEY_PATH, -1, 1)!='/' ? '/' : '').'key.public') || !file_exists($SFTP_PUBLIC_KEY_PATH.(substr($SFTP_PUBLIC_KEY_PATH, -1, 1)!='/' ? '/' : '').'key.private')) {
					echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>SFTP public key path configured, but cannot locate public and private keys.</td></tr>';
				}
				else {
					$ssh_auth=@ssh2_auth_pubkey_file($ssh, $SFTP_USER, $SFTP_PUBLIC_KEY_PATH.(substr($SFTP_PUBLIC_KEY_PATH, -1, 1)!='/' ? '/' : '').'key.public', $SFTP_PUBLIC_KEY_PATH.(substr($SFTP_PUBLIC_KEY_PATH, -1, 1)!='/' ? '/' : '').'key.private');
				};
			}
			else {
				$ssh_auth=@ssh2_auth_password($ssh, $SFTP_USER, $SFTP_PASSWORD);
			};
			if (!empty($ssh_auth)) {
				if ($ssh_sftp=@ssh2_sftp($ssh)) {
					$has_sftp_access=true;
					if (@is_dir('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME)) {
						$SFTP_PATH_HOME_PARENT=dirname($SFTP_PATH_HOME);
						@touch('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME_PARENT);
						$is_writable=file_exists('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME_PARENT);
						if (!empty($is_writable)) {
							echo '<tr><td class="success">SUCCESS</td><td>SSH2/SFTP Access (HOME)</td><td></td></tr>';
							@unlink('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME_PARENT);
						}
						else {
							echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access (HOME)</td><td>Access successful, but cannot write to '.$SFTP_PATH_HOME_PARENT.' (for node module installation).</td></tr>';
						};
					}
					else {
						echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Access successful, but cannot locate '.$SFTP_PATH_HOME.'.</td></tr>';
					};
				}
				else {
					echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Cannot obtain SFTP handle.</td></tr>';
				};
				if (@is_dir('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_WWW)) {
					echo '<tr><td class="success">SUCCESS</td><td>SSH2/SFTP Access (WWW)</td><td></td></tr>';
					
				}
				else {
					echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access (WWW)</td><td>Access successful, but cannot locate '.$SFTP_PATH_WWW.'.</td></tr>';
				};
			}
			else {
				$ssh_auth_methods=ssh2_auth_none($ssh, $SFTP_USER);
				$has_password_auth=@in_array('password', $ssh_auth_methods);
				echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Cannot authenticate with credentials provided.'.((!$has_password_auth && empty($SFTP_PUBLIC_KEY_PATH)) ? ' Note that "password" authentication scheme not enabled in sshd_config.' : '').'</td></tr>';
			};
		}
		else {
			echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Cannot access host '.$SFTP_HOST.'. (Please ensure SELinux is not blocking connections.)</td></tr>';
		};
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Cannot perform check, ssh2 extension not available.</td></tr>';
	};

	// SSH2/SFTP Write Access

	if (!empty($has_sftp_access)) {
		if ($contents=@file_get_contents('./file.html')) {
			if ($stream=@fopen('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html', 'w')) {
				for ($written=0, $max=strlen($contents);$written<$max;$written+=$fwrite) {
					$fwrite=@fwrite($stream, substr($contents, $written));
					if ($fwrite===false) {
						fclose($stream);
						unset($stream);
						break;
					};
				};
				if (isset($stream)) {
					fclose($stream);
					unset($stream);
				};
				if (!empty($written) && file_exists('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html')) {
					$perms=@fileperms('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html');
					if ($perms!==false) {
						$perms=(($perms & 0xC000) == 0xC000) ? 's' : ((($perms & 0xA000) == 0xA000) ? 'l' : ((($perms & 0x8000) == 0x8000) ? '-' : ((($perms & 0x6000) == 0x6000) ? 'b' : ((($perms & 0x4000) == 0x4000) ? 'd' : ((($perms & 0x2000) == 0x2000) ? 'c' : ((($perms & 0x1000) == 0x1000) ? 'p' : 'u')))))).(($perms & 0x0100) ? 'r' : '-').(($perms & 0x0080) ? 'w' : '-').(($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-')).(($perms & 0x0020) ? 'r' : '-').(($perms & 0x0010) ? 'w' : '-').(($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-')).(($perms & 0x0004) ? 'r' : '-').(($perms & 0x0002) ? 'w' : '-').(($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));
					};
					if ($perms!='-rw-rw-r--') {
						echo '<tr><td class="warning">WARNING</td><td>SSH2/SFTP Write Access</td><td>Write successful, but resulting permissions are '.$perms.' instead of -rw-rw-r--.<br/>Group write (UMASK 002) is not required, but may result in multiple SFTP users locking each other out of files if not set.<br/>On Ubuntu, this can typically be resolved by editing /etc/pam.d/common-session and changing "session optional pam_umask.so" to "session optional pam_umask.so umask=002".</td></tr>';
					}
					else {
						echo '<tr><td class="success">SUCCESS</td><td>SSH2/SFTP Write Access</td><td></td></tr>';
					};
					@unlink('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html');
					@ssh2_exec($ssh, 'echo hello > '.$SFTP_PATH_HOME.'/file2.html');
					if (!file_exists('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html')) {
						echo '<tr><td class="warning">WARNING</td><td>SSH2 Shell Exec</td><td>There is no SSH2 shell access with this server. While this is not required, some additional functionality may be limited. Providing shell access is recommended for White Whale to provide full diagnostics/support for your installation.</td></tr>';
					}
					else {
						echo '<tr><td class="success">SUCCESS</td><td>SSH2 Shell Exec Access</td><td></td></tr>';
					};
					@unlink('ssh2.sftp://'.intval($ssh_sftp).$SFTP_PATH_HOME.'/file2.html');
				}
				else {
					echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Write Access</td><td>Could not open file for writing at '.$SFTP_PATH_HOME.'.</td></tr>';
				};
			}
			else {
				echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Write Access</td><td>Could not open file for writing at '.$SFTP_PATH_HOME.'.</td></tr>';
			};
		}
		else {
			echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Write Access</td><td>Could not obtain source file to write.</td></tr>';
		};
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Write Access</td><td>Cannot perform check, ssh/sftp access not available.</td></tr>';
	};

	//SSH2/SFTP Group Write

	if (!empty($has_sftp_access)) {
	
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Group Write</td><td>Cannot perform check, ssh/sftp access not available.</td></tr>';
	};

	# SSH2/SFTP & Firewall

	if (file_get_contents('http://www.livewhale.com/sftp/?host='.rawurlencode($HTTP_HOST))=='PASS') {
		echo '<tr><td class="success">SUCCESS</td><td>SSH2/SFTP &amp; Firewall</td><td></td></tr>';
	}
	else {
		echo '<tr><td class="warning">WARNING</td><td>SSH2/SFTP &amp; Firewall</td><td>SSH connections from the LiveWhale server to '.$HTTP_HOST.' are being blocked. If using SFTP instead of FTP (recommended), this will need to be resolved before LiveWhale can be installed.</td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>SSH2/SFTP Access</td><td>Skipping SFTP tests because credentials not specified.</td></tr>';
};

// Database Access

if (!empty($DB_HOST) && !empty($DB_USER) && !empty($DB_PASSWORD) && !empty($DB_DATABASE)) {
	if (function_exists('mysqli_init')) {
		$db=mysqli_init();
		$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
		@$db->real_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
		if (mysqli_connect_errno()) {
			echo '<tr><td class="failure">FAIL</td><td>Database Access</td><td>MySQLi Error: '.$db->error.'</td></tr>';
		}
		else {
			$has_db=true;
			echo '<tr><td class="success">SUCCESS</td><td>Database Access</td><td></td></tr>';
		};
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>Database Access</td><td>MySQLi extension not available.</td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>Database Access</td><td>Cannot perform check, login credentials not available.</td></tr>';
};

// PHP Extensions

$missing=[];
foreach(['SimpleXML', 'dom', 'tidy', 'ftp', 'json', 'mysqli', 'gd', 'curl', 'zlib'] as $extension) {
	if (!extension_loaded($extension)) {
		$missing[]=$extension;
	};
};
$has_apc=extension_loaded('apcu') ? true : extension_loaded('apc');
$has_ssh2=extension_loaded('ssh2');
if (!empty($missing)) {
	echo '<tr><td class="failure">FAIL</td><td>PHP Extensions</td><td>The following required PHP extensions are missing: '.implode(', ',$missing).'.'.(!$has_apc ? '<br/><br/>Though not required, we suggest installing and enabling APCu.' : '').(!$has_ssh2 ? '<br/><br/>Though not required, we <strong>strongly</strong> suggest installing and enabling SSH2 for SFTP support.' : '').'</td></tr>';
}
else {
	echo '<tr><td class="success">SUCCESS</td><td>PHP Extensions</td><td>'.(!$has_apc ? '<br/><br/>Though not required, we suggest installing and enabling APCu.' : '').(!$has_ssh2 ? '<br/><br/>Though not required, we suggest installing and enabling SSH2 for SFTP support.' : '').'</td></tr>';
};

// Shell Access

$ts=@shell_exec('echo "'.time().'"');
if (!preg_match('~^[0-9]+$~', $ts)) {
	echo '<tr><td class="failure">FAIL</td><td>Shell Exec</td><td>Local shell access is disabled on this server. Ability to use the shell_exec() function is critical for a number of tasks, such as performing database backups.</td></tr>';
}
else {
	echo '<tr><td class="success">SUCCESS</td><td>Shell Exec</td><td></td></tr>';
};

// PHP CLI Execution

$cli_check=@shell_exec('php -v');
if (strpos($cli_check, 'Zend')===false) {
	echo '<tr><td class="failure">FAIL</td><td>PHP CLI Execution</td><td>The web server cannot run command line PHP commands.</td></tr>';
}
else {
	echo '<tr><td class="success">SUCCESS</td><td>PHP CLI Execution</td><td></td></tr>';
};

// PHP Configuration Settings

$settings=[];
if ($memory=ini_get('memory_limit')) {
	if (substr($memory, -1, 1)=='M') {
		$memory=substr($memory, 0, -1);
		if ($memory<128) {
			$settings[]='The memory_limit setting is '.$memory.'M -- it should be at least 128M';
		};
	};
};
if (!ini_get('allow_url_fopen')) {
	$settings[]='allow_url_fopen should be enabled';
};
if (!ini_get('date.timezone')) {
	$settings[]='date.timezone should be set';
};
$upload_max_filesize=intval(ini_get('upload_max_filesize'));
if ($upload_max_filesize<10) {
	$settings[]='PHP\'s upload_max_filesize size should be large enough to accomodate modern high resolution digital camera image files, or large, several-megabyte PDF files, we recommend setting this to something sufficiently high (20M - 50M) to clear any issues with upload limitations';
};
$post_max_size=intval(ini_get('post_max_size'));
if ($post_max_size<=$upload_max_filesize) {
	$settings[]='PHP\'s post_max_size ('.$post_max_size.') size must be larger than PHP\'s upload_max_filesize ('.$upload_max_filesize.')';
};
$max_input_vars=ini_get('max_input_vars');
if ($max_input_vars<5000) {
	$settings[]='Though not required, we suggest raising max_input_vars to a recommended limit of 5000.';
};
if ($has_apc) {
	$apc_shm_size=intval(ini_get('apc.shm_size'));
	if (empty($apc_shm_size) || $apc_shm_size<64) {
		$settings[]='apc.shm_size should be at least 256M';
	};
	if (extension_loaded('apcu')) {
		$apc_ttl=ini_get('apc.ttl');
		if (empty($apc_ttl)) {
			$settings[]='apc.ttl should be enabled (example: 7200)';
		};
	}
	else {
		$apc_user_ttl=ini_get('apc.user_ttl');
		if (empty($apc_user_ttl)) {
			$settings[]='apc.user_ttl should be enabled (example: 7200)';
		};
	};
};
$disable_functions=explode(',', ini_get('disable_functions'));
if (!empty($disable_functions)) {
	$disabled=[];
	foreach($disable_functions as $function) {
		$function=trim($function);
		if (in_array($function, ['shell_exec', 'curl_exec', 'curl_multi_exec'])) {
			$disabled[]=$function;
		};
	};
	if (!empty($disabled)) {
		$settings[]='Remove the following functions from disable_functions: '.implode(', ', $disabled);
	};
};
$php_version=phpversion();
if (version_compare($php_version, '5.5.0', '<') || version_compare($php_version, '7.4.0', '>=')) {
	$settings[]='The PHP version now required for new LiveWhale installs is: 5.5 - 7.4.';
};
if (!empty($settings)) {
	echo '<tr><td class="failure">FAIL</td><td>PHP Configuration Settings</td><td>PHP Version: '.$php_version.'<br/>Timezone: '.(ini_get('date.timezone')=='' ? 'blank' : ini_get('date.timezone')).'<br/><br/>The following PHP settings should be corrected:<br/><br/>'.implode('<br/><br/>',$settings).'</td></tr>';
}
else {
	echo '<tr><td class="success">SUCCESS</td><td>PHP Configuration Settings</td><td>PHP Version: '.$php_version.'<br/>Timezone: '.ini_get('date.timezone').'</td></tr>';
};


// MySQL Configuration Settings

if (!empty($has_db)) {
	$settings=[];
	$version='';
	if ($res=$db->query('SHOW VARIABLES LIKE "version";')) {
		if ($res2=$res->fetch_assoc()) {
			$version=$res2['Value'];
			if (version_compare($version, '5.1.0', '<')) {
				$settings[]='The minimum version required is 5.1.';
			};
		};
		$res->close();
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "innodb_buffer_pool_size";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			$res2['Value']=$res2['Value']/1024/1024;
			if ($res2['Value']<256) {
				$settings[]='The innodb_buffer_pool_size setting should be at least 256MB - although RAM permitting, something higher such as 1G is recommended. It is currently '.(!empty($res2['Value']) ? $res2['Value'] : 'blank').'.';
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "innodb_flush_method";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			if ($res2['Value']!='O_DIRECT') {
				$settings[]='innodb_flush_method should be O_DIRECT, it is currently '.(!empty($res2['Value']) ? $res2['Value'] : 'blank');
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "query_cache_size";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			if (empty($res2['Value'])) {
				$has_no_query_cache=true;
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "max_allowed_packet";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			$max_allowed_packet=$res2['Value'];
			$upload_max_filesize=ini_get('upload_max_filesize');
			if (substr($upload_max_filesize, -1, 1)=='M') {
				$upload_max_filesize=(int)substr($upload_max_filesize, 0, -1)*1024*1024;
			};
			if (!empty($max_allowed_packet) && !empty($upload_max_filesize)) {
				if ($max_allowed_packet/1e+6<$upload_max_filesize/1024/1024) {
					$settings[]='MySQL\'s max_allowed_packet size ('.$max_allowed_packet.') must be at least as big as PHP\'s upload_max_filesize ('.$upload_max_filesize.')';
				};
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "innodb_file_per_table";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			if ($res2['Value']!='ON') {
				$settings[]='Though not required, the MySQL innodb_file_per_table setting is recommended to be on, currently it is off.';
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "group_concat_max_len";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			if ($res2['Value']<128) {
				$settings[]='The value of group_concat_max_len ('.$res2['Value'].') is too low. A value of 1024 is recommended.';
			};
		};
	};
	if ($res=$db->query('SHOW VARIABLES LIKE "innodb_ft_min_token_size";')) {
		if ($res->num_rows) {
			$res2=$res->fetch_assoc();
			$res->close();
			if ($res2['Value']>3) {
				$settings[]='The value of innodb_ft_min_token_size ('.$res2['Value'].') is too high. A value of 3 is recommended.';
			};
		};
	};
	if (!empty($settings)) {
		echo '<tr><td class="failure">FAIL</td><td>MySQL Configuration Settings</td><td>MySQL Version: '.$version.'<br/><br/>The following MySQL settings should be corrected:<br/><br/>'.implode('<br/><br/>',$settings).'</td></tr>';
	}
	else {
		echo '<tr><td class="success">SUCCESS</td><td>MySQL Configuration Settings</td><td>MySQL Version: '.$version.'</td></tr>';
	};
	$db->close();
}
else {
	echo '<tr><td class="failure">FAIL</td><td>MySQL Configuration Settings</td><td>Cannot perform check, database access not available.</td></tr>';
};

# HTTP Access

if (extension_loaded('curl')) {
	$response=getUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$HTTP_HOST.'/livewhale-readiness-test/file.html', true, false, [CURLOPT_ENCODING=>1, CURLOPT_HTTPHEADER=>['Accept-Encoding: gzip,deflate']]);
	if (empty($GLOBALS['last_code']) || strpos($response, '<div id="foo"></div>')===false) {
		echo '<tr><td class="failure">FAIL</td><td>HTTP Access</td><td>The web server is not able to make an HTTP request to itself at '.$HTTP_HOST.'.</td></tr>';
	}
	else {
		echo '<tr><td class="success">SUCCESS</td><td>HTTP Access</td><td></td></tr>';
		$has_http_access=true;
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>HTTP Access</td><td>Cannot perform check, curl extension not available.</td></tr>';
};

# GZIP Enabled

if (!empty($has_http_access)) {
	if (empty($GLOBALS['last_headers']) || strpos(implode(' ', $GLOBALS['last_headers']), 'gzip')===false) {
		echo '<tr><td class="failure">FAIL</td><td>GZIP Enabled</td><td>The web server is not GZIP enabled. While not required, we recommend enabling mod_deflate for Apache. Please consult LiveWhale\'s <a href="https://livewhale.desk.com/customer/portal/articles/1620221-apache-mod_deflate">server requirements</a> for example configuration settings.</td></tr>';
	}
	else {
		echo '<tr><td class="success">SUCCESS</td><td>GZIP Enabled</td><td></td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>GZIP Enabled</td><td>Cannot perform check, HTTP access not available.</td></tr>';
};

# Caching Headers (JS)

if (!empty($has_http_access)) {
	$response=getUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$HTTP_HOST.'/livewhale-readiness-test/file.js', true, false);
	if (!empty($GLOBALS['last_headers'])) {
		$has_caching=false;
		foreach($GLOBALS['last_headers'] as $val) {
			if (strpos($val, 'max-age=')!==false) {
				$has_caching=true;
				break;
			}
			else if (strpos($val, 'Expires: ')===0 && strtotime(substr($val, 9))>$_SERVER['REQUEST_TIME']) {
				$has_caching=true;
				break;
			};
		};
		if ($has_caching) {
			echo '<tr><td class="success">SUCCESS</td><td>Caching Headers (JS)</td><td></td></tr>';
		}
		else {
			echo '<tr><td class="failure">FAIL</td><td>Caching Headers (JS)</td><td>The web server is not setting proper caching headers for scripts. Please consult LiveWhale\'s <a href="https://livewhale.desk.com/customer/portal/articles/1620227-apache-mod_expires">server requirements</a> for example configuration settings.</td></tr>';
		};
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>Caching Headers (JS)</td><td>Cannot perform check, HTTP access not available.</td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>Caching Headers (JS)</td><td>Cannot perform check, HTTP access not available.</td></tr>';
};

# Caching Headers (CSS)

if (!empty($has_http_access)) {
	$response=getUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$HTTP_HOST.'/livewhale-readiness-test/file.css', true, false);
	if (!empty($GLOBALS['last_headers'])) {
		$has_caching=false;
		foreach($GLOBALS['last_headers'] as $val) {
			if (strpos($val, 'max-age=')!==false) {
				$has_caching=true;
				break;
			}
			else if (strpos($val, 'Expires: ')===0 && strtotime(substr($val, 9))>$_SERVER['REQUEST_TIME']) {
				$has_caching=true;
				break;
			};
		};
		if ($has_caching) {
			echo '<tr><td class="success">SUCCESS</td><td>Caching Headers (CSS)</td><td></td></tr>';
		}
		else {
			echo '<tr><td class="failure">FAIL</td><td>Caching Headers (CSS)</td><td>The web server is not setting proper caching headers for stylesheets. Please consult LiveWhale\'s <a href="https://livewhale.desk.com/customer/portal/articles/1620227-apache-mod_expires">server requirements</a> for example configuration settings.</td></tr>';
		};
	}
	else {
		echo '<tr><td class="failure">FAIL</td><td>Caching Headers (CSS)</td><td>Cannot perform check, HTTP access not available.</td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>Caching Headers (CSS)</td><td>Cannot perform check, HTTP access not available.</td></tr>';
};

# AllowOverride Enabled

if (!empty($has_http_access)) {
	$response=getUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$HTTP_HOST.'/livewhale-readiness-test/htaccess/index.html', true, false);
	if (empty($GLOBALS['last_code']) || strpos($response, '<div id="error"></div>')===false) {
		echo '<tr><td class="warning">WARNING</td><td>AllowOverride Enabled</td><td>The web server is not configured with AllowOverride All, so that per-dir .htaccess files can be utilized. (Integrated password protection and HTTP redirects support requires it.) If this setting is not to be expected, you should contact support@whitewhale.net for an alternate configuration setting which will allow installation to complete.</td></tr>';
	}
	else {
		echo '<tr><td class="success">SUCCESS</td><td>AllowOverride Enabled</td><td></td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>AllowOverride Enabled</td><td>Cannot perform check, HTTP access not available.</td></tr>';
};

# HTTPS/SSL Access

if (!empty($has_http_access)) {
	$response=getUrl('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$HTTP_HOST.'/livewhale-readiness-test/file.html', true, false);
	if (empty($GLOBALS['last_code']) || strpos($response, '<div id="foo"></div>')===false) {
		echo '<tr><td class="failure">FAIL</td><td>HTTPS/SSL Access</td><td>The web server is not able to make an HTTPS/SSL request to itself at '.$HTTP_HOST.'. This is strongly recommended for security reasons. If not enabled, LiveWhale will be configured in a sub-optimal security mode.</td></tr>';
	}
	else {
		echo '<tr><td class="success">SUCCESS</td><td>HTTPS/SSL Access</td><td></td></tr>';
	};
}
else {
	echo '<tr><td class="failure">FAIL</td><td>HTTPS/SSL Access</td><td>Cannot perform check, HTTP access not available.</td></tr>';
};

# Node Installed

$response=shell_exec('whereis node');
if (!empty($response) && strpos($response, 'node:')===0 && trim($response)!='node:') {
	echo '<tr><td class="success">SUCCESS</td><td>Node Installed</td><td></td></tr>';
	if ($node_version=shell_exec('node -v')) { // check that node is >= 10.x
		$matches=[];
		preg_match('~([0-9]+?\.[0-9]+?\.[0-9]+?)~', $node_version, $matches);
		if (!empty($matches[1])) {
			$node_version=$matches[1];
			if (version_compare($node_version, '10.0', '<')) {
				echo '<tr><td class="warning">WARNING</td><td>Node Installed</td><td>Command line "node" is installed. However, the installed node version on this server is '.$node_version.'. Version 10.0+ is required for full compatibility.</td></tr>';
			};
		};
	};
}
else {
	echo '<tr><td class="warning">WARNING</td><td>Node Installed</td><td>Command line "node" not installed. Node modules are used by the CMS to enable support for compilation (LESS, SASS, ES6, achecker, etc.) as well as for minifying resources and accessibility checking. Installing "node" along with "npm" and "npx" for these features is strongly recommended.</td></tr>';
};

# NPM Installed

$response=shell_exec('whereis npm');
if (!empty($response) && strpos($response, 'npm:')===0 && trim($response)!='npm:') {
	echo '<tr><td class="success">SUCCESS</td><td>NPM Installed</td><td></td></tr>';
}
else {
	echo '<tr><td class="warning">WARNING</td><td>NPM Installed</td><td>Command line "npm" not installed. Node modules are used by the CMS to enable support for compilation (LESS, SASS, ES6, achecker, etc.) as well as for minifying resources and accessibility checking. Installing "node" along with "npm" and "npx" for these features is strongly recommended.</td></tr>';
};

# NPX Installed

$response=shell_exec('whereis npx');
if (!empty($response) && strpos($response, 'npx:')===0 && trim($response)!='npx:') {
	echo '<tr><td class="success">SUCCESS</td><td>NPX Installed</td><td></td></tr>';
}
else {
	echo '<tr><td class="warning">WARNING</td><td>NPX Installed</td><td>Command line "npx" not installed. Node modules are used by the CMS to enable support for compilation (LESS, SASS, ES6, achecker, etc.) as well as for minifying resources and accessibility checking. Installing "node" along with "npm" and "npx" for these features is strongly recommended.</td></tr>';
};

# LDAP Installed

if (extension_loaded('ldap')) {
	echo '<tr><td class="success">SUCCESS</td><td>LDAP Installed</td><td></td></tr>';
}
else {
	echo '<tr><td class="warning">WARNING</td><td>LDAP Installed</td><td>The PHP exension for LDAP is not installed. This is only a problem if you plan to use LDAP as your login mode, in which case this extension is a requirement.</td></tr>';
};

# mod_rewrite Installed

if (!function_exists('apache_get_modules')) {
	echo '<tr><td class="warning">WARNING</td><td>mod_rewrite Installed</td><td>Cannot check for mod_rewrite support because Apache is not available.</td></tr>';
}
else if (in_array('mod_rewrite', apache_get_modules())) {
	echo '<tr><td class="success">SUCCESS</td><td>mod_rewrite Installed</td><td></td></tr>';
}
else {
	echo '<tr><td class="warning">WARNING</td><td>mod_rewrite Installed</td><td>The Apache mod_rewrite extension is not installed. Please enable it for certain built-in LiveWhale services.</td></tr>';
};

# Zend OPCache Installed/Compatible

$has_zend_opcache=@ini_get('opcache.enable');
if (!empty($has_zend_opcache)) {
	$freq=ini_get('opcache.revalidate_freq');
	if ((int)$freq!==0) {
		echo '<tr><td class="warning">WARNING</td><td>Zend OPCache configuration</td><td>Incompatible Zend OPCache setting. opcache.revalidate_freq must be set to 0. It is currently set to '.(int)$freq.'.</td></tr>';
	};
}
else {
	echo '<tr><td class="warning">WARNING</td><td>Zend OPCache Installed</td><td>The OPCache extension is not enabled. While not required, we recommend enabling this extension for significant performance improvements on your server. Be sure to set opcache.revalidate_freq = 0 in your php.ini for compatibility with LiveWhale.</td></tr>';
};

# Write Permissions

if (@is_writable($SFTP_PATH_WWW)) {
	echo '<tr><td class="failure">FAIL</td><td>Write Permissions</td><td>Apache can write to the document root. LiveWhale should be assigned an SFTP user with write permissions while Apache should run as a user without write permissions.</td></tr>';
}
else {
	echo '<tr><td class="success">SUCCESS</td><td>Write Permissions Secure</td><td></td></tr>';
};

?>
</table>

</body>
</html>