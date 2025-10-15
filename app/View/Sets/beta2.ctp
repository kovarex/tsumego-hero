
	<?php
	
	
		if(isset($_SESSION['loggedInUser'])){
			if($_SESSION['loggedInUser']['User']['completed']!=1){
				echo '<script type="text/javascript">window.location.href = "/";</script>';
			}	
		}else{
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}
	
	
		$active0 = '';
		$active1 = '';
		$active2 = '';
		$active3 = '';
		$active4 = '';
		
		
		if(isset($_SESSION['loggedInUser'])){
			if($_SESSION['loggedInUser']['User']['id']==72){
				//echo '<pre>';
				//print_r($test1);
				//echo '</pre>';
			}			
		}
		
	?>
	<div align="center" class="display1" style="padding-top:10px;">
		
	<div id="sandbox">
	<h4>Removed Collections</h4>
		<div align="left">
		Collections that you find here have been removed.<br>
		<table width="100%">
			<tr>
			<td>
				<a href="/sets/beta">Sandbox</a>
			</td>
			<td>
			</td>
			</tr>
			</table>
		</div>
	</div>	
	<section class="scontainer">
	  <div class="row">
		<div class="col-12@sm">
		  <div id="my-shuffle" class="items">
			<?php 
				for($i=0; $i<count($sets); $i++) {
					if($sets[$i]['Set']['solved']==0) $solvedBar = '000';
					else if($sets[$i]['Set']['solved']<2.5) $solvedBar = '025';
					else if($sets[$i]['Set']['solved']<5) $solvedBar = '050';
					else if($sets[$i]['Set']['solved']<7.5) $solvedBar = '075';
					else if($sets[$i]['Set']['solved']<10) $solvedBar = '010';
					else if($sets[$i]['Set']['solved']<12.5) $solvedBar = '125';
					else if($sets[$i]['Set']['solved']<15) $solvedBar = '150';
					else if($sets[$i]['Set']['solved']<17.5) $solvedBar = '175';
					else if($sets[$i]['Set']['solved']<20) $solvedBar = '200';
					else if($sets[$i]['Set']['solved']<22.5) $solvedBar = '225';
					else if($sets[$i]['Set']['solved']<25) $solvedBar = '250';
					else if($sets[$i]['Set']['solved']<27.5) $solvedBar = '275';
					else if($sets[$i]['Set']['solved']<30) $solvedBar = '300';
					else if($sets[$i]['Set']['solved']<32.5) $solvedBar = '325';
					else if($sets[$i]['Set']['solved']<35) $solvedBar = '350';
					else if($sets[$i]['Set']['solved']<37.5) $solvedBar = '375';
					else if($sets[$i]['Set']['solved']<40) $solvedBar = '400';
					else if($sets[$i]['Set']['solved']<42.5) $solvedBar = '425';
					else if($sets[$i]['Set']['solved']<45) $solvedBar = '450';
					else if($sets[$i]['Set']['solved']<47.5) $solvedBar = '475';
					else if($sets[$i]['Set']['solved']<50) $solvedBar = '500';
					else if($sets[$i]['Set']['solved']<52.5) $solvedBar = '525';
					else if($sets[$i]['Set']['solved']<55) $solvedBar = '550';
					else if($sets[$i]['Set']['solved']<57.5) $solvedBar = '575';
					else if($sets[$i]['Set']['solved']<60) $solvedBar = '600';
					else if($sets[$i]['Set']['solved']<62.5) $solvedBar = '625';
					else if($sets[$i]['Set']['solved']<65) $solvedBar = '650';
					else if($sets[$i]['Set']['solved']<67.5) $solvedBar = '675';
					else if($sets[$i]['Set']['solved']<70) $solvedBar = '700';
					else if($sets[$i]['Set']['solved']<72.5) $solvedBar = '725';
					else if($sets[$i]['Set']['solved']<75) $solvedBar = '750';
					else if($sets[$i]['Set']['solved']<77.5) $solvedBar = '775';
					else if($sets[$i]['Set']['solved']<80) $solvedBar = '800';
					else if($sets[$i]['Set']['solved']<82.5) $solvedBar = '825';
					else if($sets[$i]['Set']['solved']<85) $solvedBar = '850';
					else if($sets[$i]['Set']['solved']<87.5) $solvedBar = '850';
					else if($sets[$i]['Set']['solved']<90) $solvedBar = '875';
					else if($sets[$i]['Set']['solved']<92.5) $solvedBar = '900';
					else if($sets[$i]['Set']['solved']<95) $solvedBar = '925';
					else if($sets[$i]['Set']['solved']<97.5) $solvedBar = '950';
					else if($sets[$i]['Set']['solved']<100) $solvedBar = '975';
					else $solvedBar = '100';
					
					if($sets[$i]['Set']['id']==11969 || $sets[$i]['Set']['id']==29156 || $sets[$i]['Set']['id']==31813 || $sets[$i]['Set']['id']==33007 
					|| $sets[$i]['Set']['id']==71790 || $sets[$i]['Set']['id']==74761 || $sets[$i]['Set']['id']==81578 || $sets[$i]['Set']['id']==88156){
						$secretAreaBg = 'sa';
					}else{
						$secretAreaBg = '';
					}
					$s = 's';
					if($sets[$i]['Set']['id']==31813) $s = '';
					
					if($sets[$i]['Set']['solved']==100) $completed=' Completed';
					else $completed='';
					echo '
						<div id="set'.$sets[$i]['Set']['id'].'" class="box box'.$solvedBar.$secretAreaBg.' '.$completed.'" data-reviews="'.$sets[$i]['Set']['created'].'" data-problems="'.$sets[$i]['Set']['anz'].'" 
						data-difficulty="'.$sets[$i]['Set']['difficulty'].'" data-solved="'.$sets[$i]['Set']['solved'].'" 
						style="background-color:'.$sets[$i]['Set']['topicColor'].';">
							<a href="/sets/view/'.$sets[$i]['Set']['id'].'" style="text-decoration:none;color:white;">
								<div style="text-decoration:none;">
									<p class="title">'.$sets[$i]['Set']['title'].'</p>
									<p class="titleBy">by '.$sets[$i]['Set']['author'].'</p> 
									<b>'.$sets[$i]['Set']['title2'].'</b>';
									if($sets[$i]['Set']['id']!=58 && $sets[$i]['Set']['id']!=68) echo '<br><br>';
									else echo '<br>';
									echo '<table border="0" width="100%" style="padding-top:9px;">
										<tr>
											<td width="50%">
												<div class="display1" align="center"><b>'.$sets[$i]['Set']['anz'].' Problem'.$s.'<br>Solved: '.$sets[$i]['Set']['solved'].'%</b></div>
											</td>
											<td>
												<div align="center" style="padding-top:6px;"><b>Difficulty:<br>
												<img src="/img/stars'.$sets[$i]['Set']['difficulty'].'.png" width="130px"
												alt="Tsumego difficulty: '. $sets[$i]['Set']['difficulty'].'" title="Tsumego difficulty: '. $sets[$i]['Set']['difficulty'].'"></div></b>
											</td>
										</tr>
									</table>
									<br>
								</div>
							</a>
						</div>
						
					';
				}
			?>
		  </div>
		</div>
	  </div>
	</section>
	<br><br>
	</div>
	
	<script>
		function topicColor() {
			<?php
				for($i=0; $i<count($sets); $i++) {
					echo '
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.transition = "all 1s"; 
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.backgroundColor = "'.$sets[$i]['Set']['topicColor'].'";
					';
				}
			?>
			
			var btnContainer = document.getElementById("sorter2");
			var btns = btnContainer.getElementsByClassName("btn");
			for (var i = 0; i < btns.length; i++){
				btns[i].className = btns[i].className.replace("btn active","btn");
			}
			btns[0].className = btns[0].className.replace("btn","btn active");
		}
		function solvedColor() {
			<?php
				for($i=0; $i<count($sets); $i++) {
					echo '
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.transition = "all 1s"; 
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.backgroundColor = "'.$sets[$i]['Set']['solvedColor'].'";
					';
				}
			?>
			
			
			var btnContainer = document.getElementById("sorter2");
			var btns = btnContainer.getElementsByClassName("btn");
			for (var i = 0; i < btns.length; i++){
				btns[i].className = btns[i].className.replace("btn active","btn");
			}
			btns[1].className = btns[1].className.replace("btn","btn active");
			
		}
		
		function difficultyColor() {
			<?php
				for($i=0; $i<count($sets); $i++) {
					echo '
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.transition = "all 1s"; 
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.backgroundColor = "'.$sets[$i]['Set']['difficultyColor'].'";
					';
				}
			?>
			
			var btnContainer = document.getElementById("sorter2");
			var btns = btnContainer.getElementsByClassName("btn");
			for (var i = 0; i < btns.length; i++){
				btns[i].className = btns[i].className.replace("btn active","btn");
			}
			btns[2].className = btns[2].className.replace("btn","btn active");
		}
		function sizeColor() {
			<?php
				for($i=0; $i<count($sets); $i++) {
					echo '
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.transition = "all 1s"; 
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.backgroundColor = "'.$sets[$i]['Set']['sizeColor'].'";
					';
				}
			?>
			
			var btnContainer = document.getElementById("sorter2");
			var btns = btnContainer.getElementsByClassName("btn");
			for (var i = 0; i < btns.length; i++){
				btns[i].className = btns[i].className.replace("btn active","btn");
			}
			btns[3].className = btns[3].className.replace("btn","btn active");
		}
		function dateColor() {
			<?php
				for($i=0; $i<count($sets); $i++) {
					echo '
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.transition = "all 1s"; 
						document.getElementById("set'.$sets[$i]['Set']['id'].'").style.backgroundColor = "'.$sets[$i]['Set']['dateColor'].'";
					';
				}
			?>
			
			var btnContainer = document.getElementById("sorter2");
			var btns = btnContainer.getElementsByClassName("btn");
			for (var i = 0; i < btns.length; i++){
				btns[i].className = btns[i].className.replace("btn active","btn");
			}
			btns[4].className = btns[4].className.replace("btn","btn active");
		}
	</script>

