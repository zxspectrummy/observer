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

OB.Playlist.deletePage = function()
{

  if($('.sidebar_search_playlist_selected').size() < 1) { return; }

  OB.UI.replaceMain('playlist/delete.html')

  // get 'where playlist is used' information, load page.
  var playlist_ids = Array();
  $('.sidebar_search_playlist_selected').each(function(index,element) { playlist_ids.push($(element).attr('data-id')); });

  OB.API.post('playlist', 'used', { 'id': playlist_ids }, function(data) {

    var used_info = data.data;
    var append_html = '';

    $.each(used_info,function(used_index,used) {

      $playlist = $('#sidebar_search_playlist_result_'+used.id);

      if(used.can_delete)
      {
        $('#playlist_delete_list').append('<li data-id="'+$playlist.attr('data-id')+'">'+htmlspecialchars($playlist.attr('data-name'))+'</li>');
      }
      else
      {
        $('#playlist_cannot_delete > ul').append('<li data-id="'+$playlist.attr('data-id')+'">'+htmlspecialchars($playlist.attr('data-name'))+'</li>');
        $('#playlist_cannot_delete').show();
      }

      if(used.can_delete && used.used.length>0)
      {

        append_html = '<ul>';

        $.each(used.used,function(where_used_index,where_used) {

          append_html += '<li>'+htmlspecialchars(OB.t('Playlist Delete','Item will be removed from'))+' '+htmlspecialchars(OB.t('Playlist Where Used',where_used.where))+' <i>'+htmlspecialchars(where_used.name)+'</i></li>';

        });

        append_html += '</ul>';

        $('#playlist_delete_list > li[data-id='+used.id+']').append(append_html);

        // if(used.can_delete) $('#playlist_delete_list > li[data-id='+used.id+']').append(append_html);
        // else $('#playlist_cannot_delete > ul > li[data-id='+used.id+']').append(append_html);

      }

    });

  });


}

OB.Playlist.delete = function()
{

  var delete_ids = new Array();

  $('#playlist_delete_list > li').each(function(index,element) {
    delete_ids.push($(element).attr('data-id'));
  });

  OB.API.post('playlist','delete',{ 'id': delete_ids },function(data) {

    if(data.status==true) 
    {
      OB.Sidebar.playlistSearch();

      $('.playlist_delete_button').remove();
      $('#playlist_delete_list').remove();

      $('#playlist_top_message').text(OB.t('Playlist Delete','Playlists have been deleted.'));
    }

    else OB.UI.alert(data.msg);

  });

}
