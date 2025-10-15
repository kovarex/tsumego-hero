	<?php ?>
	<script src ="/js/previewBoard.js"></script>
	<div align="center">
	<p class="title">
		<br>
		Similar problem search for <?php echo $title; ?>
		<br>
	</p>
	</div>
	<table width="100%" border="0" class="duplicateSearchTable">
		<tr>
		<td width="50%">
			<div align="right">
			<?php echo '<a href="/tsumegos/play/'.$t['Tsumego']['id'].'">'.$title.'</a><br><br>'; ?>
			<div id="tooltipSvg-1"></div>
			</div>
		</td>
		<td width="50%">
		<?php 
			for($i=0; $i<count($similarArr); $i++){
				if($similarDiff[$i]==0) $description1 = 'No difference. ';
				else if($similarDiff[$i]==1) $description1 = $similarDiff[$i].' stone different. ';
				else $description1 = $similarDiff[$i].' stones different. ';
				echo '<a href="/tsumegos/play/'.$similarId[$i].'">'.$similarTitle[$i].'</a><br>';
				//echo $description1;
				//echo $similarDiffType[$i];
				echo '<br>';
				echo '<div id="tooltipSvg'.$i.'"></div><br>';
			}
			if(count($similarArr)==0)
				echo 'No problems found.';
		?>
		</td>
		</tr>
	</table>
	<script>
		let similarArr = [];
		<?php
		echo 'let tSgfArr = [];';
		for($y=0; $y<count($tSgfArr); $y++){
			echo 'tSgfArr['.$y.'] = [];';
			for($x=0; $x<count($tSgfArr[$y]); $x++){
				echo 'tSgfArr['.$y.'].push("'.$tSgfArr[$x][$y].'");';
			}
		}
		
		for($a=0; $a<count($similarArr); $a++){
			echo 'similarArr['.$a.'] = [];';
			for($y=0; $y<count($similarArr[$a]); $y++){
				echo 'similarArr['.$a.']['.$y.'] = [];';
				for($x=0; $x<count($similarArr[$a][$y]); $x++){
					echo 'similarArr['.$a.']['.$y.'].push("'.$similarArr[$a][$x][$y].'");';
				}
			}
		}
		echo 'createPreviewBoard(-1, tSgfArr, '.$tSgfArrInfo[0].', '.$tSgfArrInfo[1].', '.$tSgfArrBoardSize.');';
		for($i=0; $i<count($similarArr); $i++){
			echo 'createPreviewBoard('.$i.', similarArr['.$i.'], '.$similarArrInfo[$i][0].', '.$similarArrInfo[$i][1].', '.$similarArrBoardSize[$i].');';
		}
		?>
		
	</script>
	<style>.duplicateSearchTable td{vertical-align: top;padding:14px;}</style>