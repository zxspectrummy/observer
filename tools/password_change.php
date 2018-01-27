#!/usr/bin/php -d short_open_tag=On
<?php

if(php_sapi_name()!='cli') die('cli only');

if(empty($argv[1]) || empty($argv[2])) die('invalid args');

// argv[1] = username
// argv[2] = password

require('config.php');

$hashed = password_hash($argv[2].OB_HASH_SALT,PASSWORD_DEFAULT);

$conn = mysqli_connect(OB_DB_HOST, OB_DB_USER, OB_DB_PASS);
mysqli_select_db($conn, OB_DB_NAME);
mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE username='$argv[1]';");
echo "done\n";
