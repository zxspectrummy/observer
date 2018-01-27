<?php 

/*     
    Copyright 2013 OpenBroadcaster, Inc.

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

require('updates.php'); 

if(empty($_GET['run']) || $_GET['run']!=1) $run=false;
else $run=true;

?>
<html>
<head>
  <title>OpenBroadcaster Admin</title>

  <style>
  body
  {
    font-family: sans-serif;
  }

  h1
  {
    font-size: 22px;
  }
  
  h2
  {
    font-size: 16px;
    font-weight: bold;
  }

  p, td, li
  {
    font-size: 13px;
  }

  #container
  {
    max-width: 700px;
  }

  #check_table
  {
    border-collapse: separate;
  }

  #check_table td
  {
    padding: 5px;
  }

  #check_table td:first-child
  {
    font-weight: bold;
  }

  .check_error
  {
    background-color: #faa;
  }

  .check_warning
  {
    background-color: #ffc;
  }

  .check_ok
  {
    background-color: #cfc;
  }
  
  </style>
</head>

<body>

<div id="container">

<h1>OpenBroadcaster Install Checker</h1>

<table id="check_table">
<?php foreach($u->checker_results as $result) { ?>
<tr class="check_<?php if($result[2]==0) echo 'ok'; elseif($result[2]==1) echo 'warning'; else echo 'error'; ?>">
  <td><?=htmlspecialchars($result[0])?></td>
  <td><?php if(is_array($result[1])) { $output = implode("\n\n",$result[1]); echo nl2br(htmlspecialchars($output)); } else echo nl2br(htmlspecialchars($result[1])); ?></td>
</tr>
<?php } ?>
</table>

<?php if(!$u->checker_status) { ?><p style="font-weight: bold; padding-top: 25px;">Errors above (red) must be corrected before updates can be run.</p><?php } ?>

<?php if($u->checker_status) { ?>
  <h1>OpenBroadcaster Updates</h1>

  <?php $list = $u->updates(); 
    //$list = array_reverse($list); // don't reverse list, causes updates to run in wrong order. will need to rework to do this. (new updates on top is nicer.)
  ?>

  <p>This will complete database and other updates required when upgrading OpenBroadcaster.</p>

  <p style="font-weight: bold;">Please make a backup of the database before running updates!</p>

  <p style="text-align: center; font-size: 1.1em; padding: 10px 0;"><a href="index.php?run=1">Run Updates Now</a></p>


  <?php foreach($list as $update) { ?>

    <h2><?=$update->version?></h2>

    <?php if(!$update->needed) { ?>
      <p style="font-weight: bold; color: #006;">This update is not needed or is already installed.</p>
    <?php } elseif($run) { 
      $result = $u->run($update);
      if($result==true) { ?><p style="font-weight: bold; color: #060;">This update has completed successfully.</p><?php } 
      else
      {
        ?><p style="color: #a00; font-weight: bold;">An error occurred while attempting to make this update.</p>
        <?php if($update->error) echo '<p style="font-weight: bold;">'.htmlspecialchars($update->error).'</p>'; ?>
        <p>This script will now terminate.</p><?php
        break;
      }
    } ?>  

    <?php 
    $items = $update->items(); 
    if(empty($items)) { ?><p>No description is available for this update.</p><?php } else { ?>
      <ul>
      <?php foreach($items as $item) { ?>
        <li><?=htmlspecialchars($item)?></li>
      <?php } ?>
      </ul>
    <?php } ?>
    <br>

  <?php } ?>
<?php } ?>

</div>

</body>
</html>
