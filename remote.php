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

require('components.php');

date_default_timezone_set('Etc/UTC');

function openbroadcaster_show_times_sort($a,$b)
{
  if($a['start']==$b['start']) return 0;
  return ($a['start'] < $b['start']) ? -1 : 1;
}

// TODO: refactoring needed.  this is a bit mind boggling (especially schedule and default playlist stuff).
class Remote
{

  private $io;
  private $load;
  private $user;
  private $db;

  private $devmode;
  private $device;
  private $buffer;
  private $localtime;

  private $xml;

  private $SchedulesPermissionsModel;
  private $SchedulesModel;
  private $MediaModel;
  private $DevicesModel;

  public function __construct()
  {
    $this->io = OBFIO::get_instance();
    $this->load = OBFLoad::get_instance();
    $this->user = OBFUser::get_instance();
    $this->db = OBFDB::get_instance();

    $this->SchedulesPermissionsModel = $this->load->model('SchedulesPermissions');
    $this->SchedulesModel = $this->load->model('Schedules');
    $this->MediaModel = $this->load->model('Media');
    $this->DevicesModel = $this->load->model('Devices');

    // development/testing mode
    if(!empty($_GET['devmode']) && defined('OB_REMOTE_DEBUG') && $_GET['devmode']==OB_REMOTE_DEBUG) { $_POST=$_GET; $this->devmode=TRUE; }
    else $this->devmode=FALSE;

    // get our action
    if(!empty($_GET['action'])) $action = $_GET['action'];
    else $action = null;

    // authenticate the device, load device information.
    if(empty($_POST['id'])) $this->send_xml_error('device id required');

    $this->db->where('id',$_POST['id']);
    $this->device = $this->db->get_one('devices');

    if($this->device['parent_device_id'])
    {
      $this->db->where('id',$this->device['parent_device_id']);
      $this->parent_device = $this->db->get_one('devices');
    }

    if($this->device['parent_device_id'] && $this->device['use_parent_playlist'])
    {
      $this->default_playlist_id = $this->parent_device['default_playlist_id'];
      $this->default_playlist_device_id = $this->device['parent_device_id'];
    }
    else
    {
      $this->default_playlist_id = $this->device['default_playlist_id'];
      $this->default_playlist_device_id = $this->device['id'];
    }

    if($this->device['parent_device_id'] && $this->device['use_parent_schedule'])
    {
      $this->schedule_device_id = $this->device['parent_device_id'];
    }
    else 
    {
      $this->schedule_device_id = $this->device['id'];
    }

    if($this->device['parent_device_id'] && $this->device['use_parent_schedule'] && $this->device['use_parent_dynamic'])
    {
      $this->cache_device_id = $this->device['parent_device_id'];
    }
    else
    {
      $this->cache_device_id = $this->device['id'];
    }

    // see if password matches (using old/bad hashing or new/good hashing).
    if(!empty($this->device['password']))
    {
      $password_info = password_get_info($this->device['password']);
      if($password_info['algo']==0) $password_match = $this->device['password']==sha1(OB_HASH_SALT.$_POST['pw']);
      else $password_match = password_verify($_POST['pw'].OB_HASH_SALT, $this->device['password']);
    }
    else $password_match = false;

    // if password is correct but needs rehashing, do that now and store in db.
    if($password_match && password_needs_rehash($this->device['password'], PASSWORD_DEFAULT))
    {
      $new_password_hash = password_hash($_POST['pw'].OB_HASH_SALT, PASSWORD_DEFAULT);
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('password'=>$new_password_hash));
    }

    if(!$this->devmode && (!$this->device || !$password_match || ($_SERVER['REMOTE_ADDR']!=$this->device['ip_address'] && $this->device['ip_address']!='') )) 
      $this->send_xml_error('invalid id/password/ip combination');

    if($action=='schedule' || $action=='emerg')
    {
      if(isset($_POST['buffer'])) $this->buffer = $_POST['buffer']*86400;
      elseif(isset($_POST['hbuffer'])) $this->buffer = $_POST['hbuffer']*3600;
    }

    if(($action=='schedule' || $action=='emerg') && empty($this->buffer)) 
      $this->send_xml_error('required information missing');

    $this->localtime=time();

    // update our 'last connect' date
    $this->db->where('id',$this->device['id']);
    $this->db->update('devices',array('last_connect'=>$this->localtime,'last_ip_address'=>$_SERVER['REMOTE_ADDR']));

    // initialize xml stuff.
    $this->xml=new SimpleXMLElement('<?xml version=\'1.0\' standalone=\'yes\'?><obconnect></obconnect>');

    if($action=='version')
    {
        if(!empty($_POST['version'])) 
        {
          $this->DevicesModel('update_version',$this->device['id'],$_POST['version']);
	//add function to update location
         if(!empty($_POST['longitude']) || !empty($_POST['latitude'])) 
         {
           $this->DevicesModel('update_location',$this->device['id'],$_POST['longitude'],$_POST['latitude']);
         }
  
        }

	if(is_file('VERSION'))
        {
          $version = trim(file_get_contents('VERSION'));
          header ("content-type: application/json");
          echo json_encode($version);
        }
    }

    elseif($action=='schedule') 
    {
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('last_connect_schedule'=>$this->localtime));
      $this->schedule();

