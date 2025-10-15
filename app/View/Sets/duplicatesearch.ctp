
	<div align="center">
	<p class="title">
		<br>
		<?php echo 'Duplicate Search Results'; ?><br><br>
	</p>
	</div>
	<table width="100%" border="0" class="duplicateSearchTable">
		<?php for($i=0; $i<count($s); $i++){ ?>
			<tr>
				<td class="td2">
				<?php
					if($s[$i]['Set']['public']==0)
						$sb = '<font style="color:gray;"> (sandbox)</font>';
					else
						$sb = '';
					
					$dup = '<b style="color:#6088c2;"> '.$s[$i]['Set']['dNum'].'</b>';
					echo '<a href="/sets/duplicates/'.$s[$i]['Set']['id'].'">'.$s[$i]['Set']['title'].' '.$s[$i]['Set']['title2'].'</a>'.$sb.$dup;
				?>
				</td>
			</tr>
			
		<?php } ?>
	</table>

	
	<style>
		.duplicateSearchTable td{vertical-align: top;padding:14px;}
		.duplicateSearchTable .td2{padding:0 14px;}
		.tooltipSvg{padding-top:7px;}
	</style>