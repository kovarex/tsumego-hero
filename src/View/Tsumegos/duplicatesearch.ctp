	<?php
?>
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
			<?php $sourceTsumegoButton->render(); ?>
			<div id="tooltipSvg-1"></div>
			</div>
		</td>
		<td width="50%">
		<?php
			foreach ($result as $item)
			{
				if ($similarDiff[$i]==0)
					$description1 = 'No difference. ';
				elseif ($similarDiff[$i]==1)
					$description1 = $similarDiff[$i].' stone different. ';
				else
					$description1 = $similarDiff[$i].' stones different. ';
				echo $item->title;
				$item->tsumegoButton->render();
				echo '<br>';
			}
			if (empty($result))
				echo 'No problems found.';
		?>
		</td>
		</tr>
	</table>
	<style>.duplicateSearchTable td{vertical-align: top;padding:14px;}</style>
