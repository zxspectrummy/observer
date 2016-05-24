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

class UsersModel extends OBFModel
{

  public function user_list()
  {
    $this->db->what('display_name');
    $this->db->what('id');
    $rows = $this->db->get('users');

    return $rows;
  }

  public function user_manage_list($sort_col='display_name',$sort_dir='asc')
  {

    $this->db->what('id');
    $this->db->what('name');
    $this->db->what('username');
    $this->db->what('email');
    $this->db->what('display_name');
    $this->db->what('enabled');
    $this->db->what('created');
    $this->db->what('last_access');

    $this->db->orderby($sort_col,$sort_dir);

    $rows = $this->db->get('users');

    // get user groups
    foreach($rows as $index=>$row)
    {
    
      $rows[$index]['groups']=array();

      $this->db->where('user_id',$row['id']);
      $this->db->leftjoin('users_groups','users_to_groups.group_id','users_groups.id');
      $groups = $this->db->get('users_to_groups');

      foreach($groups as $group)
      {
        $rows[$index]['groups'][]=array('id'=>$group['id'],'name'=>$group['name']);
      }

    }

    return $rows;

  }

  public function user_validate($data,$id=null)
  {

    foreach($data as $key=>$value) $$key=$value;

    // basic validation 
    if(empty($name) || empty($email) || empty($username) || empty($display_name)) return array(false,['User Edit', 'Required Not Filled']);

    if(empty($id) && (empty($password) || empty($password_confirm))) return array(false,['User Edit', 'Required Not Filled']);

    // email validation
    if(!PHPMailer::ValidateAddress($email)) return array(false,['User Edit', 'Invalid Email']);

    // make sure email not in use
    $this->db->where('email',$email);
    if(!empty($id)) $this->db->where('id',$id,'!=');
    if($this->db->get_one('users')) return array(false,['User Edit', 'Email In Use']);

    // make sure username not in use.
    $this->db->where('username',$username);
    if(!empty($id)) $this->db->where('id',$id,'!=');
    if($this->db->get_one('users')) return array(false,['User Edit', 'Username In Use']);

    // make sure passwords match.
    if(!empty($password) && $password!=$password_confirm) return array(false,['User Edit', 'Password Mismatch']);
    if(!empty($password) && strlen($password)<6) return array(false,['User Edit', 'Password Short']);

    return array(true,'Valid');

  }

  public function user_save($data,$id=null)
  {
    // add/edit now.
    $dbdata['name']=$data['name'];
    $dbdata['username']=$data['username'];
    $dbdata['email']=$data['email'];
    $dbdata['display_name']=$data['display_name'];
    $dbdata['enabled']=$data['enabled'];
    if(!empty($data['password'])) $dbdata['password']=$this->user->password_hash($data['password']);

    if(!empty($id))
    {
      $this->db->where('id',$id);
      $this->db->update('users',$dbdata);
    }

    else 
    {
      $dbdata['created']=time();
      $insert_id = $this->db->insert('users',$dbdata);
    }

    // handle groups
    if(!empty($id))
    {
      $this->db->where('user_id',$id);
      $this->db->delete('users_to_groups');
    }

    else $id = $insert_id;
    
    $group_data = array();
    $group_data['user_id']=$id;

    foreach($data['group_ids'] as $group_id)
    {
      $group_data['group_id']=$group_id;
      $this->db->insert('users_to_groups',$group_data);
    }

    return true;
  }

  public function user_delete($id)
  {

    $this->db->where('id',$id);
    $this->db->delete('users');

    $this->db->where('user_id',$id);
    $this->db->delete('users_to_groups');

    $this->db->where('user_id',$id);
    $this->db->delete('schedules_permissions');

    $this->db->where('user_id',$id);
    $this->db->delete('schedules_permissions_recurring');

    return true;

  }

