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

class Playlist extends OBFController 
{

  public function __construct()
  {
    parent::__construct();
    
    $this->user->require_authenticated();

    $this->PlaylistModel = $this->load->model('Playlists');
  }

  // get single playlist
  public function get()
  {

    $id = $this->data('id');

    $playlist = $this->PlaylistModel('get_by_id',$id);

    if($playlist) 
    {

      $playlist['items'] = $this->PlaylistModel('get_items',$id);
      if($playlist['type']=='live_assist') $playlist['liveassist_button_items'] = $this->PlaylistModel('get_liveassist_items',$id);

      // if playlist is private and not ours, require 'manage_playlists'.
      if($playlist['status']=='private' && $playlist['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_playlists');

      return array(true,'Playlist found.',$playlist);

    }
    

    return array(false,'Playlist not found.');

  }

  // get more playlist details
  public function get_details()
  {

    $id = $this->data('id');

    // TODO, can get these details even if we don't have access (private, non-owner).

    // get where used information...
    $where_used = $this->PlaylistModel('where_used',$id);
    $where_used = $where_used['used'];

    return array(true,'Playlist details.',$where_used);
  }

  // simple search
  public function playlist_search()
  {

    $query = $this->data('q');
    $limit = $this->data('l');
    $offset = $this->data('o');

    $sort_by = $this->data('sort_by');
    $sort_dir = $this->data('sort_dir');

    $my = $this->data('my');

    $search_result = $this->PlaylistModel('search',$query,$limit,$offset,$sort_by,$sort_dir,$my);

    return array(true,'Playlists',$search_result);

  }

  // addedit playlist
  public function edit()
  {

    $this->user->require_permission('manage_playlists or create_own_playlists');

    $media_model = $this->load->model('Media');

    $id = trim($this->data('id'));
    $name = trim($this->data('name'));
    $description = trim($this->data('description'));
    $status = trim($this->data('status'));
    $type = trim($this->data('type'));
    $items = $this->data('items');
    $liveassist_button_items = $this->data('liveassist_button_items');

    // if we have an id, see if id exists.  check permission if editing someone else's playlist.
    if(!empty($id))
    {
      $original_playlist = $this->PlaylistModel('get_by_id',$id);
      if(!$original_playlist) { return array(false,array('Playlist Edit','Unable To Edit')); }
      if($original_playlist['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_playlists');
    }

    // validate data.
    $validate_playlist = $this->PlaylistModel('validate_playlist', array('name'=>$name, 'status'=>$status, 'type'=>$type) );
    if($validate_playlist[0]==false) return array(false,array('Playlist Edit',$validate_playlist[1]));

    // check each playlist item.
    foreach($items as $item)
    {
      $validate_item = $this->PlaylistModel('validate_playlist_item',$item,$id);
      if($validate_item[0]==false) return array(false,array('Playlist Edit',$validate_item[1]));
    }

    // check each liveassist button item
    if($type=='live_assist' && is_array($liveassist_button_items)) foreach($liveassist_button_items as $liveassist_button_item)
    {
      $validate_item = $this->PlaylistModel('validate_liveassist_button_item',$liveassist_button_item);
      if($validate_item[0]==false) return array(false,array('Playlist Edit',$validate_item[1]));
    }

    // add/edit playlist entry.
    $data['name']=$name;
    $data['description']=$description;
    $data['status']=$status;
    $data['type']=$type;
    $data['updated']=time();

    if(!$id)
    {
      $data['created']=time();
      $data['owner_id']=$this->user->param('id');
      $id = $this->PlaylistModel('insert',$data);
    }

    else
    {
      $this->db->where('id',$id);
      $this->PlaylistModel('update',$data);

      // TODO this should use the schedule model.

      // delete schedule cache using this playlist.  can this be made more efficient (less queries)?
      $this->db->where('item_id',$id);
      $this->db->where('item_type','playlist');
      $schedules = $this->db->get('schedules');
  
      $this->db->where('item_id',$id);
      $this->db->where('item_type','playlist');
      $schedules = array_merge($schedules,$this->db->get('schedules_recurring'));

      foreach($schedules as $schedule)
      {
        $this->db->where('schedule_id',$schedule['id']);
        $this->db->where('mode',(!empty($schedule['mode']) ? 'recurring' : 'once'));
        $this->db->delete('schedules_media_cache');
      }


      // TODO use a model... (devices model)
      // delete default_playlist cache if this is a default playlist.

      // get a list of devices using this playlist as a default playlist
      $this->db->where('default_playlist_id',$id);
      $devices = $this->db->get('devices');

      foreach($devices as $device)
      {
        // remove default playlist cache for this device.
        $this->db->where('device_id',$device['id']);
        $this->db->where('mode','default_playlist');
        $this->db->delete('schedules_media_cache');
      }

      // TODO use a model... (liveassist model or playlist model?)
      // delete liveassist related cache for this playlist.
    
      $this->db->query('SELECT * FROM playlists_liveassist_buttons WHERE playlist_id = "'.$this->db->escape($id).'" OR button_playlist_id = "'.$this->db->escape($id).'"');
      $groups = $this->db->assoc_list();

      foreach($groups as $group)
      { 
        $this->db->where('playlists_liveassist_button_id',$group['id']);
        $this->db->delete('schedules_liveassist_buttons_cache');
      }

    }

    // at this point, we should have an ID.
    if(!$id) return array(false,array('Playlist Edit','Error While Saving'));

    // update our playlist items. first delete all items, then re-add them.
    $this->PlaylistModel('delete_items',$id);

    foreach($items as $index=>$item)
    {

      unset($data);
      $data=array();

      $data['playlist_id']=$id;
      $data['item_type']=$item['type'];
      $data['ord']=$index;

      if($item['type']=='media')
      {
        $data['item_id']=$item['id'];
        $data['duration']=$item['duration'];
      }
    
      elseif($item['type']=='dynamic')
      {
        $data['dynamic_num_items']=$item['num_items_all'] ? null : $item['num_items'];
        $data['dynamic_image_duration']=$item['image_duration'];
        $data['dynamic_query']=$item['query'];
        $data['dynamic_name']=$item['name'];
      }

      elseif($item['type']=='station_id')
      {
        // nothing special to set here.
      }
    
      elseif($item['type']=='breakpoint')
      {
        // nothing special to set here.
      }

      $this->db->insert('playlists_items',$data);

    }

    if($type=='live_assist' && is_array($liveassist_button_items))
      $this->PlaylistModel('update_liveassist_items',$id, $liveassist_button_items);

    return array(true,'Playlist saved.',$id);

  }

  // validate dynamic selection, provide duration estimate.
  public function validate_dynamic_properties($search_query=null,$num_items=null,$image_duration=null) {

    $this->user->require_permission('create_own_playlists or manage_playlists');

    if($search_query===null) $search_query = $this->data('search_query');

    $search_query = (array) $search_query; // might be object... want to make consistent.

    if($num_items===null) $num_items = trim($this->data('num_items'));
    if($num_items_all===null) $num_items_all = trim($this->data('num_items_all'));
    if($image_duration===null) $image_duration = trim($this->data('image_duration'));

    $validation = $this->PlaylistModel('validate_dynamic_properties',$search_query,$num_items,$num_items_all,$image_duration);

    if($validation[0]==false) return array(false,array('Playlist Dynamic Item Properties',$validation[1]));

    if($num_items_all) $num_items = null; // duration function uses empty num_items indicate 'all items' mode.

    // valid, also return some additional information.
    $validation[2]=array('duration'=>$this->PlaylistModel('dynamic_selection_duration',$search_query,$num_items,$image_duration));
    return $validation;

  }

  public function delete()
  {

    $this->user->require_permission('manage_playlists or create_own_playlists');

    $ids = $this->data('id');
      
    // if we just have a single ID, make it into an array so we can proceed on that assumption. 
    if(!is_array($ids)) $ids = array($ids);

    // make sure we have all our playlists. check permission.
    foreach($ids as $id)
    {

      $playlist = $this->PlaylistModel('get_by_id',$id);

      if(!$playlist) return array(false,'One or more playlists were not found.');

      if($playlist['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_playlists');

      // check where used, see if we have permission to remove from those.
      $where_used = $this->PlaylistModel('where_used',$id);
      if($where_used['can_delete']==false) return array(false,'Cannot delete one or more playlists as you do not have adequate permissions.');

    }
    
    // proceed with delete
    foreach($ids as $id)
    {
      $this->PlaylistModel('delete',$id);
    }

    return array(true,'Playlists have been deleted.');

  }

  public function used()
  {

    $ids = $this->data('id');
    if(!is_array($ids)) $ids = array($ids);

    $return = array();

    foreach($ids as $id) $return[]=$this->PlaylistModel('where_used',$id);

    return array(true,'Playlist where used information.',$return);

  }
  

}
