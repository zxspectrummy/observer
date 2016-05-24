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

OB.Device = new Object();

OB.Device.init = function()
{
  OB.Callbacks.add('ready',-4,OB.Device.initMenu);
}

OB.Device.initMenu = function()
{
  OB.UI.addSubMenuItem('admin',['Admin Menu','Device Manager'],'device_settings',OB.Device.settings,20,'manage_devices');
  OB.UI.addSubMenuItem('admin',['Admin Menu','Device Monitoring'],'device_monitoring',OB.Device.monitor,30,'view_device_monitor');
}

OB.Device.settingsDetails = function(id) 
{
  var expand_text = OB.t('Common', 'Expand');
  var collapse_text = OB.t('Common', 'Collapse');

  if($('#device_'+id+'_details').is(':visible')==true)
  {
    $('#device_'+id+'_details').hide();
    $('#device_'+id+'_expand_link').html(expand_text);
  }

  else
  {
    $('#device_'+id+'_details').show();
    $('#device_'+id+'_expand_link').html(collapse_text);
  }

  $('#device_list .device_details').not('#device_'+id+'_details').hide(); // only allow one device 'expanded' at a time.
  $('#device_list .device_expand_link').not('#device_'+id+'_expand_link').html(expand_text);

}

OB.Device.settingsRemoveDefaultPlaylist = function(device_id)
{
  $('.device_settings_form[data-device_id='+device_id+'] .device_settings_default_playlist').html(OB.t('Device Manager','Playlist Drag Zone'));
}

OB.Device.settingsAddDefaultPlaylist = function(device_id, playlist_id, playlist_name)
{
  var html = '<div data-id="'+playlist_id+'"><a href="javascript: OB.Device.settingsRemoveDefaultPlaylist(\''+device_id+'\');">x</a> '+htmlspecialchars(playlist_name)+'</div>';
  $('.device_settings_form[data-device_id='+device_id+'] .device_settings_default_playlist').html(html);
}

OB.Device.settingsAddStationId = function(device_id, media_id, media_text)
{

    // get rid of our help text if this is the first item.
    if($('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids > div').length<1) $('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').html('');

    // only add if it doesn't already exist
    if($('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').children('[data-id="'+media_id+'"]').length<1)
    {
      var html = '<div data-id="'+media_id+'"><a href="javascript: OB.Device.settingsRemoveStationId(\''+device_id+'\','+media_id+');">x</a> '+htmlspecialchars(media_text)+'</div>';
      $('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').append(html);  
    }
}

OB.Device.settingsRemoveStationId = function(device_id, media_id)
{
  $('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').children('[data-id="'+media_id+'"]').remove();

  // restore our help text if there are no more station IDs.
  if($('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').children().length<1) $('.device_settings_form[data-device_id='+device_id+'] .device_settings_station_ids').html(OB.t('Device Manager','Media Drag Zone'));

}

OB.Device.settingsNewDevice = function()
{
  
  // TODO this is ugly... lock up and wait for device list ot actually load since we use the device list to determine our available parent devices.
  // will be fixed up with some device settings UI enhanacements soon.
  var cache_wait_start = new Date().getTime();

  while(OB.Device.settings_device_cache === false)
  {
    if(new Date().getTime() > cache_wait_start + 3000) // don't wait longer than 3 seconds.
    {
      OB.UI.alert(OB.t('Device Manager','Device List Must Load'));
      return;
    }
  }

  var htmladd = OB.Device.settingsForm();
  
  htmladd += '<fieldset>' +
  '<div class="fieldrow"><button class="add" onclick="OB.Device.deviceSave();" data-t data-tns="Common">Save</button>' +
  '<button onclick="OB.Device.settingsNewDeviceCancel();" data-t data-tns="Common">Cancel</button></div></fieldset>';

  $('#devices_new_form').html(htmladd);

  $('#devices_new_form').wrap('<div data-tns="Device Manager"></div>');

  OB.UI.translateHTML( $('#devices_new_form') );
//  OB.UI.replaceMain();
//  OB.UI.translateHTML( $('#layout_main') );

  // some form processing
  OB.Device.settingsFormProcess();

  $('#devices_new_form').show();

  $('#devices_new_device_button').hide();

}

