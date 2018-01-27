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

OB.Sidebar = new Object();

OB.Sidebar.init = function()
{
  OB.Callbacks.add('ready',-5,OB.Sidebar.sidebarInit);
}

OB.Sidebar.sidebarInit = function()
{

  if(parseInt(OB.Account.userdata.sidebar_display_left))
  {
    $('#main_container').addClass('sidebar_display_left');
  }

  $('#sidebar_player').droppable({
    drop: function(event, ui) {

      var mode = $(ui.draggable).attr('data-mode');
      var type = $(ui.draggable).attr('data-type');
      var id = $(ui.draggable).attr('data-id');

      if(!mode || !type || !id) return;

      OB.Sidebar.playerPlay(mode,type,id);

    }

  });

  $('#sidebar_search_media_input').keyup(

      function (event) {

        // cancel advanced search (if applicable)
        OB.Sidebar.advanced_search_filters = null;

        if(event.keyCode=='13') {
          $.doTimeout('media_search_timeout'); // cancel timeout function below.
          OB.Sidebar.mediaSearch();
        }
        else $.doTimeout('media_search_timeout',750, function() { OB.Sidebar.mediaSearch(); });
      });

  $('#sidebar_search_playlist_input').keyup(
      function () {
        $.doTimeout('playlist_search_timeout',750, function() { OB.Sidebar.playlistSearch(); });
      });

  OB.Sidebar.playlistEditDeleteVisibility();
  OB.Sidebar.mediaSearchFilter('approved');
  OB.Sidebar.playlistSearch();
}

OB.Sidebar.playerPlay = function(mode,type,id)
{

  if(mode=='playlist')
  {
    OB.UI.alert('Playlist preview coming soon.');
  }

  else
  {

    type = type.toLowerCase();

    image_width = $('#sidebar_player_draghere').innerWidth();
    image_height = $('#sidebar_player_draghere').innerHeight();

    // a little extra info so we can select an appropriate format for video or audio.
    $.browser.chrome = /chrome/.test(navigator.userAgent.toLowerCase());

    if(type=='video')
    {
      // what format do we need?
      if($.browser.msie || ($.browser.safari && !$.browser.chrome)) var video_format = 'mp4';
      else var video_format = 'ogv';

      $('#sidebar_player_draghere').html('<video preload="auto" autoplay="autoplay" src="/preview.php?id='+id+'&w='+image_width+'&h='+image_height+'&format='+video_format+'" controls="controls"></video>');
    }

    else if(type=='audio')
    {
      // what format do we need?
      if($.browser.msie || ($.browser.safari && !$.browser.chrome)) var audio_format = 'mp3';
      else var audio_format = 'ogg';

      $('#sidebar_player_draghere').html('<audio preload="auto" autoplay="autoplay" src="/preview.php?id='+id+'&format='+audio_format+'" controls="controls"></audio>');
    }

    else if(type=='image')
    {

      $('#sidebar_player_draghere').html('<img src="/preview.php?id='+id+'&w='+image_width+'&h='+image_height+'">');

    }

  }

}

OB.Sidebar.showMediaSearch = function()
{
  $('#sidebar_search_playlist_container').hide();
  $('#sidebar_search_media_container').showFlex();

  /* $('#sidebar_search_tab_playlist').css('z-index',5); */

  $('.sidebar_search_tab').removeClass('selected');
  $('#sidebar_search_tab_media').addClass('selected');

  // TODO this is temporary to fix context menu not loading properly sometimes. real cause should be fixed and this should be removed.
  OB.Sidebar.mediaSearch(true);

  OB.Layout.tableFixedHeaders($('#sidebar_search_media_headings'),$('#sidebar_search_media_results'));
}

OB.Sidebar.showPlaylistSearch = function()
{

  // if we are showing detailed media view, then close back to basic view before switching to playlist view.
  if($('#sidebar_search').hasClass('sidebar_search_detailed')) OB.Sidebar.mediaDetailedToggle();

  $('#sidebar_search_media_container').hide();
  $('#sidebar_search_playlist_container').showFlex();

  /* $('#sidebar_search_tab_playlist').css('z-index',15); */

  $('.sidebar_search_tab').removeClass('selected');
  $('#sidebar_search_tab_playlist').addClass('selected');


  // TODO this is temporary to fix context menu not loading properly sometimes. real cause should be fixed and this should be removed.
  OB.Sidebar.playlistSearch(true);

  OB.Layout.tableFixedHeaders($('#sidebar_search_playlist_headings'),$('#sidebar_search_playlist_results'));
}

OB.Sidebar.mediaDetailedToggle = function()
{

  if($('#sidebar_search').hasClass('sidebar_search_detailed'))
  {
    $('#main_container').removeClass('sidebar_expanded');
    $('#sidebar_search').removeClass('sidebar_search_detailed');
    $('#sidebar_search_media_container').addClass('sidebar_search_media_container_basic');
    $('#sidebar_search_media_container').removeClass('sidebar_search_media_container_detailed');

    // hide detailed columns
    $('.sidebar_search_media_detailed_column').hide();

    // update detailed toggle link
    $('#media_detailed_toggle_text').text(OB.t('Sidebar','more'));

  }

  else {
    $('#main_container').addClass('sidebar_expanded');
    $('#sidebar_search').addClass('sidebar_search_detailed');
    $('#sidebar_search_media_container').removeClass('sidebar_search_media_container_basic');
    $('#sidebar_search_media_container').addClass('sidebar_search_media_container_detailed');

    // show detailed columns
    $('.sidebar_search_media_detailed_column').show();

    // update detailed toggle link
    $('#media_detailed_toggle_text').text(OB.t('Sidebar','less'));
  }

  $('#sidebar_search_media_headings').width($('#sidebar_search_media_results').width());

  OB.Layout.tableFixedHeaders($('#sidebar_search_media_headings'),$('#sidebar_search_media_results'));

}

OB.Sidebar.media_search_filters = new Object();
OB.Sidebar.media_search_filters.mode='approved';
OB.Sidebar.media_search_filters.my=false;
OB.Sidebar.media_search_filters.bookmarked=false;

