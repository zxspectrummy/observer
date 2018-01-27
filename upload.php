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

    // **** //

    This file includes code from Valums File Uploader. © 2010 Andrew Valums.
    Licensed under GPL v2 or later.
    See license information in extras/valums-file-uploader.
*/

require('components.php');

// COMPLETE AUTHENTICATION, usually handled by api.php
$user = OBFUser::get_instance();

$auth_id = null;
$auth_key = null;

// try to get an ID/key pair for user authorization.
if(!empty($_POST['i']) && !empty($_POST['k'])) 
{
  $auth_id = $_POST['i'];
  $auth_key = $_POST['k'];
}

// if not in post, try fetching from cookie.
elseif(!empty($_COOKIE['ob_auth_id']) && !empty($_COOKIE['ob_auth_key']))
{
  $auth_id = $_COOKIE['ob_auth_id'];
  $auth_key = $_COOKIE['ob_auth_key'];
} 

// authorize our user (from post data, cookie data, whatever.)
$user->auth($auth_id,$auth_key);

// define our class, create instance, handle upload.

class Upload extends OBFController
{

  // used by handle_upload() to get some important information about the uploaded media
  private function media_info($filename)
  {

    // this is the info we want -- if we can't get it, it will remain null.
    $return = array();
    $return['type']=null;
    $return['duration']=null;
    $return['format']=null;

    // get our mime data
    if(defined('OB_MAGIC_FILE'))
    {
      $finfo = new finfo(FILEINFO_MIME_TYPE, OB_MAGIC_FILE);
      $mime = strtolower($finfo->file($filename));
    }

    // did ob_magic_file cause problems?
    if(!defined('OB_MAGIC_FILE') || $mime=='' || $mime=='application/octet-stream') 
    {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = strtolower($finfo->file($filename));
    }

    $mime_array = explode('/',$mime);

    if($mime_array[0]=='image')
    {
      $return['type']='image';
      if($mime_array[1]=='jpeg') $return['format']='jpg';
      elseif($mime_array[1]=='png') $return['format']='png';
      elseif($mime_array[1]=='svg+xml') $return['format']='svg';
    }

    // if we have an audio or a video, then we use avprobe to find format and duration.
    else
    {

      $mediainfo_json = shell_exec('avprobe -show_format -show_streams -of json '.escapeshellarg($filename));
      if($mediainfo_json===null || !$mediainfo=json_decode($mediainfo_json)) return $return;
  
      // if missing information or duration is zero, not valid.
      if(empty($mediainfo->streams) || empty($mediainfo->format) || empty($mediainfo->format->duration)) return $return;
    
      $has_video_stream = false;
      $has_audio_stream = false;

      $possibly_audio = array_search($mediainfo->format->format_name, array('flac','mp3','ogg','wav'))!==false || $mediainfo->format->format_long_name=='QuickTime / MOV';

      foreach($mediainfo->streams as $stream)
      {
        // ignore probable cover art
        if($possibly_audio && ($stream->codec_name=='mjpeg' || $stream->codec_name=='png') && $stream->avg_frame_rate=='0/0') continue;    

        if($stream->codec_type=='video') $has_video_stream = true;
        elseif($stream->codec_type=='audio') $has_audio_stream = true;
      }

 

      // if no audio or video stream found, invalid (image already tested above).
      if(!$has_video_stream && !$has_audio_stream) return $return;

      // set duration
      $return['duration'] = $mediainfo->format->duration;

      // figure out audio or video
      if($has_video_stream) $return['type']='video';
      else $return['type']='audio';

      // figure out format
      if($return['type']=='audio')
      {
        if($mediainfo->format->format_long_name=='QuickTime / MOV') $return['format']='mp4';

        else switch($mediainfo->format->format_name)
        {
          case 'flac':
            $return['format']='flac';
            break;

          case 'mp3':
            $return['format']='mp3';
            break;

          case 'ogg':
            $return['format']='ogg';
            break;

          case 'wav':
            $return['format']='wav';
            break;
        }
      }

      elseif($return['type']=='video')
      {
        if($mediainfo->format->format_long_name=='QuickTime / MOV') $return['format']='mov';

        else switch($mediainfo->format->format_name)
        {
          case 'avi':
            $return['format']='avi';
            break;
    
          case 'mpeg':
            $return['format']='mpg';
            break;
    
          case 'ogg':
            $return['format']='ogg';
            break;
  
          case 'asf':
            $return['format']='wmv';
            break;
        }
      }
    }

    return $return;

  }


