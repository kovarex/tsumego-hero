function darkAndLight()
{
	// Both theme CSS bundles are loaded with IDs in default.ctp.
	// Toggle the disabled attribute to switch active theme
	const darkLink = document.getElementById("dark-theme-css");
	const lightLink = document.getElementById("light-theme-css");

	var goingDark = light;

	if (goingDark)
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

	if (window.__apexCharts)
	{
		var themeMode = goingDark ? 'dark' : 'light';
		var foreColor = goingDark ? '#f0f0f0' : '#373d3f';
		var gridColors = goingDark ? ['#3e3e3e', 'transparent'] : ['#ddd', 'transparent'];
		window.__apexCharts.forEach(function(chart) {
			chart.updateOptions({
				theme: { mode: themeMode },
				chart: { foreColor: foreColor },
				grid: { row: { colors: gridColors } },
				plotOptions: {
					bar: {
						dataLabels: {
							total: { style: { color: foreColor } }
						}
					}
				}
			}, true, false); // redrawPaths=true, animate=false
		});
	}

	light = !light;
}
