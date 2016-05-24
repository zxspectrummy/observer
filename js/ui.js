/*     
    Copyright 2014 OpenBroadcaster, Inc.

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

OB.UI = new Object();

OB.UI.htmlCache = new Array();

OB.UI.init = function()
{
	OB.Callbacks.add('ready',-50,OB.UI.initLayout);
}

OB.UI.initLayout = function()
{
	OB.API.post('ui','html',{},function(response) 
	{
		OB.UI.htmlCache = response.data;

		$('#main_container').html(OB.UI.getHTML('layout.html'));
		$('#sidebar_player').html(OB.UI.getHTML('sidebar/player.html'));
		$('#sidebar_search').html(OB.UI.getHTML('sidebar/search.html'));

    OB.UI.translateHTML($('#sidebar_player'));
    OB.UI.translateHTML($('#sidebar_search'));

	},'sync');

  $(window).resize(OB.UI.resizeModalWindow);
}

OB.UI.getHTML = function(what)
{
  return this.htmlCache[what];
}

OB.UI.ajaxLoader = function(where,type)
{
  if(type==undefined) type='';
  else type = '-'+type;

  $(where).html('<center><img src="/images/ajax-loader'+type+'.gif"></center>');
}

OB.UI.ajaxLoaderOn = function()
{
/*  $('#footer_ajax').css('visibility','visible'); */
  $('#footer_ajax').animate({opacity: 1}, 'fast');
}

OB.UI.ajaxLoaderOff = function()
{
  $('#footer_ajax').animate({opacity: 0}, 'fast');
  return;
  $('#footer_ajax').css('visibility','hidden');
}

OB.UI.alert = function(text)
{
  OB.UI.alertOff();
  OB.UI.confirmOff();

  $('body').append( OB.UI.getHTML('alert.html') );
  $('#alert').draggable({ containment: 'document' });

  // hook up things and string translate
  OB.UI.widgetHTML( $('#alert_container') );
  OB.UI.translateHTML( $('#alert_container') );

  if(text) $('#alert_message').text(OB.t(text));
}

OB.UI.alertOff = function()
{
  $('#alert_container').remove();
}

OB.UI.confirm = function(text,callback,okay_text,cancel_text,okay_class,cancel_class)
{

  if(text && text.constructor === Object)
  {
    var args = text;
    if(args.text) text = args.text;
    if(args.callback) callback = args.callback;
    if(args.okay_text) okay_text = args.okay_text;
    if(args.cancel_text) cancel_text = args.cancel_text;
    if(args.okay_class) okay_class = args.okay_class;
    if(args.cancel_class) cancel_class = args.cancel_class;
  }

  OB.UI.alertOff();
  OB.UI.confirmOff();

  $('body').append( OB.UI.getHTML('confirm.html') );
  $('#confirm').draggable({ containment: 'document' });

  // hook up things and string translate
  OB.UI.widgetHTML( $('#confirm_container') );
  OB.UI.translateHTML( $('#confirm_container') );

  if(text) $('#confirm_message').text(OB.t(text));
  if(okay_text) $('#confirm_button_okay').text(OB.t(okay_text));
  if(cancel_text) $('#confirm_button_cancel').text(OB.t(cancel_text));
  if(okay_class) $('#confirm_button_okay').addClass(okay_class);
  if(cancel_class) $('#confirm_button_cancel').addClass(cancel_class);

  if(callback) OB.UI.confirmCallback = callback;
  else OB.UI.confirmCallback = function() { return; }
}

OB.UI.confirmOff = function(callback)
{
  $('#confirm_container').remove();
  if(callback) OB.UI.confirmCallback();
}

OB.UI.confirmCallback = null;

OB.UI.permissionsUpdate = function(context)
{

  if(typeof(context)==='undefined') context = 'body';

  var available_permissions = new Array();

  // determine available permissions without item id.  (we just need access to 1 item in order to show) if item id is not specified.
  $.each(OB.Settings.permissions, function(index,permission)
  {
    var permission_array = permission.split(':');
    available_permissions.push(permission_array[0]);
  });

  // show/hide based on available permissions.
  $(context).find('[data-permissions]').each(function(index,element)
  {

    var permissions_list = $(element).attr('data-permissions').split(' ');
    var has_permission = false;
  
    for(var i in permissions_list)
    {
      if(available_permissions.indexOf(permissions_list[i])!=-1 || OB.Settings.permissions.indexOf(permissions_list[i])!=-1) { has_permission = true; break; }
    }

    if(has_permission) $(element).show();
    else $(element).hide();

  });

  // if our menu has no visible items, hide the menu...
  $(context).find('#obmenu > li').each(function(index,element)
  {
    var count = 0;  

    $(element).find('li').each(function(i,e) { if($(e).css('display')!='none') count++; });

    if(count==0) $(element).hide();
    else $(element).show();
  });

}

OB.UI.replaceMain = function(file)
{
  $('#layout_main').html(OB.UI.getHTML(file));

  // form tag is used for layout information, but we never do a regular form submit.
  $('#layout_main form').submit(function() { event.preventDefault(); }); 

  OB.UI.widgetHTML( $('#layout_main') );
  OB.UI.translateHTML( $('#layout_main') );
}

OB.UI.scrollIntoView = function($element)
{

  if($element.closest('#layout_main_container').length)
    var $container = $('#layout_main_container');

  else if($element.closest('#layout_modal_window').length)
    var $container = $('#layout_modal_window');

  else return;

  // TODO: some instances where element is not entirely visible still return as visible.
  if(!$element.visible(false))
  {
    $container.scrollTo($element,{offset: {top: -10}});
  }

}

