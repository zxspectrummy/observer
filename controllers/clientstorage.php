<?php

/*     
    Copyright 2012 OpenBroadcaster, Inc.

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

// allow clients (like the main javascript/web client, or a third party client) to store data associated with a user.
class ClientStorage extends OBFController
{

  public function __construct()
  {
    parent::__construct();
    $this->ClientStorageModel = $this->load->model('ClientStorage');  
  }

  public function store()
  {

    $this->user->require_authenticated();

    $data['client_name'] = $this->data('client_name');
    $data['data'] = $this->data('data');

    // global data or user data?  global data is for all users.
    if($this->data('global')) {
      $data['user_id'] = 0;
      $this->user->require_permission('manage_global_client_storage'); // we need special permission to store globally. (but not to get globally)
    }
    else $data['user_id'] = $this->user->param('id');

    $validation = $this->ClientStorageModel('validate',$data);
    if($validation[0]==false) return $validation;

    $this->ClientStorageModel('store',$data);

    return array(true,'Data has been stored.');

  }

  public function get()
  {

    $client_name = $this->data('client_name');
    $global = $this->data('global');

    // global data or user data?  global is for all users.
    if($global) $user_id = 0;
    else 
    {
      $user_id = $this->user->param('id');
      $this->user->require_authenticated(); // require authenticated if it's user-dependent.
    }

    return $this->ClientStorageModel('get',$client_name,$user_id);

  }

}
