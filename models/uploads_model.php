<?

/*     
    Copyright 2012 OpenBroadcaster, Inc.

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

class UploadsModel extends OBFModel
{

  public function is_valid($id,$key)
  {

    $id = trim($id);
    $key = trim($key);

    if(empty($id) || empty($key)) return false;

    $this->db->where('id',$id);
    $this->db->where('key',$key);

    if($this->db->get_one('uploads')) return true;
    else return false;

  }

  public function file_info($id,$key)
  {

    $id = trim($id);
    $key = trim($key);

    if(empty($id) || empty($key)) return false;

    $this->db->where('id',$id);
    $this->db->where('key',$key);

    $this->db->what('type');
    $this->db->what('format');
    $this->db->what('duration');

    return $this->db->get_one('uploads');

  }

}
