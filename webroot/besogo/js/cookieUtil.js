(function() {
'use strict';

besogo.getCookie =	function (cname){
	var name = cname + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for(var i = 0; i<ca.length; i++){
		var c = ca[i];
		while (c.charAt(0) == ' '){
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0){
			return c.substring(name.length, c.length);
		}
	}
	return "";
};

besogo.setCookie =	function (cname, value, days){
	var expires = new Date();
	expires.setTime(expires.getTime() + (days || 365) * 24 * 60 * 60 * 1000);
	document.cookie =
		cname + "=" + encodeURIComponent(value) +
		"; expires=" + expires.toUTCString() +
		"; path=/" +
		"; SameSite=Lax" +
		"; Secure";
};

})(); 
