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
    if($this->theme && file_exists('themes/'.$this->theme.'/style.css')) $css_files[] = 'themes/'.$this->theme.'/style.css';
    else $css_files[] = 'themes/default/style.css';
    
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

    $themes = [];

    foreach($dirs as $dir)
    {
      if($dir[0]=='.' && !is_dir($dir)) continue;
      if(!file_exists('themes/'.$dir.'/style.css')) continue;
      
      $css = file_get_contents('themes/'.$dir.'/style.css');
      $comment = [];
      
      // https://www.w3.org/TR/CSS2/grammar.html#scanner
      if(!preg_match('#\/\*[^*]*\*+([^/*][^*]*\*+)*\/#',$css,$comment)) continue;

      // remove /*, */
      $comment = trim(preg_replace(['#^/\*#','#\*/$#'],'',trim($comment[0])));
      
      // get theme data
      $data = [];
      $rows = explode("\n",$comment);
      foreach($rows as $row)
      {
        $tmp = explode(':',$row);
        $key = strtolower(trim(array_shift($tmp)));
        $value = trim(implode(':',$tmp));
        $data[$key] = $value;
      }
      
      // only using description for now
      if(isset($data['description']) && $data['description']!=='') $themes[$dir] = $data['description'];
    }
    
    // sort by description
    asort($themes);
    
    return $themes;
  }

  public function get_languages()
  {
    $languages = array();

    foreach ($this->db->get('translations_languages') as $language) {
      $languages[] = array(
        'name' => $language['name'],
        'code' => $language['code']
      );
    }

    return $languages;
  }

  // get language for user
  public function get_user_language()
  {
    $all_languages = $this->get_languages();

    if($this->user->userdata && !empty($this->user->userdata['language']) && !empty($all_languages[$this->user->userdata['language']]))
      return $all_languages[$this->user->userdata['language']];

    else return false;
  }

  // get strings for the user's language
  public function strings()
  {

    if ($this->user->userdata && !empty($this->user->userdata['language'])) {
      $language = $this->user->userdata['language'];

      if(!preg_match('/^[0-9a-z_-]+$/i',$language)) return array();

      $languages = array_keys($this->get_languages());
      if(array_search($language,$languages)===false) return array();

      $strings = array();

      $this->db->where('code', $language);
      $lang_id = $this->db->get_one('translations_languages')['id'];

      $this->db->where('language_id', $lang_id);
      $translations = $this->db->get('translations_values');

      foreach ($translations as $translation) {
        $strings[$translation['source_str']] = $translation['result_str'];
      }

      return $strings;

    } else {
      return array();
    }
  }

  // recursive function to find files with specific extention given a directory.
  // if override_path is set, same-filename-files in this directory will be used instead of given directory. (theme overrides).
  private function find_files($dir,$ext=false,&$array=[])
  {
    if(!is_dir($dir)) return $array;

    $files = scandir($dir);

    foreach($files as $file)
    {
      $dirfile = $dir.'/'.$file;

      // scan if directory
      if(is_dir($dirfile) && $file[0]!='.') $this->find_files($dirfile,$ext,$array);
      
      // or add file if file
      elseif(is_file($dirfile)) $array[]=$dirfile;
    }

    return $array;
  }
  
}
