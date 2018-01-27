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

// used by new / edit to establish playlist as 'droppable' target.
OB.Playlist.addeditInit = function()
{
  // establish drop target for media (station IDs)  
  $('#playlist_items').droppable({
      drop: function(event, ui) {

        var private_media_alert = false;
        var playlist_owner_id = $('#playlist_owner_id').val();

        if($(ui.draggable).attr('data-mode')=='media')
        {
          $('.sidebar_search_media_selected').each(function(index,element) { 

            if($(element).attr('data-public_status')=='private' && ($(element).attr('data-owner_id')!=playlist_owner_id))
            {
              private_media_alert = true;
              return true;
            }

            if($(element).attr('data-type')=='image') var insert_duration = 15;
            else var insert_duration = $(element).attr('data-duration');

            OB.Playlist.addeditInsertItem($(element).attr('data-id'),$(element).attr('data-artist')+' - '+$(element).attr('data-title'),insert_duration,$(element).attr('data-type'));
          });

          if(private_media_alert) OB.UI.alert(['Playlist Edit','Cannot Use Private Item']);
  
          // unselect our media from our sidebar
          OB.Sidebar.mediaSelectNone();

        }

        else if($(ui.draggable).attr('data-mode')=='playlist')
        {

          $('.sidebar_search_playlist_selected').each(function(index,element) {

            OB.API.post('playlist','get', { 'id': $(element).attr('data-id') }, function(data) {

              if(data.status==false) return;

              var playlist_data = data.data;                

              $.each(playlist_data['items'], function(index, item) {
                if(item.type=='dynamic') OB.Playlist.addeditInsertDynamic(false,item['dynamic_query'],item['dynamic_duration'],item['dynamic_name'],item['dynamic_num_items'],item['dynamic_image_duration']);
                else if(item.type=='station_id') OB.Playlist.addeditInsertStationId();
                else if(item.status=='private' && item.owner_id!=playlist_owner_id)
                {
                  private_media_alert = true;
                  return true;
                }
                else OB.Playlist.addeditInsertItem(item['id'],item['artist']+' - '+item['title'],item['duration'],item['type']);
              });

              if(private_media_alert) OB.UI.alert(['Playlist Edit','Cannot Use Private Item']);

            });

          });

          // get the content of this playlist, add it to our playlist we are editing.


          // unselect our playlists from our sidebar
          OB.Sidebar.playlistSelectNone();
        }
      }
  });

  $('#playlist_liveassist_items').droppable({
      drop: function(event, ui) {
        if($(ui.draggable).attr('data-mode')=='playlist')
        {
          var item = {};
          item.id = $(ui.draggable).attr('data-id');
          item.name = $(ui.draggable).attr('data-name');
          item.description = $(ui.draggable).attr('data-description');

          OB.Playlist.addeditInsertLiveassistItem(item);
        }
      }
  });

}

OB.Playlist.addeditKeypress = function(e)
{
  if(!$('#playlist_edit_standard_container:visible').length) return; // make sure we are actually editing advanced playlist.
  if(!$('#playlist_edit_standard_container .playlist_addedit_item.selected').length && !$('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected').length) return; // make sure we have something selected.

  if(e.keyCode == 8 || e.keyCode == 46)
  {
    OB.Playlist.addeditRemoveItem();
    e.preventDefault();
  }

  else if(e.keyCode == 38) OB.Playlist.addeditItemUp();
  else if(e.keyCode == 40) OB.Playlist.addeditItemDown();
}


OB.Playlist.addedit_item_last_id = 0;

// used by new / edit to add item to playlist
OB.Playlist.addeditInsertItem = function(id,description,duration,type)
{
  OB.Playlist.addedit_item_last_id += 1;

  // duration will be null (string) for images, newly added.
  if(duration=='null') duration = '';

  var duration_text = secsToTime(duration);

  $('#playlist_items').append('<div class="playlist_addedit_item" id="playlist_addedit_item_'+OB.Playlist.addedit_item_last_id+'" data-id="'+id+'" data-type="'+type+'">'+htmlspecialchars(description)+'<span class="playlist_addedit_duration">'+duration_text+'</span></div>');

  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).attr('data-duration',duration);

  // bind double-click to edit item properties.
  eval("$('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).dblclick(function() { OB.Playlist.addeditItemProperties("+OB.Playlist.addedit_item_last_id+",'"+type+"'); });");

  // item select
  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).click(OB.Playlist.addeditItemSelect);

  // update our total duration
  OB.Playlist.addeditTotalDuration();

  // hide our 'drag items here' help.
  $('#playlist_items_drag_help').hide();

  // make our list sortable
  $('#playlist_items').sortable({ start: OB.Playlist.addeditSortStart, stop: OB.Playlist.addeditSortStop });
}