OB.Sidebar.mediaSearchFilter = function(what)
{

  // group one (can select only one of these)
  if(what=='approved' || what=='unapproved' || what=='archived')
  {
    OB.Sidebar.media_search_filters.mode=what;
  }

  // group two (toggle on and off).
  else
  {
    if(what=='my') OB.Sidebar.media_search_filters.my = !OB.Sidebar.media_search_filters.my;
    else if(what=='bookmarked') OB.Sidebar.media_search_filters.bookmarked = !OB.Sidebar.media_search_filters.bookmarked;
  }

  // set the look of the buttons
  if(OB.Sidebar.media_search_filters.mode=='approved') $('#sidebar_search_media_approved').html(OB.t('Sidebar','Approved Filter Link').toUpperCase());
  else $('#sidebar_search_media_approved').html(OB.t('Sidebar','Approved Filter Link').toLowerCase());

  if(OB.Sidebar.media_search_filters.mode=='unapproved') $('#sidebar_search_media_unapproved').html(OB.t('Sidebar','Unapproved Filter Link').toUpperCase());
  else $('#sidebar_search_media_unapproved').html(OB.t('Sidebar','Unapproved Filter Link').toLowerCase());

  if(OB.Sidebar.media_search_filters.mode=='archived') $('#sidebar_search_media_archived').html(OB.t('Sidebar','Archived Filter Link').toUpperCase());
  else $('#sidebar_search_media_archived').html(OB.t('Sidebar','Archived Filter Link').toLowerCase());

  if(OB.Sidebar.media_search_filters.my==true) $('#sidebar_search_media_my').html(OB.t('Sidebar','My Filter Link').toUpperCase());
  else $('#sidebar_search_media_my').html(OB.t('Sidebar','My Filter Link').toLowerCase());

  if(OB.Sidebar.media_search_filters.bookmarked==true) $('#sidebar_search_media_bookmarked').html('BK');
  else $('#sidebar_search_media_bookmarked').html('bk');

  // reload search
  OB.Sidebar.mediaSearch();
}

OB.Sidebar.mediaSelectAll = function()
{
  $('.sidebar_search_media_result').addClass('sidebar_search_media_selected');
}

OB.Sidebar.mediaSelectNone = function()
{
  $('.sidebar_search_media_result').removeClass('sidebar_search_media_selected');
}

OB.Sidebar.media_last_selected = null;

OB.Sidebar.mediaSelect = function(object,dragging,keypress)
{

  if(keypress==null && !dragging) $('.sidebar_search_media_selected').removeClass('sidebar_search_media_selected');
  else if(keypress==null && dragging && !$(object).hasClass('sidebar_search_media_selected')) $('.sidebar_search_media_selected').removeClass('sidebar_search_media_selected');

  var media_id = $(object).attr('data-id');

  if(!dragging && keypress!='shift' && $('#sidebar_search_media_result_'+media_id).hasClass('sidebar_search_media_selected'))
    $('#sidebar_search_media_result_'+media_id).removeClass('sidebar_search_media_selected');

  else
  {
    if(keypress=='shift')
    {

      var last_selected = $('#sidebar_search_media_result_'+OB.Sidebar.media_last_selected);

      // figure out if we have to move up or down.
      if($(last_selected).parent().children().index($(last_selected)) < $('#sidebar_search_media_result_'+media_id).parent().children().index($('#sidebar_search_media_result_'+media_id))) var shift_down = true;
      else var shift_down = false;

      while(last_selected.attr('data-id')!=media_id)
      {

        if(shift_down) last_selected = $(last_selected).next();
        else last_selected = $(last_selected).prev();

        last_selected.addClass('sidebar_search_media_selected');

      }

    }

    else $('#sidebar_search_media_result_'+media_id).addClass('sidebar_search_media_selected');

    OB.Sidebar.media_last_selected = media_id;

  }

  OB.Sidebar.mediaEditDeleteVisibility();

}

// determine whether user can edit media (id).
OB.Sidebar.mediaCanEdit = function(id)
{

  if(!OB.Settings.permissions) return false;

  if(OB.Settings.permissions.indexOf('manage_media')!=-1) return true;

  else if(OB.Settings.permissions.indexOf('create_own_media')==-1) return false;

  else if($('#sidebar_search_media_result_'+id).attr('data-owner_id')==OB.Account.user_id) return true;

  return false;

}

// adjust the visibility of the media edit / delete buttons.
OB.Sidebar.mediaEditDeleteVisibility = function()
{

  // default to true.
  var visible = true;

  // if there is nothing selected, then we can't edit or delete.
  if($('#sidebar_search_media_results tbody .sidebar_search_media_selected').length==0) visible = false;

  // see if we can edit/delete each item.
  $('#sidebar_search_media_results tbody .sidebar_search_media_selected').each(function(index,element)
  {

    if(!OB.Sidebar.mediaCanEdit($(element).attr('data-id'))) { visible = false; return false; }

  });

  if(visible) 
  { 
    $('#sidebar_media_edit_button').show();
    $('#sidebar_media_delete_button').show();
    if(OB.Sidebar.media_search_filters.mode=='archived') $('#sidebar_media_unarchive_button').show();
  }
  else 
  { 
    $('#sidebar_media_edit_button').hide(); 
    $('#sidebar_media_delete_button').hide();
    $('#sidebar_media_unarchive_button').hide(); 
  }

}

OB.Sidebar.mediaSearchNumResults = function()
{

  var num_results = $('#sidebar_search_media_results').attr('data-num_results');

  // special case for no results.
  if(num_results == 0)
  {
    $('#sidebar_search_media_results tbody').html('<td class="sidebar_search_noresults"></td>');
    $('#sidebar_search_media_results tbody .sidebar_search_noresults').text(OB.t('Sidebar','No Media Found'));
  }

  if(num_results==1) num_results_text = OB.t('Sidebar','Media Item Found');
  else num_results_text = OB.t('Sidebar','Media Items Found',num_results);

  $('#sidebar_search_media_footer .results .num_results').html(num_results_text);
  $('#sidebar_search_media_footer .results .dynamic_selection').html('&nbsp;');

}

OB.Sidebar.media_search_offset = 0;

OB.Sidebar.mediaSearchPageNext = function()
{

  var num_results = $('#sidebar_search_media_results').attr('data-num_results');

  if(num_results <= OB.Sidebar.media_search_offset + OB.ClientStorage.get('results_per_page'))
  {
    return;
  }

  OB.Sidebar.media_search_offset += OB.ClientStorage.get('results_per_page');
  OB.Sidebar.mediaSearch(true);

}

OB.Sidebar.mediaSearchPagePrevious = function()
{

  if(OB.Sidebar.media_search_offset == 0) return;

  OB.Sidebar.media_search_offset -= OB.ClientStorage.get('results_per_page');
  if(OB.Sidebar.media_search_offset<0) OB.Sidebar.media_search_offset = 0;
  OB.Sidebar.mediaSearch(true);

}

