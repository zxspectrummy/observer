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

class PlaylistsModel extends OBFModel
{

  // insert a playlist
  public function insert($data)
  {
    return $this->db->insert('playlists',$data);
  }

  // update a playlist
  public function update($data)
  {
    return $this->db->update('playlists',$data);
  }

  public function update_permissions_users($playlist_id, $user_ids)
  {
    if(!is_array($user_ids)) return false;

    $this->db->where('playlist_id',$playlist_id);
    $this->db->delete('playlists_permissions_users');

    foreach($user_ids as $user_id)
    {
      $this->db->insert('playlists_permissions_users',['playlist_id'=>$playlist_id, 'user_id'=>$user_id]);
    }

    return true;
  }

  public function update_permissions_groups($playlist_id, $group_ids)
  {
    if(!is_array($group_ids)) return false;

    $this->db->where('playlist_id',$playlist_id);
    $this->db->delete('playlists_permissions_groups');

    foreach($group_ids as $group_id)
    {
      $this->db->insert('playlists_permissions_groups',['playlist_id'=>$playlist_id, 'group_id'=>$group_id]);
    }

    return true;
  }

  public function get_permissions($playlist_id)
  {
    $return = [];
    $return['groups'] = [];
    $return['users'] = [];

    $this->db->where('playlist_id',$playlist_id);
    $groups = $this->db->get('playlists_permissions_groups');
    foreach($groups as $group) $return['groups'][] = (int) $group['group_id'];

    $this->db->where('playlist_id',$playlist_id);
    $users = $this->db->get('playlists_permissions_users');
    foreach($users as $user) $return['users'][] = (int) $user['user_id'];

    return $return;
  }

  // get a playlist by ID
  public function get_by_id($id)
  {

    $this->db->what('playlists.*');
    $this->db->what('users.display_name', 'owner_name');
    $this->db->where('playlists.id',$id);
    $this->db->leftjoin('users','playlists.owner_id','users.id');

    $playlist = $this->db->get_one('playlists');

    return $playlist;

  }

  // get playlist items for playlist ID
  public function get_items($id)
  {

    $return = array();

    // get playlist items.
    $this->db->orderby('playlists_items.ord');

    $this->db->what('playlists_items.duration','duration');
    $this->db->where('playlist_id',$id);
    $this->db->what('playlists_items.ord','ord');
    $this->db->what('playlists_items.item_type','type');

    $this->db->what('playlists_items.dynamic_name','dynamic_name');
    $this->db->what('playlists_items.dynamic_num_items','dynamic_num_items');
    $this->db->what('playlists_items.dynamic_image_duration','dynamic_image_duration');
    $this->db->what('playlists_items.dynamic_query','dynamic_query');

    $this->db->what('media.id','id');
    $this->db->what('media.type','media_type');
    $this->db->what('media.title','title');
    $this->db->what('media.artist','artist');
    $this->db->what('media.owner_id','owner_id');
    $this->db->what('media.status','status');

    $this->db->leftjoin('media','playlists_items.item_id','media.id');

    $items=$this->db->get('playlists_items');

    if($items) foreach($items as $item)
    {

      // for media type, provide 'audio' 'video' or 'image' instead.
      if($item['type']=='media')
      {
        $item['type']=$item['media_type'];
      }

      // we don't use this (merged into 'type')
      unset($item['media_type']);

      // for dynamic items, provide a time estimate.
      if($item['type']=='dynamic')
      {
        $item['dynamic_duration']=$this('dynamic_selection_duration',json_decode($item['dynamic_query']),$item['dynamic_num_items'],$item['dynamic_image_duration']);
      }

      $return[]=$item;
    }

    return $return;

  }

  public function get_liveassist_items($id)
  {
    $this->db->what('playlists.id','id');
    $this->db->what('playlists.name','name');
    $this->db->what('playlists.description','description');

    $this->db->orderby('playlists_liveassist_buttons.order_id');
    $this->db->where('playlists_liveassist_buttons.playlist_id',$id);
    $this->db->leftjoin('playlists','playlists_liveassist_buttons.button_playlist_id','playlists.id');
    return $this->db->get('playlists_liveassist_buttons');
  }