  public function handle_upload()
  {

    // max file size in bytes
    // $sizeLimit = 100 * 1024 * 1024;

    $key = $this->randKey();
    $id = $this->db->insert('uploads',array('key'=>$key, 'expiry'=>strtotime('+24 hours')));

    $input = fopen("php://input", "r");
    $target = fopen('assets/uploads/'.$id, "w");
    $realSize = stream_copy_to_stream($input, $target);
    fclose($input);
    fclose($target);
    
    if($realSize != (int) $_SERVER["CONTENT_LENGTH"])
    {            
      echo json_encode(array('error'=>'File upload was not successful.  Please try again.'));
      unlink('assets/uploads/'.$id);
      return;
    }

    // presently hard-coded 1gb filesize limit. this should be a setting.
    if( ($realSize/1024/1024) > 1024 )
    {
      echo json_encode(array('error'=>'File too large (max size 1GB).')); 
      unlink('assets/uploads/'.$id);
      return;
    }

    $result['file_id']=$id;
    $result['file_key']=$key;

    // get ID3 data.
    $id3=$this->getid3('assets/uploads/'.$id);

    // $result['info'] = array('comments'=>$id3['comments']);

    // get only the data we need (this should be expanded). sometimes other data causes encoding problems? maybe re: thumbnail image.
    $id3_data = array();
    if(isset($id3['comments']['artist'])) $id3_data['artist'] = $id3['comments']['artist'];
    if(isset($id3['comments']['album'])) $id3_data['album'] = $id3['comments']['album'];
    if(isset($id3['comments']['title'])) $id3_data['title'] = $id3['comments']['title'];
    if(isset($id3['comments']['comments'])) $id3_data['comments'] = $id3['comments']['comments'];
    if(count($id3_data)>0) $result['info'] = array('comments'=>$id3_data);
    else $result['info'] = array();

    // get some useful media information, insert it into the db with our file id/key.
    $media_info = $this->media_info('assets/uploads/'.$id);
    $this->db->where('id',$id);
    $this->db->update('uploads',array('format'=>$media_info['format'], 'type'=>$media_info['type'], 'duration'=>$media_info['duration']));

    $result['media_info'] = $media_info;

    $media_model = $this->load->model('Media');
    $result['media_supported'] = $media_model('format_allowed',$media_info['type'],$media_info['format']);

    // to pass data through iframe you will need to encode all html tags
    echo json_encode($result);

  }

  private function randKey()
  {

    $chars = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789';
    $key = '';
    for($i=0;$i<16;$i++)  $key.=$chars[rand(0,(strlen($chars)-1))];
    return $key;

  }

  private function getid3($filename)
  {

    require('extras/getid3/getid3/getid3.php');
    $getID3 = new getID3;

    $info = $getID3->analyze($filename);
    getid3_lib::CopyTagsToComments($info);

    return Upload::makeSafe($info);

  }

  private static function makeSafe($array)
  {

    foreach (array_keys($array) as $key)
    {

      if(gettype($array[$key]) == "array")
      {
        $array[$key] = Upload::makeSafe($array[$key]);
      }

      else if(gettype($array[$key]) == "string")
      {
        $array[$key] = iconv('UTF-8', 'UTF-8//IGNORE', $array[$key]);
      }

    }
    return($array);

  }

}

$upload = new Upload();
$upload->handle_upload();
