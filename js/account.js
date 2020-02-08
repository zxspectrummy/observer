/*
    Copyright 2012-2020 OpenBroadcaster, Inc.

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

OB.Account = new Object();

OB.Account.username = '';
OB.Account.user_id = 0;
OB.Account.userdata = null;

OB.Account.profile_upload_status = false;

OB.Account.init = function()
{
  OB.Callbacks.add('ready',-80,OB.Account.getAccountStatus);
  OB.Callbacks.add('ready',-5,OB.Account.initMenu);
  OB.Callbacks.add('ready',-5,OB.Account.updateAccountStatus); // this is also called by getAccountStatus, but layout not ready then.
}

OB.Account.initMenu = function()
{
  //T Account
  OB.UI.addMenuItem('Account', 'account', 10);
  //T Account Settings
  OB.UI.addSubMenuItem('account', 'Account Settings','settings', OB.Account.settings, 10);
  //T Logout
  OB.UI.addSubMenuItem('account', 'Logout', 'logout', OB.Account.logout, 30);
}

OB.Account.getAccountStatus = function()
{

  var post = [];
  post.push(['account','uid',{}]);
  post.push(['account','permissions',{}]);
  post.push(['account','groups',{}]);
  post.push(['account','settings',{}]);

  OB.API.multiPost(post,function(data) {

    var uid = data[0];
    var permissions = data[1];
    var groups = data[2];
    var settings = data[3];

    OB.Account.user_id=uid.data.id;
    OB.Account.username=uid.data.username;
    OB.Account.userdata=settings.data;

    OB.Account.updateAccountStatus();

		// reload to direct user back to welcome screen if they are not logged in for some reason.
    if(OB.Account.user_id == 0) location.reload();

    OB.Settings.permissions = permissions.data;
    OB.Settings.groups = groups.data;

  },'sync');

}

OB.Account.loginWindow = function()
{

  OB.UI.openModalWindow('account/login.html');

	$('#account_login_username').text(OB.Account.username);

  // allow enter key to go from username to password, or password to submit.
  $('#account_login_password').unbind('keydown');
  $('#account_login_password').keydown(function(event) {
    if(event.keyCode == '13') $('#account_login_submit').click();
  });

  // set our focus on the username field.
  $('#account_login_password').focus();

}

OB.Account.login = function()
{

  var username = $('#account_login_username').text();
  var password = $('#account_login_password').val();

  OB.API.post('account','login', { 'username': username, 'password': password },function(data) {

    if(data.status==false)
    {
      //T The password you have provided is incorrect.
      $('#account_login_message').obWidget('error', 'The password you have provided is incorrect.');

      $('#account_login_username').focus();
      $('#account_login_username').select();
    }

    else
    {
      // no need to update these as user doesn't change
      // OB.Account.username = $('#account_login_username').val();
      // OB.Account.user_id = data.data.id;
      // OB.Account.updateAccountStatus();

      $('#account_login_username').val('');
      $('#account_login_password').val('');
      OB.UI.closeModalWindow();

      OB.Callbacks.callall('account_login');
    }

  });
}

OB.Account.logout = function()
{

  OB.API.post('account','logout',{},function(data) {

    if(data.status==true) {
			window.location.href = window.location.href;
    }

    else {
      //T Unable to log out.
      OB.UI.alert('Unable to log out.');
    }
  });

}

OB.Account.updateAccountStatus = function()
{
  //T Logged in as: %1
  $('#footer_account_username').html(OB.t('Logged in as: %1', OB.Account.username));
  $('#footer_box_left').show();
  $('#footer_box_right').show();
}

OB.Account.settings = function()
{

  var post = [];
  post.push(['account','settings',{}]);
  post.push(['ui','get_languages',{}]);
  post.push(['ui','get_themes',{}]);

  OB.API.multiPost(post,function(data) {

    var settings = data[0];
    var languages = data[1];
    var themes = data[2];

    OB.UI.replaceMain('account/settings.html');

    var userdata = settings.data;

    $('#account_username').text(userdata['username']);
    $('#account_name_input').val(userdata['name']);
    $('#account_email_input').val(userdata['email']);
    $('#account_display_name_input').val(userdata['display_name']);

    // user settings
    $('#account_user_results_per_page').val(OB.ClientStorage.get('results_per_page'));

    if(languages && languages.data) $.each(languages.data, function(value,language)
    {
      $('#account_language').append('<option value="'+language.code+'">'+htmlspecialchars(language.name)+'</option>');
    });

    if(themes && themes.data) $.each(themes.data, function(value,theme)
    {
      $('#account_theme').append('<option value="'+value+'">'+htmlspecialchars(theme)+'</option>');
    });

    var language = userdata['language'];
    if (!language == '') $('#account_language').val(language);

    var theme = userdata['theme'];
    if(!theme) theme = 'default';
    $('#account_theme').val(theme);

    var dyslexia_friendly_font = userdata['dyslexia_friendly_font'];
    if(!parseInt(dyslexia_friendly_font)) dyslexia_friendly_font = 0;
    else dyslexia_friendly_font = 1;
    $('#account_dyslexia_friendly_font').val(dyslexia_friendly_font);

    var sidebar_display_left = userdata['sidebar_display_left'];
    if(!parseInt(sidebar_display_left)) sidebar_display_left = 0;
    else sidebar_display_left = 1;
    $('#account_sidebar_display_left').val(sidebar_display_left);

  });

}

OB.Account.settingsSubmit = function()
{

  var data = {};
  data['name'] = $('#account_name_input').val();
  data['password'] = $('#account_password_input').val();
  data['password_again'] = $('#account_password_again_input').val();
  data['email'] = $('#account_email_input').val();
  data['display_name'] = $('#account_display_name_input').val();
  data['language'] = $('#account_language').val();
  data['theme'] = $('#account_theme').val();
  data['dyslexia_friendly_font'] = $('#account_dyslexia_friendly_font').val();
  data['sidebar_display_left'] = $('#account_sidebar_display_left').val();

  OB.API.post('account','update_settings',data,function(response) {
    $('#account_settings_message').obWidget(response.status ? 'success' : 'error',response.msg);
  });

  // TODO this is a bit meh. we merged this to the main account settings page but this doesn't provide any feedback/error/etc.
  // minor issue i think.
  var settings = {};
  settings.results_per_page = parseInt($('#account_user_results_per_page').val());
  OB.ClientStorage.store(settings,function() {});

}
