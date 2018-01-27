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

class EmergenciesModel extends OBFModel
{

  public function get_init()
  {

    $this->db->what('media.title','title');
    $this->db->what('media.artist','artist');
    $this->db->what('media.type','item_type');
    $this->db->what('media.duration','item_duration');
    $this->db->what('emergencies.id','id');
    $this->db->what('emergencies.item_id','item_id');
    $this->db->what('emergencies.duration','duration');
    $this->db->what('emergencies.frequency','frequency');
    $this->db->what('emergencies.name','name');
    $this->db->what('emergencies.start','start');
    $this->db->what('emergencies.stop','stop');
    $this->db->what('emergencies.device_id','device_id');

    $this->db->leftjoin('media','emergencies.item_id','media.id');

  }

  public function get_one($id)
  {

    $this('get_init');

    $this->db->where('emergencies.id',$id); 

    $emergency = $this->db->get_one('emergencies');

    if(!$emergency) return false;

    $emergency['item_name']=$emergency['artist'].' - '.$emergency['title'];
    unset($emergency['artist']);
    unset($emergency['title']);

    if($emergency['item_type']!='image') $emergency['duration']=$emergency['item_duration'];
    unset($emergency['item_duration']);

    return $emergency;

  }
  
  public function get_for_device($device_id)
  {

    $this('get_init');
  
    $this->db->where('emergencies.device_id',$device_id);

    $emergencies = $this->db->get('emergencies');

    foreach($emergencies as $index=>$emergency) 
    {
      $emergencies[$index]['item_name']=$emergency['artist'].' - '.$emergency['title'];
      unset($emergencies[$index]['artist']);
      unset($emergencies[$index]['title']);

      if($emergencies[$index]['item_type']!='image') $emergencies[$index]['duration']=$emergencies[$index]['item_duration'];
      unset($emergencies[$index]['item_duration']);
    }

    return $emergencies;

  }

  public function validate($data,$id=false)
  {

    foreach($data as $key=>$value) $$key=$value;

    // required fields?
    if(empty($name) || empty($device_id) || empty($item_id) || empty($frequency) || empty($start) || empty($stop)) return array(false,'Required Field Missing');

    // check if ID is valid (if editing)
    if(!empty($id)) 
    {
      if(!$this->db->id_exists('emergencies',$id)) return array(false,'Item Does Not Exist');
    }

    // check if device ID is valid
    if(!$this->db->id_exists('devices',$device_id)) return array(false,'Device Does Not Exist');

    // check if media ID is valid
    if(empty($item_id)) return array(false,'Media Invalid');
    $this->db->where('id',$item_id);
    $media = $this->db->get_one('media');
    if(!$media) return array(false,'Media Invalid');
    if($media['is_approved']==0) return array(false,'Media Not Approved');
    if($media['is_archived']==1) return array(false,'Media Is Archived');

    // is frequency valid?
    if(!preg_match('/^[0-9]+$/',$frequency) || $frequency < 1) return array(false,'Frequency Invalid');

    // is duration valid? only needed for images...
    if($media['type']=='image' && (!preg_match('/^[0-9]+$/',$duration) || $duration < 1)) return array(false,'Duration Invalid');

    // is start/stop valid?
    if(!preg_match('/^[0-9]+$/',$start)) return array(false,'Start DateTime Invalid');
    if(!preg_match('/^[0-9]+$/',$stop)) return array(false,'Stop DateTime Invalid');
    if($start >= $stop) return array(false,'Stop Must Be After Start');

    return array(true,'');

  }

  public function save($data,$id=false)
  {
    $this->db->where('id',$data['item_id']);
    $media = $this->db->get_one('media');
    if($media['type']!='image') unset($data['duration']); // duration not needed unless this is an image.

    if(empty($id)) $this->db->insert('emergencies',$data);

    else
    {
      $this->db->where('id',$id);
      $this->db->update('emergencies',$data);
    }
  }

  public function delete($id)
  {
    $this->db->where('id',$id);
    $this->db->delete('emergencies');
  }

}
