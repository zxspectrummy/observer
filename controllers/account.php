<?php

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

class Account extends OBFController
{

  public function __construct()
  {
    parent::__construct();

    $this->UsersModel = $this->load->model('Users');
    $this->PermissionsModel = $this->load->model('Permissions');

  }

  public function login() 
  {

    $username = trim($this->data('username'));
    $password = trim($this->data('password'));

    $login = $this->user->login($username,$password);

    if($login==false) return array(false,'Login Failed.');
    elseif(is_array($login) && $login[0]===false) return array(false,$login[1]);

    else return array(true,'Login Successful',$login);
  }

  public function uid()
  {
    $data['id']=$this->user->param('id');
    $data['username']=$this->user->param('username');

    return array(true,'UID/Username',$data);
  }

  public function permissions()
  {

    $permissions = $this->PermissionsModel('get_user_permissions',$this->user->param('id'));
    return array(true,'Permissions',$permissions);

    /*
    $permissions = array();
    $permission_list = $this->db->get('users_permissions'); 

    foreach($permission_list as $check) $permissions[$check['name']]=$this->user->check_permission($check['name']);

    return array(true,'Permissions',$permissions);
    */

  }

  public function groups()
  {
    $groups = $this->PermissionsModel('get_user_groups',$this->user->param('id'));
    return array(true,'Groups',$groups);
  }

  public function logout()
  {
    $logout = $this->user->logout();

    if($logout) return array(true,'Logged Out');
    else return array(false,'Unable to log out, an unknown error occurred.');

  }

  public function settings()
  {

    $this->user->require_authenticated();

    $userdata = $this->user->userdata;
    unset($userdata['password']);
    unset($userdata['key']);
    unset($userdata['key_expiry']);
    unset($userdata['enabled']);

    return array(true,null,$userdata);

  }

  public function update_settings()
  {

    $this->user->require_authenticated();
  
    $user_id = $this->user->param('id');

    $data = array();
    $data['name'] = trim($this->data('name'));
    $data['password'] = trim($this->data('password'));
    $data['password_again'] = trim($this->data('password_again'));
    $data['email'] = trim($this->data('email'));
    $data['display_name'] = trim($this->data('display_name'));
    $data['language'] = trim($this->data('language'));
    $data['theme'] = trim($this->data('theme'));
    $data['dyslexia_friendly_font'] = trim($this->data('dyslexia_friendly_font'));
    $data['sidebar_display_left'] = trim($this->data('sidebar_display_left'));

    $validation = $this->UsersModel('settings_validate',$user_id,$data);
    
    if($validation[0]==false) return array(false,array('Account Settings',$validation[1]));
  
    $this->UsersModel('settings_update',$user_id,$data);

    return array(true,array('Account Settings','Settings Updated'));

  }

  public function forgotpass()
  {

    $email = trim($this->data('email'));

    $validation = $this->UsersModel('forgotpass_validate',$email);
    
    if($validation[0]==false) return $validation;

    $this->UsersModel('forgotpass_process',$email);

    return array(true,'A new password has been emailed to you.');

  }

  public function newaccount()
  {

    $data = array();
    $data['name'] = trim($this->data('name'));
    $data['email'] = trim($this->data('email'));
    $data['username'] = trim($this->data('username'));

    $validation = $this->UsersModel('newaccount_validate',$data);
    if($validation[0]==false) return $validation;

    $this->UsersModel('newaccount_process',$data);

    return array(true,'A new account has been created.  A randomly generated password has been emailed to you.');

  }


}
