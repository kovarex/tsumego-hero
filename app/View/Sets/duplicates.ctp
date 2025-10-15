
	
	<script src ="/js/previewBoard.js"></script>
	<?php $counter = 0; ?>
	<div align="center">
	<p class="title">
		<br>
		<?php echo 'Duplicate Search Results for '.$set['Set']['title'].' '.$set['Set']['title2'];?><br><br>
	</p>
	</div>
	<table width="100%" border="0" class="duplicateSearchTable">
		<?php for($i=0; $i<count($d2); $i++){ ?>
			<tr>
				<td class="td2"><hr></td>
				<td class="td2"><hr></td>
			</tr>
			<tr>
			<td width="50%">
				<div align="right">
					<?php echo '<a href="/tsumegos/play/'.$d2[$i][0]['Tsumego']['id'].'?sid='.$d2[$i][0]['Tsumego']['set_id'].'">'.$d2[$i][0]['Tsumego']['title'].'</a><br>'; ?>
					<div class="tooltipSvg" id="tooltipSvg<?php echo $counter++; ?>"></div>
				</div>
			</td>
			<td width="50%">
				<?php for($j=1; $j<count($d2[$i]); $j++){ ?>
					<?php echo '<a href="/tsumegos/play/'.$d2[$i][$j]['Tsumego']['id'].'?sid='.$d2[$i][$j]['Tsumego']['set_id'].'">'.$d2[$i][$j]['Tsumego']['title'].'</a><br>'; ?>
					<div class="tooltipSvg" id="tooltipSvg<?php echo $counter++; ?>"></div>
				<?php } ?>
				<div align="left"><a href="/sets/duplicates/<?php echo $id.'?unmark='.$d2[$i][0]['Tsumego']['dGroup']; ?>">Remove duplicate mark</a></div>
			</td>
			</tr>
			
		<?php } ?>
	</table>
	
	<?php
		if(count($d2)==0)
			echo '<div align="center">No duplicates found.</div>';
		$counter = 0;
		/*
		echo '<pre>'; print_r($d); echo '</pre>'; 
		echo '<pre>'; print_r($ts); echo '</pre>'; 
		echo '<pre>'; print_r(count($d)); echo '</pre>';
		 echo '<pre>'; print_r($similarArr); echo '</pre>';
		*/
		//echo '<pre>'; print_r($d2); echo '</pre>';
	?>
	
	<script>
		let similarArr = [];
		<?php
		
		for($a=0; $a<count($similarArr); $a++){
			echo 'similarArr['.$a.'] = [];';
			for($y=0; $y<count($similarArr[$a]); $y++){
				echo 'similarArr['.$a.']['.$y.'] = [];';
				for($x=0; $x<count($similarArr[$a][$y]); $x++){
					echo 'similarArr['.$a.']['.$y.'].push("'.$similarArr[$a][$x][$y].'");';
				}
			}
		}
		?>
		console.log(similarArr);
		<?php
		//echo 'createPreviewBoard(1, similarArr[1], '.$similarArrInfo[0][0].', '.$similarArrInfo[0][1].', '.$similarArrBoardSize[0].');';
		for($i=0; $i<count($similarArr); $i++){
			echo 'createPreviewBoard('.$i.', similarArr['.$i.'], '.$similarArrInfo[$i][0].', '.$similarArrInfo[$i][1].', '.$similarArrBoardSize[$i].');';
		}
		?>
		
	</script>
	
	<style>
		.duplicateSearchTable td{vertical-align: top;padding:14px;}
		.duplicateSearchTable .td2{padding:0 14px;}
		.tooltipSvg{padding-top:7px;}
	</style>