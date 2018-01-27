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

OB.Playlist.newPage = function()
{

  OB.Playlist.advanced_items = [];

  OB.API.post('device','station_id_avg_duration', {}, function(data) {
    OB.Playlist.station_id_avg_duration=data.data;

    OB.UI.replaceMain('playlist/addedit.html');
    $('#playlist_edit_heading').text( OB.t('Playlist New', 'Heading') );
    $('#playlist_edit_instructions').text( OB.t('Playlist New', 'Instructions') );

    // need this to prevent adding other user's private media to this playlist.
    $('#playlist_owner_id').val(OB.Account.user_id);

    OB.Playlist.addeditTypeChange();

    OB.Playlist.addeditInit();
    OB.Playlist.advancedInit();
  });

}


OB.Playlist.editPage = function()
{

  OB.Playlist.advanced_items = [];

  if($('.sidebar_search_playlist_selected').size() > 1) { OB.UI.alert( ['Playlist Edit','Select One Playlist Only'] ); return; }

  // playlist data from our search result (slight possibility it is out of date).
  var playlist_local = $('.sidebar_search_playlist_selected');

  OB.API.post('device','station_id_avg_duration', {}, function(data) { OB.Playlist.station_id_avg_duration=data.data;
  OB.API.post('playlist','get', { 'id': $(playlist_local).attr('data-id') }, function(data) {

    // if we didn't get our playlist we are trying to edit, just direct to create new playlist instead.
    if(data.status==false) { OB.Playlist.newPage(); return; }

    OB.UI.replaceMain('playlist/addedit.html');
    $('#playlist_edit_heading').text( OB.t('Playlist Edit', 'Edit Playlist') );
    $('#playlist_edit_instructions').text( OB.t('Playlist Edit', 'Instructions') );

    OB.Playlist.addeditInit();
    OB.Playlist.advancedInit();

    // playlist data from the db (up to date, at this point anyway...)
    playlist_data = data.data;

    $('#playlist_name_input').val(playlist_data['name']);
    $('#playlist_description_input').val(playlist_data['description']);
    $('#playlist_id').val($(playlist_local).attr('data-id'));
    $('#playlist_owner_id').val($(playlist_local).attr('data-owner_id'));

    $('#playlist_status_input').val(playlist_data['status']);
    $('#playlist_type_input').val(playlist_data['type']);

    OB.Playlist.addeditTypeChange();

    $.each(playlist_data['items'], function(index, item) {

      if(playlist_data['type']=='advanced')
      {
        OB.Playlist.advancedAddItem(item,true);
      }
      
      else
      {
        if(item['type']=='dynamic') OB.Playlist.addeditInsertDynamic(false,item['dynamic_query'],item['dynamic_duration'],item['dynamic_name'],item['dynamic_num_items'],item['dynamic_image_duration']);
        else if(item['type']=='station_id') OB.Playlist.addeditInsertStationId();
        else if(item['type']=='breakpoint') OB.Playlist.addeditInsertBreakpoint();
        else OB.Playlist.addeditInsertItem(item['id'],item['artist']+' - '+item['title'],item['duration'],item['type']);
      }

    });

    if(playlist_data['type']=='advanced') OB.Playlist.advancedItemsDisplay();
    else OB.Playlist.addeditTotalDuration();

    if(playlist_data['type']=='live_assist')
    {
      $.each(playlist_data['liveassist_button_items'], function(index,item) { OB.Playlist.addeditInsertLiveassistItem(item); });
    }

  }); });

}

// remove all playlist items from all playlist types/containers.
OB.Playlist.addeditRemoveAllFromAll = function()
{
  OB.Playlist.addeditRemoveAll(true);
  OB.Playlist.advancedRemoveAll(true);
}

OB.Playlist.addedit_type = false;

OB.Playlist.addeditTypeChange = function()
{
  var change_to = $('#playlist_type_input').val();
  if(change_to=='live_assist') change_to='standard';

  if(OB.Playlist.addedit_type == 'advanced') var has_items = OB.Playlist.advanced_items.length>0;
  else var has_items = $('.playlist_addedit_item').length>0;

  if(!has_items || OB.Playlist.addeditTypeChangeConfirm())
  {
    OB.Playlist.addedit_type = $('#playlist_type_input').val();

    OB.Playlist.addeditRemoveAllFromAll();
    $('.playlist_edit_container').hide();
    $('#playlist_edit_'+change_to+'_container').show();

    if(change_to=='standard' && $('#playlist_type_input').val()=='live_assist') {
      $('#playlist_insert_breakpoint_button').show();
      $('#playlist_liveassist_buttons').show();
    }
    else if(change_to=='standard') {
      $('#playlist_insert_breakpoint_button').hide();
      $('#playlist_liveassist_buttons').hide();
    }
  }
  else $('#playlist_type_input').val(OB.Playlist.addedit_type);
}

OB.Playlist.addeditTypeChangeConfirm = function()
{
  return confirm( OB.t('Playlist Edit','Change Playlist Type Confirm') );
}