OB.Device.settingsNewDeviceCancel = function(keep_message)
{
  if(!keep_message) $('#device_main_message').hide();

  $('#devices_new_form').hide();
  $('#devices_new_device_button').show();
}

OB.Device.settingsFormParentChange = function(element)
{

  $form = $(element).parents('.device_settings_form');

  if($form.find('.device_settings_parent_id').val()==0) 
  {
    $form.find('.device_settings_parent_id_options').hide();
    $form.find('.device_settings_use_parent_schedule').attr('checked',false);
    $form.find('.device_settings_use_parent_dynamic').attr('checked',false);
    $form.find('.device_settings_use_parent_ids').attr('checked',false);
    $form.find('.device_settings_use_parent_playlist').attr('checked',false);
    $form.find('.device_settings_use_parent_emergency').attr('checked',false);
    OB.Device.settingsFormParentOptionChange(element);
  }
  else $form.find('.device_settings_parent_id_options').show();
}

OB.Device.settingsFormParentOptionChange = function(element)
{
  var $form = $(element).parents('.device_settings_form');
  var ids_checked = $form.find('.device_settings_use_parent_ids').is(':checked');
  var playlist_checked = $form.find('.device_settings_use_parent_playlist').is(':checked');
  var schedule_checked = $form.find('.device_settings_use_parent_schedule').is(':checked');

  if(!schedule_checked)
  {
    $form.find('.device_settings_use_parent_dynamic_container').hide();
    $form.find('.device_settings_use_parent_dynamic').attr('checked',false);
  }
  else $form.find('.device_settings_use_parent_dynamic_container').show();

  var dynamic_checked = $form.find('.device_settings_use_parent_dynamic').is(':checked');

  // show/hide station ID settings
  $form.find('.device_settings_station_ids_row').toggle(!ids_checked);
  $form.find('.device_settings_station_ids_duration_row').toggle(!ids_checked);      

  // show/hide default playlist setting.
  $form.find('.device_settings_default_playlist_row').toggle(!playlist_checked);

  // show/hide media types setting.
  $form.find('.device_settings_supports_dynamic').toggle(!dynamic_checked);

}

OB.Device.settingsForm = function(data)
{

  if(data) var device_id = data.id;
  else device_id = 'new';

  var $html = $(OB.UI.getHTML('device/settings_form.html'));

  // set device id on form.
  $html.find('.device_settings_form').attr('data-device_id',device_id);

  // add timezone options to form.
  $html.find('.device_settings_timezone').html(OB.UI.getHTML('device/tzoptions.html'));

  return $html.html();
}

OB.Device.settingsFormProcess = function(data)
{

  if(data) var device_id = data.id;
  else device_id = 'new';

  $form = $('.device_settings_form[data-device_id='+device_id+']');

  // establish drop target for media (station IDs)  
  $form.find('.device_settings_station_ids').addClass('droppable_target_media');
  $form.find('.device_settings_station_ids').droppable({
      drop: function(event, ui) 
      {
//        alert('ding dong');
        if($(ui.draggable).attr('data-mode')=='media') 
        {
          OB.Device.settingsAddStationId(device_id,$(ui.draggable).attr('data-id'),$(ui.draggable).attr('data-artist')+' - '+$(ui.draggable).attr('data-title'));  
        }
      }

  });

  // establish drop target for playlist (default playlist)
  $form.find('.device_settings_default_playlist').addClass('droppable_target_playlist');
  $form.find('.device_settings_default_playlist').droppable({

    drop: function(event, ui) { 

      if($(ui.draggable).attr('data-mode')!='playlist') return;

      if($('.sidebar_search_playlist_selected').length>1) { OB.UI.alert('Select a single playlist for default content.'); return; }

      $('.sidebar_search_playlist_selected').each(function(index,element) {
        OB.Device.settingsAddDefaultPlaylist(device_id,$(element).attr('data-id'),$(element).attr('data-name'));
      });

    }

  });

  // add parent list.
  var devices = OB.Device.settings_device_cache;
  var $select = $form.find('.device_settings_parent_id');

  for(var i in devices)
  {
    if(!devices[i].parent_device_id && devices[i].id!=device_id) // child devices can't be parents.
      $select.append('<option value="'+devices[i].id+'">'+htmlspecialchars(devices[i].name)+'</option>');
  }

  if(data)
  {
    $form.find('.device_settings_name').val(data.name);
    $form.find('.device_settings_description').val(data.description);
    $form.find('.device_settings_stream_url').val(data.stream_url);
    $form.find('.device_settings_ip').val(data.ip_address);
    $form.find('.device_settings_station_id_image_duration').val(data.station_id_image_duration);

    $form.find('.device_settings_parent_id option[value='+data.parent_device_id+']').attr('selected','selected');
    $form.find('.device_settings_timezone option[value="'+data.timezone+'"]').attr('selected','selected');

    if(data.support_audio==1) $form.find('.device_settings_supports_audio').attr('checked','checked');
    if(data.support_images==1) $form.find('.device_settings_supports_images').attr('checked','checked');
    if(data.support_video==1) $form.find('.device_settings_supports_video').attr('checked','checked');
    if(data.support_linein==1) $form.find('.device_settings_supports_linein').attr('checked','checked');

    if(data.parent_device_id)
    {
      if(data.use_parent_dynamic==1) $form.find('.device_settings_use_parent_dynamic').attr('checked','checked');
      if(data.use_parent_schedule==1) $form.find('.device_settings_use_parent_schedule').attr('checked','checked');
      if(data.use_parent_ids==1) $form.find('.device_settings_use_parent_ids').attr('checked','checked');
      if(data.use_parent_playlist==1) $form.find('.device_settings_use_parent_playlist').attr('checked','checked');
      if(data.use_parent_emergency==1) $form.find('.device_settings_use_parent_emergency').attr('checked','checked');
    }

  }

  OB.Device.settingsFormParentChange($form.find('.device_settings_parent_id'));
  OB.Device.settingsFormParentOptionChange($form.find('.device_settings_parent_id'));

}

