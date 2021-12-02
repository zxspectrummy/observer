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

/**
 * The playlist controller manages all playlists on the server, including dynamic
 * selections.
 *
 * @package Controller
 */
class Playlist extends OBFController
{

  public function __construct()
  {
    parent::__construct();

    $this->user->require_authenticated();
  }

  /**
   * Checks if the current user can edit playlists. Private method used by many
   * other playlist management methods in this controller.
   *
   * The rules are as follows: If the user has the 'manage_playlists' permission,
   * return TRUE. If the player owns the playlist AND the user has the
   * 'create_own_playlists' permission, return TRUE. If the user is in the
   * permissions array for this playlist, return TRUE. If the user is in a group
   * that is in the group permissions array for this playlist, return TRUE.
   * Otherwise, return FALSE.
   *
   * @param playlist
   *
   * @return can_edit
   */
  private function user_can_edit($playlist)
  {
    $permissions = $this->models->playlists('get_permissions',$playlist['id']);

    if($this->user->check_permission('manage_playlists')) return true;
    if($playlist['owner_id']==$this->user->param('id') && $this->user->check_permission('create_own_playlists')) return true;
    if(array_search($this->user->param('id'), $permissions['users'])!==FALSE) return true;
    if(count(array_intersect($this->user->get_group_ids(), $permissions['groups']))>0) return true;
    return false;
  }