// modal window for item settings (like start/stop time, image duration, dynamic selection name...)
OB.Playlist.addeditItemProperties = function(id,type,required)
{

  OB.UI.openModalWindow('playlist/'+type+'_properties.html');

  // initialize properties window for dynamic item.
  if(type=='dynamic')
  {
    $('#dynamic_name').val($('#playlist_addedit_item_'+id).attr('data-name'));
    $('#dynamic_num_items').val( $('#playlist_addedit_item_'+id).attr('data-num_items') ? $('#playlist_addedit_item_'+id).attr('data-num_items') : 10); // 10 is default.
    $('#dynamic_image_duration').val( $('#playlist_addedit_item_'+id).attr('data-image_duration') ? $('#playlist_addedit_item_'+id).attr('data-image_duration') : 15); // 15 is default.

    $('#dynamic_num_items_all').change(function()
    {
      if($('#dynamic_num_items_all').is(':checked')) $('#dynamic_num_items').hide();
      else $('#dynamic_num_items').show();
    });


    if($('#playlist_addedit_item_'+id).attr('data-num_items_all')=="true") $('#dynamic_num_items_all').attr('checked','checked');
    $('#dynamic_num_items_all').change();

    // determine how this dynamic item works (and provide information)
    var search_query = $.parseJSON($('#playlist_addedit_item_'+id).attr('data-query'));

    if(search_query.mode=='advanced')
    {
      var search_type = 'Advanced Search Type';
      var search_string = '';

      $.each(search_query.filters,function(index,filter)
      {
        search_string += '&bull; '+htmlspecialchars(filter.description)+'<br>';
      });
    }

    else if(search_query.string=='') 
    {
      var search_type = 'All Media Search Type';
      var search_string = null;
    }

    else
    {
      var search_type = 'Standard Search Type';
      var search_string = htmlspecialchars(search_query.string);
    }

    $('#dynamic_item_description').append('<div class="fieldrow"><label>Type</label><span>'+htmlspecialchars(OB.t('Playlist Dynamic Item Properties',search_type))+'</span></div>');
    if(search_string!=null) $('#dynamic_item_description').append('<div class="fieldrow"><label>Query</label><span>'+search_string+'</span></div>');

  }

  // initialize properties window for image item.
  else if(type=='image')
  {
    if($('#playlist_type_input').val()=='standard')
    {
      $('#image_properties_duration').val( Math.round($('#playlist_addedit_item_'+id).attr('data-duration')) );
    }

    else // advanced
    {
      $('#image_properties_duration').val(OB.Playlist.advanced_items[id].duration);
    }
  }

  // if our this is required (new dynamic itemof rexample), we remove the item if this is cancelled.
  if(required)
  {
    $('#item_properties_cancel').click(function() {
      OB.UI.closeModalWindow();
      OB.Playlist.addeditRemoveItem(id);
    });
  }

  $('#item_properties_save').click(function() {

    // dynamic used only for standard playlist right now.
    if(type=='dynamic')
    {
      var selection_name = $('#dynamic_name').val();
      var num_items = $('#dynamic_num_items').val();
      var num_items_all = $('#dynamic_num_items_all').is(':checked');
      var image_duration = $('#dynamic_image_duration').val();
      var search_query = $('#playlist_addedit_item_'+id).attr('data-query');

      $('#item_properties_message').hide();

      OB.API.post('playlist','validate_dynamic_properties', { 'selection_name': selection_name, 'num_items': num_items, 'num_items_all': num_items_all, 'image_duration': image_duration, 'search_query': $.parseJSON(search_query) }, function(data) {

        if(data.status==false)
        {
          $('#item_properties_message').obWidget('error',data.msg);
        }

        else
        {
          OB.Playlist.addeditSetDynamicItemProperties( id, data.data.duration, selection_name, num_items, num_items_all, image_duration );
          OB.Playlist.addeditTotalDuration();
          OB.UI.closeModalWindow();
        }

      });
    }

    // image properties could be for standard or advanced playlist.
    if(type=='image')
    {

      // make sure image properties are valid.
      if(!$('#image_properties_duration').val().match(/^[0-9]+$/) || $('#image_properties_duration')=='0')
      {
        $('#item_properties_message').obWidget('error',['Playlist Image Properties','Valid Image Duration Required']);
      }

      // okay to save, standard playlist.
      else if($('#playlist_type_input').val()=='standard')
      {
        $('#playlist_addedit_item_'+id).attr('data-duration', $('#image_properties_duration').val());    
        OB.Playlist.addeditImageDurationUpdate(id);
        OB.UI.closeModalWindow();
      }

      // okay to save, advanced playlist.
      else
      {      
        OB.Playlist.advanced_items[id].duration = $('#image_properties_duration').val();
        OB.Playlist.advancedItemsDisplay();
        OB.UI.closeModalWindow();
      }

    }

  });

}

OB.Playlist.save = function()
{

  var id = $('#playlist_id').val();
  var playlist_name = $('#playlist_name_input').val();
  var description = $('#playlist_description_input').val();
  var status = $('#playlist_status_input').val();
  var type = $('#playlist_type_input').val();

  if(type=='advanced') var items = OB.Playlist.advancedGetItems();
  else var items = OB.Playlist.addeditGetItems();

  if(type=='live_assist') var liveassist_button_items = OB.Playlist.liveassistButtonItems();
  else var liveassist_button_items = false;

  $('#playlist_addedit_message').hide();

  OB.API.post('playlist','edit', { 'id': id, 'name': playlist_name, 'description': description, 'status': status, 'type': type, 'items': items, 'liveassist_button_items': liveassist_button_items }, function(data) {

    $('#playlist_addedit_message').obWidget(data.status ? 'success' : 'error', data.msg);

    if(data.status == true)
    {
      $('#playlist_id').val(data.data);
      OB.Sidebar.playlistSearch(); // update sidebar search entries.
    }

  });

}
