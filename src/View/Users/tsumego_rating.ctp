	<?php
	if($lightDark=='dark'){
		$lightDarkChartColor = '#fff';
		$lightDarkChartColor2 = '#3e3e3e';
	}else{
		$lightDarkChartColor = '#000';
		$lightDarkChartColor2 = '#ddd';
	}
	?>

	<div id="chartContainer">
	<br>
		<div><a href="/tsumegos/play/<?php echo $id; ?>">back</a>
		<?php 
		//echo '<pre>'; print_r($ta[0]['TsumegoAttempt']['tsumego_elo']); echo '</pre>'; 
		//echo '<pre>'; print_r($ta[count($ta)-1]['TsumegoAttempt']['tsumego_elo']); echo '</pre>'; 
		 ?>
		</div>
		<div id="chart2"></div>
	</div>
	<div align="center">
		<?php
			if(count($ta)==0)
				echo '<i>No rating changes found.</i><br><br>';
			echo 'Rating: '.$rating;
		?>
	</div>
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
		let graph2dates = [];
		let graph2Ranks = [];
		<?php
		$currentGdate = 0;
		for($i=0;$i<count($ta);$i++){
			$gDate = new DateTime($ta[$i]['TsumegoAttempt']['created']);
			$gDate = $gDate->format('d.m.y');
			if($gDate!=$currentGdate){
				$currentGdate = $gDate;
				echo 'graph2dates.push("'.$currentGdate.'");';
			}else
				echo 'graph2dates.push("");';
			echo 'graph2Ranks.push("'.$ta[$i]['TsumegoAttempt']['tsumego_elo'].'");';
		}
		?>
        var options = {
          series: [{
            name: "Rating",
            data: graph2Ranks,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
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
          text: "Rating history of <?php echo $name; ?>",
          align: 'left'
        },
        grid: {
          row: {
            colors: ["<?php echo $lightDarkChartColor2; ?>", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: graph2dates
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart2"), options);
        chart.render();
    </script>