  /**
   * Get a single playlist. Only get the advanced permissions if the user has
   * the 'playlists_advanced_permissions' permission.
   *
   * @param id
   *
   * @return playlist
   */
  public function get()
  {

    $id = $this->data('id');

    $playlist = $this->models->playlists('get_by_id',$id);

    if($playlist)
    {
      $playlist['items'] = $this->models->playlists('get_items',$id);
      if($playlist['type']=='live_assist') $playlist['liveassist_button_items'] = $this->models->playlists('get_liveassist_items',$id);

      // if playlist is private and not ours, require 'manage_playlists'.
      if($playlist['status']=='private' && $playlist['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_playlists');

      // get our advanced permissions
      $permissions = $this->models->playlists('get_permissions',$id);
      if($this->user->check_permission('playlists_advanced_permissions'))
      {
        $playlist['permissions_users'] = $permissions['users'];
        $playlist['permissions_groups'] = $permissions['groups'];
      }

      $playlist['can_edit'] = $this->user_can_edit($playlist, $permissions);

      if($this->data('where_used'))
      {
        $where_used = $this->models->playlists('where_used',$id);
        $playlist['where_used'] = $where_used;
      }

      return array(true,'Playlist found.',$playlist);
    }

    return array(false,'Playlist not found.');

  }

  /**
   * Playlist search.
   *
   * @param q Query
   * @param l Limit
   * @param o Offset
   * @param sort_by
   * @param sort_dir
   * @param my Ownership. Set to filter for playlists owned by user.
   *
   * @return [num_results, playlists]
   */
  public function search()
  {

    $query = $this->data('q');
    $limit = $this->data('l');
    $offset = $this->data('o');

    $sort_by = $this->data('sort_by');
    $sort_dir = $this->data('sort_dir');

    $my = $this->data('my');

    $search_result = $this->models->playlists('search',$query,$limit,$offset,$sort_by,$sort_dir,$my);

    foreach($search_result['playlists'] as &$playlist)
    {
      $playlist['can_edit'] = $this->user_can_edit($playlist);
    }

    //T Playlists
    return array(true,'Playlists',$search_result);

  }

  /**
   * Edit a playlist. Requires 'manage_playlists' or 'create_own_playlists'
   * permission when creating new playlists, and the usual permissions from
   * 'user_can_edit()' for editing.
   *
   * @param id ID of the playlist. Optional, not provided when creating a new playlist.
   * @param name
   * @param description
   * @param status Visibility: private, visible, or public.
   * @param type Standard, advanced, or liveassist.
   * @param items
   * @param liveassist_button_items
   *
   * @return id
   */
  public function save()
  {

    $id = trim($this->data('id'));
    $name = trim($this->data('name'));
    $description = trim($this->data('description'));
    $status = trim($this->data('status'));
    $type = trim($this->data('type'));
    $items = $this->data('items');
    $liveassist_button_items = $this->data('liveassist_button_items');

    // editing playlist
    if(!empty($id))
    {
      $original_playlist = $this->models->playlists('get_by_id',$id);
      //T Unable to edit this playlist.
      if(!$original_playlist) { return array(false,'Unable to edit this playlist.'); }

      // if user can't edit, this will trigger a permission failure in via require_permission.
      if(!$this->user_can_edit($original_playlist)) $this->user->require_permission('manage_playlists');
    }

    // new playlist
    else $this->user->require_permission('manage_playlists or create_own_playlists');

    // validate data.
    $validate_playlist = $this->models->playlists('validate_playlist', array('name'=>$name, 'status'=>$status, 'type'=>$type) );
    if($validate_playlist[0]==false) return array(false,$validate_playlist[1]);

    // check each playlist item.
    foreach($items as $item)
    {
      $validate_item = $this->models->playlists('validate_playlist_item',$item,$id);
      if($validate_item[0]==false) return array(false,$validate_item[1]);
    }

    // check each liveassist button item
    if($type=='live_assist' && is_array($liveassist_button_items)) foreach($liveassist_button_items as $liveassist_button_item)
    {
      $validate_item = $this->models->playlists('validate_liveassist_button_item',$liveassist_button_item);
      if($validate_item[0]==false) return array(false,$validate_item[1]);
    }

    // add/edit playlist entry.
    $data = [];
    $data['name']=$name;
    $data['description']=$description;
    $data['status']=$status;
    $data['type']=$type;
    $data['updated']=time();

    if(!$id)
    {
      $data['created']=time();
      $data['owner_id']=$this->user->param('id');
      $id = $this->models->playlists('insert',$data);
    }

    else
    {
      $this->db->where('id',$id);
      $this->models->playlists('update',$data);

      // TODO this should use the schedule model.

      // delete show cache using this playlist.
      $this->db->where('shows.item_id',$id);
      $this->db->where('shows.item_type','playlist');
      $this->db->what('shows_expanded.id','show_expanded_id');
      $this->db->leftjoin('shows_expanded','shows.id','shows_expanded.show_id');
      $shows = $this->db->get('shows');

      foreach($shows as $show)
      {
        $this->db->where('show_expanded_id',$show['show_expanded_id']);
        $this->db->delete('shows_cache');
      }


      // TODO use a model... (players model)
      // delete default_playlist cache if this is a default playlist.

      // get a list of players using this playlist as a default playlist
      $this->db->where('default_playlist_id',$id);
      $players = $this->db->get('players');

      foreach($players as $player)
      {
        // remove default playlist cache for this player.
        $this->db->where('player_id',$player['id']);
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
    //T An error occurred while saving this playlist.
    if(!$id) return array(false,'An error occurred while saving this playlist.');

    // update our playlist items. first delete all items, then re-add them.
    $this->models->playlists('delete_items',$id);

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

        // track properties
        $properties = [];

        // image properties
        $media = $this->models->media('get_by_id', ['id' => $data['item_id']]);
        if($media && $media['type']=='image')
        {
          $properties['duration'] = (int) $item['duration'];
          if($properties['duration']<=0) $properties['duration'] = 15;
        }

        // audio properties
        if($media['type']=='audio' && ($item['crossfade'] ?? false))
        {
          $properties['crossfade'] = (float) $item['crossfade'];
        }

        // set properties
        if(!empty($properties)) $data['properties'] = json_encode($properties);
      }

      elseif($item['type']=='dynamic')
      {
        $properties = [];
        $properties['num_items']=$item['num_items_all'] ? null : $item['num_items'];
        $properties['image_duration']=$item['image_duration'];
        $properties['query']=json_decode($item['query']);
        $properties['name']=$item['name'];
        $properties['crossfade']=$item['crossfade'] ?? 0;
        $properties['crossfade_last']=$item['crossfade_last'] ?? 0;
        $data['properties'] = json_encode($properties);
      }

      elseif($item['type']=='station_id')
      {
        // nothing special to set here.
      }

      elseif($item['type']=='breakpoint')
      {
        // nothing special to set here.
      }

      elseif($item['type']=='custom')
      {
        $data['properties'] = json_encode(['name'=>$item['query']['name']]);
      }

      $this->db->insert('playlists_items',$data);

    }

    if($type=='live_assist' && is_array($liveassist_button_items))
      $this->models->playlists('update_liveassist_items',$id, $liveassist_button_items);

    // handle advanced permissions if we're allowed
    if($this->user->check_permission('playlists_advanced_permissions'))
    {
      $this->models->playlists('update_permissions_users', $id, $this->data('permissions_users'));
      $this->models->playlists('update_permissions_groups', $id, $this->data('permissions_groups'));
    }

    return array(true,'Playlist saved.',$id);
  }

  /**
   * Validate dynamic selection and provide running estimate.
   *
   * @param search_query
   * @param num_items
   * @param num_items_all Boolean set to TRUE to use all items. Overrides num_items.
   * @param image_duration Duration static images are displayed in seconds.
   *
   * @return [duration]
   */
  public function validate_dynamic_properties() {
    $search_query = $this->data('search_query');

    $search_query = (array) $search_query; // might be object... want to make consistent.

    $num_items = trim($this->data('num_items'));
    $num_items_all = trim($this->data('num_items_all'));
    $image_duration = trim($this->data('image_duration'));

    $validation = $this->models->playlists('validate_dynamic_properties',$search_query,$num_items,$num_items_all,$image_duration);

    if($validation[0]==false) return array(false,array('Playlist Dynamic Item Properties',$validation[1]));

    if($num_items_all) $num_items = null; // duration function uses empty num_items indicate 'all items' mode.

    // valid, also return some additional information.
    $validation[2]=array('duration'=>$this->models->playlists('dynamic_selection_duration',$search_query,$num_items,$image_duration));
    return $validation;

  }

  /**
   * Delete a playlist. Requires the usual 'user_can_edit()' permissions.
   *
   * @param id An array of playlist IDs. Can be a single ID.
   */
  public function delete()
  {
    $ids = $this->data('id');

    // if we just have a single ID, make it into an array so we can proceed on that assumption.
    if(!is_array($ids)) $ids = array($ids);

    // make sure we have all our playlists. check permission.
    foreach($ids as $id)
    {

      $playlist = $this->models->playlists('get_by_id',$id);

      if(!$playlist) return array(false,'One or more playlists were not found.');

      // if user can't edit, this will trigger a permission failure in via require_permission.
      if(!$this->user_can_edit($playlist)) $this->user->require_permission('manage_playlists');

      // check where used, see if we have permission to remove from those.
      $where_used = $this->models->playlists('where_used',$id);
      if($where_used['can_delete']==false) return array(false,'Cannot delete one or more playlists as you do not have adequate permissions.');
    }

    // proceed with delete
    foreach($ids as $id)
    {
      $this->models->playlists('delete',$id);
    }

    return array(true,'Playlists have been deleted.');

  }

}