OB.Sidebar.media_search_sort_by = 'updated';
OB.Sidebar.media_search_sort_dir = 'desc';

OB.Sidebar.mediaSearchSort = function(sortby)
{

  // change direction if already sorting by this column
  if(sortby == OB.Sidebar.media_search_sort_by)
  {
    if(OB.Sidebar.media_search_sort_dir=='asc') OB.Sidebar.media_search_sort_dir = 'desc';
    else OB.Sidebar.media_search_sort_dir = 'asc';
  }

  OB.Sidebar.media_search_sort_by = sortby;

  OB.Sidebar.mediaSearch();

}

// we keep track of the last simple/advanced search so people can toggle between.
OB.Sidebar.media_search_last_simple = false;
OB.Sidebar.media_search_last_advanced = false;

OB.Sidebar.media_search_last_query = false;

OB.Sidebar.mediaSearchMode = function(mode)
{

  if(OB.Sidebar.media_search_last_query)
  {

    if(mode=='simple')
    {
      $('#sidebar_search_media_input').unbind('click');
      $('#sidebar_search_media_input').focus();
      $('#sidebar_search_media_input').attr('placeholder',OB.t('Sidebar','Enter Search Query'));

      if(OB.Sidebar.media_search_last_query.mode=='advanced' && OB.Sidebar.media_search_last_simple) $('#sidebar_search_media_input').val(OB.Sidebar.media_search_last_simple.string);
      OB.Sidebar.advanced_search_filters = null;
      OB.Sidebar.mediaSearch();
    }

    if(mode=='advanced')
    {
      OB.Sidebar.advancedSearchWindow();
    }

  }

}

