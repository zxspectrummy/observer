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


// media add/edit: copy field to all other media we are adding/editing.
OB.Media.copyField = function(button)
{

  var field_class = $(button).attr('data-field');
  var value = $(button).parent().find('.'+field_class).val();

  if(field_class == 'category_field')
  {
    $('.media_addedit').each(function(index,element)
    {
      var $field = $(element).find('.'+field_class);
      if($field.val()!=value)
      {
        $field.val(value);
        OB.Media.updateGenreList($(element).attr('data-id'));
      }
    });
  }

  else $('.media_addedit .'+field_class).val(value);

}

// media add/edit: get our data from EXIF/ID3 and copy it to our field values.
OB.Media.mediaInfoImport = function(button)
{

  var id = $(button).parents('.media_addedit').attr('data-id');

  if(typeof(OB.Media.media_info[id])!='undefined' && typeof(OB.Media.media_info[id].comments)!='undefined') {

    $form = $('#media_addedit_'+id)

    if(typeof OB.Media.media_info[id].comments.artist != 'undefined') $form.find('.artist_field').val(OB.Media.media_info[id].comments.artist[0]);
    if(typeof OB.Media.media_info[id].comments.album != 'undefined') $form.find('.album_field').val(OB.Media.media_info[id].comments.album[0]);
    if(typeof OB.Media.media_info[id].comments.title != 'undefined') $form.find('.title_field').val(OB.Media.media_info[id].comments.title[0]);
    if(typeof OB.Media.media_info[id].comments.comments != 'undefined') $form.find('.comments_field').val(OB.Media.media_info[id].comments.comments[0]);
  }

}

OB.Media.editCancel = function(button)
{

  var id = $(button).parents('.media_addedit').attr('data-id');

  $(button).parents('.media_addedit').remove();
  if(OB.Media.media_uploader_xhr[id]) OB.Media.media_uploader_xhr[id].abort();

  OB.Media.media_uploader_uploading = false;
  OB.Media.mediaUploaderUpload(); //process next in queue if available.

  if($('#media_data_middle').html()=='') $('#media_data').hide(); 
}

OB.Media.editToggle = function(button)
{

  $container = $(button).parents('.media_addedit').find('.addedit_form_container');

  if($container.is(':visible')==true)
  {
    $container.slideUp('fast');
    $(button).text(OB.t('Common','Expand'));
  }

  else
  {
    $container.slideDown('fast');
    $(button).text(OB.t('Common','Collapse'));
  }

}

OB.Media.mediaAddeditForm = function(id,title)
{

  var form = OB.UI.getHTML('media/addedit_form.html');

  $('#media_data_middle').append('<div id="media_addedit_'+id+'" class="media_addedit" data-id="'+id+'">'+form+'</div>');

  var $form = $('#media_addedit_'+id);
  $form.find('.addedit_form_title').text(title);

  // fill category list
  for(var i in OB.Settings.categories)
  {
    $form.find('.category_field').append('<option value="'+OB.Settings.categories[i].id+'">'+htmlspecialchars(OB.t('Media Categories',OB.Settings.categories[i].name))+'</option>');
  }

  // fill language list
  for(var i in OB.Settings.languages)
  {
    $form.find('.language_field').append('<option value="'+OB.Settings.languages[i].id+'">'+htmlspecialchars(OB.t('Media Languages',OB.Settings.languages[i].name))+'</option>');
  }

  // fill country list
  for(var i in OB.Settings.countries)
  {
    $form.find('.country_field').append('<option value="'+OB.Settings.countries[i].id+'">'+htmlspecialchars(OB.t('Media Countries',OB.Settings.countries[i].name))+'</option>');
  }

  // tie together genre list with category list on change
  $form.find('.category_field').change(function() { OB.Media.updateGenreList(id); });
  OB.Media.updateGenreList(id);

  // process HTML widgets/strings
  OB.UI.widgetHTML( $('#media_data_middle') );
  OB.UI.translateHTML( $('#media_data_middle') );

  // one or more elements have visibility depending on permissions. call our update function to adjust this.
  OB.UI.permissionsUpdate();

}

OB.Media.updateGenreList = function(id)
{

  var $form = $('#media_addedit_'+id);

  var selected_category = $form.find('.category_field').val();

  $form.find('.genre_field option').remove();

  // fill genre list
  for(var i in OB.Settings.genres)
  {
    if(OB.Settings.genres[i].media_category_id == selected_category)
      $form.find('.genre_field').append('<option value="'+OB.Settings.genres[i].id+'">'+htmlspecialchars(OB.t('Media Genres',OB.Settings.genres[i].name))+'</option>');
  }
}

