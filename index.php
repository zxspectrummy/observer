<?
/*     
    Copyright 2012-2013 OpenBroadcaster, Inc.

    This file is part of OpenBroadcaster Server.

    OpenBroadcaster Server is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    OpenBroadcaster Server is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with OpenBroadcaster Server.  If not, see <http://www.gnu.org/licenses/>.
*/

// used by install checker to check whether this is an OB application index file.
// might be used by other things as well.
header('OpenBroadcaster-Application: index'); 

require('components.php');

if(is_file('VERSION')) $version = trim(file_get_contents('VERSION'));
else $version = false;

// are we logged in? if not, redirect to welcome page.
$user = OBFUser::get_instance();
if(!isset($_COOKIE['ob_auth_id']) || !isset($_COOKIE['ob_auth_key']) || !$user->auth($_COOKIE['ob_auth_id'],$_COOKIE['ob_auth_key']))
{
  header('Location: /welcome');
  die();
}

// we're logged in! continue with load.
$load = OBFLoad::get_instance();
$ui_model = $load->model('ui');
$js_files = $ui_model->js_files();
$css_files = $ui_model->css_files();
$image_files = $ui_model->image_files();

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>OpenBroadcaster</title>

  <? // TODO: need a way to add this to theme or default UI information elsewhere ... like prepend/append html HEAD function/variable/object? ?>
  <link href='//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800' rel='stylesheet' type='text/css'>

  <link type="text/css" href="/extras/jquery-ui-themes/ui-darkness/jquery-ui-1.8.23.custom.css" rel="Stylesheet">

  <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

  <? // TODO: don't require migrate... I think superfish is causing problems with newer jquery but we need to swap this plugin out anyway. ?>
  <script src="//code.jquery.com/jquery-migrate-1.2.1.js"></script>

  <script type="text/javascript" src="extras/jquery-ui-1.8.23.custom.min.js"></script>
  <script type="text/javascript" src="extras/jquery-ui-timepicker-addon.js"></script>
  <script type="text/javascript" src="extras/jquery.ba-dotimeout.min.js"></script>
  <script type="text/javascript" src="extras/jquery.json.js"></script>
  <script type="text/javascript" src="extras/jquery.DOMWindow.js"></script>
  <script type="text/javascript" src="extras/jquery.superfish.js"></script>
  <script type="text/javascript" src="extras/jquery.scrollTo.min.js"></script>
  <script type="text/javascript" src="extras/jquery.visible.min.js"></script>
  <script type="text/javascript" src="extras/jquery.mousewheel.min.js"></script>
  <script type="text/javascript" src="extras/jquery.contextMenu.js"></script>
  <script type="text/javascript" src="extras/dateformat.js"></script>

  <link rel="stylesheet" type="text/css" href="extras/jquery-ui-timepicker-addon.css">
 
  <? foreach($js_files as $file) { ?>
    <script type="text/javascript" src="<?=$file?>?v=<?=urlencode($version)?>"></script>
  <? } ?>
  <? /* <script type="text/javascript" src="js-min/ob.min.js"></script> */ ?>

  <script type="text/javascript" src="strings.php?v=<?=urlencode($version)?>"></script>

  <? foreach($css_files as $file) { ?>
    <link rel="stylesheet" type="text/css" href="<?=$file?>?v=<?=urlencode($version)?>">
  <? } ?>

  <? if(!empty($user->userdata['dyslexia_friendly_font'])) { ?>
    <link rel="stylesheet" type="text/css" href="opendyslexic/opendyslexic.css?v=<?=urlencode($version)?>">
  <? } ?>

</head>

<body>

<div id="main_container"></div>

<div id="preload_images" style="display: none;">
  <? foreach($image_files as $file) { ?>
    <img src="<?=$file?>">
  <? } ?>
</div>

</body>
</html>
