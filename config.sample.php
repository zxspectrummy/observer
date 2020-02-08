<?php

define('OB_DB_USER','dbuser'); // database user
define('OB_DB_PASS','dbpass'); // database password
define('OB_DB_HOST','localhost'); // database hostname
define('OB_DB_NAME','dbname'); // database name

define('OB_HASH_SALT','CHANGEMEPLEASE'); // change to random characters for password salt.

// make sure the following directories are writable by the web server.
define('OB_MEDIA','/where/to/put/media');
define('OB_MEDIA_UPLOADS','/where/to/put/media/uploads'); // can be subdirectory of OB_MEDIA, but doesn't need to be.
define('OB_MEDIA_ARCHIVE','/where/to/put/media/archive'); // can be subdirectory of OB_MEDIA, but doesn't need to be.
define('OB_CACHE','/where/to/put/cache/files');

define('OB_SITE','http://example.com/'); // where do you access OB?

define('OB_EMAIL_REPLY','noreply@example.com'); // emails to users come from this address
define('OB_EMAIL_FROM','OpenBroadcaster'); // emails to users come from this name

//
// THE FOLLOWING ARE OPTIONAL SETTINGS
//

// verify audio/video media validity, possibly slow (default true)
// define('OB_MEDIA_VERIFY',true);

// media filesize limit in MB (default 1024)
// define('OB_MEDIA_FILESIZE_LIMIT',1024);

// where to media file versions (default is OB_MEDIA/versions)
// define('OB_MEDIA_VERSIONS','/where/to/put/media/versions');

// set a custom assets directory (required for multisite installations)
// define('OB_ASSETS','/where/to/put/assets');

// disable language/translation demos (translated using Google Translate, will contain many errors)
// define('OB_DISABLE_LANGUAGE_DEMOS',TRUE);

// external database for filetype identification if php-bundled database is not adequate. (see php info).
// define('OB_MAGIC_FILE','');

// set for debugging remote.php (remote.php?devmode=CHANGEME sets $_POST = $_GET and skips device authentication).
// define('OB_REMOTE_DEBUG','CHANGEME');

// uncomment to log slow SQL queries using php error_log().
// define('OB_LOG_SLOW_QUERIES',TRUE);

// set optional username/password for updates area. (if not set, you can log in as an OB admin first.)
// get password hash with: php -r "echo password_hash('password',PASSWORD_DEFAULT).\"\n\";"
// define('OB_UPDATES_USER','updates');
// define('OB_UPDATES_PW','PASSWORD_HASH');

// set custom audio transcode command (outputting to mp3 or ogg).
// define('OB_TRANSCODE_AUDIO_MP3','transcode {infile} {outfile}');
// define('OB_TRANSCODE_AUDIO_OGG','transcode {infile} {outfile}');

// set custom video transcode command (outputting to mp4 or ogv, with specified output resolution)
// define('OB_TRANSCODE_VIDEO_MP4','transcode {infile} -s {width}x{height} {outfile}');
// define('OB_TRANSCODE_VIDEO_OGV','transcode {infile} -s {width}x{height} {outfile}');

// allow api access without login. use carefully as this overrides the authentication requirement in the permission system.
// define('OB_PUBLIC_API', ['Settings.category_list','Settings.genre_list']);

// enable public media browsing and streaming
// define('OB_STREAM_API',true); 