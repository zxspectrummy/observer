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

require_once('components.php');

if(isset($_POST['c']) && isset($_POST['a']))
{
  $controller_action = strtolower($_POST['c'].'.'.$_POST['a']);
  if(defined('OB_PUBLIC_API') && is_array(OB_PUBLIC_API) && array_search($controller_action,array_map('strtolower',OB_PUBLIC_API))!==FALSE)
  {
    require('api.php');
    exit();
  }
}

http_response_code(404);