OB.Media.media_uploader_count = 0;
OB.Media.media_uploader_queue = [];
OB.Media.media_uploader_uploading_count = 0;
OB.Media.media_uploader_uploading = false;
OB.Media.media_uploader_xhr = {};

OB.Media.mediaUploader = function()
{

  $('#media_upload_file_field').change(function () {

    $.each($(this)[0].files,function(index,file)
    {
      OB.Media.media_uploader_count++;
      OB.Media.media_uploader_queue.push(file);
      OB.Media.mediaAddeditForm(OB.Media.media_uploader_count,file.name);
    });

    OB.Media.mediaUploaderUpload();

  });

  $.event.props.push('dataTransfer');

  $('#media_upload_form').bind('dragenter', OB.Media.mediaUploaderNoop);
  $('#media_upload_form').bind('dragexit', OB.Media.mediaUploaderNoop);
  $('#media_upload_form').bind('dragover', OB.Media.mediaUploaderNoop);

  $('#media_upload_form').bind('drop',function(event)
  {
    event.stopPropagation();
    event.preventDefault();

    // not sure why 'files' is empty in firefox?
    var files = event.dataTransfer.files;

    if(files.length==0) OB.UI.alert( ['Upload Media','File Drop Failed'] );

    $.each(files,function(index,file)
    {
      OB.Media.media_uploader_count++;
      OB.Media.media_uploader_queue.push(file);
      OB.Media.mediaAddeditForm(OB.Media.media_uploader_count,file.name);
    });

    OB.Media.mediaUploaderUpload();
  });

}

OB.Media.mediaUploaderNoop = function(event)
{
  event.stopPropagation();
  event.preventDefault();
}

OB.Media.mediaUploaderUpload = function()
{

  // already uploading? wait for last upload to finish (this will get called again).
  if(OB.Media.media_uploader_uploading) return;

  // nothing to upload?
  if(!OB.Media.media_uploader_queue.length) return;

  var file_data = OB.Media.media_uploader_queue.shift();

  OB.Media.media_uploader_uploading_count++;
  OB.Media.media_uploader_uploading = true;

  $.ajax({
      url: '/upload.php',
      type: 'POST',
      xhr: function() {
          myXhr = $.ajaxSettings.xhr();
          if(myXhr.upload)
          {
              myXhr.upload.addEventListener('progress',OB.Media.mediaUploaderProgress, false);
          }

          OB.Media.media_uploader_xhr[OB.Media.media_uploader_uploading_count] = myXhr;

          return myXhr;
      },
      data: file_data,
      cache: false,
      contentType: false,
      processData: false,
      complete : OB.Media.mediaUploaderComplete
  });

  $('#media_data').show();

}

OB.Media.mediaUploaderProgress = function(progress)
{
  var percent = Math.floor((progress.loaded/progress.total)*100);
  $('#media_addedit_'+OB.Media.media_uploader_uploading_count).find('.new_media_status').text(OB.t('Upload Media','Upload Status',[percent]));
}

OB.Media.mediaUploaderComplete = function(xhr)
{

  // set our 'uploading' status to false
  OB.Media.media_uploader_uploading = false;

  if(!xhr.responseText) return; // no response? probably cancelled.

  var res = $.parseJSON(xhr.responseText);
  var id = OB.Media.media_uploader_uploading_count;
  var $form = $('#media_addedit_'+id);

  if(res.error)
  {
    var filename = $form.find('.addedit_form_title').text();
    OB.UI.alert(filename+': '+res.error);
    OB.Media.editCancel(id);
  }

  else
  {
    OB.Media.media_info[id] = res.info;

    $form.find('.upload_file_id').val(res.file_id);
    $form.find('.upload_file_key').val(res.file_key);

    if(typeof(res.info.comments) != 'undefined' && res.info.comments) 
    {
      $form.find('.new_media_status').hide();
      $form.find('.use_id3_button').show();
    }

    else if(res.media_supported) $form.find('.new_media_status').text(OB.t('Upload Media','ID3 Unavailable'));  
  }

  if(!res.media_supported) { 
    $form.find('.addedit_form_message').obWidget('warning',['Edit Media Form','Format Not Supported']);
    $form.find('.new_media_only').hide();
  }

  // call the uploader again in case we have something left in the queue.
  OB.Media.mediaUploaderUpload();
}

