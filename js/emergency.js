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

OB.Emergency = new Object();

OB.Emergency.init = function()
{
  OB.Callbacks.add('ready',-4,OB.Emergency.initMenu);
}

OB.Emergency.initMenu = function()
{
  //T Priority Broadcasts
  OB.UI.addSubMenuItem('schedules', 'Priority Broadcasts', 'emergency', OB.Emergency.emergency, 30, 'manage_emergency_broadcasts');
}

OB.Emergency.device_id = null;

OB.Emergency.emergency = function()
{

  OB.UI.replaceMain('emergency/emergency.html');

  OB.Emergency.device_id = null;

  OB.Emergency.emergencyInit();

  $('#emergency_list_container').droppable({
      drop: function(event, ui) {

        if($(ui.draggable).attr('data-mode')=='media')
        {
          //T You can schedule only one item at a time.
          if($('.sidebar_search_media_selected').length!=1) { OB.UI.alert('You can schedule only one item at a time.'); return; }

          var item_id = $('.sidebar_search_media_selected').first().attr('data-id');
          var item_name = $('.sidebar_search_media_selected').first().attr('data-artist')+' - '+$('.sidebar_search_media_selected').first().attr('data-title');
          var item_type = $('.sidebar_search_media_selected').first().attr('data-type');

          var item_duration = $('.sidebar_search_media_selected').first().attr('data-duration');

          OB.Emergency.addEmergency(item_id,item_name,item_type);

        }

        else if($(ui.draggable).attr('data-mode')=='playlist')
        {

          //T Priority broadcast playlists are not supported at this time.
          OB.UI.alert('Priority broadcast playlists are not supported at this time.');

        }



      }

  });

}

OB.Emergency.emergencyInit = function()
{

  var post = [];
  post.push(['device','device_list', {}]);
  post.push(['emergency','emergencies_get_last_device', {}]);

  OB.API.multiPost(post, function(responses)
  {

    var devices = responses[0].data;
    var last_device = responses[1];

    $.each(devices,function(index,item) {

      if(item.use_parent_emergency=='1') return; // device uses parent emergency broadcasts, setting them here would not do anything.

      // make sure we have permission for this
      if(OB.Settings.permissions.indexOf('manage_emergency_broadcasts')==-1 && OB.Settings.permissions.indexOf('manage_emergency_broadcasts:'+item.id)==-1) return;


      if(OB.Emergency.device_id==null) OB.Emergency.device_id = item.id; // default to first device.
      $('#emergency_device_select').append('<option value="'+item.id+'">'+htmlspecialchars(item.name)+'</option>');

    });

    if(last_device.status && $('#emergency_device_select option[value='+last_device.data+']').length)
    {
      $('#emergency_device_select').val(last_device.data);
      OB.Emergency.device_id = last_device.data;
    }

    OB.Emergency.loadEmergencies();

  });

}

OB.Emergency.deviceChange = function()
{

  OB.Emergency.device_id = $('#emergency_device_select').val();
  OB.Emergency.loadEmergencies();

}

OB.Emergency.loadEmergencies = function()
{

  var post = [];
  post.push(['emergency','emergencies',{ 'device_id': OB.Emergency.device_id }]);
  post.push(['emergency','emergencies_set_last_device', { 'device': OB.Emergency.device_id}]);

  OB.API.multiPost(post, function(responses)
  {

    if(responses[0].status==true)
    {

      var emergencies = responses[0].data;

      $('#emergency_list tbody').children().not('#emergency_table_empty').remove();

      if($(emergencies).length>0)
      {

        $('#emergency_table_empty').hide();

        $.each(emergencies,function(index,data)
        {

          if(data.duration) var duration = Math.round(data.duration)+' seconds';
          else var duration = '';

          $('#emergency_list tbody').append('<tr id="emergency_'+data.id+'"><td>'+htmlspecialchars(data.name)+'</td></td><td>'+format_timestamp(data.start)+'</td><td>'+format_timestamp(data.stop)+'</td><td>'+data.frequency+' '+OB.t("seconds")+'</td><td>'+secsToTime(data.duration)+'</td><td>'+htmlspecialchars(data.item_name)+'</td>');

          $('#emergency_'+data.id).dblclick(function(eventObj)
          {
            OB.Emergency.editEmergency(data.id);
          });

        });

      }

      else $('#emergency_table_empty').show();

    }

  });

}