OB.Device.settings_device_cache = false;
OB.Device.settingsDeviceList = function()
{

  $('#device_list').hide();
  $('#device_list').html('<tr><th><span data-t>ID</span></th><th data-t>Name</th><th data-t>Description</th><th>&nbsp;</th><th data-t>Status</th><th>&nbsp;</th></tr>');


  OB.API.post('device','device_list', { }, function(data) { 

    OB.Device.settings_device_cache = data.data;

    var devices = data.data;

    for(var i in devices)
    {

      var last_connect = devices[i].last_connect ? format_timestamp(devices[i].last_connect) : '<i data-t>never</i>';
      var last_connect_schedule = devices[i].last_connect_schedule ? format_timestamp(devices[i].last_connect_schedule) : '<i data-t>never</i>';
      var last_connect_playlog = devices[i].last_connect_playlog ? format_timestamp(devices[i].last_connect_playlog) : '<i data-t>never</i>';
      var last_connect_media = devices[i].last_connect_media ? format_timestamp(devices[i].last_connect_media) : '<i data-t>never</i>';
      var last_connect_emergency =   devices[i].last_connect_emergency ? format_timestamp(devices[i].last_connect_emergency) : '<i data-t>never</i>';
      var version = devices[i].version ? devices[i].version : '<i data-t>not available</i>';

      var dhtml = '<tr id="device_'+devices[i].id+'_row">\
          <td>'+htmlspecialchars(devices[i].id)+'</td>\
          <td >'+htmlspecialchars(devices[i].name)+'</td>\
          <td>'+htmlspecialchars(devices[i].description)+'</td>\
          <td class="last_connect_error"><div>&nbsp</div><div class="last_connect_error_all">&nbsp;</div>\
          <div class="last_connect_error_schedule">&nbsp;</div><div class="last_connect_error_emergency">&nbsp;</div>\
          <div class="last_connect_error_media">&nbsp;</div><div class="last_connect_error_playlog">&nbsp;</div></td>\
          <td class="last_connect_time">\
          <div class="version"><span data-t>Version</span>: '+version+'</div>' +
          '<div class="last_connect_all"><span data-t>All</span>: '+last_connect+'</div>' +
          '<div class="last_connect_schedule"><span data-t>Schedule</span>: '+last_connect_schedule+'</div>' +
          '<div class="last_connect_emergency"><span data-t>Emergency</span>: '+last_connect_emergency+'</div>' +
          '<div class="last_connect_media"><span data-t>Media</span>: '+last_connect_media+'</div>' +
          '<div class="last_connect_playlog"><span data-t>Playlog</span>: '+last_connect_playlog+'</div>\
          </td>\
          <td><a class="device_expand_link" id="device_'+devices[i].id+'_expand_link" href="javascript: OB.Device.settingsDetails('+devices[i].id+');" data-t data-tns="Common">Expand</a></td></tr>\
          <tr class="hidden expanding device_details" id="device_'+devices[i].id+'_details"><td colspan="5">\
            <obwidget id="device_'+devices[i].id+'_message" type="message"></obwidget>';
  
      dhtml += OB.Device.settingsForm(devices[i]);

      dhtml +='<fieldset><div class="fieldrow"><button class="add" onclick="OB.Device.deviceSave('+devices[i].id+');" data-t data-tns="Common">Save</button><button onclick="OB.Device.deviceDelete('+devices[i].id+');" class="delete" data-t data-tns="Common">Delete</button><button onclick="$(\'#device_'+devices[i].id+'_details\').hide();" data-t data-tns="Common">Cancel</button></div></fieldset>\
          </td></tr>';

      $('#device_list').append(dhtml);

      OB.UI.widgetHTML( $('#device_list') );

      // display alert icons where device has not connected recently 
      var present_timestamp = Math.floor(new Date().getTime()/1000);
      if( devices[i].last_connect && present_timestamp-devices[i].last_connect > 3600 ) $('#device_'+devices[i].id+'_row .last_connect_error_all').html('&lt;!&gt;');
      if( devices[i].last_connect_schedule && present_timestamp-devices[i].last_connect_schedule > 3600 ) $('#device_'+devices[i].id+'_row .last_connect_error_schedule').html('&lt;!&gt;');
      if( devices[i].last_connect_playlog && present_timestamp-devices[i].last_connect_playlog > 3600 ) $('#device_'+devices[i].id+'_row .last_connect_error_playlog').html('&lt;!&gt;');
      if( devices[i].last_connect_media && present_timestamp-devices[i].last_connect_media > 3600 ) $('#device_'+devices[i].id+'_row .last_connect_error_media').html('&lt;!&gt;');
      if( devices[i].last_connect_emergency && present_timestamp-devices[i].last_connect_emergency > 3600 ) $('#device_'+devices[i].id+'_row .last_connect_error_emergency').html('&lt;!&gt;');

      // add our default playlist.
      if(devices[i].default_playlist_id) OB.Device.settingsAddDefaultPlaylist(devices[i].id,devices[i].default_playlist_id,devices[i].default_playlist_name);

      // add our station ids
      var media_ids = devices[i].media_ids;
      for(var j in media_ids)
      {
        OB.Device.settingsAddStationId(devices[i].id,media_ids[j].id,media_ids[j].artist+' - '+media_ids[j].title);
      }

      // some form processing
      OB.Device.settingsFormProcess(devices[i]);

    }

    $('#device_list').show();
    $('#device_list').wrap('<div data-tns="Device Manager"></div>');
    OB.UI.translateHTML( $('#layout_main') );

  });

}

