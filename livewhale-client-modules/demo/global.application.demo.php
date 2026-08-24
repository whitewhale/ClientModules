<?php

$_LW->REGISTERED_APPS['demo'] = [
	'title'    => 'Demo',
	'handlers' => ['onLoad'],
	'custom'   => [
		'group_name'  => 'Sample Content',
		'weeks_back'  => 4, // keep sample events populated starting this many weeks in the past
		'weeks_ahead' => 8, // populate sample events out to this many weeks in the future
	]
];

class LiveWhaleApplicationDemo {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function getGroupId() { // returns the GID for "Sample Content", or false if not found
		global $_LW;
		return $_LW->dbo->query('select', 'id', 'livewhale_groups', 'fullname="Sample Content"')->firstRow('id')->run();
	}


	// -------------------------------------------------------------------------
	// Install
	// -------------------------------------------------------------------------

	public function onLoad() {
		global $_LW;

		// Create "Sample Content" group if it doesn't already exist
		if (!$this->getGroupId()) {
			$_LW->create('groups', [
				'fullname'        => 'Sample Content',
				'fullname_public' => 'Sample Content'
			]);

			// Register weekly cron job
			$_LW->dbo->sql('REPLACE INTO livewhale_scheduler VALUES("demo_weekly", "demo_weekly", NOW(), 604800, "public");');
		};

	}


	// -------------------------------------------------------------------------
	// Populate
	// -------------------------------------------------------------------------