OB.Emergency.saveEmergency = function()
{

  fields = new Object();

  fields.name = $('#emergency_name').val();

  fields.device_id = OB.Emergency.device_id;

  fields.frequency = $('#emergency_frequency').val();
  fields.duration = parseInt($('#emergency_duration_minutes').val()*60) + parseInt($('#emergency_duration_seconds').val());

  var start_date_array = $('#emergency_start_date').val().split('-');
  var start_time_array = $('#emergency_start_time').val().split(':');
  var start_time = new Date(start_date_array[0],start_date_array[1]-1,start_date_array[2],start_time_array[0],start_time_array[1],start_time_array[2],0);
  fields.start = Math.round(start_time.getTime()/1000)+'';

  var stop_date_array = $('#emergency_stop_date').val().split('-');
  var stop_time_array = $('#emergency_stop_time').val().split(':');
  var stop_time = new Date(stop_date_array[0],stop_date_array[1]-1,stop_date_array[2],stop_time_array[0],stop_time_array[1],stop_time_array[2],0);
  fields.stop = Math.round(stop_time.getTime()/1000)+'';

  fields.id = $('#emergency_id').val();
  fields.item_id = $('#emergency_item_id').val();

  OB.API.post('emergency','save_emergency',fields,function(data)
  {

    if (data.status == true)
    {
      OB.UI.closeModalWindow();
      OB.Emergency.loadEmergencies();

    } else
    {
      $('#emergency_addedit_message').obWidget('error', data.msg);
    }

  });

}

OB.Emergency.addeditEmergencyWindow = function()
{
  OB.UI.openModalWindow('emergency/addedit.html');

  // friendly date picker
  $('#emergency_start_date').datepicker({ dateFormat: "yy-mm-dd" });
  $('#emergency_stop_date').datepicker({ dateFormat: "yy-mm-dd" });

  // friendly time picker
  $('#emergency_start_time').timepicker({timeFormat: 'hh:mm:ss',showSecond: true});
  $('#emergency_stop_time').timepicker({timeFormat: 'hh:mm:ss',showSecond: true});
}

OB.Emergency.editEmergency = function(id)
{

  OB.API.post('emergency','emergencies',{ 'device_id': OB.Emergency.device_id, 'id': id }, function(data)
  {

    if(data.status==true)
    {

      emerg = data.data;

      OB.Emergency.addeditEmergencyWindow();
      $('.edit_only').show();

      if(emerg.item_type=='image')
      {
        $('#emergency_duration').show();

        var duration = Math.round(emerg.duration);
        var duration_seconds = duration%60;
        var duration_minutes = (duration - duration_seconds)/60;

        $('#emergency_duration_minutes').val(duration_minutes);
        $('#emergency_duration_seconds').val(duration_seconds);
      }
      else $('#emergency_duration').hide();

      $('#emergency_item_info').text(emerg.item_name);
      $('#emergency_name').val(emerg.name);
      $('#emergency_frequency').val(emerg.frequency);
      $('#emergency_item_id').val(emerg.item_id);
      $('#emergency_id').val(emerg.id);

      var start_time = new Date(parseInt(emerg.start)*1000);
      var stop_time = new Date(parseInt(emerg.stop)*1000);

      $('#emergency_start_date').val(start_time.getFullYear()+'-'+timepad(start_time.getMonth()+1)+'-'+timepad(start_time.getDate()));
      $('#emergency_start_time').val(timepad(start_time.getHours())+':'+timepad(start_time.getMinutes())+':'+timepad(start_time.getSeconds()));

      $('#emergency_stop_date').val(stop_time.getFullYear()+'-'+timepad(stop_time.getMonth()+1)+'-'+timepad(stop_time.getDate()));
      $('#emergency_stop_time').val(timepad(stop_time.getHours())+':'+timepad(stop_time.getMinutes())+':'+timepad(stop_time.getSeconds()));

    }

    else OB.UI.alert(data.msg);

  });

}

OB.Emergency.addEmergency = function(item_id,item_name,item_type)
{

  OB.Emergency.addeditEmergencyWindow();
  $('.edit_only').hide();

  $('#emergency_item_id').val(item_id);
  $('#emergency_item_info').text(item_name);

  if(item_type=='image') $('#emergency_duration').show();
  else $('#emergency_duration').hide();

}

OB.Emergency.deleteEmergency = function(confirm)
{

  if(confirm)
  {

    OB.API.post('emergency','delete_emergency',{ 'id': $('#emergency_id').val() }, function(data)
    {

      if(data.status==true)
      {
        OB.UI.closeModalWindow();
        OB.Emergency.loadEmergencies();
      }

      else
      {
        $('#emergency_addedit_message').obWidget('error',data.msg);
      }

    });

  }

  else
  {
    //T Are you sure you want to delete this priority broadcast?
    //T Yes, Delete
    //T No, Cancel
    OB.UI.confirm(
      'Are you sure you want to delete this priority broadcast?',
      function() { OB.Emergency.deleteEmergency(true); },
      'Yes, Delete',
      'No, Cancel',
      'delete'
    );
  }

}