OB.Playlist.addeditImageDurationUpdate = function(id)
{
  $('#playlist_addedit_item_'+id+' .playlist_addedit_duration').text(secsToTime($('#playlist_addedit_item_'+id).attr('data-duration')));
  OB.Playlist.addeditTotalDuration();
}

// remove selected item from playlist
OB.Playlist.addeditRemoveItem = function()
{
  if($('#playlist_edit_standard_container .playlist_addedit_item.selected').length)
  {
    $('#playlist_edit_standard_container .playlist_addedit_item.selected').remove();
    if( $('#playlist_items').children().size() == 1 ) $('#playlist_items_drag_help').show();
    OB.Playlist.addeditTotalDuration();
  }

  else if($('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected').length)
  {
    $('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected').remove();
    if( $('#playlist_liveassist_items').children().size() == 1 ) $('#playlist_liveassist_drag_help').show();
  }
}

// move selected item up
OB.Playlist.addeditItemUp = function()
{
  if($('#playlist_edit_standard_container .playlist_addedit_item.selected').length)
    $selected = $('#playlist_edit_standard_container .playlist_addedit_item.selected');

  else if($('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected').length)
    $selected = $('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected');

  else return; // nothing selected

  if($selected.is(':nth-child(2)')) return; // actually the first element already.
  $selected.insertBefore($selected.prev());
}

// move selected item down
OB.Playlist.addeditItemDown = function()
{
  if($('#playlist_edit_standard_container .playlist_addedit_item.selected').length)
    $selected = $('#playlist_edit_standard_container .playlist_addedit_item.selected');

  else if($('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected').length)
    $selected = $('#playlist_edit_standard_container .playlist_addedit_liveassist_item.selected');

  else return; // nothing selected

  if($selected.is(':last-child')) return; // actually the last element already.
  $selected.insertAfter($selected.next());
}

// used by new / edit to remove all items from playlist
OB.Playlist.addeditRemoveAll = function(skip_confirm)
{
  if($('.playlist_addedit_item').length && (skip_confirm || confirm( OB.t('Playlist Edit','Clear Items Confirm') )))
  {
    $('.playlist_addedit_item').remove();
    $('#playlist_items_drag_help').show();
    OB.Playlist.addeditTotalDuration();
  }
}

// used by new / edit to update total playlist time/duration
OB.Playlist.addeditTotalDuration = function()
{
  var total_duration = 0.00;
  var is_estimated = false;  // track whether this is just an estimate or not (i.e. dynamic selection or station id)

  $('#playlist_items .playlist_addedit_item').each(function(index,element) { 
    if(!isNaN(parseFloat($(element).attr('data-duration')))) {
      total_duration += parseFloat($(element).attr('data-duration'));
    }

    if($(element).attr('data-type')=='dynamic' || $(element).attr('data-type')=='station_id') is_estimated = true;
  });


  $('#playlist_total_duration').text(secsToTime(total_duration));
  if(is_estimated) 
  {
    $('#playlist_total_duration').prepend('*');
    $('#playlist_edit_estimated_help').show();
  }
  else $('#playlist_edit_estimated_help').hide();
}

// add a dynamic selection
OB.Playlist.addeditInsertDynamic = function(is_new,query,duration,selection_name,num_items,image_duration)
{
  if(typeof(query)=='object') query = $.toJSON(query); // we can get this in json (string) or object format.

  OB.Playlist.addedit_item_last_id += 1;

  $('#playlist_items').append('<div class="playlist_addedit_item" id="playlist_addedit_item_'+OB.Playlist.addedit_item_last_id+'">'+htmlspecialchars( OB.t('Playlist Edit','Dynamic Selection') )+': <span id="playlist_dynamic_selection_'+OB.Playlist.addedit_item_last_id+'_name"></span><span class="playlist_addedit_duration" id="playlist_dynamic_selection_'+OB.Playlist.addedit_item_last_id+'_duration"></span></div>');

  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).attr('data-query',query);
  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).attr('data-type','dynamic');

  eval("$('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).dblclick(function() { OB.Playlist.addeditItemProperties("+OB.Playlist.addedit_item_last_id+",'dynamic'); });");

  // item select
  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).click(OB.Playlist.addeditItemSelect);

  if(is_new) 
  {
    OB.Playlist.addeditItemProperties(OB.Playlist.addedit_item_last_id,'dynamic',true);
    $('#dynamic_item_instructions').text( OB.t('Playlist Edit','Dynamic Selection Last Search Notice') );
  }
  else 
  {
    OB.Playlist.addeditSetDynamicItemProperties(OB.Playlist.addedit_item_last_id,duration,selection_name,(num_items ? num_items : 0),!num_items,image_duration);
  }

  // hide our 'drag items here' help.
  $('#playlist_items_drag_help').hide();
  $('#playlist_items').sortable({ start: OB.Playlist.addeditSortStart, stop: OB.Playlist.addeditSortStop });
}