OB.Sidebar.mediaSearch = function(pagination)
{

  // if not the result of pagination (new search), reset offset to 0
  if(!pagination)
  {
    OB.Sidebar.media_search_offset = 0;
  }

  // generate our search query
  var search_query = Object();

  if(OB.Sidebar.advanced_search_filters==null)
  {
    search_query.mode = 'simple';
    search_query.string = $('#sidebar_search_media_input').val();
  }

  else
  {
    search_query.mode = 'advanced';
    search_query.filters = OB.Sidebar.advanced_search_filters;
    $('#sidebar_search_media_input').val('');
    $('#sidebar_search_media_input').attr('placeholder',OB.t('Sidebar','Advanced Search Query'));
    $('#sidebar_search_media_input').click(function(e) { OB.Sidebar.mediaSearchMode('advanced'); });
  }

  OB.API.post('media','media_search',{ save_history: true, sort_by: OB.Sidebar.media_search_sort_by, sort_dir: OB.Sidebar.media_search_sort_dir, q: search_query, s: OB.Sidebar.media_search_filters.mode, l: OB.ClientStorage.get('results_per_page'), o: OB.Sidebar.media_search_offset, my: OB.Sidebar.media_search_filters.my },function (data)
  {

    // clear out context menus, they are about to be reloaded. (context menus only used for media. but this should be fixed so context menus can be used elsewhere).
    $('.context-menu').remove();

    var media_class = media; // media singleton is needed, but media local variable below overrides.

    var media = data.data.media;
    var num_results = data.data.num_results;

    // update pagination
    if(OB.Sidebar.media_search_offset > 0) var pagination_previous = true;
    else var pagination_previous = false;

    if(num_results > OB.Sidebar.media_search_offset + OB.ClientStorage.get('results_per_page')) { var pagination_next = true; }
    else var pagination_next = false;

    if(pagination_previous) $('#sidebar_search_media_pagination_previous a').removeClass('disabled');
    else $('#sidebar_search_media_pagination_previous a').addClass('disabled');

    if(pagination_next) $('#sidebar_search_media_pagination_next a').removeClass('disabled');
    else $('#sidebar_search_media_pagination_next a').addClass('disabled');

    // handle results
    $('#sidebar_search_media_results tbody').html('');

    if(data.status != false) for(var i in media)
    {

      var duration = media[i]['duration'];
      if(duration==null) duration = '';
      else duration = secsToTime(duration);

      if(media[i]['is_archived']==1) var data_mode = 'media_archived';
      else if(media[i]['is_approved']==0) var data_data = 'media_unapproved';
      else data_mode = 'media';

      var media_type_symbol = OB.t('Sidebar', media[i]['type'][0].toUpperCase()+media[i]['type'].substring(1).toLowerCase()+' Search Symbol');

      $('#sidebar_search_media_results tbody').append('\
        <tr class="sidebar_search_media_result" id="sidebar_search_media_result_'+media[i]['id']+'" data-mode="'+data_mode+'">\
          <td class="sidebar_search_media_type" data-column="type">'+htmlspecialchars(media_type_symbol)+'</td>\
          <td class="sidebar_search_media_artist" data-column="artist">'+htmlspecialchars(media[i]['artist'])+'</td>\
          <td class="sidebar_search_media_detailed_column hidden" data-column="album">'+htmlspecialchars(media[i]['album'])+'</td>\
          <td class="sidebar_search_media_title" data-column="title">'+htmlspecialchars(media[i]['title'])+'</td>\
          <td class="sidebar_search_media_detailed_column sidebar_search_media_year hidden" data-column="year">'+htmlspecialchars(media[i]['year'])+'</td>\
          <td class="sidebar_search_media_detailed_column hidden" data-column="category">'+htmlspecialchars(media[i]['category_name'])+'</td>\
          <td class="sidebar_search_media_detailed_column hidden" data-column="genre">'+htmlspecialchars(media[i]['genre_name'])+'</td>\
          <td class="sidebar_search_media_detailed_column hidden" data-column="country">'+htmlspecialchars(media[i]['country_name'])+'</td>\
          <td class="sidebar_search_media_detailed_column hidden" data-column="language">'+htmlspecialchars(media[i]['language_name'])+'</td>\
          <td class="sidebar_search_media_time" data-column="time">'+duration+'</td>\
        </tr>');

      $('#sidebar_search_media_result_'+media[i]['id']).click(function(e) {

        var keypress = null;
        if(e.shiftKey) keypress='shift';
        else if(e.ctrlKey) keypress='ctrl';

        OB.Sidebar.mediaSelect(this,false,keypress);
      });

      // change null object to blank string when required
      if(media[i]['year'] === null) media[i]['year']='';

      if(media[i]['country_id'] === null) media[i]['country_id']='';
      if(media[i]['country_name'] === null) media[i]['country_name']='';

      if(media[i]['language_id'] === null) media[i]['language_id']='';
      if(media[i]['language_name'] === null) media[i]['language_name']='';


      // some additional attributes to set
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-duration', media[i]['duration']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-id', media[i]['id']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-artist', media[i]['artist']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-title', media[i]['title']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-year', media[i]['year']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-album', media[i]['album']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-type', media[i]['type']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-filename', media[i]['filename']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-category_id', media[i]['category_id']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-category_name', media[i]['category_name']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-country_id', media[i]['country_id']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-country_name', media[i]['country_name']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-language_id', media[i]['language_id']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-language_name', media[i]['language_name']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-genre_id', media[i]['genre_id']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-genre_name', media[i]['genre_name']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-comments', media[i]['comments']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-is_copyright_owner', media[i]['is_copyright_owner']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-public_status', media[i]['status']);
      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-dynamic_select', media[i]['dynamic_select']);

      $('#sidebar_search_media_result_'+media[i]['id']).attr('data-owner_id', media[i]['owner_id']);

      if(media[i]['is_archived']==1) $('#sidebar_search_media_result_'+media[i]['id']).attr('data-status', 'archived');
      else if(media[i]['is_approved']==0) $('#sidebar_search_media_result_'+media[i]['id']).attr('data-status', 'unapproved');
      else $('#sidebar_search_media_result_'+media[i]['id']).attr('data-status', 'approved');

      // remove our hidden class from detailed columns if we're in an expanded (detailed) view.
      if($('#sidebar_search').hasClass('sidebar_search_detailed')) $('.sidebar_search_media_detailed_column').show();

      // set up context menu

      var menuOptions = new Object();

      menuOptions[OB.t('Common','Details')] = { click: function(element) { OB.Sidebar.contextMenuDetailsPage($(element).attr('data-id')); } };
      if(OB.Sidebar.mediaCanEdit(media[i]['id'])) menuOptions[OB.t('Common','Edit')] = { click: function(element) { OB.Sidebar.contextMenuEditPage(); } };
      if(OB.Settings.permissions.indexOf('download_media')!=-1) menuOptions[OB.t('Common','Download')] = { click: function(element){ OB.Sidebar.contextMenuDownload($(element).attr('data-id')); } };

      if(Object.keys(menuOptions).length>0)
      {

        $('#sidebar_search_media_result_'+media[i]['id']).contextMenu('context-menu-'+media[i]['id'],

            menuOptions,

            {
              disable_native_context_menu: false,
              showMenu: function(element) { $(element).click(); },
              hideMenu: function() { },
              leftClick: false // trigger on left click instead of right click
            });

      }

    }

    if(data.status==false) $('#sidebar_search_media_results').attr('data-num_results',0);
    else $('#sidebar_search_media_results').attr('data-num_results',num_results);

    OB.Sidebar.mediaSearchNumResults();
    OB.Sidebar.media_search_last_query = search_query;

    if(search_query.mode=='simple') OB.Sidebar.media_search_last_simple = search_query;
    else OB.Sidebar.media_search_last_advanced = search_query;

    if(data.status!=false && media.length>0) {
      $('.sidebar_search_media_result').draggable({helper: 'clone', opacity: 0.8, cursor: 'crosshair',
        start: function(event, ui) {

          var keypress = null;
          if(event.shiftKey) keypress='shift';
          else if(event.ctrlKey) keypress='ctrl';

          // select the media we're dragging also.
          OB.Sidebar.mediaSelect($(ui.helper),true,keypress);

          // but we don't actually want the helper to count as part of this.
          $(ui.helper).removeClass('sidebar_search_media_selected');

          var num_selected = $('.sidebar_search_media_selected').size();

          if(num_selected==1) var helper_text = $(ui.helper).attr('data-artist') + ' - ' + $(ui.helper).attr('data-title');
          else var helper_text = num_selected+' items';

          $(ui.helper).html('<div class="sidebar_dragging_items">'+htmlspecialchars(helper_text)+'</div>');

          var data_status = $(ui.helper).attr('data-status');

          if(data_status == 'approved')
            $('.droppable_target_media').addClass('droppable_target_highlighted');

          else if(data_status == 'unapproved')
            $('.droppable_target_media_unapproved').addClass('droppable_target_highlighted');

          else if(data_status == 'archived')
            $('.droppable_target_media_unapproved').addClass('droppable_target_highlighted');

        },

        stop: function(event, ui) {

          var data_status = $(ui.helper).attr('data-status');

          if(data_status == 'approved')
            $('.droppable_target_media').removeClass('droppable_target_highlighted');

          else if(data_status == 'unapproved')
            $('.droppable_target_media_unapproved').removeClass('droppable_target_highlighted');

          else if(data_status == 'archived')
            $('.droppable_target_media_unapproved').removeClass('droppable_target_highlighted');

        }

      });

      OB.Layout.tableFixedHeaders($('#sidebar_search_media_headings'),$('#sidebar_search_media_results'));
    }

    OB.Sidebar.mediaEditDeleteVisibility();

  });

}

OB.Sidebar.contextMenuEditPage = function()
{
  OB.Media.editPage();
}

OB.Sidebar.contextMenuDownload = function(id)
{
  OB.Media.download(id);
}

OB.Sidebar.contextMenuDetailsPage = function(id)
{
  OB.Media.detailsPage(id);
}

OB.Sidebar.contextMenuPlaylistEditPage = function()
{
  OB.Playlist.editPage();
}

OB.Sidebar.contextMenuPlaylistDetailsPage = function(id)
{
  OB.Playlist.detailsPage(id);
}

OB.Sidebar.playlist_search_filters = new Object();
OB.Sidebar.playlist_search_filters.my=false;
OB.Sidebar.playlist_search_filters.bookmarked=false;

OB.Sidebar.playlistSearchFilter = function(what)
{

  // toggle on off
  if(what=='my') OB.Sidebar.playlist_search_filters.my = !OB.Sidebar.playlist_search_filters.my;

  if(OB.Sidebar.playlist_search_filters.my==true) $('#sidebar_search_playlist_my').text(OB.t('Sidebar','My Filter Link').toUpperCase());
  else $('#sidebar_search_playlist_my').text(OB.t('Sidebar','My Filter Link').toLowerCase());

  // reload search
  OB.Sidebar.playlistSearch();

}

OB.Sidebar.playlistSelectAll = function()
{
  $('.sidebar_search_playlist_result').addClass('sidebar_search_playlist_selected');
}

OB.Sidebar.playlistSelectNone = function()
{
  $('.sidebar_search_playlist_result').removeClass('sidebar_search_playlist_selected');
}

