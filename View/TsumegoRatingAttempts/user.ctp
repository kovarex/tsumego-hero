

<div align="center">
<p class="title"><br> 
	Rating Mode History of <?php echo $uname; ?>
<br><br> 
</p>

<table width="85%" border="0" class="userstatstable">
	
	<tr>
		<th width="30%"><div align="left">Tsumego</div></th>
		<th width="12%">User rating</th>
		<th width="12%">Tsumego rating</th>
		<th width="12%">Result</th>
		<th width="12%">Time spent</th>
		<th width="22%"><div align="right">Time | Date</div></th>
	</tr>
	<?php
		for($i=0; $i<count($trs); $i++){
			if($trs[$i]['TsumegoAttempt']['tsumego_elo']!=null){
				echo '<tr>';
					echo '<td colspan="6">';
						echo '<div class="sandboxComment" id="comment0"><table class="sandboxTable2" width="100%" border="0">
								<tr>';
									echo '<td width="30%">';
										echo '<div align="left">'.$trs[$i]['TsumegoAttempt']['title'].'</div>';
									echo '</td>';
									echo '<td width="12%">';
										echo '<div align="center">'.$trs[$i]['TsumegoAttempt']['elo'].'</div>';
									echo '</td>';
									echo '<td width="12%">';
										echo '<div align="center">'.$trs[$i]['TsumegoAttempt']['tsumego_elo'].'</div>';
									echo '</td>';
									echo '<td width="12%">';
										echo '<div align="center">'.$trs[$i]['TsumegoAttempt']['status'].'</div>';
									echo '</td>';
									echo '<td width="12%">';
										echo '<div align="center">'.$trs[$i]['TsumegoAttempt']['seconds'].'</div>';
									echo '</td>';
									echo '<td width="22%">';
										echo '<div align="right">'.$trs[$i]['TsumegoAttempt']['created'].'</div>';
									echo '</td>';
						echo '</tr>
						</table>
						</div>';
						//echo '<div id="space0"><br></div>';
					echo '</td>';
					
				echo '</tr>';
			}
		}

	?>
	</table>
</div>

<?php
	//echo '<pre>'; print_r($trs); echo '</pre>';
?>