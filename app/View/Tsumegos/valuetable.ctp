
	<?php
		/*
		echo '<pre>'; print_r($win); echo '</pre>';
		echo '<pre>'; print_r($loss); echo '</pre>';
		echo '<pre>'; print_r($win2); echo '</pre>';
		echo '<pre>'; print_r($loss2); echo '</pre>';
		echo '<pre>'; print_r($win3); echo '</pre>';
		echo '<pre>'; print_r($loss3); echo '</pre>';
		echo '<pre>'; print_r($win4); echo '</pre>';
		echo '<pre>'; print_r($loss4); echo '</pre>';
		*/
	?>
	<div align="left">
		<div align="center"><h2>Calculations for the Tsumego Hero rating system</h2></div><br>
		<h1>This page lists the rating changes for all user vs. Tsumego plays. The increase should become weaker with higher
		values. For this behavior, logarithmic functions are implemented.</h1>
		<h1>X-axis: Rating difference to the problem; Example: User rating is 600 (15k), Tsumego rating is 1600 (5k), the difference is -1000 (+11/-1)</h1>
		<table>
			<tr>
				<td>Formula for user wins, user has lower rating:</td><td><img src="/img/uWins-tBigger.PNG"></td>
				<td>Formula for user wins, user has higher rating:</td><td><img src="/img/uWins-uBigger.PNG"></td>
			</tr>
		</table>
		<div id="chartA"></div>
	</div>
	<div align="left">
		<h1>You can see here a small bias that is implemented in favor of the user. A 50/50 solved/fail ratio is still rewarded with a small gain.</h1>
		<table>
			<tr>
				<td>Formula for user loses, user has lower rating:</td><td><img src="/img/asdfasdf.PNG"></td>
				<td>Formula for user loses, user has higher rating:</td><td><img src="/img/uloss-uBigger.PNG"></td>
			</tr>
		</table>
		<div id="chartB"></div>
	</div>
	<div align="left">
		<h1>Tsumego rating variance. Example: Tsumego rating is 600 (15k), user rating is 1600 (5k), the difference is 1000 (+5/-0)</h1>
		<h1>Only unexpected outcomes are interesting and have a value above zero. (Tsumego has higer rating but loses, Tsumego has lower rating but wins)</h1>
		<table>
			<tr>
				<td>Formula for Tsumego wins, Tsumego has lower rating:</td><td><img src="/img/tWinLoss.PNG"></td>
			</tr>
		</table>
		<div id="chartT2"></div>
	</div>
	<div align="left">
		<table>
			<tr>
				<td>Formula for Tsumego loses, Tsumego has higher rating:</td><td><img src="/img/tWinLoss.PNG"></td>
			</tr>
		</table>
		<div id="chartT1"></div>
	</div>
	<div align="left">
		<h1>For the user rating, another parameter has to be considered, which is the activity value. This value determines how active the user is
		and also changes the rating variance to zero if the problem has been recently solved. The activity value takes the 90 last played probelms
		in any mode, takes the hours for each problem that have passed since the attempt and calculates the average value.</h1>
		<table border="1" class="avgTimePassed">
			<tr>
				<th>Avg. time passed of the last 90 problems</th>
				<th>User type</th>
				<th>Activity value multiplyer</th>
			</tr>
			<tr>
				<td style="text-align:right;"><30 hrs</td>
				<td>high activity</td>
				<td>1</td>
			</tr>
			<tr>
				<td style="text-align:right;"><50 hrs</td>
				<td>average activity</td>
				<td>1.6</td>
			</tr>
			<tr>
				<td style="text-align:right;"><70 hrs</td>
				<td>low activity</td>
				<td>2.2</td>
			</tr>
			<tr>
				<td style="text-align:right;">>=70 hrs</td>
				<td>very low activity</td>
				<td>2.8</td>
			</tr>
		</table>
		<table>
			<tr>
				<td>Formula for user wins, user has lower rating:</td><td><img src="/img/uWins-tBigger2.PNG"></td>
				<td>Formula for user wins, user has higher rating:</td><td><img src="/img/uWins-uBigger2.PNG"></td>
			</tr>
		</table>
		<h1>The x-axis is again the rating difference from user to tsumego.</h1>
		<div id="chartA2"></div>
	</div>
	<div align="left">
		<table>
			<tr>
				<td>Formula for user loses, user has lower rating:</td><td><img src="/img/uloss-tBigger2.PNG"></td>
				<td>Formula for user loses, user has higher rating:</td><td><img src="/img/uloss-uBigger2.PNG"></td>
			</tr>
		</table>
		<div id="chartB2"></div>
	</div>
	<div align="left">
		<div id="chartA3"></div>
	</div>
	<div align="left">
		<div id="chartB3"></div>
	</div>
	<div align="left">
		<div id="chartA4"></div>
	</div>
	<div align="left">
		<div id="chartB4"></div>
	</div>
	<div align="left">
		<h2>Improved conversion of rating to expierience points (xp)</h2>
		<h1>
			When looking at the xp that a user can generate while
			solving easy problems in comparision to solving difficult problems, it was more rewarding to solve easy problems. However, it makes more sense 
			to reward solving difficult problems. The solution is to use an exponential function instead of a linear function, which means, higher values 
			have a higher increment.</h1>
		<table>
			<tr>
				<td>The old xp to rating calculation was approximately rating/29 = xp.</td><td><img src="/img/ratingToXp1.PNG"></td>
				<td>New formula for rating to xp calculation:</td><td><img src="/img/ratingToXp2.PNG"></td>
			</tr>
		</table>
		<img src="/img/ratingToXpGraph.PNG" width="100%">
		<h1>Example 1: A problem with a rating of 2100 (1d) gives 106 xp while it was giving 72 xp before.</h1>
		<h1>Example 2: A problem with a rating of 800 (13k) gives 19 xp while it was giving 27 xp before.</h1>
	</div>
	<br><br><br><br>
	<script>
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js"><\/script>'
        )
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/eligrey-classlist-js-polyfill@1.2.20171210/classList.min.js"><\/script>'
        )
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/findindex_polyfill_mdn"><\/script>'
        )
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
	 <script>
      // Replace Math.random() with a pseudo-random number generator to get reproducible results in e2e tests
      // Based on https://gist.github.com/blixt/f17b47c62508be59987b
      var _seed = 42;
      Math.random = function() {
        _seed = _seed * 16807 % 2147483647;
        return (_seed - 1) / 2147483646;
      };
    </script>
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let elo = [];
		let variance = [];
		<?php
		foreach ($win as $k => $v){
			echo 'elo.push('.$k.');';
			echo 'variance.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: variance,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User wins (high activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: elo
        },
		yaxis: {
		  min: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartA"), options);
        chart.render();
    </script>
	<script>
		let elo2 = [];
		let variance2 = [];
		<?php
		foreach ($loss as $k => $v){
			echo 'elo2.push('.$k.');';
			echo 'variance2.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: variance2,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User loses (high activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: elo2
        },
		yaxis: {
		  min: -12,
		  max: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartB"), options);
        chart.render();
    </script>
	
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let elo4 = [];
		let variance4 = [];
		<?php
		foreach ($tloss as $k => $v){
			echo 'elo4.push('.$k.');';
			echo 'variance4.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: variance4,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'Tsumego wins',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: elo4
        },
		yaxis: {
		  min: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartT2"), options);
        chart.render();
    </script>
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let elo3 = [];
		let variance3 = [];
		<?php
		foreach ($twin as $k => $v){
			echo 'elo3.push('.$k.');';
			echo 'variance3.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: variance3,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'Tsumego loses',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: elo3
        },
		yaxis: {
		   min: -100,
		  max: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartT1"), options);
        chart.render();
    </script>
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let eloA2 = [];
		let varianceA2 = [];
		<?php
		foreach ($win2 as $k => $v){
			echo 'eloA2.push('.$k.');';
			echo 'varianceA2.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceA2,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User wins (average activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloA2
        },
		yaxis: {
		  min: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartA2"), options);
        chart.render();
    </script>
	<script>
		let eloB2 = [];
		let varianceB2 = [];
		<?php
		foreach ($loss2 as $k => $v){
			echo 'eloB2.push('.$k.');';
			echo 'varianceB2.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceB2,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User loses (average activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloB2
        },
		yaxis: {
		  max: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartB2"), options);
        chart.render();
    </script>
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let eloA3 = [];
		let varianceA3 = [];
		<?php
		foreach ($win3 as $k => $v){
			echo 'eloA3.push('.$k.');';
			echo 'varianceA3.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceA3,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User wins (low activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloA3
        },
		yaxis: {
		  min: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartA3"), options);
        chart.render();
    </script>
	<script>
		let eloB3 = [];
		let varianceB3 = [];
		<?php
		foreach ($loss3 as $k => $v){
			echo 'eloB3.push('.$k.');';
			echo 'varianceB3.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceB3,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User loses (low activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloB3
        },
		yaxis: {
		  max: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartB3"), options);
        chart.render();
    </script>
	<script>
		document.cookie = "lightDark=light;SameSite=none;Secure=false";
		let eloA4 = [];
		let varianceA4 = [];
		<?php
		foreach ($win4 as $k => $v){
			echo 'eloA4.push('.$k.');';
			echo 'varianceA4.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceA4,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User wins (very low activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloA4
        },
		yaxis: {
		  min: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartA4"), options);
        chart.render();
    </script>
	<script>
		let eloB4 = [];
		let varianceB4 = [];
		<?php
		foreach ($loss4 as $k => $v){
			echo 'eloB4.push('.$k.');';
			echo 'varianceB4.push('.$v.');';
		}
		
		?>
        var options = {
          series: [{
            name: "Rating change",
            data: varianceB4,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          width: 1200,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'User loses (very low activity)',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["#ccc", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: eloB4
        },
		yaxis: {
		  max: 0
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chartB4"), options);
        chart.render();
    </script>
	<style>
	.new-button-time-inactive{
		cursor:pointer;
	}
	.userMain1{
		width:100%;
		height:138px;
		margin: 0 4px;
	}
	.userMain2{
		width:100%;
		height:217px;
		margin: 0 4px;
	}
	.userTop1{
		float:left;
		border: 3px solid #caa8d8;
		margin:2px;
		width:310px;
		height:182px;
	}
	.userTop2{
		float:left;
		border: 3px solid #74d14c;
		margin:2px;
		width:310px;
		height:135px;
	}
	.userTopTable1 td{
		vertical-align:top;padding:7px;
		text-align:left;
		width:50%;
	}
	</style>