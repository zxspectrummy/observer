<div>

  <h1>Welcome to OpenBroadcaster</h1>

  <p id="login_welcome"><?=nl2br(htmlspecialchars($welcome_message))?></p>

  <p id="login_message"></p>

  <?php /* form/submit tags used so browser offers to save password. does not function without javascript. */ ?>
  <form method="post" action="index.php" onSubmit="return false;">
  <table>
  <tr>
    <td class="required">Username:</td>
    <td><input name="ob_login_username" id="login_username" type="text"></td>
  </tr>
  <tr>
    <td class="required">Password:</td>
    <td><input name="ob_login_password" id="login_password" type="password"></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td class="submit"><input type="submit" value="Log In" onclick="OB.Welcome.login();"></td>
  </tr>
  </table>
  </form>

  <p><a href="javascript: OB.Welcome.show('forgotpass');">Forgot password?</a></p>
  <?php
  $load = OBFLoad::get_instance();
  $user_model = $load->model('users');
  if($user_model->user_registration_get()) { ?>
    <p><a href="javascript: OB.Welcome.show('newaccount');">Create new account.</a></p>
  <?php } ?>

</div>
