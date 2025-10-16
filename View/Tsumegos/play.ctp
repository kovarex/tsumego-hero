<?php if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210){ ?>
	<script src="/js/multipleChoice.js"></script>
	<style>.alertBox{height:auto!important;}</style>
<?php }else if($tv!=null){ ?>
	<script src="/js/multipleChoiceCustom.js"></script>
	<script src="/js/scoreEstimatingCustom.js"></script>
<?php } ?>
<link rel="stylesheet" type="text/css" href="/besogo/css/besogo.css">
<link rel="stylesheet" type="text/css" href="/besogo/css/board-flat.css">
<script src="/besogo/js/besogo.js"></script>
<script src="/besogo/js/transformation.js"></script>
<script src="/besogo/js/treeProblemUpdater.js"></script>
<script src="/besogo/js/nodeHashTable.js"></script>
<script src="/besogo/js/editor.js"></script>
<script src="/besogo/js/gameRoot.js"></script>
<script src="/besogo/js/status.js"></script>
<script src="/besogo/js/svgUtil.js"></script>
<script src="/besogo/js/cookieUtil.js"></script>
<script src="/besogo/js/parseSgf.js"></script>
<script src="/besogo/js/loadSgf.js"></script>
<script src="/besogo/js/saveSgf.js"></script>
<script src="/besogo/js/boardDisplay.js"></script>
<script src="/besogo/js/coord.js"></script>
<script src="/besogo/js/toolPanel.js"></script>
<script src="/besogo/js/filePanel.js"></script>
<script src="/besogo/js/controlPanel.js"></script>
<script src="/besogo/js/commentPanel.js"></script>
<script src="/besogo/js/treePanel.js"></script>
<script src="/besogo/js/diffInfo.js"></script>
<script src="/besogo/js/scaleParameters.js"></script>
<script src ="/FileSaver.min.js"></script>
<script src ="/js/previewBoard.js"></script>
<?php
	$choice = array();
	for($i=1;$i<=count($enabledBoards);$i++){
		if($enabledBoards[$i]=='checked') array_push($choice, $boardPositions[$i]);
	}
	$boardSize = 'large';
	shuffle($choice);

	if($t['Tsumego']['author']=='Stepan')
		$t['Tsumego']['author'] = 'Stepan Trubitsin';
	$authorx = $t['Tsumego']['author'];
	if($authorx=='Joschka Zimdars') $authorx = 'd4rkm4tter';
	else if($authorx=='Jérôme Hubert') $authorx = 'jhubert';
	else if($authorx=='Stepan Trubitsin') $authorx = 'Stepan';
	$heroPower1 = 'hp1x';
	$heroPower2 = 'hp2x';
	$heroPower3 = 'hp3x';
	$heroPower4 = 'hp4x';
	$heroPower5 = 'hp5x';
	
	if($eloScore2==0)
		$eloScore2d = '-0';
	else
		$eloScore2d = $eloScore2;
	if($eloScore2Rounded==0)
		$eloScore2dRounded = '-0';
	else
		$eloScore2dRounded = $eloScore2Rounded;
	
	if($lightDark=='dark'){
		$playGreenColor = '#0cbb0c';
		$playBlueColor = '#72a7f2';
		$xpDisplayColor = '#f0f0f0';
	}else{
		$playGreenColor = 'green';
		$playBlueColor = 'blue';
		$xpDisplayColor = 'black';
	}
	
	if($goldenTsumego){
		$xpDisplayColor = '#b5910b';
		echo '<style>#xpDisplay{font-weight:800;color:#b5910b;}</style>';
	}
	if(isset($formRedirect)){
		$duplicateLink = '';
		if(isset($t['Tsumego']['duplicateLink']))
			$duplicateLink = $t['Tsumego']['duplicateLink'];
		echo '<script type="text/javascript">window.location.href = "/tsumegos/play/'.$t['Tsumego']['id'].$t['Tsumego']['duplicateLink'].'";</script>';
	}
	if(isset($deleteProblem2)) echo '<script type="text/javascript">window.location.href = "/sets/view/'.$t['Tsumego']['set_id'].'";</script>';
	if($r10==1)
		echo '<script type="text/javascript">window.location.href = "/ranks/result";</script>';
	if($isSandbox){
		$sandboxComment = '(Sandbox)';
		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			echo '<script type="text/javascript">window.location.href = "/";</script>';
	}else $sandboxComment = '';
	if($sandboxComment2)
		$sandboxComment = '(reduced)';

	if($t['Tsumego']['set_id']==6473 ||$t['Tsumego']['set_id']==11969 || $t['Tsumego']['set_id']==29156 || $t['Tsumego']['set_id']==31813 || $t['Tsumego']['set_id']==33007
	|| $t['Tsumego']['set_id']==71790 || $t['Tsumego']['set_id']==74761 || $t['Tsumego']['set_id']==81578 || $t['Tsumego']['set_id']==88156){
		echo '<style>#xpDisplay{font-weight:800;color:#60167d;}</style>';
	}
	if($t['Tsumego']['premium']==1){
		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			echo '<script type="text/javascript">window.location.href = "/";</script>';
	}

	if($goldenTsumego) echo '<script type="text/javascript" src="/'.$boardSize.'/board46.js"></script>'; //Golden
	else if($t['Tsumego']['set_id']==11969) echo '<script type="text/javascript" src="/'.$boardSize.'/board23.js"></script>'; //Pretty Area
	else if($t['Tsumego']['set_id']==29156) echo '<script type="text/javascript" src="/'.$boardSize.'/board24.js"></script>'; //Hunting
	else if($t['Tsumego']['set_id']==31813) echo '<script type="text/javascript" src="/'.$boardSize.'/board25.js"></script>'; //The Ghost
	else if($t['Tsumego']['set_id']==33007) echo '<script type="text/javascript" src="/'.$boardSize.'/board26.js"></script>'; //Carnage
	else if($t['Tsumego']['set_id']==71790) echo '<script type="text/javascript" src="/'.$boardSize.'/board27.js"></script>'; //Blind Spot
	else if($t['Tsumego']['set_id']==74761) echo '<script type="text/javascript" src="/'.$boardSize.'/board28.js"></script>'; //Giants
	else if($t['Tsumego']['set_id']==81578) echo '<script type="text/javascript" src="/'.$boardSize.'/board29.js"></script>'; //Moves of Resistance
	else if($t['Tsumego']['set_id']==88156) echo '<script type="text/javascript" src="/'.$boardSize.'/board30.js"></script>'; //Hand of God
	else if($t['Tsumego']['set_id']==6473) echo '<script type="text/javascript" src="/'.$boardSize.'/board55.js"></script>'; //Tsumego Grandmaster
	else echo '<script type="text/javascript" src="/'.$boardSize.'/board'.$choice[0][0].'.js"></script>'; // Regular
	
	if($goldenTsumego) $choice[0] = array(46,'texture46','black34.png','white34.png'); //Golden
	else if($t['Tsumego']['set_id']==11969) $choice[0] = $boardPositions[44]; //Pretty Area
	else if($t['Tsumego']['set_id']==29156) $choice[0] = $boardPositions[45]; //Hunting
	else if($t['Tsumego']['set_id']==31813) $choice[0] = $boardPositions[46]; //The Ghost
	else if($t['Tsumego']['set_id']==33007) $choice[0] = $boardPositions[47]; //Carnage
	else if($t['Tsumego']['set_id']==71790) $choice[0] = $boardPositions[48]; //Blind Spot
	else if($t['Tsumego']['set_id']==74761) $choice[0] = $boardPositions[49]; //Giants
	else if($t['Tsumego']['set_id']==81578) $choice[0] = $boardPositions[50]; //Moves of Resistance
	else if($t['Tsumego']['set_id']==88156) $choice[0] = $boardPositions[50]; //Hand of God
	else echo '<script type="text/javascript" src="/'.$boardSize.'/board'.$choice[0][0].'.js"></script>'; // Regular
	if(isset($_SESSION['lastVisit'])) $lv = $_SESSION['lastVisit'];
	else $lv = '15352';
	$a1 = '';
	$b1 = '';
	$c1 = '';
	$d1 = '';
	$x2 = '';
	$ansDisplay = 'ans';
	$playerColor = array();
	$pl = 0;
	$plRand = false;
	if($colorOrientation=='black') 
		$pl = 0;
	else if($colorOrientation=='white') 
		$pl = 1;
	else if($tv!=null){
		$pl = 0;
	}else{
		$pl = rand(0,1);
		$plRand = true;
	}
	if($checkBSize!=19 || $t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161 || $t['Tsumego']['set_id']==239 
	|| $t['Tsumego']['set_id']==243 || $t['Tsumego']['set_id']==244 || $t['Tsumego']['set_id']==246 || $t['Tsumego']['set_id']==251 || $t['Tsumego']['set_id']==253)
		$pl=0;
	if($pl==0){
		$playerColor[0] = 'BLACK';
		$playerColor[1] = 'WHITE';
	}else{
		$playerColor[0] = 'WHITE';
		$playerColor[1] = 'BLACK';
	}
	if($pl==0)
		$descriptionColor = 'Black ';
	else 
		$descriptionColor = 'White ';
		
	if($startingPlayer==1 && $plRand==true){
		if($descriptionColor=='Black ')
			$descriptionColor = 'White ';
		else if($descriptionColor=='White ')
			$descriptionColor = 'Black ';
	}	
	$t['Tsumego']['description'] = str_replace('[b]', $descriptionColor, $t['Tsumego']['description']);
	if($nothingInRange!=false)
		echo '<div align="center" style="color:red;font-weight:800;">'.$nothingInRange.'</div>';
	?>
	<table width="100%" border="0">
	<tr>
	<td align="center" width="29%">
		<div id="health">
			<?php

			$health = $user['User']['health'] - $user['User']['damage'];
			for($i=0; $i<$health; $i++){
				echo '<img title="Heart" id="heart'.$i.'" src="/img/'.$fullHeart.'.png">';
			}
			for($i=0; $i<$user['User']['damage']; $i++){
				$h = $health+$i;
				echo '<img title="Empty Heart" id="heart'.$h.'" src="/img/'.$emptyHeart.'.png">';
			}
			?>
		</div>
	</td>
	<td align="center" width="42%">
	<table>
	<tr>
		<td align="center">
			<div id="playTitle">
				<?php
					if(strlen($t['Tsumego']['file'])>4){
						$di = ' - ';
						$di2 = '';
						$anz = '';
					}else{
						$di = ' ';
						$di2 = '/';
					}
					if($mode==2)
						$inFavorite='';
					$hasPartition = '';
					if($partition>0)
						$hasPartition = '?partition='.$partition;
					if($query=='difficulty' || $query=='tags'){
						echo '<a id="playTitleA" href="/sets/view/'.$_SESSION['lastSet'].$hasPartition.'">'.$queryTitle.'</a><br>';
						echo '<font style="font-weight:400;" color="grey">
							<a style="color:grey;" id="playTitleA" href="/sets/view/'.$set['Set']['id'].'">
								('.$set['Set']['title'].$di.$t['Tsumego']['actualNum'].$di2.$amountOfOtherCollection.')
							</a>
						</font>';
					}else if($query=='topics' && !$favorite){
						if($inFavorite==''){
							if($mode==1){
								if($t['Tsumego']['set_id']==38){
									if($t['Tsumego']['file']<=17) $altTitle = 'Essential Tsumego Vol. I';
									elseif($t['Tsumego']['file']<=28) $altTitle = 'Essential Tsumego Vol. II';
									else $altTitle = 'How to win the capturing race';
									echo '<a id="playTitleA" href="/sets/view/'.$set['Set']['id'].'">'.$altTitle.$di.$t['Tsumego']['file'].$di2.$anz.'</a>';
								}else{
									echo '<a id="playTitleA" href="/sets/view/'.$set['Set']['id'].$hasPartition.'">'.$set['Set']['title'].' '.$queryTitleSets.' '.$di.$t['Tsumego']['file'].$di2.$anz.'</a>';
								}
							}elseif($mode==2){
								//echo '<a id="playTitleA" href="/sets/view/'.$set['Set']['id'].'">'.$set['Set']['title'].$di.$t['Tsumego']['file'].$di2.$anz.'</a>';
								echo '<div class="slidecontainer">
									<input type="range" min="1" max="7" value="'.$difficulty.'" class="slider" id="rangeInput" name="rangeInput">
									<div id="sliderText">regular</div>
								</div>';
								echo '<a id="playTitleA" href=""></a>';
							}elseif($mode==3){
								echo '<font size="5px">'.$currentRankNum.' of '.$stopParameter.'</font>';
							}
						}
					}else{
						echo '<a id="playTitleA" href="/sets/view/1">Favorites</a><br>';
						echo '<font style="font-weight:400;" color="grey">
							<a style="color:grey;" id="playTitleA" href="/sets/view/'.$set['Set']['id'].'">
								('.$set['Set']['title'].$di.$t['Tsumego']['file'].$di2.$anz.')
							</a>
						</font>';
					}
				?>
				<br>
				<?php
				if($t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161){
					if($additionalInfo['mode']==0){
				?>
						<table class="gameInfo" style="padding-top:7px;">
						<tr>
						<td>
						Black:
						</td>
						<td>
						White:
						</td>
						</tr>
						<tr>
						<td style="padding-left:20px;padding-right:20px;">
						<?php echo $additionalInfo['playerNames'][0]; ?>
						</td>
						<td>
						<?php echo $additionalInfo['playerNames'][1]; ?>
						</td>
						</tr>
						</table>
					<?php }else{
						if(count($additionalInfo['playerNames'])==4){
					?>
						<table class="gameInfo" style="padding-top:7px;">
						<tr>
						<td>
						<?php echo $additionalInfo['playerNames'][0].' on '.$additionalInfo['playerNames'][1]; ?>
						</td>
						</tr>
						</table>
						<table class="gameInfo" style="padding-top:7px;">
						<tr>
						<td>
						Black:
						</td>
						<td>
						White:
						</td>
						</tr>
						<tr>
						<td style="padding-left:20px;padding-right:20px;">
						<?php echo $additionalInfo['playerNames'][2]; ?>
						</td>
						<td>
						<?php echo $additionalInfo['playerNames'][3]; ?>
						</td>
						</tr>
						</table>
				<?php }}} ?>
			</div>
		</td>
	</tr>
	<tr>
		<td align="center">
		<?php 
		if($mode==1)
			echo '<div id="titleDescription" class="titleDescription1">';
		elseif($mode==2  || $mode==3)
			echo '<div id="titleDescription" class="titleDescription2">';
		if($t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161)
			echo '<b>'.$t['Tsumego']['description'].'</b> '; 
		else
			echo '<a id="descriptionText">'.$t['Tsumego']['description'].'</a> '; 
		if(isset($t['Tsumego']['hint']) && $t['Tsumego']['hint']!='')
			echo '<font color="grey" style="font-style:italic;">('.$t['Tsumego']['hint'].')</font>';
		if($tv!=null){
			if($tv['TsumegoVariant']['type']=='score_estimating'){
				echo '<br>';
				echo 'Black captures: '.$tv['TsumegoVariant']['answer2'].' | ';
				echo 'White captures: '.$tv['TsumegoVariant']['answer3'].' | ';
				echo 'Komi: '.$tv['TsumegoVariant']['answer1'].' ';
			}
		}
		if($_SESSION['loggedInUser']['User']['isAdmin']>0){
		?>
		<a class="modify-description" href="#">(Edit)</a>
		<div class="modify-description-panel">
			<?php
				$placeholder = str_replace($descriptionColor, '[b]', $t['Tsumego']['description']);
				echo $this->Form->create('Comment');
				echo $this->Form->input('id', array('type' => 'hidden', 'value' => $t['Tsumego']['id']));
				echo $this->Form->input('admin_id', array('type' => 'hidden', 'value' => $_SESSION['loggedInUser']['User']['id']));
				echo $this->Form->input('modifyDescription', array('value' => $placeholder, 'label' => '', 'type' => 'text', 'placeholder' => 'Description'));
				echo $this->Form->input('modifyHint', array('value' => $t['Tsumego']['hint'], 'label' => '', 'type' => 'text', 'placeholder' => 'Hint'));
				if(true) $modifyDescriptionType = 'text';
				else $modifyDescriptionType = 'hidden';
				echo $this->Form->input('modifyElo', array('value' => $t['Tsumego']['elo_rating_mode'], 'label' => '', 'type' => $modifyDescriptionType, 'placeholder' => 'Rating'));
				echo $this->Form->input('modifyAuthor', array('value' => $t['Tsumego']['author'], 'label' => '', 'type' => $modifyDescriptionType, 'placeholder' => 'Author'));
				echo $this->Form->input('deleteTag', array('label' => '', 'type' => 'text', 'placeholder' => 'Delete Tag'));
				if($isSandbox)
					echo $this->Form->input('deleteProblem', array('value' => '', 'label' => '', 'type' => 'text', 'placeholder' => 'delete'));
				echo $this->Form->end('Submit');
			?>
		</div>
		<?php } ?>
		</div>
		</td>
	</tr>
	</table>
	</td>
	<td align="center" width="29%">
		<?php
			if($mode==1){
				if($user['User']['level']>=20 && $sprintEnabled==1){
					echo '
						<a href="#"><img id="sprint" title="Sprint: Double XP for 2 minutes." alt="Sprint" src="/img/hp1.png"
						onmouseover="sprintHover()" onmouseout="sprintNoHover()" onclick="sprint(); return false;"></a>
					';
				}else{
					echo '
						<a href="#"><img id="sprint" title="Sprint (Level 20): Double XP for 2 minutes." src="/img/'.$heroPower1.'.png"
						style="cursor: context-menu;" alt="Sprint"></a>
					';
				}
				if($user['User']['level']>=30 && $intuitionEnabled==1){
					echo '
						<a href="#"><img id="intuition" title="Intuition: Shows the first correct move." alt="Intuition" src="/img/hp2.png"
						onmouseover="intuitionHover()" onmouseout="intuitionNoHover()" onclick="intuition(); return false;"></a>
					';
				}else{
					echo '
						<a href="#"><img id="intuition" title="Intuition (Level 30): Shows the first correct move." src="/img/'.$heroPower2.'.png"
						style="cursor: context-menu;" alt="Intuition"></a>
					';
				}
				if($user['User']['level']>=40 && $rejuvenationEnabled==1){
					echo '
						<a href="#"><img id="rejuvenation" title="Rejuvenation: Restores health, Intuition and locks." src="/img/hp3.png"
						onmouseover="rejuvenationHover()" onmouseout="rejuvenationNoHover()" onclick="rejuvenation(); return false;"></a>
					';
				}else{
					echo '
						<a href="#"><img id="rejuvenation" title="Rejuvenation (Level 40): Restores health, Intuition and locks." src="/img/'.$heroPower3.'.png"
						style="cursor: context-menu;" alt="Rejuvenation"></a>
					';
				}
				if($user['User']['premium']>0 || $user['User']['level']>=100){
					if($refinementEnabled==1){
						echo '
							<a href="#"><img id="refinement" title="Refinement: Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/hp4.png"
							onmouseover="refinementHover()" onmouseout="refinementNoHover()" onclick="refinement(); return false;"></a>
						';
					}else{
						echo '
							<a href="#"><img id="refinement" title="Refinement (Level 100 or Premium): Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/'.$heroPower4.'.png"
							style="cursor: context-menu;" alt="Refinement"></a>
						';
					}
				}else{
					echo '
						<a href="#"><img id="refinement" title="Refinement (Level 100 or Premium): Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/'.$heroPower4.'.png"
						style="cursor: context-menu;" alt="Refinement"></a>
					';
				}
				if($hasRevelation){
					echo '
							<a href="#" class="revelation-anchor"><img id="revelation" title="Revelation ('.$user['User']['revelation'].'): Solves a problem, but you don\'t get any reward." src="/img/hp6x.png"
							onmouseover="revelationHover()" onmouseout="revelationNoHover()" onclick="revelation(); return false;"></a>
					';
				}
				if($user['User']['premium']>0){
					if($potionActive){
						echo '
							<img id="potion" title="Potion (Passive): If you misplay and have no hearts left, you have a small chance to restore your health." src="/img/hp5.png">
						';
					}
				}
			}else{
				echo '
					<a href="#"><img id="sprint" title="Sprint (Level 20): Double XP for 2 minutes." src="/img/hp1x.png"
					style="cursor: context-menu;" alt="Sprint"></a>
				';
				echo '
					<a href="#"><img id="intuition" title="Intuition (Level 30): Shows the first correct move." src="/img/hp2x.png"
					style="cursor: context-menu;" alt="Intuition"></a>
				';
				echo '
					<a href="#"><img id="rejuvenation" title="Rejuvenation (Level 40): Restores health, Intuition and locks." src="/img/hp3x.png"
					style="cursor: context-menu;" alt="Rejuvenation"></a>
				';
				echo '
					<a href="#"><img id="refinement" title="Refinement (Level 100 or Premium): Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/hp4x.png"
					style="cursor: context-menu;" alt="Refinement"></a>
				';
			}
		?>
	</td>
	</tr>
	</table>
	<div align="center" id="xpDisplayDiv">
		<table class="xpDisplayTable" border="0" width="70%">
			<tr>
			<td width="33%">
				<?php if($mode!=3){ ?>
				<div id="xpDisplay" align="center">
					<font size="4" color="<?php echo $xpDisplayColor; ?>"></font>
				</div>
				<?php }else{ ?>
				<div id="xpDisplay" align="center"></div>
				<div id="time-mode-countdown">
					10.0
				</div>
				<div id="plus2">+2</div>
				<?php } ?>
			</td>
			<td width="33%">
				<div id="status" align="center" style="color:black;"></div>
			</td>
			<td width="33%">
				<div id="status2" align="center" style="color:black;">
				<font size="4">
				<?php
				echo  $tRank.' <font color="grey">('.$t['Tsumego']['elo_rating_mode'].')</font>';
				?>
				</font>
				</div>
			</td>
			</tr>
		</table>
	</div>
	<div align="center">
		<div id="theComment">

		</div>
	</div>
	<?php if($dailyMaximum) echo '<style>.upgradeLink{display:block;}</style>'; ?>
	<div class="upgradeLink" align="center">
		<a href="/users/donate">Upgrade to Premium</a>
	</div>

	<!-- BOARD -->
	<?php if($ui==2){ ?>
		<div id="target"></div>
		<div id="targetLockOverlay"></div>
	<?php }else{ ?>
		<div id="board" align="center"></div>
	<?php } ?>
	<?php if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210 || ($tv!=null&&$tv['TsumegoVariant']['type']=='multiple_choice')){ ?>
	<div align="center">
	<br>
		<a href="/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>" title="reset problem" id="besogo-next-button">Reset</a>
		<br><br>
	</div>
	<?php }else if($tv!=null&&$tv['TsumegoVariant']['type']=='score_estimating'){ ?>
	<div align="center">
		<?php if($t['Tsumego']['set_id']==262){ ?>
			<br>
			<a id="submitScoreEstimatingBlackWins" href="#">Black wins</a>
			<?php if(substr($tv['TsumegoVariant']['answer1'],-2) !== '.5') { ?>
				<a id="submitScoreEstimatingJigo" href="#">Jigo</a>
			<?php } ?>
			<a id="submitScoreEstimatingWhiteWins" href="#">White wins</a>
		<?php }else{ ?>
			<a href="/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>" title="reset problem" id="besogo-next-button">Reset</a>
			<input value="0" placeholder="Score" type="text" id="ScoreEstimatingSE">
			<a id="submitScoreEstimatingSE" href="#">Submit</a>
		<?php } ?>
		<br><br>
	</div>
	<?php } ?>
	<div id="errorMessageOuter" align="center">
		<div id="errorMessage"></div>
	</div>
	<?php
	if($mode==1 || $mode==3){
		$naviAdjust1 = 38;
	}elseif($mode==2){
		$naviAdjust1 = 25;
	}
	?>

	<?php if(!$checkNotInSearch){ ?>
	<?php if($firstRanks==0){ ?>
	<div class="tsumegoNavi1">
		<div class="tsumegoNavi2">
			<?php
			for($i=0; $i<count($navi); $i++){
				if($t['Tsumego']['id'] == $navi[$i]['Tsumego']['id']) $additionalId = 'id="currentElement"';
				else $additionalId = '';
				if(!isset($navi[$i]['Tsumego']['duplicateLink']))
					$duplicateLink = '';
				else
					$duplicateLink = $navi[$i]['Tsumego']['duplicateLink'];
				if($inFavorite=='?favorite=1')
					$duplicateLink = '';
				
				echo '<li '.$additionalId.' id="naviElement'.$i.'" class="'.$navi[$i]['Tsumego']['status'].'">
					<a id="tooltip-hover'.$i.'" class="tooltip" href="/tsumegos/play/'.$navi[$i]['Tsumego']['id'].$duplicateLink.$inFavorite.'">
					'.$navi[$i]['Tsumego']['num'].'<span><div id="tooltipSvg'.$i.'"></div></span></a>
					</li>';
				if($i==0 || $i==count($navi)-2) echo '<li class="setBlank"><a></a></li>';
			}
			?>
		</div>
	</div>
	<?php 
		}else{
			echo '<div id="currentElement"></div>';
		}		
	}else{
		echo '<div id="currentElement"></div>';
	}