OB.Sidebar.playlist_last_selected = null;

OB.Sidebar.playlistSelect = function(object,dragging,keypress)
{

  if(keypress==null && !dragging) $('.sidebar_search_playlist_selected').removeClass('sidebar_search_playlist_selected');
  else if(keypress==null && dragging && !$(object).hasClass('sidebar_search_playlist_selected')) $('.sidebar_search_playlist_selected').removeClass('sidebar_search_playlist_selected');

  var media_id = $(object).attr('data-id');

  if(!dragging && keypress!='shift' && $('#sidebar_search_playlist_result_'+media_id).hasClass('sidebar_search_playlist_selected'))
    $('#sidebar_search_playlist_result_'+media_id).removeClass('sidebar_search_playlist_selected');

  else
  {
    if(keypress=='shift')
    {

      var last_selected = $('#sidebar_search_playlist_result_'+OB.Sidebar.playlist_last_selected);

      // figure out if we have to move up or down.
      if($(last_selected).parent().children().index($(last_selected)) < $('#sidebar_search_playlist_result_'+media_id).parent().children().index($('#sidebar_search_playlist_result_'+media_id))) var shift_down = true;
      else var shift_down = false;

      while(last_selected.attr('data-id')!=media_id)
      {
        if(shift_down) last_selected = $(last_selected).next();
        else last_selected = $(last_selected).prev();

        last_selected.addClass('sidebar_search_playlist_selected');
      }

    }

    else $('#sidebar_search_playlist_result_'+media_id).addClass('sidebar_search_playlist_selected');

    OB.Sidebar.playlist_last_selected = media_id;

  }

  OB.Sidebar.playlistEditDeleteVisibility();


}

// determine whether user can edit media (id).
OB.Sidebar.playlistCanEdit = function(id)
{

  if(OB.Settings.permissions.indexOf('manage_playlists')!=-1) return true;

  else if(OB.Settings.permissions.indexOf('create_own_playlists')==-1) return false;

  else if($('#sidebar_search_playlist_result_'+id).attr('data-owner_id')==OB.Account.user_id) return true;

  return false;

}

// adjust the visibility of the playlist edit / delete buttons.
OB.Sidebar.playlistEditDeleteVisibility = function()
{

  // default to true.
  var visible = true;

  // if there is nothing selected, then we can't edit or delete.
  if($('#sidebar_search_playlist_results tbody .sidebar_search_playlist_selected').length==0) visible = false;

  // if we can manage playlists, then we can always edit/delete.
  else if(OB.Settings.permissions.indexOf('manage_playlists')!=-1) visible = true;

  // if we can't edit our own playlists either, then we definitely can't edit/delete.
  else if(OB.Settings.permissions.indexOf('create_own_playlists')==-1) visible = false;

  // otherwise, we have to go through each seleted item to see if we can edit/delete.
  else
  {

    $('#sidebar_search_playlist_results tbody .sidebar_search_playlist_selected').each(function(index,element)
    {
      if(!OB.Sidebar.playlistCanEdit($(element).attr('data-id'))) { visible = false; return false; }
    });

  }

  if(visible) { $('#sidebar_playlist_edit_button').show(); $('#sidebar_playlist_delete_button').show(); }
  else { $('#sidebar_playlist_edit_button').hide(); $('#sidebar_playlist_delete_button').hide(); }

}

OB.Sidebar.playlist_search_offset = 0;

OB.Sidebar.playlistSearchPageNext = function()
{

  var num_results = $('#sidebar_search_playlist_results').attr('data-num_results');

  if(num_results <= OB.Sidebar.playlist_search_offset + OB.ClientStorage.get('results_per_page'))
  {
    return;
  }

  OB.Sidebar.playlist_search_offset += OB.ClientStorage.get('results_per_page');
  OB.Sidebar.playlistSearch(true);

}

OB.Sidebar.playlistSearchPagePrevious = function()
{

  if(OB.Sidebar.playlist_search_offset == 0) return;

  OB.Sidebar.playlist_search_offset -= OB.ClientStorage.get('results_per_page');
  if(OB.Sidebar.playlist_search_offset<0) OB.Sidebar.playlist_search_offset = 0;
  OB.Sidebar.playlistSearch(true);

}

OB.Sidebar.playlist_search_sort_by = 'updated';
OB.Sidebar.playlist_search_sort_dir = 'desc';

OB.Sidebar.playlistSearchSort = function(sortby)
{

  // change direction if already sorting by this column
  if(sortby == OB.Sidebar.playlist_search_sort_by)
  {
    if(OB.Sidebar.playlist_search_sort_dir=='asc') OB.Sidebar.playlist_search_sort_dir = 'desc';
    else OB.Sidebar.playlist_search_sort_dir = 'asc';
  }

  OB.Sidebar.playlist_search_sort_by = sortby;

  OB.Sidebar.playlistSearch();

}

