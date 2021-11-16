# OBServer Translation Guide

OB has a translation function specified in OB.UI.translate and aliased as OB.t. Use OB.t to translate. OB.t accepts 1, 2, or 3 arguments.

- 1 argument: if argument is a string, this string gets returned. If argument is an array, this gets converted to 1, 2, or 3 arguments. (so arguments can be accepted as array too).

- 2 arguments: first argument is namespace, second argument is the string ID. see strings/*.txt for how this looks.

- 3 arguments: first argument is namespace, second argument is string ID, third argument is data array which converts %1, %2, .. %n in string value to data[n].

HTML should have “data-t” attribute to indicate that tag contents should be translated. The tag contents specified in the HTML file will be the string ID.

OB.UI.translateHTML = function( $element ) will do a translation on an element in the DOM.

OB.UI.replaceMain will automatically translate.

OB.UI.addMenuItem and OB.UI.addSubMenuItem will automatically translate.
