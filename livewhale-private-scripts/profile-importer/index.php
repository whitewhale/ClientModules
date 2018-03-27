<?php

/*

This script is designed to import profiles to any profile type.

Instructions:

- Export a profiles.csv that contains your profiles to import and store it in the same directory as this file. (See sample CSV.)
- Set key_field_id if you wish to update profiles by a custom field value rather than the core profile ID field.
- Leave the ID field blank for any profiles you wish to create rather than update.
- Run the script to validate all data, and then import.

*/

/* START SETTINGS */

$GLOBALS['key_field_id']=''; // the ID of the custom field to use as the unique identifier, if the profile ID field is left blank (optional, this allows you to update existing profiles via a custom field value rather than the core profile ID field) -- if both ID and this are left blank, then new profiles will be created

/* END SETTINGS */

/*

The following are all possible errors that can be triggered by a bad CSV file:

- CSV file doesn't exist
- No profiles found in CSV
- First row of the CSV wasn't a header row
- Could not determine profile type (from the header row's custom fields)
- Header row doesn't start with required fields (id, gid, firstname, middlename, lastname, title, description, url, contact_info, username)
- Header row doesn't contain any custom fields (profiles_1, etc.)
- Header row doesn't contain all required custom fields for the profile type
- Key field ID specified above but couldn't find that field in the profile type
- A value for the key field matched multiple profiles in the system
- A profile has incorrect column count
- A profile id or gid is non-numeric
- A profile gid does not correspond to any group in the system
- A profile doesn't provide name or title as needed by the profile type's style (person or thing)
- A profile custom text field contained line break
- A profile custom number field contained non-numeric character
- A profile custom radio button / checkbox / select field contained an invalid value
- A profile custom date field contained an invalid date
- A profile custom email field contained an invalid email address

*/

require $_SERVER['DOCUMENT_ROOT'].'/livewhale/nocache.php';

if (file_exists('./profiles.csv')) { // if the CSV exists
	if ($info=getProfilesFromCSV('./profiles.csv')) { // if profiles extracted from the CSV
		if (!empty($info['profile_type_id'])) { // if the profile type was determined
			if (!empty($info['profiles'])) { // if there were profiles found
				if (empty($GLOBALS['key_field_id']) || !empty($info['key_field_title'])) { // if the key field is blank or valid
					if (empty($_LW->_POST['confirmed'])) { // if the user did not yet confirm this import
						echo '<p>I will now import all profiles of type <strong>'.$_LW->setFormatClean($info['profile_type_title']).'</strong> into the specified groups, identifying existing profiles using '.(!empty($info['key_field_title']) ? 'the <strong>'.$_LW->setFormatClean($info['key_field_title']).'</strong> field' : 'the profile ID field').'. Shall I proceed? <form method="post" action="'.$_LW->setFormatClean($_SERVER['REQUEST_URI']).'"><input type="checkbox" value="1" checked="checked" name="dry_run"/> Do a dry run <input type="submit" name="confirmed" value="Do it!"/></form>'; // show confirmation form
					}
					else { // else if the user confirmed the import
						importProfiles($info); // import the profiles
					};
				}
				else { // else warn on invalid key field
					die('A key field was set, but could not be located in this profile type.');
				};
			}
			else { // else warn on empty CSV
				die('No profiles were found in the CSV.');
			};
		}
		else { // else warn on unknown profile type
			die('Could not determine which profile type to import to. Make sure all custom profile fields equally match a profile type in the system.');
		};
	};
}
else { // else warn on missing CSV
	die('Could not locate profiles.csv.');
};

