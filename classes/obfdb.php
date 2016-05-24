<?

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

if(!defined('TABLE_PREFIX')) define('TABLE_PREFIX','');

class OBFDB 
{

  private $connection;
  private $result;
  private $last_query;

  // helper variables for higher-level functions
  private $hl_what;
  private $hl_where;
  private $hl_where_string;
  private $hl_orderby;
  private $hl_limit;
  private $hl_offset;
  private $hl_leftjoin;
  private $hl_foundrows;

  private $hl_where_mode='AND';

  public function __construct() 
  {
    $this->connection = mysqli_connect(OB_DB_HOST, OB_DB_USER, OB_DB_PASS);
    mysqli_select_db($this->connection, OB_DB_NAME);
    mysqli_query($this->connection, 'SET NAMES \'utf8\'');
  }

  static function &get_instance() {

    static $instance;
  
    if (isset( $instance )) {
      return $instance;
    }

    $instance = new OBFDB();

    return $instance;

  }
    
  // basic sql query
  public function query($sql) 
  {
    $this->last_query = $sql;

    $start_time = microtime(true);
    $this->result = mysqli_query($this->connection, $sql);

    if(defined('OB_LOG_SLOW_QUERIES') && OB_LOG_SLOW_QUERIES==TRUE)
    {
      $duration = round(((microtime(true)-$start_time)),4);
      if($duration > 1) error_log('SLOW SQL ('.$duration.'s): '.$sql);
    }
  
    if($this->result) return true;
  }

  // get last query
  public function last_query() {
    return $this->last_query;
  }

  // sql error
  public function error()
  {
    return mysqli_error($this->connection);
  }

  // return an assoc_list from the previous query as an array.
  public function assoc_list() 
  {

    if(empty($this->result)) return false;
  
    $return = array();

    for($i=0;$i<$this->num_rows();$i++) 
    {

      $return[$i]=mysqli_fetch_assoc($this->result);

    }

    return $return;

  }

  // return the next assoc array from the last query.  useful when there is only one row (i.e.,selecting where id = x).
  public function assoc_row()
  {
    if(empty($this->result)) return false;
    return mysqli_fetch_assoc($this->result);  
  }

  // return an indexed array from the last query. 
  public function indexed_list()
  {

    if(empty($this->result)) return false;
  
    $return = array();

    for($i=0;$i<$this->num_rows();$i++) 
    {

      $return[$i]=mysqli_fetch_array($this->result);

    }

    return $return;

  }

  // return the next indexed array from the last query.
  public function indexed_row()
  {
    if(empty($this->result)) return false;
    return mysqli_fetch_array($this->result);  
  }

  // return the insert id of the last insert.
  public function insert_id() 
  {
    return mysqli_insert_id($this->connection);
  }

  // return the number of affected rows from the last query.
  public function affected_rows() 
  {
    return mysqli_affected_rows($this->connection);
  }

  // return the number of rows from the last select.
  public function num_rows()
  {
    return mysqli_num_rows($this->result);
  }

  // the very important SQL escape function. 
  public function escape($str)
  {
    return mysqli_real_escape_string($this->connection, $str);
  }



  /* HIGHER-LEVEL HELPER FUNCTIONS - Simple Active-Record Style System.  Room for improvement, but covers the basics. */
  /* not designed to replace every function, only most. */

  public function what($column,$as=null)
  {

    if(!is_array($this->hl_what)) $this->hl_what = array();

    $what = $this->format_table_column($column);
    if(!empty($as)) $what.= ' AS '.$this->format_backticks($as);

    $this->hl_what[] = $what;

    return true;
  }

  // compares a column to a value.
  public function where($column,$value,$operator='=')
  {
    if(!is_array($this->hl_where)) $this->hl_where=array();

    if(array_search($operator,array('=','>','<','>=','<=','!='))===FALSE) $operator='=';
  
    $where = $this->format_table_column($column).$operator.$this->format_value($value);

    $this->hl_where[]=$where;

    return true;
  }

  // compares a column to a value, using LIKE.
  public function where_like($column,$value)
  {

    if(!is_array($this->hl_where)) $this->hl_where=array();

    $where = $this->format_table_column($column).' LIKE '.$this->format_value('%'.$value.'%');
    $this->hl_where[]=$where;

    return true;

  }

  public function where_not_like($column,$value)
  {

    if(!is_array($this->hl_where)) $this->hl_where=array();

    $where = $this->format_table_column($column).' NOT LIKE '.$this->format_value('%'.$value.'%');
    $this->hl_where[]=$where;

    return true;

  }