	public function populateEvents() { // wipes and regenerates the rolling window of sample events from sample_events.csv
		global $_LW;

		$_LW->logDebug('Demo: populateEvents() running');

		$created = 0;
		$failed = [];

		$demo_gid = $this->getGroupId();
		if (!$demo_gid) {
			$_LW->logDebug('Demo: populateEvents() could not find "Sample Content" group');
			return ['created' => 0, 'failed' => ['Could not find "Sample Content" group']];
		};

		// Read sample_events.csv into rows keyed by header. Built from INCLUDES_DIR_PATH rather than __DIR__,
		// since __DIR__ reflects wherever this file actually runs from, which doesn't always mirror the
		// repo's client/modules/demo layout — this is the same path the core code itself uses to find a
		// client module's directory (see class.resources.php).
		$module_dir = $_LW->INCLUDES_DIR_PATH.'/client/modules/demo';
		$csv_path = $module_dir.'/data/sample_events.csv';
		$rows = [];
		if (($fp = @fopen($csv_path, 'r')) !== false) {
			$header = fgetcsv($fp);
			while (($line = fgetcsv($fp)) !== false) {
				$rows[] = array_combine($header, $line);
			};
			fclose($fp);
		}
		else {
			$_LW->logDebug('Demo: populateEvents() could not open '.$csv_path.' (exists: '.(file_exists($csv_path) ? 'yes' : 'no').', readable: '.(is_readable($csv_path) ? 'yes' : 'no').')');
			return ['created' => 0, 'failed' => ['Could not open '.$csv_path.' — exists: '.(file_exists($csv_path) ? 'yes' : 'no').', readable: '.(is_readable($csv_path) ? 'yes' : 'no')]];
		};
		if (empty($rows)) {
			$_LW->logDebug('Demo: populateEvents() found no rows in '.$csv_path);
			return ['created' => 0, 'failed' => ['Found no rows in '.$csv_path]];
		};

		// Wipe only events this module created (flagged via the "demo_generated" custom field), never a
		// hand-made event a person happens to have filed under the Sample Content group. Skip the trash
		// since these get regenerated every cycle and don't need to be recoverable.
		$to_wipe = $_LW->dbo->query('select', 'pid', 'livewhale_custom_data', 'type="events" AND name="demo_generated"')->run();
		$_LW->logDebug('Demo: populateEvents() wiping '.sizeof($to_wipe).' previously-generated event(s)'); // logged up front so a slow wipe shows progress instead of going silent between this and the next log line
		foreach ($to_wipe as $res2) {
			$_LW->delete('events', $res2['pid'], false);
		};
		$_LW->logDebug('Demo: populateEvents() wipe complete, regenerating from CSV');

		// Cycle anchor: earliest start_date in the CSV, floored to that week's Monday
		$earliest_ts = false;
		foreach ($rows as $row) {
			if (($ts = strtotime($row['start_date'])) !== false && ($earliest_ts === false || $ts < $earliest_ts)) {
				$earliest_ts = $ts;
			};
		};
		if ($earliest_ts === false) {
			$_LW->logDebug('Demo: populateEvents() could not parse any start_date in sample_events.csv');
			return ['created' => 0, 'failed' => ['Could not parse any start_date in sample_events.csv']];
		};
		$cycle_start = strtotime('monday this week', $earliest_ts);

		// Cycle length: span from cycle_start through the latest date used, rounded up to a full week
		$latest_ts = $cycle_start;
		foreach ($rows as $row) {
			foreach (['start_date', 'end_date'] as $key) {
				if (!empty($row[$key]) && ($ts = strtotime($row[$key])) !== false && $ts > $latest_ts) {
					$latest_ts = $ts;
				};
			};
		};
		$cycle_seconds = ceil((($latest_ts - $cycle_start) / 86400 + 1) / 7) * 7 * 86400;

		// Window to populate: N weeks back through N weeks ahead, aligned to full weeks
		$monday_this_week = strtotime('monday this week');
		$weeks_back  = $_LW->REGISTERED_APPS['demo']['custom']['weeks_back'];
		$weeks_ahead = $_LW->REGISTERED_APPS['demo']['custom']['weeks_ahead'];
		$window_start = strtotime('-'.$weeks_back.' weeks', $monday_this_week);
		$window_end   = strtotime('+'.($weeks_ahead + 1).' weeks', $monday_this_week) - 1;

		// Map of event category title => id, extended with any missing categories found in the CSV
		$category_map = [];
		foreach ($_LW->dbo->query('select', 'id, title', 'livewhale_events_categories')->run() as $res2) {
			$category_map[$res2['title']] = $res2['id'];
		};
		foreach ($rows as $row) {
			if (empty($row['categories'])) continue;
			foreach (explode('|', $row['categories']) as $category_title) {
				if (!isset($category_map[$category_title])) {
					$category_map[$category_title] = $_LW->create('events_category', [
						'type'       => 1,
						'title'      => $category_title,
						'gid'        => '',
						'is_starred' => 1
					]);
				};
			};
		};

		// Map of source image filename => already-uploaded image id (flagged via the "demo_source_image" custom
		// field), so a regeneration reuses the same library image instead of uploading a fresh duplicate each time
		$image_id_map = [];
		foreach ($_LW->dbo->query('select', 'livewhale_custom_data.value, livewhale_custom_data.pid', 'livewhale_custom_data', 'livewhale_custom_data.type="images" AND livewhale_custom_data.name="demo_source_image"')
			->innerJoin('livewhale_images', 'livewhale_images.id=livewhale_custom_data.pid')
			->run() as $res2) {
			$image_id_map[$res2['value']] = $res2['pid'];
		};

		// A handful of the CSV's recurring location strings get a Saved Location (map pin) attached, so events at
		// that location show a built-in map. The coordinates below are illustrative placeholders, not tied to any
		// real address for these venue names -- swap them for real coordinates if that ever matters for a demo.
		// Guarded behind hasTable() since the Places feature/table isn't present on every install; skip quietly
		// (falling back to plain-text locations, same as before) rather than fatal the whole regeneration.
		$location_presets = $_LW->dbo->hasTable('livewhale_places') ? [
			'Fieldhouse Arena'            => [39.1682, -86.5230],
			'Aquatics Center'              => [39.1690, -86.5225],
			'Main Quad'                    => [39.1675, -86.5240],
			'Student Union Ballroom'       => [39.1678, -86.5235],
			'Intramural Fields'            => [39.1660, -86.5210],
			'Wellness Center Studio'       => [39.1685, -86.5245],
			'Alumni Center'                => [39.1670, -86.5255],
			'Tennis Complex'               => [39.1655, -86.5200],
			'Recital Hall'                 => [39.1680, -86.5250],
			'Ice Arena'                    => [39.1695, -86.5215],
			'Career Services Suite'        => [39.1672, -86.5238],
			'Main Auditorium'              => [39.1677, -86.5233],
			'Community Garden'             => [39.1650, -86.5260],
			'The Underground Coffeehouse'  => [39.1679, -86.5237],
			'Track & Field Complex'        => [39.1658, -86.5205],
		] : [];

		// Map of Saved Location title => existing preset place id, scoped to the Sample Content group, so a
		// regeneration reuses the same pin instead of creating a fresh preset for each cycle. Wrapped in a
		// try/catch (rather than just the hasTable() guard above) so that if this table/column shape turns out
		// to differ from what we assumed, we get a logged, catchable error instead of a silent hard crash that
		// takes down the whole regeneration -- PHP 7+ errors (e.g. calling an unexpected method, a DB layer
		// throwing on bad SQL) are Throwable and catchable, unlike older true fatals.
		$location_map = [];
		if (!empty($location_presets)) {
			try {
				foreach ($_LW->dbo->query('select', 'id, title', 'livewhale_places', 'gid='.(int)$demo_gid.' AND is_preset IS NOT NULL AND title IN ('.implode(', ', array_map([$_LW, 'escape'], array_keys($location_presets))).')')->run() as $res2) {
					$location_map[$res2['title']] = $res2['id'];
				};
			}
			catch (\Throwable $e) {
				$_LW->logDebug('Demo: populateEvents() could not query livewhale_places, disabling Saved Locations for this run — '.$e->getMessage());
				$location_presets = []; // disable the feature for the rest of this run rather than retrying per-row
			};
		};

		// Tile the cycle back before the window, then forward across it
		$tile_start = $cycle_start;
		while ($tile_start > $window_start) {
			$tile_start -= $cycle_seconds;
		};
		while ($tile_start <= $window_end) {

			foreach ($rows as $row) {

				$row_start_ts = strtotime($row['start_date'].(!empty($row['start_time']) ? ' '.$row['start_time'] : ''));
				if ($row_start_ts === false) continue;
				$day_offset  = strtotime($row['start_date']) - $cycle_start;
				$time_of_day = $row_start_ts - strtotime($row['start_date']);
				$new_start_ts = $tile_start + $day_offset + $time_of_day;

				if ($new_start_ts < $window_start || $new_start_ts > $window_end) continue; // outside window

				$new_end_ts = false;
				if (!empty($row['end_date'])) {
					$row_end_ts = strtotime($row['end_date'].(!empty($row['end_time']) ? ' '.$row['end_time'] : ''));
					$new_end_ts = $new_start_ts + ($row_end_ts - $row_start_ts);
				};

				$data = [
					'gid'                 => $demo_gid,
					'status'              => (!empty($row['status']) ? (int)$row['status'] : 1), // create()/save() doesn't default this outside the editor POST path; CSV can set 2 (hidden) to demo that state
					'timezone'            => (!empty($_LW->CONFIG['TIMEZONE']) ? $_LW->CONFIG['TIMEZONE'] : date_default_timezone_get()), // onValidateEvents() only backfills this on updates, not creates
					'is_all_day'          => (!empty($row['is_all_day']) ? 1 : ''), // part of the same interdependent-field set as date/date2/date_time/date2_time/timezone; must always be present
					'title'               => $row['title'],
					'date'                => date('m/d/Y', $new_start_ts),
					'date_time'           => ((empty($row['is_all_day']) && !empty($row['start_time'])) ? date('H:i', $new_start_ts) : ''),
					'date2'               => ($new_end_ts ? date('m/d/Y', $new_end_ts) : ''),
					'date2_time'          => ((empty($row['is_all_day']) && !empty($row['end_time']) && $new_end_ts) ? date('H:i', $new_end_ts) : ''),
					'summary'             => $row['summary'],
					'description'         => $row['description'],
					'location'            => $row['location'],
					'is_starred'          => $row['is_starred'],
					'is_online'           => $row['is_online'],
					'online_type'         => $row['online_type'],
					'online_url'          => $row['online_url'],
					'online_button_label' => $row['online_button_label'],
					'online_instructions' => $row['online_instructions'],
					'cost'                => $row['cost'],
					'cost_type'           => (!empty($row['cost']) ? 4 : ''), // 4 = "Other" (free-text cost); validateCost() requires a type whenever cost is set
					'is_canceled'         => (!empty($row['is_canceled']) ? 1 : ''),
					'has_registration'    => $row['has_registration'],
					'registration_owner_email' => (!empty($row['has_registration']) ? 'sample-content@example.com' : ''), // required whenever has_registration is set; no site-wide default to pull from
				];

				if (!empty($row['categories'])) {
					$data['categories'] = array_filter(array_map(function($title) use ($category_map) {
						return @$category_map[$title];
					}, explode('|', $row['categories'])));
				};

				if (!empty($location_presets[$row['location']])) {
					if (!empty($location_map[$row['location']])) {
						// already created on a previous regeneration (or earlier in this same run) — reuse the pin
						$data['associated_data']['places'] = $location_map[$row['location']];
					}
					else {
						// first time this Saved Location is needed — create it as a group preset so future
						// instances of this same location string (this run or a later regeneration) reuse it
						$data['associated_data']['places'] = [
							'gid'          => $demo_gid,
							'title'        => $row['location'],
							'latitude'     => $location_presets[$row['location']][0],
							'longitude'    => $location_presets[$row['location']][1],
							'save_preset'  => 1
						];
					};
				};

				if (!empty($row['tags'])) {
					// events don't have a 'tags' column of their own — tags are a generic association, applied via
					// associated_data['tags'] as an array of titles (core's own CSV importer does the same conversion
					// in onFormatCSVImportRow(); the tags module creates any titles that don't already exist)
					$data['associated_data']['tags'] = array_filter(explode('|', $row['tags']));
				};

				$image_is_new = false; // tracks whether this instance's image still needs tagging/caching after create()
				if (!empty($row['image']) && !empty($image_id_map[$row['image']])) {
					// already uploaded on a previous regeneration (or earlier in this same run) — reuse it
					// rather than uploading yet another copy of the same file
					$data['associated_data']['images'][0] = [
						'id'            => $image_id_map[$row['image']],
						'alt'           => (!empty($row['image_alt']) ? $row['image_alt'] : ''),
						'is_thumb'      => 1,
						'is_decoration' => 1
					];
				}
				else if (!empty($row['image'])) {
					// images' onValidateImages() only accepts a path under WWW_DIR_PATH or INCLUDES_DIR_PATH/data/uploads/
					// (silently skipping the upload otherwise), so stage a copy there rather than pointing at our own
					// client/modules/demo/data/images/ path directly — same trick core uses for url-sourced CSV images
					$image_src = $module_dir.'/data/images/'.$row['image'];
					$image_tmp = $_LW->INCLUDES_DIR_PATH.'/data/uploads/demo_'.uniqid().'_'.$row['image'];
					if (file_exists($image_src) && @copy($image_src, $image_tmp)) {
						$data['associated_data']['images'][0] = [
							'path'          => $image_tmp,
							'gid'           => $demo_gid, // onSaveSuccess() only backfills gid from the parent item for url-referenced images, never path-referenced ones
							'description'   => (!empty($row['image_alt']) ? $row['image_alt'] : $row['title']), // required by images onBeforeValidate() for a path-referenced image
							'date'          => date('m/d/Y'), // required alongside description
							'alt'           => (!empty($row['image_alt']) ? $row['image_alt'] : ''),
							'is_thumb'      => 1,
							'is_decoration' => 1 // these are stock placeholder photos, not meaningful content
						];
						$image_is_new = true;
					}
					else {
						$_LW->logDebug('Demo: populateEvents() could not stage image '.$image_src.' to '.$image_tmp);
					};
				};

				try {
					$event_id = $_LW->create('events', $data);
				}
				catch (\Throwable $e) {
					// catches an unexpected throw from deep inside create() (e.g. the places associated_data
					// shape not matching what this install's Places feature expects) so one bad row can't take
					// down the rest of the regeneration the way an uncaught error would
					$error = 'Failed to create sample event "'.$row['title'].'" on '.date('m/d/Y', $new_start_ts).' — '.$e->getMessage();
					$_LW->logDebug('Demo: '.$error);
					$failed[] = $error;
					continue;
				};
				if (empty($event_id)) {
					$error = 'Failed to create sample event "'.$row['title'].'" on '.date('m/d/Y', $new_start_ts).' — '.$_LW->error;
					$_LW->logDebug('Demo: '.$error);
					$failed[] = $error;
					continue;
				};
				$created++;

				// Flag as ours, invisibly, so the next regeneration only wipes what this module created
				$_LW->setCustomFields('events', $event_id, ['demo_generated' => 1], ['demo_generated']);

				// Flag the freshly-uploaded image with its source filename, and cache it, so later instances
				// of this row (this run or a future regeneration) reuse it instead of re-uploading
				if (!empty($image_is_new)) {
					if ($image_id = $_LW->dbo->query('select', 'id1', 'livewhale_images2any', 'id2='.(int)$event_id.' AND type="events"')->firstRow('id1')->run()) {
						$_LW->setCustomFields('images', $image_id, ['demo_source_image' => $row['image']], ['demo_source_image']);
						$image_id_map[$row['image']] = $image_id;
					};
				};

				// Cache a freshly-created Saved Location the same way, so later instances of this row (this run
				// or a future regeneration) reuse the same preset pin instead of creating another one
				if (!empty($location_presets[$row['location']]) && empty($location_map[$row['location']])) {
					if ($place_id = $_LW->dbo->query('select', 'id1', 'livewhale_places2any', 'id2='.(int)$event_id.' AND type="events"')->firstRow('id1')->run()) {
						$location_map[$row['location']] = $place_id;
					};
				};

				// Seed bundled sample RSVPs, if any, onto this instance
				if (!empty($row['has_registration']) && !empty($row['registrations_received'])) {
					if ($registrants = @json_decode($row['registrations_received'], true)) {
						foreach ($registrants as $registrant) {
							if (empty($registrant['email'])) continue;
							$registrant = array_merge([
								'firstname'                  => '',
								'lastname'                   => '',
								'email'                      => '',
								'phone'                      => '',
								'attending'                  => '',
								'comments_by_registrant'     => '',
								'comments_by_editor'         => '',
								'is_cancelled'                => '',
								'status'                      => '',
								'is_waitlisted'               => '',
								'custom_fields'               => '',
								'sent_registration_reminder'  => '',
								'sent_registration_followup'  => '',
							], $registrant);
							$had_status = !empty($registrant['status']); // capture before escaping empties it out to '' (which isn't the same as leaving status NULL)
							$registrant['pid']           = $event_id;
							$registrant['cancel_id']      = md5(uniqid('', true)); // must be unique per registration, not sourced from the CSV
							$registrant['date_created']   = date('Y-m-d H:i:s');
							$registrant['last_modified']  = date('Y-m-d H:i:s');
							foreach ($registrant as $key => $val) {
								$registrant[$key] = $_LW->escape($val);
							};
							$registrant['status'] = ($had_status ? $registrant['status'] : 'NULL'); // most sample RSVPs leave status unset; the CSV can mark a few 1 ("Attended") for past instances
							$_LW->dbo->query('insert', 'livewhale_events_registrations', $registrant)->run();
						};
					};
				};

			};

			$tile_start += $cycle_seconds;
		};

		return ['created' => $created, 'failed' => $failed];
	}

}

?>
