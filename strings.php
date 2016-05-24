<?

require('components.php');

$load = OBFLoad::get_instance();

if(!empty($_COOKIE['ob_auth_id']) && !empty($_COOKIE['ob_auth_key']))
{
  $auth_id = $_COOKIE['ob_auth_id'];
  $auth_key = $_COOKIE['ob_auth_key'];

  $user = OBFUser::get_instance();
  $user->auth($auth_id,$auth_key);
} 

$ui_model = $load->model('ui');
$strings = $ui_model->strings();
$language = $ui_model->get_user_language();

header('Content-type: text/javascript');

echo 'OB.UI.strings = '.json_encode($strings).';';

if(!empty($language['html_lang_attr'])) echo "\n$(document).ready(function() { $('html').attr('lang','".$language['html_lang_attr']."'); });";
