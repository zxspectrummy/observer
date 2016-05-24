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

// get the media format settings.
OB.Media.settings = function()
{
  $('.sf-submenu').hide();

  OB.API.post('media','formats_get',{ }, function(data) {

    OB.UI.replaceMain('media/settings.html');
    
    formats = data.data;

    var video = formats.video_formats;
    var image = formats.image_formats;
    var audio = formats.audio_formats;

    for(var i in video)
    {
      $('.video_formats[value='+video[i]+']').attr('checked',true);
    }

    for(var i in audio)
    {
      $('.audio_formats[value='+audio[i]+']').attr('checked',true);
    }

    for(var i in image)
    {
      $('.image_formats[value='+image[i]+']').attr('checked',true);
    }

    OB.Media.categoriesGet();

  });

}

OB.Media.genresGet = function()
{

  OB.API.post('settings','genre_list',{},function(response)
  {

    if(!response.status) return;
  

    genres = response.data;

    if(genres.length==0)
    {
      $('#media_genres').html('<td colspan="3" >'+OB.t('Media Settings','No Genres')+'</td>');
      return;
    }

    $.each(genres,function(index,genre) 
    {
      var $tr = $('<tr class="hidden" id="media_genre_'+genre.id+'" data-category_id='+genre.media_category_id+'></tr>');
      $tr.append('<td>'+htmlspecialchars(genre.name)+'</td>');
      $tr.append('<td>'+htmlspecialchars(genre.description)+'</td>');
      $tr.append('<td><a href="javascript:OB.Media.genreAddeditWindow('+genre.id+');" data-t data-tns="Common">Edit</a></td>');

      // place after category name, or last item of that category id.
      if($('#media_categories tr[data-category_id='+genre.media_category_id+']').length) $('#media_categories tr[data-category_id='+genre.media_category_id+']').last().after($tr.outerHTML());
      else $('#media_category_'+genre.media_category_id).after($tr.outerHTML());

      $('#media_genre_'+genre.id).data('details',genre);
    });

    OB.UI.translateHTML($('#media_categories'));

  });   

}

OB.Media.genresToggle = function(catid)
{

  if(!$('#media_category_'+catid).length) return;
  
  var expanded = $('#media_category_'+catid).attr('data-expanded');

  if(expanded=='true') 
  {
    $('#media_categories tr[data-category_id='+catid+']').hide();
    $('#media_category_'+catid).attr('data-expanded','false');
  }
  
  else
  {
    $('#media_categories tr[data-category_id='+catid+']').show();
    $('#media_category_'+catid).attr('data-expanded','true');
  }

}

OB.Media.genreAddeditWindow = function(id)
{

  if(id)
  {
    var genre = $('#media_genre_'+id).data('details');
    if(!genre) return;
  }

  OB.UI.openModalWindow('media/genre_addedit.html');

  var categories = $('#media_categories').data('categories');
  $.each(categories,function(index,category)
  {
    $('#genre_categories').append('<option value="'+category.id+'">'+htmlspecialchars(category.name)+'</option>');
  });

  if(id) 
  {
    $('#genre_addedit_heading').text(OB.t('Genre Edit','Edit Genre'));
    $('#genre_name').val(genre.name);
    $('#genre_description').val(genre.description);
    $('#genre_categories').val(genre.media_category_id);
    $('#genre_addedit_id').val(id);
  }
  else
  {
    $('#genre_addedit_heading').text(OB.t('Genre Edit','New Genre'));
    $('.edit_only').hide();
  }
/*  else $('legend').text('New Heading');*/

}

OB.Media.genreSave = function()
{

  var postfields = new Object();
  postfields.id = $('#genre_addedit_id').val();
  postfields.name = $('#genre_name').val();
  postfields.description = $('#genre_description').val();
  postfields.media_category_id = $('#genre_categories').val();

  OB.API.post('settings','genre_save',postfields,function(data)
  {

    if(data.status==true)
    {
      OB.UI.closeModalWindow();
      OB.Media.categoriesGet();

      $('#media_categories_message').obWidget('success',['Genre Edit','Genres Updated Message']);
    }

    else
    {
      $('#genre_addedit_message').obWidget('error', data.msg);
    }

  });

}

