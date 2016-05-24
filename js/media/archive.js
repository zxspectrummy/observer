/*     
    Copyright 2012-2014 OpenBroadcaster, Inc.

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

OB.Media.deletePage = function()
{

  if($('.sidebar_search_media_selected').size() < 1) { OB.UI.alert(['Media Delete','At Least One Delete']); return; }

  var status = $('.sidebar_search_media_selected:first').attr('data-status');

  if(status != 'approved' && status != 'unapproved' && status != 'archived') { OB.UI.alert(['Media Delete','Error Fetch Media Status']); return; }

  OB.UI.replaceMain('media/delete.html');

  $('#media_delete_list').attr('data-status',status);

  if(status=='approved') $('#media_second_message').text(OB.t('Media Delete','Media Move to Archive'));
  else $('#media_second_message').text(OB.t('Media Delete','Media Deletion'));

  // get 'where media is used' information, load page.
  var media_ids = Array();
  $('.sidebar_search_media_selected').each(function(index,element) { media_ids.push($(element).attr('data-id')); });

  OB.API.post('media', 'used', { 'id': media_ids }, function(data) {

    var used_info = data.data;
    var append_html = '';

    $.each(used_info,function(used_index,used) {

      $media = $('#sidebar_search_media_result_'+used.id);

      if(used.can_delete)
      {
        $('#media_delete_list').append('<li data-id="'+$media.attr('data-id')+'">'+htmlspecialchars($media.attr('data-artist')+' - '+$media.attr('data-title'))+'</li>');
      } 

      else 
      {
        $('#media_cannot_delete ul').append('<li data-id="'+$media.attr('data-id')+'">'+htmlspecialchars($media.attr('data-artist')+' - '+$media.attr('data-title'))+'</li>');
        $('#media_cannot_delete').show();
      }

      if(used.can_delete && used.used.length>0)  
      {

        append_html = '<ul>';

        $.each(used.used,function(where_used_index,where_used) {

          append_html += '<li>'+htmlspecialchars(OB.t('Media Delete','Item will be removed from'))+' '+htmlspecialchars(OB.t('Media Where Used',where_used.where))+' <i>'+htmlspecialchars(where_used.name)+'</i></li>';

        });

        append_html += '</ul>';

        $('#media_delete_list > li[data-id='+used.id+']').append(append_html);  

        // if(used.can_delete) $('#media_delete_list > li[data-id='+used.id+']').append(append_html);  
        // else $('#media_cannot_delete > ul > li[data-id='+used.id+']').append(append_html);

      }

    });

  });



}

OB.Media.delete = function()
{
    
  var status = $('#media_delete_list').attr('data-status');

  var delete_ids = new Array();

  $('#media_delete_list > li').each(function(index,element) {
    delete_ids.push($(element).attr('data-id'));
  });

  if(status == 'approved') var delete_method = 'archive';
  else var delete_method = 'delete';

  OB.API.post('media',delete_method,{ 'id': delete_ids },function(data) {

    if(data.status==true) 
    {

      OB.Sidebar.mediaSearch();

      $('.media_delete_button').remove();
      $('#media_delete_list').remove();

      $('#media_top_message').text(OB.t('Media Delete','Media Deleted'));
      $('#media_second_message').remove();

    }

    else OB.UI.alert(data.msg);

  });



}


OB.Media.unarchivePage = function()
{

  if($('.sidebar_search_media_selected').size() < 1) { OB.UI.alert(['Media Restore','At Least One Restore']); return; }

  OB.UI.replaceMain('media/unarchive.html');

  $('.sidebar_search_media_selected').each(function(index,element) {
    $('#media_restore_list').append('<li data-id="'+$(element).attr('data-id')+'">'+htmlspecialchars($(element).attr('data-artist')+' - '+$(element).attr('data-title'))+'</li>');
  });

}

OB.Media.unarchive = function()
{
    
  var restore_ids = new Array();

  $('#media_restore_list > li').each(function(index,element) {
    restore_ids.push($(element).attr('data-id'));
  });

  OB.API.post('media','unarchive',{ 'id': restore_ids },function(data) {

    if(data.status==true) 
    {

      OB.Sidebar.mediaSearch();

      $('.media_restore_button').remove();
      $('#media_restore_list').remove();

      $('#media_top_message').text(OB.t('Media Restore','Media Restored'));
      $('#media_second_message').remove();

    }

    else OB.UI.alert(data.msg);

  });

}
