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

class Modules extends OBFController
{

  public function __construct()
  {
    parent::__construct();
    $this->user->require_permission('manage_modules');
    $this->ModulesModel = $this->load->model('modules');
  }

  public function modules_list()
  {

    $modules = array();
    $modules['installed'] = $this->ModulesModel('get_installed');
    $modules['available'] = $this->ModulesModel('get_not_installed');

    return array(true,'Modules',$modules);

  }

  public function install()
  {

    $module = $this->data('name');

    $install = $this->ModulesModel('install',$module);

    if($install) return array(true,'Module installed. Refreshing the page may be required to update the user interface.');
    else return array(false,'An error occurred while attempting to install this module.');

  }

  public function uninstall()
  {

    $module = $this->data('name');

    $uninstall = $this->ModulesModel('uninstall',$module);

    if($uninstall) return array(true,'Module uninstalled. Refreshing the page may be required to update the user interface.');
    else return array(false,'An error occurred while attempting to uninstall this module.');

  }

}
