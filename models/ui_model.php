<?php
/*     
    Copyright 2014 OpenBroadcaster, Inc.

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

class UIModel extends OBFModel
{

  public function __construct()
  {
    parent::__construct();
    $modules_model = $this->load->model('modules');
    $this->modules = $modules_model('get_installed');
    $this->theme = !empty($this->user->userdata['theme']) && $this->user->userdata['theme']!='default' ? $this->user->userdata['theme'] : false;
  }

  // get core, theme, and enabled-module image files.
  public function image_files()
  {
    $image_files = $this->find_files('images');
    if($this->theme) $image_files = array_merge($image_files,$this->find_files('themes/'.$this->theme.'/images')); // add our custom theme images.
    foreach($this->modules as $module) $image_files = array_merge($image_files,$this->find_files('modules/'.$module['dir'].'/images'));
    return $image_files;
  }

  // get core, theme, and enabled-module CSS files.
  public function css_files()
  {
    $css_files = $this->find_files('css','css', $this->theme ? 'themes/'.$this->theme.'/css_core' : false);
    if($this->theme) $css_files = array_merge($css_files,$this->find_files('themes/'.$this->theme.'/css_theme','css'));
    foreach($this->modules as $module) $css_files = array_merge($css_files,$this->find_files('modules/'.$module['dir'].'/css','css'));
    return $css_files;
  }

  // get core and enabled-module JS files.
  public function js_files()
  {
    $js_files = $this->find_files('js','js');
    foreach($this->modules as $module) $js_files = array_merge($js_files,$this->find_files('modules/'.$module['dir'].'/js','js'));

    return $js_files;
  }

  // see http://stackoverflow.com/questions/2236668/file-get-contents-breaks-up-utf-8-characters
  private function file_get_contents_utf8($fn) {
     $content = file_get_contents($fn);
      return mb_convert_encoding($content, 'UTF-8',
          mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
  }

  // see http://stackoverflow.com/questions/10290849/how-to-remove-multiple-utf-8-bom-sequences-before-doctype
  // don't want this in our array key.
  private function remove_utf8_bom($text)
  {
    $bom = pack('H*','EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
  }

  public function get_themes()
  {
    $dirs = scandir('themes');

    $themes = array();

    $themes['default'] = 'Light On Dark (Lower Contrast)';

    foreach($dirs as $dir)
    {
      if($dir[0]=='.' && !is_dir($dir)) continue;
      if(!file_exists('themes/'.$dir.'/theme.php')) continue;

      include('themes/'.$dir.'/theme.php');
    }

    return $themes;
  }

  public function get_languages()
  {
    $dirs = scandir('strings');

    $languages = array();

    foreach($dirs as $dir)
    {
      if($dir[0]=='.' && !is_dir($dir)) continue;
      if(!file_exists('strings/'.$dir.'/language.php')) continue;

      include('strings/'.$dir.'/language.php');
    }

    return $languages;

  }

  // get language for user
  public function get_user_language()
  {
    $all_languages = $this->get_languages();

    if($this->user->userdata && !empty($this->user->userdata['language']) && !empty($all_languages[$this->user->userdata['language']]))
      return $all_languages[$this->user->userdata['language']];

    else return $all_languages['default'];      
  }

  // get strings for the user's language
  public function strings()
  {
    $default = $this->strings_for_language('default');

    if($this->user->userdata && !empty($this->user->userdata['language']) && $this->user->userdata['language']!='default')
    {
      $language = $this->strings_for_language($this->user->userdata['language']);

      // array_merge() doesn't work how we want for multi-dimensional arrays. so let's do this manually.  
      // start with language and then fill in anything we don't have from default.
      $return = $language;

      foreach($default as $namespace=>$strings)
      {
        // don't even have the namespace? create it.
        if(!isset($return[$namespace])) $return[$namespace] = array();

        // make sure we have all the values.
        foreach($strings as $index=>$value)
        {
          if(!isset($return[$namespace][$index])) $return[$namespace][$index] = $value;
        }
      }    

      return $return;
    }

    return $default;
  }

  public function strings_for_language($language)
  {

    if(!preg_match('/^[0-9a-z_-]+$/i',$language)) return array();

    $languages = array_keys($this->get_languages());
    if(array_search($language,$languages)===false) return array();

    $string_files = $this->find_files('strings/'.$language);

    $strings = array();

    foreach($string_files as $file)
    {

      if(!preg_match('/\.txt$/',$file)) continue;

      $contents = explode("\n",$this->file_get_contents_utf8($file));

      $namespace = false;

      foreach($contents as $line)
      {

        // ignore empty lines
        $line = trim($line);
        if($line=='') continue;

        $line_split = preg_split("/(?<!\\\):/", $line, 2);
        
        if(count($line_split)==1)
        {
          $namespace = $this->remove_utf8_bom(trim($line_split[0]));
          if(!isset($strings[$namespace])) $strings[$namespace] = array();
        }

        elseif(count($line_split)>=2 && $namespace!==false)
        {
          $strings[$namespace][$this->remove_utf8_bom(trim($line_split[0]))] = trim($line_split[1]);
        } 
      }
    }

    // die();

    return $strings;

  }

  // recursive function to find files with specific extention given a directory.
  // if override_path is set, same-filename-files in this directory will be used instead of given directory. (theme overrides).
  private function find_files($dir,$ext=false,$override_path=false,&$array=array())
  {
    if(!is_dir($dir)) return array();
    
    if($override_path)
    {
      $dir_explode = explode('/',$dir);
      $override_replace = $dir_explode[0];
    }

    $files = scandir($dir);

    foreach($files as $file)
    {
      $dirfile = $dir.'/'.$file;
    
      if(is_dir($dirfile) && $file[0]!='.') $this->find_files($dirfile,$ext,$override_path,$array);
      elseif(is_file($dirfile) && (!$ext || preg_match('/\.'.$ext.'$/',$dirfile))) 
      {
        // use theme override?
        if($override_path)
        {
          $override_file = $override_path.'/'.preg_replace('/^'.$override_replace.'\//','',$dirfile);
          if(is_file($override_file)) 
          {
            $array[]=$override_file;
            continue;
          }
        }

        // otherwise use core file.
        $array[]=$dirfile;
      }
    }

    return $array;
  }


}
