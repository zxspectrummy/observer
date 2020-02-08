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

// storage for the client application (user and global)
OB.ClientStorage = new Object();
  
OB.ClientStorage.init = function()
{
  OB.Callbacks.add('ready',-60,OB.ClientStorage.globalInit);
	OB.Callbacks.add('ready',-60,OB.ClientStorage.getData);
}

OB.ClientStorage.store = function(data,callback,use_global)
{

  $.each(data,function(index,value)
  {
    if(use_global) OB.ClientStorage.global_data[index] = value;
    else OB.ClientStorage.data[index] = value;
  });

  var postfields = new Object();
  postfields.client_name = 'obapp_web_client';

  if(use_global) 
  {
    postfields.global = 1;
    postfields.data = $.toJSON(OB.ClientStorage.global_data);
  }
  else postfields.data = $.toJSON(OB.ClientStorage.data);

  OB.API.post('clientstorage','store',postfields, function(response)
  {
    if(callback) callback();
  });

}

OB.ClientStorage.get = function(name,use_global)
{

  if(use_global) 
  {
    var data = OB.ClientStorage.global_data;
    var defaults = OB.ClientStorage.global_defaults;
  }

  else
  {
    var data = OB.ClientStorage.data;
    var defaults = OB.ClientStorage.defaults;
  } 

  if(typeof(data[name])!='undefined') return data[name];
  else if(typeof(defaults[name])!='undefined') return defaults[name];
  else return null;
}

OB.ClientStorage.global_data = null
OB.ClientStorage.global_defaults = {
  welcome_message: 'Welcome to OpenBroadcaster.'
};

OB.ClientStorage.data = null;
OB.ClientStorage.defaults = { 
  results_per_page: 250
};

// used by login data to set data directly from the server.
OB.ClientStorage.getData = function()
{

  OB.API.post('clientstorage','get',{'client_name': 'obapp_web_client'}, function(response)
  {
    if(response.data == '') OB.ClientStorage.data = new Object();
    else OB.ClientStorage.data = $.parseJSON(response.data);
  },'sync');

}

OB.ClientStorage.globalInit = function()
{

  OB.API.post('clientstorage','get',{'client_name': 'obapp_web_client', 'global': 1}, function(response)
  {
    if(response.data == '') OB.ClientStorage.global_data = new Object();
    else OB.ClientStorage.global_data = $.parseJSON(response.data);
  },'sync');

}