  public function group_list()
  {
    $groups = $this->db->get('users_groups');

    foreach($groups as $index=>$group) 
    {
      if($group['id']==1) continue; // administrator has all permissions and not found in table.

      $groups[$index]['permissions'] = array();

      $this->db->what('users_permissions.name');
      $this->db->what('users_permissions.category');
      $this->db->what('users_permissions_to_groups.item_id');

      $this->db->where('group_id',$group['id']);
      
      $this->db->leftjoin('users_permissions','users_permissions_to_groups.permission_id','users_permissions.id');

      $permissions = $this->db->get('users_permissions_to_groups');

      foreach($permissions as $permission) 
      {
        $groups[$index]['permissions'][]=$permission['name'].($permission['item_id'] ? ':'.$permission['item_id'] : '');
      }

    }

    return $groups;
  }

  public function permissions_list()
  {

    $this->db->orderby('category');
    $permissions = $this->db->get('users_permissions');

    $return = array();

    $devices_model = $this->load->model('devices');
    $devices = $devices_model('get_all');

    foreach($permissions as $permission)
    {

      if($permission['category']=='device') 
      {
        foreach($devices as $device)
        {
          if(!isset($return['device: '.$device['name']])) $return['device: '.$device['name']] = array();

          $new_permission = $permission;
          $new_permission['name'] .= ':'.$device['id'];

          $return['device: '.$device['name']][] = $new_permission;
        }
        continue;
      }

      if(!isset($return[$permission['category']])) $return[$permission['category']] = array();
      $return[$permission['category']][] = $permission;
    }

    return $return;

  }



  public function group_delete($id)
  {
    $this->db->where('id',$id);
    $this->db->delete('users_groups');

    $this->db->where('group_id',$id);
    $this->db->delete('users_permissions_to_groups');

    $this->db->where('group_id',$id);
    $this->db->delete('users_to_groups');

    return true;
  }

  public function group_validate($data,$id=null)
  {

    foreach($data as $key=>$value) $$key=$value;

    // we require a name
    if($name=='') return array(false,['Permissions Edit','Group Name Required']);

    // we require valid permissions
    if(!is_array($permissions)) return array(false,['Permissions Edit','Permission Invalid']);

    foreach($permissions as $pname)
    {
      $pname_array = explode(':',$pname);
      $this->db->where('name',$pname_array[0]);
      if(!$this->db->get_one('users_permissions')) return array(false,['Permissions Edit','Permission Invalid']);
    }

    // we can't edit the admin group
    if($id==1) return array(false,['Permissions Edit','Cannot Delete Admin']);

    return array(true,'Valid.');

  }

  public function group_save($data,$id=null)
  {

    $dbdata['name']=$data['name'];

    if(!empty($id))
    {
      $this->db->where('id',$id);
      $this->db->update('users_groups',$dbdata);
    }

    else
    {
      $id = $this->db->insert('users_groups',$dbdata);
    }

    if(empty($id)) return false;

    // handle our permissions.  first deleting existing permissions for this group, then adding new permissions.
    $this->db->where('group_id',$id);
    $this->db->delete('users_permissions_to_groups');

    $pdata = array();
    $pdata['group_id']=$id;
    // $pdata['value']='true';

    foreach($data['permissions'] as $pname)
    {

      $pname_array = explode(':',$pname);

      $this->db->where('name',$pname_array[0]);
      $permission_info = $this->db->get_one('users_permissions');
      if(!$permission_info) continue;

      $pdata['permission_id'] = $permission_info['id'];

      // is permission associated with an item id? (like a device id specific permission...)
      if(count($pname_array)>1) $pdata['item_id'] = $pname_array[1];
      else $pdata['item_id']=null;

      $this->db->insert('users_permissions_to_groups',$pdata);

    }

    return true;

  }

  public function settings_validate($user_id,$data)
  {

    if(empty($data['name']) || empty($data['email']) || empty($data['display_name'])) return array(false,'Required Fields Not Filled');
    if(!PHPMailer::ValidateAddress($data['email'])) return array(false,'Email Address Invalid');

    // make sure email not in use
    $this->db->where('id',$user_id,'!=');
    $this->db->where('email',$data['email']);
    if($this->db->get_one('users')) return array(false,'Email Address In Use');

    // verify password.
    if(isset($data['password']) && $data['password']!='') {
      if($data['password']!=$data['password_again']) return array(false,'Passwords Do Not Match');
      elseif(strlen($data['password'])<6) return array(false,'Password Not Long Enough');
    }

    // make sure language is valid
    $ui_model = $this->load->model('ui');
    $languages = array_keys($ui_model->get_languages());
    if(array_search($data['language'],$languages)===false) return array(false,'Language Not Valid');

    // make sure theme is valid
    $themes = array_keys($ui_model->get_themes());
    if(array_search($data['theme'],$themes)===false) return array(false,'Theme Not Valid');

    return array(true,'');

  }

