<?php
/**
 * @var View $this
 * @var array $a
 * @var int $unlockedCounter2
 */

?>
	
	<div align="center">
	<p class="profile-username">
		<?php echo isset($viewedUser) ? htmlspecialchars($viewedUser['name']) : 'Achievements'; ?>
	</p>
	<?php if (isset($viewedUser))
		echo $this->element('user_subnav', ['userID' => $viewedUser['id'], 'activeTab' => 'achievements']);
	?>
		<div align="center" id="achievementWrapper">
		<?php
		$unlockedCounter = 0;
				for($i=0; $i<count($a); $i++){
					$isActive = 'ac000i';
					//$a[$i]['Achievement']['unlocked'] = true;
					$displayColor = $a[$i]['Achievement']['color'];
					if($a[$i]['Achievement']['unlocked']){
						$isActive = $a[$i]['Achievement']['image'];
						$unlockedCounter++;
					}else $displayColor = 'achievementColorGray';
					if(strlen($a[$i]['Achievement']['name'])>30) $adjust = 'style="font-weight:normal;font-size:17px;"';
					else $adjust = '';
					?>
					<a href="/achievements/view/<?php echo $a[$i]['Achievement']['id']; ?>">
					<div align="center" class="achievement1 <?php echo $displayColor; ?>">
						<div class="acTitle">
							<h1 <?php echo $adjust; ?>><?php echo h($a[$i]['Achievement']['name']); ?></h1>
						</div>
						<div class="acImg">
							<img src="/img/<?php echo $isActive; ?>.png"><br>
							<?php 
							$a46style = '';
							if($a[$i]['Achievement']['id']==46 && $a[$i]['Achievement']['unlocked']){ 
								$a46style = ' style="top:-22px;"';
							?>
							<div class="acImgXp2">
								<?php echo $a[$i]['Achievement']['a46value']; ?>
							</div>
							<?php } ?>
							<div class="acImgXp"<?php echo $a46style; ?>>
							<?php echo $a[$i]['Achievement']['xp']; ?> XP
							</div>
						</div>
						<div class="acDesc">
							<?php echo h($a[$i]['Achievement']['description']); ?>
						</div>
						<?php if ($a[$i]['Achievement']['unlocked']) { ?>
						<div class="acDate">
							<?php 
							echo '<time datetime="' . Util::toIso8601($a[$i]['Achievement']['unlocked_at']) . '" data-format="datetime">' . $a[$i]['Achievement']['unlocked_at'] . '</time>';
							?>
						</div>
						<?php } ?>
					</div>
					</a>
				
				<?php
				}
				?>
				
				<div style="clear:both;"></div> 
			</div>
			<br>
			<br>
			<?php if (isset($viewedUser)):
				$name = $viewedUser['id'] != Auth::getUserID() ? htmlspecialchars($viewedUser['name']) : 'You';
				echo $name . ' completed ' . ($unlockedCounter + $unlockedCounter2) . ' of ' . count($a) . ' achievements.';
			endif; ?>
			<br>
			<br>
			
			
			
			
	</div>
	<script>
	 let trueBoardHeight = $("#achievementWrapper").height();
	</script>