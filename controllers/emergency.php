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

class Emergency extends OBFController 
{

  public function __construct()
  {
    parent::__construct();
    
    $this->user->require_authenticated();

    $this->EmergenciesModel = $this->load->model('Emergencies');

  }

  public function emergencies()
  {

    $id = trim($this->data('id'));
  
    $device_id = trim($this->data('device_id'));

    if(!empty($id)) 
    {

      $emergency = $this->EmergenciesModel('get_one',$id);
      if(!$emergency) return array(false,['Emergency','Not Found']);

      $this->user->require_permission('manage_emergency_broadcasts:'.$emergency['device_id']);
      return array(true,'Emergency broadcast.',$emergency);

    }
    else 
    {
      $this->user->require_permission('manage_emergency_broadcasts:'.$device_id);
      return array(true,'Emergency broadcasts.',$this->EmergenciesModel('get_for_device',$device_id));
    }

  }

  public function save_emergency()
  {

    $id = trim($this->data('id'));
    $data['item_id'] = trim($this->data('item_id'));

    $data['device_id'] = trim($this->data('device_id'));

    $data['name'] = trim($this->data('name'));

    $data['frequency'] = trim($this->data('frequency'));
    $data['duration'] = trim($this->data('duration'));
    $data['start'] = trim($this->data('start'));
    $data['stop'] = trim($this->data('stop'));
    
    $data['user_id']=$this->user->param('id');

    $validation = $this->EmergenciesModel('validate',$data,$id);
    if($validation[0]==false) return array(false,['Emergency',$validation[1]]);

    // check permission on this device.
    $this->user->require_permission('manage_emergency_broadcasts:'.$data['device_id']);

    $this->EmergenciesModel('save',$data,$id);

    return array(true,'Emergency broadcast saved.');

  }

  public function delete_emergency()
  {

    $id = trim($this->data('id'));

    $emergency = $this->EmergenciesModel('get_one',$id);
    if(!$emergency) return array(false,'Emergency broadcast not found.');
  
    // check permission on appropriate device.
    $this->user->require_permission('manage_emergency_broadcasts:'.$emergency['device_id']);

    $this->EmergenciesModel('delete',$id);

    return array(true,'Emergency deleted.');    

  }

}