OB.Sidebar.playlistSearch = function(pagination)
{

  // if not the result of pagination (new search), reset offset to 0
  if(!pagination)
  {
    OB.Sidebar.playlist_search_offset = 0;
  }

  $('#sidebar_search_playlist_headings').show();

  OB.API.post('playlist','playlist_search',{ sort_by: OB.Sidebar.playlist_search_sort_by, sort_dir: OB.Sidebar.playlist_search_sort_dir, q: $('#sidebar_search_playlist_input').val(), l: OB.ClientStorage.get('results_per_page'), o: OB.Sidebar.playlist_search_offset, my: OB.Sidebar.playlist_search_filters.my },function (data) {

    var playlist = data.data.playlists;
    var num_results = data.data.num_results;

    // update pagination
    if(OB.Sidebar.playlist_search_offset > 0) var pagination_previous = true;
    else var pagination_previous = false;

    if(num_results > OB.Sidebar.playlist_search_offset + OB.ClientStorage.get('results_per_page')) { var pagination_next = true; }
    else var pagination_next = false;

    if(pagination_previous) $('#sidebar_search_playlist_pagination_previous a').removeClass('disabled');
    else $('#sidebar_search_playlist_pagination_previous a').addClass('disabled');

    if(pagination_next) $('#sidebar_search_playlist_pagination_next a').removeClass('disabled');
    else $('#sidebar_search_playlist_pagination_next a').addClass('disabled');

    // handle results
    $('#sidebar_search_playlist_results').attr('data-num_results',num_results);

    $('#sidebar_search_playlist_results tbody').html('');

    if(num_results == 0)
    {
      $('#sidebar_search_playlist_results tbody').html('<tr><td colspan="3" class="sidebar_search_noresults"></td></tr>');
      $('#sidebar_search_playlist_results tbody .sidebar_search_noresults').text(OB.t('Sidebar','No Playlists Found'));
    }

    if(num_results==1) num_results_text = OB.t('Sidebar','Playlist Found');
    else num_results_text = OB.t('Sidebar','Playlists Found',num_results);

    $('#sidebar_search_playlist_footer .results .num_results').html(num_results_text);



    for(var i in playlist)
    {

      var duration = playlist[i]['duration'];
      if(duration==null) duration = '';
      else duration = secsToTime(duration);

      var playlist_description = playlist[i]['description'];
      if(playlist_description.length>150) playlist_description = playlist_description.substr(0,150)+'...';

      $('#sidebar_search_playlist_results tbody').append('\
        <tr class="sidebar_search_playlist_result" id="sidebar_search_playlist_result_'+playlist[i]['id']+'" data-mode="playlist">\
          <td class="sidebar_search_playlist_name" data-column="name">'+htmlspecialchars(playlist[i]['name'])+'</td>\
          <td class="sidebar_search_playlist_description" data-column="description">'+playlist_description+'</td>\
        </tr>');

      $('#sidebar_search_playlist_result_'+playlist[i]['id']).click(function(e) {

        var keypress = null;
        if(e.shiftKey) keypress='shift';
        else if(e.ctrlKey) keypress='ctrl';

        OB.Sidebar.playlistSelect(this,false,keypress);

      });

      // some additional attributes to set
      $('#sidebar_search_playlist_result_'+playlist[i]['id']).attr('data-id', playlist[i]['id']);
      $('#sidebar_search_playlist_result_'+playlist[i]['id']).attr('data-name', playlist[i]['name']);
      $('#sidebar_search_playlist_result_'+playlist[i]['id']).attr('data-description', playlist[i]['description']);
      $('#sidebar_search_playlist_result_'+playlist[i]['id']).attr('data-status', playlist[i]['status']);
      $('#sidebar_search_playlist_result_'+playlist[i]['id']).attr('data-owner_id', playlist[i]['owner_id']);

      // set up context menu

      var menuOptions = new Object();

      menuOptions[OB.t('Common','Details')] = { click: function(element) { OB.Sidebar.contextMenuPlaylistDetailsPage($(element).attr('data-id')); } };
      if(OB.Sidebar.playlistCanEdit(playlist[i]['id'])) menuOptions[OB.t('Common','Edit')] = { click: function(element) { OB.Sidebar.contextMenuPlaylistEditPage(); } };

      if(Object.keys(menuOptions).length>0)
      {

        $('#sidebar_search_playlist_result_'+playlist[i]['id']).contextMenu('context-menu-'+playlist[i]['id'],

            menuOptions,

            {
              disable_native_context_menu: false,
              showMenu: function(element) { $(element).click(); },
              hideMenu: function() { },
              leftClick: false // trigger on left click instead of right click
            });

      }

    }

    if(playlist.length>0) {
      $('.sidebar_search_playlist_result').draggable({helper: 'clone', opacity: 0.8, cursor: 'crosshair',
        start: function(event, ui) {

          var keypress = null;
          if(event.shiftKey) keypress='shift';
          else if(event.ctrlKey) keypress='ctrl';

          // select the media we're dragging also.
          OB.Sidebar.playlistSelect($(ui.helper),true,keypress);

          // but we don't actually want the helper to count as part of this.
          $(ui.helper).removeClass('sidebar_search_playlist_selected');

          var num_selected = $('.sidebar_search_playlist_selected').size();

          if(num_selected==1) var helper_text = $(ui.helper).attr('data-name');
          else var helper_text = num_selected+' items';

          $(ui.helper).html('<div class="sidebar_dragging_items">'+htmlspecialchars(helper_text)+'</div>');

          $('.droppable_target_playlist').addClass('droppable_target_highlighted');

        },

        stop: function(event, ui) {
          $('.droppable_target_playlist').removeClass('droppable_target_highlighted');
        }
      });

      OB.Layout.tableFixedHeaders($('#sidebar_search_playlist_headings'),$('#sidebar_search_playlist_results'));
    }

    OB.Sidebar.playlistEditDeleteVisibility();

  });

}

OB.Sidebar.mySearchesContextMenuOn = function(e,type,id)
{
  $('#my_searches_item_'+id).addClass('context_menu_on');

  $('#my_searches_'+type+'_context_menu').css('left',e.pageX).css('top',e.pageY);

  // set default or unset default?
  if(type=='saved')
  {
    if($('#my_searches_item_'+id+' .media_search_item_default_text').length)
    {
      $('#my_searches_context_menu_unset_default').show();
      $('#my_searches_context_menu_set_default').hide();
    }
    else
    {
      $('#my_searches_context_menu_set_default').show();
      $('#my_searches_context_menu_unset_default').hide();
    }
  }

  $('#my_searches_'+type+'_context_menu').show();
  $(window).bind('mousedown',OB.Sidebar.mySearchesContextMenuOff);
}

OB.Sidebar.mySearchesContextMenuOff = function(e)
{
  var $target = $(e.target);
  if($target.hasClass('context_menu_item')) $target.click();

  $('#my_searches_history_context_menu').hide();
  $('#my_searches_saved_context_menu').hide();

  $('.my_searches_item').removeClass('context_menu_on');

  $(window).unbind('mousedown',OB.Sidebar.mySearchesContextMenuOff);
}

OB.Sidebar.mySearchesSave = function()
{
  if(!$('.my_searches_item.context_menu_on').length) return;

  var id = $('.my_searches_item.context_menu_on').attr('data-id');

  OB.API.post('media','media_my_searches_save',{ 'id': id }, function(response)
  {
    if(response.status==true)
    {
      $('.my_searches_item[data-id='+id+']').prependTo('#my_searches_saved');
      OB.Sidebar.mySearchesItemContextMenu($('.my_searches_item[data-id='+id+']'),'saved');
      OB.Sidebar.mySearchesNosearchtext();
    }
    else OB.UI.alert('An error occurred while trying to save this search item.');
  });
}

OB.Sidebar.mySearchesDelete = function()
{
  if(!$('.my_searches_item.context_menu_on').length) return;

  var id = $('.my_searches_item.context_menu_on').attr('data-id');

  OB.API.post('media','media_my_searches_delete',{ 'id': id }, function(response)
  {
    if(response.status==true)
    {
      $('.my_searches_item[data-id='+id+']').remove();
      OB.Sidebar.mySearchesNosearchtext();
    }
    else OB.UI.alert('An error occurred while trying to delete this search item.');
  });
}

