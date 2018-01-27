<?php

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

/* run from command line, output list of missing media files. */

if(php_sapi_name()!='cli') die('cli only');

require('../components.php');

$db = OBFDB::get_instance();

$db->query('select * from media where is_approved = 1 and is_archived = 0 order by id');

foreach($db->assoc_list() as $nfo)
{

	$filename = OB_MEDIA.'/'.$nfo['file_location'][0].'/'.$nfo['file_location'][1].'/'.$nfo['filename'];
	if(!file_exists($filename)) 
	{
		echo $filename;

		if($nfo['artist'] != trim($nfo['artist']) || $nfo['title'] != trim($nfo['title']) )
			echo ' TRIM';

		echo "\n";

	}


}
