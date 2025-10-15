	<?php
	if(isset($_SESSION['loggedInUser'])){
		if($_SESSION['loggedInUser']['User']['isAdmin']<1){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	
	?>
	<script src ="/FileSaver.min.js"></script>

	<div align="center">
	<p class="title">
		<br>
		<?php if($type=='tsumego'){ ?>
			Upload History of <?php echo $name; ?>
		<?php }else{ ?>
			Upload History of <?php echo $ux; ?>
		<?php } ?>
		<br><br> 
	</p>
	<?php if(!empty($dId)){ ?>
		<div>
			Duplicate with: <?php
			for($i=0; $i<count($dId); $i++){
				echo '<a href="/tsumegos/play/'.$dId[$i].'">'.$dTitle[$i].'</a>';
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
			<a href="/sgfs/view/<?php echo $id2; ?>">back</a>
		<?php } ?>
	</div>
	<table class="highscoreTable" border="0">
	<tbody>
	<tr>
		<th>Date</th>
		<th align="left">&nbsp;Author</th>
		<th align="left">&nbsp;Version</th>
	</tr>
	<?php
		for($i=0; $i<count($s); $i++){
			echo '<tr id="'.$s[$i]['Sgf']['id'].'">
			<td class="timeTableLeft versionColor" align="center">
				'.$s[$i]['Sgf']['created'].'
			</td>
			<td class="timeTableMiddle versionColor" align="left">';
			if($s[$i]['Sgf']['user_id']==33)
				echo 'automatically generated';
			else
				echo '<a href="/sgfs/view/'.($s[$i]['Sgf']['tsumego_id']*1337).'?user='.$s[$i]['Sgf']['user_id'].'">'.$s[$i]['Sgf']['user'].'</a>';
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor" align="left">';
			if($type=='tsumego')
				echo '<a href="/tsumegos/play/'.($s[$i]['Sgf']['tsumego_id']).'"> '.$s[$i]['Sgf']['title'].'</a> ';
			else
				echo '<a href="/sgfs/view/'.($s[$i]['Sgf']['tsumego_id']*1337).'"> '.$s[$i]['Sgf']['title'].'</a> ';
			if(floor($s[$i]['Sgf']['version'])==$s[$i]['Sgf']['version']) $isDecimal='.0';
			else $isDecimal='';
			echo '<a href="#" id="dl1-'.$s[$i]['Sgf']['id'].'">v'.$s[$i]['Sgf']['version'].$isDecimal.'</a>';
			echo '</td>
			<td class="timeTableRight versionColor" align="left">
			<a id="open-'.$s[$i]['Sgf']['id'].'">open</a>&nbsp;&nbsp;&nbsp;';
			if($i!=count($s)-1)
				echo '<a id="compare-'.$s[$i]['Sgf']['id'].'">diff</a>&nbsp;&nbsp;&nbsp;';
			echo '<a href="#" id="dl2-'.$s[$i]['Sgf']['id'].'">download</a>';
			if($s[$i]['Sgf']['delete']) 
				echo '&nbsp;&nbsp;&nbsp;<a onclick="delV('.$s[$i]['Sgf']['id'].');" href="#">delete</a>';
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
		if(confirmed) window.location.href = "/sgfs/view/<?php echo $id2; ?>?delete="+sgfid;
	}
	
	<?php for($i=0; $i<count($s); $i++){ ?>
		$("#open-<?php echo $s[$i]['Sgf']['id'] ?>").attr("href", "<?php echo '/tsumegos/open/'.$s[$i]['Sgf']['tsumego_id'].'/'.$s[$i]['Sgf']['id']; ?>");
		<?php
		if($i!=count($s)-1) 
			echo '$("#compare-'.$s[$i]['Sgf']['id'].'").attr("href", "/tsumegos/open/'.$s[$i]['Sgf']['tsumego_id'].'/'.$s[$i]['Sgf']['id'].'/'.$s[$i]['Sgf']['diff'].'");';
		?>
		$("#dl1-<?php echo $s[$i]['Sgf']['id']; ?>").click(function(){
			var blob<?php echo $s[$i]['Sgf']['id']; ?> = new Blob(["<?php echo $s[$i]['Sgf']['sgf']; ?>"],{
				type: "sgf",
			});
			saveAs(blob<?php echo $s[$i]['Sgf']['id']; ?>, "<?php echo $s[$i]['Sgf']['title'].' v'.$s[$i]['Sgf']['version']; ?>.sgf");
		});
		$("#dl2-<?php echo $s[$i]['Sgf']['id']; ?>").click(function(){
			var blob2<?php echo $s[$i]['Sgf']['id']; ?> = new Blob(["<?php echo $s[$i]['Sgf']['sgf']; ?>"],{
				type: "sgf",
			});
			saveAs(blob2<?php echo $s[$i]['Sgf']['id']; ?>, "<?php echo $s[$i]['Sgf']['num']; ?>.sgf");
		});
		$("#<?php echo $s[$i]['Sgf']['id']; ?>").hover(
		  function () {
			$("#<?php echo $s[$i]['Sgf']['id']; ?> td").css("background","linear-gradient(#f7f7f7, #b9b9b9)");
		  }, 
		  function () {
			$("#<?php echo $s[$i]['Sgf']['id']; ?> td").css("background","");
		  }
		);
		<?php if($i!=count($s)-1){ ?>
			$("#compare-<?php echo $s[$i]['Sgf']['id']; ?>").hover(
			  function () {
				$("#<?php echo $s[$i]['Sgf']['id']; ?> td").css("background","linear-gradient(#fff, #c8c8c8)");
				$("#<?php echo $s[$i]['Sgf']['diff']; ?> td").css("background","linear-gradient(#fff, #c8c8c8)");
			  }, 
			  function () {
				$("#<?php echo $s[$i]['Sgf']['id']; ?> td").css("background","");
				$("#<?php echo $s[$i]['Sgf']['diff']; ?> td").css("background","");
			  }
			);
		<?php } ?>
	<?php } ?>
</script>