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

	light = !light;
}
