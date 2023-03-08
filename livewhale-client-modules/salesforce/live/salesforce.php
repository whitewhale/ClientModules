<?php

// LiveURL plugin for Salesforce-to-iCAL requests.

require $LIVE_URL['DIR'].'/cache.livewhale.php'; // load LiveWhale
$output='';
if (!empty($LIVE_URL['REQUEST'])) { // if params supplied
	if ($tmp=@parse_url($LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1], PHP_URL_PATH)) {
		if (!empty($tmp) && $tmp!=$LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1]) {
			$LIVE_URL['REQUEST'][sizeof($LIVE_URL['REQUEST'])-1]=$tmp;
		};
	};
	$type=array_shift($LIVE_URL['REQUEST']);
	if (!empty($type) && !empty($_LW->CONFIG['SALESFORCE']['OBJECTS'][$type])) { // if valid type
		$count=0;
		$params=[];
		if (!empty($LIVE_URL['REQUEST'])) {
			foreach($LIVE_URL['REQUEST'] as $val) { // convert request elements to args
				$val=str_replace('\\', '/', rawurldecode($val));
				if (!$count) {
					$key=$val;
				}
				else {
					if (!isset($params[$key])) {
						$params[$key]=$_LW->setFormatClean($val);
					}
					else {
						if (!is_array($params[$key])) {
							$params[$key]=[$params[$key]];
						};
						$params[$key][]=$_LW->setFormatClean($val);
					};
				};
				$count=$count ? 0 : 1;
			};
		};
		$_LW->initModule('application', 'salesforce'); // init Salesforce module
		if (empty($params['start_date'])) { // set default start date
			$params['start_date']=$_LW->toDate('Y-m-d', $_LW->toTS('-1 year'));
		};
		if (empty($params['end_date'])) { // set default end date
			$params['end_date']=$_LW->toDate('Y-m-d', $_LW->toTS('+1 year'));
		};
		if (!empty($params['start_date']) && !empty($params['end_date']) && $_LW->toTS($params['start_date'])!==false && $_LW->toTS($params['end_date'])!==false) { // if required start and end dates are valid
			if ($_LW->a_salesforce->initSalesforce()) { // if Salesforce loaded
				$ical=$_LW->a_salesforce->getEventsAsICAL($type, $params); // fetch and format events as ICAL feed
				if ($ical===false) {
					die($_LW->httpResponse(404, true));
				}
				else {
					$output=$ical;
				};
			}
			else {
				die($_LW->httpResponse(404, true));
			};
		}
		else {
			header('X-Salesforce-Error: Invalid params');
			die($_LW->httpResponse(404, true));
		};
	}
	else {
		header('X-Salesforce-Error: Invalid type '.rawurlencode($type));
		die($_LW->httpResponse(404, true));
	};
}
else {
	header('X-Salesforce-Error: Type and parameters are required');
	die($_LW->httpResponse(404, true));
};
header('Content-Type: text/calendar'); // send content encoding header
die($output); // show iCAL

?>