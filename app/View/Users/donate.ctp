	<div align="center">
	<?php if(
		!isset($_SESSION['loggedInUser']['User']['id'])
		|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
	)
		$upgrade = true;
	else
		$upgrade = false;
		?>
	</div>
	<div id="login-box2" class="users form">
		<div class="left2 signin">
			<u><b>20,00 € Tsumego Hero Premium (lifetime)</b></u><br><br>
		
			Thank you for considering  
			<?php
				if($upgrade)
					echo 'an upgrade!';
				else
					echo 'a donation!';
			?>
			 The following rewards await you:<br>
			<br>
			
			<table>
				<tr>
					<td>
						<img src="/img/hero powers.png" alt="Secret Area: Ko Gems" title="Secret Area: Ko Gems"><br>
					</td>
					<td>
						<b>Premium collections (<?php echo $premiumTsumegos; ?> problems)</b><br> 
						<?php
							for($i=0;$i<count($premiumSets);$i++){
								echo $premiumSets[$i]['Set']['title'];
								if($i<count($premiumSets)-1)
									echo ', ';
							}
						?>
						<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/hero power sandbox.png" alt="Sandbox" title="Sandbox"><br>
					</td>
					<td>
						<b>Sandbox</b><br>Contains <?php echo $overallCounter; ?> unpublished problems.<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/hp4.png" alt="Hero Power: Refinement" title="Hero Power: Refinement"><br>
					</td>
					<td>
						<b>Hero Power: Refinement</b><br>Creates a golden Tsumego.<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/hp6.png" alt="Hero Power: Revelation" title="Hero Power: Revelation"><br>
					</td>
					<td>
						<b>Hero Power: Revelation (Level 100)</b><br> Solves a problem, but you don't get any reward.<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/hp5.png" alt="Hero Power: Potion" title="Hero Power: Potion"><br>
					</td>
					<td>
						<b>Hero Power: Potion</b><br>Might refill hearts.<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/Unbenannt7.png" alt="no daily limit" title="no daily limit"><br>
					</td>
					<td>
						<b>No daily limit</b><br>Removes the current limit of 12000 XP.<br>
					</td>
				</tr>
				<tr>
					<td>
						<img src="/img/hpEmptyHeart.png" alt="no board locks" title="no board locks"><br>
					</td>
					<td>
						<b>No board locks</b><br>Removes the board locks on failed attempts while hearts are empty.<br>
					</td>
				</tr>
			</table>
			
			
			
		</div>
		<div class="right2">
			
			<div class="donate-paypal-right">
				<img src="/img/paypal-icon.png">
				<div class="donate-paypal-right-inner-2">
					<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
					<input type="hidden" name="cmd" value="_s-xclick" />
					<input type="hidden" name="hosted_button_id" value="RC7Z3QUNS9H4E" />
					<?php if($upgrade){ ?>
						<input type="image" id="donateH" src="/img/PayPal-Upgrade-Button.png" onmouseover="upgradeHover()" onmouseout="upgradeNoHover()" 
						border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Upgrade with PayPal button" />
					<?php }else{ ?>
						<input type="image" id="donateH" src="/img/PayPal-Donate-Button.png" onmouseover="donateHover()" onmouseout="donateNoHover()" 
						border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
					<?php } ?>
					<img alt="" border="0" src="https://www.paypal.com/en_EN/i/scr/pixel.gif" width="1" height="1" />
					</form>
				</div>
				<div class="donate-paypal-right-inner">
						Or to bank account:<br>
						Owner: Joschka Zimdars<br>
						IBAN: DE61200909002740631600<br>
						Bank: PSD Bank Nord eG<br>
						BIC: GENODEF1P08
				</div>
			</div>
		</div>
	</div>
	<br><br>
	<script>
		function donateHover(){
			document.getElementById("donateH").src = '/img/PayPal-Donate-ButtonH.png';
		}	
		function donateNoHover(){
			document.getElementById("donateH").src = "/img/PayPal-Donate-Button.png";
		}
		function upgradeHover(){
			document.getElementById("donateH").src = '/img/PayPal-Upgrade-ButtonH.png';
		}	
		function upgradeNoHover(){
			document.getElementById("donateH").src = "/img/PayPal-Upgrade-Button.png";
		}
	</script>