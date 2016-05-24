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

class SchedulesPermissionsModel extends OBFModel
{

  public function __construct()
  {
    parent::__construct();
  }

  public function get_permissions($start,$end,$device,$not_entry=false,$user_id=false)
  {

    // get device (for timezone)
    $this->db->where('id',$device);
    $device_data = $this->db->get_one('devices');

    // set our timezone based on device settings.  this makes sure 'strtotime' advancing by days, weeks, months will account for DST propertly.
    date_default_timezone_set($device_data['timezone']);

    // init
    $data = array();

    // fill in non-recurring data
    $this->db->query('SELECT schedules_permissions.*,users.display_name as user FROM schedules_permissions LEFT JOIN users ON users.id = schedules_permissions.user_id
                       WHERE schedules_permissions.device_id = "'.$this->db->escape($device)
                        .'" AND schedules_permissions.end > "'.$this->db->escape($start)
                        .'" AND schedules_permissions.start < "'.$this->db->escape($end).'"'
                        .($not_entry && !$not_entry['recurring'] ? ' AND schedules_permissions.id != '.$not_entry['id'] : '')
                        .(!empty($user_id) ? ' AND schedules_permissions.user_id = "'.$this->db->escape($user_id).'"' : '')
                    );

    $rows = $this->db->assoc_list();

    foreach($rows as $row)
    {
      unset($row['user_id']);
      unset($row['device_id']);
      $data[]=$row;
    }

    // fill in recurring data
    $this->db->query('SELECT schedules_permissions_recurring.*,users.display_name AS user, schedules_permissions_recurring_expanded.start as single_start FROM schedules_permissions_recurring_expanded 
                        LEFT JOIN schedules_permissions_recurring ON schedules_permissions_recurring_expanded.recurring_id = schedules_permissions_recurring.id
                        LEFT JOIN users ON users.id = schedules_permissions_recurring.user_id
                        WHERE schedules_permissions_recurring.device_id = "'.$this->db->escape($device).'" AND
                        schedules_permissions_recurring_expanded.end > "'.$this->db->escape($start).'" AND
                        schedules_permissions_recurring_expanded.start < "'.$this->db->escape($end).'"'
                        .($not_entry && $not_entry['recurring'] ? ' AND schedules_permissions_recurring.id!= '.$not_entry['id'] : '') 
                        .(!empty($user_id) ? ' AND schedules_permissions_recurring.user_id = "'.$this->db->escape($user_id).'"' : '')
                    );

    $rows = $this->db->assoc_list();

    foreach($rows as $row)
    {

      $row['recurring_start']=$row['start'];
      $row['recurring_stop']=$row['stop'];

      unset($row['device_id']);
      unset($row['start']);
      unset($row['stop']);

      $row['start']=$row['single_start'];
      unset($row['single_start']);

      $data[]=$row;

    }

    return $data;

  }

  public function get_permission_by_id($id,$recurring=false)
  {

    $this->db->where('id',$id);
    if(!$recurring) $row = $this->db->get_one('schedules_permissions');
    else $row = $this->db->get_one('schedules_permissions_recurring');

    return $row;

  }

  public function delete_permission($id,$recurring=false)
  {

    $this->db->where('id',$id);
    if($recurring) $this->db->delete('schedules_permissions_recurring');
    else $this->db->delete('schedules_permissions');

    // delete from expanded if recurring
    if($recurring)
    {
      $this->db->where('recurring_id',$id);
      $this->db->delete('schedules_permissions_recurring_expanded');
    }

    return true;

  }

  public function validate_permission($data,$id=false)
  {

    // make sure data is valid.
    if($data['description']=='' || empty($data['device_id']) || empty($data['user_id']) || empty($data['mode']) || empty($data['start']) ||
        ( empty($data['x_data']) && ( $data['mode']=='xdays' || $data['mode']=='xweeks' || $data['mode']=='xmonths' )) || ($data['mode']!='once' && empty($data['stop'])))
        return array(false,'Required Field Missing');

    // check if user is valid.
    if(!$this->db->id_exists('users',$data['user_id'])) return array(false,'User Does Not Exist');

    // check if device is valid.
    $this->db->where('id',$data['device_id']);
    $device_data = $this->db->get_one('devices');

    if(!$device_data) return array(false,'Device Does Not Exist');

    // set our timezone based on device settings.  this makes sure 'strtotime' advancing by days, weeks, months will account for DST propertly.
    date_default_timezone_set($device_data['timezone']);

    // check valid scheduling mode
    if(array_search($data['mode'],array('once','daily','weekly','monthly','xdays','xweeks','xmonths'))===false)
      return array(false,'Scheduling Mode Not Valid');

    // check if start date is valid.
    if(!preg_match('/^[0-9]+$/',$data['start'])) return array(false,'Start Date/Time Not Valid');

    // check if the stop date is valid.
    if($data['mode']!='once' && !preg_match('/^[0-9]+$/',$data['stop'])) return array(false,'Stop Date/Time Not Valid');

    if($data['mode']!='once' && $data['start']>=$data['stop']) return array(false,'Stop Date/Time Not Valid');

    // check if x data is valid.
    if(!empty($data['x_data']) && (!preg_match('/^[0-9]+$/',$data['x_data']) || $data['x_data']>65535))
      return array(false,'Recurring Frequency Not Valid');


    return array(true,'Valid');

  }

