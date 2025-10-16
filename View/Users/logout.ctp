<script type="text/javascript">
	var cacheLifetime = new Date();
	cacheLifetime.setTime(cacheLifetime.getTime()+1*1*1*5*1000);
	cacheLifetime = cacheLifetime.toUTCString()+"";
	setCookie("z_hash", "1");
	delCookie("z_sess");
	delCookie("z_user_hash");
	localStorage.setItem("z_hash", "1");
	localStorage.removeItem("z_sess");
  localStorage.removeItem("z_user_hash");

	window.location.href = "/";
	function setCookie(cookie, value=""){
		let paths = ["/", "/sets", "/sets/view", "/tsumegos/play", "/users", "/users/view", "/users/add"];
		for(let i=0;i<paths.length;i++)
			document.cookie = cookie+"="+value+";SameSite=none;Secure=false;expires="+cacheLifetime+";path="+paths[i];
	}
	function delCookie(cookie, value=""){
		let paths = ["/", "/sets", "/sets/view", "/tsumegos/play", "/users", "/users/view", "/users/add"];
		for(let i=0;i<paths.length;i++)
			document.cookie = cookie+"="+value+";SameSite=none;expires=Thu, 01 Jan 1970 00:00:00 GMT;Secure=false;path="+paths[i];
	}
</script>