// media add/edit: save media
OB.Media.save = function()
{

  if(OB.Media.media_uploader_uploading) { OB.UI.alert( ['Upload Media','Wait For Upload To Save'] ); return; }

  var media_array = new Array();

  $('.media_addedit').each( function(index, element) {

    var item = new Object();
    item.local_id = $(element).attr('data-id');
    var local_id = item.local_id;

    if($(element).attr('data-edit')) item.id = $(element).attr('data-id');

    item.artist = $(element).find('.artist_field').val();
    item.title = $(element).find('.title_field').val();
    item.album = $(element).find('.album_field').val();
    item.year = $(element).find('.year_field').val();

    item.country_id = $(element).find('.country_field').val();
    item.category_id = $(element).find('.category_field').val();
    item.language_id = $(element).find('.language_field').val();
    item.genre_id = $(element).find('.genre_field').val();

    item.comments = $(element).find('.comments_field').val();

    item.is_copyright_owner = $(element).find('.copyright_field').val();
    item.is_approved = $(element).find('.approved_field').val();
    item.status = $(element).find('.status_field').val();
    item.dynamic_select = $(element).find('.dynamic_select_field').val();

    item.file_id = $(element).find('.upload_file_id').val();
    item.file_key = $(element).find('.upload_file_key').val();

    media_array.push(item);
  
  });

  $('#media_top_message').hide();
  $('#media_data .addedit_form_message').hide();

  OB.API.post('media','edit',{ 'media': media_array }, function(data) { 

    // one or more validation errors.
    if(data.status==false)
    {

      var validation_errors = data.data;

      // single error (not array), no specific item.
      if(!validation_errors)
      {
        $('#media_top_message').obWidget('error',data.msg);
      }

      else
      {
        for(var i in validation_errors)
        {
          $('#media_addedit_'+validation_errors[i][1]).find('.addedit_form_message').obWidget('error',OB.t('Edit Media Form',validation_errors[i][2]));
        }
      }

    }

    // update/new complete, no errors.
    else
    {
      $('#media_data_middle').html('');
      $('#media_upload_form').hide();
      $('#media_data').hide();
      $('#media_top_instructions').hide();
      $('#media_top_message').obWidget('success',['Edit Media Form','Media Saved']);
      OB.Sidebar.mediaSearch(); // reload our sidebar media search - maybe it needs updating.
    } 
  });

}

// media add/edit: edit page
OB.Media.editPage = function()
{

  var items_selected = false;

  OB.Media.media_uploading = 0; // reset media upload counter.
  OB.Media.media_info = new Array(); // reset 

  OB.UI.replaceMain('media/addedit.html');
  $('#media_heading').text(OB.t('Edit Media','Heading'));
  $('#media_top_instructions').text(OB.t('Edit Media','Instructions'));

  $('#media_upload_container').hide();

  $('#media_data').show();

  $('.sidebar_search_media_selected').each(function(index,element) { 

    var local_id = $(element).attr('data-id');

    OB.Media.mediaAddeditForm(local_id,$(element).attr('data-artist')+' - '+$(element).attr('data-title'));

    $('#upload_'+local_id+'_data_container').attr('data-id',local_id); // id is ID in database, it being set means we are editing existing data.

    $form = $('.media_addedit').last();

    $form.attr('data-edit',1);

    $form.find('.artist_field').val( $(element).attr('data-artist') );
    $form.find('.title_field').val( $(element).attr('data-title') );
    $form.find('.album_field').val( $(element).attr('data-album') );
    $form.find('.year_field').val( $(element).attr('data-year') );

    $form.find('.category_field').val( $(element).attr('data-category_id') );
    OB.Media.updateGenreList(local_id);

    $form.find('.country_field').val( $(element).attr('data-country_id') );
    $form.find('.language_field').val( $(element).attr('data-language_id') );
    $form.find('.genre_field').val( $(element).attr('data-genre_id') );

    $form.find('.comments_field').val( $(element).attr('data-comments') );

    $form.find('.copyright_field').val( $(element).attr('data-is_copyright_owner') );
    $form.find('.status_field').val( $(element).attr('data-public_status') );
    $form.find('.dynamic_select_field').val( $(element).attr('data-dynamic_select') );

    if($(element).attr('data-status')=='approved') $form.find('.approved_field').val(1);
    else $form.find('.approved_field').val(0);

    items_selected = true;

  });

  $('.new_media_only').hide();

}

// media add/edit: upload page
OB.Media.uploadPage = function()
{

  OB.Media.media_uploading = 0; // reset media upload counter.
  OB.Media.media_info = new Array(); // reset 

  OB.UI.replaceMain('media/addedit.html');
  OB.Media.mediaUploader();

}

