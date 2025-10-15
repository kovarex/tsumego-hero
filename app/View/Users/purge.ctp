
	<h1>Purge</h1><br>
	<a class="new-button new-buttonx" href="/users/purge?p=1">purge</a>
	<br><br>
	<a href="/users/countlist">purge2</a><br>
	<a href="/users/tsumego_score">set score</a>
	<br><br><br>
	<?php
		//echo $t;
		//echo '<pre>'; print_r($pl); echo '</pre>';
		echo '<table border="1">';
		echo '<th>start</th><th>empty_uts</th><th>purge</th><th>count</th><th>archive</th><th>tsumego_scores</th><th>set_scores</th>';
		for($i=0; $i<count($pl); $i++){
			echo '<tr>';
			echo '<td>'.$pl[$i]['PurgeList']['start'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['empty_uts'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['purge'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['count'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['archive'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['tsumego_scores'].'</td>';
			echo '<td>'.$pl[$i]['PurgeList']['set_scores'].'</td>';
			echo '</tr>';
		}
		echo '</table>';
		
		echo '<h1>Add Problem</h1>';
		echo $this->Form->create('Schedule');
		echo $this->Form->input('num', array('label' => 'num: ', 'type' => 'text', 'placeholder' => 'num'));
		echo $this->Form->input('set_id_from', array('label' => 'set_id_from: ', 'type' => 'text', 'placeholder' => 'set_id_from'));
		echo $this->Form->input('set_id_to', array('label' => 'set_id_to: ', 'type' => 'text', 'placeholder' => 'set_id_to'));
		echo $this->Form->input('date', array('label' => 'date:', 'type' => 'text', 'placeholder' => 'date'));
		echo $this->Form->end('Submit');
	?>
	<br><br><br>
	<?php
	/*
	echo count($repPos).'<br><br>';
	foreach ($repPos3 as $key => $value) {
		echo $key.' '.$value.'<br>';
	}
	echo '<br><br>';
	echo count($repNeg).'<br><br>';
	foreach ($repNeg3 as $key => $value) {
		echo $key.' '.$value.'<br>';
	}
	*/
	?>
	<br><br>
	<table><tr><td>
	<?php
	for($i=0; $i<count($ans); $i++){
		$date2 = new DateTime($ans[$i]['Answer']['created']);
		$date2 = $date2->format('Y-m-d');
		echo $date2.' | <b>'.$ans[$i]['Answer']['dismissed'].'</b><br>';
	}
	?>
	</td><td style="vertical-align: top;">
	<?php
	for($i=0; $i<count($s); $i++){
		echo $s[$i]['Schedule']['published'].' | '.$s[$i]['Schedule']['date'].' | '.$s[$i]['Schedule']['set_id'].' | <b>'.$s[$i]['Schedule']['tsumego_id'].'</b><br>';
	}
	//echo '<pre>'; print_r($s); echo '</pre>';
	?>
	</td></tr></table>
	<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
	<script type="text/JavaScript">
		<?php if($p==1){ ?>
			setTimeout(function () {window.location.href = "/users/empty_uts";}, 3000);
		<?php } ?>
	</script>
