	<?php
	if(isset($_SESSION['loggedInUser'])){
		if($_SESSION['loggedInUser']['User']['isAdmin']<1){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	?>
	<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
	<div align="center">
	<p class="title">
		<br>
		Signed In Users Per Day
		<br><br> 
		</p>
		<div class="statsText1">
		This graph shows the number of users per day that were active on registered accounts. The number of users per day including 
		unregistered users is on average 2,5 times as high.
		
	</div>
	</div>
	<br>
	<br>
	<br>
	<div id="chartContainer" style="height: 600px; width: 100%;"></div>
	<br>
	<script>
		window.onload = function () {

		var chart = new CanvasJS.Chart("chartContainer", {
			animationEnabled: true,
			animationDuration: 1000,
			theme: "dark1",
			title:{
				text: "",
				fontSize: 20,
				 fontWeight: "lighter"
			},
		   axisX:{
				valueFormatString: "DD MMM YY",
				labelFontSize: 11
			},
			axisY:{
				includeZero: true,
				labelFontSize: 14
			},
			data: [{        
				type: "area", 
				color: "#d19fe4",
				fillOpacity: .7,
				lineThickness: 3,
				dataPoints: [
					<?php
						for($j=0; $j<count($a); $j++){
							$a[$j]['num'] = round($a[$j]['num']);
							echo '{ x: new Date('.$a[$j]['y'].', '.($a[$j]['m']-1).', '.$a[$j]['d'].'), y: '.$a[$j]['num'].' }';
							if($j!=count($a)-1) echo ',';
						}
					?>
				]
			}]
		});
		chart.render();

		}
		</script>
<?php //echo '<pre>'; print_r($a); echo '</pre>'; ?>