?>
	<div align="center">
	<?php 
	
	if(isset($_SESSION['loggedInUser']['User']['id'])){
		if($firstRanks==0){
			$makeProposal = '';
			$proposalSentColor = '';
			if($_SESSION['loggedInUser']['User']['isAdmin'] > 0)
				$makeProposal = 'Open';
			else{
				if(!$hasSgfProposal){
					if($isAllowedToContribute)
						$makeProposal = 'Make Proposal';
				}else{
					$makeProposal = 'Proposal sent';
					$proposalSentColor = 'color:#717171;';
				}
			}
			$getTitle = str_replace('&','and',$set['Set']['title']);
			$getTitle .= ' '.$t['Tsumego']['num'];
		?>
		<div class="tag-container" align="center">
			<div class="tag-list"></div>
			<?php if($isAllowedToContribute2){ ?>
			<div class="add-tag-list-button"><a class="add-tag-list-anchor" id="open-add-tag-menu">
			<?php if($isAllowedToContribute){ ?>
			<?php if($firstRanks==0 && $t['Tsumego']['set_id']!=181 && $t['Tsumego']['set_id']!=191){ ?>
				Add tag
			<?php } ?>
			<?php } ?>
			</a></div>
			<?php }else{
				echo '<div style="color:gray;font-size:14px">Daily limit reached.</div>';
			} ?>
			<div class="add-tag-list-popular"></div>
			<div class="add-tag-list"></div>
		</div>
		<br>
		<?php
			echo '<a id="showx5" style="margin-right:20px;'.$proposalSentColor.'" class="selectable-text">'.$makeProposal.'</a>';
			echo '<a id="showx3" style="margin-right:20px;" class="selectable-text">Download SGF</a>';
			echo '<a id="showx7x" style="margin-right:20px;" class="selectable-text">Find Similar Problems</a>';
			echo '<a id="showFilters" class="selectable-text">Filters<img id="greyArrowFilter" src="/img/greyArrow1.png"></a>';
			echo '<br><br>';
			echo '<div class="filters-outer">
				<div id="msgFilters">
					<div class="active-tiles-container tiles-view"></div>
				</div>
			</div>';
			if(isset($_SESSION['loggedInUser']['User']['id'])){
				if($t['Tsumego']['duplicate']!=0 && $t['Tsumego']['duplicate']!=-1 && $mode!=2){
					echo '<div class="duplicateTable">Is duplicate group:<br>';
					for($i=0; $i<count($duplicates); $i++){
						echo '<a href="/tsumegos/play/'.$duplicates[$i]['SetConnection']['tsumego_id'].'?sid='.$duplicates[$i]['SetConnection']['set_id'].'">'
						.$duplicates[$i]['SetConnection']['title'].'</a>';
						if($i!=count($duplicates)-1)
							echo ', ';
					}
					echo '</div><br>';
				}
			}
			if($sgf['Sgf']['user_id']!=33)
				$adHighlight = 'historyLink';
			else
				$adHighlight = '';
				
			if($_SESSION['loggedInUser']['User']['isAdmin']>=1){
					echo '<a id="showx99" style="margin-right:20px;" class="selectable-text">Admin-Request Solution</a>';
					echo '<a id="showx4" style="margin-right:20px;" class="selectable-text">Admin-Download</a>';
					echo '<a id="show4" style="margin-right:20px;" class="selectable-text">Admin-Upload<img id="greyArrow4" src="/img/greyArrow1.png"></a>';
					echo '<a id="showx6" style="margin-right:20px;" class="selectable-text '.$adHighlight.'">History</a>';
					echo '<a id="showx8" style="margin-right:20px;" class="selectable-text">Rating</a>';
					echo '<a id="show5" class="selectable-text">Settings<img id="greyArrow5" src="/img/greyArrow1.png"></a>';
					if($virtual_children==1){
						$vcOn = 'checked="checked"';
						$vcOff = '';
					}else{
						$vcOn = '';
						$vcOff = 'checked="checked"';
					}
					if($alternative_response==1){
						$arOn = 'checked="checked"';
						$arOff = '';
					}else{
						$arOn = '';
						$arOff = 'checked="checked"';
					}
					if($passEnabled==1){
						$passOn = 'checked="checked"';
						$passOff = '';
					}else{
						$passOn = '';
						$passOff = 'checked="checked"';
					}
					if($set_duplicate==-1){
						$duOn = 'checked="checked"';
						$duOff = '';
					}else{
						$duOn = '';
						$duOff = 'checked="checked"';
					}
					if($tv==null){
						$multipleNo = 'checked="checked"';
						$multipleYes = '';
						$scoreEstNo = 'checked="checked"';
						$scoreEstYes = '';
					}else{
						if($tv['TsumegoVariant']['type'] == 'multiple_choice'){
							$multipleNo = '';
							$multipleYes = 'checked="checked"';
							$scoreEstNo = 'checked="checked"';
							$scoreEstYes = '';
						}else{
							$multipleNo = 'checked="checked"';
							$multipleYes = '';
							$scoreEstNo = '';
							$scoreEstYes = 'checked="checked"';
						}
					}
					if($_SESSION['loggedInUser']['User']['isAdmin']>=1){
					echo '<div id="msg4">
							<br>
							<form action="" method="POST" enctype="multipart/form-data">
								<input type="file" name="adminUpload" />
								<input value="Submit" type="submit"/>
							</form>
						</div>
						<div id="msg5">
							<br>
							<form action="" method="POST" enctype="multipart/form-data">
								<table>
									<tr>
										<td>Merge recurring positions</td>
										<td><input type="radio" id="r38" name="data[Settings][r38]" value="on" '.$vcOn.'><label for="r38">on</label></td>
										<td><input type="radio" id="r38" name="data[Settings][r38]" value="off" '.$vcOff.'><label for="r38">off</label></td>
									</tr>
									<tr>
										<td>Alternative Response Mode</td>
										<td><input type="radio" id="r39" name="data[Settings][r39]" value="on" '.$arOn.'><label for="r39">on</label></td>
										<td><input type="radio" id="r39" name="data[Settings][r39]" value="off" '.$arOff.'><label for="r39">off</label></td>
									</tr>
									<tr>';
										if($t['Tsumego']['duplicate']==0 || $t['Tsumego']['duplicate']==-1){
											echo '<td>Mark as duplicate</td>';
											echo '<td><input type="radio" id="r40" name="data[Settings][r40]" value="off" '.$duOff.'><label for="r40">no</label></td>
											<td><input type="radio" id="r40" name="data[Settings][r40]" value="on" '.$duOn.'><label for="r40">yes</label></td>';
										}else{
											
										}
									echo '</tr>';
									echo '<tr>
										<td>Enable passing</td>
										<td><input type="radio" id="r43" name="data[Settings][r43]" value="no" '.$passOff.'><label for="r43">no</label></td>
										<td><input type="radio" id="r43" name="data[Settings][r43]" value="yes" '.$passOn.'><label for="r43">yes</label></td>
									</tr>';
									if($isSandbox){
										echo '<tr>
											<td>Multiple choice problem</td>
											<td><input type="radio" id="r41" name="data[Settings][r41]" value="no" '.$multipleNo.'><label for="r41">no</label></td>
											<td><input type="radio" id="r41" name="data[Settings][r41]" value="yes" '.$multipleYes.'><label for="r41">yes</label></td>
										</tr>';
										echo '<tr>
											<td>Score estimating problem</td>
											<td><input type="radio" id="r42" name="data[Settings][r42]" value="no" '.$scoreEstNo.'><label for="r42">no</label></td>
											<td><input type="radio" id="r42" name="data[Settings][r42]" value="yes" '.$scoreEstYes.'><label for="r42">yes</label></td>
										</tr>';
									}
								echo '</table>
								<br>
								<input value="Submit" type="submit"/>
							</form>
						</div>
						<br><br>';
				}
				if($isSandbox && $tv!=null){
					if($tv['TsumegoVariant']['type']=='multiple_choice'){
						$studyCorrectOptions = array(
							'1'=>'1',
							'2'=>'2',
							'3'=>'3',
							'4'=>'4'
						);
						$studyCorrectAttributes = array(
							'legend'=>false,
							'value'=>$tv['TsumegoVariant']['numAnswer']
						);
						echo '<a id="showMC" class="selectable-text" style="display: inline-block;">Edit multiple choice
						<img id="greyArrowMC" src="/img/greyArrow1.png"></a><br><br>';
						echo '<div id="showxMC">';
						echo $this->Form->create('Study');
						echo $this->Form->input('study1', array('value' => $tv['TsumegoVariant']['answer1'], 'label' => 'Answer 1: ', 'type' => 'text', 'placeholder' => 'Answer 1'));
						echo $this->Form->input('study2', array('value' => $tv['TsumegoVariant']['answer2'], 'label' => 'Answer 2: ', 'type' => 'text', 'placeholder' => 'Answer 2'));
						echo $this->Form->input('study3', array('value' => $tv['TsumegoVariant']['answer3'], 'label' => 'Answer 3: ', 'type' => 'text', 'placeholder' => 'Answer 3'));
						echo $this->Form->input('study4', array('value' => $tv['TsumegoVariant']['answer4'], 'label' => 'Answer 4: ', 'type' => 'text', 'placeholder' => 'Answer 4'));
						echo $this->Form->input('explanation', array('value' => $tv['TsumegoVariant']['explanation'], 'label' => 'Explanation: ', 'type' => 'textfield', 'placeholder' => 'Explanation'));
						echo '<br>';
						echo '<label for="studyCorrect">Correct: </label>';
						echo $this->Form->radio('studyCorrect', $studyCorrectOptions, $studyCorrectAttributes);
						echo '<br><br>';
						echo $this->Form->end('Submit');
						echo '<br><br>';
						echo '</div>';
					}else{
						echo '<a id="showSE" class="selectable-text" style="display: inline-block;">Edit correct answer
						<img id="greyArrowSE" src="/img/greyArrow1.png"></a><br><br>';
						echo '<div id="showxSE">';
						echo '<input type="button" value="Black wins" id="besogo-se-edit-black">';
						echo '<input type="button" value="White wins" id="besogo-se-edit-white">';
						echo '<input type="button" value="-" id="besogo-se-edit-less">';
						echo '<input type="button" value="+" id="besogo-se-edit-more">';
						echo '<br><br>';
						echo $this->Form->create('Study2');
						echo '<table><tr><td>';
						//echo $this->Form->input('winner', array('value' => $tv['TsumegoVariant']['winner'], 'label' => 'Correct answer: ', 'type' => 'text', 'placeholder' => 'Score', 'id' => 'scoreEstEditField'));
						echo '<label for="scoreEstEditField">Correct answer: </label>';
						echo '</td><td>';
						echo '<input name="data[Study2][winner]" value="'.$tv['TsumegoVariant']['winner'].'" placeholder="Score" id="scoreEstEditField" type="text">';
						echo '</td></tr><tr><td>';
						//echo $this->Form->input('answer1', array('value' => $tv['TsumegoVariant']['answer1'], 'label' => 'Komi: ', 'type' => 'text', 'placeholder' => 'Komi', 'id' => 'scoreEstEditField2'));
						echo '<label for="scoreEstEditField2">Komi: </label>';
						echo '</td><td>';
						echo '<input name="data[Study2][answer1]" value="'.$tv['TsumegoVariant']['answer1'].'" placeholder="Komi" id="scoreEstEditField2" type="text">';
						echo '</td></tr><tr><td>';
						//echo $this->Form->input('answer2', array('value' => $tv['TsumegoVariant']['answer2'], 'label' => 'Black captures: ', 'type' => 'text', 'placeholder' => 'Black captures', 'id' => 'scoreEstEditField3'));
						echo '<label for="scoreEstEditField3">Black captures: </label>';
						echo '</td><td>';
						echo '<input name="data[Study2][answer2]" value="'.$tv['TsumegoVariant']['answer2'].'" placeholder="Black captures" id="scoreEstEditField3" type="text">';
						echo '</td></tr><tr><td>';
						//echo $this->Form->input('answer3', array('value' => $tv['TsumegoVariant']['answer3'], 'label' => 'White captures: ', 'type' => 'text', 'placeholder' => 'White captures', 'id' => 'scoreEstEditField4'));
						echo '<label for="scoreEstEditField4">White captures: </label>';
						echo '</td><td>';
						echo '<input name="data[Study2][answer3]" value="'.$tv['TsumegoVariant']['answer3'].'" placeholder="White captures" id="scoreEstEditField4" type="text">';
						echo '</td></tr></table>';
						echo '<br>';
						echo '<div class="submit"><input type="submit" value="Submit" id="scoreEstEditSubmit"></div>';
						echo '<br>';
						echo '</div>';
					}
				}
			}else echo '<br>';
			//if($_SESSION['loggedInUser']['User']['premium']>=1){
			//if($t['Tsumego']['set_id']!=122 && $t['Tsumego']['set_id']!=124 && $t['Tsumego']['set_id']!=127 && $t['Tsumego']['set_id']!=139){
			$allowed = true;
			if(isset($_SESSION['loggedInUser']['User']['id'])){
				if($_SESSION['loggedInUser']['User']['id']!=5080){
					echo '<div id="msg1">Leave a <a id="show">message<img id="greyArrow1" src="/img/greyArrow1.png"></a></div>';
				}else{
					$allowed = false;
				}
			}
			echo '<div id="msg2">';
			echo '<div id="commentPosition">Link current position</div>';
			echo $this->Form->create('Comment');
			echo $this->Form->input('tsumego_id', array('type' => 'hidden', 'value' => $t['Tsumego']['id']));
			if(isset($t['Tsumego']['duplicateLink']))
				echo $this->Form->input('set_id', array('type' => 'hidden', 'value' => str_replace('?sid=', '', $t['Tsumego']['duplicateLink'])));
			echo $this->Form->input('user_id', array('type' => 'hidden', 'value' => $user['User']['id']));
			if($allowed)
				echo $this->Form->input('message', array('label' => '', 'type' => 'textarea', 'placeholder' => 'Message'));
			echo $this->Form->input('position', array('label' => '', 'type' => 'hidden'));

			if($_SESSION['loggedInUser']['User']['isAdmin']>=1)
				echo $this->Form->input('status', array('type' => 'hidden', 'value' => 97));

			echo $this->Form->end('Submit');
		?>
		<br>
			<a id="show3">Upload SGF<img id="greyArrow2" src="/img/greyArrow1.png"></a>
			<div id="msg3">
				<br>
				<form action="" method="POST" enctype="multipart/form-data">
					<input type="file" name="game" />
					<input value="Submit" type="submit"/>
				</form>
			</div>
		</div>
		
		<?php }
		if($firstRanks==0){
		?>

		<table class="sandboxTable" width="62%">
		<tr>
		<td>
		
		<?php
		echo '<div id="commentSpace">';
			$showComment2 = array();
			$showComment3 = array();

			for($i=0; $i<count($showComment); $i++){
				if(is_numeric($showComment[$i]['Comment']['status'])) $showComment[$i]['Comment']['textAnswer'] = 'false';
				else{
					$showComment[$i]['Comment']['textAnswer'] = $showComment[$i]['Comment']['status'];
					$showComment[$i]['Comment']['status'] = 100;
				}
				if($showComment[$i]['Comment']['status']!=99) array_push($showComment2, $showComment[$i]);
			}
			$showComment = $showComment2;
			$resolvedCommentCount = 0;

			if(count($showComment)>0) echo '<div id="msg1x"><a id="show2">Comments<img id="greyArrow" src="/img/greyArrow2.png"></a></div><br>';
				echo '<div id="msg2x">';
			for($i=count($showComment)-1; $i>=0; $i--){
					$commentColorCheck = false;
					for($a=0; $a<count($admins); $a++){
						if($showComment[$i]['Comment']['user']==$admins[$a]['User']['name']) $commentColorCheck = true;
					}
					if($commentColorCheck) $commentColor = 'commentBox2';
					else $commentColor = 'commentBox1';
					
					$cpArray = null;
					if($showComment[$i]['Comment']['position']!=null){
						$cpNew = null;
						if(strpos($showComment[$i]['Comment']['position'], '|') !== false){
							$cpNew = explode("|", $showComment[$i]['Comment']['position']);
							$cp = explode('/', $cpNew[0]);
							$cpNew[1] = ',\''.$cpNew[1].'\'';
						}else
							$cp = explode('/', $showComment[$i]['Comment']['position']);
						$cpArray = $cp[0].','.$cp[1].','.$cp[2].','.$cp[3].','.$cp[4].','.$cp[5].','.$cp[6].','.$cp[7].',\''.$cp[8].'\'';
						if($cpNew!=null)
							$cpArray .= $cpNew[1];
						$cpArray = '<img src="/img/positionIcon1.png" class="positionIcon1" onclick="commentPosition('.$cpArray.');">';
					}
					$showComment[$i]['Comment']['message'] = '|'.$showComment[$i]['Comment']['message'];
					if(strpos($showComment[$i]['Comment']['message'], '[current position]')!=0){
						$showComment[$i]['Comment']['message'] = str_replace('[current position]', $cpArray, $showComment[$i]['Comment']['message']);
					}
					$showComment[$i]['Comment']['message'] = substr($showComment[$i]['Comment']['message'], 1);
					
					echo '<div class="sandboxComment">';
					echo '<table class="sandboxTable2" width="100%" border="0"><tr><td>';
					echo '<div class="'.$commentColor.'">'.$showComment[$i]['Comment']['user'].':<br>';
					echo $showComment[$i]['Comment']['message'].' </div>';
					if($showComment[$i]['Comment']['status']!=0 && $showComment[$i]['Comment']['status']!=97 && $showComment[$i]['Comment']['status']!=98 && $showComment[$i]['Comment']['status']!=96){
						echo '<div class="commentAnswer">';
							echo '<div style="padding-top:7px;"></div>'.$showComment[$i]['Comment']['admin'].':<br>';
							if($showComment[$i]['Comment']['status']==1) echo 'Your move(s) have been added.<br>';
							else if($showComment[$i]['Comment']['status']==2) echo 'Your file has been added.<br>';
							else if($showComment[$i]['Comment']['status']==3) echo 'Your solution has been added.<br>';
							else if($showComment[$i]['Comment']['status']==4) echo 'I disagree with your comment.<br>';
							else if($showComment[$i]['Comment']['status']==5) echo 'Provide sequence.<br>';
							else if($showComment[$i]['Comment']['status']==6) echo 'Resolved.<br>';
							else if($showComment[$i]['Comment']['status']==7) echo 'I couldn\'t follow your comment.<br>';
							else if($showComment[$i]['Comment']['status']==8) echo 'You seem to try sending non-SGF-files.<br>';
							else if($showComment[$i]['Comment']['status']==9) echo 'You answer is inferior to the correct solution.<br>';
							else if($showComment[$i]['Comment']['status']==10) echo 'I disagree with your comment. I added sequences.<br>';
							else if($showComment[$i]['Comment']['status']==11) echo 'I don\'t know.<br>';
							else if($showComment[$i]['Comment']['status']==12) echo 'I added sequences.<br>';
							else if($showComment[$i]['Comment']['status']==13) echo 'You are right, but the presented sequence is more interesting.<br>';
							else if($showComment[$i]['Comment']['status']==14) echo 'I didn\'t add your file.<br>';
							else if($showComment[$i]['Comment']['status']==100) echo $showComment[$i]['Comment']['textAnswer'];
							else echo $showComment[$i]['Comment']['status'];
						echo '</div>';
					}
					echo '</td><td align="right" class="sandboxTable2time">';
					echo $showComment[$i]['Comment']['created'];
					if($_SESSION['loggedInUser']['User']['id'] == $showComment[$i]['Comment']['user_id']){
						echo '<a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'"><br>Delete</a>';
					}
					if($_SESSION['loggedInUser']['User']['isAdmin']==1){
						if($showComment[$i]['Comment']['status']==0){
							//echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=2">Can\'t Resolve This</a>';
							echo '<br>';
							if($_SESSION['loggedInUser']['User']['id']!=$showComment[$i]['Comment']['user_id'] && $commentColorCheck){
								echo '<a class="deleteComment" style="text-decoration:none;" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=3">
									<img class="thumbs-small" title="approve this comment" width="20px" src="/img/thumbs-small.png">
								</a>';
							}
							echo '&nbsp;<a id="adminComment'.$i.'" class="adminComment" href="">Answer</a>';

						}else{
							echo '<a id="adminComment'.$i.'" class="adminComment" href=""><br>Edit</a>';
						}
					}
					echo '</td></tr></table>';

					echo '<div id="adminCommentPanel'.$i.'" class="adminCommentPanel">
					<table class="sandboxTable2" width="100%" border="0">
					<tr>
					<td width="50%" style="vertical-align:top">';
						echo $this->Form->create('Comment');
						echo $this->Form->input('id', array('type' => 'hidden', 'value' => $showComment[$i]['Comment']['id']));
						echo $this->Form->input('admin_id', array('type' => 'hidden', 'value' => $_SESSION['loggedInUser']['User']['id']));
						echo $this->Form->input('status', array('id' => 'CommentStatus'.$i, 'label' => '', 'type' => 'textarea', 'placeholder' => 'Message'));
						echo $this->Form->end('Submit');
					echo '</td>
					<td width="50%" style="vertical-align:top">';

						echo '<select id="myselect'.$i.'">
							<option value="0"></option>
							<option value="1">Your moves have been added.</option>
							<option value="2">Your move has been added.</option>
							<option value="3">Your file has been added.</option>
							<option value="4">I disagree with your comment.</option>
							<option value="6">Provide sequence.</option>
							<option value="6">Resolved.</option>
							<option value="7">I couldn\'t follow your comment.</option>
							<option value="8">You seem to try sending non-SGF-files.</option>
							<option value="9">You answer is inferior to the correct solution.</option>
							<option value="10">I disagree with your comment. I added sequences.</option>
							<option value="11">I don\'t know.</option>
							<option value="12">I added sequences.</option>
							<option value="13">You are right, but the presented sequence is more interesting.</option>
							<option value="14">I didn\'t add your file.</option>
						</select>';
						echo '<div align="right">';
						echo '<br><br>';
						if($showComment[$i]['Comment']['status'] != 0){
							echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=4">Mask as unresolved</a>';
						}else{
							echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=1">Mark as resolved</a>';
						}
						echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'">Delete</a>';
						echo '</div>';
					echo '</td>
					</tr>
					</table>
					</div>
					';
					echo '</div>';
					echo '<div class="sandboxCommentSpace"></div>';
			}

		?>
		</div>

		</td>
		</tr>
		</table>

	<?php
	}
	}else{
		?>
		<br>
		</div><br>
		<div align="center">
		<table class="sandboxTable" width="62%">
		<tr>
		<td>
		<?php
		echo '<div id="commentSpace">';

			$showComment2 = array();
			$showComment3 = array();

			for($i=0; $i<count($showComment); $i++){
				if(is_numeric($showComment[$i]['Comment']['status'])) $showComment[$i]['Comment']['textAnswer'] = 'false';
				else{
					$showComment[$i]['Comment']['textAnswer'] = $showComment[$i]['Comment']['status'];
					$showComment[$i]['Comment']['status'] = 100;
				}
				if($showComment[$i]['Comment']['status']!=99) array_push($showComment2, $showComment[$i]);
			}
			$showComment = $showComment2;
			$resolvedCommentCount = 0;

			if(count($showComment)>0) echo '<div id="msg1x"><a id="show2">Comments<img id="greyArrow" src="/img/greyArrow2.png"></a></div><br>';
				echo '<div id="msg2x">';
			for($i=count($showComment)-1; $i>=0; $i--){
					echo '<div class="sandboxComment">';
					echo '<table class="sandboxTable2" width="100%" border="0"><tr><td>';
					echo $showComment[$i]['Comment']['user'].':<br>';
					echo $showComment[$i]['Comment']['message'].'<br>';
					if($showComment[$i]['Comment']['status']!=0 && $showComment[$i]['Comment']['status']!=97 && $showComment[$i]['Comment']['status']!=98  && $showComment[$i]['Comment']['status']!=96){
						echo '<div class="commentAnswer">';
							echo '<div style="padding-top:7px;"></div>'.$showComment[$i]['Comment']['admin'].':<br>';
							if($showComment[$i]['Comment']['status']==1) echo 'Your move(s) have been added.<br>';
							else if($showComment[$i]['Comment']['status']==2) echo 'Your file has been added.<br>';
							else if($showComment[$i]['Comment']['status']==3) echo 'Your solution has been added.<br>';
							else if($showComment[$i]['Comment']['status']==4) echo 'I disagree with your comment.<br>';
							else if($showComment[$i]['Comment']['status']==5) echo 'Provide sequence.<br>';
							else if($showComment[$i]['Comment']['status']==6) echo 'Resolved.<br>';
							else if($showComment[$i]['Comment']['status']==7) echo 'I couldn\'t follow your comment.<br>';
							else if($showComment[$i]['Comment']['status']==8) echo 'You seem to try sending non-SGF-files.<br>';
							else if($showComment[$i]['Comment']['status']==9) echo 'You answer is inferior to the correct solution.<br>';
							else if($showComment[$i]['Comment']['status']==10) echo 'I disagree with your comment. I added sequences.<br>';
							else if($showComment[$i]['Comment']['status']==11) echo 'I don\'t know.<br>';
							else if($showComment[$i]['Comment']['status']==12) echo 'I added sequences.<br>';
							else if($showComment[$i]['Comment']['status']==13) echo 'You are right, but the presented sequence is more interesting.<br>';
							else if($showComment[$i]['Comment']['status']==14) echo 'I didn\'t add your file.<br>';
							else if($showComment[$i]['Comment']['status']==100) echo $showComment[$i]['Comment']['textAnswer'];
							else echo $showComment[$i]['Comment']['status'];
						echo '</div>';
					}
					echo '</td><td align="right" class="sandboxTable2time">';
					echo $showComment[$i]['Comment']['created'];
					if($_SESSION['loggedInUser']['User']['id'] == $showComment[$i]['Comment']['user_id']){
						echo '<a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'"><br>Delete</a>';
					}
					if($_SESSION['loggedInUser']['User']['isAdmin']==1){
						if($showComment[$i]['Comment']['status']==0) echo '<a id="adminComment'.$i.'" class="adminComment" href=""><br>Answer</a>';
						else echo '<a id="adminComment'.$i.'" class="adminComment" href=""><br>Edit</a>';
					}
					echo '</td></tr></table>';

					echo '<div id="adminCommentPanel'.$i.'" class="adminCommentPanel">
					<table class="sandboxTable2" width="100%" border="0">
					<tr>
					<td width="50%" style="vertical-align:top">';
						echo $this->Form->create('Comment');
						echo $this->Form->input('id', array('type' => 'hidden', 'value' => $showComment[$i]['Comment']['id']));
						echo $this->Form->input('admin_id', array('type' => 'hidden', 'value' => $_SESSION['loggedInUser']['User']['id']));
						echo $this->Form->input('status', array('id' => 'CommentStatus'.$i, 'label' => '', 'type' => 'textarea', 'placeholder' => 'Message'));
						echo $this->Form->end('Submit');
					echo '</td>
					<td width="50%" style="vertical-align:top">';

						echo '<select id="myselect'.$i.'">
							<option value="0"></option>
							<option value="1">Your moves have been added.</option>
							<option value="2">Your move has been added.</option>
							<option value="3">Your file has been added.</option>
							<option value="4">I disagree with your comment.</option>
							<option value="6">Provide sequence.</option>
							<option value="6">Resolved.</option>
							<option value="7">I couldn\'t follow your comment.</option>
							<option value="8">You seem to try sending non-SGF-files.</option>
							<option value="9">You answer is inferior to the correct solution.</option>
							<option value="10">I disagree with your comment. I added sequences.</option>
							<option value="11">I don\'t know.</option>
							<option value="12">I added sequences.</option>
							<option value="13">You are right, but the presented sequence is more interesting.</option>
							<option value="14">I didn\'t add your file.</option>
						</select>';
						echo '<div align="right">';
						echo '<br><br><br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=1">No Answer Necessary</a>';
						echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=2">Can\'t Resolve This</a>';
						echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'">Delete</a>';
						echo '</div>';
					echo '</td>
					</tr>
					</table>
					</div>
					';
					echo '</div>';
					echo '<div class="sandboxCommentSpace"></div>';
			}

		?>
		</div>

		</td>
		</tr>
		</table>
		</div>
	<?php
	}
	?>
	<?php if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210 || $tv!=null&&$tv['TsumegoVariant']['type']=='multiple_choice'){ ?>
		<label>
		<input type="checkbox" class="alertCheckbox1" id="alertCheckbox" autocomplete="off" />
		<div class="alertBox alertInfo" id="multipleChoiceAlerts">
		<div class="alertBanner">
		Infomation
		<span class="alertClose">x</span>
		</div>
		<span class="alertText2">
		<div id="multipleChoiceText"></div>
		<div class="clear1"></div>
		</span>
		</div>
		</label>
	<?php }else{ ?>
		<?php if($potionAlert){ ?>
		<label>
		  <input type="checkbox" class="alertCheckbox1" id="potionAlertCheckbox" autocomplete="off" />
		  <div class="alertBox alertInfo" id="potionAlerts">
			<div class="alertBanner" align="center">
			Hero Power
			<span class="alertClose">x</span>
			</div>
			<span class="alertText">
			<?php
			echo '<img id="hpIcon1" src="/img/hp5.png">
			You found a potion, your hearts have been restored.<br>'
			?>
			<br class="clear1"/></span>
		  </div>
		</label>
	<?php }else{ ?>
		<label>
		<input type="checkbox" class="alertCheckbox1" id="customAlertCheckbox" autocomplete="off" />
		<div class="alertBox alertInfo" id="customAlerts">
		<div class="alertBanner">
		Message
		<span class="alertClose">x</span>
		</div>
		<span class="alertText3">
		<div id="customText"></div>
		<div class="clear1"></div>
		</span>
		</div>
		</label>
	<?php } ?>
	<?php } ?>
	
	<?php echo '</div>'; ?>
	<div class="loader-container">
		<div class="loader-content">
			<div class="loader-text">
				searching...
			</div>
		</div>
	</div>
	<?php
	if(count($showComment)>0){
		echo '<div align="center">';
		echo '<font color="grey"></font>';
		echo '</div>';
	}
	$browser = $_SERVER['HTTP_USER_AGENT'] . "\n\n";
	echo '<audio><source src="/sounds/newStone.ogg"></audio>';
	echo '';
	
	/* 
	§TESTING AREA§
	echo '<pre>'; print_r($levelBar); echo '</pre>';
	*/
	?>