  // provide information about where the playlist is used.
  public function where_used($id)
  {

    $info = array();

    $info['used']=array();
    $info['id']=$id;
    $info['can_delete']=true;

    // is this used on a schedule?
    $this->db->what('devices.id','device_id');
    $this->db->what('devices.name','device_name');
    $this->db->what('schedules.user_id','user_id');
    $this->db->what('schedules.id','id');
    $this->db->where('item_id',$id);
    $this->db->where('item_type','playlist');
    $this->db->leftjoin('devices','schedules.device_id','devices.id');
    $schedules = $this->db->get('schedules');

    foreach($schedules as $schedule)
    {

      if($schedule['user_id']!=$this->user->param('id') && !$this->user->check_permission('manage_schedule_permissions')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'schedule';
      $used_data->name = $schedule['device_name']; // for device
      $used_data->id = $schedule['id'];
      $used_data->user_id = $schedule['user_id'];

      $info['used'][] = $used_data;
    }

    // is this used on a schedule (recurring)?
    $this->db->what('devices.id','device_id');
    $this->db->what('devices.name','device_name');
    $this->db->what('schedules_recurring.user_id','user_id');
    $this->db->what('schedules_recurring.id','id');
    $this->db->where('item_id',$id);
    $this->db->where('item_type','playlist');
    $this->db->leftjoin('devices','schedules_recurring.device_id','devices.id');
    $schedules = $this->db->get('schedules_recurring');

    foreach($schedules as $schedule)
    {

      if($schedule['user_id']!=$this->user->param('id') && !$this->user->check_permission('manage_schedule_permissions')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'recurring schedule';
      $used_data->name = $schedule['device_name']; // for device
      $used_data->id = $schedule['id'];
      $used_data->user_id = $schedule['user_id'];

      $info['used'][] = $used_data;
    }

    // is this used as a default playlist?
    $this->db->what('id','device_id');
    $this->db->what('name','device_name');
    $this->db->where('default_playlist_id',$id);
    $devices = $this->db->get('devices');

    foreach($devices as $device)
    {
      $used_data = new stdClass();
      $used_data->where = 'default playlist';
      $used_data->name = $device['device_name']; // for device
      $used_data->id = $device['device_id'];
      $used_data->user_id = false;

      $info['used'][] = $used_data;
    }

    // is this used as a liveassist button playlist?
    $this->db->where('button_playlist_id',$id);
    $this->db->leftjoin('playlists','playlists_liveassist_buttons.playlist_id','playlists.id');
    $liveassist_playlists = $this->db->get('playlists_liveassist_buttons');

    foreach($liveassist_playlists as $playlist)
    {
      $used_data = new stdClass();
      $used_data->where = 'liveassist buttons';
      $used_data->name = $playlist['name']; // for playlist
      $used_data->id = $playlist['id'];
      $used_data->user_id = false;

      $info['used'][] = $used_data;
    }

    return $info;

  }

  // search for playlists
  public function search($query,$limit,$offset,$sort_by,$sort_dir,$my=false)
  {

    $where_strings = array();

    if($query!=='' && $query!==false && $query!==null) $where_strings[] = '(name LIKE "%'.$this->db->escape($query).'%" OR description LIKE "%'.$this->db->escape($query).'%")';
    if(!$this->user->check_permission('manage_playlists')) $where_strings[] = '(status = "public" or owner_id = "'.$this->db->escape($this->user->param('id')).'")';

    // limit results to those owned by the presently logged in user.
    if($my) $where_strings[]='owner_id = "'.$this->db->escape($this->user->param('id')).'"';

    if(count($where_strings)>0) $this->db->where_string(implode(' AND ',$where_strings));

    if(!empty($offset)) $this->db->offset($offset);
    if(!empty($limit)) $this->db->limit($limit);

    // otherwise, if posted sort by data is valid, use that...
    if( ($sort_dir =='asc' || $sort_dir == 'desc') && array_search($sort_by, array('name','description','updated'))!==false )
    {
      $this->db->orderby($sort_by,$sort_dir);
    }

    // otherwise, show the most recently updated first
    else $this->db->orderby('updated','desc');

    $this->db->calc_found_rows();

    $playlists = $this->db->get('playlists');

    return array('num_results'=>$this->db->found_rows(),'playlists'=>$playlists);

  }

  // validate playlist
  public function validate_playlist($data)
  {

    //T A playlist name is required.
    if(empty($data['name'])) return array(false,'A playlist name is required.');
    //T A valid status is required.
    if($data['status'] != 'private' && $data['status'] != 'public') return array(false,'A valid status is required.');
    //T A valid type is required.
    if($data['type'] != 'standard' && $data['type'] != 'advanced' && $data['type'] != 'live_assist') return array(false,'A valid type is required.');

    return array(true,'Playlist is valid.');

  }

  // validate a single playlist item
  public function validate_playlist_item($item,$playlist_id = null)
  {

    if($playlist_id) $original_playlist = $this('get_by_id',$playlist_id);

    //T One or more playlist items are not valid.
    if($item['type']!='media' && $item['type']!='dynamic' && $item['type']!='station_id' && $item['type']!='breakpoint') return array(false,'One or more playlist items are not valid.');

    if($item['type']=='media')
    {

      //T One or more media durations are invalid or zero.
      if(empty($item['duration']) || !preg_match('/^[0-9]+(\.[0-9]+)?$/',$item['duration']) || $item['duration']<=0) return array(false,'One or more media durations are invalid or zero.');

      $this->db->where('id',$item['id']);
      $media = $this->db->get_one('media');

      //T One or more playlist items are not valid.
      if(!$media) return array(false,'One or more playlist items are not valid.');

      //T Only approved, unarchived media can be used in playlists.
      if($media['is_approved']==0 || $media['is_archived']==1) return array(false,'Only approved, unarchived media can be used in playlists.');

      // can't use private media that isn't ours unless we have 'manage_media' permission.
      if($media['status']=='private' && $media['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_media');

      // can't add private media to a playlist with a different owner.
      //T A media item is marked as private. It can only be used in playlists created by the same owner.
      if(!$playlist_id && $media['status']=='private' && $media['owner_id']!=$this->user->param('id')) return array(false,'A media item is marked as private. It can only be used in playlists created by the same owner.');
      //T A media item is marked as private. It can only be used in playlists created by the same owner.
      if($playlist_id && $media['status']=='private' && $media['owner_id']!=$original_playlist['owner_id']) return array(false,'A media item is marked as private. It can only be used in playlists created by the same owner.');

    }

    elseif($item['type']=='dynamic')
    {
      $dynamic_validation = $this('validate_dynamic_properties',json_decode($item['query']),$item['num_items'],$item['num_items_all'],$item['image_duration']);
      //T One or more dynamic playlist items are not valid.
      if($dynamic_validation[0]==false) return array(false,'One or more dynamic playlist items are not valid.');
    }

    return array(true,'Playlist item is valid.');

  }

  public function validate_liveassist_button_item($playlist_id)
  {
    if($this->db->id_exists('playlists',$playlist_id))
      return array(true,'Live Assist button item is valid.');
    //T One or more LiveAssist button playlists are invalid.
    else return array(false,'One or more LiveAssist button playlists are invalid.');
  }

  // validate dynamic properties
  public function validate_dynamic_properties($search_query,$num_items,$num_items_all,$image_duration)
  {

    $search_query = (array) $search_query; // convert to array (maybe comes in as object?)

    //T The number of items is invalid.
    if(!$num_items_all && (!preg_match('/^[0-9]+$/',$num_items) || $num_items=='0')) return array(false,'The number of items is invalid.');
    //T The image duration is invalid.
    if(!preg_match('/^[0-9]+$/',$image_duration) || $image_duration=='0') return array(false,'The image duration is invalid.');

    if($search_query['mode']=='advanced') foreach($search_query['filters'] as $filter)
    {

      $filter = (array) $filter;

      // make sure our search field and comparison operator is valid
      // TODO fix code duplication with media model
      $allowed_filters = ['comments','artist','title','album','year','type','category','country','language','genre','duration','is_copyright_owner'];
      $metadata_model = $this->load->model('MediaMetadata');
      $metadata_fields = $metadata_model('get_all');
      foreach($metadata_fields as $metadata_field)
      {
        $allowed_filters[] = 'metadata_'.$metadata_field['name'];
      }

      //T Invalid search criteria.
      if(array_search($filter['filter'],$allowed_filters)===false) return array(false,'Invalid search criteria.');

      //T Invalid search criteria.
      if(array_search($filter['op'],array('like','not_like','is','not','gte','lte'))===false) return array(false,'Invalid search criteria.');

    }

    return array(true,'Dynamic selection is valid.');

  }

  public function update_liveassist_items($playlist_id, $items)
  {

    $this->db->where('playlist_id',$playlist_id);
    $this->db->delete('playlists_liveassist_buttons');

    $ord = 0;

    foreach($items as $item)
    {
      $data = array();
      $data['playlist_id'] = $playlist_id;
      $data['order_id'] = $ord;
      $data['button_playlist_id'] = $item;

      $this->db->insert('playlists_liveassist_buttons', $data);

      $ord++;
    }

    return true;

  }

  // figure out the dynamic selection duration (estimated unless 'all items' selected)
  public function dynamic_selection_duration($search_query,$num_items,$image_duration)
  {

    $search_query = (array) $search_query; // convert to array (might come in as object with json_decode)

    $where = array();

    // simple mode
    if($search_query['mode']=='simple') $where[] = '(artist like "%'.$this->db->escape($search_query['string']).'%" or title like "%'.$this->db->escape($search_query['string']).'%") and is_approved = 1 and is_archived = 0';

    // advanced mode.  Filters should be already validated!
    else
    {

      $filters = $search_query['filters'];

      foreach($filters as $filter)
      {

        $filter = (array) $filter;

        // TODO fix code duplication with media model

        // our possible column (mappings)
        $column_array = array();
        $column_array['artist']='media.artist';
        $column_array['title']='media.title';
        $column_array['album']='media.album';
        $column_array['year']='media.year';
        $column_array['type']='media.type';
        $column_array['category']='media.category_id';
        $column_array['country']='media.country_id';
        $column_array['language']='media.language_id';
        $column_array['genre']='media.genre_id';
        $column_array['duration']='media.duration';
        $column_array['comments']='media.comments';

        $metadata_model = $this->load->model('MediaMetadata');
        $metadata_fields = $metadata_model('get_all');
        foreach($metadata_fields as $metadata_field)
        {
          $column_array['metadata_'.$metadata_field['name']] = 'media_metadata.'.$metadata_field['name'];
        }

        // our possibile comparison operators
        $op_array = array();
        $op_array['like'] = 'LIKE';
        $op_array['not_like'] = 'NOT LIKE';
        $op_array['is'] = '=';
        $op_array['not'] = '!=';
        $op_array['gte'] = '>=';
        $op_array['lte'] = '<=';

        // put together our query segment
        $tmp_sql = $column_array[$filter['filter']] .' '. $op_array[$filter['op']] . ' "';

        if($filter['op']=='like' || $filter['op']=='not_like') $tmp_sql .= '%';

        $tmp_sql .= $this->db->escape($filter['val']);

        if($filter['op']=='like' || $filter['op']=='not_like') $tmp_sql .= '%';

        $tmp_sql.='"';

        $where[]=$tmp_sql;

      }

    }

    // 'all items' selected.
    if(empty($num_items))
    {
      $this->db->query('select sum(if(duration is null, '.$image_duration.', duration)) as total from media left join media_metadata on media.id=media_metadata.media_id where '.implode(' AND ',$where));
      $result = $this->db->assoc_list();

      if(empty($result[0]['total'])) return 0;
      return $result[0]['total'];
    }

    else
    {
      // complete
      $this->db->query('select avg(if(duration is null, '.$image_duration.', duration)) as avg from media left join media_metadata on media.id=media_metadata.media_id where '.implode(' AND ',$where));
      $result = $this->db->assoc_list();

      if(empty($result[0]['avg'])) return 0;

      return $result[0]['avg']*$num_items;
    }

  }

  // delete playlist and associated items and scheduled content.
  public function delete($id)
  {

      $this->db->where('id',$id);
      $delete = $this->db->delete('playlists');

      if($delete) {

        $this('delete_items',$id);

        $this->db->where('item_id',$id);
        $this->db->where('item_type','playlist');
        $this->db->delete('schedules');

        $this->db->where('item_id',$id);
        $this->db->where('item_type','playlist');
        $this->db->delete('schedules_recurring');

        $this->db->where('button_playlist_id',$id);
        $this->db->delete('playlists_liveassist_buttons');

        return true;

      }

      return false;

  }

  // delete playlist items
  public function delete_items($playlist_id)
  {
    $this->db->where('playlist_id',$playlist_id);
    $this->db->delete('playlists_items');
  }


}
