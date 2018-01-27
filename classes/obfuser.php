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

class OBFUser 
{

  private $db;
  private $load;
  private $io;

  // begin with anonymous user.
  public $userdata = null;

  public $is_admin = false;

  public function __construct()
  {
    $this->db = OBFDB::get_instance();
    $this->io = OBFIO::get_instance();
    $this->load = OBFLoad::get_instance();
  }

  static function &get_instance() 
  {
    static $instance;
  
    if (isset( $instance )) {
      return $instance;
    }

    $instance = new OBFUser();

    return $instance;
  }

  private function random_key()
  {

    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    $key = '';

    for($i=1;$i<64;$i++)
    {

      $key .= $chars[mt_rand(0,(strlen($chars)-1))];

    }

    return $key;

  }

  public function password_hash($pass)
  {
    return password_hash($pass.OB_HASH_SALT, PASSWORD_DEFAULT);
  }

  public function password_verify($pass, $hash)
  {
    // old (bad) hashing; fixed in later code + db update.
    $info = password_get_info($hash);
    if($info['algo']==0) return sha1(OB_HASH_SALT.$pass)==$hash;

    // good hashing.
    else return password_verify($pass.OB_HASH_SALT, $hash);
  }

  // login a user. returns key if successful, false otherwise.
  // loads userdata into $this->userdata.
  public function login($user,$pass) 
  {

    // check username / password.
    $this->db->where('username',$user);
    $this->db->where('enabled',1);    
    $result = $this->db->get_one('users');

    if($result && $result['password']=='')
    {
      return array(false,'Due to security updates, a password reset is required. Use "Forgot Password" to reset your password.');
    }

    // valid user and password verified?
    elseif($result && $this->password_verify($pass, $result['password']))
    {

      // if rehash required, do that and store in db.
      if(password_needs_rehash($result['password'], PASSWORD_DEFAULT))
      {
        $new_hash = $this->password_hash($pass);
        $this->db->where('id',$result['id']);
        $this->db->update('users',array('password'=>$new_hash));
      }

      // cache our userdata.
      $this->userdata=$result;

      // generate random key, salted sha1 hash key, write hashed key to database.  set key expirey.
      $key = $this->random_key();
      $key_expiry = strtotime('+1 hour');
      $this->db->where('id',$result['id']);
      $this->db->update('users', array('key'=>$this->password_hash($key), 'key_expiry'=>$key_expiry) );

      setcookie('ob_auth_id',$result['id'],0,'/',null,false,false);
      setcookie('ob_auth_key',$key,0,'/',null,false,false);

      // return key data.
      return array('id'=>$result['id'],'key'=>$key, 'key_expiry'=>$key_expiry);
    }

    else 
    {
      return array(false,'The login or password you have provided is incorrect.');
    } 

  }

  // logout 
  public function logout()
  {

    if($this->param('id')==0) return true;

    // remote key and expiry key in database.
    $this->db->where('id',$this->param('id'));
    $this->db->update('users',array('key' => '', 'key_expiry'=>0));

    // expire cookies in browser.
    setcookie('ob_auth_id','',time() - 3600,null,null,false,true);
    setcookie('ob_auth_key','',time() - 3600,null,null,false,true);

    return true;

  }

  // figure out if user is authenticated (logged in).  return session ID, or false for anonymous user.
  // loads userdata into $this->userdata.
  public function auth($id,$key)
  {

    // if anything missing, return false. didn't work.
    if(empty($id) || empty($key)) return false;

    // get salted sha1 hash of key, check database with key/user combo
    $this->db->where('id',$id);
    $this->db->where('key','','!=');
    $this->db->where('key_expiry',time(),'>=');
    $result = $this->db->get_one('users');

    // session exists and key match?
    if($result && $this->password_verify($key, $result['key'])) 
    {

      // cache our userdata.
      $this->userdata=$result;

      // add additional users settings
      $this->db->where('user_id',$result['id']);
      $settings = $this->db->get('users_settings');
      foreach($settings as $setting) $this->userdata[$setting['setting']] = $setting['value'];

      // update key expirey, return id, key, key_expiry.
      $key_expiry = strtotime('+1 hour');
      $last_access = time();
      $this->db->where('id',$result['id']);
      $this->db->update('users', array('key_expiry'=>$key_expiry,'last_access'=>$last_access) );

      // see if user is admin
      $this->db->where('user_id',$result['id']);
      $this->db->where('group_id',1);
      if($this->db->get_one('users_to_groups')) $this->is_admin=true;

      return true;

    }

    return false;

  }

  public function param($param)
  {

    if(empty($this->userdata)) 
    {
      if($param=='id') return 0; // anonymous user ID.
      else return false;
    }

    if(isset($this->userdata[$param])) return $this->userdata[$param];
    return false;

  }

  public function require_authenticated()
  {

    if($this->param('id')==0) 
    {
      $this->io->error(OB_ERROR_DENIED);
      die();
    }

  }

  public function check_permission($permission)
  {

    if($this->is_admin) return true;

    $permissions = $this->load->model('Permissions');

    return $permissions('check_permission',$permission,$this->param('id'));

  }

  public function require_permission($permission)
  {

    if($this->is_admin) return true;

    if($this->check_permission($permission)===FALSE) 
    {
      $this->io->error(OB_ERROR_DENIED);
      die();
    }

  }

}

