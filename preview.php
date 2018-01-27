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

// set default transcoding commands
if(!defined('OB_TRANSCODE_AUDIO_MP3')) define('OB_TRANSCODE_AUDIO_MP3', 'avconv -i {infile} -q 9 -ac 1 -ar 22050 {outfile}');
if(!defined('OB_TRANSCODE_AUDIO_OGG')) define('OB_TRANSCODE_AUDIO_OGG', 'avconv -i {infile} -acodec libvorbis -q 0 -ac 1 -ar 22050 {outfile}');
if(!defined('OB_TRANSCODE_VIDEO_MP4')) define('OB_TRANSCODE_VIDEO_MP4', 'avconv -i {infile} -crf 40 -vcodec libx264 -s {width}x{height} -ac 1 -ar 22050 {outfile}');
if(!defined('OB_TRANSCODE_VIDEO_OGV')) define('OB_TRANSCODE_VIDEO_OGV', 'avconv -i {infile} -q 0 -s {width}x{height} -acodec libvorbis -ac 1 -ar 22050 {outfile}');

// Sanity check on ID
if(!empty($_GET['id']) && preg_match('/^[0-9]+$/',$_GET['id'])) $media_id = $_GET['id'];
else die();

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

class MediaPreview extends OBFController
{

  public function output($id,$download) 
  {

    global $user;

    $media_model = $this->load->model('Media');
    $media_model('get_init');
    $this->db->where('media.id',$id);
    $media = $this->db->get_one('media');

    if(!$media) die();

    $type = strtolower($media['type']);
    $format = strtolower($media['format']);

    $media_location = OB_MEDIA;

    if(!preg_match('/^[a-z0-9_-]+\.[a-z0-9]+$/i',$media['filename'])) die();
    if(!preg_match('/^[A-Z0-9]{2}$/',$media['file_location'])) die();

    if($media['is_archived']==1) $user->require_permission('manage_media');

    if($media['status']=='private' && $media['owner_id']!=$user->param('id')) $user->require_permission('manage_media');

    if($media['is_archived']==1) $media_location = OB_MEDIA_ARCHIVE;
    elseif($media['is_approved']==0) $media_location = OB_MEDIA_UPLOADS;
    else $media_location = OB_MEDIA;

    $media_location.='/'.$media['file_location'][0].'/'.$media['file_location'][1].'/';
    $media_file = $media_location.$media['filename'];

    // get our cached data directory ready if we don't already have it.
    if(!file_exists(OB_CACHE.'/media')) mkdir(OB_CACHE.'/media');
    if(!file_exists(OB_CACHE.'/media/'.$media['file_location'][0])) mkdir(OB_CACHE.'/media/'.$media['file_location'][0]);
    if(!file_exists(OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1])) mkdir(OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1]);
    $cache_dir = OB_CACHE.'/media/'.$media['file_location'][0].'/'.$media['file_location'][1];

    if($type=='audio') 
    {

      // download mode
      if($download)
      {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename='.$media['filename']);

        if($format=='flac') header('Content-Type: audio/x-flac');
        elseif($format=='mp3') header('Content-Type: audio/mpeg');
        elseif($format=='ogg') header('Content-Type: audio/ogg');
        else die();

        header('Content-Length: '.filesize($media_file));

        $fp = fopen($media_file,'rb');
        fpassthru($fp);
        exit();
      }

      if(!empty($_GET['format']) && $_GET['format']=='mp3') $audio_format = 'mp3';
      else $audio_format = 'ogg';

      $cache_file = $cache_dir.'/'.$media['id'].'_audio.'.$audio_format;

      if(!file_exists($cache_file))
      {
        $strtr_array = array('{infile}'=>$media_file, '{outfile}'=>$cache_file);

        if($audio_format == 'mp3') exec(strtr(OB_TRANSCODE_AUDIO_MP3,$strtr_array));
        else exec(strtr(OB_TRANSCODE_AUDIO_OGG,$strtr_array));
      }

      // temporary 
      header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
      header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

      if($audio_format == 'mp3') header('Content-Type: audio/mpeg');
      else header("Content-Type: audio/ogg");
      header('Content-Length: '.filesize($cache_file));
      $fp = fopen($cache_file,'rb');
      fpassthru($fp);

    }

    elseif($type=='video')
    {

      // download mode
      if($download)
      {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename='.$media['filename']);

        if($format=='avi') header('Content-Type: video/avi');
        elseif($format=='mpg') header('Content-Type: video/mpeg');
        elseif($format=='ogg') header('Content-Type: video/ogg');
        elseif($format=='wmv') header('Content-Type: video/x-ms-wmv');
        elseif($format=='mov') header('Content-Type: video/quicktime');
        else die();

        header('Content-Length: '.filesize($media_file));

        $fp = fopen($media_file,'rb');
        fpassthru($fp);
        exit();
      }

      if(!empty($_GET['w']) && !empty($_GET['h']) && preg_match('/^[0-9]+$/',$_GET['w']) && preg_match('/^[0-9]+$/',$_GET['h']))
      {
        $dest_width=$_GET['w']; 
        $dest_height=$_GET['h'];
      }
      
      else 
      {
        $dest_width = 320;
        $dest_height = 240;
      }

      // we could use half resolution, then zoom by 200%. (faster encoding, lower birate) ...
      // $dest_width = round($dest_width/2);
      // $dest_height = round($dest_height/2);

      if(!empty($_GET['format']) && $_GET['format']=='mp4') $video_format = 'mp4';
      else $video_format = 'ogv';

      $cache_file = $cache_dir.'/'.$media['id'].'_'.$dest_width.'x'.$dest_height.'.'.$video_format;

      if(!file_exists($cache_file))
      {
        $strtr_array = array('{infile}'=>$media_file, '{outfile}'=>$cache_file, '{width}'=>$dest_width, '{height}'=>$dest_height);

        if($video_format == 'mp4')
        {
          // resolution apparently needs to be divisible by 2.
          $dest_width = round($dest_width/2)*2;
          $dest_height = round($dest_height/2)*2;
          exec(strtr(OB_TRANSCODE_VIDEO_MP4, $strtr_array));
        }
        else exec(strtr(OB_TRANSCODE_VIDEO_OGV, $strtr_array));
      }

      if($video_format == 'mp4') header('Content-Type: video/mp4');
      else header("Content-Type: video/ogg");
      header('Content-Length: '.filesize($cache_file));
      $fp = fopen($cache_file,'rb');
      fpassthru($fp);

    }

    elseif($type=='image')
    {

      // download mode
      if($download)
      {
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename='.$media['filename']);

        if($format=='png') header('Content-Type: image/png');
        elseif($format=='jpg') header('Content-Type: image/jpeg');
        elseif($format=='gif') header('Content-Type: image/gif');
        elseif($format=='svg') header('Content-Type: image/svg+xml');
        else die();

        header('Content-Length: '.filesize($media_file));

        $fp = fopen($media_file,'rb');
        fpassthru($fp);
        exit();
      }

      if(!empty($_GET['w']) && !empty($_GET['h']) && preg_match('/^[0-9]+$/',$_GET['w']) && preg_match('/^[0-9]+$/',$_GET['h']))
      {
        $dest_width=$_GET['w']; 
        $dest_height=$_GET['h'];
      }
    
      else 
      {
        $dest_width = 320;
        $dest_height = 240;
      }

      $cache_file = $cache_dir.'/'.$media['id'].'_'.$dest_width.'x'.$dest_height.'.jpg';

      if(!file_exists($cache_file))
      {
 
        // TODO better error reporting back to user needed.
        // SVG preview requires imagick extension.
        if($format=='svg' && !extension_loaded('imagick')) die();

        if($format=='svg')
        {
          $im = new Imagick();
          $svg = file_get_contents($media_file);
          $im->readImageBlob($svg);

          $source_width = $im->getImageWidth();
          $source_height = $im->getImageHeight();
          $source_ratio = $source_width/$source_height;
          $dest_ratio = $dest_width/$dest_height;

          if($dest_ratio > $source_ratio) $dest_width = $dest_height * $source_ratio;
          else $dest_height = $dest_width / $source_ratio;

          $im->setImageFormat("jpeg");
          $im->adaptiveResizeImage($dest_width, $dest_height);

          $im->writeImage($cache_file);
          $im->clear();
          $im->destroy();
        }

        else
        {
          $image_data = getimagesize($media_file);

          list($source_width,$source_height) = $image_data;

          $source_ratio = $source_width/$source_height;
          $dest_ratio = $dest_width/$dest_height;

          if($dest_ratio > $source_ratio) $dest_width = $dest_height * $source_ratio;
          else $dest_height = $dest_width / $source_ratio;

          if($image_data[2]==IMAGETYPE_PNG) $image_source = imagecreatefrompng($media_file);
          elseif($image_data[2]==IMAGETYPE_JPEG) $image_source = imagecreatefromjpeg($media_file);
          elseif($image_data[2]==IMAGETYPE_GIF) $image_source = imagecreatefromgif($media_file);
          else die();

          $image_dest = imagecreatetruecolor($dest_width,$dest_height);

          imagecopyresampled($image_dest,$image_source,0,0,0,0,$dest_width,$dest_height,$source_width,$source_height);

          imagejpeg($image_dest,$cache_file);
          imagedestroy($image_dest);
          imagedestroy($image_source);
        }
      }

      header('Content-type: image/jpeg');
      header('Content-Length: '.filesize($cache_file));
      $fp = fopen($cache_file,'rb');
      fpassthru($fp);

    }

    exit();

  }

}

// have we requested to download? if so, check permission.  if permission does not match up, switch automatically to preview.
if(isset($_GET['dl']) && $_GET['dl']==1 && $user->check_permission('download_all_media')) $download_mode = true;
else $download_mode = false;

$preview = new MediaPreview();
$preview->output($_GET['id'],$download_mode);