function getProfilesFromCSV($path) { // extracts profiles from the CSV and validates all data
global $_LW;
set_time_limit(0); // disable resource limits
ini_set('display_errors', 1);
ini_set('auto_detect_line_endings', true); // detect CSV line endings
$output=array( // init required output
	'profile_type_id'=>false,
	'profile_type_title'=>false,
	'profiles'=>array(),
	'key_field_title'=>false,
	'required_columns'=>array(),
	'custom_ids'=>array()
);
if ($file=fopen($path, 'r')) { // if CSV opens
	$data=array();
	while (($item=fgetcsv($file, 0, ',', '"'))!==false) { // read all data from the CSV
		$data[]=$item;
	};
	fclose($file);
	$required_columns=array('id', 'gid', 'firstname', 'middlename', 'lastname', 'title', 'description', 'url', 'contact_info', 'username'); // init the leading columns that are required in the header row
	$required_custom_field_columns=array(); // init array of column indexes that correspond to custom fields that are required
	$custom_fields=array(); // init array of custom fields and their attributes
	$column_count=0; // init total column count
	$count=0; // init CSV row counter, so we can distinguish the header row
	$header=array(); // init list of all header columns
	$output['required_columns']=$required_columns; // add the required header columns to the output
	$gids=array(); // init array of gids, for existance-checking
	foreach($data as $item) { // for each CSV row
		if (empty($count)) { // if this is the header row
			foreach($item as $key=>$val) { // standardize the column case
				$item[$key]=strtolower($val);
			};
			$header=$item; // record the full list of header columns
			$column_count=sizeof($item); // record the column count
			$core_columns=array_slice($item, 0, sizeof($required_columns)); // get the core profile columns
			$custom_columns=array_slice($item, sizeof($required_columns)); // get the custom field columns
			if ($core_columns!=$required_columns || empty($custom_columns)) { // warn if missing any core columns or if there are no custom columns
				die('The first row of your CSV must specify the columns ('.implode(', ', $required_columns).') and then be followed by one or more custom profile fields (ex: profiles_1, profiles_2, etc.)');
			};
			foreach($custom_columns as $val) { // warn if the custom columns are not named properly
				if (!preg_match('~^profiles_[0-9]+$~', $val)) {
					die('The first row of your CSV must specify the columns ('.implode(', ', $required_columns).') and then be followed by one or more custom profile fields (ex: profiles_1, profiles_2, etc.)');
				};
			};
			if (!empty($GLOBALS['key_field_id'])) { // get the column that corresponds to the key field
				$key_field_column=array_search('profiles_'.$GLOBALS['key_field_id'], $item);
			};
			foreach($custom_columns as $val) { // for each custom column
				$custom_field_id=substr($val, 9); // get the field ID
				$output['custom_ids'][]=$custom_field_id; // record it for output
				if (empty($output['profile_type_id'])) { // if we haven't detected the profile type yet
					if ($profile_type_id=$_LW->dbo->query('select', 'pid', 'livewhale_profiles_types_fields', 'id='.(int)$custom_field_id)->firstRow('pid')->run()) { // find it
						$output['profile_type_id']=$profile_type_id; // record it for output
					}
					else { // else bail if it could not be found
						break;
					};
				}
				else { // else if we haven't detected the profile type
					$profile_type_id=$_LW->dbo->query('select', 'pid', 'livewhale_profiles_types_fields', 'id='.(int)$custom_field_id)->firstRow('pid')->run(); // get the profile type ID based on each additional custom field
					if (empty($profile_type_id) || $profile_type_id!=$output['profile_type_id']) { // if it didn't match the profile type ID based on the first custom field
						$output['profile_type_id']=''; // blank the profile type ID so we can warn later
						break;
					};
				};
			};
			if (!empty($output['profile_type_id'])) { // if the profile type was detected
				if (empty($custom_fields)) { // if we haven't yet obtained the info for the custom fields of this type
					foreach($_LW->dbo->query('select', 'id, title, is_required, type, field_options', 'livewhale_profiles_types_fields', 'pid='.(int)$profile_type_id)->run() as $custom_field) { // fetch them
						if (!empty($custom_field['field_options'])) { // format any field options
							$custom_field['field_options']=explode("\n", $custom_field['field_options']);
							foreach($custom_field['field_options'] as $key=>$val) {
								$custom_field['field_options'][$key]=trim($val);
							};
						};
						$custom_fields[$custom_field['id']]=$custom_field; // and record them
					};
					if (!empty($GLOBALS['key_field_id']) && empty($output['key_field_title'])) { // if we haven't yet obtained the key field title
						if (isset($custom_fields[$GLOBALS['key_field_id']])) { // if the key field was found among the custom fields
							$output['key_field_title']=$custom_fields[$GLOBALS['key_field_id']]['title']; // record the key field title
						};
					};
				};
				if (empty($output['profile_type_title'])) { // if we haven't yet obtained the profile type title
					$output['profile_type_title']=$_LW->dbo->query('select', 'title', 'livewhale_profiles_types', 'id='.(int)$profile_type_id)->firstRow('title')->run(); // record the profile type title for output
					$output['profile_type_style']=$_LW->dbo->query('select', 'style', 'livewhale_profiles_types', 'id='.(int)$profile_type_id)->firstRow('style')->run(); // record the profile type style for output
				};
				foreach($custom_fields as $custom_field) { // for each custom field
					if (!empty($custom_field['is_required'])) { // if the custom field is required
						if (!in_array('profiles_'.$custom_field['id'], $item)) { // warn if not present in this header row
							die('A column was not provided for required profile field '.$_LW->setFormatClean($custom_field['title']).'.');
						}
						else { // else if required custom field is present in the header row
							$required_custom_field_columns[]=array('title'=>$custom_field['title'], 'index'=>array_search('profiles_'.$custom_field['id'], $item)); // record the required custom field column so we can ensure that the subsequent profiles satisfy those requirements
						};
					};
				};
			};
		}
		else { // else if this is a profile row
			if (sizeof($item)!=$column_count) { // warn if the profile column count doesn't match the header column count
				die('Profiles are expected to have '.$column_count.' columns. A profile with '.sizeof($item).' columns was encountered.');
			};
			if (!empty($output['profile_type_id'])) { // if the profile type was determined
				foreach($item as $key=>$val) { // sanitize all the profile data
					$item[$key]=$_LW->setFormatSanitize(trim($val));
				};
				if (!empty($item[0]) && !preg_match('~[0-9]+$~', $item[0])) { // warn if profile ID is set but not numeric
					die('All profile IDs must be numeric.');
				};
				if (empty($item[1]) || !preg_match('~^[0-9]+$~', $item[1])) { // warn if profile gid is not set, or not numeric
					die('A numeric group ID must always be set for profiles being imported.');
				};
				if (!isset($gids[$item[1]])) { // warn if profile gid is set but doesn't correspond to any groups in the system
					if ($_LW->dbo->query('select', '1', 'livewhale_groups', 'id='.(int)$item[1])->firstRow()->run()) {
						$gids[$item[1]]='';
					}
					else {
						die('Group ID '.(int)$item[1].' was specified but there is no group with that ID.');
					};
				};
				if ($output['profile_type_style']==1) { // warn if either firstname/lastname or title not set, according to style of the profile type
					if (empty($item[2]) || empty($item[4])) {
						die('A firstname and lastname must always be set for profiles of this type.');
					};
				}
				else {
					if (empty($item[5])) {
						die('A title must always be set for profiles of this type.');
					};
				};
				if (!empty($required_custom_field_columns)) { // warn if any of the required custom fields don't have values in this profile
					foreach($required_custom_field_columns as $required_column) {
						if (empty($item[$required_column['index']])) {
							die('All required fields for this profile type are not satisfied by the CSV data. Empty value encountered for the '.$_LW->setFormatClean($required_column['title']).' field.');
						};
					};
				};
				foreach($custom_fields as $custom_field) { // for each custom field
					if ($index=array_search('profiles_'.$custom_field['id'], $header)) { // find the column index for it
						if (!empty($item[$index])) {
							switch($custom_field['type']) { // validate according to the custom field type
								case 'text': // warn if text field is multi-line
									if (preg_match('~[\n\r]~', $item[$index])) {
										die('The field '.$_LW->setFormatClean($custom_field['title']).' is a text field and must not include any line breaks.');
									};
									break;
								case 'textarea': // do nothing for textareas
								case 'textarea_long':
									break;
								case 'number': // warn if number field is non-numeric
									if (!preg_match('~^[0-9\.]+$~', $item[$index])) {
										die('The field '.$_LW->setFormatClean($custom_field['title']).' is a number field and must not include any non-numeric characters.');
									};
									break;
								case 'radio_button': // warn if value doesn't exist among possible values for a multi-value field
								case 'checkbox':
								case 'select_menu':
									if (strpos($item[$index], '|')!==false) {
										foreach(explode('|', $item[$index]) as $sub) {
											if (!in_array($sub, $custom_field['field_options'])) {
												die('The field '.$_LW->setFormatClean($custom_field['title']).' has a fixed set of possible values, but encountered invalid value in CSV data.');
											};
										};
									}
									else if (!in_array($item[$index], $custom_field['field_options'])) {
										die('The field '.$_LW->setFormatClean($custom_field['title']).' has a fixed set of possible values, but encountered invalid value in CSV data.');
									};
									break;
								case 'date': // warn if date field doesn't parse
								case 'date_time':
									if (!@strtotime($item[$index])) {
										die('The field '.$_LW->setFormatClean($custom_field['title']).' is a date/time field, but an invalid date was encountered.');
									};
									break;
								case 'email': // warn if email field doesn't parse
									if (!$_LW->isValidEmail($item[$index])) {
										die('The field '.$_LW->setFormatClean($custom_field['title']).' is an email field, but an invalid email address was encountered.');
									};
									break;
							};
						};
					};
					
				};
				if (empty($item[0]) && !empty($GLOBALS['key_field_id']) && !empty($key_field_column)) { // if a profile ID isn't set, and we can obtain one from a key field
					$key_field_value=$item[$key_field_column]; // get the value of the key field for this profile
					if (!empty($key_field_value)) { // if there is a value (otherwise it's a new profile)
						$matching_profiles=$_LW->dbo->query('select', 'pid', 'livewhale_profiles_fields', 'fid='.(int)$GLOBALS['key_field_id'].' AND value='.$_LW->escape($key_field_value).' AND NOT EXISTS(SELECT 1 FROM livewhale_profiles WHERE livewhale_profiles.id=livewhale_profiles_fields.pid AND livewhale_profiles.parent IS NOT NULL AND livewhale_profiles.url IS NOT NULL)')->allRows()->run(); // find all profiles matching on the key field
						if (sizeof($matching_profiles)>1) { // warn if more than one profile matched (key field value is expected to be unique)
							die('The key field is expected to have unique values, but found '.sizeof($matching_profiles).' matching profiles for the value of '.$_LW->setFormatClean($key_field_value).'.');
						}
						else if (!empty($matching_profiles)) { // else if there was a single match
							$item[0]=$matching_profiles[0]['pid']; // set the ID of the profile being imported to the ID of the profile with this key field value
						};
					};
				};
				$output['profiles'][]=$item; // record this profile for importing
			};
		};
		$count++; // increment the CSV row count
	};
};
return $output;
}

