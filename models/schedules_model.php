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

class SchedulesModel extends OBFModel
{

  public function __construct()
  {
    parent::__construct();
    $this->SchedulesPermissionsModel = $this->load->model('SchedulesPermissions');
    $this->PlaylistsModel = $this->load->model('Playlists');
    $this->MediaModel = $this->load->model('Media');
  }

  public function friendly_schedule($start,$end,$device)
  {

    $timeslots = $this->SchedulesPermissionsModel('get_permissions',$start,$end,$device);

    $schedule = array();

    foreach($timeslots as $timeslot)
    {
      $item = array();
      $item['duration']=$timeslot['duration'];
      $item['start']=$timeslot['start'];
      $item['name']=$timeslot['description'];
      $schedule[]=$item;
    }

    // merge in scheduled show data where we don't have timeslot data.
    // TODO this doesn't yet consider where scheduled shows might overlap timeslots, or where we
    // might have more than one playlist mapped to a single timeslot.

    usort($schedule,array($this,'order_schedule'));

    $shows = $this('get_shows',$start,$end,$device);

    if($shows)
    {

      $shows=$shows[2];

      usort($shows,array($this,'order_schedule'));

      $index = 0;

      foreach($shows as $show)
      {

        while(count($schedule)>=($index+1) && $schedule[$index]['start']<$show['start'])
          $index++;

        if(!isset($schedule[$index]) || $schedule[$index]['start']!=$show['start'])
        {

          $item = array();
          $item['duration']=$show['duration'];
          $item['start']=$show['start'];
          $item['name']=$show['name'];

          $schedule[] = $item;

        }

      }

    }

    return $schedule;

  }