  // sets where_mode to 'or' or 'and'
  public function where_mode($mode) 
  {

    $mode=strtoupper($mode);  

    if($mode!='OR' && $mode!='AND') return false;

    $this->hl_where_mode=$mode;
    return true;
  }
  
  // compares a column to a column.
  public function where_col($column,$value,$operator='=')
  {
    if(!is_array($this->hl_where)) $this->hl_where=array();

    if(array_search($operator,array('=','>','<','>=','<=','!='))===FALSE) $operator = '=';
  
    $where = $this->format_table_column($column).$operator.$this->format_table_column($value);

    $this->hl_where[]=$where;

    return true;  
  }

  // custom where string
  public function where_string($string)
  {

    $this->hl_where_string = trim($string);
    return true;

  }

  public function random_order()
  {
    $this->hl_orderby = 'rand()';
  }

  public function orderby($column,$dir='asc') 
  {
    if(!preg_match('/asc/i',$dir) && !preg_match('/desc/i',$dir)) $dir='asc';

    $this->hl_orderby = $this->format_table_column($column).' '.$dir;
    return true;
  }

  public function limit($limit)
  {
    if(!preg_match('/^[0-9]+$/',$limit)) return false;
    $this->hl_limit = $limit;
    return true;
  }

  public function offset($offset)
  {
    if(!preg_match('/^[0-9]+$/',$offset)) return false;
    $this->hl_offset = $offset;
    return true;
  }

  public function leftjoin($table,$column1,$column2)
  {
    $table = $this->format_backticks($table);
    $column1 = $this->format_table_column($column1);
    $column2 = $this->format_table_column($column2);

    if(!is_array($this->hl_leftjoin)) $this->hl_leftjoin=array();

    $this->hl_leftjoin[] = array($table,$column1,$column2);
  }

  private function reset_hlvars()
  {
    $this->hl_table = null;
    $this->hl_what = null;
    $this->hl_where = null;
    $this->hl_where_string = null;
    $this->hl_orderby = null;
    $this->hl_limit = null;
    $this->hl_offset = null;
    $this->hl_leftjoin = null;
    $this->hl_foundrows = null;
  }

  // generate a sql formatted datetime from a timestamp.
  public function format_datetime( $timestamp = null )
  {
    if(empty($timestamp)) $timestamp = time();
    return date('Y-m-d H:i:s', $timestamp);
  }

  public function format_value($value)
  {
    if(!is_int($value)) $value = '"'.$this->escape($value).'"';
    return $value;    
  }

  public function format_backticks($value)
  {
    return '`'.$this->escape($value).'`';
  }

  public function format_table_column($value) 
  {

    $table_column = explode('.',$value);

    if(count($table_column)>1) 
    { 
      $table = $this->format_backticks($table_column[0]);
      unset($table_column[0]);
      $column = implode('.',$table_column);

      if($column!='*') $column = $this->format_backticks($column);

      return $table.'.'.$column;
    }

    else return $this->format_backticks($table_column[0]);

  }


  // high-level get function. returns assoc-list with results or false.
  // param function in approximate order of use-frequency.
  /* select WHAT from TABLE left join LEFTJOIN on COLUMN1=COLUMN2 where WHERE order by ORDERBY limit LIMIT offset OFFSET. */
  public function get($table) 
  {

    if(empty($table)) return false; // we need a table!

    // get our table name.
    $table = $this->format_backticks($table);

    // put together our left join text.
    $leftjoin = '';
    if(is_array($this->hl_leftjoin)) foreach($this->hl_leftjoin as $lj)
    {
      $leftjoin .=' LEFT JOIN '.$lj[0].' ON '.$lj[1].'='.$lj[2];
    }

    $sql = 'SELECT '.($this->hl_foundrows ? 'SQL_CALC_FOUND_ROWS' : '').
                      (!empty($this->hl_what) ? ' '.implode(',',$this->hl_what) : ' *').
                      ' FROM '.TABLE_PREFIX.$table.
                      $leftjoin;

    if(!empty($this->hl_where_string)) $sql .= ' WHERE '.$this->hl_where_string;
    elseif(!empty($this->hl_where)) $sql .= ' WHERE '.implode(' '.$this->hl_where_mode.' ',$this->hl_where);

    $sql .= (!empty($this->hl_orderby) ? ' ORDER BY '.$this->hl_orderby : '').
                      (!empty($this->hl_limit) ? ' LIMIT '.$this->hl_limit : '').
                      (!empty($this->hl_offset) ? ' OFFSET '.$this->hl_offset : '');

    $this->query($sql);

    $this->reset_hlvars(); // resets all higher level variables for the next query data.

    return $this->assoc_list();

  }

