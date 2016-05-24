<?

/*     
    Copyright 2012 OpenBroadcaster, Inc.

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

class OBFCallbacks
{

  private $callbacks;
  private $load;
  private $retvals;

  public function __construct()
  {
    $this->callbacks = array();
    $this->retvals = array();
  }

  static function &get_instance() {
    static $instance;
    if (isset( $instance )) {
      return $instance;
    }
    $instance = new OBFCallbacks();
    return $instance;
  }

  public function reset_retvals($hook)
  {
    if(!isset($this->retvals[$hook])) return false;
    $this->retvals[$hook] = array();
  }

  public function store_retval($hook,$callback,$value)
  {
    if(!isset($this->retvals[$hook])) $this->retvals[$hook] = array();
    $this->retvals[$hook][$callback] = $value;
  }

  public function get_retvals($hook)
  {
    if(!isset($this->retvals[$hook])) return false;
    return $this->retvals[$hook];
  }

  public function register_callback($callback,$hook,$position,$weight=0)
  {
  
    /*
      callback: what to call back?  must be model or controller method... in "SomeModel.method" or "ControllerName.method" format.
      hook: what to hook into? Class.method.
      position: where in this method is our callback run?
      weight: lower numbers are run first.   can be negative.
      return: whether the callback should hijack the method's return (will prevent further execution of method or additional callbacks)
    
      standard positions for models (might not be available, others will be used too):

        init: found before the method.
        return: found before a data return.

      standard positions for controllers (these are always available, more might be available)

        controller callbacks are chained together with the last callback's return array provided to the next callback.
          non-callback controller methods are not provided with the previous return.

        init: run before the controller
        return: run after the controller
    */

    if(!isset($this->callbacks[$hook])) $this->callbacks[$hook]=array();
    if(!isset($this->callbacks[$hook][$position])) $this->callbacks[$hook][$position]=array();

    $cb = new stdClass;
    $cb->callback = $callback;  
    $cb->hook = $hook;
    $cb->position = $position;
    $cb->weight = $weight;

    $this->callbacks[$hook][$position][]=$cb;

    usort($this->callbacks[$hook][$position],array($this,'callbacks_sort'));

    return true;

  }

  private function callbacks_sort($a,$b)
  {
    return ($a->weight < $b->weight) ? -1 : 1;
  }

  public function fire($hook,$position,&$args=null,&$data=null)
  {

    // get our OBFLoader.  (Loading in construct creates a loop/php-crash).
    if(empty($this->load)) $this->load = OBFLoad::get_instance();

    // return early if no registered callbacks.
    if(!isset($this->callbacks[$hook])) return new OBFCallbackReturn;
    if(!isset($this->callbacks[$hook][$position])) return new OBFCallbackReturn;

    foreach($this->callbacks[$hook][$position] as $cb)
    {

      $cbname_explode = explode('.',$cb->callback);
      $callback_is_model = (strtolower(substr($cbname_explode[0],-5))=='model' ? true : false);

      if($callback_is_model)
      {
        $model = $this->load->model(substr($cbname_explode[0],0,-5));
        $cb_return = $model->$cbname_explode[1]($hook,$position,$args);
      }

      else
      {
        $controller = $this->load->controller($cbname_explode[0]);
        if($data) $controller->data = &$data;
        $cb_return = $controller->handle($cbname_explode[1],$hook,$position);
      }

      if(isset($cb_return->v)) $this->store_retval($hook,$cb->callback,$cb_return->v);
      else $this->store_retval($hook,$cb->callback,$cb_return->v);

      // callback is forcing an early return.
      if(!empty($cb_return) && $cb_return->r)
      { 
        return $cb_return;
      }

    }

    return new OBFCallbackReturn;

  }

}

class OBFCallbackReturn
{
  public $r;
  public $v;

  public function __construct()
  {
    
    $args = func_get_args();

    if(isset($args[0])) $this->v = $args[0];
    else $this->v = null;

    if(isset($args[1]) && !empty($args[1])) $this->r = true;
    else $this->r = false;

  }
}
