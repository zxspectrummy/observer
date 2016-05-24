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

class Media extends OBFController 
{

  public function __construct()
  {
    parent::__construct();  
    $this->MediaModel = $this->load->model('Media');
  }

  public function formats_get()
  {
    $this->user->require_authenticated();
    $formats = $this->MediaModel('formats_get_all');
    return array(true,'Accepted formats.',$formats);
  }

  public function formats_save()
  {
    $this->user->require_permission('manage_media_settings');

    $data['video_formats'] = $this->data('video_formats');
    $data['image_formats'] = $this->data('image_formats');
    $data['audio_formats'] = $this->data('audio_formats');

    $validation = $this->MediaModel('formats_validate',$data);
    if($validation[0]==false) return $validation;

    $this->MediaModel('formats_save',$data);

    return array(true,['Media Settings','Formats Saved Message']);
  }

  public function media_my_searches()
  {
    $this->user->require_authenticated();
    return array(true,'Searches',array('saved'=>$this->MediaModel('search_get_saved','saved'), 'history'=>$this->MediaModel('search_get_saved','history')));
  }

  // moves 'history' item to 'saved' item.
  public function media_my_searches_save()
  {
    $this->user->require_authenticated();

    if($this->MediaModel('search_save_history',$this->data('id'), $this->user->param('id') )) return array(true,'Search item saved.');
    else return array(false,'Error saving search item.');
  }

  public function media_my_searches_edit()
  {
    $this->user->require_authenticated();

    $id = $this->data('id');
    $filters = $this->data('filters');
    $description = trim($this->data('description'));

    if($this->MediaModel('search_edit',$id, $filters, $description, $this->user->param('id') )) return array(true,'Search item edited.');
    else return array(false,'Error editing search item.');
  }

  public function media_my_searches_delete()
  {
    $this->user->require_authenticated();

    if($this->MediaModel('search_delete_saved',$this->data('id'), $this->user->param('id') )) return array(true,'Search item deleted.');
    else return array(false,'Error deleting search item.');
  }

  public function media_my_searches_default()
  {
    $this->user->require_authenticated();

    if($this->MediaModel('search_default',$this->data('id'), $this->user->param('id') )) return array(true,'Search item is now default.');
    else return array(false,'Error setting search item as default.');
  }

  public function media_my_searches_unset_default()
  {
    $this->user->require_authenticated();

    if($this->MediaModel('search_unset_default', $this->user->param('id') )) return array(true,'Search default has been unset.');
    else return array(false,'Error removing default from search item.');
  }

  public function media_search()
  {

    $this->user->require_authenticated();

    $params['query'] = $this->data('q');
    $params['limit'] = $this->data('l');
    $params['offset'] = $this->data('o');
    $params['sort_by'] = $this->data('sort_by');
    $params['sort_dir'] = $this->data('sort_dir');
    $params['status'] = $this->data('s');
    $params['my'] = $this->data('my');

    // if we're doing a simple search, we might need to apply some 'default' filters. this is handled by the media model search method.
    if($params['query']['mode']=='simple' && $default_filters = $this->MediaModel('search_get_default_filters',$this->user->param('id')) )
      $params['default_filters']=$default_filters;

    $media_result = $this->MediaModel('search',$params,null);

    if($media_result==false) return array(false,'Search error.');
  
    if($this->data('save_history') && $params['query']['mode']=='advanced')
    {
      $this->MediaModel('search_save',$params['query']);
    }

    return array(true,'Media',array('num_results'=>$media_result[1],'media'=>$media_result[0]));

  }
  
  public function edit()
  {

    $this->user->require_permission('manage_media or create_own_media');

    $media = $this->data('media');

    $uploads_model = $this->load->model('Uploads');

    $all_valid = true;
    $validation = array();

    // add our file info to our media array. (this also validates the file upload id/key)
    // also trim artist/title (which is used to determine filename)
    foreach($media as $index=>$item)
    {
      if(!empty($item['file_id']))
      {
        $media[$index]['file_info']=$uploads_model->file_info($item['file_id'],$item['file_key']);
      }
    
      $media[$index]['artist']=trim($media[$index]['artist']);
      $media[$index]['title']=trim($media[$index]['title']);
    }

    // validate each media item
    foreach($media as $index=>$item)
    {

      // check permissions
      if(empty($item['id'])) $this->user->require_permission('create_own_media or manage_media');
      else
      {
        $this->db->where('id',$item['id']);
        $media_item = $this->db->get_one('media');
        if($media_item['owner_id']==$this->user->param('id')) $this->user->require_permission('create_own_media or manage_media');
        else $this->user->require_permission('manage_media');
      }

      $check_media = $this->MediaModel('validate',$item);
      if(!$check_media[0]) { $validation[]=$check_media; $all_valid = false; }

    }

    // if all valid, proceed with media update (or create new)
    if($all_valid)
    {

      foreach($media as $item) $this->MediaModel('save',$item);
      return array(true);
    }

    else
    {
      return array(false,'Media update validation error(s).',$validation);
    }

  }

  public function archive()
  {

    $this->user->require_authenticated();

    $ids = $this->data('id');

    // if we just have a single ID, make it into an array so we can proceed on that assumption.
    if(!is_array($ids)) $ids = array($ids);

    if(!$this->MediaModel('archive',$ids)) return array(false,'An error occurred while attempting to archive this media.');

    return array(true,'Media has been archived.');

  }

  public function unarchive()
  {

    $this->user->require_permission('manage_media');

    $ids = $this->data('id');

    // if we just have a single ID, make it into an array so we can proceed on that assumption.
    if(!is_array($ids)) $ids = array($ids);

    if(!$this->MediaModel('unarchive',$ids)) return array(false,'An error occurred while attempting to un-archive this media.');

    return array(true,'Media has been un-archived.');

  }

  // delete archived or unapproved media.
  public function delete()
  {

    $this->user->require_permission('manage_media');

    $ids = $this->data('id');
      
    // if we just have a single ID, make it into an array so we can proceed on that assumption. 
    if(!is_array($ids)) $ids = array($ids);

    if(!$this->MediaModel('delete',$ids)) return array(false,'An error occurred while attempting to delete this media.');

    return array(true,'Media has been permanently deleted.');

  }

  // get single media item
  public function get()
  {

    $this->user->require_authenticated();

    $id = $this->data('id');

    $media = $this->MediaModel('get_by_id',$id);

    if(!$media) return array(false,'Media not found.');

    if($media['status']=='private' && $media['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_media');
    
    return array(true,'Media data.',$media);

  }

  // get more details...
  public function get_details()
  {

    $this->user->require_authenticated();

    // TODO, can get these details even if we don't have access (private, non-owner).

    $id = $this->data('id');

    // get where used information...
    $where_used = $this->MediaModel('where_used',$id,true);
    $where_used = $where_used['used'];

    return array(true,'Media details.',$where_used);

  }

  public function used()
  {

    $this->user->require_authenticated();

    $ids = $this->data('id');
    if(!is_array($ids)) $ids = array($ids);

    $return = array();

    foreach($ids as $id) $return[]=$this->MediaModel('where_used',$id);

    return array(true,'Media where used information.',$return);

  }

}

