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

/**
 * OpenBroadcaster helper functions.
 *
 * @package Class
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

  /**
   * Test method. Simply echoes 'obfhelper test'.
   */
  public function test()
  {
    echo 'obfhelper test';
  }

  /**
   * Sanitize HTML, allowing only specific tags and attributes from user input
   * and stripping out everything else.
   *
   * @param html
   *
   * @return sanitized_html
   */
  static public function sanitize_html ($html) {
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

  /**
   * Require specific named arguments in arrays passed to model methods. If
   * any of the required arguments aren't passed, throw an error using
   * user_error.
   *
   * @param args The array of arguments to check.
   * @param reqs The required arguments, in the format ['req1', 'req2', ...]
   */
  static public function require_args ($args, $reqs) {
    if (!is_array($args)) user_error('Passed arguments should be an array.');
    if (!is_array($reqs)) user_error('Required arguments should be an array.');

    foreach ($reqs as $req) {
      if (!isset($args[$req])) user_error('Missing argument: ' . $req);
    }
  }

  /**
   * Get list of arguments passed to a model method as reference, then set
   * any arguments to the default if not specified.
   *
   * @param args A reference to the array of arguments to check.
   * @param defs The default values in case an argument is not set, in the format ['def1' => defval1, 'def2' => defval2, ...]
   */
  static public function default_args (&$args, $defs) {
    if (!is_array($args)) user_error('Passed arguments should be an array.');
    if (!is_array($defs)) user_error('Default arguments should be an array.');

    foreach ($defs as $def => $defval) {
      if (!isset($args[$def])) $args[$def] = $defval;
    }
  }

}
