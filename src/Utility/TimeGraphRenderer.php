<?php

class TimeGraphRenderer
{
	public static function render($caption, $id, $input, $value)
	{
		echo '<div id="' . $id . '" class="timeGraph"></div>';
		echo '<script>';
		$values = [];
		foreach ($input as $item)
		{
			$timeStamp = new DateTime($item['day'])->getTimestamp() * 1000; // ms
			$values [] = '[' . $timeStamp . ', ' . $item[$value] . ']';
		}

		echo "
var options =
{
	series: [
	{
		name: '" . $caption . "',
		data: [ " . implode(',', $values) . "],
		color: '#74d14c'
	}],
	chart:
	{
		height: 520,
		type: 'line',
		foreColor: '" . Util::getGraphGridColor() . "',
		zoom: {enabled: false}
	},
	dataLabels:	{ enabled: false },
	stroke:
	{
		curve: 'straight',
		colors: ['#74d14c']
	},
	title:
	{
		text: '" . $caption . "',
		align: 'left'
	},
	grid:
	{
		row:
		{
			colors: ['" . Util::getGraphColor() . "', 'transparent'],
			opacity: 0.5
		},
	},
	xaxis: { type: 'datetime' },
	fill:
	{
		opacity: 1,
		colors: ['#74d14c', '#d63a49']
	}
};
var chart = new ApexCharts(document.querySelector('#" . $id . "'), options);
chart.render();";
		echo '</script>';
	}

	// this is weird, why do we include 3rd party js, we should just put the js on our site
	// instead of depending on availability of theirs
	public static function renderScriptInclude()
	{
		echo '
<script>
window.Promise ||
document.write(
\' < script src = "https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js" ><\/script > \'
)
window.Promise ||
document.write(
\'<script src = "https://cdn.jsdelivr.net/npm/eligrey-classlist-js-polyfill@1.2.20171210/classList.min.js" ><\/script > \'
)
window.Promise ||
document.write(
\'<script src = "https://cdn.jsdelivr.net/npm/findindex_polyfill_mdn" ><\/script > \'
)
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
	}
}
