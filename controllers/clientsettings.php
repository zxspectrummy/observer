<?php

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

class ClientSettings extends OBFController {
  public function __construct () {
    parent::__construct();
    $this->SettingsModel = $this->load->model('Settings');
  }

  public function set_login_message () {
    $this->user->require_permission('manage_global_client_storage');
    $data = $this->data('client_login_message');
    return $this->SettingsModel->setting_set('client_login_message', $data);
  }

  public function get_login_message () {
    return $this->SettingsModel->setting_get('client_login_message');

  }

  public function set_welcome_page () {
    $this->user->require_permission('manage_global_client_storage');
    $data = $this->data('client_welcome_page');
    return $this->SettingsModel->setting_set('client_welcome_page', $data);
  }

  public function get_welcome_page () {
    $this->user->require_authenticated();
    return $this->SettingsModel->setting_get('client_welcome_page');
  }
}
