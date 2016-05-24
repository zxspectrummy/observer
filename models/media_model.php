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

class MediaModel extends OBFModel
{
  
  public function get_init()  
  {

    $this->db->what('media.id','id');
    $this->db->what('media.title','title');
    $this->db->what('media.artist','artist');
    $this->db->what('media.album','album');
    $this->db->what('media.year','year');

    $this->db->what('media.type','type');
    $this->db->what('media.format','format');

    $this->db->what('media.category_id','category_id');
    $this->db->what('media_categories.name','category_name');

    $this->db->what('media.country_id','country_id');
    $this->db->what('media_countries.name','country_name');

    $this->db->what('media.language_id','language_id');
    $this->db->what('media_languages.name','language_name');

    $this->db->what('media.is_approved','is_approved');

    $this->db->what('media.genre_id','genre_id');
    $this->db->what('media_genres.name','genre_name');

    $this->db->what('media.comments','comments');

    $this->db->what('media.filename','filename');
    $this->db->what('media.file_hash','file_hash');
    $this->db->what('media.file_location','file_location');

    $this->db->what('media.is_copyright_owner','is_copyright_owner');
    $this->db->what('media.duration','duration');
    $this->db->what('media.owner_id','owner_id');
    $this->db->what('media.created','created');
    $this->db->what('media.updated','updated');
    $this->db->what('media.is_archived','is_archived');
    $this->db->what('media.status','status');
    $this->db->what('media.dynamic_select','dynamic_select');

    $this->db->what('users.display_name','owner_name');

    $this->db->leftjoin('media_categories','media_categories.id','media.category_id');
    $this->db->leftjoin('media_languages','media.language_id','media_languages.id');
    $this->db->leftjoin('media_countries','media.country_id','media_countries.id');
    $this->db->leftjoin('media_genres','media.genre_id','media_genres.id');

    $this->db->leftjoin('users','media.owner_id','users.id');

  }

  public function get_by_id($id)
  {
    $this('get_init');

    $this->db->where('media.id',$id);
    return $this->db->get_one('media');
  }

  public function search_get_default_filters($user_id)
  {
    $this->db->where('user_id',$user_id);
    $this->db->where('default',1);
    $search = $this->db->get_one('media_searches');

    if(!$search) return false;

    $query = unserialize($search['query']);
    return $query['filters'];
  }

  public function search_default($id, $user_id)
  {
    $this->db->where('user_id',$user_id);
    $this->db->update('media_searches',array('default'=>0));

    $this->db->where('user_id',$user_id);
    $this->db->where('id',$id);
    $this->db->where('type','saved'); // can only make 'saved' searches the default.

    if($this->db->update('media_searches',array('default'=>1))) return true; 
    else return false;
  }
   
  public function search_unset_default($user_id)
  {
    $this->db->where('user_id',$user_id);
    $this->db->update('media_searches',array('default'=>0));
    return true;
  }

  public function search_save($query)
  {
    if(!$this->user->param('id')) return false;

    $query=serialize($query);

    $this->db->where('user_id',$this->user->param('id'));
    $this->db->where('query',$query);

    $history_item = $this->db->get_one('media_searches');

    // is this search already saved? then don't do anything.
    if($history_item && $history_item['type']=='saved') return true;

    // is this search item saved to history? then remove it so it gets re-added with a higher ID (to show up as more recent).
    if($history_item && $history_item['type']=='history')
    {
      $this->db->where('id',$history_item['id']);
      $this->db->delete('media_searches');
    }

    $this->db->insert('media_searches',array('user_id'=>$this->user->param('id'),'query'=>$query,'type'=>'history'));

    // shrink our history list down to the top 5.
    $this->db->query('select id from media_searches where user_id = "'.$this->db->escape($this->user->param('id')).'" and type="history"');
    $num_to_delete = $this->db->num_rows() - 5;
    if($num_to_delete > 0) $this->db->query('delete from media_searches where user_id = "'.$this->db->escape($this->user->param('id')).'" and type="history" order by id limit '.$num_to_delete);

    return true;

  }

