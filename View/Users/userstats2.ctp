<?php
	if(isset($_SESSION['loggedInUser'])){
		if($_SESSION['loggedInUser']['User']['id']!=72 && $_SESSION['loggedInUser']['User']['id']!=1543 && $_SESSION['loggedInUser']['User']['id']!=1565){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}

	
	if($noIndex) echo '<div align="center"><a style="color:black;text-decoration:none;" href="/users/userstats/">back</a></div>';
	
	echo '<div align="center">';
	
	echo '<pre>';print_r($performance);echo '</pre>';
	
	?>
	
	<table width="85%" border="0" class="userstatsstbale">
	<tr>
		<th>category</th>
		<th>count</th>
		<th>percent</th>
	</tr>
	<?php
		$category = 10;
		for($i=0; $i<9; $i++){
				
				echo '<tr>';
					echo '<td>';
						echo $category;
					echo '</td>';
					echo '<td>';
						echo $performance['p'.$category.'S'].'/'.$performance['p'.$category];
					echo '</td>';
					echo '<td>';
						$val = $performance['p'.$category.'S'] / $performance['p'.$category];
						$val*=100;
						$val = round($val,2);
						echo $val.'%';
					echo '</td>';
				echo '</tr>';
			$category += 10;
		}
	
	?>
	</table>
	
	<table width="85%" border="0" class="userstatsstbale">
	<tr>
		<th>#</th>
		<th>user-id</th>
		<th>name</th>
		<th>time spent</th>
		<th>xp</th>
		<th>result</th>
		<th style="text-align:right">tsumego</th>
		<th></th>
		<th>tsumego-id</th>
		<th>date/time</th>
	</tr>
	<?php
		$px = 0;
		for($i=0; $i<count($ur); $i++){
			//if($ur[$i]['TsumegoAttempt']['tsumego_xp']==40){
			if(true){
				$px++;
				echo '<tr>';
					echo '<td>';
						echo $px;
					echo '</td>';
					echo '<td>';
						echo '<a style="color:black;" href="/users/userstats/'.$ur[$i]['TsumegoAttempt']['user_id'].'">'.$ur[$i]['TsumegoAttempt']['user_id'].'</a>';
					echo '</td>';
					echo '<td style="text-align:center">';
						echo '<a style="color:black;text-decoration:none;" href="/users/userstats/'.$ur[$i]['TsumegoAttempt']['user_id'].'">'.$ur[$i]['TsumegoAttempt']['user_name'].'</a>';
					echo '</td>';
					echo '<td>';
						echo $ur[$i]['TsumegoAttempt']['seconds'];
					echo '</td>';
					echo '<td>';
						echo $ur[$i]['TsumegoAttempt']['tsumego_xp'];
					echo '</td>';
					echo '<td>';
						echo $ur[$i]['TsumegoAttempt']['solved'];
					echo '</td>';
					echo '<td style="text-align:right">';
						if(strlen($ur[$i]['TsumegoAttempt']['set_name'])>=30) $ur[$i]['TsumegoAttempt']['set_name'] = substr($ur[$i]['TsumegoAttempt']['set_name'], 0, 30);
						echo $ur[$i]['TsumegoAttempt']['set_name'];
					echo '</td>';
					
					echo '<td>';
						echo $ur[$i]['TsumegoAttempt']['tsumego_num'];
					echo '</td>';
					echo '<td>';
						echo 'id <a style="color:black;" target="_blank"
						href="/tsumegos/play/'.$ur[$i]['TsumegoAttempt']['tsumego_id'].'">
						'. $ur[$i]['TsumegoAttempt']['tsumego_id'].'</a>';
					echo '</td>';
					echo '<td>';
						echo $ur[$i]['TsumegoAttempt']['created'];
					echo '</td>';
				echo '</tr>';
			}	
		}
	
	?>
	</table>
	<?php
	//echo '<pre>';print_r($ur);echo '</pre>';
	echo $px;
	echo '<br></div>';
	
	
	
?>