OB.Device.settings = function()
{
  $('.sf-submenu').hide();

  OB.UI.replaceMain('device/settings.html');
  OB.Device.settingsDeviceList();


}

OB.Device.deviceDelete = function(device_id,confirm)
{

  if(!confirm)
  {

    OB.UI.confirm('Are you sure you want to delete this device? All data for this device (including schedules) will be removed.',
      function() { OB.Device.deviceDelete(device_id,true); }, 'Yes, Delete', 'No, Cancel', 'delete');

  }

  else
  {

    OB.API.post('device','delete', { 'id': device_id },function(data) {

      if(data.status==true)
      {
        $('#device_main_message').obWidget('success','Device deleted.');
        OB.Device.settingsDeviceList();
      }

      else
      {
        $('#device_'+device_id+'_message').obWidget('error',data.msg);
      }

    });

  }
}

OB.Device.deviceSave = function(device_id) 
{

  if(!device_id) {
    device_id = 'new';
  }

  var $form = $('.device_settings_form[data-device_id='+device_id+']');

  var device_name = $form.find('.device_settings_name').val();
  var device_description = $form.find('.device_settings_description').val();
  var device_stream = $form.find('.device_settings_stream_url').val();
  var device_ip = $form.find('.device_settings_ip').val();
  var device_password = $form.find('.device_settings_password').val();
  var device_station_id_image_duration = $form.find('.device_settings_station_id_image_duration').val();

  var device_timezone = $form.find('.device_settings_timezone').val();

  var device_supports_audio = $form.find('.device_settings_supports_audio').is(':checked');
  var device_supports_images = $form.find('.device_settings_supports_images').is(':checked');
  var device_supports_video = $form.find('.device_settings_supports_video').is(':checked');
  var device_supports_linein = $form.find('.device_settings_supports_linein').is(':checked');

  var device_parent_id = $form.find('.device_settings_parent_id').val();
  var device_use_parent_schedule = $form.find('.device_settings_use_parent_schedule').is(':checked');
  var device_use_parent_dynamic = $form.find('.device_settings_use_parent_dynamic').is(':checked');
  var device_use_parent_ids = $form.find('.device_settings_use_parent_ids').is(':checked');
  var device_use_parent_playlist = $form.find('.device_settings_use_parent_playlist').is(':checked');
  var device_use_parent_emergency = $form.find('.device_settings_use_parent_emergency').is(':checked');

  // get station IDs.
  var device_station_ids = new Array();
  $form.find('.device_settings_station_ids').children().each(function(key,value) {
    if($(value).attr('data-id')) device_station_ids.push($(value).attr('data-id'));
  });

  // get default playlist
  var device_default_playlist = false;
  $form.find('.device_settings_default_playlist').children().each(function(key,value) {
    if($(value).attr('data-id')) device_default_playlist = $(value).attr('data-id');
  });

  if(device_id == 'new') device_id = false;

  OB.API.post('device','edit', { 'id': device_id, 'timezone': device_timezone, 'station_ids': device_station_ids, 
          'default_playlist': device_default_playlist, 'name': device_name, 'description': device_description, 
          'stream_url': device_stream, 'ip_address': device_ip, 'password': device_password, 
          'support_audio': device_supports_audio, 'support_video': device_supports_video, 
          'support_images': device_supports_images, 'support_linein': device_supports_linein, 'station_id_image_duration': device_station_id_image_duration,
          'parent_device_id': device_parent_id, 'use_parent_schedule': device_use_parent_schedule,
          'use_parent_dynamic': device_use_parent_dynamic, 'use_parent_ids': device_use_parent_ids,
          'use_parent_playlist': device_use_parent_playlist, 'use_parent_emergency': device_use_parent_emergency }, function(data) {

    if(!device_id) device_id = 'main'; // we want to manipulate the main message for new devices.

    $('#device_'+device_id+'_message').obWidget((data.status==true ? 'success' : 'error'), data.msg);

    if(device_id == 'main' && data.status == true)
    {
      OB.Device.settingsNewDeviceCancel(true); // hides form, keeps message
      OB.Device.settingsDeviceList(); // reload the device list
    }

    if(device_id!='main') {
      $('#device_main_message').hide(); // hide this so we aren't confusing.
    }

  });

}
  
