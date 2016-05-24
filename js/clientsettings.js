/*     
    Copyright 2012-2014 OpenBroadcaster, Inc.

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

// client settings
OB.ClientSettings = new Object();

OB.ClientSettings.init = function()
{
  OB.Callbacks.add('ready',-4,OB.ClientSettings.initMenu);
}

OB.ClientSettings.initMenu = function()
{
  OB.UI.addSubMenuItem('admin',['Admin Menu','Client Settings'],'client_settings',OB.ClientSettings.settings,10,'manage_global_client_storage');
}

// get the media format settings.
OB.ClientSettings.settings = function()
{
  OB.UI.replaceMain('client_settings/settings.html');
  $('#client_settings_welcome_message').val(OB.ClientStorage.get('welcome_message',true));     
}

OB.ClientSettings.welcomeMessageSave = function()
{
  OB.ClientStorage.store({'welcome_message': $('#client_settings_welcome_message').val()},function()
  {
    $('#client_settings_message').obWidget('success',OB.t('Client Settings','Save Confirmation'));
  }, true);
}


