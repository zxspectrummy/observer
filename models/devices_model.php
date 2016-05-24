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

class DevicesModel extends OBFModel
{

  public function get_one($id)
  {

    $this->db->where('id',$id);
    $device = $this->db->get_one('devices');

    if($device) {
      $device['station_ids']=$this('get_station_ids',$id);  
    }

    return $device;

  }

  public function get_all()
  {
    return $this->db->get('devices');
  }

  public function get($params)
  {

    foreach($params as $name=>$value) $$name=$value;

    if($filters) foreach($filters as $filter)
    {
      $column = $filter['column'];
      $value = $filter['value'];
      $operator = (empty($filter['operator']) ? '=' : $filter['operator']);

      $this->db->where($column,$value,$operator);
    }

    if($orderby) $this->db->orderby($orderby,(!empty($orderdesc) ? 'desc' : 'asc'));

    if($limit) $this->db->limit($limit);

    if($offset) $this->db->offset($offset);

    $result = $this->db->get('devices');

    if($result === false) return false;
  
    foreach($result as $index=>$row)
    {
  
      // get our default playlist name.
      if(!empty($row['default_playlist_id'])) 
      {

        $this->db->what('name');
        $this->db->where('id',$row['default_playlist_id']);
        $default_playlist = $this->db->get_one('playlists');

        $result[$index]['default_playlist_name']=$default_playlist['name'];

      }
    
      else 
      {
        $result[$index]['default_playlist_name']=null;
        $result[$index]['default_playlist_id']=null;
      }

      // get our station ids
      $result[$index]['media_ids']=array();

      $station_ids = $this('get_station_ids',$row['id']);
      foreach($station_ids as $station_id) 
      {   
        $this->db->where('id',$station_id);
        $media=$this->db->get_one('media');

        if($media) $result[$index]['media_ids'][]=$media;
      }

    }

    return $result;

  }

  // get station IDs for a device.
  public function get_station_ids($id)
  {

    $this->db->where('device_id',$id);
    $station_ids = $this->db->get('devices_station_ids');

    $media_ids = array();
    
    foreach($station_ids as $station_id) $media_ids[]=$station_id['media_id'];

    return $media_ids;

  }

  // very unintelligently guess at a station id duration. really, since this is used in playlists which are not tied to a device, 
  // and since station ID durations can vary considerably, this is probably going to be a pretty terrible estimate.
  public function station_id_average_duration()
  {
    $this->db->query('select sum(media.duration) as sum, count(*) as count from devices_station_ids left join media on devices_station_ids.media_id = media.id where media.type!="image"');
    $data = $this->db->assoc_list();
    $sum = $data[0]['sum'];
    $sum_count = $data[0]['count'];

    $devices = $this->get_all();

    foreach($devices as $device)
    {
      $this->db->query('select count(*) as count from devices_station_ids left join media on devices_station_ids.media_id = media.id where media.type="image" and devices_station_ids.device_id="'.$this->db->escape($device['id']).'"');
      $data = $this->db->assoc_list();

      $sum += $data['0']['count'] * $device['station_id_image_duration'];
      $sum_count += $data[0]['count'];
    }    

    if($sum_count==0) return 0; // no station IDs? then duration is zero.
    return $sum/$sum_count;
  }

  public function validate($data,$id=false)
  {

    $error = false;

    if(empty($data['name'])) $error = 'A device name is required.';

    elseif(isset($data['stream_url']) && $data['stream_url']!='' && !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $data['stream_url'])) $error = 'The stream URL is not valid.  Only HTTP(s) is supported.';

    elseif(empty($data['password']) && !$id) $error = 'A device password is required.'; // only required for new devices. if password not specified for existing devices, no password change will occur.

    elseif(!empty($data['password']) && strlen($data['password'])<6) $error = 'The password must be at least 6 characters long.';

    elseif($id && !$this->db->id_exists('devices',$id)) $error = 'The device you are attempted to edit does not exist.';

    elseif(!preg_match('/^[0-9]+$/',$data['station_id_image_duration']) || $data['station_id_image_duration']==0) $error = 'Station ID image duration is not valid.  Enter a number to specify duration in seconds.';

    // verify timezone
    elseif(empty($data['timezone'])) $error = 'You must set a timezone for each device.';