<script type="text/javascript">
	var jrecordValue = 19;
	<?php if($checkBSize!=19) echo 'jrecordValue = '.$checkBSize.';'; ?>
	var jrecord = new JGO.Record(jrecordValue, jrecordValue);
	var jboard = jrecord.jboard;
	var jsetup = new JGO.Setup(jboard, JGO.BOARD.<?php echo $boardSize; ?>Walnut);
	var player = <?php echo 'JGO.'.$playerColor[0]; ?>;
	var ko = false, lastMove = false;
	var lastHover = false, lastX = -1, lastY = -1;
	var moveCounter = 0;
	var move = 0;
	var branch = "";
	var misplays = 0;
	var hoverLocked = false;
	var tryAgainTomorrow = false;
	var doubleXP = false;
	var countDownDate = new Date();
	var sprintEnabled = true;
	var intuitionEnabled = true;
	var rejuvenationEnabled = true;
	var refinementEnabled = true;
	var revelationEnabled = false;
	var multipleChoiceSelected = false;
	var safetyLock = false;
	var msg2selected = false;
	var msg2xselected = false;
	var msg3selected = false;
	var msg4selected = false;
	var msg5selected = false;
	var msgMCselected = false;
	var msgSEselected = false;
	var playedWrong = false;
	var seconds = 0;
	var difficulty = <?php echo $difficulty; ?>;
	var sequence = "|";
	var freePlayMode = false;
	var freePlayMode2 = false;
	var freePlayMode2done = false;
	var rw = false;
	var rwLevel = 0;
	var rwBranch = 0;
	var masterArrayPreI = 0;
	var masterArrayPreJ = 0;
	var rwSwitcher = 2;
	var inPath = false;
	var pathLock = false;
	var reviewEnabled = false;
	var set159 = false;
	var theComment = "";
	var moveHasComment = false;
	var isIncorrect = false;
	var josekiHero = false;
	var josekiLevel = 1;
	var thumbsUpSelected = false;
	var thumbsDownSelected = false;
	var thumbsUpSelected2 = false;
	var thumbsDownSelected2 = false;
	var mode = 1;
	var timeModeEnabled = true;
	var timeUp = false;
	var moveTimeout = 360;
	var authorProblem = false;
	var tcount = 0.0;
	var isCorrect = false;
	var whiteMoveAfterCorrect = false;
	var whiteMoveAfterCorrectI = 0;
	var whiteMoveAfterCorrectJ = 0;
	var reviewModeActive = false;
	var ui = <?php echo $ui; ?>;
	var userXP = <?php echo $user['User']['xp']; ?>;
	var prevButtonLink = "<?php echo $prev; ?>";
	var nextButtonLink = "<?php echo $next; ?>";
	var lastInFav = "<?php echo $lastInFav; ?>";
	var nextButtonLinkSet = <?php echo $t['Tsumego']['set_id']; ?>;
	var nextButtonLinkLv = <?php echo $lv; ?>;
	var isMutable = true;
	var deleteNextMoveGroup = false;
	var file = "<?php echo $file; ?>";
	var clearFile = "<?php echo $set['Set']['title'].' - '.$t['Tsumego']['num']; ?>";
	var clearFile2 = "<?php echo $set['Set']['folder'].'/'.$t['Tsumego']['num']; ?>";
	var tsumegoFileLink = "<?php echo $t['Tsumego']['id']; ?>";
	var requestSignature = "<?php echo $requestSignature; ?>";
	var idForSignature = "<?php echo $idForSignature; ?>";
	var idForSignature2 = "<?php echo $idForSignature2; ?>";
	var author = "<?php echo $t['Tsumego']['author']; ?>";
	var inFavorite = "<?php echo $inFavorite; ?>";
	var besogoPlayerColor = "black";
	var favorite = "<?php echo $isTSUMEGOinFAVORITE; ?>";
	var besogoMode2Solved = false;
	var disableAutoplay = false;
	var besogoNoLogin = false;
	var soundParameterForCorrect = false;
	let multipleChoiceLibertiesB = 0;
	let multipleChoiceLibertiesW = 0;
	let multipleChoiceVariance = <?php echo $t['Tsumego']['variance']; ?>+"";
	let multipleChoiceLibertyCount = <?php echo $t['Tsumego']['libertyCount']; ?>+"";
	let multipleChoiceSemeaiType = <?php echo $t['Tsumego']['semeaiType']; ?>+"";
	let multipleChoiceInsideLiberties = <?php echo $t['Tsumego']['insideLiberties']; ?>+"";
	let multipleChoiceEnabled = false;
	let hasChosen = false;
	let enableDownloads = false;
	let cvn = true;
	let adminCommentOpened = false;
	let customMultipleChoiceAnswer = 0;
	let boardLockValue = 0;
	let mText = "";
	let ratingBarLock = false;
	let passEnabled = <?php echo $t['Tsumego']['pass']; ?>+"";
	let besogoRotation = -1;
	let msgFilterSelected = false;
	let revelationCounter = <?php echo $user['User']['revelation']; ?>+"";
	let hasPremium = "<?php echo $hasPremium; ?>";
	const activeTopicTiles = [];
	const activeDifficultyTiles = [];
	const activeTagTiles = [];
	<?php
		if($hasRevelation)
			echo 'let hasRevelation = true;';
		else
			echo 'let hasRevelation = false;';

		$hasPartition = '';
		if($partition>0)
			$hasPartition = '?partition='.$partition;
		if($query=='difficulty' || $query=='tags'){
			$nextButtonLinkSet = $_SESSION['lastSet'].$hasPartition;
		}else if($query=='topics'){
			$nextButtonLinkSet = $set['Set']['id'].$hasPartition;
		}
		echo 'nextButtonLinkSet="'.$nextButtonLinkSet.'";';
		?>
		if(inFavorite!==''){
			prevButtonLink += inFavorite;
			nextButtonLink += inFavorite;
		}
		<?php
		if($favorite){
			echo 'if(lastInFav==="1"){
				nextButtonLink = 0;
				nextButtonLinkSet = 1;
			}
			if(lastInFav==="-1"){
				prevButtonLink = 0;
				nextButtonLinkSet = 1;
			}';
		}
	?>

	$("#showFilters").click(function(){
		if(!msgFilterSelected){
			$("#msgFilters").fadeIn(250);
			document.getElementById("greyArrowFilter").src = "/img/greyArrow2.png";
		}else{
			$("#msgFilters").fadeOut(250);
			document.getElementById("greyArrowFilter").src = "/img/greyArrow1.png";
		}
		msgFilterSelected = !msgFilterSelected;
	});

	$("#msgFilters").css("display", "none");

	<?php 
		if($query!='topics')
			for($i=0; $i<count($search1); $i++)
				echo 'activeTopicTiles.push("'.$search1[$i].'");';
		if($query!='difficulty')
			for($i=0; $i<count($search2); $i++)
				echo 'activeDifficultyTiles.push("'.$search2[$i].'");';
		if($query!='tags')
			for($i=0; $i<count($search3); $i++)
				echo 'activeTagTiles.push("'.$search3[$i].'");';
	?>
	drawActiveTiles();

	function drawActiveTiles(){
		$(".active-tiles-container").html("");
		for(let i=0;i<activeTopicTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown-tile tile-color1" id="active-tiles-element'+i+'" onclick="removeActiveTopic('+i+')" style="cursor:context-menu">'+activeTopicTiles[i]+'</div>');
		for(let i=0;i<activeDifficultyTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown-tile tile-color2" id="active-tiles-element'+i+'" onclick="removeActiveDifficulty('+i+')" style="cursor:context-menu">'+activeDifficultyTiles[i]+'</div>');
		for(let i=0;i<activeTagTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown-tile tile-color3" id="active-tiles-element'+i+'" onclick="removeActiveTag('+i+')" style="cursor:context-menu">'+activeTagTiles[i]+'</div>');
		if(activeTopicTiles.length>0 || activeDifficultyTiles.length>0 || activeTagTiles.length>0)
			$(".active-tiles-container").append('<a class="dropdown-tile tile-color4" id="unselect-active-tiles" href="">clear</a><div style="clear:both"</div>');
	}

	$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e){
		e.preventDefault();
		$(".active-tiles-container").html("");
		setCookie("search1", "@");
		setCookie("search2", "@");
		setCookie("search3", "@");
		window.location.href = "/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>";
	});

	<?php 
	if($tv!=null){
	if($tv['TsumegoVariant']['type']=='multiple_choice' && $tv['TsumegoVariant']['explanation']!=""){
		echo 'mText = "'.$tv['TsumegoVariant']['explanation'].'";';
	}}

	if(isset($_SESSION['loggedInUser']['User']['id'])){
		if($_SESSION['loggedInUser']['User']['isAdmin']>0){
			echo '$(".modify-description-panel").hide();';
		}
		echo 'var besogoUserId = '.$_SESSION['loggedInUser']['User']['id'].';';
	}else{
		echo 'besogoNoLogin = true;';
	}
	
	if($pl==1) echo 'besogoPlayerColor = "white";';
	if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210 || $t['Tsumego']['set_id']==109
	|| $t['Tsumego']['set_id']==233 || $t['Tsumego']['set_id']==236) 
	echo 'besogoPlayerColor = "black";';
	
	if($authorx==$_SESSION['loggedInUser']['User']['name']) echo 'authorProblem = true;';
	if($_SESSION['loggedInUser']['User']['id']==72){
		echo 'authorProblem = true;';
		echo 'revelationEnabled = true;';
	} 
	if($requestSolution)
		echo 'authorProblem = true;';
	if($firstRanks!=0) echo 'document.cookie = "mode=3;path=/tsumegos/play;SameSite=none;Secure=false";';
	if($mode==3){
		echo 'seconds = 0.0;';
		echo 'var besogoMode3Next = '.$next.';';
	}else if($mode==2)
		echo 'document.cookie = "ratingModePreId='.$t['Tsumego']['id'].';path=/tsumegos/play;SameSite=none;Secure=false";';
	echo '
	';
	if($t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161)
		echo 'set159 = true;';
	if($t['Tsumego']['set_id']==161)
		echo 'josekiHero = true;';
	echo 'josekiLevel = '.$josekiLevel.';';
	?>

	var eloScore = <?php echo $eloScore; ?>;
	var eloScore2 = <?php echo $eloScore2; ?>;
	
	<?php
		if($corner=='t' || $corner=='b' || $corner=='full board'){
			echo '$("#plus2").css("left", "340px");';
		}
	?>

	<?php if($mode!=3){ ?>
		function incrementSeconds(){
			seconds += 1;
		}
		var secondsx = setInterval(incrementSeconds, 1000);
	<?php }else{ ?>
		function incrementSeconds(){
			seconds += 0.1;
		}
		var secondsx = setInterval(incrementSeconds, 100);
	<?php } ?>
	$(".adminCommentPanel").hide();
	$(".tsumegoNavi-middle2").hide();
	$(".tsumegoNavi-middle2").hide();
	$(".reviewNavi").hide();
	$("#reviewButton").hide();
	$("#reviewButton2").hide();
	<?php if(($t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161) && $isSemeai){
		echo 'document.getElementById("multipleChoice1").innerHTML ="'.$partArray[0].'";';
		echo 'document.getElementById("multipleChoice2").innerHTML ="'.$partArray[1].'";';
		echo 'document.getElementById("multipleChoice3").innerHTML ="'.$partArray[2].'";';
		echo 'document.getElementById("multipleChoice4").innerHTML ="'.$partArray[3].'";';
	} ?>

	<?php if($mode==2){ ?>
	var rangeInput = document.getElementById("rangeInput");
	const Slider = document.querySelector('input[name=rangeInput]');

	if(difficulty==1){
		$('#sliderText').css({"color":"hsl(138, 47%, 50%)"});
		$('#sliderText').text("very easy");
		Slider.style.setProperty('--SliderColor', 'hsl(138, 47%, 50%)');
	}else if(difficulty==2){
		$('#sliderText').css({"color":"hsl(138, 31%, 50%)"});
		$('#sliderText').text("easy");
		Slider.style.setProperty('--SliderColor', 'hsl(138, 31%, 50%)');
	}else if(difficulty==3){
		$('#sliderText').css({"color":"hsl(138, 15%, 50%)"});
			$('#sliderText').text("casual");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 15%, 50%)');
	}else if(difficulty==4){
		$('#sliderText').css({"color":"hsl(138, 0%, 47%)"});
			$('#sliderText').text("regular");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 0%, 60%)');
	}else if(difficulty==5){
		$('#sliderText').css({"color":"hsl(0, 31%, 50%)"});
			$('#sliderText').text("challenging");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 31%, 50%)');
	}else if(difficulty==6){
		$('#sliderText').css({"color":"hsl(0, 52%, 50%)"});
			$('#sliderText').text("difficult");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 47%, 50%)');
	}else if(difficulty==7){
		$('#sliderText').css({"color":"hsl(0, 66%, 50%)"});
			$('#sliderText').text("very difficult");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 63%, 50%)');
	}else{
		$('#sliderText').css({"color":"hsl(138, 0%, 47%)"});
			$('#sliderText').text("regular");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 0%, 60%)');
	}

	rangeInput.addEventListener('change', function(){
		const Slider = document.querySelector('input[name=rangeInput]');
		document.cookie = "difficulty="+this.value;
		if(this.value==1){
			$('#sliderText').css({"color":"hsl(138, 47%, 50%)"});
			$('#sliderText').text("very easy");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 47%, 50%)');
		}else if(this.value==2){
			$('#sliderText').css({"color":"hsl(138, 31%, 50%)"});
			$('#sliderText').text("easy");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 31%, 50%)');
		}else if(this.value==3){
			$('#sliderText').css({"color":"hsl(138, 15%, 50%)"});
			$('#sliderText').text("casual");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 15%, 50%)');
		}else if(this.value==4){
			$('#sliderText').css({"color":"hsl(138, 0%, 47%)"});
			$('#sliderText').text("regular");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 0%, 60%)');
		}else if(this.value==5){
			$('#sliderText').css({"color":"hsl(0, 34%, 50%)"});
			$('#sliderText').text("challenging");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 34%, 50%)');
		}else if(this.value==6){
			$('#sliderText').css({"color":"hsl(0, 52%, 50%)"});
			$('#sliderText').text("difficult");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 47%, 50%)');
		}else if(this.value==7){
			$('#sliderText').css({"color":"hsl(0, 66%, 50%)"});
			$('#sliderText').text("very difficult");
			Slider.style.setProperty('--SliderColor', 'hsl(0, 63%, 50%)');
		}else{
			$('#sliderText').css({"color":"#616161"});
			$('#sliderText').text("regular");
			Slider.style.setProperty('--SliderColor', 'hsl(138, 0%, 60%)');
		}
	});
	<?php } ?>
	$(".modify-description").click(function(e){
		adminCommentOpened = true;
		e.preventDefault();
		$(".modify-description-panel").toggle(250);
	});

	<?php if($tv!=null&&$tv['TsumegoVariant']['type']=='score_estimating'){ ?>
		$("#submitScoreEstimatingSE").click(function(e){
			e.preventDefault();
			if(!hasChosen){
				let correctNum = "<?php echo $tv['TsumegoVariant']['winner']; ?>";
				let guess = $("#ScoreEstimatingSE").val();
				if(parseFloat(correctNum.slice(2))===0 || parseFloat(correctNum)===0)
					correctNum = 0;
				if(parseFloat(guess.slice(2))===0 || parseFloat(guess)===0)
					guess = 0;
				let color = "#e0747f";
				if(guess==correctNum){
					displayResult("S");
					color = "#3ecf78";
				}else{
					displayResult("F");
					color = "#e0747f";
				}
				hasChosen = true;
				locked = true;
				$("#ScoreEstimatingSE").prop("disabled", true);
				$("#besogo-se-black").css("background-color", color);
				$("#besogo-se-white").css("background-color", color);
				$("#besogo-se-more").css("background-color", color);
				$("#besogo-se-less").css("background-color", color);
				$("#submitScoreEstimatingSE").css("background-color", color);
			}
		});
		$("#submitScoreEstimatingBlackWins").click(function(e){
			e.preventDefault();
			let scoreResult = "<?php echo $tv['TsumegoVariant']['winner']; ?>";
			if(!hasChosen){
				let res1;
				if(scoreResult.charAt(scoreResult.length - 1) === '0')
					res1 = "Result: Jigo";
				else
					res1 = "Result: "+scoreResult;
				$("#se-result-text").css("opacity", "1");
				$("#se-result-text").text(res1);
				let color = "#e0747f";
				let color2 = "rgb(224, 60, 75)";
				if (scoreResult.charAt(0) === 'B' && scoreResult.charAt(scoreResult.length - 1) !== '0') {
					displayResult("S");
					color = "#3ecf78";
					color2 = "rgb(12, 187, 12)";
				}else{
					displayResult("F");
					color = "#e0747f";
					color2 = "rgb(224, 60, 75)";
				}
				hasChosen = true;
				locked = true;
				$("#submitScoreEstimatingBlackWins").css("background-color", color);
				$("#submitScoreEstimatingWhiteWins").css("background-color", color);
				$("#submitScoreEstimatingJigo").css("background-color", color);
				$("#se-result-text").css("color", color2);
			}
		});
		$("#submitScoreEstimatingWhiteWins").click(function(e){
			e.preventDefault();
			let scoreResult = "<?php echo $tv['TsumegoVariant']['winner']; ?>";
			if(!hasChosen){
				let res1;
				if(scoreResult.charAt(scoreResult.length - 1) === '0')
					res1 = "Result: Jigo";
				else
					res1 = "Result: "+scoreResult;
				$("#se-result-text").css("opacity", "1");
				$("#se-result-text").text(res1);
				let color = "#e0747f";
				let color2 = "rgb(224, 60, 75)";
				if (scoreResult.charAt(0) === 'W' && scoreResult.charAt(scoreResult.length - 1) !== '0') {
					displayResult("S");
					color = "#3ecf78";
					color2 = "rgb(12, 187, 12)";
				}else{
					displayResult("F");
					color = "#e0747f";
					color2 = "rgb(224, 60, 75)";
				}
				hasChosen = true;
				locked = true;
				$("#submitScoreEstimatingBlackWins").css("background-color", color);
				$("#submitScoreEstimatingWhiteWins").css("background-color", color);
				$("#submitScoreEstimatingJigo").css("background-color", color);
				$("#se-result-text").css("color", color2);
			}
		});
		$("#submitScoreEstimatingJigo").click(function(e){
			e.preventDefault();
			let scoreResult = "<?php echo $tv['TsumegoVariant']['winner']; ?>";
			if(!hasChosen){
				let res1;
				if(scoreResult.charAt(scoreResult.length - 1) === '0')
					res1 = "Result: Jigo";
				else
					res1 = "Result: "+scoreResult;
				$("#se-result-text").css("opacity", "1");
				$("#se-result-text").text(res1);
				let color = "#e0747f";
				let color2 = "rgb(224, 60, 75)";
				if (scoreResult.charAt(scoreResult.length - 1) === '0') {
					displayResult("S");
					color = "#3ecf78";
					color2 = "rgb(12, 187, 12)";
				}else{
					displayResult("F");
					color = "#e0747f";
					color2 = "rgb(224, 60, 75)";
				}
				hasChosen = true;
				locked = true;
				$("#submitScoreEstimatingBlackWins").css("background-color", color);
				$("#submitScoreEstimatingWhiteWins").css("background-color", color);
				$("#submitScoreEstimatingJigo").css("background-color", color);
				$("#se-result-text").css("color", color2);
			}
		});
		$("#besogo-se-edit-black").click(function(e){
			e.preventDefault();
			let chars = "";
			let num = 0;
			let v = "";
			v = $("#scoreEstEditField").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = "B+";
				num = v.slice(2);
			}else{
				chars = "B+";
				num = v;
			}
			$("#scoreEstEditField").val(chars+num);
		});
		$("#besogo-se-edit-white").click(function(e){
			e.preventDefault();
			let chars = "";
			let num = 0;
			let v = "";
			v = $("#scoreEstEditField").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = "W+";
				num = v.slice(2);
			}else{
				chars = "W+";
				num = v;
			}
			$("#scoreEstEditField").val(chars+num);
		});
		$("#besogo-se-edit-more").click(function(e){
			e.preventDefault();
			let chars = "";
			let num = 0;
			let v = "";
			v = $("#scoreEstEditField").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = v.slice(0,2);
				num = v.slice(2);
			}else{
				chars = "";
				num = v;
			}
			if(num=="")
				num=0;
			if(is_numeric(num)){
				num = parseFloat(num);
				num += .5;
			}
			$("#scoreEstEditField").val(chars+num);
		});
		$("#besogo-se-edit-less").click(function(e){
			e.preventDefault();
			let chars = "";
			let num = 0;
			let v = "";
			v = $("#scoreEstEditField").val();
			if(v.slice(0,2)=="B+" || v.slice(0,2)=="W+"){
				chars = v.slice(0,2);
				num = v.slice(2);
			}else{
				chars = "";
				num = v;
			}
			if(num=="")
				num=0;
			if(is_numeric(num)){
				num = parseFloat(num);
				if(num>0)
					num -= .5;
			}
			$("#scoreEstEditField").val(chars+num);
		});
	<?php } ?>
	<?php
	if($potionSuccess){
		echo 'document.cookie = "rejuvenationx=2;path=/tsumegos/play;SameSite=none;Secure=false";';
		echo 'window.location = "/tsumegos/play/'.$t['Tsumego']['id'].'?potionAlert=1";';
	}

	if($mode==1) echo 'mode = 1;';
	if($mode==2) echo 'mode = 2;';
	if($mode==3) echo 'mode = 3;';
	if($mode==1){
	}elseif($mode==2){
		echo '
			$(".tsumegoNavi1").hide();
			$(".tsumegoNavi-middle").hide();
			$(".tsumegoNavi-middle2").show();
			$(".mode1").css({"padding-top":"8px"});
			$(".selectable-text").hide();
			$("#commentSpace").hide();
			$("#msg1").hide();
			$("#reviewButton").hide();
			$("#reviewButton2").hide();
		';
	}elseif($mode==3){
		echo '$("#account-bar-user > a").css({color:"#ca6658"});';
	}

	for($i=count($showComment)-1; $i>=0; $i--){
		echo '
		$("#myselect'.$i.'").change(function(){
			$("#CommentStatus'.$i.'").val($("#myselect'.$i.' option:selected").text());
		});

		$("#adminComment'.$i.'").click(function(e){
			e.preventDefault();
			$("#adminCommentPanel'.$i.'").toggle(250);
			adminCommentOpened = true;
		});
		';
	}

	if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2' || $t['Tsumego']['status']=='setW2'){
		echo 'var showCommentSpace = true;';
	}else echo 'var showCommentSpace = false;';
	if($_SESSION['loggedInUser']['User']['isAdmin']>0){
		echo 'var showCommentSpace = true;';
		echo '$("#show5").css("display", "inline-block");';
	}	
	if($set['Set']['public']==0) echo 'var showCommentSpace = true;';
	if($goldenTsumego) echo 'var goldenTsumego = true;';
	else echo 'var goldenTsumego = false;';

	$sandboxCheck = 'document.getElementById("currentElement").style.backgroundColor = "'.$playGreenColor.'";';

	echo 'var sprintLockedInSecretArea = false;';

	if($firstPlayer=='w') echo 'player = JGO.'.$playerColor[1].';';

	if($t['Tsumego']['status'] == 'setF2' || $t['Tsumego']['status'] == 'setX2'){
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'toggleBoardLock(true);';
	}else echo 'var locked=false;';

	if($dailyMaximum){
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'document.getElementById("status").innerHTML = "<h3><b>You reached the daily maximum for non-premium users.</b></h3>";
			document.getElementById("status").style.color = "#000";
			document.getElementById("xpDisplay").innerHTML = "&nbsp;";';
	}
	if($suspiciousBehavior){
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'document.getElementById("status").innerHTML = "<h3><b>Your account is temporarily locked.</b></h3>";
			document.getElementById("status").style.color = "red";
			document.getElementById("xpDisplay").innerHTML = "&nbsp;";
			toggleBoardLock(true);';
	}

	if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2' || $maxNoUserLevel){
		echo 'var noXP=true;';
	}else{
		echo 'var noXP=false;';
	}

	if($t['Tsumego']['set_id']==156 || $t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161){
		if($additionalInfo['lastPlayed'][0]!=99) echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['lastPlayed'][0].', '.$additionalInfo['lastPlayed'][1].'), JGO.MARK.CIRCLE);';
		for($i=0; $i<count($additionalInfo['triangle']); $i++){
			echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['triangle'][$i][0].', '.$additionalInfo['triangle'][$i][1].'), JGO.MARK.TRIANGLE);';
		}
		for($i=0; $i<count($additionalInfo['square']); $i++){
			echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['square'][$i][0].', '.$additionalInfo['square'][$i][1].'), JGO.MARK.SQUARE);';
		}
	}

	//BOARD ORIENTATION
	      if($corner=='tl'){echo 'jsetup.view(0, 0, 9, 9);';
	}else if($corner=='br'){echo 'jsetup.view(10, 10, 9, 9);';
	}else if($corner=='bl'){echo 'jsetup.view(0, 10, 9, 9);';
	}else if($corner=='tr'){echo 'jsetup.view(10, 0, 9, 9);';
	}else if($corner=='b'){echo 'jsetup.view(0, 10, 19, 9);';
	}else if($corner=='t'){echo 'jsetup.view(0, 0, 19, 9);';
	}else{} // Full Board
