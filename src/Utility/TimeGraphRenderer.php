<?php

class TimeGraphRenderer
{
	static public function render($caption, $id, $input, $value)
	{
		$values = [];
		foreach ($input as $item)
		{
          $timeStamp = new DateTime($item['day'])->getTimestamp() * 1000; // ms
          $values []= '[' . $timeStamp . ', ' . $item[$value] . ']';
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
	}
}
