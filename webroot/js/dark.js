function darkAndLight()
{
	// Both theme CSS bundles are loaded with IDs in default.ctp.
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