OB.Device.monitor = function()
{
  $('.sf-submenu').hide();
  OB.UI.replaceMain('device/monitor.html');

  // friendly date picker
  $('#monitor_date_start').datepicker({ dateFormat: "yy-mm-dd" });
  $('#monitor_date_end').datepicker({ dateFormat: "yy-mm-dd" });

  // fill in date fields with some likely defaults
  var presentDate = new Date();
  var presentDateFormatted = presentDate.getFullYear()+'-'+timepad((presentDate.getMonth()+1))+'-'+timepad(presentDate.getDate());

  $('#monitor_date_start').val(presentDateFormatted);
  $('#monitor_date_end').val(presentDateFormatted);

  OB.API.post('device','device_list', {}, function(data)
  {
    var devices = data.data;
    $.each(devices,function(index,item) {

      // make sure we have permission for this
      if(OB.Settings.permissions.indexOf('view_device_monitor')==-1 && OB.Settings.permissions.indexOf('view_device_monitor:'+item.id)==-1) return;
      $('#monitor_device_select').append('<option value="'+item.id+'">'+htmlspecialchars(item.name)+'</option>');
    });
  });

}

OB.Device.monitorFilterFieldChange = function()
{

  var val = $('#monitor_filter_field').val();
  
  if(val=='media_id')
  {
    $('#monitor_filter_operator option:[value="like"]').hide();
    $('#monitor_filter_operator option:[value="not_like"]').hide();
  }

  else
  {
    $('#monitor_filter_operator option:[value="like"]').show();
    $('#monitor_filter_operator option:[value="not_like"]').show();
  }

}

