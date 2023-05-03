This module filters the out-of-the-box iCal feed from CampusLabs Engage to allow you to filter by event presenter (X-HOSTS in the iCal feed.)

Instructions:

- After installing, add to your global config 
	`$_LW->CONFIG['ENGAGE_ICAL_URL']='https://myschool.campuslabs.com/engage/events.ics';`
- Edit the config line to point to the correct iCal URL
- Add a linked calendar pointing to "/live/engage/host/My Event Host" to show just that organizer's events in the feed.