/*
    Copyright 2012-2020 OpenBroadcaster, Inc.

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
  //T Player Manager
  OB.UI.addSubMenuItem('admin', 'Player Manager', 'device_settings', OB.Device.settings, 20, 'manage_devices');
  //T Player Monitoring
  OB.UI.addSubMenuItem('admin', 'Player Monitoring', 'device_monitoring', OB.Device.monitor, 30, 'view_device_monitor');
}

/* ======================
   PLAYER MANAGER SECTION
====================== */

OB.Device.settings = function () {
  OB.UI.replaceMain('device/settings.html');
  OB.Device.playerOverview();
}

OB.Device.playerOverview = function (orderby = 'name', orderdesc = null) {
  var post = {};
  post.orderby = orderby;
  if (orderdesc != null) post.orderdesc = 'desc';

  OB.API.post('device', 'device_list', post, function (response) {
    if (!response.status) {
      $('#device_main_message').obWidget('error', response.msg);
      return false;
    }

    $.each(response.data, function (i, device) {
      var dev_version       = (device.version == '') ? '<em>N/A</em>': device.version;
      var dev_lastip        = device.last_ip_address;
      var dev_conn_all      = device.last_connect;
      var dev_conn_schedule = device.last_connect_schedule;
      var dev_conn_priority = device.last_connect_emergency;
      var dev_conn_media    = device.last_connect_media;
      var dev_conn_playlog  = device.last_connect_playlog;

      $html = $('<tr/>');
      $html.append($('<td/>').text(device.id));
      $html.append($('<td/>').text(device.name));
      $html.append($('<td/>').text(device.description));

      $error = $('<div/>');
      $html.append($('<td/>').append($error));

      $status = $('<div/>');

      //T Version
      $status.append($('<div/>').html('<span>' + OB.t('Version') + ': </span>' + dev_version));
      //T Last IP
      if (dev_lastip) $status.append($('<div/>').html('<span>' + OB.t('Last IP') + ': </span>' + dev_lastip));

      //T All
      if (dev_conn_all) $status.append(OB.Device.generateConnectionHTML('All', dev_conn_all));
      //T Schedule
      if (dev_conn_schedule) $status.append(OB.Device.generateConnectionHTML('Schedule', dev_conn_schedule));
      //T Priority
      if (dev_conn_priority) $status.append(OB.Device.generateConnectionHTML('Priority', dev_conn_priority));
      //T Media
      if (dev_conn_media) $status.append(OB.Device.generateConnectionHTML('Media', dev_conn_media));
      //T Playlog
      if (dev_conn_playlog) $status.append(OB.Device.generateConnectionHTML('Playlog', dev_conn_playlog));
      $html.append($('<td/>').append($status));
      
      //T Edit
      $html.append($('<td/>').html('<button class="edit" onclick="OB.Device.editDevice(' + device.id + ');">Edit</button>'));

      $('#device_list tbody').append($html);
    });
  });
}

OB.Device.sortOverview = function (elem, field) {
    $('#device_list tbody tr:nth-child(n+2)').empty();

    if ($(elem).hasClass('device_order_asc')) {
      $(elem).removeClass('device_order_asc').addClass('device_order_desc');
      $(elem).find('i').removeClass('fa-angle-down').addClass('fa-angle-up');
      OB.Device.playerOverview(field);
    } else {
      $(elem).removeClass('device_order_desc').addClass('device_order_asc');
      $(elem).find('i').removeClass('fa-angle-up').addClass('fa-angle-down');
      OB.Device.playerOverview(field, 'desc');
    }
    return false;
}

OB.Device.generateConnectionHTML = function (label, last_conn) {
  var now = new Date().getTime() / 1000;
  var alert_icon = ((now - last_conn) > 3600) ? '<i class="device_list_alert fas fa-exclamation-triangle"></i>' : '';

  return $('<div/>').html(alert_icon + '<span>' + label + ': </span>' + format_timestamp(last_conn));
}

OB.Device.newDevice = function () {
  OB.UI.openModalWindow('device/settings_form.html');

  $('#device_settings_id').val('new');
  $('.device_existing').hide();

  $('#device_settings_timezone').html(OB.UI.getHTML('device/tzoptions.html'));
  OB.Device.loadParentPlayers('new');

  OB.Device.dynamicFormOptions();
}

