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

class Device extends OBFController
{

  public function __construct()
  {
    parent::__construct();
    $this->DevicesModel = $this->load->model('Devices');
  }

  public function device_list()
  {

    $this->user->require_authenticated();
  
    $params['filters'] = $this->data('filters');
    $params['orderby'] = $this->data('orderby');
    $params['orderdesc'] = $this->data('orderdesc');
    $params['limit'] = $this->data('limit');
    $params['offset'] = $this->data('offset');

    if($params['orderby'] == false) $params['orderby'] = 'name';

    $devices = $this->DevicesModel('get',$params);

    if($devices===false) return array(false,'An unknown error occurred while fetching devices.');

    foreach($devices as $index=>$device)
    {
      unset($devices[$index]['password']); // never need to share this.

      if(!$this->user->check_permission('manage_devices')) // hide sensitive info unless we are a device manager.
      {
        unset($devices[$index]['ip_address']);
      }
    }

    return array(true,'Device list.',$devices);

  }

  public function edit()
  {

    $this->user->require_permission('manage_devices');

    $id = trim($this->data('id'));
    $data['station_ids'] = $this->data('station_ids');

    $data['name'] = trim($this->data('name'));
    $data['description'] = $this->data('description');
    $data['stream_url'] = $this->data('stream_url');
    $data['password'] = $this->data('password');
    $data['ip_address'] = $this->data('ip_address');

    $data['support_audio'] = $this->data('support_audio');
    $data['support_video'] = $this->data('support_video');
    $data['support_images'] = $this->data('support_images');
    $data['support_linein'] = $this->data('support_linein');

    $data['timezone'] = trim($this->data('timezone'));
    $data['station_id_image_duration'] = trim($this->data('station_id_image_duration'));
    $data['default_playlist_id'] = $this->data('default_playlist'); 

    $data['parent_device_id'] = $this->data('parent_device_id');
    if(!empty($data['parent_device_id']))
    {
      $data['use_parent_dynamic'] = (int) $this->data('use_parent_dynamic');
      $data['use_parent_schedule'] = (int) $this->data('use_parent_schedule');
      $data['use_parent_ids'] = (int) $this->data('use_parent_ids');
      $data['use_parent_playlist'] = (int) $this->data('use_parent_playlist');
      $data['use_parent_emergency'] = (int) $this->data('use_parent_emergency');

      if($data['use_parent_ids']) 
      {
        $data['station_ids'] = array();
        $data['station_id_image_duration'] = 15; // this field will be hidden, set to default. it doesn't matter but needs to be valid.
      }

      if($data['use_parent_playlist']) $data['default_playlist_id'] = false;
    }
    else
    {
      $data['parent_device_id'] = null;
      $data['use_parent_dynamic'] = 0;
      $data['use_parent_schedule'] = 0;
      $data['use_parent_ids'] = 0;
      $data['use_parent_playlist'] = 0;
      $data['use_parent_emergency'] = 0;
    }

    $validation = $this->DevicesModel('validate',$data,$id);
    if($validation[0]==false) return $validation;

    $id = $this->DevicesModel('save',$data,$id);

    return array(true,['Device Manager','Saved Message', $id]);

  }

  public function delete()
  {

    $this->user->require_permission('manage_devices');

    $id = $this->data('id');

    if(empty($id)) return array(false,'A device ID is required.');

    $validation = $this->DevicesModel('delete_check_permission',$id);
    if($validation[0]==false) return $validation;

    if($this->DevicesModel('device_is_parent',$id)) return array(false,'This device is a parent device, and cannot be deleted.');

    $this->DevicesModel('delete',$id);

    return array(true,'Device deleted.');

  }

  public function get()
  {

    $this->user->require_authenticated();

    $id = $this->data('id');

    $device = $this->DevicesModel('get_one',$id);

    if($device)
    {
      unset($device['password']); // never need to share this.

      if(!$this->user->check_permission('manage_devices')) // hide sensitive info unless we are a device manager.
      {
        unset($device['ip_address']);
      }

      return array(true,'Device data',$device);
    }

    else return array(false,'Device not found.');
    
  }

  public function station_id_avg_duration()
  {
    $this->user->require_authenticated();
    $average = $this->DevicesModel('station_id_average_duration');
    return array(true,'Average duration for station IDs.',$average);
  }

  public function monitor_search()
  {

    $data['device_id'] = $this->data('device_id');
    $data['start'] = $this->data('start_timestamp');
    $data['end'] = $this->data('end_timestamp');

    $data['orderby'] = $this->data('orderby');
    $data['orderdesc'] = $this->data('orderdesc');

    $data['filters'] = $this->data('filters');

    $data['limit'] = $this->data('limit');
    $data['offset'] = $this->data('offset');

    if(!$data['orderby']) { $data['orderby'] = 'timestamp'; $data['orderdesc'] = true; }
    
    // validate device_id, check permission.
    if(!preg_match('/^[0-9]+$/',$data['device_id'])) return array(false,'Invalid device ID.');
    $this->user->require_permission('view_device_monitor:'.$data['device_id']);

    $result = $this->DevicesModel('monitor_search',$data);

    if($result[0] === false) return array(false,'An unknown error occurred while searching the playlog.');

    return array(true,'Playlog search results.',array('results'=>$result[0],'total_rows'=>$result[1]));

  }

  public function now_playing()
  {
    $device_id = $this->data('id');
    $return = $this->DevicesModel('now_playing',$device_id);
    return array(true,'Now playing.',$return);
  }

}
