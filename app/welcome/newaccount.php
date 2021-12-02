<div>

<h1>New Account</h1>

<p id="newaccount_message"></p>

<div id="newaccount_form">

  <p>Fill out the following form to create a new account.  A random password will be emailed to you.</p>

  <table>
  <tr>
    <td class="required">Name: </td>
    <td><input id="newaccount_name" type="text"></td>
  </tr>
  <tr>
    <td class="required">Username: </td>
    <td><input id="newaccount_username" type="text"></td>
  </tr>
  <tr>
    <td class="required">Email: </td>
    <td><input id="newaccount_email" type="text"></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td class="submit"><input type="button" value="Create New Account" onclick="OB.Welcome.newaccount();"></td>
  </tr>

  </table>

</div>

<p><a href="javascript: OB.Welcome.show('login');">Return to login window.</a></p>

</div>