  public function search_get_saved($type='history')
  {
    $this->db->what('id');
    $this->db->what('query');
    $this->db->what('default');
    $this->db->what('description');

    $this->db->where('user_id',$this->user->param('id'));
    $this->db->where('type',$type);

    $this->db->orderby('id','desc');
    
    $searches = $this->db->get('media_searches');
      
    foreach($searches as $index=>$search)
    {
      $searches[$index]['query'] = unserialize($search['query']);
    }

    return $searches;
  }

  public function search_save_history($id,$user_id=false)
  {
    if(!$id) return false;

    $this->db->where('id',$id);
    if($user_id) $this->db->where('user_id',$user_id);

    return $this->db->update('media_searches',array('type'=>'saved'));
  }

  public function search_delete_saved($id,$user_id=false)
  {
    if(!$id) return false;

    $this->db->where('id',$id);
    if($user_id) $this->db->where('user_id',$user_id);

    return $this->db->delete('media_searches');
  }

  public function search_edit($id,$filters,$description,$user_id=false)
  {
    if(!$this->search_filters_validate($filters)) return false;

    if($user_id) $this->db->where('user_id',$user_id);
    $this->db->where('id',$id);
    $query = array('mode'=>'advanced','filters'=>$filters);
    $this->db->update('media_searches',array('query'=>serialize($query),'description'=>$description));

    return true;
  }

  public function search($params,$device_id=false,$random_order=false)
  {

    // if we are accessing from a remote, determine the valid media types.
    if($device_id)
    {
      $this->db->where('id',$device_id);
      $device = $this->db->get_one('devices');

      if(!$device) return false;

      $supported_types = array();
      
      if($device['support_audio']==1) $supported_types[]='media.type = "audio"';
      if($device['support_video']==1) $supported_types[]='media.type = "video"';
      if($device['support_images']==1) $supported_types[]='media.type = "image"';     

      if(count($supported_types)==0) return false;
    }

    // default status is "approved"

    $where_array = array();

    if(isset($params['status']) && $params['status'] == 'archived') $where_array[] = 'is_archived = 1';
    elseif(isset($params['status']) && $params['status'] == 'unapproved') $where_array[] = '(is_approved = 0 and is_archived = 0)';
    else $where_array[] = '(is_approved = 1 and is_archived = 0)';

    // only select media (if remote) where dynamic selection is allowed.
    if($device_id) {
      $where_array[] = 'dynamic_select = 1';
      $where_array[] = '('.implode(' OR ',$supported_types).')';
    }

    // if we don't have "manage_media" permission, we can't view others' private media.
    if(!$device_id && !$this->user->check_permission('manage_media')) $where_array[] = '(status = "public" OR owner_id = "'.$this->db->escape($this->user->param('id')).'")';

    $this('get_init');

    // limit by id?
    if(!empty($params['id'])) $where_array[] = 'media.id = "'.$this->db->escape($params['id']).'"';

    // simple search
    if($params['query']['mode']=='simple')
    {
      $where_array[] = '(artist like "%'.$this->db->escape($params['query']['string']).'%" or title like "%'.$this->db->escape($params['query']['string']).'%")';
      if(isset($params['default_filters'])) 
      {
        if(!$this->search_filters_validate($params['default_filters'])) return false;
        $where_array = array_merge($where_array,$this->search_filters_where_array($params['default_filters']));
      }
    }

    // advanced search
    elseif($params['query']['mode']=='advanced')
    {
      if(!$this->search_filters_validate($params['query']['filters'])) return false;
      $where_array = array_merge($where_array,$this->search_filters_where_array($params['query']['filters']));
    }

    else return false; // invalid mode.

    // limit results to those owned by the presently logged in user.
    if(isset($params['my']) && $params['my']) $where_array[]='owner_id = "'.$this->db->escape($this->user->param('id')).'"';

    // put all the where data together.
    $this->db->where_string(implode(' AND ',$where_array));

    if(!empty($params['offset'])) $this->db->offset($params['offset']);
    if(!empty($params['limit'])) $this->db->limit($params['limit']);

    // if remote mode, we need random order... (generating dynamic playlist)
    if($random_order) $this->db->random_order();

    // otherwise, if posted sort by data is valid, use that...
    elseif( isset($params['sort_dir']) && ($params['sort_dir'] =='asc' || $params['sort_dir'] == 'desc') && array_search($params['sort_by'], array('artist','album','title','year','category_name','genre_name','country_name','language_name','duration','updated'))!==false )
    {
      $this->db->orderby($params['sort_by'],$params['sort_dir']);
    }

    // otherwise, show the most recently updated first
    else $this->db->orderby('updated','desc');

    if(method_exists($this->db,'calc_found_rows')) $this->db->calc_found_rows();

    $media = $this->db->get('media');

    return array($media,$this->db->found_rows());

  }