OB.Device.monitor_filter_id = 0;

OB.Device.monitorFilterAdd = function()
{

  OB.Device.monitor_filter_id++;

  var filter_column = $('#monitor_filter_field').val();
  var filter_operator = $('#monitor_filter_operator').val();
  var filter_value = $.trim($('#monitor_filter_value').val());

  if(filter_value=='') return;

  var filter_friendly_string = $('#monitor_filter_field option:selected').text()+' '+$('#monitor_filter_operator option:selected').text()+' '+$('#monitor_filter_value').val();

  var $html = $('<div><a href="javascript: OB.Device.monitorFilterDel('+OB.Device.monitor_filter_id+');">[x]</a> '+htmlspecialchars(filter_friendly_string)+'</div>');
  $html.attr('id','monitor_filter_'+OB.Device.monitor_filter_id);
  $html.attr('data-column',filter_column);
  $html.attr('data-operator',filter_operator);
  $html.attr('data-value',filter_value);

  $('#monitor_filter_list').append($html.outerHTML());

  // redo search if we have a visible container (i.e. if we're searched...)
  if($('#monitor_results_container').is(':visible')) OB.Device.monitorSearch();

}

OB.Device.monitorFilterDel = function(id)
{
  $('#monitor_filter_'+id).remove();
  if($('#monitor_results_container').is(':visible')) OB.Device.monitorSearch();
}

OB.Device.monitorSearch = function()
{

  $('#monitor_results_container').hide();

  var date_start = $('#monitor_date_start').val();
  var date_end = $('#monitor_date_end').val();
  
  // validate our start / end date, convert to UTC timestamp.
  var date_regexp = /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/;
  if(!date_regexp.test(date_end) || !date_regexp.test(date_start))
  {
    $('#monitor_message').obWidget('error','The start or end date is invalid.');
    return;
  }

  var date_start_array = date_start.split('-');
  var date_end_array = date_end.split('-');

  var date_start_object = new Date(date_start_array[0],(date_start_array[1]-1),date_start_array[2],0,0,0);
  var date_end_object = new Date(date_end_array[0],(date_end_array[1]-1),date_end_array[2],23,59,59);

  if(date_start_object > date_end_object)
  {
    $('#monitor_message').obWidget('error','The start date must be before the end date.');
    return;
  }

  var fields = new Object();
  fields.limit = 1000;
  fields.offset = 0;
  fields.orderby = $('#monitor_orderby').val();
  fields.orderdesc = $('#monitor_orderdesc').val();

  fields.device_id = $('#monitor_device_select').val();

  fields.start_timestamp = Math.round(date_start_object.getTime()/1000);
  fields.end_timestamp = Math.round(date_end_object.getTime()/1000);

  // get our filters...
  filters = new Array();
  $('#monitor_filter_list div').each(function(index,element)
  {

    var filter = new Object();
    filter.column = $(element).attr('data-column');
    filter.operator = $(element).attr('data-operator');
    filter.value = $(element).attr('data-value');

    filters.push(filter);

  });

  fields.filters = filters;

  OB.API.post('device','monitor_search',fields,function(data) { 

    if(data.status==false) 
    {

      $('#monitor_message').obWidget('error',data.msg);
      return;

    }

    var results = data.data.results;
    var total_rows = data.data.total_rows;

    if(total_rows==0)
    {
      $('#monitor_no_results').show();
      $('#monitor_results_table').hide();      
    }

    else
    {
      $('#monitor_no_results').hide();
      $('#monitor_results_table tbody').html('');
      $('#monitor_results_table').show();

      // add results to table
      $.each(results,function(index,row) {

        var html = '<tr><td>'+row.media_id+'</td><td>'+htmlspecialchars(row.artist)+'</td><td>'+htmlspecialchars(row.title)+'</td><td>'+format_timestamp(row.timestamp)+'</td><td>'+htmlspecialchars(row.context)+'</td><td>'+htmlspecialchars(row.notes)+'</td></tr>';

        $('#monitor_results_table tbody').append(html);

      });

    }

    $('#monitor_results_container').show();

  });

}


