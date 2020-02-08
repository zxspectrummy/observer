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

class ClientStorageModel extends OBFModel
{

  public function validate($data)
  {

    if(empty($data['client_name']) || $data['data']===false || !preg_match('/^[0-9]+$/',$data['user_id']) )
    {
      return array(false,'Invalid client name, data, or user.');
    }

    return array(true,'');

  }

  public function store($data)
  {

    // see if we already have a row for this client_name / user_id.
    $this->db->where('user_id',$data['user_id']);
    $this->db->where('client_name',$data['client_name']);
    $rows = $this->db->get('client_storage');

    if(!empty($rows) && count($rows)>0)
    {
      $this->db->where('user_id',$data['user_id']);
      $this->db->where('client_name',$data['client_name']);
      $this->db->update('client_storage',array('data'=>$data['data']));
    }

    else
      $this->db->insert('client_storage',array('user_id'=>$data['user_id'],'client_name'=>$data['client_name'],'data'=>$data['data']));

  }

  public function get($client_name,$user_id)
  {

    if(empty($client_name) || !preg_match('/^[0-9]+$/',$user_id) )
    {
      return array(false,'Invalid client name or user.');
    }

    // see if we already have a row for this client_name / user_id.
    $this->db->where('user_id',$user_id);
    $this->db->where('client_name',$client_name);
    $data = $this->db->get_one('client_storage');

    if($data==false) return array(true,'No data found.','');
    else return array(true,'Stored data found.',$data['data']);

  }

}
