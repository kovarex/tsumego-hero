<?php

/**
 * @var View $this
 * @var int $count
 * @var bool $noIndex
 * @var int $penalty
 * @var int $uid
 * @var array $ur
 */

	
	if($noIndex) echo '<div align="center"><a class="link-plain" href="/users/userstats/">back</a></div>';
	
	
	if($uid==99){
		$diabolical = array();
		$diabolical[1] = array();
		$diabolical[2] = array();
		$diabolical[3] = array();
		$diabolical[4] = array();
		$diabolical[5] = array();
		$diabolical[6] = array();
		$diabolical[7] = array();
		$diabolical[8] = array();
		$diabolical[9] = array();
		$diabolical[10] = array();
		for($i=0; $i<count($ur); $i++){
			if($ur[$i]['TsumegoAttempt']['tsumego_id']==19752) array_push($diabolical[1], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19753) array_push($diabolical[2], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19758) array_push($diabolical[3], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19759) array_push($diabolical[4], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19760) array_push($diabolical[5], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19761) array_push($diabolical[6], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19754) array_push($diabolical[7], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19755) array_push($diabolical[8], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19756) array_push($diabolical[9], $ur[$i]['TsumegoAttempt']['gain']);
			elseif($ur[$i]['TsumegoAttempt']['tsumego_id']==19757) array_push($diabolical[10], $ur[$i]['TsumegoAttempt']['gain']);
		}
		echo '<br><table border="0">';
		for($i=1; $i<=10; $i++){
			$sCount = 0;
			$vCount = 0;
			echo '<tr>';
			for($j=0; $j<count($diabolical[$i]); $j++){
				if($diabolical[$i][$j]!=0) $sCount++;
				else $vCount++;
			}
			echo '<td>Diabolical '.$i.' solved:</td><td>'.$sCount.'&nbsp;&nbsp;&nbsp;</td>';
			echo '<td>Diabolical '.$i.' not solved:</td><td>'.$vCount.'</td>';
			echo '<tr>';
		}
		echo '</table<br>';
	}
	echo '<div align="center">';
	?>
	
	
	
	<table width="85%" border="0" class="data-table data-table--compact">
	<tr>
		<th>#</th>
		<th>user-id</th>
		<th>name</th>
		<th>lvl</th>
		<th>time spent</th>
		<th>xp gain</th>
		<th class="text-right">tsumego</th>
		<th></th>
		<th>tsumego-id</th>
		<th>date/time</th>
	</tr>
	<?php
	
	if($count==0){
		
		for($i=0; $i<count($ur); $i++){
			echo '<tr>';
				echo '<td>';
					echo $i+1;
				echo '</td>';
				echo '<td>';
					echo '<a class="link-plain" href="/users/userstats/'.$ur[$i]['TsumegoAttempt']['user_id'].'">'.$ur[$i]['TsumegoAttempt']['user_id'].'</a>';
				echo '</td>';
				echo '<td class="text-center">';
				echo '<a class="link-plain" href="/users/userstats/'.$ur[$i]['TsumegoAttempt']['user_id'].'">'.h($ur[$i]['TsumegoAttempt']['user_name']).'</a>';
				echo '</td>';
				echo '<td>';
					echo 'lvl '. $ur[$i]['TsumegoAttempt']['level'];
				echo '</td>';
				echo '<td>';
					echo $ur[$i]['TsumegoAttempt']['seconds'];
				echo '</td>';
				echo '<td>';
					echo '+'.$ur[$i]['TsumegoAttempt']['gain'].' ('.$ur[$i]['TsumegoAttempt']['tsumego_xp'].')';
				echo '</td>';
				echo '<td class="text-right">';
					if(strlen($ur[$i]['TsumegoAttempt']['set_name'])>=30) $ur[$i]['TsumegoAttempt']['set_name'] = substr($ur[$i]['TsumegoAttempt']['set_name'], 0, 30);
					echo h($ur[$i]['TsumegoAttempt']['set_name']);
				echo '</td>';
				echo '<td>';
					echo $ur[$i]['TsumegoAttempt']['tsumego_num'];
				echo '</td>';
				echo '<td>';
					echo 'id <a class="link-plain" target="_blank"
					href="/tsumegos/play/'.$ur[$i]['TsumegoAttempt']['tsumego_id'].'">
					'. $ur[$i]['TsumegoAttempt']['tsumego_id'].'</a>';
				echo '</td>';
				echo '<td>';
					echo '<time datetime="' . Util::toIso8601($ur[$i]['TsumegoAttempt']['created']) . '" data-format="datetime">' . $ur[$i]['TsumegoAttempt']['created'] . '</time>';
				echo '</td>';
			echo '</tr>';
		}
	}else{
		echo h($ur[0]['TsumegoAttempt']['user_name']).'<br>';
		
		$ids = array();
		for($i=0; $i<count($ur); $i++){
			array_push($ids, $ur[$i]['TsumegoAttempt']['tsumego_id']);
			
		}
		$vals = array_count_values($ids);
		asort($vals);
		$vals = array_reverse($vals, true);
		
		$penalty = 0;
		
		foreach($vals as $key => $value){
			if($value>2){
				echo '<br>';
				echo "$key => $value";
				echo '<br>';
				for($i=0; $i<count($ur); $i++){
					if($ur[$i]['TsumegoAttempt']['tsumego_id'] == $key){
						echo '<table>';
						echo '<tr>';
						echo '<td>';
							echo $i+1;
						echo '</td>';
						echo '<td>';
							echo $ur[$i]['TsumegoAttempt']['user_id'];
						echo '</td>';
						echo '<td class="text-center">';
							echo '<a class="link-plain" 
						href="/users/userstats/'.$ur[$i]['TsumegoAttempt']['user_id'].'">'.h($ur[$i]['TsumegoAttempt']['user_name']).'</a>';
						echo '</td>';
						echo '<td>';
							echo 'lvl '. $ur[$i]['TsumegoAttempt']['level'];
						echo '</td>';
						echo '<td>';
							echo $ur[$i]['TsumegoAttempt']['xp'].' xp';
						echo '</td>';
						echo '<td>';
							echo '+'.$ur[$i]['TsumegoAttempt']['gain'].' ('.$ur[$i]['TsumegoAttempt']['tsumego_xp'].')';
							$penalty += $ur[$i]['TsumegoAttempt']['gain'];
						echo '</td>';
						echo '<td class="text-right">';
							if(strlen($ur[$i]['TsumegoAttempt']['set_name'])>=30) $ur[$i]['TsumegoAttempt']['set_name'] = substr($ur[$i]['TsumegoAttempt']['set_name'], 0, 30);
							echo h($ur[$i]['TsumegoAttempt']['set_name']);
						echo '</td>';
						echo '<td>';
							echo $ur[$i]['TsumegoAttempt']['tsumego_num'];
						echo '</td>';
						echo '<td>';
							echo 'id <a class="link-plain" target="_blank"
							href="/tsumegos/play/'.$ur[$i]['TsumegoAttempt']['tsumego_id'].'">
							'. $ur[$i]['TsumegoAttempt']['tsumego_id'].'</a>';
						echo '</td>';
						echo '<td>';
echo '<time datetime="' . Util::toIso8601($ur[$i]['TsumegoAttempt']['created']) . '" data-format="datetime">' . $ur[$i]['TsumegoAttempt']['created'] . '</time>';
						echo '</td>';
						echo '</tr>';
						echo '</table>';
					}						
				}
			} 
		}
		
		
		
	}
	?>
	</table>
	<?php
	echo 'Penalty: '.$penalty;
	echo '<br></div>';
	
	
	
?>