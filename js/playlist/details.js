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

// playlist details page
OB.Playlist.detailsPage = function(id)
{

  OB.UI.replaceMain('playlist/details.html');

  OB.API.post('device','station_id_avg_duration', {}, function(data) { OB.Playlist.station_id_avg_duration=data.data;

  OB.API.post('playlist', 'get', { 'id': id }, function(data) {
  
    if(data.status==false) { $('#playlist_details_message').text(OB.t('Playlist Details','Playlist not found')); return; }
    var pldata = data.data;

    OB.API.post('playlist','get_details',{'id': id}, function(data) {

      var used = data.data;

      $('#playlist_details_id').text(id);

      $('#playlist_details_name').text(pldata.name);
      $('#playlist_details_description').text(pldata.description);

      if(OB.Playlist.status=='private') $('#playlist_details_visibility').text(OB.t('Playlist Details','Private'));
      else $('#playlist_details_visibility').text(OB.t('Playlist Details','Public'));

      $('#playlist_details_created').text(format_timestamp(pldata.created));
      $('#playlist_details_updated').text(format_timestamp(pldata.updated));

      $('#playlist_details_owner').text(pldata.owner_name);

      // handle playlist items
      if(typeof(pldata.items)=='undefined' || pldata.items.length==0) $('#playlist_details_items_table').replaceWith(htmlspecialchars(OB.t('Playlist Details','No playlist items found')));

      else { 

        var pl_item_time_estimated = false;   
        var pl_item_time_total = 0;

        $.each(pldata.items, function(index,item) {

          if(item.type=='station_id') 
          {
            $('#playlist_details_items_table').append('<tr><td>'+htmlspecialchars(OB.t('Playlist Edit','Station ID'))+'</td><td>'+secsToTime(OB.Playlist.station_id_avg_duration)+' ('+htmlspecialchars(OB.t('Playlist Details','estimated'))+')</td></tr>');
            pl_item_time_estimated = true;
            pl_item_time_total += parseFloat(OB.Playlist.station_id_avg_duration);
          }

          else if(item.type=='breakpoint') 
          {
            $('#playlist_details_items_table').append('<tr><td>'+htmlspecialchars(OB.t('Playlist Edit','Breakpoint'))+'</td><td>00:00</td></tr>');
          }

          else if(item.type=='dynamic') 
          {
            $('#playlist_details_items_table').append('<tr><td>'+htmlspecialchars(OB.t('Playlist Edit','Dynamic Selection'))+': '+htmlspecialchars(item.dynamic_name)+'</td><td>'+secsToTime(item.dynamic_duration)+' ('+htmlspecialchars(OB.t('Playlist Details','estimated'))+')</td></tr>');
            pl_item_time_estimated = true;
            pl_item_time_total += parseFloat(item.dynamic_duration);
          }

          else 
          {
            $('#playlist_details_items_table').append('<tr><td>'+htmlspecialchars(item.artist+' - '+item.title)+'</td><td>'+secsToTime(item.duration)+'</td></tr>');
            pl_item_time_total += parseFloat(item.duration);
          }

        });

        $('#playlist_details_items_table').append('<tr><td colspan="2" ><span>'+htmlspecialchars(OB.t('Playlist Edit','Total Duration'))+':</span> '+secsToTime(pl_item_time_total)+(pl_item_time_estimated ? ' ('+htmlspecialchars(OB.t('Playlist Details','estimated'))+')' : '')+'</td></tr>');

      }

      

      // handle 'where used';
      if(used.length==0) $('#playlist_details_used').append(OB.t('Playlist Where Used','Playlist is not in use'));

      else
      {
        $.each(used,function(index,used_detail) {
          $('#playlist_details_used ul').append('<li>'+htmlspecialchars(OB.t('Playlist Where Used',used_detail.where))+': '+htmlspecialchars(used_detail.name)+'</li>');
        });
      }

      $('#playlist_details_message').html('');
      $('#playlist_details').show();

    });

  }); 

  });

}
