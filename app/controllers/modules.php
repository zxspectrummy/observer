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

/**
 * Manages module installation and uninstallation.
 *
 * @package Controller
 */
class Modules extends OBFController
{

  public function __construct()
  {
    parent::__construct();
    $this->user->require_permission('manage_modules');
  }

  /**
   * Return a list of currently installed and available (= uninstalled) modules.
   *
   * @return [installed, available]
   */
  public function search()
  {

    $modules = array();
    $modules['installed'] = $this->models->modules('get_installed');
    $modules['available'] = $this->models->modules('get_not_installed');

    return array(true,'Modules',$modules);

  }

  /**
   * Install a module. Requires a page refresh after installation.
   *
   * @param name
   */
  public function install()
  {

    $module = $this->data('name');

    $install = $this->models->modules('install',$module);

    if($install) return array(true,'Module installed. Refreshing the page may be required to update the user interface.');
    else return array(false,'An error occurred while attempting to install this module.');

  }

  /**
   * Uninstall a module. Requires a page refresh after uninstallation.
   *
   * @param name
   */
  public function uninstall()
  {

    $module = $this->data('name');

    $uninstall = $this->models->modules('uninstall',$module);

    if($uninstall) return array(true,'Module uninstalled. Refreshing the page may be required to update the user interface.');
    else return array(false,'An error occurred while attempting to uninstall this module.');

  }

}
