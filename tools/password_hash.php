<?php

if(php_sapi_name()!='cli') die('cli only');

if(empty($argv[1]) || empty($argv[2])) die();

// argv[1] = password
// argv[2] = salt

echo password_hash($argv[1].$argv[2],PASSWORD_DEFAULT);