    // make sure device name is unique
    if(empty($error))
    {
      if($id) $this->db->where('id',$id,'!=');
      $this->db->where('name',$data['name']);
      if($this->db->get_one('devices')) $error = 'Device name must be unique.';
    }

    if(empty($error))
    {

      try 
      {
        $tz_test = new DateTimeZone($data['timezone']);
      }
      catch(Exception $e)
      {
        $tz_test = false;
      }

      if(!$tz_test) $error = 'There was an error setting the timezone.';

    }

    // make sure parent device is valid.
    if(empty($error) && $data['parent_device_id'])
    {
      $this->db->where('id',$data['parent_device_id']);
      $parent_device = $this->db->get_one('devices');

      if(!$parent_device) $error = 'The specified parent device no longer exists.';
      elseif($parent_device['parent_device_id']!=0) $error = 'This parent device cannot be used.  Devices that act as child devices cannot be used as parents.';  
    }

    // verify station IDs.
    if(is_array($data['station_ids']) && !$error) 
    {
      foreach($data['station_ids'] as $station_id) 
      {
        $this->db->where('id',$station_id);
        $media_info = $this->db->get_one('media');
        if(!$media_info) { $error = 'A station ID you have selected no longer exists.'; break; }
        if($media_info['is_archived']==1 || $media_info['is_approved']==0) { $error = 'Station IDs may be approved media only.'; break; }
      }
    }

    // verify playlist ID
    if(!$error && !empty($data['default_playlist_id'])) if(!$this->db->id_exists('playlists',$data['default_playlist_id'])) $error = 'The playlist you have selected no longer exists.';
  
    if($error) return array(false,$error);

