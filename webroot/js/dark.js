function darkAndLight()
{
	if (light)
	{
		setCookie("lightDark", "dark");
		document.documentElement.dataset.theme = "dark";
	}
	else
	{
		setCookie("lightDark", "light");
		document.documentElement.dataset.theme = "light";
	}

	updateApexChartsTheme();

	light = !light;
}

function updateApexChartsTheme()
{
	if (!window.__apexCharts)
		return;

	var goingDark = document.documentElement.dataset.theme === "dark";
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
		}, true, false);
	});
}
