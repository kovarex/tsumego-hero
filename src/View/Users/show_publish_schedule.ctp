<?php
/**
 * @var View $this
 * @var array $p
 * @var array $sandboxSets
 * @var array $publicSets
 */
$tomorrow = date('Y-m-d', strtotime('tomorrow'));
?>
<div align="center">
<p class="title">
					<br> Publish Schedule
				<br><br>
				</p>
<div align="left"><a href="/sets/sandbox">back</a></div>

	<h2>Upcoming</h2>
	<table class="highscoreTable" border="0">
	<tbody>
	<tr>
		<th width="60px">Date</th>
		<th width="220px" align="left">&nbsp;Name</th>
		<th></th>
	</tr>

	<?php
		for($i=0; $i<count($p); $i++){
			echo '<tr><td class="timeTableLeft timeTableColor11" align="center">
				'.$p[$i]['date'].'
			</td>
			<td class="timeTableRight timeTableColor11" width="225px" align="left">
				<a href="/tsumegos/play/'.$p[$i]['tsumego_id'].'">'.h(trim($p[$i]['set_title'].' '.$p[$i]['set_title2']).' '.$p[$i]['num']).'</a>
			</td>
			<td>
				<form method="post" action="/users/cancelSchedule/'.$p[$i]['id'].'" style="display:inline">
					<button type="submit">Cancel</button>
				</form>
			</td></tr>';
		}
	?>

	</tbody>
	</table>

	<h2>Schedule problems</h2>
	<form method="post" action="/users/addToSchedule" style="display:inline-block; text-align:left; margin-top:10px;">
		<div style="margin-bottom:8px;">
			<div>From sandbox set</div>
			<select name="set_id_from">
				<?php foreach ($sandboxSets as $set) { ?>
					<option value="<?php echo $set['Set']['id']; ?>"><?php echo h($set['Set']['title']); ?></option>
				<?php } ?>
			</select>
		</div>
		<div style="margin-bottom:8px;">
			<div>Into public set</div>
			<select name="set_id_to">
				<?php foreach ($publicSets as $set) { ?>
					<option value="<?php echo $set['Set']['id']; ?>"><?php echo h($set['Set']['title']); ?></option>
				<?php } ?>
			</select>
		</div>
		<div style="margin-bottom:8px;">
			<div>Count</div>
			<input type="number" name="count" value="1" min="1" max="100">
		</div>
		<div style="margin-bottom:8px;">
			<div>Start from problem num (optional)</div>
			<input type="number" name="num" min="1" placeholder="optional">
		</div>
		<div style="margin-bottom:8px;">
			<div>Start date</div>
			<input type="date" name="start_date" value="<?php echo $tomorrow; ?>" min="<?php echo $tomorrow; ?>">
		</div>
		<button type="submit">Schedule</button>
	</form>
</div>
<br>