    return array(true,'');
  }

  public function save($data,$id=false)
  {

    $station_ids = $data['station_ids'];
    unset($data['station_ids']);

    if(!$data['use_parent_schedule']) $data['use_parent_dynamic']=0;

    if(!$id)
    {
      $data['password'] = password_hash($data['password'].OB_HASH_SALT, PASSWORD_DEFAULT);
      $data['owner_id'] = $this->user->param('id');
      $id = $this->db->insert('devices',$data);
      if(!$id) return false;
    }

    else
    {

      // get original device, see if we're updating default playlist.
      $this->db->where('id',$id);
      $original_device = $this->db->get_one('devices');
  
      // do we need to clear out all the cache? (child/parent setting change)
      if($original_device['use_parent_dynamic']!=$data['use_parent_dynamic']
          || $original_device['use_parent_schedule']!=$data['use_parent_schedule']
          || $original_device['use_parent_ids']!=$data['use_parent_ids']
          || $original_device['use_parent_playlist']!=$data['use_parent_playlist'])
      {
        $this->db->where('device_id',$id);
        $this->db->delete('schedules_media_cache');
      }

      // if we are changing the default playlist, clear the default playlist schedule cache for this device
      elseif($original_device['default_playlist_id']!=$data['default_playlist_id']) 
      {
        $this->db->where('device_id',$id);
        $this->db->where('mode','default_playlist');
        $this->db->delete('schedules_media_cache');
      }

      // unset the password if empty - we don't want to change. otherwise, set as hash.
      if($data['password']=='') unset($data['password']);
      else $data['password'] = password_hash($data['password'].OB_HASH_SALT, PASSWORD_DEFAULT);

      $this->db->where('id',$id);
      $update = $this->db->update('devices',$data);

      if(!$update) return false;

    }

    $station_id_data['device_id']=$id;
    if($station_ids!==false)
    {

      // delete all station IDs for this device.
      $this->db->where('device_id',$id);
      $this->db->delete('devices_station_ids');

      // add all the station IDs we have.
      if(is_array($station_ids)) foreach($station_ids as $station_id) 
      { 
        $station_id_data['media_id']=$station_id;
        $this->db->insert('devices_station_ids',$station_id_data);
      }

    }

    return $id;

  }

  public function update_version($id,$version)
  {
    $this->db->where('id',$id);
    $this->db->update('devices',array('version'=>$version));
  }

  public function delete_check_permission($id)
  {

    // see if there are emergency broadcasts associated with this device.
    $this->db->where('device_id',$id);
    if($this->db->get_one('emergencies') && !$this->user->check_permission('manage_emergency_broadcasts')) 
      return array(false,'Unable to remove this device.  It has emergency broadcast content that you do not have permission to delete.');

    // this doesn't check 'able to delete own show' ability... not sure it's practically necessary..
    $schedule_fail = false;

    $this->db->where('device_id',$id);
    if($this->db->get_one('schedules') && !$this->user->check_permission('manage_schedule_permissions')) $schedule_fail = true;
    $this->db->where('device_id',$id);
    if($this->db->get_one('schedules_recurring') && !$this->user->check_permission('manage_schedule_permissions')) $schedule_fail = true;
    $this->db->where('device_id',$id);
    if($this->db->get_one('schedules_permissions') && !$this->user->check_permission('manage_schedule_permissions')) $schedule_fail = true;
    $this->db->where('device_id',$id);
    if($this->db->get_one('schedules_permissions_recurring') && !$this->user->check_permission('manage_schedule_permissions')) $schedule_fail = true;

    if($schedule_fail) return array(false,'Unable to remove this device.  It has schedule data that you do not have permission to delete.');
  
    return array(true,'');
    
  }

  public function device_is_parent($id)
  {
    $this->db->where('parent_device_id',$id);
    $test = $this->db->get_one('devices');

    if($test) return true;
    else return false;
  }

  public function delete($id)
  {
    $this->db->where('id',$id);
    $this->db->delete('devices');

    $this->db->where('device_id',$id);
    $this->db->delete('devices_station_ids');

    $this->db->where('device_id',$id);
    $this->db->delete('emergencies');

    $this->db->where('device_id',$id);
    $this->db->delete('schedules');
    $this->db->where('device_id',$id);
    $this->db->delete('schedules_recurring');
    $this->db->where('device_id',$id);
    $this->db->delete('schedules_permissions');
    $this->db->where('device_id',$id);
    $this->db->delete('schedules_permissions_recurring');
  }

  public function monitor_search($params)
  {

    foreach($params as $name=>$value) $$name=$value;

    $this->db->where('device_id',$device_id);
    $this->db->where('timestamp',$start,'>=');
    $this->db->where('timestamp',$end,'<=');

    if($orderby) $this->db->orderby($orderby,(!empty($orderdesc) ? 'desc' : 'asc'));
    if($limit) $this->db->limit($limit);
    if($offset) $this->db->offset($offset);

    if($filters) foreach($filters as $filter)
    {
      $column = $filter['column'];
      $value = $filter['value'];
      $operator = $filter['operator'];

      if(array_search($column,array('media_id','artist','title'))===false) return array(false,null);
      if(array_search($operator,array('is','not','like','not_like'))===false) return array(false,null);

      if($operator=='like') $this->db->where_like($column,$value);
      elseif($operator=='not_like') $this->db->where_not_like($column,$value);
      else $this->db->where($column,$value,($operator=='is' ? '=' : '!='));

    }

    $this->db->calc_found_rows();

    $results = $this->db->get('playlog');
    $numrows = $this->db->found_rows();

    return array($results,$numrows);

  }

  public function now_playing($device_id)
  {

    $this->db->what('current_playlist_id');
    $this->db->what('current_playlist_end');
    $this->db->what('current_media_id');
    $this->db->what('current_media_end');
    $this->db->what('current_show_name');

    $this->db->where('id',$device_id);
    $device = $this->db->get_one('devices');

    if(!$device) return false;

    $return = array();
    $return['show_name']=$device['current_show_name'];
    $return['show_time_left']=$device['current_playlist_end'] - time();

    $media_model = $this->load->model('media');
    $media_model('get_init');

    $this->db->where('media.id',$device['current_media_id']);
    $media = $this->db->get_one('media');

    $media_data = array();
    $media_data['id']=$media['id'];
    $media_data['title']=$media['title'];
    $media_data['album']=$media['album'];
    $media_data['artist']=$media['artist'];
    $media_data['year']=$media['year'];
    $media_data['category_id']=$media['category_id'];
    $media_data['category_name']=$media['category_name'];
    $media_data['country_id']=$media['country_id'];
    $media_data['country_name']=$media['country_name'];
    $media_data['language_id']=$media['language_id'];
    $media_data['language_name']=$media['language_name'];
    $media_data['genre_id']=$media['genre_id'];
    $media_data['genre_name']=$media['genre_name'];
    $media_data['duration']=$media['duration'];
    $media_data['time_left']=$device['current_media_end']-time();

    $return['media']=$media_data;

    return $return;

  }

}