  public function get_shows($start,$end,$device,$not_entry=false)
  {

    // get device (for timezone)
    $this->db->where('id',$device);
    $device_data = $this->db->get_one('devices');

    // set our timezone based on device settings.  this makes sure 'strtotime' advancing by days, weeks, months will account for DST propertly.
    date_default_timezone_set($device_data['timezone']);

    // init
    $data = array();

    // fill in non-recurring data
    $this->db->query('SELECT schedules.*,users.display_name as user FROM schedules LEFT JOIN users ON users.id = schedules.user_id
                       WHERE schedules.device_id = "'.$this->db->escape($device).'" AND
                        schedules.end > "'.$this->db->escape($start).'" AND
                        schedules.start < "'.$this->db->escape($end).'"'.($not_entry && !$not_entry['recurring'] ? ' AND schedules.id != '.$not_entry['id'] : ''));

    $rows = $this->db->assoc_list();

    foreach($rows as $row)
    {
      unset($row['device_id']);
      $data[]=$row;
    }

    // fill in recurring data
    $this->db->query('SELECT schedules_recurring.*,users.display_name AS user, schedules_recurring_expanded.start as single_start FROM schedules_recurring_expanded
                        LEFT JOIN schedules_recurring ON schedules_recurring_expanded.recurring_id = schedules_recurring.id
                        LEFT JOIN users ON users.id = schedules_recurring.user_id
                        WHERE schedules_recurring.device_id = "'.$this->db->escape($device).'" AND
                        schedules_recurring_expanded.end > "'.$this->db->escape($start).'" AND
                        schedules_recurring_expanded.start < "'.$this->db->escape($end).'"'
                        .($not_entry && $not_entry['recurring'] ? ' AND schedules_recurring.id!= '.$not_entry['id'] : ''));

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

    // get media/playlist name for each item.
    foreach($data as $index=>$item)
    {

      // $this->db->where('id',$item['item_id']);

      if($item['item_type']=='playlist')
      {
        $playlist = $this->PlaylistsModel('get_by_id',$item['item_id']);
        $data[$index]['name']=$playlist['name'];
        $data[$index]['description']=$playlist['description'];
        $data[$index]['owner'] = $playlist['owner_name'];
        $data[$index]['type'] = $playlist['type'];
      }

      elseif($item['item_type']=='media')
      {
        $media = $this->MediaModel('get_by_id',$item['item_id']);
        $data[$index]['name'] = $media['title'];
        $data[$index]['owner'] = $media['owner_name'];
        $data[$index]['type'] = 'standard';
      }

      elseif($item['item_type']=='linein')
      {
        $data[$index]['name'] = 'Line-In';
        $data[$index]['type'] = 'standard';
        $data[$index]['owner'] = 'n/a';
      }

    }

    return $data;

  }

  public function get_show_by_id($id,$recurring=false)
  {

    $this->db->where('id',$id);
    if($recurring) $row = $this->db->get_one('schedules_recurring');
    else $row = $this->db->get_one('schedules');

    if(!$row) return false;

    if(!$row) return array(false,'Show not found.');

    // if linein, we don't need item data so return early.
    if($row['item_type']=='linein') return $row;

    // get item data
    $this->db->where('id',$row['item_id']);
    if($row['item_type']=='media')
    {
      $media = $this->db->get_one('media');
      $row['item_name'] = $media['artist'].' - '.$media['title'];
    }

    elseif($row['item_type']=='playlist')
    {
      $playlist = $this->db->get_one('playlists');
      $row['item_name'] = $playlist['name'];
    }

    return $row;

  }

  public function delete_show($id,$recurring=false)
  {

    // get show information, start time and device id needed for liveassist cache delete
    $this->db->where('id',$id);
    if($recurring) $show = $this->db->get_one('schedules_recurring');
    else $show = $this->db->get_one('schedules');

    if(!$show) return false;

    // if recurring, figure out show start times for deleting liveassist cache.
    $starts = [];
    if($recurring)
    {
      $this->db->where('recurring_id',$id);
      $recurring_expanded = $this->db->get('schedules_recurring_expanded');
      foreach($recurring_expanded as $expanded) $starts[] = $expanded['start'];
    }
    else $starts[] = $show['start'];

    // proceed with delete.
    $this->db->where('id',$id);
    if($recurring) $this->db->delete('schedules_recurring');
    else $this->db->delete('schedules');

    // delete from cache
    $this->db->where('schedule_id',$id);
    if($recurring) $this->db->where('mode','recurring');
    else $this->db->where('mode','once');
    $this->db->delete('schedules_media_cache');

    // delete from expanded if recurring
    if($recurring)
    {
      $this->db->where('recurring_id',$id);
      $this->db->delete('schedules_recurring_expanded');
    }

    // delete related liveassist cache
    foreach($starts as $start)
    {
      $this->db->where('device_id',$show['device_id']);
      $this->db->where('start',$start);
      $this->db->delete('schedules_liveassist_buttons_cache');
    }

    return true;

  }

  public function validate_show($data,$id=false)
  {

    // make sure data is valid.
    if(empty($data['device_id']) || empty($data['mode']) || empty($data['start'])
        || (       empty($data['x_data']) && ( $data['mode']=='xdays' || $data['mode']=='xweeks' || $data['mode']=='xmonths' )     )
        || (       $data['mode']!='once' && empty($data['stop'])     )
        || (       empty($id) && (empty($data['item_type']) || ($data['item_type']!='linein' && empty($data['item_id'])))          )
    )

    //T One or more required fields were not filled.
    return array(false,'One or more required fields were not filled.');

    // check if device is valid.
    $this->db->where('id',$data['device_id']);
    $device_data = $this->db->get_one('devices');

    //T This player no longer exists.
    if(!$device_data) return array(false,'This player no longer exists.');

    // set our timezone based on device settings.  this makes sure 'strtotime' advancing by days, weeks, months will account for DST propertly.
    date_default_timezone_set($device_data['timezone']);

    // make sure item type/id is valid (if not editing)
    if(empty($id))
    {

      if($data['item_type']!='playlist' && $data['item_type']!='media' && $data['item_type']!='linein') return array(false,'Item Invalid');

      //T The item you are attempting to schedule is not valid.
      if(empty($data['item_id']) && $data['item_type']!='linein') return array(false,'The item you are attempting to schedule is not valid.');

      if($data['item_type']=='playlist')
      {

        $this->db->where('id',$data['item_id']);
        $playlist = $this->db->get_one('playlists');

        //T The item you are attempting to schedule does not exist.
        if(!$playlist) return array(false,'The item you are attempting to schedule does not exist.');

        // don't allow use of private playlist unless playlist manager or owner of this playlist.
        if($playlist['status']=='private' && $playlist['owner_id']!=$this->user->param('id')) $this->user->require_permission('manage_playlists');

      }

      elseif($data['item_type']=='media')
      {

        $this->db->where('id',$data['item_id']);
        $media = $this->db->get_one('media');

        //T The item you are attempting to schedule does not exist.
        if(!$media) return array(false,'The item you are attempting to schedule does not exist.');

        // don't allow use of private media unless media manage or owner of this media.
        if($media['status']=='private' && $media['owner_id']!=$this->user->param('id')) $this->user->require_premission('manage_media');

        //T The media must be approved.
        if($media['is_approved']==0) return array(false,'The media must be approved.');
        //T The media must not be archived.
        if($media['is_archived']==1) return array(false,'The media must not be archived.');

      }

      elseif($data['item_type']=='linein')
      {
        // make sure linein scheduling is supported by this device.
        //T The item you are attempting to schedule is not valid.
        if(empty($device_data['support_linein'])) return array(false,'The item you are attempting to schedule is not valid.');
      }

    }

    // check valid scheduling mode
    //T The selected scheduling mode is not valid.
    if(array_search($data['mode'],array('once','daily','weekly','monthly','xdays','xweeks','xmonths'))===false)
      return array(false,'The selected scheduling mode is not valid.');

    // check if start date is valid.
    //T The start date/time is not valid.
    if(!preg_match('/^[0-9]+$/',$data['start'])) return array(false,'The start date/time is not valid.');

    // check if the stop date is valid.
    //T The stop (last) date is not valid and must come after the start date/time.
    if($data['mode']!='once' && !preg_match('/^[0-9]+$/',$data['stop'])) return array(false,'The stop (last) date is not valid and must come after the start date/time.');
    //T The stop (last) date is not valid and must come after the start date/time.
    if($data['mode']!='once' && $data['start']>=$data['stop']) return array(false,'The stop (last) date is not valid and must come after the start date/time.');


    // check if x data is valid.
    //T The recurring frequency is not valid.
    if(!empty($data['x_data']) && (!preg_match('/^[0-9]+$/',$data['x_data']) || $data['x_data']>65535))
      return array(false,'The recurring frequency is not valid.');

    return array(true,'Valid.');

  }

  // see if this collides with another scheduled item (excluding item with id = $id).
  public function collision_permission_check($data,$id=false,$edit_recurring=false)
  {

    if(!empty($id)) $not_entry = array('id'=>$id,'recurring'=>$edit_recurring);
    else $not_entry = false;

    $collision_check = array();

    $duration = $data['duration'];

    if($data['mode']=='once') $collision_check[]=$data['start'];

    else
    {

      //T Recurring shows cannot be longer than 28 days.
      if($duration > 2419200) return array(false,'Recurring shows cannot be longer than 28 days.');

      // this is a recurring item.  make sure we don't collide with ourselves.
      //T A show scheduled daily cannot be longer than a day.
      if($data['mode']=='daily' && $duration > 86400) return array(false,'A show scheduled daily cannot be longer than a day.');
      //T A show scheduled weekly cannot be longer than a week.
      if($data['mode']=='weekly' && $duration > 604800) return array(false,'A show scheduled weekly cannot be longer than a week.');
      //T A show cannot be longer than its frequency.
      if($data['mode']=='xdays' && $duration > 86400*$data['x_data']) return array(false,'A show cannot be longer than its frequency.');
      //T A show cannot be longer than its frequency.
      if($data['mode']=='xweeks' && $duration > 604800*$data['x_data']) return array(false,'A show cannot be longer than its frequency.');


      // this is a recurring item.  set up times to check for collisions
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

      $collision_data = $this('get_shows',$check,$check + $duration, $data['device_id'], $not_entry);
      $collision_data = $collision_data;

      //T This show conflicts with another on the schedule.
      if(!is_array($collision_data) || count($collision_data)>0) return array(false,'This show conflicts with another on the schedule.');

    }

    // check schedule permission unless we're a schedule admin or have advanced scheduling permission
    if(!$this->user->check_permission('manage_schedule_permissions or advanced_show_scheduling'))
    {

      foreach($collision_check as $check)
      {

        $permissions = $this->SchedulesPermissionsModel('get_permissions',$check,$check + $duration, $data['device_id'], false, $this->user->param('id'));

        // put our permissions in order so we can make sure they are adequate.
        usort($permissions,array($this,'order_schedule'));

        // make sure there are no gaps in the permission between this start and end timestamp.
        $permission_check_failed = false;

        // the first permission must start at or be equal to the check start.
        if($permissions[0]['start'] > $check) $permission_check_failed = true;

        // the last permission must end at the end of our permission or later.
        if( ($permissions[count($permissions)-1]['start'] + $permissions[count($permissions)-1]['duration']) < ($check + $duration) ) $permission_check_failed = true;

        // make sure there are no gaps...
        foreach($permissions as $index=>$permission)
        {

          if($index==0)
          {
            $permission_last_end = $permission['start'] + $permission['duration'];
            continue;
          }

          if($permission['start'] > $permission_last_end)
          {
            $permission_check_failed = true;
            break;
          }

        }

        //T You do not have permission to schedule an item at the specified time(s).
        if($permission_check_failed) return array(false,'You do not have permission to schedule an item at the specified time(s).');

      }

    }

    return array(true,'No collision, permissions okay.');

  }

  public function save_show($data,$id,$edit_recurring)
  {

    // if editing, we delete our existing show then add a new one.  (might be another type).
    // if editing, we also delete our expanded show data in schedules_recurring_expanded (if recurring)

    if(!empty($id))
    {

      $this->db->where('id',$id);
      if($edit_recurring) $show_data = $this->db->get_one('schedules_recurring');
      else $show_data = $this->db->get_one('schedules');

      $this->db->where('id',$id);
      if($edit_recurring) $this->db->delete('schedules_recurring');
      else $this->db->delete('schedules');

      // delete from cache
      $this->db->where('schedule_id',$id);
      if($edit_recurring) $this->db->where('mode','recurring');
      else $this->db->where('mode','once');
      $this->db->delete('schedules_media_cache');

      // delete from expanded
      if($edit_recurring)
      {
        $this->db->where('recurring_id',$id);
        $this->db->delete('schedules_recurring_expanded');
      }

    }

    $dbdata = array();

    $dbdata['device_id']=$data['device_id'];
    $dbdata['start']=$data['start'];
    $dbdata['duration']=$data['duration'];
    $dbdata['user_id']=$this->user->param('id');

    if(!empty($id))
    {
      $dbdata['item_id']=$show_data['item_id'];
      $dbdata['item_type']=$show_data['item_type'];
    }

    else
    {
      $dbdata['item_id']=$data['item_id'];
      $dbdata['item_type']=$data['item_type'];
    }

    if($data['mode']!='once')
    {

      $dbdata['mode']=$data['mode'];
      $dbdata['x_data']=$data['x_data'];
      $dbdata['stop']=$data['stop'];

      $recurring_id = $this->db->insert('schedules_recurring',$dbdata);

      // fill out our expanded recurring data (schedules_recurring_expanded)
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
        $this->db->insert('schedules_recurring_expanded',$expanded_data);

        $tmp_time = strtotime($interval,$tmp_time);

      }

    }

    else
    {
      $dbdata['end']=$dbdata['start']+$dbdata['duration']; // we also add 'end' data, which is used to speed up show searching
      $this->db->insert('schedules',$dbdata);
    }

    return true;

  }

  private function order_schedule($a,$b)
  {
    if($a['start']>$b['start']) return 1;
    else return -1;
  }

}
