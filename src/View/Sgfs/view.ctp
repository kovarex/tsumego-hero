  <?php

use App\Utility\SgfDescription;
use App\Utility\Util;

/**
 * @var View $this
 * @var array $dTitle
 * @var int $id
 * @var string $name
 * @var array $versions
 * @var string $type
 * @var string $ux
 */

?>

	<div align="center">
	<p class="title">
		<br>
		<?php if($type=='tsumego'){ ?>
			Upload History of <?php echo h($name); ?>
		<?php }else{ ?>
			Upload History of <?php echo h($ux); ?>
		<?php } ?>
		<br><br>
	</p>
	<?php if(!empty($dId)){ ?>
		<div>
			Duplicate with: <?php
			for($i=0; $i<count($dId); $i++){
				echo '<a href="/tsumegos/play/'.$dId[$i].'">'.h($dTitle[$i]).'</a>';
				if($i!=count($dId)-1)
					echo ', ';
			}
			?>
		</div>
	<?php } ?>
	<div align="left">
		<?php if($type=='tsumego'){ ?>
			<a href="/tsumegos/play/<?php echo $id; ?>">back</a>
		<?php }else{ ?>
			<a href="/sgfs/view/<?php echo $id; ?>">back</a>
		<?php } ?>
	</div>
	<table class="data-table" border="0">
	<tbody>
	<tr>
		<th>Date</th>
		<th align="left">&nbsp;Author</th>
		<th align="left">&nbsp;Version</th>
		<th align="left">&nbsp;Description</th>
		<th></th>
	</tr>
	<?php
		for($i=0; $i<count($versions); $i++){
			echo '<tr id="'.$versions[$i]['id'].'">
			<td class="timeTableLeft versionColor" align="center">
				<time datetime="'.Util::toIso8601($versions[$i]['created']).'" data-format="datetime">'.$versions[$i]['created'].'</time>
			</td>
			<td class="timeTableMiddle versionColor" align="left">';
			if($versions[$i]['user_id']==33)
				echo 'automatically generated';
			else
				echo '<a href="/sgfs/view/'.$versions[$i]['tsumego_id'].'?user='.$versions[$i]['user_id'].'">'.h($versions[$i]['user']).'</a>';
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor" align="left">';
			if($type=='tsumego')
				echo '<a href="/tsumegos/play/'.($versions[$i]['tsumego_id']).'"> '.h($versions[$i]['title']).'</a> ';
			else
				echo '<a href="/sgfs/view/'.$versions[$i]['tsumego_id'].'"> '.h($versions[$i]['title']).'</a> ';
			echo '<a href="/sgfs/view/'.$versions[$i]['tsumego_id'].'" id="dl1-'.$versions[$i]['id'].'">v '.$versions[$i]['version'].'</a>';
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor proposal-description" align="left" id="description-'.$versions[$i]['id'].'">'.SgfDescription::render($versions[$i]['description'] ?? null).'</td>';
			echo '<td class="timeTableRight versionColor" align="left">
			<a id="open-'.$versions[$i]['id'].'" class="openHistoryPointLink">open</a>&nbsp;&nbsp;&nbsp;';
			if ($versions[$i]['diff'])
				echo '<a id="compare-'.$versions[$i]['id'].'" class="openDiff" href="/editor?sgfID='.$versions[$i]['id'].'&diffID='.$versions[$i]['diff'].'">diff</a>&nbsp;&nbsp;&nbsp;';
			echo '<a href="#" id="dl2-'.$versions[$i]['id'].'">download</a>';
			if($versions[$i]['delete'])
				echo '&nbsp;&nbsp;&nbsp;<a onclick="delV('.$versions[$i]['id'].');" href="#">delete</a>';
			echo '</td>
			</tr>';
		}
	?>
	</tbody>
	</table>
</div>
<br>
<script>
	function delV(sgfid){
		var confirmed = confirm("Are you sure?");
		if(confirmed) window.location.href = "/sgfs/view/<?php echo $id; ?>?delete="+sgfid;
	}

	<?php for($i=0; $i<count($versions); $i++){ ?>
		$("#open-<?php echo (int)$versions[$i]['id'] ?>").attr("href", "<?php echo '/editor?sgfID=' . (int)$versions[$i]['id']; ?>");
		$("#dl1-<?php echo (int)$versions[$i]['id']; ?>").click(function(){
			var blob<?php echo (int)$versions[$i]['id']; ?> = new Blob([<?php echo json_encode($versions[$i]['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>],{
				type: "sgf",
			});
			saveAs(blob<?php echo (int)$versions[$i]['id']; ?>, <?php echo json_encode($versions[$i]['title'].' v'.$versions[$i]['version'] . '.sgf', JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#dl2-<?php echo (int)$versions[$i]['id']; ?>").click(function(){
			var blob2<?php echo (int)$versions[$i]['id']; ?> = new Blob([<?php echo json_encode($versions[$i]['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>],{
				type: "sgf",
			});
			saveAs(blob2<?php echo (int)$versions[$i]['id']; ?>, <?php echo json_encode($versions[$i]['num'] . '.sgf', JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#<?php echo (int)$versions[$i]['id']; ?>").hover(
		  function () {
			$("#<?php echo (int)$versions[$i]['id']; ?> td").css("background","linear-gradient(#f7f7f7, #b9b9b9)");
		  },
		  function () {
			$("#<?php echo (int)$versions[$i]['id']; ?> td").css("background","");
		  }
		);
		<?php if($versions[$i]['diff']){ ?>
			$("#compare-<?php echo (int)$versions[$i]['id']; ?>").hover(
			  function () {
				$("#<?php echo (int)$versions[$i]['id']; ?> td").css("background","linear-gradient(#fff, #c8c8c8)");
				$("#<?php echo (int)$versions[$i]['diff']; ?> td").css("background","linear-gradient(#fff, #c8c8c8)");
			  },
			  function () {
				$("#<?php echo (int)$versions[$i]['id']; ?> td").css("background","");
				$("#<?php echo (int)$versions[$i]['diff']; ?> td").css("background","");
			  }
			);
		<?php } ?>
	<?php } ?>
</script>
