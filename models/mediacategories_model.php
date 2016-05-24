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

class MediaCategoriesModel extends OBFModel
{
  public function search($filters,$orderby,$orderdesc,$limit,$offset)
  {

    $this->db->what('media_categories.id','id');
    $this->db->what('media_categories.name','name');

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

    $result = $this->db->get('media_categories');

    return $result;

  }

  public function save($data,$id=false)
  {
    if(empty($id)) 
    {
      return $this->db->insert('media_categories',$data);
    }

    else 
    {
      if(!$this->db->id_exists('media_categories',$id)) return false;
      $this->db->where('id',$id);
      return $this->db->update('media_categories',$data);
    }
  }

  public function validate($data,$id=false)
  {
    if(!$data['name']) return array(false,['Category Edit','Name Required Message']);
    return array(true,'Valid.');
  }
  
  public function can_delete($id)
  {

    $this->db->where('media_category_id',$id);
    $genres = $this->db->get('media_genres');

    if(count($genres)>0) return array(false,['Category Edit','Must Remove Message']);
    else return array(true,'Can delete.');

  }

  public function delete($id)
  {
    $this->db->where('id',$id);
    $delete = $this->db->delete('media_categories');
    
    return $delete;
  }
  
  public function get_by_id($id)
  {
    $this->db->where('media_categories.id',$id);
    $this->db->what('media_categories.id','id');
    $this->db->what('media_categories.name','name');

    $category = $this->db->get_one('media_categories'); 
    
    return $category;
  }
}
