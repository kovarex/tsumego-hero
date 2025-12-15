<?php

class ValueGraphRenderer
{
	public static function render($caption, $id, $structure, $input, $categoryName, $reverseOrder = false)
	{
		$series = [];
		foreach ($structure as $node)
		{
			$data = [];
			foreach ($input as $item)
				$data[] = $item[$node['name']];
			$series[] = "{
				  name: '" . $node['name'] . "',
				  data: [" . implode(',', $reverseOrder ? array_reverse($data) : $data) . "],
				  color: '" . $node['color'] . "'}";
		}

		$categories = [];
		foreach ($input as $item)
			$categories[] = $item[$categoryName];

		echo "
		var options =
		{
			series: [" . $result = implode(',', $series) . "],
			chart:
			{
				type: 'bar',
				height: " . Util::getValueGraphHeight($input) . ",
				stacked: true,
				foreColor: '" . Util::getGraphGridColor() . "'
			},
			plotOptions:
			{
				bar:
				{
					horizontal: true,
					dataLabels:
					{
						total:
						{
							enabled: true,
							offsetX: 0,
							style:
							{
								fontSize: '13px',
								fontWeight: 900,
								color: '" . Util::getGraphGridColor() . "'
							}
					  }
					}
			  }
			},
			stroke:
			{
				width: 1,
				colors: ['#fff']
			},
			title: { text: '" . $caption . "' },
			xaxis:
			{
				categories: [" . implode(',', array_map(fn($c) => "'$c'", $reverseOrder ? array_reverse($categories) : $categories)) . "],
				labels: {formatter: function (val) { return val; }}
			},
			yaxis: { title: { text: undefined }},
			tooltip: {y: { formatter: function (val) { return val; }}},
			fill:
			{
				opacity: 1,
				colors: ['#74d14c', '#d63a49']
			}
		};
		new ApexCharts(document.querySelector('#" . $id . "'), options).render();";
	}
}