  public function search_filters_validate($filters)
  {
    foreach($filters as $filter)
    {
      if(is_object($filter)) $filter = get_object_vars($filter);

      // make sure our search field and comparison operator is valid
      if(array_search($filter['filter'],array('comments','artist','title','album','year','type','category','country','language','genre','duration'))===false) return false;
      if(array_search($filter['op'],array('like','not_like','is','not','gte','lte'))===false) return false;
    }

    return true;
  }

  // get some 'where' SQL from a search filters array.
  public function search_filters_where_array($filters)
  {

    $where_array = array();

    foreach($filters as $filter)
    {
      if(is_object($filter)) $filter = get_object_vars($filter);

      // our possible column (mappings)
      $column_array = array();
      $column_array['artist']='artist';
      $column_array['title']='title';
      $column_array['album']='album';
      $column_array['year']='year';
      $column_array['type']='media.type';
      $column_array['category']='category_id';
      $column_array['country']='country_id';
      $column_array['language']='language_id';
      $column_array['genre']='genre_id';
      $column_array['duration']='duration';
      $column_array['comments']='comments';

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

      $where_array[]=$tmp_sql;

    }

    return $where_array;
  }

  // where media is used information
  public function where_used($id,$include_dynamic = false)
  {

    $info = array();

    $info['used']=array();
    $info['id']=$id;
    $info['can_delete']=true;

    // is this used with a playlist?
    $this->db->what('playlists.name','name');
    $this->db->what('playlists.id','playlist_id');
    $this->db->where('playlists_items.item_id',$id);
    $this->db->where('playlists_items.item_type','media');
    $this->db->leftjoin('playlists','playlists.id','playlists_items.playlist_id');
    $playlists = $this->db->get('playlists_items');
    
    foreach($playlists as $playlist) 
    {

      // if($playlist['owner_id']!=$this->user->param('id') && !$this->user->check_permission('manage_playlists')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'playlist';
      $used_data->id = $playlist['playlist_id'];
      $used_data->name = $playlist['name'];

      $info['used'][] = $used_data;
    }

    // is this potentially found in a dynamic selection?
    if($include_dynamic)
    {

      // see if media can actually be used in dynamic selections.
      $this->db->where('id',$id);
      $media = $this->db->get_one('media');

      if($media['dynamic_select']==1) {

        $this->db->what('playlists.name');
        $this->db->what('playlists.id');
        $this->db->what('playlists_items.dynamic_query');

        $this->db->where('item_type','dynamic');

        $this->db->leftjoin('playlists','playlists.id','playlists_items.playlist_id');

        $dynamic_items = $this->db->get('playlists_items');

        $found_in_playlists = array();

        foreach($dynamic_items as $item)
        {

          if(array_search($item['id'],$found_in_playlists)!==false) continue; // don't search if we've already found it in this playlist.

          $media_search = $this('search', array('limit'=>1,'query'=>(array) json_decode($item['dynamic_query']),'id'=>$id) );

          if($media_search && $media_search[1]>0) 
          {

            $used_data = new stdClass();
            $used_data->where = 'playlist_dynamic';
            $used_data->id = $item['id'];
            $used_data->name = $item['name'];

            $info['used'][] = $used_data;

            $found_in_playlists[]=$item['id'];

          }

        }

      }

    }
        
    // is this used with a station ID?
    $this->db->what('devices.name','name');
    $this->db->what('devices.id','device_id');
    $this->db->where('devices_station_ids.media_id',$id);
    $this->db->leftjoin('devices','devices_station_ids.device_id','devices.id');
    $station_ids = $this->db->get('devices_station_ids');

    foreach($station_ids as $station_id)
    {

      if(!$this->user->check_permission('manage_devices')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'device';
      $used_data->id = $station_id['device_id'];
      $used_data->name = $station_id['name'];

      $info['used'][] = $used_data;

    }

    // is this used with an emergency broadcast?
    $this->db->where('item_id',$id);
    $emergencies = $this->db->get('emergencies');
    
    foreach($emergencies as $emergency)
    {

      if(!$this->user->check_permission('manage_emergency_broadcasts')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'emergency';
      $used_data->name = $emergency['name'];
      $used_data->user_id = $emergency['user_id'];
      $used_data->id = $emergency['id'];

      $info['used'][] = $used_data;

    }
  
    // is this used on a schedule?
    $this->db->what('devices.id','device_id');
    $this->db->what('devices.name','device_name');
    $this->db->what('schedules.user_id','user_id');
    $this->db->what('schedules.id','id');
    $this->db->where('item_id',$id);
    $this->db->where('item_type','media');
    $this->db->leftjoin('devices','schedules.device_id','devices.id');
    $schedules = $this->db->get('schedules');

    foreach($schedules as $schedule)
    {

      if($schedule['user_id']!=$this->user->param('id') && !$this->user->check_permission('manage_schedule_permissions')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'schedule';
      $used_data->name = $schedule['device_name'];
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
    $this->db->where('item_type','media');
    $this->db->leftjoin('devices','schedules_recurring.device_id','devices.id');
    $schedules = $this->db->get('schedules_recurring');

    foreach($schedules as $schedule)
    {

      if($schedule['user_id']!=$this->user->param('id') && !$this->user->check_permission('manage_schedule_permissions')) $info['can_delete']=false;

      $used_data = new stdClass();
      $used_data->where = 'recurring schedule';
      $used_data->name = $schedule['device_name'];
      $used_data->id = $schedule['id'];
      $used_data->user_id = $schedule['user_id'];

      $info['used'][] = $used_data;
    }

    return $info;

  }

  public function validate($item,$skip_upload_check = false)
  {

    // check if id is valid (if editing)
    if(!empty($item['id']) && !$this->db->id_exists('media',$item['id'])) return array(false,$item['local_id'],'Media Not Found');

    // check if file exists (if uploading)
    if(!empty($item['file_id']) && !$item['file_info']) return array(false,$item['local_id'],'Upload Not Valid');

    // require uploading for new item.
    if(!$skip_upload_check && empty($item['id']) && empty($item['file_id'])) return array(false,$item['local_id'],'File Upload Required');

    // check if format valid/allowed (if uploading)
    if(!empty($item['file_id']) && !$this('format_allowed',$item['file_info']['type'],$item['file_info']['format'])) return array(false,$item['local_id'],'Format Not Supported');

    // validate text fields
    if(empty($item['artist']) || empty($item['title'])) return array(false,$item['local_id'],'Required Fields Not Filled');

    // make sure artist and title aren't too long.  letting the db do the truncating messes up the filename.
    if(strlen($item['artist'])>255 || strlen($item['title'])>255) return array(false,$item['local_id'],'Artist/Title Too Long');

    // check if year valid
    if(!empty($item['year']) && (!preg_match('/^[0-9]+$/',$item['year']) || $item['year']>2100)) return array(false,$item['local_id'],'Year Not Valid');

    // validate select fields
    if(!empty($item['category_id']) && !$this->db->id_exists('media_categories',$item['category_id'])) return array(false,$item['local_id'],'Category Not Valid');
    if(!empty($item['country_id']) && !$this->db->id_exists('media_countries',$item['country_id'])) return array(false,$item['local_id'],'Country Not Valid');
    if(!empty($item['genre_id']) && !$this->db->id_exists('media_genres',$item['genre_id'])) return array(false,$item['local_id'],'Genre Not Valid');
    if(!empty($item['language_id']) && !$this->db->id_exists('media_languages',$item['language_id'])) return array(false,$item['local_id'],'Language Not Valid');
    
    if($item['status']!='private' && $item['status']!='public') return array(false,$item['local_id'],'Status Not Valid');

    // make sure genre belongs to the selected category.
    $this->db->where('id',$item['genre_id']);
    $genre = $this->db->get_one('media_genres');
    if($genre['media_category_id']!=$item['category_id']) return array(false,$item['local_id'],'Genre Not Valid For Category');

    // not bothering to validate yes/no... if not 1 (yes), assuming 0 (no).

    return array(true,$item['local_id']);

  }

  public function save($item)
  {
  
    // grab some important values
    $id = $item['id'];
    $file_id = $item['file_id'];
    $file_info = (isset($item['file_info']) ? $item['file_info'] : null);

    // get our original item before edit (we may need this)
    if($id)
    {
      $this->db->where('id',$id);
      $original_media = $this->db->get_one('media');
    }
    else $original_media = false;

    // some data might need cleanup
    if($item['is_copyright_owner']!=1) $item['is_copyright_owner']=0;
    if($item['is_approved']!=1) $item['is_approved']=0;
    if($item['dynamic_select']!=1) $item['dynamic_select']=0;

    // if "approve own media" permission is false, just set is_approved to 0. this means that edited media or new media will need to be approved.
    if(!$this->user->check_permission('approve_own_media or manage_media')) $item['is_approved']=0;

    // set null values where appropriate
    if(empty($item['category_id'])) $item['category_id']=null;
    if(empty($item['country_id'])) $item['country_id']=null;
    if(empty($item['language_id'])) $item['language_id']=null;
    if(empty($item['genre_id'])) $item['genre_id']=null;
    if(empty($item['year'])) $item['year']=null;
  
    // unset some values.
    unset($item['local_id']);
    unset($item['file_id']);
    unset($item['file_key']);
    unset($item['id']);
    unset($item['file_info']);

    // set our file info if we have it
    if($file_id) 
    {
      $item['duration']=$file_info['duration'];
      $item['type']=$file_info['type'];
      $item['format']=$file_info['format'];
    }

    // update or insert.
    if(!empty($id)) 
    {
      $this->db->where('id',$id);
      $item['updated']=time();
      $this->db->update('media',$item);

      // delete from schedules_media_cache where this item has been scheduled.  should regenerate cache.
      $this->db->where_like('data','"id":"'.$this->db->escape($id).'"');
      $this->db->delete('schedules_media_cache');
    }

    else
    {
      $item['owner_id']=$this->user->param('id');
      $item['created']=time();
      $item['updated']=time();

      $id = $this->db->insert('media',$item);
    }

    // determine our file's name (may be used if we have a new file, or file requires renaming)
    $filename_artist = preg_replace("/[^a-zA-Z0-9]/", "_", $item['artist']);
    $filename_title = preg_replace("/[^a-zA-Z0-9]/", "_", $item['title']);
    $filename = $id.'-'.$filename_artist.'-'.$filename_title.'.'.(!empty($item['format']) ? $item['format'] : $original_media['format']);

    // handle file if we have it.
    if(!empty($file_id)) 
    {

      // determine our (random) file location
      if($original_media) $file_location = $original_media['file_location'];
      else $file_location = $this->rand_file_location();
      $media_location = '/'.$file_location[0].'/'.$file_location[1].'/';

      // remove our original file if we have it
      if($original_media)
      {
        // if($original_media['is_archived']==1) unlink(OB_MEDIA_ARCHIVE.$media_location); // should not be archived, can not edit archived media.
        if($original_media['is_approved']==0) unlink(OB_MEDIA_UPLOADS.$media_location.$original_media['filename']);
        else unlink(OB_MEDIA.$media_location.$original_media['filename']);
      }

      // move our file to its home
      $file_src = 'assets/uploads/'.$file_id;
      if($item['is_approved']==0) $file_dest = OB_MEDIA_UPLOADS.$media_location.$filename;
      else $file_dest = OB_MEDIA.$media_location.$filename;
      rename($file_src,$file_dest);


      // remove file upload row from uploads table.
      $this->db->where('id',$file_id);
      $this->db->delete('uploads'); 

      // update our database with file hash, location, filename
      $data['filename']=$filename;
      $data['file_location']=$file_location;
      $data['file_hash']=md5_file($file_dest);

      $this->db->where('id',$id);
      $this->db->update('media',$data);

    }

    // if we are not uploading new file, but we are changing approved status, we need to move our file. or we're changing the artist or title name (on which the filename is based).
    elseif($original_media && ($original_media['is_approved']!=$item['is_approved'] || $original_media['artist']!=$item['artist'] || $original_media['title']!=$item['title']))
    {
      $media_location = '/'.$original_media['file_location'][0].'/'.$original_media['file_location'][1].'/';

      if($original_media['is_approved']==1) $file_src = OB_MEDIA.$media_location.$original_media['filename'];
      else $file_src = OB_MEDIA_UPLOADS.$media_location.$original_media['filename'];

      if($item['is_approved']==1) $file_dest = OB_MEDIA.$media_location.$filename;
      else $file_dest = OB_MEDIA_UPLOADS.$media_location.$filename;
    
      rename($file_src,$file_dest);

      // update db with new filename
      $this->db->where('id',$id);
      $this->db->update('media',array('filename'=>$filename));
    }

    return $id;

  }

  public function archive($ids)
  {

    $original_media = array();

    // make sure all our media exists. media must not be unapproved or already archived.
    foreach($ids as $id) 
    {
      $this->db->where('is_approved',1);
      $this->db->where('is_archived',0);
      $this->db->where('id',$id);
      $media = $this->db->get_one('media');

      if(!$media) return false;

      $original_media[$id]=$media;
    }

    // check permissions.
    if($media['owner_id']==$this->user->param('id')) $this->user->require_permission('create_own_media or manage_media');
    else $this->user->require_permission('manage_media');

    // proceed with archiving
    foreach($ids as $id)
    {

      $where_used = $this('where_used',$id);
      if($where_used['can_delete']==false) return false;

      $this->db->where('id',$id);
      $update = $this->db->update('media',array('is_archived'=>1));

      if($update)
      {
        $src_file = OB_MEDIA.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];
        $dest_file = OB_MEDIA_ARCHIVE.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];

        if(file_exists($src_file)) rename($src_file,$dest_file);

        $this('remove_where_used',$id);
      }

    }

    return true;

  }

  public function unarchive($ids)
  {

    $original_media = array();

    // make sure all our media exists. media must already be archived. unarchived media moves back to approved.
    foreach($ids as $id) 
    {
      $this->db->where('is_archived',1);
      $this->db->where('id',$id);
      $media = $this->db->get_one('media');

      if(!$media) return false;

      $original_media[$id]=$media;
    }

    // proceed with unarchive
    foreach($ids as $id)
    {
      $this->db->where('id',$id);
      // unarchived media must go to approved.
      $update = $this->db->update('media',array('is_archived'=>0, 'is_approved'=>1));

      if($update)
      {
        $src_file = OB_MEDIA_ARCHIVE.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];
        $dest_file = OB_MEDIA.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];

        if(file_exists($src_file)) rename($src_file,$dest_file);
      }
    }

    return true;

  }