OB.Sidebar.mySearchesNosearchtext = function()
{
  if($('#my_searches_history .my_searches_item').length==0) $('#my_searches_history_nosearches').show();
  else $('#my_searches_history_nosearches').hide();

  if($('#my_searches_saved .my_searches_item').length==0) $('#my_searches_saved_nosearches').show();
  else $('#my_searches_saved_nosearches').hide();
}

OB.Sidebar.mySearchesSearch = function(id)
{
  OB.Sidebar.advanced_search_filters = $('#my_searches_item_'+id).data('filters');
  OB.Sidebar.mediaSearch();
  OB.UI.closeModalWindow();
}

OB.Sidebar.mySearchesWindow = function()
{
  OB.API.post('media','media_my_searches',{},function(response)
  {

    OB.UI.openModalWindow('sidebar/my_searches.html');

    var history = response.data.history;
    var saved = response.data.saved;

    if(history && history.length>0)
    {
      $.each(history, function(index,data)
      {
        OB.Sidebar.mySearchesWindowAddItem(data,'history');
      });
    }

    if(saved && saved.length>0)
    {
      $.each(saved, function(index,data)
      {
        OB.Sidebar.mySearchesWindowAddItem(data,'saved');
      });
    }

    OB.Sidebar.mySearchesNosearchtext();
  });
}

OB.Sidebar.mySearchesWindowAddItem = function(data,type)
{
  if(data.query.mode!='advanced') return; // this shouldn't be.

  $('#my_searches_'+type).append('<div class="my_searches_item" data-id="'+data.id+'" id="my_searches_item_'+data.id+'"></div>');

  $('#my_searches_item_'+data.id).click(function() { OB.Sidebar.mySearchesSearch(data.id); });
  $('#my_searches_item_'+data.id).data('filters',data.query.filters);
  $('#my_searches_item_'+data.id).data('description',data.description);

  OB.Sidebar.mySearchesItemContextMenu($('#my_searches_item_'+data.id), type);

  if(data.default=='1') $('#my_searches_item_'+data.id).append('<div class="media_search_item_default_text">All simple searches will include these filters by default.</div>');

  if(type=='saved' && data.description!='')
  {
    $('#my_searches_item_'+data.id).append('<div><i>'+nl2br(htmlspecialchars(data.description))+'</i></div>');
  }

  else $.each(data.query.filters, function(filter_index, filter)
  {
    $('#my_searches_item_'+data.id).append('<div>'+htmlspecialchars(filter.description)+'</div>');
  });
}

OB.Sidebar.mySearchesItemContextMenu = function($element,type)
{
  $element.unbind('contextmenu').bind('contextmenu', function(e)
  {
    OB.Sidebar.mySearchesContextMenuOn(e,type,$element.attr('data-id'));
    return false;
  });
}

OB.Sidebar.mySearchesMakeDefault = function()
{
  if(!$('.my_searches_item.context_menu_on').length) return;

  var id = $('.my_searches_item.context_menu_on').attr('data-id');

  OB.API.post('media','media_my_searches_default',{ 'id': id }, function(response)
  {
    if(response.status==true)
    {
      $('.media_search_item_default_text').remove();
      $('.my_searches_item[data-id='+id+']').prepend('<div class="media_search_item_default_text">All simple searches will include these filters by default.</div>');
    }
    else OB.UI.alert('An error occurred while trying to make this search the default.');
  });
}

OB.Sidebar.mySearchesUnsetDefault = function()
{
  OB.API.post('media','media_my_searches_unset_default',{}, function(response)
  {
    if(response.status==true)
    {
      $('.media_search_item_default_text').remove();
    }
    else OB.UI.alert('An error occurred while trying to unset the default search.');
  });
}

OB.Sidebar.mySearchesEditWindow = function()
{
  if(!$('.my_searches_item.context_menu_on').length) return;

  var id = $('.my_searches_item.context_menu_on').attr('data-id');
  var filters = $('#my_searches_item_'+id).data('filters');
  var description = $('#my_searches_item_'+id).data('description');

  OB.UI.openModalWindow('sidebar/advanced_search.html');

  // switch to 'edit my search item' mode.
  $('#layout_modal_window .advanced_search_item').hide();
  $('#layout_modal_window .edit_my_search_item').show();

  $('#layout_modal_window #edit_my_search_item_save').attr('data-id',id);
  $('#edit_my_search_item_description').val(description);

  $.each(filters, function(index,filter_data)
  {
    OB.Sidebar.advancedSearchAdd(filter_data);
  });

}

OB.Sidebar.mySearchesEdit = function()
{
  postfields = {};
  postfields.id = $('#layout_modal_window #edit_my_search_item_save').attr('data-id');
  postfields.filters = OB.Sidebar.advancedSearchGetFilters();
  postfields.description = $('#edit_my_search_item_description').val();

  if(postfields.filters.length<1)
  {
    $('#advanced_search_message').obWidget('error','Add at least one search filter is required.');
    return;
  }

  OB.API.post('media','media_my_searches_edit',postfields, function(response)
  {
    if(!response.status)
    {
      $('#advanced_search_message').obWidget('error',response.msg);
      return;
    }

    OB.Sidebar.mySearchesWindow();
  });
}



OB.Sidebar.advancedSearchWindow = function()
{
  OB.UI.openModalWindow('sidebar/advanced_search.html');

  $.each(OB.Settings.categories,function(index,category)
  {
    $('#advanced_search_category_options').append('<option value="'+category.id+'">'+htmlspecialchars(category.name)+'</option>');
  });

  $.each(OB.Settings.countries,function(index,country)
  {
    $('#advanced_search_country_options').append('<option value="'+country.id+'">'+htmlspecialchars(country.name)+'</option>');
  });

  $.each(OB.Settings.genres,function(index,genre)
  {
    $('#advanced_search_genre_options').append('<option value="'+genre.id+'">'+htmlspecialchars(genre.name)+'</option>');
  });

  $.each(OB.Settings.languages,function(index,language)
  {
    $('#advanced_search_language_options').append('<option value="'+language.id+'">'+htmlspecialchars(language.name)+'</option>');
  });

  $('#advanced_search_value').focus();

  if(OB.Sidebar.media_search_last_advanced)
  {
    $.each(OB.Sidebar.media_search_last_advanced.filters, function(index,filter_data)
    {
      OB.Sidebar.advancedSearchAdd(filter_data);
    });
  }

  else
  {
    OB.API.post('media','media_my_searches',{},function(response)
    {
      if(response.data.saved)
      {
        $.each(response.data.saved, function(index,filter)
        {
          if(filter.default=='1')
          {
            $.each(filter.query.filters, function(index,filter_data)
            {
              OB.Sidebar.advancedSearchAdd(filter_data);
            });
          }
        });
      }
    });
  }

}