OB.Media.genreDelete = function(confirm)
{
  if(confirm){

    OB.API.post('settings','genre_delete', {'id': $('#genre_addedit_id').val()}, function(data)
    {
      if(data.status==false)
        {
          $('#media_categories_message').obWidget('error',data.msg);
        }
      else
      {
        OB.UI.closeModalWindow();
        OB.Media.categoriesGet();
        $('#media_categories_message').obWidget('success',['Genre Edit','Genre Deleted Message']);
      }
    });

  } else
  {
    OB.UI.confirm(
      ['Genre Edit', 'Delete Genre Confirm'],
      function(){OB.Media.genreDelete(true); },
      ['Common','Yes Delete'],
      ['Common','No Cancel'],
      'delete'
    )
  }

}

OB.Media.categoriesGet = function()
{

  $('#media_categories').html('');

  OB.API.post('settings','category_list',{},function(response)
  {

    if(!response.status) return;
  
    categories = response.data;

    $('#media_categories').data('categories',categories);

    if(categories.length==0)
    {
      $('#media_categories').html('<td colspan="3">No categories found.</td>');
      return;
    }

    var edit_button_text = OB.t('Common','Edit');
    $.each(categories,function(index,category) 
    {
      var $tr = $('<tr data-expanded="false" id="media_category_'+category.id+'"></tr>');
      $tr.append('<td colspan="2"><a href="javascript: OB.Media.genresToggle('+category.id+');">'+htmlspecialchars(category.name)+'</a></td>');
      $tr.append('<td><a href="javascript:OB.Media.categoryAddeditWindow('+category.id+');" >'+edit_button_text+'</a></td>');

      $('#media_categories').append($tr.outerHTML());

      $('#media_category_'+category.id).data('details',category);
    });

    OB.Media.genresGet();

  });

}

OB.Media.categoryAddeditWindow = function(id)
{

  if(id)
  {
    var category = $('#media_category_'+id).data('details');
    if(!category) return;
  }

  OB.UI.openModalWindow('media/category_addedit.html');

  if(id) 
  {
    $('#category_addedit_heading').text(OB.t('Category Edit','Edit Category'));
    $('#category_name_input').val(category.name);
    $('#category_addedit_id').val(id);
  }
  else
  {
    $('#category_addedit_heading').text(OB.t('Category Edit','New Category'));
    $('#layout_modal_window .edit_only').hide();
  }


}

OB.Media.categorySave = function()
{

  var postfields = new Object();
  postfields.id = $('#category_addedit_id').val();
  postfields.name = $('#category_name_input').val();

  OB.API.post('settings','category_save',postfields,function(response)
  {

    if(response.status==false) { $('#category_addedit_message').obWidget('error',response.msg); }
    else
    {
      OB.UI.closeModalWindow();
      OB.Media.categoriesGet();
      $('#media_categories_message').obWidget('success',['Category Edit','Saved Message']);
    }

  });
}

OB.Media.categoryDelete = function(confirm)
{

  var cat_id = $('#category_addedit_id').val();
  var cat_name = $('#media_categories #media_category_'+cat_id+' td a').first().text();

  if(!confirm)
  {
    OB.UI.confirm('Are you sure you want to delete this category ('+cat_name+')?)',function() { OB.Media.categoryDelete(true); }, ['Common','Yes Delete'], ['Common','No Cancel'], 'delete');
  }  

  else
  {
    OB.API.post('settings','category_delete', {'id': cat_id}, function(response)
    {
      if(response.status==false) { $('#category_addedit_message').obWidget('error',response.msg); }
      else
      {
        OB.UI.closeModalWindow();
        OB.Media.categoriesGet();
        $('#media_categories_message').obWidget('success',['Category Edit','Category Deleted Message']);
      }
    });
  }

}

// save new media format settings.
OB.Media.formatsSave = function()
{

  var audio_formats = $('.audio_formats:checked').map(function(i,n) { return $(n).val(); }).get();
  var image_formats = $('.image_formats:checked').map(function(i,n) { return $(n).val(); }).get();
  var video_formats = $('.video_formats:checked').map(function(i,n) { return $(n).val(); }).get();

  OB.API.post('media','formats_save', { 'audio_formats': audio_formats, 'video_formats': video_formats, 'image_formats': image_formats },function(data) {
    $('#formats_message').obWidget('success',data.msg);
  });

}