  // same as above, but returns only the first result.  (useful when selecting based on a specific id, and not wanting 
  // to use $return[0][column], but just $return[column] instead.)
  // returns false if unavailable.
  public function get_one($table) 
  {

    $this->limit(1); // just need one.
    $assoc_list = $this->get($table);
    
    if(!is_array($assoc_list) || count($assoc_list)<1) return false;

    return $assoc_list[0];

  }

  // call this to be able to use found_rows() after the select.
  public function calc_found_rows()
  {
    $this->hl_foundrows = true;
  }

  // get the number of 'found rows' (ignoring limit/offset) from the last query.
  public function found_rows()
  {

    if($this->query('SELECT FOUND_ROWS()'))
    {

      $row = $this->indexed_row();
      return $row[0];

    }

    return false;

  }


  // high-level insert function
  public function insert($table,$data)
  {

    if(!is_array($data) || count($data)==0) return false;

    foreach($data as $item=>$value) {
      if($value===NULL) $setsql[]=$this->format_table_column($item).'=NULL';
      else $setsql[]=$this->format_table_column($item).'='.$this->format_value($value);
    }

    $sql='INSERT INTO '.TABLE_PREFIX.$this->format_backticks($table).' SET '.
                      implode(', ',$setsql);

    $this->reset_hlvars(); // resets all higher level variables for the next query data. (hl vars not used, done for consistency)

    if($this->query($sql)) 
    {
      return $this->insert_id();
    }


    else return false;

  }

  // high-level update function
  public function update($table,$data)
  {

    if(!is_array($data) || count($data)==0) return true; // nothing to update, but this isn't an error.
    if((!is_array($this->hl_where) || count($this->hl_where)<1) && empty($this->hl_where_string)) return false; // avoid inadvertantly updating all rows.

    foreach($data as $item=>$value) {
      if($value===NULL) $setsql[]=$this->format_table_column($item).'=NULL';
      else $setsql[]=$this->format_table_column($item).'='.$this->format_value($value);
    }

    // put together our left join text.
    $leftjoin = '';
    if(is_array($this->hl_leftjoin)) foreach($this->hl_leftjoin as $lj)
    {
      $leftjoin .=' LEFT JOIN '.$lj[0].' ON '.$lj[1].'='.$lj[2];
    }

    $query='UPDATE'.
                  ' '.TABLE_PREFIX.$this->format_backticks($table).
                  $leftjoin.
                  ' SET '.implode(', ',$setsql).
                  ' WHERE '.(!empty($this->hl_where_string) ? $this->hl_where_string : implode(' '.$this->hl_where_mode.' ',$this->hl_where)).
                  (!empty($this->hl_orderby) ? ' ORDER BY '.$this->hl_orderby : '').
                  (!empty($this->hl_limit) ? ' LIMIT '.$this->hl_limit : '');

    $this->reset_hlvars(); // resets all higher level variables for the next query data.

    return $this->query($query);

  }

  // high-level delete function
  public function delete($table) 
  {

    if((!is_array($this->hl_where) || count($this->hl_where)<1) && empty($this->hl_where_string)) return false; // avoid inadvertantly updating all rows.

    // put together our left join text.
    $leftjoin = '';
    if(is_array($this->hl_leftjoin)) foreach($this->hl_leftjoin as $lj)
    {
      $leftjoin .=' LEFT JOIN '.$lj[0].' ON '.$lj[1].'='.$lj[2];
    }

    $query='DELETE'.
                  ' FROM '.TABLE_PREFIX.$this->format_backticks($table).
                  $leftjoin.
                  ' WHERE '.(!empty($this->hl_where_string) ? $this->hl_where_string : implode(' '.$this->hl_where_mode.' ',$this->hl_where)).
                  (!empty($this->hl_orderby) ? ' ORDER BY '.$this->hl_orderby : '').
                  (!empty($this->hl_limit) ? ' LIMIT '.$this->hl_limit : '');

    $this->reset_hlvars(); // resets all higher level variables for the next query data.

    return $this->query($query);

  }

  // check if id exists
  public function id_exists($table,$id)
  {
    $this->where('id',$id);
    $test = $this->get_one($table);
    if($test) return true;
    else return false;
  }

}
