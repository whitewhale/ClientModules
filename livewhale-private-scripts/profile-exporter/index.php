<?php

/*

This script is designed to export profiles for any profile type.

Instructions:

- Choose the profile type to export and click "Export".

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

if (empty($_LW->_POST['type'])) { // if a type has not been selected
	echo '<p>Choose a profile type to export.</p>';
	echo '<form method="post" action="'.$_LW->setFormatClean($_SERVER['PHP_SELF']).'"><select name="type"><option value=""></option>';
	foreach($_LW->dbo->query('select', 'livewhale_profiles_types.id, livewhale_profiles_types.title, livewhale_groups.fullname, (SELECT COUNT(livewhale_profiles.id) FROM livewhale_profiles WHERE livewhale_profiles.tid=livewhale_profiles_types.id) AS total', 'livewhale_profiles_types', false, 'livewhale_profiles_types.title ASC, livewhale_groups.fullname ASC')->leftJoin('livewhale_groups', 'livewhale_groups.id=livewhale_profiles_types.gid')->run() as $res2) {
		if (!empty($res2['total'])) {
			echo '<option value="'.(int)$res2['id'].'">'.$res2['title'].' ('.(!empty($res2['fullname']) ? $res2['fullname'] : 'global').') - '.(int)$res2['total'].' profile'.($res2['total']!=1 ? 's' : '').'</option>';
		};
	};
	echo '</select> <input type="submit" value="Export"/></form>';
}
else { // else if a type has been selected
	$_LW->xphp->disabled=true; // disable XPHP on output
	if ($profile_field_ids=$_LW->dbo->query('select', 'GROUP_CONCAT(id) AS ids', 'livewhale_profiles_types_fields', 'pid='.(int)$_LW->_POST['type'])->firstRow('ids')->run()) { // if profile field ids fetched
		$profile_field_ids=explode(',', $profile_field_ids);
		sort($profile_field_ids);
		$fields='livewhale_profiles.id, livewhale_profiles.gid, livewhale_profiles.firstname, livewhale_profiles.middlename, livewhale_profiles.lastname, livewhale_profiles.title, livewhale_profiles.description, livewhale_profiles.url, livewhale_profiles.contact_info, livewhale_profiles.username'; // set fields for columns
		foreach($profile_field_ids as $profile_field_id) {
			$fields.=', (SELECT livewhale_profiles_fields.value FROM livewhale_profiles_fields WHERE livewhale_profiles_fields.pid=livewhale_profiles.id AND livewhale_profiles_fields.fid='.(int)$profile_field_id.') AS profiles_'.(int)$profile_field_id;
		};
		header('Content-Type: application/octet-stream'); // send download headers
		header('Content-Disposition: attachment; filename="all-profiles-type-'.(int)$_LW->_POST['type'].'.csv"');
		if ($fp=fopen('php://output', 'w')) { // if CSV opens
			$header_row=['id', 'gid', 'firstname', 'middlename', 'lastname', 'title', 'description', 'url', 'contact_info', 'username'];
			foreach($profile_field_ids as $profile_field_id) {
				$header_row[]='profiles_'.(int)$profile_field_id;
			};
			fputcsv($fp, $header_row, ',', '"');
			foreach($_LW->dbo->query('select', $fields, 'livewhale_profiles', 'livewhale_profiles.tid='.(int)$_LW->_POST['type'].' AND NOT (livewhale_profiles.parent IS NOT NULL AND livewhale_profiles.url IS NOT NULL)')->run() as $res2) { // add all rows to the file
				fputcsv($fp, $res2, ',', '"');
			};
			fclose($fp);
		};
	}
	else { // else give error
		die('Could not fetch necessary information for this profile type.');
	};
};

?>