function importProfiles($info) { // imports the profiles
global $_LW;
$status=array('created'=>0, 'updated'=>0); // init return status
$required_count=sizeof($info['required_columns']); // get the count of required core profile fields
foreach($info['profiles'] as $profile) { // for each profile
	$data=array(); // init array of import data
	$data_original=array(); // and array of unescaped data for display
	foreach($info['required_columns'] as $key=>$val) { // format and add required core profile fields
		$data[$val]=$_LW->escape($profile[$key]);
		$data_original[$val]=$profile[$key];
	};
	$data['tid']=(int)$info['profile_type_id']; // set the profile type id
	$data['last_user']=(int)$_SESSION['livewhale']['manage']['uid']; // set the last user
	$data['last_modified']='NOW()'; // set the modified time
	if ($data['id']=='NULL') { // if this is a new profile, set additional fields
		$data['created_by']=(int)$_SESSION['livewhale']['manage']['uid'];
		$data['date_created']='NOW()';
		$data['status']=2;
	};
	if (!empty($_LW->_POST['dry_run'])) { // if doing a dry run
		if (empty($profile[0])) { // if creating the profile
			echo '&#8226; Will create new profile for '.$_LW->setFormatClean(str_replace("'", '', !empty($data_original['firstname']) ? $data_original['firstname'].' '.$data_original['lastname'] : $data_original['title'])).'.<br/>'; // show creation msg
		}
		else { // else if updating the profile
			$name_or_title=$_LW->dbo->query('select', 'IF(lastname IS NOT NULL, CONCAT(firstname, " ", lastname), title) AS name_or_title', 'livewhale_profiles', 'id='.(int)$profile[0])->firstRow('name_or_title')->run(); // get the name/title of the profile that would be updated
			echo '&#8226; Will update profile ID '.(int)$profile[0].' ('.$name_or_title.') with CSV data for '.$_LW->setFormatClean(str_replace("'", '', !empty($data_original['firstname']) ? $data_original['firstname'].' '.$data_original['lastname'] : $data_original['title'])).'.<br/>'; // show update msg
		};
		echo '<br/><a href="'.$_LW->setFormatClean($_SERVER['REQUEST_URI']).'">Go back</a>';
	}
	else { // else if not doing a dry run
		if (empty($profile[0])) { // if creating the profile
			$_LW->dbo->sql('INSERT INTO livewhale_profiles ('.implode(', ', array_keys($data)).') VALUES('.implode(', ', $data).');'); // create it
			if ($profile[0]=$_LW->dbo->lastInsertID()) { // get the new profile ID
				$status['created']++; // flag as created
			};
		}
		else { // else if updating the profile
			$updates=array();
			foreach($data as $key=>$val) { // generate update syntax
				$updates[]=$key.'='.$val;
			};
			$_LW->dbo->sql('UPDATE livewhale_profiles SET '.implode(', ', $updates).' WHERE id='.(int)$profile[0].';'); // update it
			$status['updated']++; // flag as updated
		};
		if (!empty($profile[0])) { // if there is a valid profile that was created/updated
			foreach(array_slice($profile, $required_count) as $key=>$val) { // for each custom field
				$profile_field_id=$_LW->dbo->query('select', 'id', 'livewhale_profiles_fields', 'pid='.(int)$profile[0].' AND fid='.(int)$info['custom_ids'][$key])->firstRow('id')->run(); // get the ID of a custom field that would be updated
				if (empty($profile_field_id)) { // if creating the custom field
					$_LW->dbo->sql('INSERT INTO livewhale_profiles_fields (id, pid, fid, value) VALUES(NULL, '.(int)$profile[0].', '.(int)$info['custom_ids'][$key].', '.$_LW->escape($val).');'); // create it
				}
				else { // else if updating the custom field
					$_LW->dbo->sql('UPDATE livewhale_profiles_fields SET value='.$_LW->escape($val).' WHERE id='.(int)$profile_field_id.';'); // update it
				};
			};
			$_LW->d_search->indexItem('profiles', $profile[0]); // index the item for the search engine
		};
	};
};
if (empty($_LW->_POST['dry_run'])) { // if not doing a dry run
	echo '<br/>'.$status['created'].' profile'.($status['created']!=1 ? 's' : '').' created, '.$status['updated'].' profile'.($status['updated']!=1 ? 's' : '').' updated.<br/>'; // show final import status
	echo '<br/><a href="'.$_LW->setFormatClean($_SERVER['REQUEST_URI']).'">Go back</a>';
};
};

?>