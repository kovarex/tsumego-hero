	<?php
	if(Auth::isLoggedIn())
	{
		if(!Auth::isAdmin())
			echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	else
	{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
?>
	<script src ="/FileSaver.min.js"></script>

	<div align="center">
	<p class="title">
		<br>
		Uploads
		<br><br>
		</p>
	<table class="highscoreTable" border="0">
	<tbody>
	<tr>
		<th>Date</th>
		<th align="left">&nbsp;Author</th>
		<th align="left">&nbsp;Version</th>
	</tr>
	<?php
	for($i = 0; $i < count($s); $i++)
	{
		echo '<tr id="' . $s[$i]['Sgf']['id'] . '">
			<td class="timeTableLeft versionColor" align="center">
				' . $s[$i]['Sgf']['created'] . '
			</td>
			<td class="timeTableMiddle versionColor" align="left">';
		echo '<a href="/sgfs/view/' . $id2 . '?user=' . $s[$i]['Sgf']['user_id'] . '">' . h($s[$i]['Sgf']['user']) . '</a>';

		echo '</td>';
		echo '<td class="timeTableMiddle versionColor" align="left">';
		echo '<a href="/tsumegos/play/' . $s[$i]['Sgf']['tsumego_id'] . '"> ' . h($s[$i]['Sgf']['title']) . '</a> ';
		echo '<a href="/sgfs/view/' . $s[$i]['Sgf']['tsumego_id'] . '" id="dl1-' . $s[$i]['Sgf']['id'] . '">v ' . $s[$i]['Sgf']['version'] . '</a>';
		echo '</td>
			<td class="timeTableRight versionColor" align="left">';
		echo '<a id="open-' . $s[$i]['Sgf']['id'] . '">open</a>&nbsp;&nbsp;&nbsp;';
		if ($i != count($s) - 1)
			echo '<a id="compare-' . $s[$i]['Sgf']['id'] . '" class="openDiff" href="/editor?sgfID=' . $s[$i]['Sgf']['id'] . '&diffID=' . $s[$i + 1]['Sgf']['id'] . '"">diff</a>&nbsp;&nbsp;&nbsp;';
		echo '<a href="#" id="dl2-' . $s[$i]['Sgf']['id'] . '">download</a>';
		echo '</td>
			</tr>';
	}
?>
	</tbody>
	</table>
</div>
<br>

<script>
	<?php for($i = 0; $i < count($s); $i++)
	{ ?>
		$("#open-<?php echo (int) $s[$i]['Sgf']['id'] ?>").attr("href", "<?php echo '/editor?sgfID=' . (int) $s[$i]['Sgf']['id']; ?>");
		$("#dl1-<?php echo (int) $s[$i]['Sgf']['id']; ?>").click(function(){
			var blob<?php echo (int) $s[$i]['Sgf']['id']; ?> = new Blob([<?php echo json_encode($s[$i]['Sgf']['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>],{
				type: "sgf",
			});
			saveAs(blob<?php echo (int) $s[$i]['Sgf']['id']; ?>, <?php echo json_encode($s[$i]['Sgf']['title'] . '.sgf', JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#dl2-<?php echo (int) $s[$i]['Sgf']['id']; ?>").click(function(){
			var blob2<?php echo (int) $s[$i]['Sgf']['id']; ?> = new Blob([<?php echo json_encode($s[$i]['Sgf']['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>],{
				type: "sgf",
			});
			saveAs(blob2<?php echo (int) $s[$i]['Sgf']['id']; ?>, <?php echo json_encode($s[$i]['Sgf']['num'] . '.sgf', JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#<?php echo (int) $s[$i]['Sgf']['id']; ?>").hover(
		  function () {
			$("#<?php echo (int) $s[$i]['Sgf']['id']; ?> td").css("background","linear-gradient(#f7f7f7, #b9b9b9)");
		  },
		  function () {
			$("#<?php echo (int) $s[$i]['Sgf']['id']; ?> td").css("background","");
		  }
		);
		<?php if($i != count($s) - 1)
		{ ?>
			$("#compare-<?php echo (int) $s[$i]['Sgf']['id']; ?>").hover(
			  function () {
				$("#<?php echo (int) $s[$i]['Sgf']['id']; ?> td").css("background","linear-gradient(#f7f7f7, #b9b9b9)");
				$("#<?php echo (int) $s[$i]['Sgf']['diff']; ?> td").css("background","linear-gradient(#f7f7f7, #b9b9b9)");
			  },
			  function () {
				$("#<?php echo (int) $s[$i]['Sgf']['id']; ?> td").css("background","");
				$("#<?php echo (int) $s[$i]['Sgf']['diff']; ?> td").css("background","");
			  }
			);
		<?php } ?>
	<?php } ?>
</script>
