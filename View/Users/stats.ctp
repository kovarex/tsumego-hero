<?php
	
	if(isset($_SESSION['loggedInUser'])){
		if($_SESSION['loggedInUser']['User']['id']!=72){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}

	echo '<div class="homeRight">';
	echo '<br><br>';
	?>
	<table width="100%">
	<tr>
		<td width="100%">
			<?php echo '<pre>';print_r($aa);echo '</pre>'; ?>
		</td>
	</tr>
	</table>
	
	<table width="100%">
	<tr>
		<td width="33%">
			<?php 
				if($page==null) echo'<b><a href="/users/stats/">All ('.$c1.')</a></b>'; 
				else echo'<a href="/users/stats/">All ('.$c1.')</a>'; 
			?>
		</td>
		<td width="33%">
			<?php 
				if($page=='public') echo'<b><a href="/users/stats/public">Public ('.$c2.')</a></b>'; 
				else echo'<a href="/users/stats/public">Public ('.$c2.')</a>'; 
			?>
		</td>
		<td width="33%">
			<?php 
				if($page=='sandbox') echo'<b><a href="/users/stats/sandbox">Sandbox ('.$c3.')</a></b>'; 
				else echo'<a href="/users/stats/sandbox">Sandbox ('.$c3.')</a>'; 
			?>
		</td>
	</tr>
	</table>
	<?php
	echo '<table class="statsTable">';
	//for($i=count($comments)-1; $i>=0; $i--){
	for($i=0; $i<count($comments); $i++){	
	//if($comments[$i]['Comment']['user_id']== 126 || $comments[$i]['Comment']['user_id'] == 1992){
	if(true){
		echo '<tr>';
		echo '<td>'.($i+1).' <a target="_blank" href="/users/view/'.$comments[$i]['Comment']['user_id'].'">'.$comments[$i]['Comment']['user_name'].'</a></td><td>'.
		$comments[$i]['Comment']['email'].'</td><td>'.$comments[$i]['Comment']['created'].'</td>';
		
		echo '</tr>';
		echo '<tr>';
		echo '<td colspan="3">'.$comments[$i]['Comment']['set'].' '.$comments[$i]['Comment']['set2'].' - '.$comments[$i]['Comment']['num'].'</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td colspan="3">'.$comments[$i]['Comment']['message'].'</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td colspan="2"><a target="_blank" href="/tsumegos/play/'.
		$comments[$i]['Comment']['tsumego_id'].'">/tsumegos/play/'.
		$comments[$i]['Comment']['tsumego_id'].'</a></td>';
		echo '<td align="right">';
		
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=1">moves</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=2">file</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=3">solution</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=4">disagree</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=5">provide sequence</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=6">resolved</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=7">can\'t follow</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=8">no sgf</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=9">inferior</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=10">disagree, added</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=11">dunno</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=12">added</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=13">current seq more interesting</a> | ';
		echo '<a href="/users/stats/'.$page.'?c='.$comments[$i]['Comment']['id'].'&s=14">didn\' add file</a> | ';
		echo $this->Form->postLink('Delete', array('action' => 'delete', $comments[$i]['Comment']['id']), array('confirm' => 'Sure?')); 
		echo '</td>';
		echo '</tr>';
		echo '<tr><td><br></td></tr>';
	}
	}
	echo '</table>';
	echo '</div>';
	//echo '<pre>';print_r($comments);echo '</pre>';
	
	echo '<div class="homeLeft">';
	
	echo '<br>Logged in users: '.count($u);
	echo '<br>';
	echo '<table class="statsTable">';
	for($i=0; $i<count($u); $i++){
		echo '<tr>';
		echo '
		<td>'.($i+1).'</td>
		<td>'.$u[$i]['id'].'</td>
		<td><a target="_blank" href="/users/view/'.$u[$i]['id'].'">'.$u[$i]['name'].'</a></td>
		<td>lvl '.$u[$i]['level'].'</td>
		
		<td> #'.$u[$i]['reuse2'].'</td>
		<td> '.$u[$i]['reuse3'].'xp</td>
		<td>'.$u[$i]['created'].'</td>';
		echo '</tr>';
	}
	echo '</table>';
	
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>';
	echo '</div>';
	
?>