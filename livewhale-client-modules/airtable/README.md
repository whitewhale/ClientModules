** All functions and configuration options are in global.application.airtable.php unless otherwise indicated.**

The Airtable integration connects to the "api_base" URL using the personal access token set in core/config.

It connects to multiple tables (set in "tables") and pulls fields from each table (set in "fields".

On the faculty profile page, email address is used to filter + generate formatted airtable results for display, those are populated in the template as

	<xphp var="airtable_books" />
	<xphp var="airtable_chapters" />
	<xphp var="airtable_articles" />
	<xphp var="airtable_other" />

To edit the HTML formatting of those results, see the HTML set in formatFacultyResult for each table type.

Cache and refreshing:
- Cached JSON results of each table's data are in /livewhale/data/airtable
- There is an hourly sync process that runs automatically (in the livewhale scheduler) to run exec/airtable_hourly.php and refreshes the cache
- There's also two failsafes when faculty pages load: if it can't find cached results, it will automatically pull and use the direct results. Also, if it finds cached results >90min old, it will use them once and then regenerate the complete cache for next time.