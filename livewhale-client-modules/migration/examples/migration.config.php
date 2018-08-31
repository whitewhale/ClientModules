<?php

/*

This is a sample migration configuration file. It should be copied to /livewhale/client along side the private/public/global configs and configured for the desired hosts.

*/

$config=array(
	'HOSTS'=>array(
		'www.foo.edu'=>array(
			'HTTP_HOST'=>'www.foo.edu',
			'FTP_HOST'=>'ftp.foo.edu',
			'FTP_PORT'=>'',
			'FTP_USER'=>'username',
			'FTP_PASS'=>'password',
			'FTP_PUB'=>'/path/to/www',
			'FTP_MODE'=>'sftp',
			'STAGE_ONLY'=>array(), // array of paths to files or directories that will be staged, otherwise all will be staged
			'STAGE_EXCLUDE'=>array(), // array of paths to files or directories that will be excluded from staging
			'STAGE_BUT_DONT_EXCLUDE'=>array(), // array of paths to files or directories exempt from the previous exclusion setting
			'STAGE_NO_EDITING'=>array(), // array of paths to files or directories that will be excluded from page editing, but otherwise migrated
			'CLEAN_ONLY'=>array(), // array of paths to files or directories that will be cleaned, otherwise all will be cleaned
			'LIVE_ONLY'=>array(), // array of paths to files or directories that will be brought live, otherwise all will be brought live
			'LIVE_HOST'=>'www.foo.edu', // specify the host associated with this content after taking live (either the same host, or the host absorbing this content)
			'LIVE_DEPTH'=>'', // maximum number of dirs deep to bring live
			'LIVE_EXTENSIONS'=>array(), // file type extensions, if only publishing certain items
			'PAGE_HOST'=>'', // db host to add migrated pages to (if not LIVE_HOST)
			'LIVE_INCLUDES_DIR'=>'/path/to/livewhale', // enter the dir (full PHP include path) to the LiveWhale installation
			'LIVE_DOCROOT'=>'/path/to/live/web/root', // enter the dir (full FTP path) to the docroot of the site content will be taken live to
			'LIVE_DIR'=>'/somedir', // enter a relative directory prefix if all files should be taken live to a subdir of the destination site (optional)
			'LIVE_IGNORED_HOSTS'=>array(), // array of hostnames that should be considered this site, besides the LIVE_HOST (i.e. if this site is staging.foo.edu but we want to convert links to www.foo.edu into site-relative ones)
			'MIGRATE_ONLY'=>array(), // array of paths to files or directories that will be migrated, otherwise all will be migrated (based on original url path)
			'MIGRATE_EXCLUDE'=>array(), // array of paths to files or directories that will be excluded from migration (based on original url path)
			'MIGRATE_DEPTH'=>'', // maximum number of dirs deep to migrate
			'TEMPLATES'=>array( // array of path prefixes to templates used in migration, least specific to most specific (optional) (set to false if excluding)
				'/'=>array( // prefix, based on original url path
					'/path/to/template.php', // full FTP path to template
					array('element_id'=>'element_id') // map of source element id in original page to template editable id
				)
			),
			'EDITABLE'=>array( // array of path prefixes to editable regions used in migration, least specific to most specific (optional)
				'/'=>array( // prefix, based on original url path
					array('element_id'), // element ids
					array('element_id') // element ids that are also optional
				)
			)
		)
	)
);

?>