<?php

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';
ini_set('display_errors', 1);
error_reporting(-1);

if ($path=$_LW->getAccessLogPath()) {
	if ($res=shell_exec('grep "utm_" '.escapeshellarg($path))) {
		$matches=[];
		preg_match_all('~"(http[s]*://[^"]+?)"~', $res, $matches);
		if (!empty($matches[1])) {
			$query_strings=[];
			foreach($matches[1] as $url) {
				if ($query_string=@parse_url($url, PHP_URL_QUERY)) {
					$tmp=[];
					parse_str($query_string, $tmp);
					$query_string=implode(', ', array_keys($tmp));
					$query_strings[$query_string]=(isset($query_strings[$query_string]) ? $query_strings[$query_string]+1 : 1);
				};
			};
			arsort($query_strings);
			foreach($query_strings as $key=>$val) {
				echo '<strong>'.$val.':</strong> '.$key.'<br/><br/>';
			};
		};
	};
};

?>