  public function settings_update($user_id,$data)
  {

    if(isset($data['password']) && $data['password']!='') $data['password']=$this->user->password_hash($data['password']);
    elseif(isset($data['password'])) unset($data['password']);

    if(isset($data['password_again'])) unset($data['password_again']);

    $settings = array();
    $settings['language'] = $data['language'];
    $settings['theme'] = $data['theme'];
    $settings['dyslexia_friendly_font'] = !empty($data['dyslexia_friendly_font']) ? 1 : 0;
    unset($data['language']);
    unset($data['theme']);
    unset($data['dyslexia_friendly_font']);

    $this->db->where('id',$user_id);
    $this->db->update('users',$data);

    foreach($settings as $setting=>$value)
    {
      $this->db->query('INSERT INTO users_settings (user_id, setting, value) 
        VALUES (
          "'.$this->db->escape($user_id).'",
          "'.$this->db->escape($setting).'",
          "'.$this->db->escape($value).'"
        ) ON DUPLICATE KEY UPDATE value = "'.$this->db->escape($value).'"');
    }

  }

  public function forgotpass_validate($email)
  {

    if(!$email) return array(false,'Email address is required.');

    $this->db->where('email',$email);
    $user=$this->db->get_one('users');

    if(!$user) return array(false,'The email address you have provided was not found.');

    return array(true,'');

  }
  
  public function forgotpass_process($email)
  {

    $password = $this->randpass();

    $password_hash = $this->user->password_hash($password);

    $this->db->where('email',$email);
    $user=$this->db->get_one('users');

    $this->db->where('id',$user['id']);
    $this->db->update('users',array('password'=>$password_hash));

    $this('email_username_password',$email,$user['username'],$password);

  }

  public function newaccount_validate($data)
  {

    $name = $data['name'];
    $email = $data['email'];
    $username = $data['username'];

    // basic validation 
    if(empty($name) || empty($email) || empty($username)) return array(false,'One or more required fields were not filled.');
    if(!PHPMailer::ValidateAddress($email)) return array(false,'The email address you have provided is not valid.');

    // make sure email not in use
    $this->db->where('email',$email);
    if($this->db->get_one('users')) return array(false,'The email address you have provided is already in use.  Use the <a href="javascript: account.forgotpass_window();">forgot password</a> function to get a new password.');

    // make sure username not in use.
    $this->db->where('username',$username);
    if($this->db->get_one('users')) return array(false,'The username you have selected is already in use.');

    return array(true,'');

  }

  public function newaccount_process($data)
  {

    $password = $this->randpass();

    $data['password']=$this->user->password_hash($password);
    $data['enabled']=1;
    $data['display_name']=$data['username'];
    $data['created']=time();

    $this->db->insert('users',$data);

    $this('email_username_password',$data['email'],$data['username'],$password);

  }


  public function randpass()
  {

    $password_chars = 'abcdefghijkmnopqrstuvwxyz23456789';
    $password = '';

    while(strlen($password)<8) $password.=$password_chars[rand(0,(strlen($password_chars)-1))];

    return $password;

  }

  public function email_username_password($email,$username,$password)
  {

    $mailer = new PHPMailer();

    $mailer->Body='Here is your username and new password for OpenBroadcaster.  You should immediately log in and reset your password.

Username: '.$username.'
Password: '.$password.'

Login at '.OB_SITE;

    $mailer->From=OB_EMAIL_REPLY;

    $mailer->FromName=OB_EMAIL_FROM;

    $mailer->Subject='Your OpenBroadcaster Account';

    $mailer->AddAddress($email);

    $mailer->Send();

  }

}