OB.Sidebar.advancedSearchFilterChange = function()
{

  $('#advanced_search_value').hide();

  $('#advanced_search_year_options').hide();
  $('#advanced_search_duration_options').hide();
  $('#advanced_search_type_options').hide();
  $('#advanced_search_category_options').hide();
  $('#advanced_search_country_options').hide();
  $('#advanced_search_language_options').hide();
  $('#advanced_search_genre_options').hide();
  $('#advanced_search_text_options').hide();
  $('#advanced_search_bool_options').hide();

  var val = $('#advanced_search_filter').val();

  if(val=='year') { $('#advanced_search_year_options').show(); $('#advanced_search_value').show(); $('#advanced_search_value').attr('size',10); }
  else if(val=='duration') { $('#advanced_search_duration_options').show(); $('#advanced_search_value').show(); $('#advanced_search_value').attr('size',10); }
  else if(val=='type') { $('#advanced_search_bool_options').show(); $('#advanced_search_type_options').show(); }
  else if(val=='category') { $('#advanced_search_bool_options').show(); $('#advanced_search_category_options').show(); }
  else if(val=='country') { $('#advanced_search_bool_options').show(); $('#advanced_search_country_options').show(); }
  else if(val=='language') { $('#advanced_search_bool_options').show(); $('#advanced_search_language_options').show(); }
  else if(val=='genre') { $('#advanced_search_bool_options').show(); $('#advanced_search_genre_options').show(); }
  else if(val=='is_copyright_owner') { $('#advanced_search_bool_options').show(); $('#advanced_search_is_copyright_owner_options').show(); }
  else { $('#advanced_search_text_options').show(); $('#advanced_search_value').show(); $('#advanced_search_value').attr('size',25); }

}

OB.Sidebar.advanced_search_filter_id = 0;

OB.Sidebar.advancedSearchAdd = function(filter_data)
{

  if (filter_data) {

    OB.Sidebar.advanced_search_filter_id++;
    var filter_description = filter_data.description;
    var op = filter_data.op;
    var val = filter_data.val;
    var filter = filter_data.filter;

  } else {

    var filter = $('#advanced_search_filter').val();
    var filter_name = $('#advanced_search_filter option:selected').first().text();

    var text_input_val = $.trim($('#advanced_search_value').val());

    // some basic validation
    if ((filter == 'artist' || filter == 'album' || filter == 'title') && text_input_val == '') {

      $('#advanced_search_message').obWidget('error',filter_name + ' text required.');

      return false;
    }

    if ((filter == 'year' || filter == 'duration') && text_input_val.match(/^[0-9]+$/) === null) {


      $('#advanced_search_message').obWidget('error','A valid ' + filter_name.toLowerCase() + ' is required.');

      return false;
    }

    if (filter == 'year' && text_input_val > 2100) {

      $('#advanced_search_message').obWidget('error','A valid year is required.');
      return false;
    }


    $('#advanced_search_no_criteria').hide();
    OB.Sidebar.advanced_search_filter_id++;

    var filter_description = filter_name;

    if (filter == 'artist' || filter == 'album' || filter == 'title' || filter == 'comments') {

      var op = $('#advanced_search_text_options').val();
      var op_name = $('#advanced_search_text_options option:selected').first().text();

      var val = text_input_val;

      filter_description += ' ' + op_name + ' "' + val + '"';

    }

    else if (filter == 'year') {

      var op = $('#advanced_search_year_options').val();
      var op_name = $('#advanced_search_year_options option:selected').first().text();

      var val = text_input_val;

      filter_description += ' ' + op_name + ' ' + val;

    }

    else if (filter == 'duration') {

      var op = $('#advanced_search_duration_options').val();
      var op_name = $('#advanced_search_duration_options option:selected').first().text();

      var val = text_input_val;

      filter_description += ' ' + op_name + ' ' + val + ' seconds';

    }

    else {

      var op = $('#advanced_search_bool_options').val();
      var op_name = $('#advanced_search_bool_options option:selected').first().text();

      var val = $('#advanced_search_' + filter + '_options').val();
      var val_name = $('#advanced_search_' + filter + '_options option:selected').first().text();

      filter_description += ' ' + op_name + ' "' + val_name + '"';

    }

  }

  $('#advanced_search_criteria_list').prepend(
      '<div id="advanced_search_filter_'+OB.Sidebar.advanced_search_filter_id+'">'+
      '<a href="javascript: OB.Sidebar.advancedSearchRemove('+OB.Sidebar.advanced_search_filter_id+');">[x]</a> '+
      htmlspecialchars(filter_description)+
      '</div>');

  var filter_div = $('#advanced_search_filter_'+OB.Sidebar.advanced_search_filter_id);

  $(filter_div).attr('data-filter_description',filter_description);
  $(filter_div).attr('data-filter',filter);
  $(filter_div).attr('data-op',op);
  $(filter_div).attr('data-val',val);

}

OB.Sidebar.advancedSearchRemove = function(id)
{
  $('#advanced_search_filter_'+id).remove();

  if($('#advanced_search_criteria_list').children().length<1)
    $('#advanced_search_no_criteria').show();
}

OB.Sidebar.advanced_search_filters = null; // if this is not null, an advanced search will be done by OB.Sidebar.mediaSearch();

// complete our advanced search.
OB.Sidebar.advancedSearch = function()
{

  var filters = OB.Sidebar.advancedSearchGetFilters();

  if(filters.length<1)
  {
    $('#advanced_search_message').obWidget('error','Add at least one search filter is required.');
    return;
  }

  $('#sidebar_search_media_input').val('');

  OB.Sidebar.advanced_search_filters = filters;
  OB.Sidebar.mediaSearch();
  OB.UI.closeModalWindow();

}

OB.Sidebar.advancedSearchGetFilters = function()
{
  var filters = new Array();

  $('#advanced_search_criteria_list').children().each(function(index,data)
  {

    filters.push({ 'filter': $(data).attr('data-filter'), 'description': $(data).attr('data-filter_description'), 'op': $(data).attr('data-op'), 'val': $(data).attr('data-val') });

  });

  return filters;
}

