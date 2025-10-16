	
	<div align="center" >
	<p class="title">
				<br>Achievements
				<br><br> 
				</p>
				<div class="achievemetIndexLink">
					<a href="/">Home</a>
				</div>
				<div class="achievemetProfileLink">
					<?php
						if(isset($_SESSION['loggedInUser']['User']['id'])){
							echo '<a href="/users/view/'.$_SESSION['loggedInUser']['User']['id'].'">Profile</a>';
						}
					?>
				</div>
		<div align="center" id="achievementWrapper">
		<?php
		$unlockedCounter = 0;
				for($i=0; $i<count($a); $i++){
					$isActive = 'ac000i';
					//$a[$i]['Achievement']['unlocked'] = true;
					if($a[$i]['Achievement']['unlocked']){
						$isActive = $a[$i]['Achievement']['image'];
						$unlockedCounter++;
					}else $a[$i]['Achievement']['color'] = 'achievementColorGray';
					if(strlen($a[$i]['Achievement']['name'])>30) $adjust = 'style="font-weight:normal;font-size:17px;"';
					else $adjust = '';
					?>
					<a href="/achievements/view/<?php echo $a[$i]['Achievement']['id']; ?>">
					<div align="center" class="achievement1 <?php echo $a[$i]['Achievement']['color']; ?>">
						<div class="acTitle">
							<h1 <?php echo $adjust; ?>><?php echo $a[$i]['Achievement']['name']; ?></h1>
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
							<?php echo $a[$i]['Achievement']['description']; ?>
						</div>
						<div class="acDate">
							<?php 
							$date = new DateTime($a[$i]['Achievement']['created']);
							echo $a[$i]['Achievement']['created'];
							?>
						</div>
					</div>
					</a>
				
				<?php
				}
				?>
				
				<div style="clear:both;"></div> 
			</div>
			<br>
			<br>
			<?php
				if(isset($_SESSION['loggedInUser']['User']['id'])){
					echo 'You completed '.($unlockedCounter+$unlockedCounter2).' of '.count($a).' achievements.';
				}
			?>
			<br>
			<br>
			
			
			
			
	</div>
	<script>
	 let trueBoardHeight = $("#achievementWrapper").height();
	</script>