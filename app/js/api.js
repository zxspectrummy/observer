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

OB.API = new Object();

OB.API.ajax_list = Array(); // list of ajax XMLHTTPREQUEST objects

OB.API.ajaxStatus = function()
{

  var is_loading = false;

  for(i in OB.API.ajax_list)
  {
    if(OB.API.ajax_list[i].readyState==4 || (OB.API.ajax_list[i].readyState==0 && OB.API.ajax_list[i].statusText=='abort')) OB.API.ajax_list.splice(i,1);
    else is_loading = true;
  }

  if(is_loading) OB.UI.ajaxLoaderOn();
  else OB.UI.ajaxLoaderOff();

}

OB.API.multiPost = function(post,callback_function,mode)
{
  if(mode=='sync') var async = false;
  else async = true;

  var controllers = [];
  var actions = [];
  var sdatas = [];

  $.each(post,function(index,data)
  {
    post[index][2] = $.toJSON(post[index][2]);
    controllers.push(post[index][0]);
    actions.push(post[index][1]);
    sdatas.push(post[index][2]);
  });

  OB.API.ajax_list.push( $.ajax( {

    'async': async,
    'type': 'POST',
    'url': '/api.php',
    'dataType': 'json',
    'data': { "m": post, "i": readCookie('ob_auth_id'), "k": readCookie('ob_auth_key') },
    'success': function(data) {
      OB.API.postSuccess(controllers,actions,callback_function,sdatas,data);
    }
  }) );

  OB.API.ajaxStatus();
}

OB.API.post = function(controller,action,sdata,callback_function,mode)
{

  if(mode=='sync') var async = false;
  else async = true;

  var xhr = $.ajax( {
    'async': async,
    'type': 'POST',
    'url': '/api.php',
    'dataType': 'json',
    'data': { "c": controller, "a": action, "d": $.toJSON(sdata), "i": readCookie('ob_auth_id'), "k": readCookie('ob_auth_key') },
    'success': function(data) {
      OB.API.postSuccess(controller,action,callback_function,sdata,data);
    }
  });

  OB.API.ajax_list.push(xhr);
  OB.API.ajaxStatus();
  return OB.API.ajax_list.length-1;
}

OB.API.abort = function(id)
{
  if(!OB.API.ajax_list[id]) return;
  OB.API.ajax_list[id].abort();
  OB.API.ajaxStatus();
}

OB.API.postSuccess = function(controller,action,callback_function,sdata,data)
{

  if(typeof(controller)=='string')
  {
    var controllers = [controller];
    var actions = [action];
    var datas = [data];
    var sdatas = [sdata];
  }
  else
  {
    var controllers = controller;
    var actions = action;
    var datas = Array.isArray(data) ? data : [data]; // if error, is not an array.
    var sdatas = sdata;
  }

  OB.API.ajaxStatus();

  // make sure we don't have any errors.
  var has_error = false;
  $.each(datas,function(index,tmp) { if(typeof(tmp.error)!='undefined') { has_error = tmp.error; return false; } });
  if(has_error)
  {
    //T Access denied while attempting to complete your request. Please refresh your web browser, log out, log back in, and try again. If the problem persists, please contact the system administrator.
    if(has_error.no==4 && has_error.uid!=0) OB.UI.alert('Access denied while attempting to complete your request. Please refresh your web browser, log out, log back in, and try again. If the problem persists, please contact the system administrator.');
    else if(has_error.no==4 && has_error.uid==0) OB.Account.loginWindow();

    //T An unknown error occurred while attempting to complete your request. Please refresh your web browser and try again. If the problem persists, please contact the system administrator.
    else OB.UI.alert('An unknown error occurred while attempting to complete your request. Please refresh your web browser and try again. If the problem persists, please contact the system administrator.');

    return false;
  }

  $.each(controllers,function(index,controller)
  {
    if(OB.API.callback_prepend_array[controller] && OB.API.callback_prepend_array[controller][actions[index]])
    {
      $.each(OB.API.callback_prepend_array[controller][actions[index]], function(index,callback) { callback(sdatas[index], datas[index]); });
    }
  });

  callback_function(data);

  $.each(controllers,function(index,controller)
  {
    if(OB.API.callback_append_array[controller] && OB.API.callback_append_array[controller][actions[index]])
    {
      $.each(OB.API.callback_append_array[controller][actions[index]], function(index,callback) { callback(sdatas[index], datas[index]); });
    }
  });

  return true;

}

OB.API.callback_prepend_array = [];
OB.API.callbackPrepend = function(controller, action, callback)
{
  if(!OB.API.callback_prepend_array[controller]) OB.API.callback_prepend_array[controller] = [];
  if(!OB.API.callback_prepend_array[controller][action]) OB.API.callback_prepend_array[controller][action] = [];

  OB.API.callback_prepend_array[controller][action].push(callback);
}

OB.API.callback_append_array = [];
OB.API.callbackAppend = function(controller, action, callback)
{
  if(!OB.API.callback_append_array[controller]) OB.API.callback_append_array[controller] = [];
  if(!OB.API.callback_append_array[controller][action]) OB.API.callback_append_array[controller][action] = [];

  OB.API.callback_append_array[controller][action].push(callback);
}
