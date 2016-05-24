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

// media details page
OB.Media.detailsPage = function(id)
{

  OB.UI.replaceMain('media/details.html');

  OB.API.post('media', 'get', { 'id': id }, function(data) {
  
    if(data.status==false) { $('#media_details_message').text(OB.t('Media Details','Media not found')); return; }
    var item = data.data;

    OB.API.post('media','get_details',{'id': id}, function(data) {

      var used = data.data;

      $('#media_details_id').text(id);

      $('#media_details_artist').text(item.artist);
      $('#media_details_title').text(item.title);
      $('#media_details_album').text(item.album);
      $('#media_details_year').text(item.year);
      $('#media_details_category').text(OB.t('Media Categories',item.category_name));
      $('#media_details_country').text(OB.t('Media Countries',item.country_name));
      $('#media_details_language').text(OB.t('Media Languages',item.language_name));
      $('#media_details_genre').text(OB.t('Media Genres',item.genre_name));
      $('#media_details_comments').text(item.comments);

      if(item.is_archived==1) $('#media_details_approval').text(OB.t('Media Details','Archived'));
      else if(item.is_approved==1) $('#media_details_approval').text(OB.t('Media Details','Approved'));
      else $('#media_details_approval').text(OB.t('Media Details','Not approved'));

      if(item.is_copyright_owner==1) $('#media_details_copyright').text(OB.t('Common','Yes'));
      else $('#media_details_copyright').text(OB.t('Common','No'));

      if(item.status=='private') $('#media_details_visibility').text(OB.t('Media Details','Private'));
      else $('#media_details_visibility').text(OB.t('Media Details','Public'));

      if(item.dynamic_select==1) $('#media_details_dynamic').text(OB.t('Common','Yes'));
      else $('#media_details_dynamic').text(OB.t('Common','No'));

      $('#media_details_created').text(format_timestamp(item.created));
      $('#media_details_updated').text(format_timestamp(item.updated));

      $('#media_details_uploader').text(item.owner_name);

      // handle 'where used';

      if(used.length==0) $('#media_details_used').append(OB.t('Media Details','Media is not in use'));

      else
      {
        $.each(used,function(index,used_detail) {
          if(used_detail.where=='playlist') $('#media_details_used ul').append('<li>'+htmlspecialchars(OB.t('Media Where Used','playlist'))+': <a href="javascript: OB.Playlist.detailsPage('+used_detail.id+');">'+htmlspecialchars(used_detail.name)+'</a></li>');
          if(used_detail.where=='playlist_dynamic') $('#media_details_used ul').append('<li>*'+htmlspecialchars(OB.t('Media Where Used','playlist_dynamic'))+': <a href="javascript: OB.Playlist.detailsPage('+used_detail.id+');">'+htmlspecialchars(used_detail.name)+'</a></li>');
          if(used_detail.where=='device') $('#media_details_used ul').append('<li>'+htmlspecialchars(OB.t('Media Where Used','device'))+': '+htmlspecialchars(used_detail.name)+'</li>');
          if(used_detail.where=='emergency') $('#media_details_used ul').append('<li>'+htmlspecialchars(OB.t('Media Where Used','emergency'))+': '+htmlspecialchars(used_detail.name)+'</li>');
          if(used_detail.where=='schedule' || used_detail.where=='recurring schedule') $('#media_details_used ul').append('<li>'+htmlspecialchars(OB.t('Media Where Used','schedule'))+': '+htmlspecialchars(used_detail.name)+'</li>');
        });

        $('#media_details_used').append('<p>* '+htmlspecialchars(OB.t('Media Details','Possible Dynamic Selection'))+'</p>');

      }

      $('#media_details_message').html('');

      $('#media_details_table').show();
      $('#media_details_used').show();

    });

  }); 

}