?>

	$(document).ready(function(){
		<?php
		if($t['Tsumego']['set_id']==210){ 
			echo '$("#author-notice").hide();';
		}
			if($ui==1) echo 'document.cookie = "ui=1;path=/tsumegos/play;SameSite=none;Secure=false";';
			elseif($ui==2) echo 'document.cookie = "ui=2;path=/tsumegos/play;SameSite=none;Secure=false";';

			if($mode==1) echo 'document.cookie = "mode=1;path=/tsumegos/play;SameSite=none;Secure=false";';
			if($mode==2) echo 'document.cookie = "mode=2;path=/tsumegos/play;SameSite=none;Secure=false";';
			if($mode==3) echo 'document.cookie = "mode=3;path=/tsumegos/play;SameSite=none;Secure=false";';

			if($mode==3){
				echo 'notMode3 = false;';
				echo '$("#account-bar-xp").text("'.$raName.'");';
				?>
				<?php $barPercent = ($crs/$stopParameter)*100; ?>
				$("#xp-increase-fx").css("display","inline-block");
				$("#xp-bar-fill").css("box-shadow", "-5px 0px 10px #fff inset");
				<?php echo '$("#xp-bar-fill").css("width","'.$barPercent.'%");'; ?>
				$("#xp-increase-fx").fadeOut(0);
				$("#xp-bar-fill").css({"-webkit-transition":"all 0.0s ease","box-shadow":""});
				<?php
			}

			if($refresh=='1') echo 'window.location = "/";';
			if($refresh=='2') echo 'window.location = "/sets";';
			if($refresh=='3') echo 'window.location = "/sets/view/'.$t['Tsumego']['set_id'].'";';
			if($refresh=='4') echo 'window.location = "/users/highscore";';
			if($refresh=='5') echo 'window.location = "/comments";';
			if($refresh=='6') echo 'window.location = "/sets/beta";';
			if($refresh=='7') echo 'window.location = "/users/leaderboard";';
			if($refresh=='8') echo 'window.location = "/tsumegos/play/'.$t['Tsumego']['id'].'";';
		?>
		<?php
		if($t['Tsumego']['status'] == 'setF2' || $t['Tsumego']['status'] == 'setX2'){
			echo '
				document.getElementById("status").innerHTML = \'<b style="font-size:17px">Try again tomorrow or <a style="color:#e03c4b" target="_blank" href="/users/donate">upgrade</a></b>\';
				tryAgainTomorrow = true;
				document.getElementById("status").style.color = "#e03c4b";
				document.getElementById("xpDisplay").innerHTML = "&nbsp;";
			';
		}
		if($potionAlert){
			echo '$(".alertBox").fadeIn(500);';
		}
		if($isSemeai){
			echo '
				tryAgainTomorrow = true;
				locked = true;
			';
		}
		if($doublexp!=null && !$goldenTsumego){
			echo 'doubleXP = true; countDownDate = '.$doublexp.';';
		}

		if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2'){
			$reviewEnabled = true;
			echo 'reviewEnabled = true;';
		}
		if(isset($_SESSION['loggedInUser'])){
			if($_SESSION['loggedInUser']['User']['isAdmin']>=1){
				if($isSandbox){
					//$reviewEnabled = true;
					//echo 'reviewEnabled = true;';
				}
			}
		}
		if($reviewCheat){
			$reviewEnabled = true;
			echo 'reviewEnabled = true;';
		}
		?>
	let tags = [];
	let unapprovedTags = [];
	let tagsGivesHint = [];
	let idTags = [];
	let allTags = [];
	let popularTags = [];
	let newTag = null;
	<?php
		for($i=0;$i<count($tags);$i++){
			echo 'tags.push("'.$tags[$i]['Tag']['name'].'");';
			echo 'unapprovedTags.push("'.$tags[$i]['Tag']['approved'].'");';
			echo 'tagsGivesHint.push("'.$tags[$i]['Tag']['hint'].'");';
			echo 'idTags.push("'.$tags[$i]['Tag']['tag_name_id'].'");';
		}
		for($i=0;$i<count($allTags);$i++)
			echo 'allTags.push("'.$allTags[$i]['TagName']['name'].'");';
		for($i=0;$i<count($popularTags);$i++)
			echo 'popularTags.push("'.$popularTags[$i].'");';
	?>
	<?php if($firstRanks==0){ ?>
	drawTags();
	<?php } ?>
	function drawTags(){
		if(tags.length>0) $(".tag-list").append("Tags: ");
		let foundNewTag = false;
		for(let i=0;i<tags.length;i++){
			let isNewTag = '';
			if(tags[i]===newTag){
				isNewTag = 'is-new-tag';
				foundNewTag = true;
			}else if(unapprovedTags[i]==0){
				isNewTag = 'is-new-tag';
			}
			if(tagsGivesHint[i]==1){
				isNewTag = 'tag-gives-hint '+isNewTag;
			}
			let tagLink = 'href="/tag_names/view/'+idTags[i]+'"';
			let tagLinkId = 'id="'+makeIdValidName(tags[i])+'"';
			if(typeof idTags[i] === "undefined"){
				tagLink = '';
				tagLinkId = '';
			}
			$(".tag-list").append('<a '+tagLink+' class="'+isNewTag+'" '+tagLinkId+'>'+tags[i]+'</a>');	
			if(i<tags.length-1){
				if(tagsGivesHint[i]==1){
					$(".tag-list").append('<p class="tag-gives-hint">, </p>');
				}else{
					if(!isLastComma(i, tagsGivesHint, tags))
						$(".tag-list").append('<p class="tag-comma">, </p>');
					else
						$(".tag-list").append('<p class="tag-gives-hint">, </p>');
				}
			}
		}
		if(foundNewTag){
			$(".tag-list").append(" ");
			$(".tag-list").append('<button id="undo-tags-button">x</button>');
			$("#undo-tags-button").show();
		}

		$(".add-tag-list-popular").append("Add tag: ");
		for(let i=0;i<popularTags.length;i++){
			$(".add-tag-list-popular").append('<a class="add-tag-list-anchor" id="'+makeIdValidName(popularTags[i])+'">'
			+popularTags[i]+'</a>');	
			if(i<popularTags.length-1)
				$(".add-tag-list-popular").append(', ');
		}
		$(".add-tag-list-popular").append(' <a class="add-tag-list-anchor" id="open-more-tags">[more]</a>');

		$(".add-tag-list").append("Add tag: ");
		for(let i=0;i<allTags.length;i++){
			$(".add-tag-list").append('<a class="add-tag-list-anchor" id="'+makeIdValidName(allTags[i])+'">'
			+allTags[i]+'</a>');	
			if(i<allTags.length-1)
				$(".add-tag-list").append(', ');
		}
		$(".add-tag-list").append(' <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>');
		<?php 
			if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2')
				echo '$(".tag-gives-hint").css("display", "inline");';
		?>
	}

	function makeIdValidName(name){
		let str = name.split("");
		for(let i=0;i<str.length; i++)
			if(!str[i].match(/[a-z]/i) && !str[i].match(/[0-9]/i))
				str[i] = "-";
		return "tag-"+str.join("");
	}

	function isLastComma(index, hints, tags){
		if(index >= hints.length-1){
			if(newTag!=null)
				return false;
			else
				return true;
		}
		for(let i=index+1;i<hints.length;i++){
			if(hints[i]==0)
				return false;
		}
		return true;
	}

	for(let i=0;i<allTags.length;i++){
		let currentIdValue = "#"+makeIdValidName(allTags[i]);
		$('.tag-container').on('click', currentIdValue, function(e){
			e.preventDefault();
			setCookie("addTag", "<?php echo $t['Tsumego']['id']; ?>-"+allTags[i]);
			newTag = $(currentIdValue).text();
			let newAllTags = [];
			for(let i=0;i<allTags.length;i++){
				if(allTags[i] !== $(currentIdValue).text())
					newAllTags.push(allTags[i]);
			}	
			allTags = newAllTags;
			tags.push($(currentIdValue).text());
			$(".tag-list").html("");
			$(".add-tag-list").html("");
			$(".add-tag-list-popular").html("");
			drawTags();
			$(".add-tag-list").hide();
			$(".add-tag-list-popular").hide();
		});
	}
	$('.tag-container').on('click', '#undo-tags-button', function(){
		setCookie("addTag", 0);
		$(".tag-list").html("");
		$(".add-tag-list").html("");
		$(".add-tag-list-popular").html("");
		$(".add-tag-list-popular").show();
		$(".add-tag-list").hide();
		tags = [];
		allTags = [];
		newTag = null;
		<?php
			for($i=0;$i<count($tags);$i++)
				echo 'tags.push("'.$tags[$i]['Tag']['name'].'");';
			for($i=0;$i<count($allTags);$i++)
				echo 'allTags.push("'.$allTags[$i]['TagName']['name'].'");';
		?>
		drawTags();
	});

	$('.tag-container').on('click', "#open-add-tag-menu", function(e){
		$("#open-add-tag-menu").hide();
		$(".add-tag-list").hide();
		$(".add-tag-list-popular").show();
	});

	$('.tag-container').on('click', "#open-more-tags", function(e){
		$("#open-add-tag-menu").hide();
		$(".add-tag-list").show();
		$(".add-tag-list-popular").hide();
	});
		var now = new Date().getTime();
		var distance = countDownDate - now;
		var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
		var seconds2 = Math.floor((distance % (1000 * 60)) / 1000);
		if (distance >= 0 && sprintLockedInSecretArea){
				window.location.href = "/sets";
		}
		<?php
		if($stopParameter2==0) echo 'tcount = 30.0;';
		elseif($stopParameter2==1) echo 'tcount = 60.0;';
		elseif($stopParameter2==2) echo 'tcount = 240.0;';
		else echo 'tcount = 0.0;';
		?>

		var tcounter = 250;
		if(mode==3){
			tcounter = 100;
			moveTimeout = 50;
		}

		var x = setInterval(function(){
			if(mode!=3){
				if(doubleXP){
					var now = new Date().getTime();
					var distance = countDownDate - now;
					var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
					var seconds2 = Math.floor((distance % (1000 * 60)) / 1000);
					var timeOutput;
					if(minutes>2)
						document.cookie = "extendedSprint="+minutes+";path=/tsumegos/play;SameSite=none;Secure=false";
					if(distance >= 0){
						if(!sprintLockedInSecretArea){
							if(seconds2<10)
								timeOutput = minutes + ":0" + seconds2;
							else	
								timeOutput = minutes + ":" + seconds2;
							document.getElementById("status2").style.color = "<?php echo $playBlueColor; ?>";
							document.getElementById("xpDisplay").style.color = "<?php echo $playBlueColor; ?>";
							document.getElementById("status2").innerHTML = "<h3>Double XP "+timeOutput+"</h3>";
							<?php
							if($levelBar==1){
								if(!$goldenTsumego)
									echo 'document.getElementById("xpDisplay").innerHTML = \'<font size="4">'.$t['Tsumego']['difficulty'].'×2 XP</font>\';';
								else
									echo 'document.getElementById("xpDisplay").innerHTML = \'<font size="4">'.$t['Tsumego']['difficulty'].' XP</font>\';';
							}else{
								echo 'document.getElementById("xpDisplay").innerHTML = \'<font size="4"><div class="eloTooltip">+'.$eloScoreRounded.'<span class="eloTooltiptext">+'.$eloScore.'</span></div>/<div class="eloTooltip">'.$eloScore2dRounded.' <span class="eloTooltiptext">'.$eloScore2d.'</span></div> '.$avActiveText.'</font>\';';
							}
							?>
							document.cookie = "sprint=1;path=/tsumegos/play;SameSite=none;Secure=false";
						}else{
							window.location.href = "/sets";
						}
					}else{
						clearInterval(x);
						<?php
						if(isset($sprintActivated)){
							echo 'document.cookie = "sprint=2;path=/tsumegos/play;SameSite=none;Secure=false";';
							echo 'document.cookie = "doublexp=0;path=/tsumegos/play;SameSite=none;Secure=false";';
						}
						if($levelBar==1){
							if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2'){
								 echo '
									document.getElementById("xpDisplay").style.color = "'.$playGreenColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4"><b>Solved</b> ('.$t['Tsumego']['difficulty'].' XP) '.$sandboxComment.'</font>\';
								';
							}else if($t['Tsumego']['status']=='setW2' || $t['Tsumego']['status']=='setX2'){
								echo '
									document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP (1/2) '.$sandboxComment.'</font>\';
								';
							}else{
								echo '
									document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP '.$sandboxComment.'</font>\';
								';
							}
						}else{
							echo '
									document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4"><div class="eloTooltip">+'.$eloScoreRounded.'<span class="eloTooltiptext">+'.$eloScore.'</span></div>/<div class="eloTooltip">'.$eloScore2dRounded.' <span class="eloTooltiptext">'.$eloScore2d.'</span></div> '.$avActiveText.'</font>\';
								';
						}
						?>
						doubleXP = false;
					}
				}else{
					<?php
					if($levelBar==1){
						if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2'){
							 echo '
								document.getElementById("xpDisplay").style.color = "'.$playGreenColor.'";
								document.getElementById("xpDisplay").innerHTML = \'<font size="4"><b>Solved</b> ('.$t['Tsumego']['difficulty'].' XP) '.$sandboxComment.'</font>\';
							';
						}else if($t['Tsumego']['status']=='setW2' || $t['Tsumego']['status']=='setX2'){
							echo '
								document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
								document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP (1/2) '.$sandboxComment.'</font>\';
							';
						}else{
							echo '
								document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
								document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP '.$sandboxComment.'</font>\';
							';
						}
					}else{
						echo '
							document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
							document.getElementById("xpDisplay").innerHTML = \'<font size="4"><div class="eloTooltip">+'.$eloScoreRounded.'<span class="eloTooltiptext">+'.$eloScore.'</span></div>/<div class="eloTooltip">'.$eloScore2dRounded.' <span class="eloTooltiptext">'.$eloScore2d.'</span></div> '.$sandboxComment.''.$avActiveText.'</font>\';';
					}
					?>
				}
			}else{
				if(timeModeEnabled){
					tcount-=0.1;
					tcount = tcount.toFixed(1);
					tcountMin = Math.floor(tcount/60);
					tcountSec = tcount%60;
					tcountSec = tcountSec.toFixed(1);
					if(tcountSec<10) tplace = "0";
					else tplace = "";

					$("#time-mode-countdown").html(tcountMin+":"+tplace+tcountSec);

					if(tcount == 0){
						timeUp = true;
						locked = true;
						tryAgainTomorrow = true;
						document.cookie = "rank=<?php echo $mode3ScoreArray[2]; ?>";
						document.cookie = "misplay=1;path=/tsumegos/play;SameSite=none;Secure=false";
						$("#time-mode-countdown").css("color","#e03c4b");
						document.getElementById("status").style.color = "#e03c4b";
						document.getElementById("status").innerHTML = "<h2>Time up</h2>";
						clearInterval(x);
						toggleBoardLock(true);
					}
				}else{
					clearInterval(x);
				}
			}
		}, tcounter);

		$('#target').click(function(e){
			if(locked==true){
				<?php
				if($mode==1 || $mode==3) if($next!='0' && !$isSemeai) echo 'window.location = "/tsumegos/play/'.$next.'";';
				if($mode==2) echo 'window.location = "/tsumegos/play/'.$nextMode['Tsumego']['id'].'";';
				?>
			}
		});

		if(!showCommentSpace) $("#commentSpace").hide();
		$("#show").click(function(){
			if(!msg2selected){
				$("#msg2").fadeIn(250);
				document.getElementById("greyArrow1").src = "/img/greyArrow2.png";
			}else{
				$("#msg2").fadeOut(250);
				document.getElementById("greyArrow1").src = "/img/greyArrow1.png";
			}
			msg2selected = !msg2selected;
		});

		$("#show2").click(function(){
			if(!msg2xselected){
				$("#msg2x").fadeIn(250);
				document.getElementById("greyArrow").src = "/img/greyArrow2.png";
			}else{
				$("#msg2x").fadeOut(250);
				document.getElementById("greyArrow").src = "/img/greyArrow1.png";
			}
			msg2xselected = !msg2xselected;
		});
		$("#msg3").hide();
		$("#show3").click(function(){
			if(!msg3selected){
				$("#msg3").fadeIn(250);
				document.getElementById("greyArrow2").src = "/img/greyArrow2.png";
			}else{
				$("#msg3").fadeOut(250);
				document.getElementById("greyArrow2").src = "/img/greyArrow1.png";
			}
			msg3selected = !msg3selected;
		});
		
		$("#show4").click(function(){
			if(!msg4selected){
				$("#msg4").fadeIn(250);
				document.getElementById("greyArrow4").src = "/img/greyArrow2.png";
			}else{
				$("#msg4").fadeOut(250);
				document.getElementById("greyArrow4").src = "/img/greyArrow1.png";
			}
			msg4selected = !msg4selected;
		});
		
		<?php
			if(isset($_SESSION['loggedInUser']['User']['id'])){
				//if($_SESSION['loggedInUser']['User']['id']==72) echo '$("#msg5").show();';
			}
		?>
		$("#show5").click(function(){
			if(!msg5selected){
				$("#msg5").fadeIn(250);
				document.getElementById("greyArrow5").src = "/img/greyArrow2.png";
			}else{
				$("#msg5").fadeOut(250);
				document.getElementById("greyArrow5").src = "/img/greyArrow1.png";
			}
			msg5selected = !msg5selected;
		});
		<?php if($tv!=null){ ?>
		<?php if($tv['TsumegoVariant']['type']=='multiple_choice'){ ?>
			$("#showxMC").hide();
			$("#showMC").click(function(){
				if(!msgMCselected){
					$("#showxMC").fadeIn(250);
					document.getElementById("greyArrowMC").src = "/img/greyArrow2.png";
				}else{
					$("#showxMC").fadeOut(250);
					document.getElementById("greyArrowMC").src = "/img/greyArrow1.png";
				}
				msgMCselected = !msgMCselected;
			});
		<?php }else{ ?>
			$("#showxSE").hide();
			$("#showSE").click(function(){
				if(!msgSEselected){
					$("#showxSE").fadeIn(250);
					document.getElementById("greyArrowSE").src = "/img/greyArrow2.png";
				}else{
					$("#showxSE").fadeOut(250);
					document.getElementById("greyArrowSE").src = "/img/greyArrow1.png";
				}
				msgSEselected = !msgSEselected;
			});
		<?php } ?>
		<?php } ?>
		$("#commentPosition").click(function(){
		  let commentContent = $("#CommentMessage").val();
		  let additionalCoords = "";
		  let current = besogo.editor.getCurrent();
		  let besogoOrientation = besogo.editor.getOrientation();
		  if(besogoOrientation[1]=="full-board")
			besogoOrientation[0] = besogoOrientation[1];
		  let isInTree = besogo.editor.isMoveInTree(current);
		  current = isInTree[0];
		  
		  if(isInTree[1]['x'].length>0){
			  for(let i=isInTree[1]['x'].length-1;i>=0;i--)
				additionalCoords += isInTree[1]['x'][i] + isInTree[1]['y'][i] + " ";
			  additionalCoords = " + " + additionalCoords;
		  }
		  if(commentContent.includes("[current position]")){
			 commentContent = commentContent.replace('[current position]','');
		  }
		  $("#CommentMessage").val(commentContent + "[current position]" + additionalCoords);
		  
		  if(current===null || current.move===null){
			$("#CommentPosition").val(
				"-1/-1/0/0/0/0/0/0/0"
			);
		  }else{
		    pX = -1;
		    pY = -1;
			if(current.moveNumber>1){
				pX = current.parent.move.x;
				pY = current.parent.move.y;
			}
			if(current.children.length===0){
				cX = -1;
				cY = -1;
			}else{
				cX = current.children[0].move.x;
				cY = current.children[0].move.y;
			}
			
			let newP = current.parent;
			let newPcoords = current.move.x+"/"+current.move.y+"+";
			  while(newP!==null && newP.move!==null){
				  newPcoords += newP.move.x+"/"+newP.move.y+"+"
				  newP = newP.parent;
			  }
			  newPcoords = newPcoords.slice(0, -1);
			$("#CommentPosition").val(
			  current.move.x+"/"+current.move.y+"/"+pX+"/"+pY+"/"+cX+"/"+cY+"/"+current.moveNumber+"/"+current.children.length+"/"+besogoOrientation[0]+"|"+newPcoords
			);
		  }
		});
		let solutionRequest = true;
		<?php if(($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2' || $t['Tsumego']['status']=='setW2') || $isSandbox){ ?>
			displaySettings();
			solutionRequest = false;
		<?php } ?>
		if(authorProblem)
			displaySettings();
		<?php if(isset($_SESSION['loggedInUser']['User']['id'])){if($_SESSION['loggedInUser']['User']['isAdmin']>=1){if(!$requestSolution){ ?>
			if(solutionRequest)
				displaySolutionRequest(); 
			$("#showx99").click(function(){
				window.location.href = "/tsumegos/play/"+<?php echo $t['Tsumego']['id']; ?>+"?requestSolution="+<?php echo $_SESSION['loggedInUser']['User']['id']; ?>;
			});	
		<?php }}} ?>		
			
		<?php if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210 || $tv!=null){ ?>
		$("#alertCheckbox").change(function(){
			$("#multipleChoiceAlerts").fadeOut(500);
		});
		<?php } ?>
		$("#customAlertCheckbox").change(function(){
			$("#customAlerts").fadeOut(500);
		});
		$("#potionAlertCheckbox").change(function(){
			$("#potionAlerts").fadeOut(500);
		});
		$("#showx3").click(function(){
			jsCreateDownloadFile("<?php echo $getTitle; ?>");
		});
		$("#showx4").click(function(){
			jsCreateDownloadFile("<?php echo $t['Tsumego']['num']; ?>");
		});
		$("#showx7x").click(function(){
			$('.loader-container').css({"display":"flex"});
			window.location.href="/tsumegos/duplicatesearch/<?php echo $t['Tsumego']['id']; ?>";
		});
		
		//if(cvn) setTimeout(function () {window.location.href = "/tsumegos/play/"+nextButtonLink}, 50);
		
		var mouseX;
		var mouseY;
		$(document).mousemove(function(e){
		   mouseX = e.pageX; 
		   mouseY = e.pageY;
		});
		
		let tooltipSgfs = [];
		
		<?php
		if($mode!=3){
			for($a=0; $a<count($tooltipSgfs); $a++){
				echo 'tooltipSgfs['.$a.'] = [];';
				for($y=0; $y<count($tooltipSgfs[$a]); $y++){
					echo 'tooltipSgfs['.$a.']['.$y.'] = [];';
					for($x=0; $x<count($tooltipSgfs[$a][$y]); $x++){
						echo 'tooltipSgfs['.$a.']['.$y.'].push("'.$tooltipSgfs[$a][$x][$y].'");';
					}
				}
			}
			for($i=0; $i<count($navi); $i++)
				echo 'createPreviewBoard('.$i.', tooltipSgfs['.$i.'], '.$tooltipInfo[$i][0].', '.$tooltipInfo[$i][1].', '.$tooltipBoardSize[$i].');';
		}
		?>
	});
	
	function displaySolutionRequest(){
		if(!authorProblem)
			$("#showx99").css("display", "inline-block");
	}

	function displaySettings(){
		$("#showx99").css("display", "none");
		enableDownloads = true;
		$("#showx3").css("display", "inline-block");
		$("#showx7x").css("display", "inline-block");
		$("#showx5").css("display", "inline-block");
		$("#showx5").attr("href", "<?php echo '/tsumegos/open/'.$t['Tsumego']['id'].'/'.$sgf['Sgf']['id']; ?>");
		<?php if($_SESSION['loggedInUser']['User']['isAdmin']>0){ ?>
		<?php if($t['Tsumego']['duplicate']==0 || $t['Tsumego']['duplicate']==-1){ ?>
			$("#showx6").attr("href", "<?php echo '/sgfs/view/'.($t['Tsumego']['id']*1337); ?>");
		<?php }else{
			$duplicateOther = array();
			if($t['Tsumego']['duplicate']<=9)
				$duplicateMain = $t['Tsumego']['id'];
			else
				array_push($duplicateOther, $t['Tsumego']['id']);
			for($i=0; $i<count($duplicates); $i++){
				if($duplicates[$i]['Tsumego']['duplicate']<=9)
					$duplicateMain = $duplicates[$i]['Tsumego']['id'];
				else
					array_push($duplicateOther, $duplicates[$i]['Tsumego']['id']);
			}
			$duplicateParamsUrl = '?duplicates=';
			for($i=0; $i<count($duplicateOther); $i++){
				$duplicateParamsUrl .= $duplicateOther[$i];
				if($i!=count($duplicateOther)-1)
					$duplicateParamsUrl .= '-';
			}
			?>
			$("#showx6").attr("href", "<?php echo '/sgfs/view/'.($t['Tsumego']['id']*1337); ?>");
		<?php } ?>
		$("#showx8").attr("href", "<?php echo '/users/tsumego_rating/'.$t['Tsumego']['id']; ?>");
		$("#showx4").css("display", "inline-block");
		$("#show4").css("display", "inline-block");
		$("#show5").css("display", "inline-block");
		$("#showx6").css("display", "inline-block");
		$("#showx8").css("display", "inline-block");
		<?php } ?>
	}
	
	function jsCreateDownloadFile(name){
		if(enableDownloads){
			var blob = new Blob(["<?php echo $sgf['Sgf']['sgf']; ?>"],{
				type: "sgf",
			});
			saveAs(blob, name+".sgf");
		}
	}

	function reset(){
		if(!tryAgainTomorrow) locked = false;
		hoverLocked = false;
		opponent = (player == JGO.BLACK) ? JGO.WHITE : JGO.BLACK;
		ko = false, lastMove = false;
		lastHover = false, lastX = -1, lastY = -1;
		moveCounter = 0;
		isCorrect = false;
		isIncorrect = false;
		whiteMoveAfterCorrect = false;
		whiteMoveAfterCorrectI = 0;
		whiteMoveAfterCorrectJ = 0;
		disableAutoplay = false;
		branch = "";
		rw = false;
		boardSize = 19;
		<?php if($checkBSize!=19) echo 'boardSize = '.$checkBSize.';'; ?>
		var i, j;
		tStatus = "<?php echo $t['Tsumego']['status']; ?>";
		if(tStatus=="setS2"||tStatus=="setC2") heartLoss = false;
		else heartLoss = true;

		if(move==0) heartLoss = false;
		if(noXP==true||freePlayMode==true||locked==true||authorProblem==true) heartLoss = false;
		if(mode==2) heartLoss = false;
		freePlayMode = false;
		freePlayMode2 = false;
		freePlayMode2done = false;
		if(heartLoss){
			misplays++;
			document.cookie = "misplay="+misplays+";path=/tsumegos/play;SameSite=none;Secure=false";
			updateHealth();
		}
		move = 0;

		document.getElementById("status").innerHTML = "";
		document.getElementById("theComment").style.cssText = "display:none;";
	}

	function skip(){
		if( $("#skipButton").text()!="Next" ) {
			document.cookie = "skip=1";
			document.cookie = "seconds="+seconds+";path=/tsumegos/play;SameSite=none;Secure=false";
		}
		<?php echo 'window.location.href = "/tsumegos/play/'.$nextMode['Tsumego']['id'].'";'; ?>
	}

	function runXPBar(increase){
		<?php $newXP = ($user['User']['xp'] + $t['Tsumego']['difficulty'])/$user['User']['nextlvl']*100; ?>
		if(mode==1 || mode==2){
			if(levelBar==1 && increase==true){
				if(!doubleXP) x2 = 1;
				else x2 = 2;
				<?php
				echo 'userDifficulty = '.$t['Tsumego']['difficulty'].'*x2;
				userNextlvl = '.$user['User']['nextlvl'].';
				newXP2 = Math.min(('.$user['User']['xp'].'+userDifficulty)/userNextlvl*100, 100);
				barPercent1 = newXP2;
				barPercent2 = Math.min('.substr(round($user['User']['elo_rating_mode']), -2).'+ '.$eloScoreRounded.', 100);
				newXP = '.$newXP.';'; ?>
				$("#xp-bar-fill").css({"width":newXP2+"%"});
				$("#xp-bar-fill").css("-webkit-transition","all 1s ease");
				$("#xp-increase-fx").fadeIn(0);
				$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
				setTimeout(function(){
					$("#xp-increase-fx").fadeOut(500);
					$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
				},1000);
			}else if(levelBar==2){
				if(!ratingBarLock){
					if(!doubleXP) x2 = 1;
					else x2 = 2;
					<?php echo 'userDifficulty = '.$t['Tsumego']['difficulty'].'*x2;
					userNextlvl = '.$user['User']['nextlvl'].';
					if(increase) newXP2 = Math.min('.substr(round($user['User']['elo_rating_mode']), -2).'+ '.$eloScoreRounded.', 100);
					else newXP2 = Math.min('.substr(round($user['User']['elo_rating_mode']), -2).'+ '.$eloScore2Rounded.', 100);
					barPercent1 = Math.min(('.$user['User']['xp'].'+userDifficulty)/userNextlvl*100, 100);
					barPercent2 = newXP2;'; ?>
					$("#xp-bar-fill").css({"width":newXP2+"%"});
					$("#xp-bar-fill").css("-webkit-transition","all 1s ease");
					$("#xp-increase-fx").fadeIn(0);
					$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
					setTimeout(function(){
						$("#xp-increase-fx").fadeOut(500);
						$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
					},1000);
				}
			}
		}else if(mode==3){
			<?php
				$xxx2 = (($crs+1)/$stopParameter)*100; 
				if($xxx2>100) $xxx2 = 100;
			?>
			$("#xp-bar-fill").css({"width":"<?php echo $xxx2; ?>%"});
			$("#xp-bar-fill").css("-webkit-transition","all 1s ease");
			$("#xp-increase-fx").fadeIn(0);
			$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
			setTimeout(function(){
				$("#xp-increase-fx").fadeOut(500);
				$("#xp-bar-fill").css({"-webkit-transition":"all 1s ease","box-shadow":""});
			},1000);
		}
	}
	
	function runXPNumber(id, start, end, duration, ulvl){
	start = Math.round(start);
	end = Math.round(end);
	<?php if(isset($_SESSION['loggedInUser']['User']['id'])){ ?>
	let runXPNumberNextLvl = <?php echo $user['User']['nextlvl']; ?>+"";
	if(start!==end && !ratingBarLock){
		userXP = end;
		userLevel = ulvl;
		var range = end - start;
		var current = start;
		var increment = end > start? 1 : -1;
		var stepTime = Math.abs(Math.floor(duration / range));
		var obj = document.getElementById(id);
		var timer = setInterval(function(){
			current += increment;
			if(mode!=3){
				if(levelBar==1){
					obj.innerHTML = current+"/"+runXPNumberNextLvl;
				}else if(levelBar==2)
					obj.innerHTML = current;
			}else{
				obj.innerHTML = current+"/"+runXPNumberNextLvl;
			}
			if(current == end){
				clearInterval(timer);
			}
		}, stepTime);
		ratingBarLock = true;
	}
	<?php } ?>
	}
	
	function updateHealth(){
		<?php
			$m = 1;
			while($health>0){
				$h = $health-1;
				echo 'if(misplays=='.$m.')document.getElementById("heart'.$h.'").src = "/img/'.$emptyHeart.'.png"; ';
				$health--;
				$m++;
			}
		?>
	}

	function sprint(){
		if(sprintEnabled){
			doubleXP = true;
			countDownDate = new Date();
			countDownDate.setMinutes(countDownDate.getMinutes() + 2);
			//setCookie("doublexp", countDownDate.getTime());
			document.cookie = "doublexp="+countDownDate.getTime()+";path=/tsumegos/play;SameSite=none;Secure=false";
			document.cookie = "sprint=1;path=/tsumegos/play;SameSite=none;Secure=false";
			document.cookie = "misplay="+misplays+";path=/tsumegos/play;SameSite=none;Secure=false";
			document.getElementById("sprint").src = "/img/hp1x.png";
			document.getElementById("sprint").style = "cursor: context-menu;";

			var x = setInterval(function(){
				if(mode==1 || $mode==3){
					if(doubleXP){
						var now = new Date().getTime();
						var distance = countDownDate - now;
						var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
						var seconds = Math.floor((distance % (1000 * 60)) / 1000);
						var timeOutput;
						if(minutes>2)
							document.cookie = "extendedSprint="+minutes+";path=/tsumegos/play;SameSite=none;Secure=false";
						if(distance>=0){
							if(!sprintLockedInSecretArea){
								if(seconds<10)
									timeOutput = minutes + ":0" + seconds;
								else
									timeOutput = minutes + ":" + seconds;
								document.getElementById("status2").innerHTML = "<h3>Double XP "+timeOutput+"</h3>";
								document.getElementById("status2").style.color = "<?php echo $playBlueColor; ?>";
								//document.getElementById("xpDisplay").style.color = "<?php echo $playBlueColor; ?>";
								<?php
								//if(!$goldenTsumego) echo 'document.getElementById("xpDisplay").innerHTML = \'<font size="4">'.$t['Tsumego']['difficulty'].'×2 XP</font>\';';
								//else echo 'document.getElementById("xpDisplay").innerHTML = \'<font size="4">'.$t['Tsumego']['difficulty'].' XP</font>\';';
								?>
								document.cookie = "sprint=1;path=/tsumegos/play;SameSite=none;Secure=false";
							}else{
								window.location.href = "/sets";
							}
						}else{
							clearInterval(x);
							<?php
							if(isset($sprintActivated)) 
								echo 'document.cookie = "sprint=2;path=/tsumegos/play;SameSite=none;Secure=false";';
							if($t['Tsumego']['status']=='setS2' || $t['Tsumego']['status']=='setC2'){
								echo '
									document.getElementById("xpDisplay").style.color = "'.$playGreenColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4"><b>Solved</b> ('.$t['Tsumego']['difficulty'].' XP) '.$sandboxComment.'</font>\';
								';
							}else if($t['Tsumego']['status']=='setW2' || $t['Tsumego']['status']=='setX2'){
								echo '
									document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP (1/2) '.$sandboxComment.'</font>\';
								';
							}else{
								echo '
									document.getElementById("xpDisplay").style.color = "'.$xpDisplayColor.'";
									document.getElementById("xpDisplay").innerHTML = \'<font size="4">  '.$t['Tsumego']['difficulty'].' XP '.$sandboxComment.'</font>\';
								';
							}
							?>
							doubleXP = false;
						}
					}
				}
			}, 250);
			sprintEnabled = false;
		}
	}
	
	function commentPosition(x, y, pX, pY, cX, cY, mNum, cNum, orientation, newX=false, newY=false){
		positionParams = [];
		positionParams[0] = x;
		positionParams[1] = y;
		positionParams[2] = pX;
		positionParams[3] = pY;
		positionParams[4] = cX;
		positionParams[5] = cY;
		positionParams[6] = mNum;
		positionParams[7] = cNum;
		positionParams[8] = orientation;
		if(newX!=false)
			positionParams[9] = newX;
		besogo.editor.commentPosition(positionParams);
	}
	
	function intuition(){
		setCookie("intuition", 1);
		document.getElementById("intuition").src = "/img/hp2x.png";
		document.getElementById("intuition").style = "cursor: context-menu;";
		intuitionEnabled = false;
		besogo.editor.intuitionHeroPower();
	}

	function rejuvenation(){
		if(rejuvenationEnabled){
			<?php
				for($i=0; $i<$user['User']['health']; $i++){
					echo 'document.getElementById("heart'.$i.'").src = "/img/'.$fullHeart.'.png";';
				}
				echo 'document.cookie = "rejuvenationx=1;path=/tsumegos/play;SameSite=none;Secure=false";';
			?>
			misplays = 0;
			document.cookie = "rejuvenation=1;path=/tsumegos/play;SameSite=none;Secure=false";
			document.getElementById("rejuvenation").src = "/img/hp3x.png";
			document.getElementById("rejuvenation").style = "cursor: context-menu;";
			<?php if(isset($intuitionEnabled) &&!$intuitionEnabled) echo 'setCookie(intuition, 2);'; ?>
			intuitionEnabled = true;
			rejuvenationEnabled = false;
			<?php echo 'window.location = "/tsumegos/play/'.$t['Tsumego']['id'].'";'; ?>
		}
	}

	function refinement(){
		if(refinementEnabled){
			setCookie("refinement", 1);
			document.getElementById("refinement").src = "/img/hp4x.png";
			document.getElementById("refinement").style = "cursor: context-menu;";
			<?php echo 'window.location.href = "/tsumegos/play/'.$g['Tsumego']['id'].'";'; ?>
		}
		refinementEnabled = false;
	}

	function revelation(){
		if(revelationEnabled){
			<?php if($t['Tsumego']['status']!='setS2' && $t['Tsumego']['status']!='setC2'){ ?>
				document.getElementById("status").style.color = "<?php echo $playGreenColor; ?>";
				document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
				if(light==true)
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
				besogo.editor.setReviewEnabled(true);
				besogo.editor.setControlButtonLock(false);
				toggleBoardLock(true);
				$("#besogo-review-button-inactive").attr("id","besogo-review-button");
				$("#commentSpace").show();
				document.getElementById("currentElement").style.backgroundColor = "<?php echo $playGreenColor ?>";
				displaySettings();
				setCookie("noScore", "<?php echo $score1; ?>");
				setCookie("noPreId", "<?php echo $t['Tsumego']['id']; ?>");
				setCookie("revelation", "1");
			<?php } ?>
			document.getElementById("revelation").src = "/img/hp6x.png";
			document.getElementById("revelation").style = "cursor: context-menu;";
			$("#revelation").attr("title","Revelation (<?php echo $user['User']['revelation']-1; ?>): Solves a problem, but you don\'t get any reward.");
			revelationEnabled = false;
		}
	}

	if(document.querySelector("input[name=favCheckbox]")){
		var checkbox = document.querySelector("input[name=favCheckbox]");
		checkbox.addEventListener('change', function(){
			if(this.checked){
				<?php if(!$favorite){ ?>
					setCookie("favorite", <?php echo $t['Tsumego']['id']; ?>);
					document.getElementById("refreshLinkToStart").href = "/tsumegos/play/<?php echo $lv ?>?refresh=1";
					document.getElementById("refreshLinkToSets").href = "/tsumegos/play/<?php echo $lv ?>?refresh=2";
					document.getElementById("playTitleA").href = "/tsumegos/play/<?php echo $lv ?>?refresh=3";
					document.getElementById("refreshLinkToHighscore").href = "/tsumegos/play/<?php echo $lv ?>?refresh=4";
					document.getElementById("refreshLinkToDiscuss").href = "/tsumegos/play/'.$lv.'?refresh=5";
					document.getElementById("refreshLinkToSandbox").href = "/tsumegos/play/'.$lv.'?refresh=6";
				<?php }else{ ?>
					setCookie("favorite", 0);
					document.getElementById("refreshLinkToStart").href = "/";
					document.getElementById("refreshLinkToSets").href = "/sets";
					document.getElementById("playTitleA").href = "/sets/view/<?php echo $t['Tsumego']['set_id']; ?>";
					document.getElementById("refreshLinkToHighscore").href = "/users/highscore";
					document.getElementById("refreshLinkToDiscuss").href = "/comments";
					document.getElementById("refreshLinkToSandbox").href = "/sets/beta";
				<?php } ?>
				document.getElementById("favCheckbox2").setAttribute("title", "Marked as favorite");
			}else{
				<?php if(!$favorite){ ?>
					setCookie("favorite", 0);
					document.getElementById("refreshLinkToStart").href = "/";
					document.getElementById("refreshLinkToSets").href = "/sets";
					document.getElementById("playTitleA").href = "/sets/view/<?php echo $t['Tsumego']['set_id']; ?>";
					document.getElementById("refreshLinkToHighscore").href = "/users/highscore";
					document.getElementById("refreshLinkToDiscuss").href = "/comments";
					document.getElementById("refreshLinkToSandbox").href = "/sets/beta";
				<?php }else{ ?>
					setCookie("favorite", -<?php echo $t['Tsumego']['id']; ?>);
					document.getElementById("refreshLinkToStart").href = "/tsumegos/play/<?php echo $lv ?>?refresh=1";
					document.getElementById("refreshLinkToSets").href = "/tsumegos/play/<?php echo $lv ?>?refresh=2";
					document.getElementById("playTitleA").href = "/tsumegos/play/<?php echo $lv ?>?refresh=3";
					document.getElementById("refreshLinkToHighscore").href = "/tsumegos/play/<?php echo $lv ?>?refresh=4";
					document.getElementById("refreshLinkToDiscuss").href = "/tsumegos/play/'.$lv.'?refresh=5";
					document.getElementById("refreshLinkToSandbox").href = "/tsumegos/play/'.$lv.'?refresh=6";
				<?php } ?>
				document.getElementById("favCheckbox2").setAttribute("title", "Mark as favorite");
			}
		});
	}

	function sprintHover(){
		if(sprintEnabled) document.getElementById("sprint").src = '/img/hp1h.png';
	}
	function sprintNoHover(){
		if(sprintEnabled) document.getElementById("sprint").src = "/img/hp1.png";
	}
	function intuitionHover(){
		if(intuitionEnabled) document.getElementById("intuition").src = '/img/hp2h.png';
	}
	function intuitionNoHover(){
		if(intuitionEnabled) document.getElementById("intuition").src = "/img/hp2.png";
	}
	function rejuvenationHover(){
		if(rejuvenationEnabled) document.getElementById("rejuvenation").src = '/img/hp3h.png';
	}
	function rejuvenationNoHover(){
		if(rejuvenationEnabled) document.getElementById("rejuvenation").src = "/img/hp3.png";
	}
	function refinementHover(){
		if(refinementEnabled) document.getElementById("refinement").src = '/img/hp4h.png';
	}
	function refinementNoHover(){
		if(refinementEnabled) document.getElementById("refinement").src = "/img/hp4.png";
	}
	function revelationHover(){
		if(revelationEnabled) document.getElementById("revelation").src = '/img/hp6h.png';
	}
	function revelationNoHover(){
		if(revelationEnabled) document.getElementById("revelation").src = "/img/hp6.png";
	}
	function thumbsUpHover(){
		if(!thumbsUpSelected && !thumbsUpSelected2) document.getElementById("thumbs-up").src = '/img/thumbs-up.png';
	}
	function thumbsUpNoHover(){
		if(!thumbsUpSelected && !thumbsUpSelected2) document.getElementById("thumbs-up").src = '/img/thumbs-up-inactive.png';
	}
	function thumbsDownHover(){
		if(!thumbsDownSelected && !thumbsDownSelected2) document.getElementById("thumbs-down").src = '/img/thumbs-down.png';
	}
	function thumbsDownNoHover(){
		if(!thumbsDownSelected && !thumbsDownSelected2) document.getElementById("thumbs-down").src = '/img/thumbs-down-inactive.png';
	}

	function selectFav(){
		document.getElementById("ans2").innerHTML = "";
	}
	
	$(document).keydown(function(event){
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if(mode!=2){
			if(!msg2selected && !adminCommentOpened && !besogo.editor.getReviewMode()){
				if(keycode == '37'){
					if(prevButtonLink!=0)
						window.location.href = prevButtonLink;
					else
						window.location.href = '/sets/view/'+nextButtonLinkSet;
				}else if(keycode == '39'){
					if(nextButtonLink!=0)
						window.location.href = '/tsumegos/play/'+nextButtonLink;
					else
						window.location.href = '/sets/view/'+nextButtonLinkSet;
				}
			}
		}
	});
	/*
	$(document).keydown(function(event){
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if(keycode == '82'){
			reset();
		}
	});
	*/
	function m1hover(){
		$("#modeSwitcher1 label").css("background-color", "#5dcb89");
	}
	function m1noHover(){
		if(ui==1) $("#modeSwitcher1 label").css("background-color", "#54b97c");
		else $("#modeSwitcher1 label").css("background-color", "#5b5d60");
	}
	function m2hover(){
		$("#modeSwitcher2 label").css("background-color", "#ca7a6f");
	}
	function m2noHover(){
		if(ui==2) $("#modeSwitcher2 label").css("background-color", "#ca6658");
		else $("#modeSwitcher2 label").css("background-color", "#5b5d60");
	}
	function goMode3x(){
		<?php echo 'window.location.href = "/ranks/overview";'; ?>
	}

	function review(){
		if(reviewEnabled){
			hoverLocked = true;
			rw = true;
			if(!tryAgainTomorrow) locked = false;
			freePlayMode = false;

			<?php
				if($ui!=2){
					echo 'player = JGO.'.$playerColor[$whoPlays2].';';
				}
			?>
			opponent = (player == JGO.BLACK) ? JGO.WHITE : JGO.BLACK;
			ko = false, lastMove = false;
			lastHover = false, lastX = -1, lastY = -1;
			moveCounter = 0;
			move = 0;
			rwLevel = 0;
			rwBranch = 0;
			rwSwitcher = 2;
			inPath = false;
			pathLock = false;

			$(".reviewNavi").fadeIn(250);

			boardSize = 19;
			<?php if($checkBSize!=19) echo 'boardSize = '.$checkBSize.';'; ?>
			var i, j;
			for (i=0;i<boardSize; i++) {
				for (j=0;j<boardSize; j++) {
					jboard.setType(new JGO.Coordinate(i, j), JGO.CLEAR);
					jboard.setMark(new JGO.Coordinate(i, j), JGO.MARK.NONE);
				}
			}

			document.getElementById("status").innerHTML = "";
			<?php
				for($i=0; $i<count($black); $i++){
					if($i%2 == 0){
						echo 'jboard.setType(new JGO.Coordinate('.$black[$i].', '.$black[$i+1].'), JGO.'.$playerColor[0].');';
					}
				}
				for($i=0; $i<count($white); $i++){
					if($i%2 == 0){
						echo 'jboard.setType(new JGO.Coordinate('.$white[$i].', '.$white[$i+1].'), JGO.'.$playerColor[1].');';
					}
				}
			?>

			<?php
			if($t['Tsumego']['set_id']==156 || $t['Tsumego']['set_id']==159 || $t['Tsumego']['set_id']==161){
				echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['lastPlayed'][0].', '.$additionalInfo['lastPlayed'][1].'), JGO.MARK.CIRCLE);';
				for($i=0; $i<count($additionalInfo['triangle']); $i++){
					echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['triangle'][$i][0].', '.$additionalInfo['triangle'][$i][1].'), JGO.MARK.TRIANGLE);';
				}
				for($i=0; $i<count($additionalInfo['square']); $i++){
					echo 'jboard.setMark(new JGO.Coordinate('.$additionalInfo['square'][$i][0].', '.$additionalInfo['square'][$i][1].'), JGO.MARK.SQUARE);';
				}
			}
			?>
			<?php
			for($i=0; $i<count($visual); $i++){
				if($visual[$i][3] === '+') echo 'jboard.setMark(new JGO.Coordinate('.$visual[$i][0].', '.$visual[$i][1].'), JGO.MARK.CORRECT);';
				else echo 'jboard.setMark(new JGO.Coordinate('.$visual[$i][0].', '.$visual[$i][1].'), JGO.MARK.INCORRECT);';
			}
			?>
			document.getElementById("theComment").style.cssText = "display:none;";
		}
	}
	<?php
	$dynamicCommentCoords = array();
	$dynamicCommentCoords[0] = array();
	$dynamicCommentCoords[1] = array();
	
	$fn1 = 1;
	for($i=0; $i<count($commentCoordinates); $i++){
		$n2x = explode(' ', $commentCoordinates[$i]);
		if(count($n2x)>0){
			$fn2 = 1;
			for($j=count($n2x)-1; $j>=0; $j--){
				$n2xx = explode('-', $n2x[$j]);
				if(strlen($n2xx[0])>0 && strlen($n2xx[1])>0){
					echo 'function ccIn'.$fn1.$fn2.'(){besogo.editor.displayHoverCoord("'.$n2xx[2].'");}';
					echo 'function ccOut'.$fn1.$fn2.'(){besogo.editor.displayHoverCoord(-1);}';
					array_push($dynamicCommentCoords[0], 'ccIn'.$fn1.$fn2);
					array_push($dynamicCommentCoords[1], $n2xx[2]);
					$fn2++;
				}
			}
		}
		$fn1++;
	}

	$fn1 = 999;
	$n2x = explode(' ', $sT[1]);
	if(count($n2x)>0){
		$fn2 = 1;
		for($j=count($n2x)-1; $j>=0; $j--){
			$n2xx = explode('-', $n2x[$j]);
			if(strlen($n2xx[0])>0 && strlen($n2xx[1])>0){
				echo 'function ccIn'.$fn1.$fn2.'(){besogo.editor.displayHoverCoord("'.$n2xx[2].'");}';
				echo 'function ccOut'.$fn1.$fn2.'(){besogo.editor.displayHoverCoord(-1);}';
				array_push($dynamicCommentCoords[0], 'ccIn'.$fn1.$fn2);
				array_push($dynamicCommentCoords[1], $n2xx[2]);
				$fn2++;
			}
		}
	}
	$fn1++;
	?>

	function displayResult(result){
		setCookie("av", <?php echo $activityValue[0]; ?>);
		if(hasRevelation && revelationCounter>0){
			revelationEnabled = true;
			$(".revelation-anchor").css("cursor", "pointer");
			$("#revelation").attr("src", "/img/hp6.png");
		}
		if(result=='S'){
			$(".tag-gives-hint").css("display", "inline");
			elo2 = <?php echo $user['User']['elo_rating_mode']; ?>+eloScore;
			let ulvl;
			if(mode!=2){//mode 1 and 3 correct
				<?php echo $sandboxCheck; ?>
				document.getElementById("status").style.color = "<?php echo $playGreenColor; ?>";
				document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
				document.getElementById("xpDisplay").style.color = "white";
				if(light==true)
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
				if(set159){
					document.getElementById("theComment").style.cssText = "visibility:visible;color:green;";
					document.getElementById("theComment").innerHTML = "xxx";
				}
				$("#commentSpace").show();
				if(mode==3){
					document.cookie = "rank=<?php echo $mode3ScoreArray[0]; ?>";
					secondsy = seconds*10*<?php echo $t['Tsumego']['id']; ?>;
					document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
					timeModeEnabled = false;
					setCookie("score", "<?php echo $score1; ?>");
					setCookie("preId", "<?php echo $t['Tsumego']['id']; ?>");
					setCookie("mode", mode);
					$("#time-mode-countdown").css("color","<?php echo $playGreenColor; ?>");
					$("#reviewButton").show();
					$("#reviewButton-inactive").hide();
					runXPBar(true);
				}
				noLastMark = true;
				besogo.editor.setReviewEnabled(true);
				besogo.editor.setControlButtonLock(false);
				$("#besogo-review-button-inactive").attr("id","besogo-review-button");
				if(!noXP){
					if(!doubleXP){
						x2 = "<?php echo $score1; ?>";
						x3 = 1;
					}else{
						x2 = "<?php echo $score2; ?>";
						x3 = 2;
					}
					if(goldenTsumego){
						x2 = "<?php echo $score1; ?>";
						x3 = 1;
					}
					setCookie("score", x2);
					setCookie("mode", mode);
					if(goldenTsumego)
						setCookie("type", "g");
					$("#skipButton").text("Next");
					xpReward = (<?php echo $t['Tsumego']['difficulty']; ?>*x3) + <?php echo $user['User']['xp']; ?>;
					userNextlvl = <?php echo $user['User']['nextlvl']; ?>;
					ulvl = <?php echo $user['User']['level']; ?>;
					if(mode==1 || mode==2) secondsy = seconds;
					if(mode==3) secondsy = seconds*10*<?php echo $t['Tsumego']['id']; ?>;
					document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
					
					if(xpReward>userNextlvl){
						xpReward = userNextlvl;
						ulvl = ulvl + 1;
					}
					<?php if(isset($_SESSION['loggedInUser']['User']['id'])){ ?>
					if(mode==1 && levelBar==1){
						runXPBar(true);
						runXPNumber("account-bar-xp", userXP, xpReward, 1000, ulvl);
					}
					if(mode==1 && levelBar==2){
						runXPBar(true);
						runXPNumber("account-bar-xp", <?php echo $user['User']['elo_rating_mode']; ?>, elo2, 1000, ulvl);
					}
					userXP = xpReward;
					userElo = Math.round(elo2);
					<?php } ?>
					noXP = true;
				}else{
					if(mode==1){
						secondsy = seconds;
						document.cookie = "correctNoPoints=1";
						document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
						if(levelBar==2){
							runXPBar(true);
							runXPNumber("account-bar-xp", <?php echo $user['User']['elo_rating_mode']; ?>, elo2, 1000, ulvl);
						}
					}
				}
			}else{//mode 2 correct
				document.getElementById("status").style.color = "<?php echo $playGreenColor; ?>";
				document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
				document.getElementById("xpDisplay").style.color = "white";
				if(light==true) 
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
				$("#commentSpace").show();
				noLastMark = true;
				besogoMode2Solved = true;
				besogo.editor.setReviewEnabled(true);
				besogo.editor.setControlButtonLock(false);
				$("#besogo-review-button-inactive").attr("id","besogo-review-button");
				$("#besogo-next-button-inactive").attr("id","besogo-next-button");
				if(!noXP){
					sequence += "correct|";
					setCookie("score", "<?php echo $score3; ?>");
					setCookie("mode", mode);
					if(goldenTsumego)
						setCookie("type", "g");
					secondsy = seconds;
					document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
					document.cookie = "sequence="+sequence;
					xpReward = (<?php echo $t['Tsumego']['difficulty']; ?>) + <?php echo $user['User']['xp']; ?>;
					userNextlvl = <?php echo $user['User']['nextlvl']; ?>;
					ulvl = <?php echo $user['User']['level']; ?>;
					if(xpReward>userNextlvl){
						xpReward = userNextlvl;
						ulvl = ulvl + 1;
					}
					runXPBar(true);
					if(levelBar==1){
						runXPBar(true);
						runXPNumber("account-bar-xp", userXP, xpReward, 1000, ulvl);
					}
					if(levelBar==2){
						runXPBar(true);
						runXPNumber("account-bar-xp", <?php echo $user['User']['elo_rating_mode']; ?>, elo2, 1000, ulvl);
					}
					userXP = xpReward;
					userElo = Math.round(elo2);
					noXP = true;
				}
			}
			toggleBoardLock(true);
			displaySettings();
		}else{//mode 1 and 3 incorrect
			if(mode!=2){
				branch = "no";
				document.getElementById("status").style.color = "#e03c4b";
				document.getElementById("status").innerHTML = "<h2>Incorrect</h2>";
				if(light==true) 
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(183, 19, 19, 0.8), 0 6px 20px 0 rgba(183, 19, 19, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgb(225, 34, 34), 0 6px 20px 0 rgba(253, 59, 59, 0.58)");
				if(mode==3){
					document.cookie = "rank=<?php echo $mode3ScoreArray[1]; ?>";
					seconds = seconds.toFixed(1);
					secondsy = seconds*10*<?php echo $t['Tsumego']['id']; ?>;
					document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
					timeModeEnabled = false;
					$("#time-mode-countdown").css("color","#e45663");
					toggleBoardLock(true);
				}
				noLastMark = true;
				if(mode==1 && levelBar==2 && misplays==0){
					elo2 = <?php echo $user['User']['elo_rating_mode']; ?>+eloScore2;
					runXPBar(false);
					runXPNumber("account-bar-xp", <?php echo $user['User']['elo_rating_mode']; ?>, elo2, 1000, <?php echo $user['User']['level']; ?>);
					userElo = Math.round(elo2);
				}
				if(!noXP){
					if(!freePlayMode){
						misplays++;
						document.cookie = "misplay="+misplays+";path=/tsumegos/play;SameSite=none;Secure=false";
						if(mode==1 || mode==2) secondsy = seconds;
						if(mode==3) secondsy = seconds*10*<?php echo $t['Tsumego']['id']; ?>;
						document.cookie = "seconds="+secondsy+";path=/tsumegos/play;SameSite=none;Secure=false";
						hoverLocked = false;
						if(mode==1) updateHealth();
					}
					freePlayMode = true;
					if(mode==1){
						if(<?php echo $user['User']['health'] - $user['User']['damage']; ?> - misplays<0){
							if(hasPremium !== "1"){
								document.getElementById("currentElement").style.backgroundColor = "#e03c4b";
								document.getElementById("status").innerHTML = '<b style="font-size:17px">Try again tomorrow or <a style="color:#e03c4b" target="_blank" href="/users/donate">upgrade</a></b>';
								tryAgainTomorrow = true;
								toggleBoardLock(true);
							}else{
								document.getElementById("currentElement").style.backgroundColor = "#e03c4b";
								document.getElementById("status").innerHTML = "<h2>Incorrect</h2>";
							}
						}
					}
					if(goldenTsumego){
						document.cookie = "refinement=-1";
						setCookie("type", "g");
						window.location.href = "/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>";
					}
				}
			}else{//mode 2 incorrect
				elo2 = <?php echo $user['User']['elo_rating_mode']; ?>+eloScore2;
				branch = "no";
				document.getElementById("status").style.color = "#e03c4b";
				document.getElementById("status").innerHTML = "<h2>Incorrect</h2>";
				noLastMark = true;
				besogoMode2Solved = true;
				$("#besogo-next-button-inactive").attr("id","besogo-next-button");
				setCookie("mode", mode);
				if(light==true) 
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(183, 19, 19, 0.8), 0 6px 20px 0 rgba(183, 19, 19, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgb(225, 34, 34), 0 6px 20px 0 rgba(253, 59, 59, 0.58)");
				if(!noXP){
					sequence += "incorrect|";
					document.cookie = "sequence="+sequence;
					playedWrong = true;
					$("#skipButton").text("Next");
					document.cookie = "misplay="+eloScore+";path=/tsumegos/play;SameSite=none;Secure=false";
					document.cookie = "seconds="+seconds+";path=/tsumegos/play;SameSite=none;Secure=false";
					document.cookie = "transition=2;path=/tsumegos/play;SameSite=none;Secure=false";
					hoverLocked = false;
					tryAgainTomorrow = true;
					freePlayMode = true;
					if(levelBar==2){
						runXPBar(false);
						runXPNumber("account-bar-xp", <?php echo $user['User']['elo_rating_mode']; ?>, elo2, 1000, <?php echo $user['User']['level']; ?>);
					}
					userElo = Math.round(elo2);
				}
				toggleBoardLock(true);
			}
		}
		setCookie("preId", "<?php echo $t['Tsumego']['id']; ?>");
	}
	
	function toggleBoardLock(t, multipleChoice=false){
		if(tryAgainTomorrow)
			t = true;
		if(t)
			boardLockValue = 1;
		else
			boardLockValue = 0;
		if(multipleChoice)
			multipleChoiceEnabled = true;
	}
	
	function displayMessage(message='text', topic='Message', color='red'){
		$('#customText').html(message);
		$("#customAlerts").fadeIn(500);
		if(color==='red')
			$(".alertBanner").addClass("alertBannerIncorrect");
		else
			$(".alertBanner").addClass("alertBannerCorrect");
		$(".alertBanner").html(topic+"<span class=\"alertClose\">x</span>");
	}
	
	function resetParameters(isAtStart){
		tStatus = "<?php echo $t['Tsumego']['status']; ?>";
		if(tStatus=="setS2"||tStatus=="setC2") heartLoss = false;
		else heartLoss = true;
		if(isAtStart) heartLoss = false;
		if(noXP==true||freePlayMode==true||locked==true||authorProblem==true) heartLoss = false;
		if(mode==2) heartLoss = false;

		freePlayMode = false;
		if(heartLoss){
			misplays++;
			document.cookie = "misplay="+misplays+";path=/tsumegos/play;SameSite=none;Secure=false";
			setCookie("preId", "<?php echo $t['Tsumego']['id']; ?>");
			updateHealth();
		}
	}
	</script>
	<?php if($ui==2){ ?>
	<script type="text/javascript">
	(function() {
	  var options = { },
		  searchString = location.search.substring(1),
		  params = searchString.split("&"),
		  div = document.getElementById('target'),
		  i, value;

	  for (i = 0; i < params.length; i++){
			value = params[i].split("=");
			options[value.shift()] = value.join("=");
	  }
	  
	  options.panels = "tree+control";
	  <?php 
	  if($_SESSION['loggedInUser']['User']['isAdmin']>0) echo 'options.panels = "tree+control+tool+comment+file";';
	  ?> 
	  options.tsumegoPlayTool = 'auto';
	  options.realstones = true;
	  options.nowheel = true;
	  options.nokeys = true;
	  options.vChildrenEnabled = true;
	  options.multipleChoice = false;
	  options.multipleChoiceSetup = [];
	  if(mode!=3)
		options.alternativeResponse = true;
	  else
		options.alternativeResponse = false;
	  <?php
		if($virtual_children!=1)
			echo 'options.vChildrenEnabled = false;';
		if($alternative_response!=1)
			echo 'options.alternativeResponse = false;';
		if($t['Tsumego']['set_id']==208 || $t['Tsumego']['set_id']==210){
			$sStatusB = '';
			$sStatusW = '';
			if($t['Tsumego']['semeaiType'] == 1){
				$t['Tsumego']['minLib'] += $t['Tsumego']['variance'];
				$t['Tsumego']['maxLib'] -= $t['Tsumego']['variance'];
				$sStatusB = rand($t['Tsumego']['minLib'],$t['Tsumego']['maxLib']);
				$sStatusWmin = $sStatusB - $t['Tsumego']['variance'];
				$sStatusWmax = $sStatusB + $t['Tsumego']['variance'];
				$sStatusW = rand($sStatusWmin,$sStatusWmax);
			}
			else if($t['Tsumego']['semeaiType'] == 2){
				$sStatusB = rand(0,$t['Tsumego']['libertyCount']);
				$sStatusW = rand(0,$t['Tsumego']['libertyCount']);
			}else if($t['Tsumego']['semeaiType'] == 3){
				$t['Tsumego']['minLib'] += $t['Tsumego']['variance'];
				$t['Tsumego']['maxLib'] -= $t['Tsumego']['variance'];
				$sStatusB = rand($t['Tsumego']['minLib'],$t['Tsumego']['maxLib']);
				$sStatusWmin = $sStatusB - $t['Tsumego']['variance'];
				$sStatusWmax = $sStatusB + $t['Tsumego']['variance'];
				$sStatusW = rand($sStatusWmin,$sStatusWmax);
			}else if($t['Tsumego']['semeaiType'] == 4){
				$sStatusB = rand(0,$t['Tsumego']['variance']);
				$sStatusW = rand(0,$t['Tsumego']['variance']);
			}else if($t['Tsumego']['semeaiType'] == 5){
				$t['Tsumego']['minLib'] += $t['Tsumego']['variance'];
				$t['Tsumego']['maxLib'] -= $t['Tsumego']['variance'];
				$sStatusB = rand($t['Tsumego']['minLib'],$t['Tsumego']['maxLib']);
				$sStatusWmin = $sStatusB - $t['Tsumego']['variance'];
				$sStatusWmax = $sStatusB + $t['Tsumego']['variance'];
				$sStatusW = rand($sStatusWmin,$sStatusWmax);
			}else if($t['Tsumego']['semeaiType'] == 6){
				$sStatusB = rand(0,$t['Tsumego']['variance']);
				$sStatusW = rand(0,$t['Tsumego']['variance']);
			}
			echo 'options.multipleChoice = true;';
			echo 'let sStatusB = '.$sStatusB.';';
			echo 'let sStatusW = '.$sStatusW.';';
			echo 'multipleChoiceLibertiesB = sStatusB;';
			echo 'multipleChoiceLibertiesW = sStatusW;';
			echo 'multipleChoiceVariance = '.$t['Tsumego']['variance'].';';
			echo 'multipleChoiceLibertyCount = '.$t['Tsumego']['libertyCount'].';';
			echo 'let mVariance = '.$multipleChoiceTriangles.';';
			echo 'let a1 = []; let a2 = [];
			for(i=0;i<mVariance;i++){
				if(sStatusB>0) a1.push(1);
				else a1.push(0);
				if(sStatusW>0) a2.push(1);
				else a2.push(0);
				sStatusB--;
				sStatusW--;
			}
			let a3 = a1.map(value => ({ value, sort: Math.random() })).sort((a, b) => a.sort - b.sort).map(({ value }) => value);
			let a4 = a2.map(value => ({ value, sort: Math.random() })).sort((a, b) => a.sort - b.sort).map(({ value }) => value);
			let a5 = [];
			a5.push(a3);
			a5.push(a4);
			';
			echo 'options.multipleChoiceSetup = a5;';
		}else if($tv!=null){
			echo 'options.multipleChoiceCustom = "'.$tv['TsumegoVariant']['type'].'";';
			echo 'let a5 = [];
			a5.push("'.$tv['TsumegoVariant']['answer1'].'");
			a5.push("'.$tv['TsumegoVariant']['answer2'].'");
			a5.push("'.$tv['TsumegoVariant']['answer3'].'");
			a5.push("'.$tv['TsumegoVariant']['answer4'].'");
			customMultipleChoiceAnswer = '.$tv['TsumegoVariant']['numAnswer'].';
			options.multipleChoiceCustomSetup = a5;';
		}
	  ?>
	  const cornerArray = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
	  shuffledCornerArray = cornerArray.sort((a, b) => 0.5 - Math.random());
	  options.corner = shuffledCornerArray[0];
	  options.playerColor = besogoPlayerColor;
      options.rootPath = '/besogo/';
	  <?php
		if(!isset($choice[0][1])) $choice[0][1] = 'texture4';
	  ?>
	  options.theme = '<?php echo $choice[0][1]; ?>';
	  options.themeParameters = ['<?php echo $choice[0][2]; ?>', '<?php echo $choice[0][3]; ?>'];
	  options.coord = 'western';
	  options.sgf = 'https://<?php echo $_SERVER['HTTP_HOST']; ?>/placeholder.sgf';
	  options.sgf2 = "<?php echo $sgf['Sgf']['sgf']; ?>";
	  options.light = "<?php echo $_COOKIE['lightDark']; ?>";
	  if (options.theme) addStyleLink('https://<?php echo $_SERVER['HTTP_HOST']; ?>/besogo/css/board-'+options.theme+'.css');
	  if (options.height && options.width && options.resize === 'fixed')
	  {
		  div.style.height = options.height + 'px';
		  div.style.width = options.width + 'px';
	  }
    options.reviewMode = false;
    options.reviewEnabled = <?php echo $reviewEnabled ? 'true' : 'false'; ?>;
	<?php
		//if(isset($_SESSION['loggedInUser'])){ if($_SESSION['loggedInUser']['User']['id']==72){ echo 'options.reviewEnabled = true;'; }}
		if($requestSolution)
			echo 'options.reviewEnabled = true;';
	?>
	if(authorProblem)
		options.reviewEnabled = true;
	besogo.create(div, options);
    besogo.editor.setAutoPlay(true);
    besogo.editor.registerDisplayResult(displayResult);
    besogo.editor.registerShowComment(function(commentText)
    {
      $("#theComment").css("display", commentText.length == 0 ? "none" : "block");
      $("#xpDisplayDiv").css("display", commentText.length == 0 ? "block" : "none");
      $("#theComment").text(commentText);
    });
	function addStyleLink(cssURL)
	{
		var element = document.createElement('link');
		element.href = cssURL;
		element.type = 'text/css';
		element.rel = 'stylesheet';
		document.head.appendChild(element);
	}
	})();
	if(mode==2) $("#targetLockOverlay").css('top', '235px');
	<?php
		for($i=0; $i<count($dynamicCommentCoords[0]); $i++){
			echo 'besogo.editor.dynamicCommentCoords("'.$dynamicCommentCoords[0][$i].'", "'.$dynamicCommentCoords[1][$i].'");';
			echo 'besogo.editor.adjustCommentCoords();';
		}
	?>
	</script>
	<?php } ?>
	<style>
		#msg2,
		#msg4,
		#msg5 {
			display: none;
		}
		.besogo-panels{display: none;flex-basis: 50%;}
		#msgFilters{
			display:inline-block;
			margin:0 4px 8px
		}
		#showFilters, .showFilters{
			<?php 
			$displayNone = false;
			if($set['Set']['id']==1 || (count($search1)==0 && count($search2)==0 && count($search3)==0))
				$displayNone = true;	
			else if($query=='topics' && count($search2)==0 && count($search3)==0)
				$displayNone = true;
			else if($query=='difficulty' && count($search1)==0 && count($search3)==0)
				$displayNone = true;
			else if($query=='tags' && count($search1)==0 && count($search2)==0)
				$displayNone = true;
			if($displayNone)
				echo 'display:none;';
			?>
			margin:8px 4px;
		}
	</style>
