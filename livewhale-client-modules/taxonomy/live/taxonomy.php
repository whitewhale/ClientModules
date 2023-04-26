<?php

// This /live/taxonomy/___ endpoint provides additional JSON results for different taxonomies

// LiveWhale CMS, Calendar, and Storyteller
	// /live/taxonomy/tags = Global Starred Tags
	// /live/taxonomy/global_tags = Global Tags
	// /live/taxonomy/all_tags = All Tags

// LiveWhale CMS / Calendar
	// /live/taxonomy/event_types = Event Types
	// /live/taxonomy/event_types/audience = Event Types: Audiences
	// /live/taxonomy/event_types/campus = Event Types: Campuses

// LiveWhale CMS / Storyteller
	// /live/taxonomy/news_categories

// All results are cached for 5min

// Custom field taxonomies can be added by uncommenting and customizing the block of code at the end.



if (!empty($LIVE_URL['REQUEST'])) { // if valid request
	require $LIVE_URL['DIR'].'/livewhale.php'; // load LiveWhale
	$request=array_shift($LIVE_URL['REQUEST']); // get command name
	switch($request) {
		case 'tags':
			// Global Starred Tags
			$key='taxonomy_tags';
			$tags = $_LW->getVariable($key);
			if (empty($tags)) { // if tags not cached
				$tags=[];
				foreach($_LW->dbo->query('select', 'livewhale_tags.id,livewhale_tags.title', 'livewhale_tags', 'livewhale_tags.is_starred IS NOT NULL AND livewhale_tags.gid IS NULL', 'livewhale_tags.title ASC')
				->groupBy('livewhale_tags.id')->run() as $res2) { // fetch tags
					$tags[]=[
						'id'=>$res2['id'],
						'title'=>trim($res2['title'])
					];
				};
				$_LW->setVariable($key, $tags, 300); // cache for 5min
			};
			echo json_encode($tags);
		break;

		case 'global_tags':
			// Global Tags
			$key='taxonomy_global_tags';
			$tags = $_LW->getVariable($key);
			if (empty($tags)) { // if tags not cached
				$tags=[];
				foreach($_LW->dbo->query('select', 'livewhale_tags.id,livewhale_tags.title', 'livewhale_tags', 'livewhale_tags.gid IS NULL', 'livewhale_tags.title ASC')
				->groupBy('livewhale_tags.id')->run() as $res2) { // fetch tags
					$tags[]=[
						'id'=>$res2['id'],
						'title'=>trim($res2['title'])
					];
				};
				$_LW->setVariable($key, $tags, 300); // cache for 5min
			};
			echo json_encode($tags);
		break;

		case 'all_tags':
			// All Tags
			$key='taxonomy_all_tags';
			$tags = $_LW->getVariable($key);
			if (empty($tags)) { // if tags not cached
				$tags=[];
				foreach($_LW->dbo->query('select', 'livewhale_tags.id,livewhale_tags.title', 'livewhale_tags', '', 'livewhale_tags.title ASC')
				->groupBy('livewhale_tags.id')->run() as $res2) { // fetch tags
					$tags[]=[
						'id'=>$res2['id'],
						'title'=>trim($res2['title'])
					];
				};
				$_LW->setVariable($key, $tags, 300); // cache for 5min
			};
			echo json_encode($tags);
		break;

		case 'event_types':
			// Starred Event Types

			$type_id=1;
			$request2=array_shift($LIVE_URL['REQUEST']); // get command name
			if ($request2 == 'audience') {$type_id=2;}
			if ($request2 == 'campus') {$type_id=3;}
			
			$key='taxonomy_calendar_categories'.$type_id;
			$event_types = $_LW->getVariable($key);
			if (empty($event_types)) { // if event_types not cached
				$event_types=[];
				foreach($_LW->dbo->query('select', 'livewhale_events_categories.id,livewhale_events_categories.title', 'livewhale_events_categories', 'livewhale_events_categories.is_starred IS NOT NULL AND livewhale_events_categories.type='.$type_id, 'livewhale_events_categories.title ASC')
					->groupBy('livewhale_events_categories.title')->run() as $res2) { // fetch categories
					$event_types[]=[
						'id'=>$res2['id'],
						'title'=>trim($res2['title'])
					];
				};
				$_LW->setVariable($key, $event_types, 300); // cache for 5min
			};
			echo json_encode($event_types);
		break;

		case 'news_categories':
			// Starred Event Types
			
			$key='taxonomy_news_categories';
			$news_categories = $_LW->getVariable($key);
			if (empty($news_categories)) { // if news_categories not cached
				$news_categories=[];
				foreach($_LW->dbo->query('select', 'livewhale_news_categories.id,livewhale_news_categories.title', 'livewhale_news_categories', '', 'livewhale_news_categories.title ASC')
					->groupBy('livewhale_news_categories.title')->run() as $res2) { // fetch categories
					$news_categories[]=[
						'id'=>$res2['id'],
						'title'=>trim($res2['title'])
					];
				};
				$_LW->setVariable($key, $news_categories, 300); // cache for 5min
			};
			echo json_encode($news_categories);
		break;	


		// Uncomment and customize to extend to custom field taxonomy:
		/*	
		case 'audiences':	
			$key='audiences';
			$audiences = $_LW->getVariable($key);
			if (empty($audiences)) { // if audiences not cached
				$audiences=[];
				
				foreach($_LW->CONFIG['CUSTOM_FIELDS']['global'] as $custom_field) {
					if ($custom_field['name'] == 'audience') {
						$audiences = $custom_field['options'];
						break;
					}
				}
				$_LW->setVariable($key, $audiences, 300); // cache for 5min
			};
			echo json_encode($audiences);
		break;
		*/
	};
};
exit;

?>