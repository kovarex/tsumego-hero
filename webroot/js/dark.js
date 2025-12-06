function darkAndLight()
{
	// With AssetCompress bundles, both themes are loaded with IDs
	// Toggle the disabled attribute to switch active theme
	const darkLink = document.getElementById("dark-theme-css");
	const lightLink = document.getElementById("light-theme-css");

	if (light)
	{
		// Switch to dark theme
		setCookie("lightDark", "dark");
		// Update body class FIRST (synchronously) to avoid white flash
		$("body").removeClass("light-theme").addClass("dark-theme");
		// Then enable new theme and disable old (both themes loaded briefly)
		darkLink.disabled = false;
		lightLink.disabled = true;
	}
	else
	{
		// Switch to light theme
		setCookie("lightDark", "light");
		// Update body class FIRST (synchronously) to avoid white flash
		$("body").removeClass("dark-theme").addClass("light-theme");
		// Then enable new theme and disable old (both themes loaded briefly)
		lightLink.disabled = false;
		darkLink.disabled = true;
	}
	
	light = !light;
}

function levelBarChange(num) {
  if (num == 1) {
    $(".account-bar-user-class").removeAttr("id");
    $(".account-bar-user-class").attr("id", "account-bar-user");
    $("#xp-bar-fill").css("width", barPercent1 + "%");
    $("#xp-bar-fill").removeAttr("class");
    $("#xp-bar-fill").attr("class", "xp-bar-fill-c1");
    $("#account-bar-xp").text(barLevelNum);
    $("#modeSelector").removeAttr("class");
    $("#modeSelector").attr("class", "modeSelector2");
    modeSelector = 2;
    levelBar = 1;
    levelToRatingHover = num;
    document.cookie = "levelBar=1;path=/;SameSite=Lax";
    document.cookie = "levelBar=1;path=/sets;SameSite=Lax";
    document.cookie = "levelBar=1;path=/sets/view;SameSite=Lax";
    document.cookie =
      "levelBar=1;path=/tsumegos/play;SameSite=Lax";
    document.cookie = "levelBar=1;path=/users;SameSite=Lax";
    document.cookie = "levelBar=1;path=/users/view;SameSite=Lax";
  } else {
    $(".account-bar-user-class").removeAttr("id");
    $(".account-bar-user-class").attr("id", "account-bar-user2");
    $("#xp-bar-fill").css("width", barPercent2 + "%");
    $("#xp-bar-fill").removeAttr("class");
    $("#xp-bar-fill").attr("class", "xp-bar-fill-c2");
    $("#account-bar-xp").text(barRatingNum);
    $("#modeSelector").removeAttr("class");
    $("#modeSelector").attr("class", "modeSelector1");
    modeSelector = 1;
    levelBar = 2;
    levelToRatingHover = num;
    document.cookie = "levelBar=2;path=/;SameSite=Lax";
    document.cookie = "levelBar=2;path=/sets;SameSite=Lax";
    document.cookie = "levelBar=2;path=/sets/view;SameSite=Lax";
    document.cookie =
      "levelBar=2;path=/tsumegos/play;SameSite=Lax";
    document.cookie = "levelBar=2;path=/users;SameSite=Lax";
    document.cookie = "levelBar=2;path=/users/view;SameSite=Lax";
  }
}
