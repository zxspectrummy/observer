<?php

if(php_sapi_name()!='cli') die('Command line tool only.');

header('Content-Type: application/json');
require_once('../../components.php');
require_once('extras/getid3/getid3/getid3.php');
$getID3 = new getID3;
$db = OBFDB::get_instance();
$models = OBFModels::get_instance();
$user_agent = 'OpenBroadcaster/'.trim(file_get_contents('VERSION'));

if(php_sapi_name()!='cli') die('Command line tool only.');

if(!defined('OB_SYNC_USERID') || !defined('OB_SYNC_SOURCE') || !defined('OB_ACOUSTID_KEY')) die('OB_SYNC_USERID, OB_SYNC_SOURCE, and OB_ACOUSTID_KEY must be defined in config.php.'.PHP_EOL);

// create thumbnail directory if needed
if(!file_exists(OB_CACHE.'/thumbnails'))
{
  if(!mkdir(OB_CACHE.'/thumbnails', 0755)) die('Unable to create thumbnail directory. Make sure the OB cache directory is writable.'.PHP_EOL);
}

while(true)
{
  echo 'getting items requiring cover art'.PHP_EOL;

  $db->query('SELECT media.file_location, media.id, sync_releasegroup_id  FROM `media_metadata` left join media on media_metadata.media_id = media.id WHERE sync_coverart_raw is null and sync_releasegroup_id is not null and sync_releasegroup_id!="" order by media_id desc limit 25');
  $rows = $db->assoc_list();

  foreach($rows as $row)
  {
    usleep(100000);
      
    // get our cover art
    $cover_art_url = false;
    $ch = curl_init('https://coverartarchive.org/release-group/'.$row['sync_releasegroup_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $coverart_raw_response = curl_exec($ch);
    $http_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if($http_response_code!=200 && $http_response_code!=404) { echo 'coverart unexpected response code '. $http_response_code.', wait 5 seconds then skip.'.PHP_EOL; sleep(5); continue; }
    else echo 'coverart http response code '.$http_response_code.PHP_EOL;
    curl_close($ch);
    $response = json_decode($coverart_raw_response);
    if(!empty($response->images) && is_array($response->images)) foreach($response->images as $image)
    {
      if($image->approved && $image->front)
      {
        $cover_art_url = $image->image;
        break;
      }
    }
    
    // download cover art
    if($cover_art_url)
    {
      echo $row['id'].': saving cover art'.PHP_EOL;    
      $l1 = $row['file_location'][0];
      $l2 = $row['file_location'][1];
      
      if(!file_exists(OB_CACHE.'/thumbnails/'.$l1.'/'.$l2))
      {
        if(!mkdir(OB_CACHE.'/thumbnails/'.$l1.'/'.$l2,0777,true)) die('Unable to create thumbnail directory; check permissions.'.PHP_EOL);
      }
      $cover_art_data = file_get_contents($cover_art_url);
      if($cover_art_data)
      {
        file_put_contents(OB_CACHE.'/thumbnails/'.$l1.'/'.$l2.'/'.$row['id'].'.jpg', $cover_art_data);
      }
    }

    $db->where('media_id',$row['id']);
    $db->update('media_metadata',[
      'sync_coverart_raw'=>$coverart_raw_response
    ]);
  }

  echo PHP_EOL.'restarting script in 5 seconds'.PHP_EOL;
  sleep(5);
}