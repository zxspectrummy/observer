<?php

require('../components.php');
$db = OBFDB::get_instance();
$db->where('name', 'client_login_message');
$result = $db->get_one('settings');
$welcome_message = $result ? $result['value'] : '';

if(is_file('VERSION')) $version = trim(file_get_contents('VERSION'));
else $version = 4;

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
	<script src="../extras/jquery.json.js"></script>
	<script src="welcome.js?v=<?=urlencode($version)?>"></script>
	<link href='//fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="welcome.css?v=<?=urlencode($version)?>" type="text/css">
  <title>OpenBroadcaster</title>
</head>
<body>

<div id="container1">

	<div id="container2">

		<div class="section" id="login">
			<?php include('login.php'); ?>
		</div>

		<div class="section" id="forgotpass" style="display: none;">
			<?php include('forgotpass.php'); ?>
		</div>

		<div class="section" id="newaccount" style="display: none;">
			<?php include('newaccount.php'); ?>
		</div>

		<p style="font-size: 0.9em; padding-top: 1em;">Running version <?=$version?>.<br>Browser support Firefox 36+, Chrome 41+, Safari 7+, IE 11+.</p>
		<p style="font-size: 0.9em; padding-top: 1em;">OpenBroadcaster is released under Affero GPL v3 and may be downloaded at <a href="https://openbroadcaster.com/observer">openbroadcaster.com</a>.  View <a href="https://openbroadcaster.com/observer_licence">license</a>.</p>

	</div>

</div>

</body>
</html>