OB.UI.openModalWindow = function(file)
{
  $('#layout_modal_container').showFlex();
  $('#layout_modal_window').draggable({ containment: 'document' });

  if(file) 
  {
    $('#layout_modal_window').html(OB.UI.getHTML(file));
    OB.UI.widgetHTML( $('#layout_modal_window') );
    OB.UI.translateHTML( $('#layout_modal_window') );
  }
  else $('#layout_modal_window').html('');

  // window resize event resets position (browser glitchy?)
  $(window).resize();
}

OB.UI.closeModalWindow = function()
{
  $('#layout_modal_container').hide();
  $('#layout_modal_window').html(''); // clear out html to avoid ID conflicts, etc.
}

OB.UI.resizeModalWindow = function()
{
  if(!$('#layout_modal_container:visible').length) return;

  // reset any position change due to dragging
  $('#layout_modal_window').css('top','0').css('left','0');
}

OB.UI.addMenuItem = function(name,slug,order)
{

  // make sure this doesn't already exist.
  if($('#obmenu > li[data-slug='+slug+']').length) return;

  // add
  var html = '<li style="display: none;" data-slug="'+slug+'" data-order="'+order+'"><a></a><ul></ul></li>';
  $('#obmenu > li').each(function(index,li)
  {
    if($(li).attr('data-order')>order)
    {
      $(li).before(html);
      return false;
    }
  });
  
  // add to end if didn't get added yet.
  if(!$('#obmenu > li[data-slug='+slug+']').length) $('#obmenu').append(html);

  // name menu
  $('#obmenu > li[data-slug='+slug+'] > a').text(OB.t(name));

}

OB.UI.addSubMenuItem = function(parent_slug,name,slug,onclick,order,permissions)
{
  // make sure we have the requested parent.
  if(!$('#obmenu > li[data-slug='+parent_slug+']').length) return;

  // make sure this doesn't already exist.
  if($('#obmenu > li[data-slug='+parent_slug+'] > ul > li[data-slug='+slug+']').length) return;

  // add
  var html = '<li data-slug="'+slug+'" data-order="'+order+'"><a href="#" onclick="return false;"></a></li>';
  $('#obmenu > li[data-slug='+parent_slug+'] > ul > li').each(function(index,li)
  {
    if($(li).attr('data-order')>order)
    {
      $(li).before(html);
      return false;
    }
  });

  // add to end if didn't get added yet.
  if(!$('#obmenu > li[data-slug='+parent_slug+'] > ul > li[data-slug='+slug+']').length)
    $('#obmenu > li[data-slug='+parent_slug+'] > ul').append(html);

  // set properties
  $('#obmenu > li[data-slug='+parent_slug+'] > ul > li[data-slug='+slug+'] > a').text(OB.t(name)).click(onclick);
  if(permissions) $('#obmenu > li[data-slug='+parent_slug+'] > ul > li[data-slug='+slug+']').attr('data-permissions',permissions);

  // TODO, should not need this third party menu system.
  $("ul.sf-menu").superfish({autoArrows: false, speed: 'fast', delay: 300}); 

  // update item visibility
  OB.UI.permissionsUpdate();

}

OB.UI.translateHTML = function( $element )
{
  $namespaces = $element.find('[data-tns]');

  // include this if it also has data-tns (would not be picked up using find)
  if( $element.attr('data-tns') !== undefined ) $namespaces = $namespaces.add($element);

  // sort namespaces by number of parents desc (work from inside out)
  $namespaces.sort(function(a, b)
  {
    return $(a).parents().length > $(b).parents().length ? -1 : 1;
  });

  // translate data-t items in namespace
  $namespaces.each(function(index,namespace) 
  {

    var tns = $(namespace).attr('data-tns');

    // is this namespace a single thing to translate?
    if( $(namespace).attr('data-t') !== undefined )
      $strings = $(namespace);

    // if not, find child elements with data-t.
    else $strings = $(namespace).find('[data-t]');

    $strings.each(function(index,string) {
      $(string).text(OB.t(tns,$(string).text()));
      if($(string).attr('placeholder') !== undefined) $(string).attr('placeholder', OB.t(tns,$(string).attr('placeholder')));
      $(string).removeAttr('data-t'); // remove data-t so we don't end up translating again.
    });

    $(namespace).removeAttr('data-tns');

  });
}


// translate based on namespace, name. returns name (which should be human readable ish at least) if no translation found.
OB.UI.translate = function(namespace,name,data)
{

  // don't have first argument? huh.
  if(typeof(namespace)=='undefined') return '';

  // don't have second argument, and first arg is a string? then we just pass it back.
  if(typeof(namespace)=='string' && typeof(name)=='undefined') return namespace;

  // don't have second argument, but first is an array/object? arguments were passed as an array instead maybe.
  if(typeof(namespace)=='object' && typeof(name)=='undefined')
  {
    var tmp = namespace;

    if(tmp.length==0) return '';

    if(tmp.length==1) return tmp[0];

    if(tmp.length>=2)
    {
      namespace = tmp[0];
      name = tmp[1];
    }

    if(tmp.length>=3)
    {
      data = tmp[2];
    }
  }

  if(typeof(OB.UI.strings[namespace])=='undefined') return name;
  if(typeof(OB.UI.strings[namespace][name])=='undefined') return name;

  var string = OB.UI.strings[namespace][name];

  // if we have a singular data item passed as a string, make it an array.
  if(typeof(data)=='string') data = [data];

  string = string.replace(/(\\)?%([0-9])+/g,function(match_string,is_escaped,data_index) { 

    // is this escaped? also data_index = 0 is not valid.
    if(is_escaped || data_index==0) return '%'+data_index;
 
    // do we have a data at the data_index?
    if(!data || !data[data_index-1]) return '';
    
    // we have everything we need, do replace.
    return data[data_index-1]; 
  });

  return string;
}

OB.t = OB.UI.translate;
