<?

require('../components.php');
$db = OBFDB::get_instance();

$db->where('user_id',0);
$db->where('client_name','obapp_web_client');
$row = $db->get_one('client_storage');

if($row) $global_client_settings = json_decode($row['data']);
if(isset($global_client_settings->welcome_message)) $welcome_message = $global_client_settings->welcome_message;
else $welcome_message = '';

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
			<? include('login.php'); ?>
		</div>

		<div class="section" id="forgotpass" style="display: none;">
			<? include('forgotpass.php'); ?>
		</div>

		<div class="section" id="newaccount" style="display: none;">
			<? include('newaccount.php'); ?>
		</div>

		<p style="font-size: 0.9em; padding-top: 1em;">Running version <?=$version?>.<br>Browser support Firefox 36+, Chrome 41+, Safari 7+, IE 11+.</p>
		<p style="font-size: 0.9em; padding-top: 1em;">OpenBroadcaster is released under Affero GPL v3 and may be downloaded at <a href="http://www.openbroadcaster.com/">openbroadcaster.com</a>.  View <a href="http://www.gnu.org/licenses/agpl.html">license</a>.</p>

	</div>

</div>

</body>
</html>