  public function collision_check($data,$id=false,$edit_recurring=false)
  {

    $duration = $data['duration'];

    // does this collide with another permission?
    if(!empty($id)) $not_entry = array('id'=>$id,'recurring'=>$edit_recurring); 
    else $not_entry = false;

    $collision_check = array();

    if($data['mode']=='once') $collision_check[]=$data['start'];

    else
    {

      if($duration > 2419200) return array(false,'Recurring Permission Too Long');

      // this is a recurring item.  make sure we don't collide with ourselves.
      if($data['mode']=='daily' && $duration > 86400) return array(false,'Daily Permission Too Long');
      if($data['mode']=='weekly' && $duration > 604800) return array(false,'Weekly Permission Too Long');
      if($data['mode']=='xdays' && $duration > 86400*$data['x_data']) return array(false,'XDay Permission Too Long');
      if($data['mode']=='xweeks' && $duration > 604800*$data['x_data']) return array(false,'XWeek Permission Too Long');

      // this is a recurring item.  determine the times to use for collision checks.
      if($data['mode']=='daily' || $data['mode']=='weekly' || $data['mode']=='monthly') $interval = '+1';
      else $interval = '+'.$data['x_data'];

      if($data['mode']=='daily' || $data['mode']=='xdays') $interval.=' days';
      elseif($data['mode']=='weekly' || $data['mode']=='xweeks') $interval.=' weeks';
      else $interval.=' months';

      $tmp_time = $data['start'];

      while($tmp_time < $data['stop'])
      {

        $collision_check[]=$tmp_time;
        $tmp_time = strtotime($interval,$tmp_time);
    
      }

    }

    foreach($collision_check as $check) 
    {

      $collision_data = $this('get_permissions',$check,$check + $duration, $data['device_id'], $not_entry);

      // if(!is_array($collision_data) || count($collision_data)>0) return array(false,'This permission conflicts with another on the schedule ('.date('M j, Y',$collision_data[0]['start']).').'.print_r($collision_data,true).' '.$check.' '.($check+$duration)); 
      if(!is_array($collision_data) || count($collision_data)>0) return array(false,'Permission Conflict');  

    }

    return array(true,'No collision found.');

  }

  public function save_permission($data,$id=false,$edit_recurring=false)
  {

    $duration = $data['duration'];

    // if editing, we delete our existing permission then add a new one.  (might be another type).
    if(!empty($id))
    {

      $this->db->where('id',$id);
      if($edit_recurring) $this->db->delete('schedules_permissions_recurring');
      else $this->db->delete('schedules_permissions');

      // delete from expanded
      if($edit_recurring)
      {
        $this->db->where('recurring_id',$id);
        $this->db->delete('schedules_permissions_recurring_expanded');
      }

    }

    $dbdata = array();

    $dbdata['device_id']=$data['device_id'];
    $dbdata['user_id']=$data['user_id'];
    $dbdata['start']=$data['start'];
    $dbdata['duration']=$duration;

    $dbdata['description']=$data['description'];

    if($data['mode']!='once')
    {

      $dbdata['mode']=$data['mode'];
      $dbdata['x_data']=$data['x_data'];
      $dbdata['stop']=$data['stop'];

      $recurring_id = $this->db->insert('schedules_permissions_recurring',$dbdata);

      // fill out our expanded recurring data (schedules_permissions_recurring_expanded)
      if($dbdata['mode']=='daily' || $dbdata['mode']=='weekly' || $dbdata['mode']=='monthly') $interval = '+1';
      else $interval = '+'.$dbdata['x_data'];

      if($dbdata['mode']=='daily' || $dbdata['mode']=='xdays') $interval.=' days';
      elseif($dbdata['mode']=='weekly' || $dbdata['mode']=='xweeks') $interval.=' weeks';
      else $interval.=' months';

      $tmp_time = $dbdata['start'];

      $count=0;

      $expanded_data = array();
      $expanded_data['recurring_id']=$recurring_id;

      while($tmp_time < $dbdata['stop'])
      {

        $count++;

        $expanded_data['start'] = $tmp_time;
        $expanded_data['end'] = $tmp_time + $dbdata['duration'];
        $this->db->insert('schedules_permissions_recurring_expanded',$expanded_data);

        $tmp_time = strtotime($interval,$tmp_time);
    
      }

    }

    else 
    {
      $dbdata['end']=$dbdata['start']+$dbdata['duration']; // we also add 'end' data, which is used to speed up permission searching
      $this->db->insert('schedules_permissions',$dbdata);
    }

    return;

  }

}
