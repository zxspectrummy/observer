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

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

require('../../components.php');

$load = OBFLoad::get_instance();
$db = OBFDB::get_instance();

// make sure this module is installed
$module_model = $load->model('Modules');
$installed_modules = $module_model('get_installed');
if(!isset($installed_modules['now_playing'])) die('The "now playing" module is not installed.');

// make sure this device is valid.
if(!isset($_GET['i']) || !preg_match('/^\d+$/',$_GET['i'])) die('Invalid device.');
$device_model = $load->model('Devices');
$device = $device_model('get_one',$_GET['i']);
if(!$device) die('Invalid device.');

$data = $device_model('now_playing',$_GET['i']);

// return information via JSON if requested as such.
if(!empty($_GET['json'])) { echo json_encode($data); die(); }

require('modules/now_playing/template.php');
