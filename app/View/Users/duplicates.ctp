<script src ="/js/previewBoard.js"></script>
<?php
	if(isset($_SESSION['loggedInUser'])){
		if($_SESSION['loggedInUser']['User']['isAdmin']<1){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	?>
	<div class="homeRight">	
	<h1>Set mark by tsumego id</h1>
	<?php
	echo '<div>';
	echo $this->Form->create('Mark');
	echo '<table border="0">';
	echo '<tr>';
	echo '<td>';
	echo $this->Form->input('tsumego_id', array('label' => '', 'type' => 'text', 'placeholder' => 'id'));
	echo '</td><td>';
	echo '<div class="submit"><input value="Submit" type="submit"></div>';
	echo '</td>';
	echo '</tr>';
	echo '</table>';
	echo '</div>';
	?>
	<br>
	<hr>
	<h1>Add problem to existing duplicate group</h1>
	<?php
	echo '<div>';
	echo $this->Form->create('Mark2');
	echo '<table border="0">';
	echo '<tr>';
	echo '<td>';
	echo $this->Form->input('tsumego_id', array('label' => '', 'type' => 'text', 'placeholder' => 'tsumego id'));
	echo '</td><td>';
	echo $this->Form->input('group_id', array('label' => '', 'type' => 'text', 'placeholder' => 'add to group id'));
	echo '</td><td>';
	echo '<div class="submit"><input value="Submit" type="submit"></div>';
	echo '</td>';
	echo '</tr>';
	echo '</table>';
	echo '</div>';
	?>
	<br>
	<hr>
	<h1>Marked as duplicate:</h1>
		<?php if(!empty($marks)){ ?>
		<font color="gray"><i>(main/duplicate group)</i></font>
		<table class="statsTable" border="0" width="100%">
		<?php
		for($i=0; $i<count($marks); $i++){
			echo '<tr>';
			echo '<td id="t0">
			<input type="radio" id="r'.$marks[$i]['Tsumego']['id'].'" name="data[Settings][r41]" value="'.$marks[$i]['Tsumego']['title'].'">
			<input type="checkbox" id="c'.$marks[$i]['Tsumego']['id'].'">
			</td>';
			echo '<td id="t1">
				<li class="set'.$marks[$i]['Tsumego']['status'].'1" style="margin-top:4px;">
					<a id="tooltip-hover'.(999+$i).'" class="tooltip" href="/tsumegos/play/'.$marks[$i]['Tsumego']['id'].'">'
					.$marks[$i]['Tsumego']['num'].'<span><div id="tooltipSvg'.(999+$i).'"></div></span></a>
				</li>
			</td>';
			echo '<td id="t2">'.$marks[$i]['Tsumego']['title'].' </td>';
			echo '<td id="t3"><a href="/users/duplicates/?remove='.$marks[$i]['Tsumego']['id'].'">remove</a></td>';
			echo '</tr>';
		}
		?>
		</table>
		<br>
		<div id="createD1"><br></div>
		<div id="createD2"><br></div>
		<br>
		<a id="submitDuplicates" class="default-submit-button-inactive">Merge duplicates</a>
		<br><br>
		<i style="color:#777">Main is the problem that is kept in the databse. The other problems of the group get <b style="color:#cb382c">deleted</b>
		and linked to the main problem.</i>
		<?php }else{ ?>
			<br>
			There are no duplicate marks.
		<?php } ?>
		<br><br><br>
	</div>
	<script>
	let markIds = [];
	let markNames = [];
	let added = [];
	let main = 0;
	let urlParams = "";
	<?php
		for($i=0; $i<count($marks); $i++){
			echo 'markIds['.$i.'] ='.$marks[$i]['Tsumego']['id'].';';
			echo 'markNames['.$i.'] ="'.$marks[$i]['Tsumego']['title'].'";';
		}
	?>
	<?php for($i=0; $i<count($marks); $i++){ ?>
		$("#c<?php echo $marks[$i]['Tsumego']['id']; ?>").change(function(){
			if ($(this).is(":checked")){
				addMark(<?php echo $i; ?>);
			}else{
				removeMark(<?php echo $i; ?>);
			}
		});
		$("#r<?php echo $marks[$i]['Tsumego']['id']; ?>").change(function(){
			main = <?php echo $marks[$i]['Tsumego']['id']; ?>;
			setUrlParams();
			$("#createD1").html("main: <b>"+$("#r<?php echo $marks[$i]['Tsumego']['id']; ?>").val() + "</b>");
			displayValid(checkValid());
		});
	<?php } ?>
	function addMark(m){
		added.push(m);
		displayMarks();
		displayValid(checkValid());
	}
	function removeMark(m){
		let a = [];
		for(i=0; i<added.length; i++){
			if(added[i]!==m)
				a.push(added[i]);
		}
		added = a;
		displayMarks();
		displayValid(checkValid());
	}
	function displayMarks(){
		let str = "duplicate group: ";
		for(i=0; i<added.length; i++){
			str = str + "<b>" + markNames[added[i]] + "</b>";
			if(i!==added.length-1)
				str += ", ";
		}
		$("#createD2").html(str);
		setUrlParams();
	}
	function setUrlParams(){
		urlParams = "?main=" + main + "&duplicates=";
		for(i=0; i<added.length; i++){
			urlParams = urlParams + markIds[added[i]];
			if(i!==added.length-1)
				urlParams += "-";
		}
	}
	function checkValid(){
		let valid = false;
		if(main===0 || added.length<2)
			return false;
		for(i=0; i<added.length; i++){
			if(markIds[added[i]]===main)
				valid = true;
		}
		return valid;
	}
	function displayValid(v){
		if(v){
			$("#submitDuplicates").addClass("default-submit-button");
			$("#submitDuplicates").removeClass("default-submit-button-inactive");
			$("#submitDuplicates").attr("href", "/users/duplicates/"+urlParams);
		}else{
			$("#submitDuplicates").removeClass("default-submit-button");
			$("#submitDuplicates").addClass("default-submit-button-inactive");
			$("#submitDuplicates").removeAttr("href");
		}
	}
	</script>
	<div class="homeLeft" style="border-right:1px solid #a0a0a0;">
	<h1>Merged Duplicates</h1>
		<?php
			if($aMessage!=null)
				echo '<p style="color:#cb382c">'.$aMessage.'</p>';
			if($errSet!='')
				echo '<p style="color:#cb382c">'.$errSet.'</p>';
			if($errSet!='')
				echo '<p style="color:#cb382c">'.$errNotNull.'</p>';
			
			echo '<hr>';
			$cx = 0;
			for($i=0; $i<count($d); $i++){
				echo 'Group id: '.$d[$i][0]['Tsumego']['id'];
				echo '<br>';
				for($j=0; $j<count($d[$i]); $j++){
					echo '<a href="/tsumegos/play/'.$d[$i][$j]['Tsumego']['id'].$d[$i][$j]['Tsumego']['duplicateLink'].'">'.$d[$i][$j]['Tsumego']['title'].'</a>';
					
					if($j<count($d[$i])-1)
						echo ', ';
				}
				echo '<br>';
				for($j=0; $j<count($d[$i]); $j++){
					echo '<td id="t1">
						<li class="set'.$d[$i][$j]['Tsumego']['status'].'1" style="margin-top:4px;">
							<a id="tooltip-hover'.$cx.'" class="tooltip" href="/tsumegos/play/'.$d[$i][$j]['Tsumego']['id'].$d[$i][$j]['Tsumego']['duplicateLink']
							.'">'.$d[$i][$j]['Tsumego']['num'].'<span><div id="tooltipSvg'.$cx.'"></div></span></a>
						</li>
					</td>';
					$cx++;
				}
				
				echo '<br><br><br>';
			}
			if(!$showAll)	
				echo '<a href="/users/duplicates?load=true">Show all</a>';
			
		?>
		<br><br><br><br><br><br><br><br><br><br>
		
	</div>
	<div style="clear:both;"></div>
	<script>
		let markTooltipSgfs = [];
		<?php
		for($a=0; $a<count($markTooltipSgfs); $a++){
			echo 'markTooltipSgfs['.$a.'] = [];';
			for($y=0; $y<count($markTooltipSgfs[$a]); $y++){
				echo 'markTooltipSgfs['.$a.']['.$y.'] = [];';
				for($x=0; $x<count($markTooltipSgfs[$a][$y]); $x++){
					echo 'markTooltipSgfs['.$a.']['.$y.'].push("'.$markTooltipSgfs[$a][$x][$y].'");';
				}
			}
		}
		for($i=0; $i<count($markTooltipBoardSize); $i++)
			echo 'createPreviewBoard('.(999+$i).', markTooltipSgfs['.$i.'], '.$markTooltipInfo[$i][0].', '.$markTooltipInfo[$i][1].', '.$markTooltipBoardSize[$i].');';
		?>
		let tooltipSgfs = [];
		<?php
		for($z=0; $z<count($tooltipSgfs); $z++){
			echo 'tooltipSgfs['.$z.'] = [];';
			for($a=0; $a<count($tooltipSgfs[$z]); $a++){
				echo 'tooltipSgfs['.$z.']['.$a.'] = [];';
				for($y=0; $y<count($tooltipSgfs[$z][$a]); $y++){
					echo 'tooltipSgfs['.$z.']['.$a.']['.$y.'] = [];';
					for($x=0; $x<count($tooltipSgfs[$z][$a][$y]); $x++){
						echo 'tooltipSgfs['.$z.']['.$a.']['.$y.'].push("'.$tooltipSgfs[$z][$a][$x][$y].'");';
					}
				}
			}
		}
		$cx = 0;
		for($i=0; $i<count($d); $i++)
			for($j=0; $j<count($d[$i]); $j++){
				echo 'createPreviewBoard('.$cx.', tooltipSgfs['.$i.']['.$j.'], '.$tooltipInfo[$i][$j][0].', '.$tooltipInfo[$i][$j][1].', '.$tooltipBoardSize[$i][$j].');';
				$cx++;
			}	
		?>
	</script>
	<style>
		.ml{text-decoration:underline;cursor:pointer;}
		#t0{margin:0;padding:0;width:55px;}
		#t1{text-align:left;width:77px;}
		#t2{text-align:left;}
		#t3{text-align:right;width:52px;}
	</style>
	<?php
	//echo date('Y-m-d H:i:s');
	?>