OB.Playlist.addeditInsertStationId = function()
{
  OB.Playlist.addedit_item_last_id += 1;

  $('#playlist_items').append('<div class="playlist_addedit_item" id="playlist_addedit_item_'+OB.Playlist.addedit_item_last_id+'"><span class="playlist_addedit_duration">*'+secsToTime(OB.Playlist.station_id_avg_duration)+'</span><i>'+htmlspecialchars( OB.t('Playlist Edit','Station ID') )+'</i></div>');

  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).attr('data-type','station_id');
  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).attr('data-duration',OB.Playlist.station_id_avg_duration);
  eval("$('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).dblclick(function() { OB.Playlist.addeditItemProperties("+OB.Playlist.addedit_item_last_id+",'station_id'); });");

  // item select
  $('#playlist_addedit_item_'+OB.Playlist.addedit_item_last_id).click(OB.Playlist.addeditItemSelect);

  // hide our 'drag items here' help.
  $('#playlist_items_drag_help').hide();

  OB.Playlist.addeditTotalDuration();
  $('#playlist_items').sortable({ start: OB.Playlist.addeditSortStart, stop: OB.Playlist.addeditSortStop });
}

OB.Playlist.addeditSetDynamicItemProperties = function(id,duration,selection_name,num_items,num_items_all,image_duration)
{
  $('#playlist_dynamic_selection_'+id+'_name').text(selection_name);
  $('#playlist_dynamic_selection_'+id+'_duration').text('*'+secsToTime(duration));          

  $('#playlist_addedit_item_'+id).attr('data-duration',duration);
  $('#playlist_addedit_item_'+id).attr('data-image_duration',image_duration);
  $('#playlist_addedit_item_'+id).attr('data-num_items',num_items);
  $('#playlist_addedit_item_'+id).attr('data-num_items_all',num_items_all);
  $('#playlist_addedit_item_'+id).attr('data-name',selection_name);
}

OB.Playlist.addeditGetItems = function()
{
  var items = new Array();

  $('#playlist_items').children().not('#playlist_items_drag_help').each(function(index,element) { 

    // type can be dynamic, audio, video, or image.  the last 3 are merged into 'media' in terms of how they are stored into the playlist items table.
    if($(element).attr('data-type')=='dynamic') items.push({ 
      'type': 'dynamic', 
      'num_items': $(element).attr('data-num_items'), 
      'num_items_all': $(element).attr('data-num_items_all')=='true' ? true : false,
      'image_duration': $(element).attr('data-image_duration'), 
      'query': $(element).attr('data-query'), 
      'name': $(element).attr('data-name') 
    });

    else if($(element).attr('data-type')=='station_id') items.push( { 'type': 'station_id' });
    else if($(element).attr('data-type')=='breakpoint') items.push( { 'type': 'breakpoint' });
    else items.push({ 'type': 'media', 'id': $(element).attr('data-id'), 'duration': $(element).attr('data-duration') });

  });

  return items;
}

OB.Playlist.addeditItemUnselect = function(e)
{
  if(e && ($(e.target).hasClass('playlist_addedit_item') || $(e.target).hasClass('playlist_addedit_liveassist_item'))) return;
  $('.playlist_addedit_item').removeClass('selected');
  $('.playlist_addedit_liveassist_item').removeClass('selected');
}

OB.Playlist.addeditItemSelect = function()
{
  OB.Playlist.addeditItemUnselect();
  $(this).addClass('selected');
}

OB.Playlist.addeditSortStart = function(event, ui)
{
  // select the item if we start moving it.
  $(ui.helper).click();
}

OB.Playlist.addeditSortStop = function(event, ui)
{
  $(ui.item).css('z-index','');
}

