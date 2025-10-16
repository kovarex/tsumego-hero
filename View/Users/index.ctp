
	<div class="homeRight">
		<?php
			if(!isset($_SESSION['loggedInUser'])){
		?>
		
		<p class="title4">Please <a href="/users/add">Sign Up</a>!</p><br>
		This website is designed for a great user expierience by 
		keeping track of your progress, providing 
		rewards and making go problems fun.
		To access all the features, please consider creating an account.

		<br><br>
		<div align="center">
		<table border="0">
			<tr>
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss5.png" alt="Level System" title="Level System" width="110px"><br>Level System
				</td>			
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss4.png" alt="More Hearts" title="More Hearts" width="110px"><br>More Hearts
				</td>
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss3.png" alt="Hero Powers" title="Hero Powers" width="110px"><br>Hero Powers
				</td>
			</tr>
			<tr>
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss2.png" alt="Visited Progress" title="Visited Progress" width="110px"><br>Visited Progress
				</td>
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss1.png" alt="Solved Progress" title="Solved Progress" width="110px"><br>Solved Progress
				</td>			
				<td class="pleaseRegisterTable" align="center" height="110px">
					<img src="/img/ss6.png" alt="Highscore" title="Highscore" width="110px"><br>Highscore
				</td>
				
			</tr>
		</table>
		</div>
		<br>
		<?php
			}
			if(isset($_SESSION['loggedInUser'])){
			//if($_SESSION['loggedInUser']['User']['completed']==1){
			if(false){
			//if($_SESSION['loggedInUser']['User']['id']==72){
			//echo '<pre>';print_r($ux);echo '</pre>';
			?>
			<div id="sandboxVolunteers2">
				<p class="title4">Sandbox</p>
				<br>
				<div align="center"><font size="4px">
				There are new problems in the sandbox.</font>
				<table border="0" width="300px">
				<tr>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
					<a href="/sets/beta" style="color:black;"><font size="4px">Enter</font><br></a>
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
				</td>
				</tr>
				</table>
				</div>
			</div>
			<?php 
			}
			
			if(false){
			?>
			<div id="sandboxVolunteers">
				<p class="title4">Sandbox Volunteers</p>
				<br>
				The Sandbox is an effort to increase the quality of the problems on Tsumego Hero. 
				All problems are there for trial, before they get published. A small group 
				of users has access. Their task is to solve them and comment if they find a mistake. 
				<br><br>
				<div align="center">
				Would you like to have early access to not published problems? 
				<table border="0" width="300px">
				<tr>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
					<a href="#" id="sandboxY" onclick="sandboxY(); return false;" style="color:black;">Yes</a>
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
					<a href="#" id="sandboxN" onclick="sandboxN(); return false;" style="color:black;">No</a>
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
					<a href="#" id="sandboxM" onclick="sandboxM(); return false;" style="color:black;">Maybe</a>
				</td>
				</tr>
				</table>
				</div>
				<br>
			</div>
			<?php 
			}
			
			if(false){
			?>
			<div id="sandboxVolunteers2">
				<p class="title4">Sandbox Volunteers</p>
				<br>
				<div align="center"><font size="4px">
				You have been invited.</font>
				<table border="0" width="300px">
				<tr>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
					<a href="/sets/beta" style="color:black;"><font size="4px">Enter</font><br></a>
				</td>
				<td width="33%" style="text-align:center;font-size:17px;font-weight:800;">
				</td>
				</tr>
				</table>
				</div>
				
			</div>
			<?php 
			}
			/*if(isset($_SESSION['loggedInUser'])){
			if($_SESSION['loggedInUser']['User']['completed']==1){
			?>
			
			<div >
				<p class="title4">Sandbox</p>
				<br>
				<div align="left"><font size="4px">
				There are new problems in the <a href="/sets/beta">Sandbox</a>, please check.
				<br><br>
				</font>
				
				</div>
			</div>
			
			<?php
			}
			}*/
			?>
			<p class="title4">Your last Activities</p>
			<br>
			<?php 
				echo '<a style="color:#000;" href="/sets/view/'.$visit1['Set']['id'].'">
					<b>'.$visit1['Set']['title'];
					if($visit1['Set']['title2']!=null) echo ', '.$visit1['Set']['title2'];
				echo '</b></a><br>'; 
				if(isset($visit2)){
					echo '<a style="color:#000;" href="/sets/view/'.$visit2['Set']['id'].'">
						<b>'.$visit2['Set']['title'];
						if($visit2['Set']['title2']!=null) echo ', '.$visit2['Set']['title2'];
					echo '</b></a><br>'; 
					if(isset($visit3)){
						echo '<a style="color:#000;" href="/sets/view/'.$visit3['Set']['id'].'">
							<b>'.$visit3['Set']['title'];
							if($visit3['Set']['title2']!=null) echo ', '.$visit3['Set']['title2'];
						echo '</b></a><br>'; 
					}
				}
			?>
			<br>
			<?php		
		}	
			
		?>
		
		<p class="title4">Recently added</p>
		<br>
		<?php
			$currentSet = 50;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-24 22:00:00'){
				if(
				$ts[$i]['Tsumego']['set_id'] == $currentSet ||
				$ts[$i]['Tsumego']['set_id'] == 52 ||
				$ts[$i]['Tsumego']['set_id'] == 53 ||
				$ts[$i]['Tsumego']['set_id'] == 54 
				){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 24. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br>';
			$currentSet = 65;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-24 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 24. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br>';
			$currentSet = 66;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-24 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 24. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br>';
			$currentSet = 127;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-17 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 17. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
			$currentSet = 115;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-17 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 17. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br>';
			
			$currentSet = 124;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-10 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 10. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br>';
			$currentSet = 115;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-05-03 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 3. May 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
			$currentSet = 115;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-04-26 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 26. April 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 122;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-04-19 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 19. April 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 115;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-04-05 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 5. April 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 114;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-03-29 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 29. March 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br>';
		
			$currentSet = 124;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-03-22 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 22. March 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 114;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-03-15 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 15. March 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 122;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-03-08 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 8. March 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 114;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-03-01 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 1. March 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 122;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-02-23 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 23. February 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 66;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-02-16 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 16. February 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			''.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br><br><br>';
		
			$currentSet = 115;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-02-09 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 9. February 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			' '.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
			echo '<br><br><br><br><br><br>';
			$currentSet = 117;
			$newTs = array();
			$newTsMap = array();
			for($i=0; $i<count($ts); $i++){
				if($ts[$i]['Tsumego']['created'] == '2020-02-02 22:00:00'){
				if($ts[$i]['Tsumego']['set_id'] == $currentSet){
					array_push($newTs, $ts[$i]);
					array_push($newTsMap, $ts[$i]['Tsumego']['num']);
				}	
				}
			}
			asort($newTsMap);
			$newTsMap = array_values($newTsMap);
			
			echo '<font color="grey">Added on Sunday, 2. February 2020:</font><br>';
			echo '<a style="color:#000;" href="/sets/view/'.$setNames[$currentSet]['Set']['id'].'"><b>'.$setNames[$currentSet]['Set']['title'].
			''.$setNames[$currentSet]['Set']['title2'].
			'</b></a> - '.count($newTs).' Problems<br><br>';
			
			for($i=count($newTs)-1; $i>=0; $i--){
				if(!isset($newTs[$i]['Tsumego']['status'])) $newTs[$i]['Tsumego']['status'] = 'N';
			}
			for($i=0; $i<count($newTsMap); $i++){
				for($j=0; $j<count($newTs); $j++){
					if($newTs[$j]['Tsumego']['num']==$newTsMap[$i]){
						echo '
							<li class="set'.$newTs[$j]['Tsumego']['status'].'1">
								<a href="/tsumegos/play/'.$newTs[$j]['Tsumego']['id'].'">'.$newTs[$j]['Tsumego']['num'].'</a>
							</li>
						';
					}
				}
			}
		?>
	</div>
	
	<div class="homeLeft">
		<p class="title4 title4x">Proverb of the Day</p>
		<p class="title4date">
		<?php
			echo $d1;
		?>
		</p>
		<br>
		
		<?php
			echo '<img src="/img/'.$quote.'.PNG" width="100%" alt="Tsumego Hero Proverb of the Day" title="Tsumego Hero Proverb of the Day">';
		?>
		<br>
		<?php
			if(isset($_SESSION['loggedInUser'])){
				if($_SESSION['loggedInUser']['User']['id']==72){
					echo '<div style="position:absolute;top:60px;left:28px;">';
					echo '<a href="/users/stats">Page Stats</a><br>';
					echo '<a href="/users/userstats">User Stats</a><br>';
					echo '<a href="/users/adminstats">Admin Stats</a><br>';
					//echo '<pre>';print_r($asdf);echo '</pre>';
					echo '</div>';
				}
				if($_SESSION['loggedInUser']['User']['id']==2781 || $_SESSION['loggedInUser']['User']['id']==1206){
					echo '<div style="position:absolute;top:60px;left:28px;">';
					echo '<a href="/users/adminstats">Admin Stats</a><br>';
					echo '</div>';
				}
				if($_SESSION['loggedInUser']['User']['id']==1543){
					echo '<div style="position:absolute;top:60px;left:28px;">';
					echo '<a href="/users/userstats">User Stats</a><br>';
					echo '</div>';
				}
			}
		?><br>
		<p class="title4" style="margin-bottom:7px;">User of the Day </p>
		
		<div class="uotd mid uotd<?php echo $uotdbg; ?> mid<?php echo $uotdbg; ?> ">
		  <h2 <?php if(strlen($userOfTheDay)>=10) echo 'class="midLongName1"'; ?>><?php echo $userOfTheDay; ?></h2>
		</div>
		<p class="uotdmargin">&nbsp;</p><br>
		
		
		<?php 
			if(isset($loggedInUser)){	
				echo '
					<p class="title4xx">Restoration Countdown</p>
					<br>
					<font size="5px">
						<div id="homeCountdown"></div>
					<br>
					</font>
				';
				
				$asdf = '';
			}else{
				$asdf = 'xx';
			}
		?>
		
		<p class="title4<?php echo $asdf; ?>">Hero Powers</p>
		<br>
		<table class="sitesTable">
		<tr>
			<td>
				<img id="sprint" title="Sprint: Double XP for 2 minutes" alt="Sprint" src="/img/hp1.png">
			</td>
			<td>
				<b>Sprint (Level 20)</b><br>
				Double XP for 2 minutes on all solved problems.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img id="sprint" title="Sprint: Double XP for 2 minutes" alt="Sprint" src="/img/hp2.png">
			</td>
			<td>
				<b>Intuition (Level 30)</b><br>
				Shows the first correct move.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img id="sprint" title="Sprint: Double XP for 2 minutes" alt="Sprint" src="/img/hp3.png">
			</td>
			<td>
				<b>Rejuvenation (Level 40)</b><br>
				Restores health, Intuition and locks.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img id="sprint" title="Sprint: Double XP for 2 minutes" alt="Sprint" src="/img/hp4.png">
			</td>
			<td>
				<b>Refinement (Level 100 or Premium)</b><br>
				Gives you a chance to solve a golden tsumego.
			</td>
		</tr>
		</table>
		<br>
		
		<p class="title4">Problem Colors</p>
		<br>
		<table class="sitesTable">
		<tr>
			<td>
				<img title="Not visited" alt="Not visited" src="/img/xN.PNG">
			</td>
			<td>
				<b>Not visited</b><br>
				You haven't seen this problem.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img title="Visited" alt="Visited" src="/img/xV.PNG">
			</td>
			<td>
				<b>Visited</b><br>
				You have seen this problem, but not solved.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img title="Solved" alt="Solved" src="/img/xS.PNG">
			</td>
			<td>
				<b>Solved</b><br>
				You solved this problem.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img title="Locked" alt="Locked" src="/img/xF.PNG">
			</td>
			<td>
				<b>Locked</b><br>
				This problem is locked for today. Problems get locked when a player misplays and has no more hearts left.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img title="Half XP" alt="Half XP" src="/img/xW.PNG">
			</td>
			<td>
				<b>Half XP</b><br>
				This problem gives half XP. It becomes available one week after the first solution.<br><br>
			</td>
		</tr>
		<tr>
			<td>
				<img title="Golden" alt="Golden" src="/img/xG.PNG">
			</td>
			<td>
				<b>Golden</b><br>
				This is a golden tsumego. It gives eight times more XP than usual. If you fail, it disappears.<br><br>
			</td>
		</tr>
		</table>
		<br>
		<?php if(isset($_SESSION['loggedInUser'])){ ?>
		<?php if($_SESSION['loggedInUser']['User']['premium']==0 && $user['User']['id']!=1165){ ?>
			<p class="title4">Donations</p><br>
			<div align="center"><a href="/users/donate"><img id="donateH" onmouseover="donateHover()" onmouseout="donateNoHover()" width="180px" src="/img/donateButton1.png"></a><br>
			Get access to <a href="/users/donate">Tsumego Hero Premium</a>.<br><br>
			</div>
		<?php }else{ ?>
			
		<?php } ?>
		<?php }else{ ?>
			<p class="title4xx">Donations</p><br>
			<div align="center"><a href="/users/donate"><img id="donateH" onmouseover="donateHover()" onmouseout="donateNoHover()" width="180px" src="/img/donateButton1.png"></a><br>
			Get access to <a href="/users/donate">Tsumego Hero Premium</a>.<br><br>
			</div>
		<?php } ?>
		<p class="title4">Problem Database Size </p>
		<?php
		$tsumegoDates = array();
		for($j=0; $j<count($tsumegos); $j++){
			$date = date_create($tsumegos[$j]);
			array_push($tsumegoDates, date_format($date,"Y-m-d"));
		}
		$tsumegoDates = array_count_values($tsumegoDates);
		$tsumegoDates['2018-02-07'] = 161;
		$tsumegoDates['2018-03-11'] = 151;
		$tsumegoDates['2018-04-10'] = 205;
		$tsumegoDates['2018-04-25'] = 204;
		$tsumegoDates['2018-05-04'] = 90;
		$dt = new DateTime();
		$dt = $dt->format('Y-m-d');
		if(!isset($tsumegoDates[$dt])){
			$tsumegoDates[$dt] = 0.1;
		}
		ksort($tsumegoDates);
		
		$td = array();
		reset($tsumegoDates);
		$nextDay = '';
		$c = 0;
		while(current($tsumegoDates)){
			if(key($tsumegoDates)!=$nextDay&&$nextDay!=''){
				while(key($tsumegoDates)!=$nextDay){
					$td[$c]['date'] = $nextDay;
					$td[$c]['num'] = 0;
					$c++;
					$nextDay = date_create($nextDay);
					$nextDay->modify('+1 day');
					$nextDay = date_format($nextDay,"Y-m-d");
				}
				
			}
			$nextDay = key($tsumegoDates);
			$nextDay = date_create($nextDay);
			$nextDay->modify('+1 day');
			$nextDay = date_format($nextDay,"Y-m-d");
		
			$td[$c]['date'] = key($tsumegoDates);
			$td[$c]['num'] = current($tsumegoDates);
			$c++;
			next($tsumegoDates);
		}
		
		$sum = 0;
		for($j=0; $j<count($td); $j++){
		
		
			$td[$j]['num'] = $td[$j]['num'] + $sum;
			$date = date_create($td[$j]['date']);
			
			if($date==date_create('2019-03-27')) $td[$j]['num'] -= 1530;
			if($date==date_create('2019-04-25')) $td[$j]['num'] -= 238;
			if($date==date_create('2019-05-01')) $td[$j]['num'] -= 32;
			if($date==date_create('2019-05-19')) $td[$j]['num'] -= 40;
			if($date==date_create('2020-02-22')) $td[$j]['num'] -= 432;
			$x = $td[$j]['num'];
			$sum = $x;
			
			
			//echo '<pre>';print_r($date);echo '</pre>';
			$td[$j]['y'] = $date->format('Y');
			$td[$j]['m'] = $date->format('m');
			$td[$j]['m'] = $td[$j]['m'] - 1;
			$td[$j]['d'] = $date->format('d');
			$td[$j]['d'] = $td[$j]['d'] * 1;
		}
		?>
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
						for($j=0; $j<count($td); $j++){
							$td[$j]['num'] = round($td[$j]['num']);
							echo '{ x: new Date('.$td[$j]['y'].', '.$td[$j]['m'].', '.$td[$j]['d'].'), y: '.$td[$j]['num'].' }';
							if($j!=count($td)-1) echo ',';
						}
					?>
				]
			}]
		});
		chart.render();

		}
		</script>
		<div id="chartContainer" style="height: 400px; width: 100%;"></div>
		<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
		<br>
		
		<p class="title4">Contributors</p>
		<br>
		Tsumego Hero is only in constant good shape, because there were users who helped creating the files for the problems.
		If you want to do some tsumego training and at the same time help the website by creating files, please message me. Help is much appreciated.<br><br>
		All contributors are listed here: <a href="/users/authors">Authors</a>
		<br><br>
		
		<p class="title4">Admins</p>
		<br>
		We are looking for more admins who can confidently answer comments in the discuss area.
		Ideally, there is a small group of active or semi-active admins that is interested in discussion, uploading problems or creating new collections.
		Let me know if you would like to be on the productive side of Tsumego Hero. (<a href="mailto:me@joschkazimdars.com">me@joschkazimdars.com</a>)
		
		<br><br>
		
		<p class="title4">Guide To Become Strong </p>
		<p class="titleBy">&nbsp;by Benjamin Teuber 6 Dan</p>
		
		<table border="0" class="newstable1">
		<tr>
			<td colspan="2">
			Many times I hear questions and requests like "How can I become strong?" or "My Go lacks this or that. 
			Please teach me how to be better at it!". In general, I think many people overestimate the role of 
			a Go-Teacher. Of course, it's very important to play and analyze with stronger players too, but still 
			the teacher is not everything. Most of the learning consists of exploring Go for yourself, and not by 
			having every single move explained.
			Actually, most part of my study in Japan did not consist of being taught by pros, but of studying by myself. 
			One big point of being next to professionals was that they explained how to do this.
			<br><br>
			For you, these lines mean that you don't have to go to Japan or find a 6-Dan teacher to become incredibly strong!!!
			 Instead, if you are ambitious, you just have to know what to do by yourself. This is why I decided to write this small tutorial.
			</td>
		</tr>
		</table><br>
		<h4>How to become strong (in order of importance)</h4>
		<table >
		<tr><td style="vertical-align:top;">1.</td><td>Play, play, play - the stronger your opponent the better for you</td></tr>
		<tr><td style="vertical-align:top;">2.</td><td> Do Tsumego in the right way continuously. Maybe this seems to be boring for you at first, but you'll see how much fun it is 
		once you start. It's very important how to do so!</td></tr>
		<tr><td style="vertical-align:top;">3.</td><td>Analyze your games with other players (as above, the stronger the better) - best would be to found a private study group 
		(ten eyes will find more than two or four...) </td></tr>
		<tr><td style="vertical-align:top;">4.</td><td>Do Tsumego</td></tr>
		<tr><td style="vertical-align:top;">5.</td><td>If you like, repeat and learn some pro games</td></tr>
		<tr><td style="vertical-align:top;">6.</td><td>More Tsumego</td></tr>
		<tr><td style="vertical-align:top;">7.</td><td>If you have some interesting book about fuseki, joseki, shape, endgame or whatever, read it if you enjoy - but don't spend too much time with it</td></tr>
		<tr><td style="vertical-align:top;">8.</td><td>If you still have time left, how about a few tsumego-problems?</td></tr>
		
		</table>
		<br>
		<font size="4px"><b>
		<a href="/sites/view/1">Read more >></a>
		</b></font>
		<br><br><br>
		
		<!--
		<p class="title4 ">Contributors</p>
		<br>
		<font size="5px">
		Timo (Timo Kreuzer)<br>
		Silent gentleman (саша черных)<br>
		Bradford (Bradford Malbon)<br>
		GoDave89 (David Ulbricht)<br> 
		okimoyo (Ryan Smith)<br>
		gobum<br>
		Andrey<br>
		<br>
		
		</font>
		
		<p class="title4 ">Donations in September</p>
		<br>
		<font size="5px">
		<table class="donatorsTable">
		<tr>
		<td>Contraband</td><td>21,00 €</td>
		</tr>
		<tr>
		<td>DuskEagle</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>og1L</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>Danglard</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>Tsultruim</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>GoGentleman</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>averell</td><td>20,00 €</td>
		</tr>
		<tr>
		<td>Sosnovsky (Juan Manuel)</td><td>10,00 €</td>
		</tr>
		<tr>
		<td>waste</td><td>10,00 €</td>
		</tr>
		<tr>
		<td>Paytonbigsby</td><td>10,00 €</td>
		</tr>
		</table>
		-->
		</font>
		<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
		<?php if(!isset($loggedInUser)) echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>'; ?>
	</div>
	
		
	
	<script>
		function donateHover(){
			document.getElementById("donateH").src = '/img/donateButton1h.png';
		}	
		function donateNoHover(){
			document.getElementById("donateH").src = "/img/donateButton1.png";
		}
		
		function sandboxY(){
			document.cookie = "sandbox=3";
			document.getElementById("sandboxVolunteers").style = "display:none;";
		}
		function sandboxN(){
			document.cookie = "sandbox=1";
			document.getElementById("sandboxVolunteers").style = "display:none;";
		}
		function sandboxM(){
			document.cookie = "sandbox=2";
			document.getElementById("sandboxVolunteers").style = "display:none;";
		}
	</script>
	
	
	
	
		