  public function delete($ids)
  {

    $original_media = array();

    // make sure we have all our media and it's already archived or still unapproved.
    foreach($ids as $id)
    {
      $this->db->where('id',$id);
      $media = $this->db->get_one('media');

      if(!$media || ($media['is_archived']==0 && $media['is_approved']==1)) return false;

      $original_media[$id]=$media;
    }
    
    // proceed with delete
    foreach($ids as $id)
    {

      $this->db->where('id',$id);
      $delete = $this->db->delete('media');

      if($delete) 
      {
        if($original_media[$id]['is_archived']==1) $media_file = OB_MEDIA_ARCHIVE.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];
        else $media_file = OB_MEDIA_UPLOADS.'/'.$original_media[$id]['file_location'][0].'/'.$original_media[$id]['file_location'][1].'/'.$original_media[$id]['filename'];

        if(file_exists($media_file)) unlink($media_file);
    
        $this->delete_cached($original_media[$id]);
      }

    }

    return true;

  }

  public function delete_cached($media)
  {

    // make sure cache dir exists (otherwise nothing to delete anyway)
    if(!is_dir(OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1])) return;

    // remove any cached preview files for this item.
    $dh = opendir(OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1]);

    while(false!==($entry=readdir($dh)))
    {
      if(strpos($entry,$media['id'].'_')===0) unlink(OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1].'/'.$entry);
    }
  }

  public function formats_validate($data)
  {

    foreach($data as $name=>$value) $$name=$value;

    // list of valid formats...
    $valid_video_formats = array('avi','mpg','ogg','wmv','mov');
    $valid_image_formats = array('jpg','png','svg');
    $valid_audio_formats = array('flac','mp3','ogg','mp4','wav');

    // verify image formats
    if(!is_array($video_formats) || !is_array($image_formats) || !is_array($audio_formats)) return array(false,'There was a problem saving the format settings.');

    foreach($video_formats as $format) {
      if(array_search($format,$valid_video_formats)===false) return array(false,'There was a problem saving the format settings.  One of the formats does not appear to be valid.');
    }
  
    foreach($audio_formats as $format) {
      if(array_search($format,$valid_audio_formats)===false) return array(false,'There was a problem saving the format settings.  One of the formats does not appear to be valid.');
    }

    foreach($image_formats as $format) {
      if(array_search($format,$valid_image_formats)===false) return array(false,'There was a problem saving the format settings.  One of the formats does not appear to be valid.');
    }

    return array(true,'');

  } 

  public function formats_save($data)
  {

    foreach($data as $name=>$value) $$name=$value;

    $this->db->where('name','audio_formats');
    $this->db->update('settings',array('value'=>implode(',',$audio_formats)));

    $this->db->where('name','image_formats');
    $this->db->update('settings',array('value'=>implode(',',$image_formats)));

    $this->db->where('name','video_formats');
    $this->db->update('settings',array('value'=>implode(',',$video_formats)));

  }

  public function formats_get_all()
  {

    $return = array();

    $this->db->where('name','audio_formats');
    $audio = $this->db->get_one('settings');

    $this->db->where('name','video_formats');
    $video = $this->db->get_one('settings');
    
    $this->db->where('name','image_formats');
    $image = $this->db->get_one('settings');

    $return['audio_formats']=explode(',',$audio['value']);
    $return['video_formats']=explode(',',$video['value']);
    $return['image_formats']=explode(',',$image['value']);
  
    return $return;

  }

  // remove everywhere the media is used.
  public function remove_where_used($id)
  {

    // remove from device ids
    $this->db->where('media_id',$id);
    $this->db->delete('devices_station_ids');

    // remove from playlists (items)
    $this->db->where('item_type','media');
    $this->db->where('item_id',$id);
    $this->db->delete('playlists_items');

    // delete from schedules_media_cache where this item has been scheduled.
    $this->db->where_like('data','"id":"'.$this->db->escape($id).'"');
    $this->db->delete('schedules_media_cache');

    // remove from schedules, schedules recurring
    $this->db->where('item_id',$id);
    $this->db->where('item_type','media');
    $this->db->delete('schedules');

    $this->db->where('item_id',$id);
    $this->db->where('item_type','media');
    $this->db->delete('schedules_recurring');

    // remove from emergencies
    $this->db->where('item_id',$id);
    $this->db->delete('emergencies');

  }

  // check whether a given type/format is allowed & valid.
  public function format_allowed($type,$format)
  {

    if(empty($type) || empty($format)) return false;

    if($type=='image')
    {
      $this->db->where('name','image_formats');
      $allowed_formats = $this->db->get_one('settings');
    }

    elseif($type=='audio')
    {
      $this->db->where('name','audio_formats');
      $allowed_formats = $this->db->get_one('settings');
    }

    elseif($type=='video')
    {
      $this->db->where('name','video_formats');
      $allowed_formats = $this->db->get_one('settings');
    }

    $allowed_formats = explode(',',$allowed_formats['value']);

    return in_array($format,$allowed_formats);

  }

  // random file location
  // generate a random file location... (splits files up into directories)
  // also create directories in upload and 
  public function rand_file_location() 
  {
    $charSelect='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    $randVal=rand(0,1295);

    $randValA=$randVal%36;
    $randValB=($randVal-$randValA)/36;

    $charA=$charSelect[$randValA];
    $charB=$charSelect[$randValB];

    $requiredDirs=array();
    $requiredDirs[]=OB_MEDIA.'/'.$charA;
    $requiredDirs[]=OB_MEDIA_ARCHIVE.'/'.$charA;   
    $requiredDirs[]=OB_MEDIA_UPLOADS.'/'.$charA;
 
    $requiredDirs[]=OB_MEDIA.'/'.$charA.'/'.$charB;
    $requiredDirs[]=OB_MEDIA_ARCHIVE.'/'.$charA.'/'.$charB;  
    $requiredDirs[]=OB_MEDIA_UPLOADS.'/'.$charA.'/'.$charB;

    foreach($requiredDirs as $checkDir) 
      if(!file_exists($checkDir)) mkdir($checkDir);

    return $charA.$charB;
  }
      
}
