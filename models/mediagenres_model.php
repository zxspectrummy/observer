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

class MediaGenresModel extends OBFModel
{
  public function search($filters,$orderby,$orderdesc,$limit,$offset)
  {

    $this->db->what('media_genres.id','id');
    $this->db->what('media_genres.name','name');
    $this->db->what('media_genres.description','description');
    $this->db->what('media_genres.media_category_id','media_category_id');
    $this->db->what('media_categories.name','media_category_name');

    $this->db->leftjoin('media_categories','media_genres.media_category_id','media_categories.id');

    if($filters) foreach($filters as $filter)
    {
      $column = $filter['column'];
      $value = $filter['value'];
      $operator = (empty($filter['operator']) ? '=' : $filter['operator']);

      $this->db->where($column,$value,$operator);
    }

    if($orderby) $this->db->orderby($orderby,(!empty($orderdesc) ? 'desc' : 'asc'));
    else $this->db->orderby('name','asc');    

    if($limit) $this->db->limit($limit);
    if($offset) $this->db->offset($offset);

    $result = $this->db->get('media_genres');

    return $result; 
  }
  
  public function save($data,$id=false)
  {
    if(empty($id)) { 
      return $this->db->insert('media_genres',$data);
    }

    else {
      $this->db->where('id',$id);
      $this->db->update('media_genres',$data);

      // changing the parent category of an existing genre.  update media items table appropriately.
      // note - fix at some point - media category column is redundant.
      if(!empty($data['media_category_id']))
      {
        $this->db->query('update media set category_id = "'.$this->db->escape($data['media_category_id']).'" where genre_id = "'.$this->db->escape($id).'"');
      }

      return true;
    } 
  }
  
  public function validate($data,$id=false)
  {
    if(!$data['name']) return array(false,['Genre Edit','Name Required Message']);
    if(!$this->db->id_exists('media_categories',$data['media_category_id'])) return array(false,'The category ID is invalid.');

    return array(true,'Valid.');
  }

  public function delete($id)
  {
    $this->db->where('id',$id);
    $delete = $this->db->delete('media_genres');

    return $delete;
  }
  
  public function get_by_id($id)
  {
    $this->db->where('media_genres.id',$id);
    $this->db->what('media_genres.id','id');
    $this->db->what('media_genres.name','name');
    $this->db->what('media_genres.description','description');
    $this->db->what('media_genres.media_category_id','media_category_id');

    $genre = $this->db->get_one('media_genres');

    return $genre;
  }

}