OB.Device.editDevice = function (dev_id) {
  OB.UI.openModalWindow('device/settings_form.html');

  $('#device_settings_id').val(dev_id);
  $('.device_existing').show();

  $('#device_settings_timezone').html(OB.UI.getHTML('device/tzoptions.html'));
  OB.Device.loadParentPlayers(dev_id);

  var post = {};
  post.id = dev_id;
  OB.API.post('device', 'get', post, function (response) {
    if (!response.status) {
      OB.UI.closeModalWindow();
      $('#device_main_message').obWidget('error', response.msg);
      return false;
    }

    $('#device_settings_name').val(response.data.name);
    $('#device_settings_description').val(response.data.description);

    $('#device_settings_parent_id').val(response.data.parent_device_id);
    if (response.data.use_parent_schedule == "1")  $('#device_settings_parent_schedule').prop('checked', true);
    if (response.data.use_parent_dynamic == "1")   $('#device_settings_parent_dynamic').prop('checked', true);
    if (response.data.use_parent_ids == "1")       $('#device_settings_parent_stations').prop('checked', true);
    if (response.data.use_parent_playlist == "1")  $('#device_settings_parent_playlist').prop('checked', true);
    if (response.data.use_parent_emergency == "1") $('#device_settings_parent_priority').prop('checked', true);

    $('#device_settings_ip').val(response.data.ip_address);

    if (response.data.support_audio == "1")  $('#device_settings_support_audio').prop('checked', true);
    if (response.data.support_images == "1") $('#device_settings_support_image').prop('checked', true);
    if (response.data.support_video == "1")  $('#device_settings_support_video').prop('checked', true);
    if (response.data.support_linein == "1") $('#device_settings_support_linein').prop('checked', true);

    $('#device_settings_timezone').val(response.data.timezone);

    $('#device_settings_playlist').val([response.data.default_playlist_id]);
    $('#device_settings_station_ids').val(response.data.station_ids)
    $('#device_settings_image_duration').val(response.data.station_id_image_duration);

    OB.Device.dynamicFormOptions();
  });
}

OB.Device.loadParentPlayers = function (dev_id) {
  $('#device_list tbody tr:gt(0)').each(function (i, e) {
    var $option = $('<option/>');
    var id      = $(e).find('td:nth-child(1)').text();
    var name    = $(e).find('td:nth-child(2)').text();

    if (id == dev_id) return;

    $option.val(id);
    $option.text(name);
    $('#device_settings_parent_id').append($option);
  });
}

OB.Device.dynamicFormOptions = function () {
  if ($('#device_settings_parent_id').val() != "") {
    $('#device_settings_parent_options').show();
  } else {
    $('#device_settings_parent_options').hide();
    $('#device_settings_parent_schedule').prop('checked', false);
    $('#device_settings_parent_stations').prop('checked', false);
    $('#device_settings_parent_playlist').prop('checked', false);
    $('#device_settings_parent_priority').prop('checked', false);
  }

  if ($('#device_settings_parent_schedule').is(':checked')) {
    $('#device_settings_parent_dynamic').parent().show();
  } else {
    $('#device_settings_parent_dynamic').parent().hide();
    $('#device_settings_parent_dynamic').prop('checked', false);
  }

  if ($('#device_settings_parent_dynamic').is(':checked')) {
    $('.no_parent_dynamic').hide();
    $('#device_settings_support_audio').prop('checked', false);
    $('#device_settings_support_image').prop('checked', false);
    $('#device_settings_support_video').prop('checked', false);
  } else {
    $('.no_parent_dynamic').show();
  }

  if ($('#device_settings_parent_stations').is(':checked')) {
    $('.no_parent_stations').hide();
    $('#device_settings_station_ids').empty();
    $('#device_settings_image_duration').val(15);
  } else {
    $('.no_parent_stations').show();
  }

  if ($('#device_settings_parent_playlist').is(':checked')) {
    $('.no_parent_playlist').hide();
    $('#device_settings_playlist').empty();
  } else {
    $('.no_parent_playlist').show();
  }
}

