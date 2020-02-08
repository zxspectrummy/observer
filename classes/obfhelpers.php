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

class OBFHelpers
{

  static public function &get_instance()
  {
    static $instance;
    if (isset( $instance )) {
      return $instance;
    }
    $instance = new OBFHelpers();
    return $instance;
  }

  public function test()
  {
    echo 'obfhelper test';
  }

  public function sanitize_html ($html) {
    $allow_tags = "<p><br><strong><b><u><ul><ol><li><a>";
    $allow_attr = array('href', 'title', 'alt');

    $html = strip_tags($html, $allow_tags);
    $result = new DOMDocument();
    if ($html == '') return '';
    $result->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    foreach ($result->getElementsByTagName('*') as $element) {
      foreach ($element->attributes as $attr) {
        if (!in_array($attr->name, $allow_attr)) {
          $element->removeAttributeNode($attr);
        }
      }
    }

    $result = $result->saveHTML();
    return $result;
  }

}
