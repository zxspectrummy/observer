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

class Settings extends OBFController
{

  public $media_types = array('audio','image','video');

  public function __construct()
  {
    parent::__construct();
    $this->user->require_authenticated();

    $this->MediaCategoriesModel = $this->load->model('MediaCategories');
    $this->MediaCountriesModel = $this->load->model('MediaCountries');
    $this->MediaGenresModel = $this->load->model('MediaGenres');
    $this->MediaLanguagesModel = $this->load->model('MediaLanguages');
    $this->MediaMetadataModel = $this->load->model('MediaMetadata');

    $this->SettingsModel = $this->load->model('Settings');
  }

  public function metadata_order()
  {
    $this->user->require_permission('manage_media_settings');
    $this->MediaMetadataModel('save_field_order',$this->data('order'));
    //T Metadata field order saved.
    return [true,'Metadata field order saved.'];
  }

  public function metadata_save()
  {
    $this->user->require_permission('manage_media_settings');

    $id = (int) $this->data('id');

    $data = [];
    $data['name'] = trim(strtolower($this->data('name')));
    $data['description'] = trim($this->data('description'));
    $data['type'] = trim($this->data('type'));
    $data['select_options'] = trim($this->data('select_options'));

    $data['default'] = $this->data('default');
    if(is_array($data['default'])) $data['default'] = array_map('trim',$data['default']);
    else $data['default'] = trim($data['default']);
    
    $data['tag_suggestions'] = $this->data('tag_suggestions');
    if(is_array($data['tag_suggestions'])) $data['tag_suggestions'] = array_map('trim',$data['tag_suggestions']);
    else $data['tag_suggestions'] = [];

    $validation = $this->MediaMetadataModel('validate',$data,$id);
    if($validation[0]==false) return $validation;

    $save = $this->MediaMetadataModel('save',$data,$id);

    if(!$save) return [false,'An unknown error occurred while trying to save this metadata field.'];
    else return [true,'Metadata field saved.'];
  }

  public function metadata_delete()
  {
    $this->user->require_permission('manage_media_settings');

    $delete = $this->MediaMetadataModel('delete',$this->data('id'));

    if(!$delete) return [false,'An unknown error occured while trying to delete this metadata field.'];
    else return [true,'Metadata field deleted.'];
  }
  
  public function metadata_tag_search()
  {
    $results = $this->MediaMetadataModel('tag_search',[
      'id' => $this->data('id'),
      'search' => $this->data('search')
    ]);
    return [true,'Tag search.',$results];
  }

  public function category_list()
  {
    $filters = $this->data('filters');
    $orderby = $this->data('orderby');
    $orderdesc = $this->data('orderdesc');
    $limit = $this->data('limit');
    $offset = $this->data('offset');

    $categories = $this->MediaCategoriesModel('search',$filters,$orderby,$orderdesc,$limit,$offset);

    if($categories === false) return array(false,'An unknown error occurred while fetching categories.');
    else return array(true,'Category list.',$categories);
  }

  public function category_save()
  {
    $this->user->require_permission('manage_media_settings');

    $id = trim($this->data['id']);

    $data = array();
    $id = $this->data('id');
    $data['name'] = trim($this->data('name'));
    $data['is_default'] = $this->data('default');

    $validation = $this->MediaCategoriesModel('validate',$data,$id);
    if($validation[0]==false) return $validation;

    $save = $this->MediaCategoriesModel('save',$data,$id);

    if(!$save) return array(false,'An unknown error occurred while trying to save this category.');
    else return array(true,'Category saved.');
  }

  public function category_delete()
  {
    $this->user->require_permission('manage_media_settings');

    $id = trim($this->data['id']);

    $can_delete = $this->MediaCategoriesModel('can_delete',$id);
    if($can_delete[0]==false) return $can_delete;

    $delete = $this->MediaCategoriesModel('delete',$id);

    if($delete) return array(true,'Category deleted.');
    else return array(false,'An unknown error occured while trying to delete the category.');
  }

  public function category_get()
  {
    $id = trim($this->data['id']);

    $category = $this->MediaCategoriesModel('get_by_id',$id);

    if($category) return array(true,'Category information.',$category);
    else return array(false,'Category not found.');
  }

  public function genre_list()
  {
    $filters = $this->data('filters');
    $orderby = $this->data('orderby');
    $orderdesc = $this->data('orderdesc');
    $limit = $this->data('limit');
    $offset = $this->data('offset');

    $genres = $this->MediaGenresModel('search',$filters,$orderby,$orderdesc,$limit,$offset);

    if($genres === false) return array(false,'An unknown error occurred while fetching genres.');
    else return array(true,'Genre list.',$genres);
  }

  public function genre_save()
  {

    $this->user->require_permission('manage_media_settings');

    $data = array();
    $id = trim($this->data['id']);
    $data['name'] = $this->data('name');
    $data['description'] = $this->data('description');
    $data['media_category_id'] = $this->data('media_category_id');
    $data['is_default'] = $this->data('default');

    $validation = $this->MediaGenresModel('validate',$data,$id);
    if($validation[0]==false) return $validation;

    if($this->MediaGenresModel('save',$data,$id)) return array(true,'Genre saved.');
    else return array(false,'An unknown error occurred while trying to save this genre.');

  }

  public function genre_delete()
  {

    $this->user->require_permission('manage_media_settings');

    $id = trim($this->data['id']);

    $delete = $this->MediaGenresModel('delete',$id);

    if($delete) return array(true,'Genre deleted.');
    else return array(false,'An unknown error occured while trying to delete the genre.');

  }

  public function genre_get()
  {

    $id = trim($this->data['id']);

    $genre = $this->MediaGenresModel('get_by_id',$id);

    if($genre) return array(true,'Genre information.',$genre);
    else return array(false,'Genre not found.');

  }

  public function country_list()
  {

    $types = $this->MediaCountriesModel('get_all');

    if($types === false) return array(false,'An unknown error occured while fetching countries.');
    else return array(true,'Country list.',$types);

  }

  public function language_list()
  {

    $types = $this->MediaLanguagesModel('get_all');

    if($types === false) return array(false,'An unknown error occured while fetching languages.');
    else return array(true,'Language list.',$types);

  }

  public function get_ob_version()
  {
    if(file_exists('VERSION')) $version = trim(file_get_contents('VERSION'));
    else $version = '';

    return array(true,'OpenBroadcaster Version',$version);
  }

  public function media_metadata_fields()
  {
    $fields = $this->MediaMetadataModel('get_all');
    return array(true,'Media metadata fields.',$fields);
  }

  public function media_get_fields () {
    return $this->MediaMetadataModel('get_fields');
  }

  public function media_required_fields () {
    $this->user->require_permission('manage_media_settings');
    $result = [false, 'An unknown error occurred while trying to update required media fields.'];

    $result = $this->MediaMetadataModel('validate_fields', $this->data);
    if (!$result[0]) {
      return $result;
    }

    $data = array(
      'artist'                  => $this->data['artist'],
      'album'                   => $this->data['album'],
      'year'                    => $this->data['year'],
      'category_id'             => $this->data['category_id'],
      'country_id'              => $this->data['country_id'],
      'language_id'             => $this->data['language_id'],
      'comments'                => $this->data['comments'],
      'dynamic_content_default' => $this->data['dynamic_content_default'],
      'dynamic_content_hidden'  => $this->data['dynamic_content_hidden']
    );

    $result = $this->MediaMetadataModel('required_fields', $data);

    return $result;
  }

}