OB.Device.saveDevice = function () {
  var post = {};
  if ($('#device_settings_id').val() != 'new') post.id = $('#device_settings_id').val();

  post.name = $('#device_settings_name').val();
  post.description = $('#device_settings_description').val();
  post.password = $('#device_settings_password').val();
  post.ip_address = $('#device_settings_ip').val();

  post.support_audio = $('#device_settings_support_audio').is(':checked');
  post.support_video = $('#device_settings_support_video').is(':checked');
  post.support_images = $('#device_settings_support_image').is(':checked');
  post.support_linein = $('#device_settings_support_linein').is(':checked');

  post.timezone = $('#device_settings_timezone').val();
  post.station_ids = $('#device_settings_station_ids').val();
  post.station_id_image_duration = $('#device_settings_image_duration').val();
  if ($('#device_settings_playlist').val().length != 0) post.default_playlist = $('#device_settings_playlist').val()[0];

  if ($('#device_settings_parent_id').val() != "") post.parent_device_id = $('#device_settings_parent_id').val();
  post.use_parent_dynamic = $('#device_settings_parent_dynamic').is(':checked');
  post.use_parent_schedule = $('#device_settings_parent_schedule').is(':checked');
  post.use_parent_ids = $('#device_settings_parent_stations').is(':checked');
  post.use_parent_playlist = $('#device_settings_parent_playlist').is(':checked');
  post.use_parent_emergency = $('#device_settings_parent_priority').is(':checked');

  OB.API.post('device', 'edit', post, function (response) {
    if (!response.status) {
      $('#device_edit_message').obWidget('error', response.msg);
    } else {
      OB.UI.closeModalWindow();
      OB.Device.settings();

      $('#device_main_message').obWidget('success', response.msg);
    }
  })
}

OB.Device.deleteDevice = function () {
  var dev_id = $('#device_settings_id').val();

  //T Are you sure you want to remove this device?
  OB.UI.confirm({
    text: "Are you sure you want to remove this device?",
    okay_class: "delete",
    callback: function () {
      OB.Device.deleteDeviceConfirm(dev_id);
    }
  })
}

OB.Device.deleteDeviceConfirm = function (dev_id) {
  var post = {};
  post.id = dev_id;

  OB.API.post('device', 'delete', post, function (response) {
    OB.UI.closeModalWindow();
    OB.Device.settings();

    if (!response.status) {
      $('#device_main_message').obWidget('error', response.msg);
    }
  });
}

/* =========================
   PLAYER MONITORING SECTION
========================= */

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
    $('#monitor_filter_operator option[value="like"]').hide();
    $('#monitor_filter_operator option[value="not_like"]').hide();
  }

  else
  {
    $('#monitor_filter_operator option[value="like"]').show();
    $('#monitor_filter_operator option[value="not_like"]').show();
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
  $('#monitor_download_csv').hide();

  var date_start = $('#monitor_date_start').val();
  var date_end = $('#monitor_date_end').val();

  // validate our start / end date
  var date_regexp = /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/;
  if(!date_regexp.test(date_end) || !date_regexp.test(date_start))
  {
    //T The start or end date is invalid.
    $('#monitor_message').obWidget('error','The start or end date is invalid.');
    return;
  }

  var date_start_array = date_start.split('-');
  var date_end_array = date_end.split('-');

  var date_start_object = new Date(date_start_array[0],(date_start_array[1]-1),date_start_array[2],0,0,0);
  var date_end_object = new Date(date_end_array[0],(date_end_array[1]-1),date_end_array[2],23,59,59);

  if(date_start_object > date_end_object)
  {
    //T The start date must be before the end date.
    $('#monitor_message').obWidget('error','The start date must be before the end date.');
    return;
  }

  var fields = new Object();
  fields.limit = 1000;
  fields.offset = 0;
  fields.orderby = $('#monitor_orderby').val();
  fields.orderdesc = $('#monitor_orderdesc').val();

  fields.device_id = $('#monitor_device_select').val();

  fields.date_start = date_start;
  fields.date_end = date_end;

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

  $('#monitor_message').hide();

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

        var $tr = $('<tr/>');
        $tr.append( $('<td/>').text(row.media_id) );
        $tr.append( $('<td/>').text(row.artist) );
        $tr.append( $('<td/>').text(row.title) );
        $tr.append( $('<td/>').text(row.datetime) );
        $tr.append( $('<td/>').text(row.context) );
        $tr.append( $('<td/>').text(row.notes) );

        $('#monitor_results_table tbody').append($tr);

      });

    }

    $('#monitor_results_container').show();
    if(total_rows>0)
    {
      $('#monitor_download_csv').show();
        var data = new Blob([data.data.csv], { type: 'application/octect-stream' });
        var url = URL.createObjectURL(data);
        var filename = 'player_monitor.csv';
        $('#monitor_download_csv').attr({
          href: url,
          download: filename
        });
    }

  });

}
