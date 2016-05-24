OB.UI.Widgets = new Object();

OB.UI.widgetHTML = function($elements)
{

  // TODO some way of better dealing with widget code. (widgets.js?)
  $elements.find('obwidget').each(function(index,element)
  {
    var attributes = $(element).getAttributes();

    // we require an ID on widgets.
    // if(!attributes.id) return;

    // deal with widget type "message".
    if($(element).attr('type')=='message')
    {
      delete attributes['type'];

      $div = $('<div></div>');
      
      $.each(attributes,function(attribute,value) { $div.attr(attribute,value); });

      $div.addClass('obwidget');
      $div.addClass('message');
      $div.addClass('hidden');

      $div.attr('data-type','message');

      $(element).replaceWith($div);
    }
  });

}

OB.UI.Widgets.message = function($element,args)
{
  // validate args.
  if(!args.length) return false;
  if(args[0]!='hide' && args.length<2) return false;
  if($.inArray(args[0],['hide','info','warning','error','success'])<0) return;

  if(args[0]=='hide') $element.hide();

  else
  {
    $element.removeClass('info');
    $element.removeClass('success');
    $element.removeClass('warning');
    $element.removeClass('error');

    $element.addClass(args[0]);

    // translate message or leave as-is
    if(typeof(args[1] == 'object') && args[1].length==2)
      var message = OB.t(args[1][0],args[1][1]);
    else if(typeof(args[1] == 'object') && args[1].length==3)
      var message = OB.t(args[1][0],args[1][1],args[1][2]);
    else
      var message = args[1];

    $element.text(message);
    
    $element.show();

    OB.UI.scrollIntoView($element);
  }

  return true;
}

$.fn.obWidget = function()
{
  if(!this.hasClass('obwidget') || !this.attr('data-type') || !OB.UI.Widgets[this.attr('data-type')]) return false;
  return OB.UI.Widgets[this.attr('data-type')](this,arguments);
}
