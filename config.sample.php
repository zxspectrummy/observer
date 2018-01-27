<?php

const OB_DB_USER = 'dbuser'; // database user
const OB_DB_PASS = 'dbpass'; // database password
const OB_DB_HOST = 'localhost'; // database hostname
const OB_DB_NAME = 'dbname'; // database name

const OB_HASH_SALT='CHANGEMEPLEASE'; // change to random characters for password salt.

// make sure the following directories are writable by the web server.
const OB_MEDIA='/where/to/put/media';
const OB_MEDIA_UPLOADS='/where/to/put/media/uploads'; // can be subdirectory of OB_MEDIA, but doesn't need to be.
const OB_MEDIA_ARCHIVE='/where/to/put/media/archive'; // can be subdirectory of OB_MEDIA, but doesn't need to be.
const OB_CACHE='/where/to/put/cache/files';

const OB_SITE = 'http://example.com/'; // where do you access OB?

const OB_EMAIL_REPLY = 'noreply@example.com'; // emails to users come from this address
const OB_EMAIL_FROM = 'OpenBroadcaster'; // emails to users come from this name


//
// THE FOLLOWING ARE OPTIONAL SETTINGS
//

// disable language/translation demos (translated using Google Translate, will contain many errors)
// const OB_DISABLE_LANGUAGE_DEMOS = true;

// external database for filetype identification if php-bundled database is not adequate. (see php info).
// const OB_MAGIC_FILE = ''; 

// set for debugging remote.php (remote.php?devmode=CHANGEME sets $_POST = $_GET and skips device authentication).
// const OB_REMOTE_DEBUG = 'CHANGEME'; 

// uncomment to log slow SQL queries using php error_log().
// const OB_LOG_SLOW_QUERIES = TRUE;

// set optional username/password for updates area. (if not set, you can log in as an OB admin first.)
// get password hash with: php -r "echo password_hash('password',PASSWORD_DEFAULT).\"\n\";"
//const OB_UPDATES_USER = 'updates';
//const OB_UPDATES_PW = 'PASSWORD_HASH';

// set custom audio transcode command (outputting to mp3 or ogg).
// const OB_TRANSCODE_AUDIO_MP3 = 'transcode {infile} {outfile}';
// const OB_TRANSCODE_AUDIO_OGG = 'transcode {infile} {outfile}';

// set custom video transcode command (outputting to mp4 or ogv, with specified output resolution)
// const OB_TRANSCODE_VIDEO_MP4 = 'transcode {infile} -s {width}x{height} {outfile}';
// const OB_TRANSCODE_VIDEO_OGV = 'transcode {infile} -s {width}x{height} {outfile}';
