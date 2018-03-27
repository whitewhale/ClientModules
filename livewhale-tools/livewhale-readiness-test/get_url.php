<?php

function getUrl($arr, $response=true, $post=false, $curl_opts=false, $always_return=false) { // gets results from url
if (!is_array($arr)) { // always use an array
	$arr=array($arr);
};
$output=array(); // init array of output
$GLOBALS['first_code']=''; // reset first_code
$GLOBALS['last_code']=''; // reset last_code
$GLOBALS['last_error']=''; // reset last_error
if (!$response && !$curl_opts) { // if a server response isn't needed and not posting and doesn't have curl opts
	foreach($arr as $key=>$url) { // loop through urls
		if (!empty($url)) {
			$output[$key]=''; // init response
			$url=parse_url($url); // parse the supplied url
			if (empty($url['path'])) { // set default path if none given
				$url['path']='/';
			};
			$fp=@fsockopen(($url['scheme']=='https' ? 'ssl://' : '').$url['host'], (empty($url['port']) ? ($url['scheme']=='https' ? 443 : 80) : $url['port']), $errno, $errstr, 10); // attempt to open a socket
			if ($fp) { // if socket was opened
				if (!empty($post)) { // if POSTing, format POST string
					foreach($post as $key=>$val) {
						$post[$key]=$key.'='.rawurlencode($val);
					};
					$post=implode('&', $post);
				};
				fwrite($fp, (!empty($post) ? 'POST' : 'GET').' '.$url['path'].(!empty($url['query']) ? '?'.$url['query'] : '')." HTTP/1.1\r\nHost: ".$url['host']."\r\nUser-Agent: LiveWhale".(!empty($post) ? "\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($post) : '')."\r\nConnection: Close\r\n\r\n".(!empty($post) ? $post : '')); // request the url
				fclose($fp); // close socket
			};
		};
	};
}
else { // else if using curl
	$opts=array( // set base request config
		CURLOPT_RETURNTRANSFER=>1,
		CURLOPT_CONNECTTIMEOUT=>6,
		CURLOPT_TIMEOUT=>15,
		CURLOPT_USERAGENT=>'LiveWhale (http://www.livewhale.com/)',
		CURLOPT_SSL_VERIFYPEER=>0,
		CURLOPT_SSL_VERIFYHOST=>0,
		CURLOPT_HEADER=>1,
		CURLOPT_DNS_USE_GLOBAL_CACHE=>1,
		CURLOPT_DNS_CACHE_TIMEOUT=>300,
		CURLOPT_ENCODING=>1,
		CURLOPT_HTTPHEADER=>array('Accept-Encoding: gzip,deflate')
	);
	if (!empty($curl_opts)) { // set/override curl opts if specified
		foreach($curl_opts as $key=>$val) {
			$opts[$key]=$val;
		};
	};
	if (!empty($post)) { // if POSTing content, enable POST
		$opts[CURLOPT_POST]=1;
		$opts[CURLOPT_POSTFIELDS]=$post;
	};
	$ch=curl_init(); // init curl
	foreach($arr as $key=>$url) { // loop through urls
		if (!empty($url)) {
			if ($url[0]=='/') { // fix absolute urls
				$url='http://'.$_SERVER['HTTP_HOST'].$url;
			};
			$url_info=@parse_url($url); // get url info
			if (empty($url_info['host'])) { // bail if no host found
				return false;
			};
			if (preg_match('~http://.+?:.+?@~', $url)) { // if this is an authenticated request
				$matches=array();
				preg_match('~http://(.+?:.+?)@~', $url, $matches); // extract username and password
				$opts_auth=$opts; // init alternative opts array
				$opts_auth[CURLOPT_HTTPAUTH]=CURLAUTH_DIGEST; // add authentication options
				$opts_auth[CURLOPT_USERPWD]=$matches[1];
				curl_setopt_array($ch, $opts_auth); // use the alternate opts array
			}
			else { // else use the base opts array
				curl_setopt_array($ch, $opts);
			};
			curl_setopt($ch, CURLOPT_URL, $url); // set url
			$GLOBALS['last_url']=$url; // record last url
			$output[$key]=curl_exec($ch); // get output
			$is_head=(!empty($curl_opts) && !empty($curl_opts[CURLOPT_NOBODY])); // set flag if this is a HEAD request
			if (!$is_head) { // separate headers from output if not HEAD request
				if ($res=explode("\n\r", $output[$key])) {
					if (sizeof($res)>1) {
						if (sizeof($res)===3 && preg_match('~HTTP/1.[0-9] 100 Continue~', $res[0])) { // remove the HTTP continue if there is one
							array_shift($res);
						};
						$output[$key]=trim(implode("\n", array_slice($res, 1)));
						$GLOBALS['last_headers']=explode("\n", str_replace("\n\n", "\n", trim($res[0])));
					};
				};
			};
			$code=curl_getinfo($ch, CURLINFO_HTTP_CODE); // check status code
			$GLOBALS['first_code']=$code; // record first code
			$GLOBALS['last_code']=$code; // record last code
			if ($code===0) { // record last error
				$GLOBALS['last_error']=curl_error($ch);
			};
			$count=0; // init reattempt counter
			while (in_array($code, array(301, 302, 303, 307)) && $count<3) { // reattempt if redirect given
				curl_setopt($ch, CURLOPT_HEADER, !($code==302 && $count==2)); // set opt to request header
				$output[$key]=curl_exec($ch); // re-request url
				$matches=array(); // init array of matches
				preg_match('~Location: (.+?)\s+~', $output[$key], $matches); // match location
				if (!empty($matches[1])) { // if there was a match
					if (strpos($matches[1], '../')===0) { // fix relative urls
						$matches[1]=preg_replace('~/[^/]+?/\.\./~', '/', $url.'/'.$matches[1]);
					};
					if ($matches[1][0]=='/' && !empty($url_info['scheme']) && !empty($url_info['host'])) { // fix absolute urls
						$matches[1]=$url_info['scheme'].'://'.$url_info['host'].$matches[1];
					};
					curl_setopt($ch, CURLOPT_HEADER, false); // disable header request
					curl_setopt($ch, CURLOPT_URL, $matches[1]); // set opt to request url supplied via location
					$GLOBALS['last_url']=$matches[1]; // record last url
					$output[$key]=curl_exec($ch); // execute new request
					$code=curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
				};
				$count++; // increment counter
				if ($count==3 && $code==302 && !empty($output[$key])) { // force 200 if 302 returned content
					$code=200;
				};
				$GLOBALS['last_code']=$code; // record last code
				if ($code===0) { // record last error
					$GLOBALS['last_error']=curl_error($ch);
				};
			};
			if (empty($always_return) && substr($code, 0, 1)!=2) { // if not successful request, clear response, only if always_return isn't enabled
				//$output[$key]='';
			};
		};
	};
	curl_close($ch); // close curl
};
return $response ? (sizeof($output)>1 ? $output : current($output)) : ''; // return response(s)
}

?>