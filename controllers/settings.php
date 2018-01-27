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



}
