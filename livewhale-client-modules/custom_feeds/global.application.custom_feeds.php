<?php

$_LW->REGISTERED_APPS['custom_feeds']=[
	'title'=>'Custom Feeds',
	'handlers'=>['onGetFeedDataFetch'],
	'flags'=>['no_autoload']
]; // configure this module

class LiveWhaleApplicationCustomFeeds {

public function onGetFeedDataFilter($buffer) { // translation for custom feed data formats
global $_LW;
$host=strtolower(@parse_url($buffer['url'], PHP_URL_HOST));
if ($host=='my.host.com') { // only apply to feeds from a particular host (optional)
	if (!empty($buffer['items']['default'])) { // if there are feed items
		foreach($buffer['items']['default'] as $key=>$val) { // for each feed item
			$buffer['items']['default'][$key]['link']='http://xxx'; // customize the link, for example
		};
	};
};
return $buffer;
}

public function onGetFeedDataFetch($url, $max_refresh_time, $buffer) { // translation for custom feed data formats (such as a proprietary JSON feed)
global $_LW;
if (strpos($url, '//my.host.com/')!==false && strpos($url, '/wp-json/')!==false) { // if this is a /wp-json/ feed on my.host.com (for example)
	if ($json=$_LW->getUrl($url, true, false, [CURLOPT_CONNECTTIMEOUT=>$max_refresh_time, CURLOPT_TIMEOUT=>($max_refresh_time+9)])) { // get feed
		if ($json=@json_decode($json, true)) { // decode feed
			if (isset($json[0]['date_gmt'])) { // if in the expected format (i.e. we have an expected date_gmt element, for example)
				$buffer=[ // create JSON feed
					'version'=>'https://jsonfeed.org/version/1',
					'title'=>$url,
					'feed_url'=>$url,
					'items'=>$json
				];
				foreach($buffer['items'] as $key=>$val) { // reformat items
					if (@$val['status']=='publish') { // if conditions are met, such as the status is "publish" (for example)
						$start=(!empty($val['acf']['start_time']) ? $val['acf']['start_time'].' ' : '').@$val['acf']['date_start']; // set the various fields we require for this feed
						$end=(!empty($val['acf']['end_time']) ? $val['acf']['end_time'].' ' : '').@$val['acf']['date_end'];
						if (!empty($start)) {
							$start=$_LW->toDate('c', $_LW->toTS($start, 'America/New_York'), 'UTC');
						};
						if (!empty($end)) {
							$end=$_LW->toDate('c', $_LW->toTS($end, 'America/New_York'), 'UTC');
						};
						if (!empty($start) && !empty($val['guid']['rendered']) && !empty($val['title']['rendered'])) { // if we have these values at least
							$buffer['items'][$key]=[ // reformat and preserve the feed item
								'id'=>$val['guid']['rendered'],
								'url'=>@$val['link'],
								'title'=>$val['title']['rendered'],
								'content_html'=>@$val['content']['rendered'],
								'_date_start'=>@$start,
								'_date_end'=>@$end
							];
						}
						else { // else remove the feed item
							unset($buffer['items'][$key]);
						};
					}
					else { // else remove the feed item
						unset($buffer['items'][$key]);
					};
				};
				return json_encode($buffer); // return the reformatted feed
			};
		};
	};
	return ''; // else return empty feed
};
return $buffer;
}

}

?>