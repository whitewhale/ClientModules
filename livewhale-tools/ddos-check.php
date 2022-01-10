<html>
<head>
<style>
li {font-size:0.9em;}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
<?php

echo '<h2>DDoS Check</h2>';
if (is_dir('/var/log/apache2')) {
	$logs=[];
	foreach(glob('/var/log/apache2/*-access.log') as $log) {
		$log=basename($log);
		if ($log[0]!='.') {
			if ($contents=@file_get_contents('/var/log/apache2/'.$log)) {
				if ($lines=explode("\n", $contents)) {
					$lines=array_reverse($lines);
					foreach($lines as $line) {
						$matches=[];
						preg_match('~^(\S+) (\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) "([^"]*)" "([^"]*)"$~', $line, $matches);
						if (!empty($matches[1]) && $matches[1]!==$_SERVER['SERVER_ADDR']) {
							if ($ts=@strtotime(str_replace('/', '-', $matches[6].' '.$matches[5]))) {
								if ($ts>$_SERVER['REQUEST_TIME']-3600) {
									if (!isset($logs[$log])) {
										$logs[$log]=[];
									};
									if (!isset($logs[$log][$matches[1]])) {
										$logs[$log][$matches[1]]=[];
									};
									if (
										!in_array($matches[9], ['/livewhale/scheduler.php', '/robots.txt', '/live/env/private/refresh', '/live/env/public/refresh', '/live/sync_uploads', '/live/uptime', '/livewhale/api/', '/livewhale/backend.php?livewhale=session_info', '/livewhale/?login', '/live/places/maps_js', '/apple-touch-icon.png', '/apple-touch-icon-precomposed.png', '/live/payments/form', '/livewhale/', '/health-check']) && 
										strpos($matches[9], '/livewhale/backend.php?livewhale=exec')!==0 && 
										strpos($matches[9], '/livewhale/nocache.php?livewhale=exec')===false && 
										strpos($matches[9], '/livewhale/backend.php?livewhale=log_error')===false && 
										strpos($matches[9], 'smart_cache_response_code=1')===false && 
										strpos($matches[9], '/livewhale/theme/core/')!==0 && 
										strpos($matches[9], '/livewhale/thirdparty/')!==0 && 
										strpos($matches[9], '/_ingredients/')!==0 && 
										strpos($matches[9], '/live/resource/')!==0 && 
										strpos($matches[9], '/livewhale/?')!==0 && 
										strpos($matches[9], '/live/image/')!==0 && 
										strpos($matches[9], 'ddos-check.php')===false && 
										substr($matches[9], -4, 4)!=='.txt' && 
										substr($matches[9], -12, 12)!=='/favicon.ico'
									) {
										$logs[$log][$matches[1]][]=htmlspecialchars(rawurldecode($matches[9]));
									};
								};
							};
						};
					};
				};
			};
			if (!empty($logs[$log])) {
				foreach($logs[$log] as $key=>$val) {
					if (sizeof($val)<60 && sizeof(array_unique($val))<30) {
						unset($logs[$log][$key]);
					};
				};
			};
			if (empty($logs[$log])) {
				unset($logs[$log]);
			}
			else {
				uasort($logs[$log], function($a, $b) {
					$a=sizeof($a);
					$b=sizeof($b);
					return ($a==$b) ? 0 : ($a<$b ? 1 : -1);
				});
			};
		};
	};
	foreach($logs as $key=>$val) {
		echo '<h3>'.$key.'</h3>';
		foreach($val as $key2=>$val2) {
			$count=sizeof($val2);
			$val2=array_unique($val2);
			echo '<p><em>'.$key2.' ('.$count.' requests, '.sizeof($val2).' unique, <strong>'.number_format($count/3600, 2).' / sec. request rate</strong>)</em> <a href="#" class="toggle">(show)</a></p><ul style="display:none;"><li>'.implode('</li><li>', $val2).'</li></ul>';
		};
	};
};


?>
<script>
$('body').on('click', '.toggle', function() {
	if ($(this).text()=='(show)') {
		$(this).text('(hide)');
		$(this).parent().next('ul').show();
	}
	else {
		$(this).text('(show)');
		$(this).parent().next('ul').hide();
	};
	return false;
});
</script>
</body>
</html>