      // reset/untoggle any last connect warning
      $this->db->where('event','device_last_connect_schedule_warning');
      $this->db->where('device_id',$this->device['id']);
      $this->db->update('notices',array('toggled'=>0));
    }

    elseif($action=='emerg') 
    {
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('last_connect_emergency'=>$this->localtime));
      $this->emergency();

      // reset/untoggle any last connect warning
      $this->db->where('event','device_last_connect_emergency_warning');
      $this->db->where('device_id',$this->device['id']);
      $this->db->update('notices',array('toggled'=>0));

    }
    elseif($action=='playlog_status') 
    {
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('last_connect_playlog'=>$this->localtime));
      $this->playlog_status();

      // reset/untoggle any last connect warning
      $this->db->where('event','device_last_connect_playlog_warning');
      $this->db->where('device_id',$this->device['id']);
      $this->db->update('notices',array('toggled'=>0));

    }
    elseif($action=='playlog_post') 
    {
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('last_connect_playlog'=>$this->localtime));
      $this->playlog_post();

      // reset/untoggle any last connect warning
      $this->db->where('event','device_last_connect_playlog_warning');
      $this->db->where('device_id',$this->device['id']);
      $this->db->update('notices',array('toggled'=>0));

    }
    elseif($action=='media') 
    {
      $this->db->where('id',$this->device['id']);
      $this->db->update('devices',array('last_connect_media'=>$this->localtime));
      $this->media();
    }
    elseif($action=='now_playing') $this->update_now_playing();

  }

  // shortcut to use $this->ModelName('method',arg1,arg2,...).
  public function __call($name,$args)
  {
    if(!isset($this->$name)) 
    {
      $stack = debug_backtrace();
      trigger_error('Call to undefined method '.$name.' ('.$stack[0]['file'].':'.$stack[0]['line'].')', E_USER_ERROR);
    }

    $obj = $this->$name;

    return call_user_func_array($obj,$args);
  }

  private function schedule()
  {

    // a little buffer... 
    $localtime=strtotime("-1 minute",$this->localtime);

    // build the schedule XML
    $schedxml=$this->xml->addChild('schedule');

    $end_timestamp=$localtime + $this->buffer + 60;

    $shows = $this->SchedulesModel('get_shows',$localtime,$end_timestamp,$this->schedule_device_id);

    $show_times = array();

    foreach($shows as $show) 
    {

      // skip this show if linein but not supported (will get default playlist instead later if available)
      if($show['item_type']=='linein' && empty($this->device['support_linein'])) continue;

      $media_items = false;

      $showxml = $schedxml->addChild('show');
      $showxml->addChild('id',$show['id']);
      $showxml->addChild('date',gmdate('Y-m-d',$show['start']));
      $showxml->addChild('time',gmdate('H:i:s',$show['start']));
      $showxml->addChild('type',$show['type']);

      // determine show name (timeslot name) for this playlist.
      // TODO this only considers the start of the timeslot... what if playlist overlaps timeslots?
      // best option might be to not allow playlist to overlap timeslots (which might be useful in itself).
      $timeslot = $this->SchedulesPermissionsModel('get_permissions',$show['start'],$show['start']+1,$this->schedule_device_id);
      // $timeslot = $timeslot[2];
      if(!empty($timeslot)) $showxml->addChild('name',$timeslot[0]['description']);

      $mediaxml = $showxml->addChild('media'); 

      if($show['item_type']=='linein')
      {
        $media_items = array(array('type'=>'linein','duration'=>$show['duration']));
      }

      elseif($show['item_type']=='media')
      {
        $this->db->where('id',$show['item_id']);
        $media = $this->db->get_one('media');

        if($media)
        {
          if(empty($timeslot)) $showxml->addChild('name','');
          $showxml->addChild('description',$media['artist'].' - '.$media['title']);
          $showxml->addChild('last_updated',$media['updated']);

          $media_items=array($media);
        }
      }

      elseif($show['item_type']=='playlist')
      {
  
        $this->db->where('id',$show['item_id']);
        $playlist = $this->db->get_one('playlists');
        $showxml->addChild('description',$playlist['description']);

        // if we didn't get our show name from the timeslot, then use the playlist name as the show name.
        if(empty($timeslot)) $showxml->addChild('name',$playlist['name']);

        // see if we have selected media in our cache.
        $this->db->where('schedule_id',$show['id']);
        if(!empty($show['recurring_start'])) 
        {
          $this->db->where('mode','recurring');
          $this->db->where('start',$show['start']);
        }
        else $this->db->where('mode','once');
        $this->db->where('device_id',$this->device['id']);

        $cache = $this->db->get_one('schedules_media_cache');

        if($cache)
        {
          $media_items = json_decode($cache['data']);
          foreach($media_items as $index=>$tmp) $media_items[$index]=get_object_vars($tmp); // convert object to assoc. array
          $showxml->addChild('last_updated',$cache['created']);
        }
    
        // are we using a parent device for cache?
        elseif($this->cache_device_id != $this->device['id']) // this was set to $this->device['device_id'] which i'm quite sure was wrong... (fyi in case i broke something).
        {

          $this->db->where('schedule_id',$show['id']);
          if(!empty($show['recurring_start'])) 
          {
            $this->db->where('mode','recurring');
            $this->db->where('start',$show['start']);
          }
          else $this->db->where('mode','once');
          $this->db->where('device_id',$this->cache_device_id);

          $cache = $this->db->get_one('schedules_media_cache');

          // we are supposed to use a parent device for cache, but that device doesn't have the cached item yet.
          if(!$cache)
          {
            $media_items = $this->generate_playlist_items($playlist['id'],$this->schedule_device_id,true);
            $cache_created = time();
            $this->db->insert('schedules_media_cache',array('device_id'=>$this->cache_device_id,'mode'=>(!empty($show['recurring_start']) ? 'recurring' : 'once'),'schedule_id'=>$show['id'],'start'=>$show['start'],'duration'=>$show['duration'],'data'=>json_encode($media_items),'created'=>$cache_created));
          }

          // oh, we do have cache from parent... let's get media items from it.
          else
          {
            $media_items = json_decode($cache['data']);
            foreach($media_items as $index=>$tmp) $media_items[$index]=get_object_vars($tmp); // convert object to assoc. array
          }

          // now we should really have parent device cache ... copy to our main (child) device.
          $media_items = $this->convert_station_ids($media_items);

          $cache_created = time();
          $showxml->addChild('last_updated',$cache_created);

          $this->db->insert('schedules_media_cache',array('device_id'=>$this->device['id'],'mode'=>(!empty($show['recurring_start']) ? 'recurring' : 'once'),'schedule_id'=>$show['id'],'start'=>$show['start'],'duration'=>$show['duration'],'data'=>json_encode($media_items),'created'=>$cache_created));
        }

        // do we still not have media items for some reason? no cache, no parent cache, or something went wrong...
        if($media_items===false)
        {
          $media_items = $this->generate_playlist_items($playlist['id'],$this->schedule_device_id);

          $cache_created = time();
          $showxml->addChild('last_updated',$cache_created);

          $this->db->insert('schedules_media_cache',array('device_id'=>$this->device['id'],'mode'=>(!empty($show['recurring_start']) ? 'recurring' : 'once'),'schedule_id'=>$show['id'],'start'=>$show['start'],'duration'=>$show['duration'],'data'=>json_encode($media_items),'created'=>$cache_created));
        }
      }

      $order_count = 0;
      $media_offset = 0.0;
      $media_audio_offset = 0.0;
      $media_image_offset = 0.0;

      foreach($media_items as $media_item) 
      {

        if($show['type']=='standard' && $media_offset > $show['duration']) break;

        if($show['type']=='advanced')
        {

          if(max($media_audio_offset,$media_image_offset) > $show['duration']) break;

          if($media_item['type']=='audio') 
          {
            $media_offset = $media_audio_offset;
            $media_audio_offset += $media_item['duration'];
          }

          elseif($media_item['type']=='image') 
          {
            $media_offset = $media_image_offset;
            $media_image_offset += $media_item['duration'];
          }

          else
          {
            $media_offset = max($media_audio_offset, $media_image_offset);
            $media_audio_offset = $media_offset + $media_item['duration'];
            $media_image_offset = $media_offset + $media_item['duration'];
          }

        }

        $itemxml=$mediaxml->addChild('item');
        $this->media_item_xml($itemxml,$media_item,$order_count,$media_offset);

        if($show['type']=='standard' || $show['type']=='live_assist') $media_offset += $media_item['duration'];

        $order_count++;
      }

      // live assist shows always use specified show duration (total media duration means nothing because of breakpoints)
      if($show['type']=='live_assist') $show_actual_duration = $show['duration'];
      else $show_actual_duration = $media_offset;

      // if the show-specified duration is less than the actual duration (total of media durations), then use the the show-specified so the next show isn't cut of at the beginning.
      // otherwise, use the actual duration (shorter) so that we can fill in the rest with 'default playlist' material.
      $showxml->addChild('duration',$show['duration'] < $show_actual_duration ? $show['duration'] : $show_actual_duration);

      $show_times[]=array('start'=>$show['start'],'end'=>$show['start'] + min($show['duration'],$show_actual_duration));


      if($show['item_type']=='playlist' && $show['type']=='live_assist')
        $this->add_liveassist_buttons($playlist['id'], $show, $showxml);

    }

    usort($show_times,"openbroadcaster_show_times_sort");

    // fill in blank spots with default playlist, if we have one.
    if(!empty($this->default_playlist_id))
    {

      // default starting time is now. but we'll check to see whether there is an earlier starting time from a cached default playlist which is still playing.
      $timestamp_pointer = time();
      $this->db->query('SELECT start FROM schedules_media_cache WHERE mode="default_playlist" AND start < '.$this->db->escape($timestamp_pointer).' AND start+duration > '.$this->db->escape($timestamp_pointer));
      if($this->db->num_rows()>0)
      {
        $cached_default_playlist = $this->db->assoc_row();
        $timestamp_pointer = $cached_default_playlist['start'];
      }  
      
      $default_playlist_finished = false;

      for($default_playlist_counter=0;$timestamp_pointer < $end_timestamp;$default_playlist_counter++)
      {

        if($default_playlist_counter==0 && (count($show_times)==0 || $show_times[0]['start']>$timestamp_pointer)) 
        {
          $default_start = $timestamp_pointer;

          if(count($show_times)==0) $default_end = $end_timestamp;
          else $default_end = $show_times[0]['start'];

          $default_start_tmp = $default_start;

          while($default_start_tmp < $default_end)
          {
            if($default_start_tmp > $end_timestamp) break(2); // end of buffer, we're done.

            // get show content.
            $showxml = $schedxml->addChild('show');
            
            // add default playlist as a show. (this function returns duration, so we add it to our time).
            $show_duration = $this->default_playlist_show_xml($showxml,$default_start_tmp,($default_end-$default_start_tmp));
            $default_start_tmp = $default_start_tmp + $show_duration;
          }

        }

        if(!empty($show_times[$default_playlist_counter]))
        {
          $default_start = ceil($show_times[$default_playlist_counter]['end']); // need ceiling since we store start times in whole numbers.

          if(count($show_times)>($default_playlist_counter+1)) $default_end = $show_times[$default_playlist_counter+1]['start'];
          else $default_end = $end_timestamp;

          // this will be false if there is no gap between shows, or at the end where a show goes over our end timestamp.
          if($default_start<$default_end)
          {

            $default_start_tmp = $default_start;

            while($default_start_tmp < $default_end)
            {
              if($default_start_tmp > $end_timestamp) break(2); // end of buffer, we're done.

              // get show content.
              $showxml = $schedxml->addChild('show');
              
              // add default playlist as a show. (this function returns duration, so we add it to our time).
              $show_duration = $this->default_playlist_show_xml($showxml,$default_start_tmp,($default_end-$default_start_tmp));
              $default_start_tmp = $default_start_tmp + $show_duration;
            }

          }

        }

        $timestamp_pointer = $default_end;

      }

    }

    header ("content-type: text/xml");
    echo @$this->xml->asXML();

  }

  private function add_liveassist_buttons($playlist_id, $show, $show_xml)
  {

    $buttons_xml = $show_xml->addChild('liveassist_buttons');

    $this->db->where('playlist_id',$playlist_id);
    $this->db->orderby('order_id');
    $buttons = $this->db->get('playlists_liveassist_buttons');

    foreach($buttons as $button)
    {

      $this->db->where('id',$button['button_playlist_id']);
      $playlist = $this->db->get_one('playlists');

      if(!$playlist) continue; // playlist not available.

      $this->db->where('device_id', $this->device['id']);
      $this->db->where('start', $show['start']);
      $this->db->where('playlists_liveassist_button_id', $button['id']);

      $cache = $this->db->get_one('schedules_liveassist_buttons_cache');

      if($cache)
      {
        $items = (array) json_decode($cache['data']);
        $cache_created = $cache['created'];
      }

      else
      {
        $items = $this->generate_playlist_items($button['button_playlist_id'],$this->device['id']);
        $cache_created = time();
        // $showxml->addChild('last_updated',$cache_created);
        $this->db->insert('schedules_liveassist_buttons_cache',array('device_id'=>$this->device['id'],'start'=>$show['start'],'playlists_liveassist_button_id'=>$button['id'],'data'=>json_encode($items),'created'=>$cache_created));
      }

      $group_xml = $buttons_xml->addChild('group');
      $group_xml->addChild('last_updated', $cache_created);
      $group_xml->addChild('name',$playlist['name']);
      $media_xml = $group_xml->addChild('media');

      foreach($items as $item)
      {
        $item = (array) $item;
        if($item['type']=='breakpoint') continue;
        $item_xml = $media_xml->addChild('item');
        $this->media_item_xml($item_xml, $item);
      }

    }

  }

  private function convert_station_ids($media_items)
  {
    // no need to swap out station IDs, we're already using parent's ids.
    if($this->device['use_parent_ids']) return $media_items;

    // swap out station IDs from parent, with station IDs for child.
    $new_items = array();
    foreach($media_items as $index=>$item)
    {

      // this item is a station ID. get a station id from our child device instead.
      if(!empty($item['is_station_id']))
      {

        $this->db->query('SELECT media.* FROM devices_station_ids LEFT JOIN media ON devices_station_ids.media_id = media.id WHERE device_id="'.$this->db->escape($this->device['id']).'" order by rand() limit 1;');
        $rows = $this->db->assoc_list(); 
        if(count($rows)>0) 
        {
          // if this station id is an image, how long should we display it for? check device settings.
          if($rows[0]['type']=='image') $rows[0]['duration'] = $this->device['station_id_image_duration'];

          $rows[0]['is_station_id'] = true;

          // add to our media items.
          $new_items[]=$rows[0];
        }

      }

      else $new_items[] = $item;

    }    

    return $new_items;
  }

  private function generate_playlist_items($playlist_id,$device_id,$generate_for_parent = false)
  {

    // get playlist items
    $this->db->where('playlist_id',$playlist_id);
    $this->db->orderby('ord');
    $playlist_items = $this->db->get('playlists_items');

    $media_items = array();

    foreach($playlist_items as $playlist_item)
    {

      // keep track of media items in this loop only (needed so we can set image duration at end of iteration)
      $media_items_tmp = array();

      // single media item
      if($playlist_item['item_type']=='media')
      {
        $this->db->where('id',$playlist_item['item_id']);
        if($media=$this->db->get_one('media')) $media_items_tmp[]=$media;
      }

      // dynamic item
      elseif($playlist_item['item_type']=='dynamic')
      {

        $dynamic_items = array();

        // we keep searching until we have enough items.  this allows randomization, but will not have two of the same tracks playing nearby each other.
        if($playlist_item['dynamic_num_items']) 
        {
          while(count($dynamic_items)<$playlist_item['dynamic_num_items'])
          {
            $media_search = $this->MediaModel('search', array('limit'=> ($playlist_item['dynamic_num_items'] - count($dynamic_items)) , 'query' => (array) json_decode($playlist_item['dynamic_query'])), $device_id, true );

            // if we get no results, then just break out of the loop.  there is nothing that matches the search query.
            if(!$media_search || empty($media_search[0])) break;

            $dynamic_items = empty($dynamic_items) ? $media_search[0] : array_merge($dynamic_items,$media_search[0]);        
          }
        }

        else
        {
          // "all items" presently not using random order. (TODO: we should add this in to dynamic selection properties in playlist addedit.).
          $media_search = $this->MediaModel('search', array('query' => (array) json_decode($playlist_item['dynamic_query'])), $device_id, false );
          if($media_search && !empty($media_search[0])) $dynamic_items = $media_search[0];        
        }

        $media_items_tmp = array_merge($media_items_tmp,$dynamic_items);

      }

      // random station id
      elseif($playlist_item['item_type']=='station_id')
      {

        if($this->device['parent_device_id'] && ($generate_for_parent || $this->device['use_parent_ids']))
        {
          $station_id_device = $this->device['parent_device_id'];
          $station_id_image_duration = $this->parent_device['station_id_image_duration'];
        }

        else
        {
          $station_id_device = $this->device['id'];
          $station_id_image_duration = $this->device['station_id_image_duration'];
        }

        $this->db->query('SELECT media.* FROM devices_station_ids LEFT JOIN media ON devices_station_ids.media_id = media.id WHERE device_id="'.$this->db->escape($station_id_device).'" order by rand() limit 1;');
        $rows = $this->db->assoc_list(); 
        if(count($rows)>0) 
        {
          // if this station id is an image, how long should we display it for? check device settings.
          if($rows[0]['type']=='image') $rows[0]['duration'] = $station_id_image_duration;

          $rows[0]['is_station_id'] = true;

          // add to our media items.
          $media_items_tmp[]=$rows[0];
        }
      }

      elseif($playlist_item['item_type']=='breakpoint')
      {
        $media_items_tmp[] = array('type'=>'breakpoint','duration'=>0);
      }

      // set our media duration for images from the specified playlist item duration.
      foreach($media_items_tmp as $index=>$item)
      {
        if($item['type']=='image' && empty($item['duration'])) $media_items_tmp[$index]['duration'] = ($playlist_item['item_type']=='dynamic' ? $playlist_item['dynamic_image_duration'] : $playlist_item['duration']);
      }

      // add our media items from this run to our complete set of media items.
      $media_items = array_merge($media_items,$media_items_tmp);

    }

    return $media_items;

  }

  private function media_item_xml(&$itemxml,$media_item,$ord=false,$offset=false)
  {

    // special handling for 'breakpoint' (not really a media item, more of an instruction).
    if($media_item['type']=='breakpoint')
    {
      if($ord!==false) $itemxml->addChild('order',$ord);
      if($offset!==false) $itemxml->addChild('offset',$offset); // offset is replacing 'order' to allow multiple media to play at once.
      $itemxml->addChild('duration',0);
      $itemxml->addChild('type',$media_item['type']);
      return;
    }

    // did we miss setting a duration?
    // if(empty($media_item['duration'])) $media_item['duration']=5;

    if(!empty($media_item['is_archived'])) $filerootdir=OB_MEDIA_ARCHIVE;
    elseif(!empty($media_item['is_approved'])) $filerootdir=OB_MEDIA;
    else $filerootdir=OB_MEDIA_UPLOADS;

    $itemxml->addChild('duration',$media_item['duration']);
    $itemxml->addChild('type',$media_item['type']);
    if($ord!==false) $itemxml->addChild('order',$ord);
    if($offset!==false) $itemxml->addChild('offset',$offset); // offset is replacing 'order' to allow multiple media to play at once.

    if($media_item['type']!='linein')
    {

      $fullfilepath=$filerootdir.'/'.$media_item['file_location'][0].'/'.$media_item['file_location'][1].'/'.$media_item['filename'];
      $filesize=filesize($fullfilepath);

      $itemxml->addChild('id',$media_item['id']);
      $itemxml->addChild('filename',htmlspecialchars($media_item['filename']));
      $itemxml->addChild('title',htmlspecialchars($media_item['title']));
      $itemxml->addChild('artist',htmlspecialchars($media_item['artist']));
      $itemxml->addChild('hash',$media_item['file_hash']);
      $itemxml->addChild('filesize',$filesize);
      $itemxml->addChild('location',$media_item['file_location']);  
      $itemxml->addChild('archived',$media_item['is_archived']);
      $itemxml->addChild('approved',$media_item['is_approved']);

    }

  }

  // add default playlist to show xml. 
  // max duration considered when adding media items to xml, but not when generating for cache purposes. 
  // this is because max_duration might 'extend' in the future (as more buffer requested by remote).
  // returns duration.
  private function default_playlist_show_xml(&$showxml,$start,$max_duration)
  {

    if(empty($this->default_playlist_id)) return array();

    // get our playlist name to report as the show name (below)
    $this->db->where('id',$this->default_playlist_id);
    $playlist = $this->db->get_one('playlists');

    if($playlist['type']=='live_assist') $playlist['type']='standard'; // live_assist converted to standard for default playlist.

    $show_media_items = array();

    // see if we have selected media in our cache.
    $this->db->where('device_id',$this->device['id']);
    $this->db->where('mode','default_playlist');
    $this->db->where('start',$start);
    // $this->db->where('duration',$end-$start);

    $cache = $this->db->get_one('schedules_media_cache');

    if($cache)
    {
      $show_media_items = json_decode($cache['data']);
      foreach($show_media_items as $index=>$tmp) $show_media_items[$index]=get_object_vars($tmp); // convert object to assoc. array
      $showxml->addChild('last_updated',$cache['created']);
      $duration = $cache['duration'];
    }

    // are we using a parent device for cache (and playlist)?
    elseif($this->cache_device_id!=$this->device['id'] && $this->device['use_parent_playlist'])
    {
      // see if parent has a cache entry.
      $this->db->where('device_id',$this->cache_device_id);
      $this->db->where('mode','default_playlist');
      $this->db->where('start',$start);
      // $this->db->where('duration',$end-$start);

      $cache = $this->db->get_one('schedules_media_cache');

      // we are supposed to use a parent device for cache, but that device doesn't have the cached item yet.
      if(!$cache)
      {
        $show_media_items = $this->generate_playlist_items($this->default_playlist_id,$this->default_playlist_device_id,true);
        $cache_created = time();
        $duration = $this->total_items_duration($show_media_items,$playlist['type']=='advanced');
        $this->db->insert('schedules_media_cache',array('mode'=>'default_playlist','schedule_id'=>null,'device_id'=>$this->cache_device_id,'start'=>$start,'duration'=>$duration,'data'=>json_encode($show_media_items),'created'=>$cache_created));
      }

      // oh, we do have cache from parent... let's get media items from it.
      else
      {
        $show_media_items = json_decode($cache['data']);
        foreach($show_media_items as $index=>$tmp) $show_media_items[$index]=get_object_vars($tmp); // convert object to assoc. array
      }

      // now we should really have parent device cache ... copy to our main (child) device.
      $show_media_items = $this->convert_station_ids($show_media_items);

      $duration = $this->total_items_duration($show_media_items,$playlist['type']=='advanced');

      $cache_created = time();
      $showxml->addChild('last_updated',$cache_created);

      $this->db->insert('schedules_media_cache',array('mode'=>'default_playlist','schedule_id'=>null,'device_id'=>$this->device['id'],'start'=>$start,'duration'=>$duration,'data'=>json_encode($show_media_items),'created'=>$cache_created));
    }

    // still don't have media items?
    if(empty($show_media_items)) 
    {
      $show_media_items = $this->generate_playlist_items($this->default_playlist_id,$this->default_playlist_device_id,false);

      $duration = $this->total_items_duration($show_media_items,$playlist['type']=='advanced');

      $cache_created = time();
      $showxml->addChild('last_updated',$cache_created);

      $this->db->insert('schedules_media_cache',array('mode'=>'default_playlist','schedule_id'=>null,'device_id'=>$this->device['id'],'start'=>$start,'duration'=>$duration,'data'=>json_encode($show_media_items),'created'=>$cache_created));
    }

    // generate XML for show/media items.
    $showxml->addChild('id',0);
    $showxml->addChild('date',gmdate('Y-m-d',$start));
    $showxml->addChild('time',gmdate('H:i:s',$start));
    $showxml->addChild('name',$playlist['name']);
    $showxml->addChild('type',$playlist['type']);
    $showxml->addChild('description','Default Playlist');
    // $showxml->addChild('last_updated',time());
    $showxml->addChild('duration',min($max_duration,$duration));

    $mediaxml = $showxml->addChild('media'); 

    $order_count = 0;

    $media_offset = 0.0;
    $media_audio_offset = 0.0;
    $media_image_offset = 0.0;

    foreach($show_media_items as $media_item) 
    {

      if($media_item['type']=='breakpoint') continue; // completely ignore breakpoints. (live assist converted to standard playlist).

      if($playlist['type']=='advanced')
      {

        if($media_item['type']=='audio') 
        {
          // if our audio offset is already past the max duration, we don't want to add more audio.
          if($media_audio_offset >= $max_duration) continue;

          $media_offset = $media_audio_offset;
          $media_audio_offset += $media_item['duration'];
        }

        elseif($media_item['type']=='image') 
        {
          // if our image offset is already past the max duration, we don't want to add more images.
          if($media_image_offset >= $max_duration) continue;

          $media_offset = $media_image_offset;
          $media_image_offset += $media_item['duration'];
        }

        else
        {
          // if audio or image offset is already past the max duration, we don't want to add anymore anything!
          // (adding video would start past the max_duration point).
          if(max($media_audio_offset,$media_image_offset) >= $max_duration) break;

          $media_offset = max($media_audio_offset, $media_image_offset);
          $media_audio_offset = $media_offset + $media_item['duration'];
          $media_image_offset = $media_offset + $media_item['duration'];
        }

        // line everything up if this was the last item in the default playlist. want to keep things lined up when looping through next time.
        // note: don't have to consider this anymore, since we are creating a new show for every loop of the default playlist. (which should already line things up).
        /*
        if(!empty($media_item['last_in_default']))
        {
          $media_audio_offset = max($media_audio_offset,$media_image_offset);
          $media_image_offset = $media_audio_offset;
        }
        */

      }

      $itemxml=$mediaxml->addChild('item');
      $this->media_item_xml($itemxml,$media_item,$order_count,$media_offset);

      if($playlist['type']=='standard') 
      {
        $media_offset+=$media_item['duration'];
        if($media_offset > $max_duration) break; // our next media offset is beyond max_duration, no more items to add. 
      }

      $order_count++;

    }

    return min($max_duration,$duration);

  }

  private function total_items_duration($media_items,$advanced = false)
  {

    $media_offset = 0.0;
    $media_audio_offset = 0.0;
    $media_image_offset = 0.0;

    if($advanced)
    {
      foreach($media_items as $media_item) 
      {

        if($media_item['type']=='audio') 
        {
          $media_audio_offset += $media_item['duration'];
        }

        elseif($media_item['type']=='image') 
        {
          $media_image_offset += $media_item['duration'];
        }

        else
        {
          $media_offset = max($media_audio_offset, $media_image_offset);
          $media_audio_offset = $media_offset + $media_item['duration'];
          $media_image_offset = $media_offset + $media_item['duration'];
        }

      }

      return ceil(max($media_audio_offset,$media_image_offset));
    }

    else
    {
      foreach($media_items as $media_item) $media_offset += $media_item['duration'];
      return ceil($media_offset);
    }

  }

  private function emergency()
  {
  
    if($this->device['parent_device_id'] && $this->device['use_parent_emergency'])
      $broadcasts = $this->get_upcoming_emergency_broadcasts($this->device['parent_device_id'],time()+$this->buffer);
 
    else
      $broadcasts = $this->get_upcoming_emergency_broadcasts($this->device['id'],time()+$this->buffer);

    $schedxml=$this->xml->addChild('emergency_broadcasts');

    if(!empty($broadcasts)) foreach($broadcasts as $broadcast) {

      $this->db->where('id',$broadcast['item_id']);
      $mediaInfo=$this->db->get_one('media');

      if(empty($broadcast['duration'])) $broadcast_duration=$mediaInfo['duration'];
      else $broadcast_duration=$broadcast['duration'];

      if(!empty($mediaInfo['is_archived'])) $filerootdir=OB_MEDIA_ARCHIVE;
      elseif(!empty($mediaInfo['is_approved'])) $filerootdir=OB_MEDIA;
      else $filerootdir=OB_MEDIA_UPLOADS;
      $fullfilepath=$filerootdir.'/'.$mediaInfo['file_location'][0].'/'.$mediaInfo['file_location'][1].'/'.$mediaInfo['filename'];
      $filesize=filesize($fullfilepath);

      // set start if we don't have one... (starts immediately).  remote wants something here.
      if(empty($broadcast['start'])) $broadcast['start']='0';

      // set end if we don't have one... (plays indefiniteyl).  remote wants something here.
      if(empty($broadcast['stop'])) $broadcast['stop']='2147483647';

      $broadcastxml = $schedxml->addChild('broadcast');
      $broadcastxml->addChild('id',$broadcast['id']);
      $broadcastxml->addChild('start_timestamp',$broadcast['start']);
      $broadcastxml->addChild('end_timestamp',$broadcast['stop']);
      $broadcastxml->addChild('frequency',$broadcast['frequency']);
      $broadcastxml->addChild('artist',htmlspecialchars($mediaInfo['artist']));
      $broadcastxml->addChild('filename',htmlspecialchars($mediaInfo['filename']));
      $broadcastxml->addChild('title',htmlspecialchars($mediaInfo['title']));
      $broadcastxml->addChild('media_id',htmlspecialchars($mediaInfo['id']));
      $broadcastxml->addChild('duration',htmlspecialchars($broadcast_duration));
      $broadcastxml->addChild('media_type',htmlspecialchars($mediaInfo['type']));
      $broadcastxml->addChild('hash',$mediaInfo['file_hash']);
      $broadcastxml->addChild('filesize',$filesize);
      $broadcastxml->addChild('location',$mediaInfo['file_location']);
      $broadcastxml->addChild('archived',$mediaInfo['flag_delete']);
      $broadcastxml->addChild('approved',$mediaInfo['is_approved']);

    }

    header ("content-type: text/xml");
    echo $this->xml->asXML();
    // die();

  }

  private function playlog_status()
  {

    $this->db->where('device_id',$this->device['id']);
    $this->db->orderby('timestamp','desc');
    $last_entry = $this->db->get_one('playlog');

    if(empty($last_entry)) $last_timestamp=0;
    else $last_timestamp=$last_entry['timestamp'];

    $replyxml=$this->xml->addChild('playlog_status');

    $replyxml->addChild('last_timestamp',$last_timestamp);

    header('content-type: text/xml');
    echo $this->xml->asXML();    

  }

  private function playlog_post()
  {

    if(empty($_POST['data'])) {
      $this->send_xml_error('missing XML post data');
      die();
    }

    $data=new SimpleXMLElement($_POST['data']);
    $playlog=$data->playlog;

    foreach($playlog->entry as $entry) {

      $entryArray=(array) $entry;

      // if a value is an object, that is because it has no value
      // in this case, it is left as a SimpleXMLElement object (empty) instead of being converted to a string.
      // so here we convert to a empty string
      foreach($entryArray as $index=>$value) {
        if(is_object($value)) $entryArray[$index]='';
      }

      // db inconsistency between web app and remote.
      $entryArray['timestamp']=$entryArray['datetime'];
      unset($entryArray['datetime']);

      $entryArray['device_id']=$this->device['id'];

      $this->addedit_playlog($entryArray);

    }

    $replyxml=$this->xml->addChild('playlog_post');

    $replyxml->addChild('status','success');

    header('content-type: text/xml');
    echo $this->xml->asXML();  

  }

  private function media()
  {

    if(empty($_POST['media_id'])) die();

    $this->db->where('id',$_POST['media_id']);
    $media = $this->db->get_one('media');
    if(empty($media)) die();

    if($media['is_archived']==1) $filedir=OB_MEDIA_ARCHIVE;
    elseif($media['is_approved']==0) $filedir=OB_MEDIA_UPLOADS;
    else $filedir=OB_MEDIA;

    $filedir.='/'.$media['file_location'][0].'/'.$media['file_location'][1];

    $fullpath=$filedir.'/'.$media['filename'];

    if(!file_exists($fullpath)) die();

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: binary"); 
    header("Content-Length: ".filesize($fullpath));

    readfile($fullpath);  

  }

  private function send_xml_error($message)
  {
    $xml=new SimpleXMLElement('<?xml version=\'1.0\' standalone=\'yes\'?><obconnect></obconnect>');
    $xml->addChild('error',$message);

    header ("content-type: text/xml");
    echo $xml->asXML();

    die();
  }

  private function get_upcoming_emergency_broadcasts($device,$timelimit) {

    $now=time();

    $addsql=' where device_id='.$device.' and (start<='.$timelimit.' or start IS NULL) and (stop>'.$now.' or stop IS NULL ) ';
    $sql='select *,TIME_TO_SEC(duration) as duration from emergencies'.$addsql.' order by start';
  
    $this->db->query($sql);
    $r=$this->db->assoc_list();

    return $r;

  }

  private function addedit_playlog($datatmp) {

    $useVals=array('device_id','media_id','artist','title','timestamp','context','emerg_id','notes');
    foreach($useVals as $val) $data[$val]=$datatmp[$val];

    // convert timestamp to mysql format
    // $data['datetime']=date('Y-m-d H:i:s',$data['datetime']);

    if($this->verify_playlog($data)) $this->db->insert('playlog',$data);

  }

  private function verify_playlog($data) {

    foreach($data as $key=>$value) { 
      $$key=$value;
      $dbcheck[]='`'.$key.'`="'.$this->db->escape($value).'"';
    }

    if(empty($device_id) || !isset($media_id) || empty($timestamp))
      $error="Required field is missing.";

    elseif($context!='show' && $context!='emerg' && $context!='fallback') 
      $error="Context is invalid.";

    elseif($context=='emerg' && !preg_match('/^[0-9]+$/',$emerg_id)) 
      $error="Emergency broadcast ID is invalid or missing.";

    elseif(!preg_match('/^[0-9]+$/',$device_id))
      $error="Device ID is invalid.";

    elseif(!preg_match('/^[0-9]+$/',$media_id))
      $error="Media ID is invalid.";

    else {

      $sql='select id from playlog where '.implode($dbcheck,' and ');

      $this->db->query($sql);
    
      // echo $this->db->error();        

      if($this->db->num_rows()>0) $error='This log entry already exists.';

    }

    if(empty($error)) return TRUE;

    return false;

  }

  private function update_now_playing()
  {

    $current_playlist_id = trim($_POST['playlist_id']);
    $current_playlist_end = trim($_POST['playlist_end']);
    $current_media_id = trim($_POST['media_id']);
    $current_media_end = trim($_POST['media_end']);
    $current_show_name = trim($_POST['show_name']);

    if(!preg_match('/^[0-9]+$/',$current_playlist_id) || empty($current_playlist_id)) $current_playlist_id = null;
    if(!preg_match('/^[0-9]+$/',$current_playlist_end) || empty($current_playlist_end)) $current_playlist_end = null;
    if(!preg_match('/^[0-9]+$/',$current_media_id) || empty($current_media_id)) $current_media_id = null;
    if(!preg_match('/^[0-9]+$/',$current_media_end) || empty($current_media_end)) $current_media_end = null;

    if($current_show_name=='') $current_show_name = null;

    $db['current_playlist_id'] = $current_playlist_id;
    $db['current_playlist_end'] = $current_playlist_end;
    $db['current_media_id'] = $current_media_id;
    $db['current_media_end'] = $current_media_end;
    $db['current_show_name'] = $current_show_name;

    $this->db->where('id',$_POST['id']);
    $this->db->update('devices',$db);

  }

}

$remote = new Remote();
