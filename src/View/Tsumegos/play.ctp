<link rel="stylesheet" type="text/css" href="/besogo/css/besogo.css">
<link rel="stylesheet" type="text/css" href="/besogo/css/board-flat.css">
<script src="/besogo/js/besogo.js"></script>
<script src="/besogo/js/transformation.js"></script>
<script src="/besogo/js/treeProblemUpdater.js?v=2"></script>
<script src="/besogo/js/nodeHashTable.js"></script>
<script src="/besogo/js/editor.js?v=2"></script>
<script src="/besogo/js/gameRoot.js?v=2"></script>
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
<?php
	$boardSize = 'large';
	if ($t['Tsumego']['author'] == 'Stepan')
		$t['Tsumego']['author'] = 'Stepan Trubitsin';
	$authorx = $t['Tsumego']['author'];
	if ($authorx == 'Joschka Zimdars')
		$authorx = 'd4rkm4tter';
	else if ($authorx == 'Jérôme Hubert')
		$authorx = 'jhubert';
	else if($authorx=='Stepan Trubitsin')
		$authorx = 'Stepan';

	if ($lightDark == 'dark')
	{
		$playGreenColor = '#0cbb0c';
		$playBlueColor = '#72a7f2';
	}
	else
	{
		$playGreenColor = 'green';
		$playBlueColor = 'blue';
	}

	if(isset($deleteProblem2))
		echo '<script type="text/javascript">window.location.href = "/sets/view/'.$t['Tsumego']['set_id'].'";</script>';
	if ($isSandbox)
	{
		$sandboxComment = '(Sandbox)';
		if (!Auth::hasPremium() && !Auth::isAdmin())
			echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	else $sandboxComment = '';

	$lv = $_COOKIE['lastVisit'] ?? '15352';
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
	else if ($tv != null)
		$pl = 0;
	else
	{
		$pl = rand(0,1);
		$plRand = true;
	}
if ($checkBSize != 19 || $t['Tsumego']['set_id'] == 239
	|| $t['Tsumego']['set_id'] == 243 || $t['Tsumego']['set_id'] == 244 || $t['Tsumego']['set_id'] == 246 || $t['Tsumego']['set_id'] == 251 || $t['Tsumego']['set_id'] == 253)
		$pl=0;
	if($pl==0)
	{
		$playerColor[0] = 'BLACK';
		$playerColor[1] = 'WHITE';
	}
	else
	{
		$playerColor[0] = 'WHITE';
		$playerColor[1] = 'BLACK';
	}
	if($pl==0)
		$descriptionColor = 'Black ';
	else
		$descriptionColor = 'White ';

	if($startingPlayer==1 && $plRand==true)
	{
		if($descriptionColor=='Black ')
			$descriptionColor = 'White ';
		else if($descriptionColor=='White ')
			$descriptionColor = 'Black ';
	}
	$t['Tsumego']['description'] = str_replace('[b]', $descriptionColor, $t['Tsumego']['description']);
	if ($nothingInRange != false)
		echo '<div align="center" style="color:red;font-weight:800;">'.$nothingInRange.'</div>';
	?>
	<table width="100%" border="0">
	<tr>
	<td align="center" width="29%">
		<div id="health">
			<?php
			if (Auth::isLoggedIn())
			{
				$maxHealth = Util::getHealthBasedOnLevel(Auth::getUser()['level']);
				$health = max(0, $maxHealth - Auth::getUser()['damage']);
				for($i = 0; $i < $health; $i++)
					echo '<img title="Heart" id="heart'.$i.'" src="/img/'.$fullHeart.'.png">';
				for($i = 0; $i < ($maxHealth - $health); $i++)
				{
					$h = $health+$i;
					echo '<img title="Empty Heart" id="heart'.$h.'" src="/img/'.$emptyHeart.'.png">';
				}
			}
			?>
		</div>
	</td>
	<td align="center" width="42%">
	<table>
	<tr>
		<td align="center">
			<div id="playTitle">
				<?php echo Play::renderTitle($setConnection, $set, $tsumegoFilters, $tsumegoButtons, $amountOfOtherCollection, $difficulty, $timeMode, $queryTitle, $t); ?>
				<br>
				<?php
				if (!isset($additionalInfo))
					$additionalInfo = ['triangle' => [], 'square' => [], 'playerNames' => [], 'lastPlayed' => [99, 99], 'mode' => 0];
				?>
			</div>
		</td>
	</tr>
	<tr>
		<td align="center">
		<?php
		if (Auth::isInLevelMode())
			echo '<div id="titleDescription" class="titleDescription1">';
		elseif (Auth::isInRatingMode()|| Auth::isInTimeMode())
			echo '<div id="titleDescription" class="titleDescription2">';
		echo '<a id="descriptionText">'.$t['Tsumego']['description'].'</a> ';
		if (isset($t['Tsumego']['hint']) && $t['Tsumego']['hint']!='')
			echo '<font color="grey" style="font-style:italic;">('.$t['Tsumego']['hint'].')</font>';
		if($tv!=null)
		{
			if($tv['TsumegoVariant']['type']=='score_estimating')
			{
				echo '<br>';
				echo 'Black captures: '.$tv['TsumegoVariant']['answer2'].' | ';
				echo 'White captures: '.$tv['TsumegoVariant']['answer3'].' | ';
				echo 'Komi: '.$tv['TsumegoVariant']['answer1'].' ';
			}
		}
		if(Auth::isAdmin()) { ?>
		<a class="grey-link" id="modify-description" href="#" onclick="adminCommentOpened = !adminCommentOpened; $('.modify-description-panel').toggle(0);">(Edit)</a>
		<div class="modify-description-panel" style="display: none;">
			<form id="tsumego-edit" method="post" action="/tsumegos/edit/<?php echo $t['Tsumego']['id']; ?>">
				<input type="hidden" name="tsumego_id" value="<?php echo $t['Tsumego']['id']; ?>">
				<input type="hidden" name="redirect" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<table>
					<tr>
						<td><label for="description">Desription:</label></td>
						<td><input type="text" name="description" id="description" value="<?php echo addslashes(str_replace($descriptionColor, '[b]', $t['Tsumego']['description'])); ?>"></td>
					</tr>
					<tr>
						<td><label for="hint">Hint:</label></td>
						<td><input type="text" name="hint" id="hint" value="<?php echo addslashes($t['Tsumego']['hint']); ?>"></td>
					</tr>
					<tr>
						<td><label for="rating">Rating:</label></td>
						<td><input type="text" name="rating" id="rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($t['Tsumego']['rating']); ?>"></td>
					</tr>
					<tr>
						<td><label for="minimum-rating">Minimum rating:</label></td>
						<td><input type="text" name="minimum-rating" id="minimum-rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($t['Tsumego']['minimum_rating']); ?>"></td>
					</tr>
					<tr>
						<td><label for="maximum-rating">Maximum rating:</label></td>
						<td><input type="text" name="maximum-rating" id="maximum-rating" value="<?php echo Rating::getReadableRankFromRatingWhenPossible($t['Tsumego']['maximum_rating']); ?>"></td>
					</tr>
					<tr>
						<td><label for="author">Author:</label></td>
						<td><input type="text" name="author" id="author" value="<?php echo $t['Tsumego']['author']; ?>"></td>
					</tr>
					<?php if ($isSandbox) { ?>
					<tr>
						<td><label for="delete">Delete:</label></td>
						<td><input type="text" name="delete" id="delete" value="" placeholder="Type delete"></td>
					</tr>
					<?php } ?>
				</table>
				<input type="submit" value="submit" id="tsumego-edit-submit">
			</form>
		</div>
		<?php } ?>
		</div>
		</td>
	</tr>
	</table>
	</td>
	<td align="center" width="29%">
		<?php HeroPowers::render(); ?>
	</td>
	</tr>
	</table>
	<?php $tsumegoXPAndRating->render(); ?>
	<div align="center">
		<div id="theComment"></div>
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
	<?php if(!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0 || ($tv!=null && $tv['TsumegoVariant']['type']=='multiple_choice')){ ?>
	<div align="center">
	<br>
		<a href="/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>" title="reset problem" id="besogo-next-button">Reset</a>
		<br><br>
	</div>
	<?php }else if($tv!=null && $tv['TsumegoVariant']['type'] == 'score_estimating'){ ?>
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
	if (Auth::isInLevelMode() || Auth::isInTimeMode()) {
		$naviAdjust1 = 38;
	} elseif (Auth::isInRatingMode()) {
		$naviAdjust1 = 25;
	}
	?>

<?php if (isset($tsumegoButtons)) { ?>
	<div class="tsumegoNavi1">
		<div class="tsumegoNavi2">
			<?php
			foreach ($tsumegoButtons as $index => $tsumegoButton) {
				$tsumegoButton->render($index, $fav);
				if($index == 0 || $index == count($tsumegoButtons) - 2)
					echo '<span class="gap10"></span>';
				$i++;
	}
			?>
		</div>
	</div>
	<?php } ?>
	<div align="center">
	<?php

		if ($firstRanks == 0)
		{
			$makeProposal = '';
			$proposalSentColor = '';
			if(Auth::isAdmin())
				$makeProposal = 'Open';
			else
			{
				if (!$hasSgfProposal)
				{
					if($isAllowedToContribute)
						$makeProposal = 'Make Proposal';
				}
				else
				{
					$makeProposal = 'Proposal sent';
					$proposalSentColor = 'color:#717171;';
				}
			}
			$getTitle = str_replace('&','and',$set['Set']['title']);
			$getTitle .= ' '.$set['SetConnection']['num'];
		?>
		<div class="tag-container" align="center">
			<div class="tag-list"></div>
			<?php if($isAllowedToContribute2){ ?>
			<div class="add-tag-list-button"><a class="add-tag-list-anchor" id="open-add-tag-menu">
			<?php if($isAllowedToContribute){ ?>
			<?php if($t['Tsumego']['set_id']!=181 && $t['Tsumego']['set_id']!=191){ ?>
				Edit tags
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
			echo '<a id="openSgfLink" href="/editor/?setConnectionID='.$setConnection['SetConnection']['id'].'&sgfID='.$sgf['Sgf']['id'].'" style="margin-right:20px;'.$proposalSentColor.'" class="selectable-text">'.$makeProposal.'</a>';
			echo '<a id="showx3" style="margin-right:20px;" class="selectable-text">Download SGF</a>';
			echo '<a id="showx7x" style="margin-right:20px;" class="selectable-text">Find Similar Problems</a>';
			echo '<a id="showFilters" class="selectable-text">Filters<img id="greyArrowFilter" src="/img/greyArrow1.png"></a>';
			echo '<br><br>';
			echo '<div class="filters-outer">
				<div id="msgFilters">
					<div class="active-tiles-container tiles-view"></div>
				</div>
			</div>';
	}
		if (count($setConnections) > 1 && Auth::getMode() != Constants::$TIME_MODE) {
        echo '<div class="duplicateTable">Is duplicate group:<br>';
		echo implode(', ', array_map(function ($setConnection) {
			return '<a href="/' . $setConnection['SetConnection']['id'] . '">'
				. $setConnection['SetConnection']['title'] . '</a>';
		}, $setConnections));
        echo '</div><br>';
	}
	if (Auth::isLoggedIn())
	{
		if ($firstRanks==0)
		{
			if($sgf['Sgf']['user_id']!=33)
				$adHighlight = 'historyLink';
			else
				$adHighlight = '';

			if(Auth::isAdmin())
			{
				echo '<a id="showx99" style="margin-right:20px;" class="selectable-text">Admin-Request Solution</a>';
				echo '<a id="showx4" style="margin-right:20px;" class="selectable-text">Admin-Download</a>';
				echo '<a id="show4" style="margin-right:20px;" class="selectable-text">Admin-Upload<img id="greyArrow4" src="/img/greyArrow1.png"></a>';
				echo '<a id="showx6" style="margin-right:20px;" class="selectable-text '.$adHighlight.'">History</a>';
				echo '<a id="showx8" style="margin-right:20px;" class="selectable-text">Rating</a>';
				echo '<a id="show5" class="selectable-text">Settings<img id="greyArrow5" src="/img/greyArrow1.png"></a>';
				if($alternative_response==1)
				{
					$arOn = 'checked="checked"';
					$arOff = '';
				}
				else
				{
					$arOn = '';
					$arOff = 'checked="checked"';
				}
				if($passEnabled==1)
				{
					$passOn = 'checked="checked"';
					$passOff = '';
				}
				else
				{
					$passOn = '';
					$passOff = 'checked="checked"';
				}
				if ($set_duplicate==-1)
				{
					$duOn = 'checked="checked"';
					$duOff = '';
				}
				else
				{
					$duOn = '';
					$duOff = 'checked="checked"';
				}
				if ($tv==null)
				{
					$multipleNo = 'checked="checked"';
					$multipleYes = '';
					$scoreEstNo = 'checked="checked"';
					$scoreEstYes = '';
				}
				else
				{
					if($tv['TsumegoVariant']['type'] == 'multiple_choice')
					{
						$multipleNo = '';
						$multipleYes = 'checked="checked"';
						$scoreEstNo = 'checked="checked"';
						$scoreEstYes = '';
					}
					else
					{
						$multipleNo = 'checked="checked"';
						$multipleYes = '';
						$scoreEstNo = '';
						$scoreEstYes = 'checked="checked"';
					}
			}
			if(Auth::isAdmin())
			{
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
									<td>Alternative Response Mode</td>
									<td><input type="radio" id="r39" name="data[Settings][r39]" value="on" '.$arOn.'><label for="r39">on</label></td>
									<td><input type="radio" id="r39" name="data[Settings][r39]" value="off" '.$arOff.'><label for="r39">off</label></td>
								</tr>
								<tr>
									<td>Enable passing</td>
									<td><input type="radio" id="r43" name="data[Settings][r43]" value="no" '.$passOff.'><label for="r43">no</label></td>
									<td><input type="radio" id="r43" name="data[Settings][r43]" value="yes" '.$passOn.'><label for="r43">yes</label></td>
								</tr>';
				if($isSandbox)
				{
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
			if($isSandbox && $tv!=null)
			{
				if($tv['TsumegoVariant']['type']=='multiple_choice')
				{
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
	} ?>

		<table class="sandboxTable" width="62%">
		<tr>
				<td><?php echo $this->element('TsumegoComments/section', [
						'issues' => $tsumegoIssues ?? [],
						'plainComments' => $tsumegoPlainComments ?? [],
						'tsumegoId' => $t['Tsumego']['id'],
						't' => $t,
					]); ?></td>
					</tr>
					</table>
			</div>
		<?php
	}
		?>
	<?php if(!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0 || $tv!=null&&$tv['TsumegoVariant']['type']=='multiple_choice'){ ?>
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
	$browser = $_SERVER['HTTP_USER_AGENT'] . "\n\n";
	echo '<audio><source src="/sounds/newStone.ogg"></audio>';
	echo '';

	/*
	§TESTING AREA§
	echo '<pre>'; print_r($levelBar); echo '</pre>';
	*/
		?>

<script type="text/javascript">
	var ko = false,
		lastMove = false;
	var lastHover = false,
		lastX = -1,
		lastY = -1;
	var moveCounter = 0;
	var move = 0;
	var branch = "";
	var misplays = 0;
	var hoverLocked = false;
	var tryAgainTomorrow = false;
	var doubleXP = false;
	var countDownDate = new Date();
	var revelationUseCount = <?php echo HeroPowers::remainingRevelationUseCount(); ?>;
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
	var xpInfo = [];
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
	var theComment = "";
	var moveHasComment = false;
	var isIncorrect = false;
	var thumbsUpSelected = false;
	var thumbsDownSelected = false;
	var thumbsUpSelected2 = false;
	var thumbsDownSelected2 = false;
	var mode = <?php echo Auth::getWithDefault('mode', 1); ?>;
	var timeModeEnabled = true;
	var timeUp = false;
	var moveTimeout = 360;
	var authorProblem = false;
	var tsumegoID = <?php echo $t['Tsumego']['id'] ?>;
	var playGreenColor = '<?php echo $playGreenColor; ?>';
	var tStatus = "<?php echo $t['Tsumego']['status']; ?>";
	var failAlreadyReported = false;

	var tcount = <?php echo $timeMode ? $timeMode->secondsToSolve : 0; ?>;
	var secondsMultiplier = <?php echo $t['Tsumego']['id'] * 7900; ?>;
	var isCorrect = false;
	var whiteMoveAfterCorrect = false;
	var whiteMoveAfterCorrectI = 0;
	var whiteMoveAfterCorrectJ = 0;
	var reviewModeActive = false;
	var ui = <?php echo $ui; ?>;
	var userXP = <?php echo Auth::getWithDefault('xp', 0); ?>;
	var previousButtonLink = "<?php echo $previousLink; ?>";
	var nextButtonLink = "<?php echo $nextLink; ?>";
	var noSkipNextButtonLink = "<?php echo $noSkipNextLink; ?>";
	var timeModeTimer = (mode == 3 ? new TimeModeTimer() : null);
	var setID = <?php echo $set['Set']['id'] ?>;
	var isMutable = true;
	var deleteNextMoveGroup = false;
	var file = "<?php echo $file; ?>";
	var clearFile = "<?php echo $set['Set']['title'].' - '.$setConnection['SetConnection']['num']; ?>";
	var tsumegoFileLink = "<?php echo $t['Tsumego']['id']; ?>";
	var requestSignature = "<?php echo $requestSignature; ?>";
	var idForSignature = "<?php echo $idForSignature; ?>";
	var idForSignature2 = "<?php echo $idForSignature2; ?>";
	var author = "<?php echo $t['Tsumego']['author']; ?>";
	var besogoPlayerColor = "black";
	var favorite = "<?php echo $isTSUMEGOinFAVORITE; ?>";
	var besogoMode2Solved = false;
	var disableAutoplay = false;
	var besogoNoLogin = false;
	var soundParameterForCorrect = false;
	var sprintSeconds = <?php echo Constants::$SPRINT_SECONDS; ?>;
	<?php $tagConnectionsEdit->renderJs(); ?>
	var problemSolved = <?php echo Util::boolString(TsumegoUtil::hasStateAllowingInspection($t)); ?>;
	var playerRatingCalculationModifier = <?php echo Constants::$PLAYER_RATING_CALCULATION_MODIFIER; ?>;
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
	var boardLockValue = 0;
	let mText = "";
	let ratingBarLock = false;
	let passEnabled = <?php echo $t['Tsumego']['pass']; ?>+"";
	let besogoRotation = -1;
	let msgFilterSelected = false;
	let hasPremium = "<?php echo Auth::hasPremium(); ?>";
	const activeTopicTiles = [];
	const activeDifficultyTiles = [];
	const activeTagTiles = [];

	function updateCurrentNavigationButton(status)
	{
		current = document.getElementById("currentNavigationButton");
		// The navigation buttons don't exist in all of the modes
		if (!current)
			return;
		current.parentElement.parentElement.className = 'status' + status + ' statusCurrent';
	}

	<?php
		$tsumegoXPAndRating->renderJavascript();
		HeroPowers::renderJavascript();
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
		if($tsumegoFilters->query != 'topics')
			foreach ($tsumegoFilters->sets as $setName)
				echo 'activeTopicTiles.push("'.$setName.'");';
		if($tsumegoFilters->query != 'difficulty')
			foreach ($tsumegoFilters->ranks as $rank)
				echo 'activeDifficultyTiles.push("'.$rank.'");';
		if($tsumegoFilters->query != 'tags')
			foreach ($tsumegoFilters->tags as $tag)
				echo 'activeTagTiles.push("'.$tag.'");';
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
		setCookie("filtered_sets", "");
		setCookie("filtered_ranks", "");
		setCookie("filtered_tags", "");
		window.location.href = "/tsumegos/play/<?php echo $t['Tsumego']['id']; ?>";
	});

<?php
	if($tv!=null){
	if($tv['TsumegoVariant']['type']=='multiple_choice' && $tv['TsumegoVariant']['explanation']!=""){
		echo 'mText = "'.$tv['TsumegoVariant']['explanation'].'";';
	}
	}

			if(Auth::isLoggedIn()){
		echo 'var besogoUserId = '.Auth::getUserID().';';
						}else{
		echo 'besogoNoLogin = true;';
	}

	if($pl==1) echo 'besogoPlayerColor = "white";';
	if (
		!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0 || $t['Tsumego']['set_id'] == 109
		|| $t['Tsumego']['set_id'] == 233 || $t['Tsumego']['set_id'] == 236
	)
	echo 'besogoPlayerColor = "black";';

	if ($authorx == Auth::getWithDefault('name', ''))
		echo 'authorProblem = true;';
	if($requestSolution)
		echo 'authorProblem = true;';
	if(Auth::isInTimeMode())
	{
		echo 'seconds = 0.0;';
		echo 'var besogoMode3Next = 0;'; // probably whatever, the id doesn't matter in time mode
	}
	?>

<?php
		if($corner=='t' || $corner=='b' || $corner=='full board')
			echo '$("#plus2").css("left", "340px");';
			?>

	<?php if(!Auth::isInTimeMode()){ ?>

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

	<?php if(Auth::isInRatingMode()) echo 'var ratingModeDifficultySelector = new RatingModeDifficultySelector();';	?>
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
		echo 'document.cookie = "rejuvenationx=2;path=/tsumegos/play;SameSite=Lax";';
		echo 'window.location = "/tsumegos/play/'.$t['Tsumego']['id'].'?potionAlert=1";';
	}
	if(Auth::isInLevelMode()){
	}elseif(Auth::isInRatingMode()){
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
	}elseif(Auth::isInTimeMode()){
		echo '$("#account-bar-user > a").css({color:"#ca6658"});';
	}

	$showComments = TsumegoUtil::hasStateAllowingInspection($t) || Auth::isAdmin();
	echo 'var showCommentSpace = ' . Util::boolString($showComments) . ';';
			if(Auth::isAdmin())
		echo '$("#show5").css("display", "inline-block");';

	echo 'var goldenTsumego = '.Util::boolString($goldenTsumego).';';

	if ($t['Tsumego']['status'] == 'F' || $t['Tsumego']['status'] == 'X') {
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'toggleBoardLock(true);';
	} else echo 'var locked=false;';

	if ($dailyMaximum)
	{
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'document.getElementById("status").innerHTML = "<h3><b>You reached the daily maximum for non-premium users.</b></h3>";
			document.getElementById("status").style.color = "#000";';
	}
	if ($suspiciousBehavior)
	{
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'document.getElementById("status").innerHTML = "<h3><b>Your account is temporarily locked.</b></h3>";
			document.getElementById("status").style.color = "red";
			toggleBoardLock(true);';
	}

	if ($t['Tsumego']['status']=='S' || $t['Tsumego']['status']=='C' || !Auth::isLoggedIn() || Auth::isInTimeMode())
		echo 'var noXP=true;';
	else
		echo 'var noXP=false;';

	if (!isset($additionalInfo))
		$additionalInfo = ['triangle' => [], 'square' => [], 'playerNames' => [], 'lastPlayed' => [99, 99]];
	?>

	$(document).ready(function(){
	<?php
		if($t['Tsumego']['set_id']==210)
			echo '$("#author-notice").hide();';
		if ($ui==1)
			echo 'document.cookie = "ui=1;path=/tsumegos/play;SameSite=Lax";';
		else if($ui==2)
			echo 'document.cookie = "ui=2;path=/tsumegos/play;SameSite=Lax";';

		if(Auth::isInTimeMode())
			echo 'notMode3 = false;';
	?>
	<?php
		if (!Auth::isInTimeMode() && ($t['Tsumego']['status'] == 'F' || $t['Tsumego']['status'] == 'X')){
		echo '
				document.getElementById("status").innerHTML = \'<b style="font-size:17px">Try again tomorrow</b>\';
				tryAgainTomorrow = true;
				document.getElementById("status").style.color = "#e03c4b";';
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

		if($t['Tsumego']['status']=='S' || $t['Tsumego']['status']=='C'){
			$reviewEnabled = true;
			echo 'reviewEnabled = true;';
	}
	if(Auth::isLoggedIn()){
					if(Auth::isAdmin()){
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

	<?php if($firstRanks==0)
		echo "tagConnectionsEdit.draw();"; ?>

	$('.tag-container').on('click', "#open-add-tag-menu", function(e)
	{
		tagConnectionsEdit.activateEdit();
		$("#open-add-tag-menu").hide();
			$(".add-tag-list").hide();
			$(".add-tag-list-popular").show();
		});

	$('.tag-container').on('click', "#open-more-tags", function(e){
		$("#open-add-tag-menu").hide();
		$(".add-tag-list").show();
			$(".add-tag-list-popular").hide();
		});

		$('#target').click(function(e){
			if(locked)
				window.location = nextButtonLink;
	});

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
		$("#msg2x").hide();
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
		<?php if(TsumegoUtil::hasStateAllowingInspection($t) || $isSandbox) { ?>
			displaySettings();
			solutionRequest = false;
		<?php } ?>
		if(authorProblem)
			displaySettings();
		<?php if (Auth::isAdmin()) {
			if (!$requestSolution) { ?>
			if(solutionRequest)
				displaySolutionRequest();
			$("#showx99").click(function(){
				window.location.href = "/tsumegos/play/"+<?php echo $t['Tsumego']['id']; ?>+"?requestSolution="+<?php echo Auth::getUserID(); ?>;
				});
		<?php }
		} ?>

		<?php if(!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0 || $tv!=null){ ?>
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
			jsCreateDownloadFile("<?php echo $setConnection['SetConnection']['num']; ?>");
		});
		$("#showx7x").click(function(){
			$('.loader-container').css({
				"display": "flex"
			});
			window.location.href="/tsumegos/duplicatesearch/<?php echo $t['Tsumego']['id']; ?>";
		});

		var mouseX;
		var mouseY;
		$(document).mousemove(function(e){
			 mouseX = e.pageX;
			 mouseY = e.pageY;
		});
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
		$("#openSgfLink").css("display", "inline-block");
		<?php if(Auth::isAdmin()){ ?>
		<?php if($t['Tsumego']['duplicate']==0 || $t['Tsumego']['duplicate']==-1){ ?>
			$("#showx6").attr("href", "<?php echo '/sgfs/view/'.$t['Tsumego']['id']; ?>");
		<?php }else{
			$duplicateOther = array();
			if($t['Tsumego']['duplicate']<=9)
				$duplicateMain = $t['Tsumego']['id'];
	else
				array_push($duplicateOther, $t['Tsumego']['id']);
      foreach ($setConnections as $setConnectionX)
			for($i=0; $i<count($setConnections); $i++){
				if($setConnectionX['id'] != $setConnection['id'])
					array_push($duplicateOther, $setConnections['SetConnection']['tsumego_id']);
	}
			$duplicateParamsUrl = '?duplicates=';
			for($i=0; $i<count($duplicateOther); $i++){
				$duplicateParamsUrl .= $duplicateOther[$i];
				if($i!=count($duplicateOther)-1)
					$duplicateParamsUrl .= '-';
	}
		?>
			$("#showx6").attr("href", "<?php echo '/sgfs/view/'.$t['Tsumego']['id']; ?>");
		<?php } ?>
		$("#showx8").attr("href", "<?php echo '/users/tsumego_rating_graph/'.$t['Tsumego']['id']; ?>");
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

	function reset()
	{
		if(!tryAgainTomorrow)
			locked = false;
		hoverLocked = false;
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
		<?php if($checkBSize!=19)
			echo 'boardSize = '.$checkBSize.';'; ?>
		var i, j;
		tStatus = "<?php echo $t['Tsumego']['status']; ?>";
		let heartLoss = !isStatusAllowingInspection(tStatus);

		if (move==0)
			heartLoss = false;
		if (noXP || freePlayMode || locked || authorProblem)
			heartLoss = false;
		if (mode==2)
			heartLoss = false;
		if (failAlreadyReported)
			heartLoss = false;
		freePlayMode = false;
		freePlayMode2 = false;
		freePlayMode2done = false;
		if (heartLoss)
		{
			misplays++;
			setCookie('misplays', 'misplays');
			updateHealth();
		}
		move = 0;

		document.getElementById("status").innerHTML = "";
		document.getElementById("theComment").style.cssText = "display:none;";
		failAlreadyReported = false;
	}

	function updateHealth()
	{
		<?php
			$m = 1;
			while($health>0)
			{
				$h = $health-1;
				echo 'if(misplays=='.$m.')document.getElementById("heart'.$h.'").src = "/img/'.$emptyHeart.'.png"; ';
				$health--;
				$m++;
			}
			?>
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

		// Scroll to board so user can see the position
		var boardElement = document.querySelector('.besogo-container') || document.getElementById('board');
		if (boardElement) {
			boardElement.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});
	}
	}

	function rejuvenation(){
		$.ajax({
				url: '/hero/rejuvenation',
				type: 'POST',
			success: function(response) {
					<?php
					$health = Util::getHealthBasedOnLevel(Auth::getWithDefault('level', 0));
					for($i = 0; $i < $health; $i++) {
						echo 'document.getElementById("heart'.$i.'").src = "/img/'.$fullHeart.'.png";';
	}
			?>
					misplays = 0;
					disableRejuvenation();
					enableIntuition();
	}
		});
	}

	function selectFav()
	{
		document.getElementById("ans2").innerHTML = "";
	}

	$(document).keydown(function(event){
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if(mode!=2){
			if(!msg2selected && !adminCommentOpened && !besogo.editor.getReviewMode()){
				// Don't navigate if focus is on a textarea or text input
				var activeElement = document.activeElement;
				var isTextInput = activeElement && (activeElement.tagName === 'TEXTAREA' || (activeElement.tagName === 'INPUT' && activeElement.type === 'text'));
				if (!isTextInput) {
					if(keycode == '37') {
						window.location.href = previousButtonLink;
					}else if(keycode == '39'){
						window.location.href =  nextButtonLink;
					}
				}
			}
		}
	});

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

	<?php
	$dynamicCommentCoords = array();
	$dynamicCommentCoords[0] = array();
	$dynamicCommentCoords[1] = array();

	// Create floating board popup for coordinate hover
			echo '
	var coordPopupBoard = null;
	var coordPopupDiv = null;
	var coordPopupTimeout = null;

	function createCoordPopup() {
		if (coordPopupDiv) return;
		coordPopupDiv = document.createElement("div");
		coordPopupDiv.id = "coord-popup-board";
		coordPopupDiv.style.cssText = "position:fixed;z-index:10000;display:none;background:#d4a76a;border:3px solid #8b5a2b;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.3);padding:8px;pointer-events:none;";
		document.body.appendChild(coordPopupDiv);
	}

	function showCoordPopup(coord, event) {
		createCoordPopup();
		var mainBoard = document.querySelector(".besogo-board svg");
		if (!mainBoard) return;

		// Always highlight on main board
		besogo.editor.displayHoverCoord(coord);

		// Clear any pending hide
		if (coordPopupTimeout) {
			clearTimeout(coordPopupTimeout);
			coordPopupTimeout = null;
	}

		// Show popup near cursor
		var clone = mainBoard.cloneNode(true);
		clone.style.width = "400px";
		clone.style.height = "400px";
		coordPopupDiv.innerHTML = "";
		coordPopupDiv.appendChild(clone);

		// Position near cursor
		var x = event.clientX + 15;
		var y = event.clientY - 200;
		if (x + 430 > window.innerWidth) x = event.clientX - 430;
		if (y < 10) y = 10;
		if (y + 430 > window.innerHeight) y = window.innerHeight - 430;

		coordPopupDiv.style.left = x + "px";
		coordPopupDiv.style.top = y + "px";
		coordPopupDiv.style.display = "block";
	}

	function hideCoordPopup() {
		if (coordPopupDiv) coordPopupDiv.style.display = "none";
		besogo.editor.displayHoverCoord(-1);
	}
		';

	if (!isset($commentCoordinates)) $commentCoordinates = [];
	$fn1 = 1;
	for($i=0; $i<count($commentCoordinates); $i++){
		$n2x = explode(' ', $commentCoordinates[$i]);
		if(count($n2x)>0){
			$fn2 = 1;
			for($j=count($n2x)-1; $j>=0; $j--){
				$n2xx = explode('-', $n2x[$j]);
				if(strlen($n2xx[0])>0 && strlen($n2xx[1])>0){
					echo 'function ccIn' . $fn1 . $fn2 . '(e){showCoordPopup("' . $n2xx[2] . '",e||window.event);}';
					echo 'function ccOut' . $fn1 . $fn2 . '(){hideCoordPopup();}';
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
				echo 'function ccIn' . $fn1 . $fn2 . '(e){showCoordPopup("' . $n2xx[2] . '",e||window.event);}';
				echo 'function ccOut' . $fn1 . $fn2 . '(){hideCoordPopup();}';
				array_push($dynamicCommentCoords[0], 'ccIn'.$fn1.$fn2);
				array_push($dynamicCommentCoords[1], $n2xx[2]);
				$fn2++;
	}
	}
	}
	$fn1++;
		?>

	function addTimeForMovePlayed(multiplier = 1)
	{
		let timeToAdd = <?php echo TimeModeUtil::$SECONDS_ADDED_PER_MOVE_PLAYED; ?> * multiplier;
		tcount += timeToAdd;
		seconds -= timeToAdd;
	}

	function displayResult(result)
	{
		let success = result == 'S';
		if (!success && failAlreadyReported)
			return;
		setCookie("secondsCheck", Math.round(Math.max(seconds, 0.01).toFixed(2) * secondsMultiplier));
		setCookie("av", <?php echo $activityValue[0]; ?>);
		document.getElementById("status").style.color = "<?php echo $playGreenColor; ?>";
		if (timeModeTimer)
			timeModeTimer.stop();

		if (accountWidget)
			accountWidget.animate(success);
		if (success)
		{
			problemSolved = true;
			tagConnectionsEdit.onProblemSolved();
			if (typeof xpStatus !== "undefined" && xpStatus)
				xpStatus.set('solved', true);
			setCookie("solvedCheck", "<?php echo $solvedCheck; ?>");
			updateCurrentNavigationButton('S');
			document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
			if(light)
				$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
			else
				$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
			besogo.editor.setReviewEnabled(true);
			besogo.editor.setControlButtonLock(false);
			noLastMark = true;
			$("#besogo-review-button-inactive").attr("id","besogo-review-button");
			$("#commentSpace").show();

			if (mode != 2)
			{//mode 1 and 3 correct
				if(mode == <?php echo Constants::$TIME_MODE; ?>)
				{
					timeModeEnabled = false;
					$("#time-mode-countdown").css("color","<?php echo $playGreenColor; ?>");
					$("#reviewButton").show();
					$("#reviewButton-inactive").hide();
				}
				if(!noXP)
				{
					if (goldenTsumego)
						setCookie("type", "g");
					noXP = true;
				}
			}
			else
			{//mode 2 correct
				besogoMode2Solved = true;
				if (!noXP)
				{
					setCookie("mode", mode);
					if(goldenTsumego)
						setCookie("type", "g");
					noXP = true;
				}
			}
			toggleBoardLock(true);
			displaySettings();
		}
		else //incorrect
		{
			failAlreadyReported = true;
			misplays++;
			// Don't lock board - let user keep trying
			if (mode != 2)
			{
				branch = "no";
				document.getElementById("status").style.color = "#e03c4b";
				document.getElementById("status").innerHTML = "<h2>Incorrect</h2>";
				if (light)
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(183, 19, 19, 0.8), 0 6px 20px 0 rgba(183, 19, 19, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgb(225, 34, 34), 0 6px 20px 0 rgba(253, 59, 59, 0.58)");
				if (mode==3)
				{
					timeModeEnabled = false;
					$("#time-mode-countdown").css("color","#e45663");
					toggleBoardLock(true);
				}
				noLastMark = true;
				if (!noXP)
				{
					if(!freePlayMode)
					{
						hoverLocked = false;
						if (mode == 1)
							updateHealth();
					}
					freePlayMode = true;
					if (mode == 1)
					{
						if(<?php echo Auth::getRemainingHealth(); ?> - misplays <= 0)
						{
							updateCurrentNavigationButton('F');
							document.getElementById("status").innerHTML = '<b style="font-size:17px">Try again tomorrow</b>';
							tryAgainTomorrow = true;
							toggleBoardLock(true);
						}
					}
					if (goldenTsumego)
						window.location.href = '/' + '<?php echo $setConnection['SetConnection']['id']; ?>';
				}
			}
			else
			{//mode 2 incorrect
				branch = "no";
				document.getElementById("status").style.color = "#e03c4b";
				document.getElementById("status").innerHTML = "<h2>Incorrect</h2>";
				noLastMark = true;
				besogoMode2Solved = true;
				setCookie("mode", mode);
				if (light)
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(183, 19, 19, 0.8), 0 6px 20px 0 rgba(183, 19, 19, 0.2)");
				else
					$(".besogo-board").css("box-shadow","0 2px 14px 0 rgb(225, 34, 34), 0 6px 20px 0 rgba(253, 59, 59, 0.58)");
				if (!noXP)
				{
					playedWrong = true;
					setCookie("transition", 2);
					hoverLocked = false;
					freePlayMode = true;
				}
			}
			setCookie("misplays", misplays);
		}
	}

	function toggleBoardLock(t, multipleChoice=false)
	{
		if (tryAgainTomorrow)
			t = true;
		if (t)
			boardLockValue = 1;
		else
			boardLockValue = 0;
		if(multipleChoice)
			multipleChoiceEnabled = true;
	}

	function displayMessage(message='text', topic='Message', color='red')
	{
		$('#customText').html(message);
		$("#customAlerts").fadeIn(500);
		if (color==='red')
			$(".alertBanner").addClass("alertBannerIncorrect");
		else
			$(".alertBanner").addClass("alertBannerCorrect");
		$(".alertBanner").html(topic+"<span class=\"alertClose\">x</span>");
	}

	function resetParameters(isAtStart)
	{
		tStatus = "<?php echo $t['Tsumego']['status']; ?>";
		if(tStatus=="S" || tStatus=="C")
			heartLoss = false;
		else
			heartLoss = true;
		if(isAtStart)
			heartLoss = false;
		if (noXP || freePlayMode || locked || authorProblem)
			heartLoss = false;
		if (failAlreadyReported)
			heartLoss = false;

		freePlayMode = false;
		if (heartLoss)
		{
			misplays++;
			if (accountWidget)
				accountWidget.animate(false);
			setCookie("secondsCheck", Math.round(Math.max(seconds, 0.01).toFixed(2) * secondsMultiplier));
			setCookie("misplays", misplays);
			updateHealth();
		}
		failAlreadyReported = false;
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
		if(Auth::isAdmin()) echo 'options.panels = "tree+control+tool+comment+file";';
					?>
		options.tsumegoPlayTool = 'auto';
		options.realstones = true;
		options.nowheel = true;
		options.nokeys = true;
		options.multipleChoice = false;
		options.multipleChoiceSetup = [];
		options.alternativeResponse = true;
		<?php
		if ($alternative_response != 1)
			echo 'options.alternativeResponse = false;';
		if (!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0)
		{
			$sStatusB = '';
			$sStatusW = '';
			if($t['Tsumego']['semeaiType'] == 1){
				$t['Tsumego']['minLib'] += $t['Tsumego']['variance'];
				$t['Tsumego']['maxLib'] -= $t['Tsumego']['variance'];
				$sStatusB = rand($t['Tsumego']['minLib'],$t['Tsumego']['maxLib']);
				$sStatusWmin = $sStatusB - $t['Tsumego']['variance'];
				$sStatusWmax = $sStatusB + $t['Tsumego']['variance'];
				$sStatusW = rand($sStatusWmin,$sStatusWmax);
				} else if ($t['Tsumego']['semeaiType'] == 2) {
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
		}
		else if($tv!=null)
		{
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
		options.theme = '<?php echo $boardSelection['texture']; ?>';
		options.themeParameters = ['<?php echo $boardSelection['black']; ?>', '<?php echo $boardSelection['white']; ?>'];
		options.coord = 'western';
		options.sgf = '/placeholder.sgf';
		options.sgf2 = "<?php echo $sgf['Sgf']['sgf']; ?>";
		options.light = "<?php echo $_COOKIE['lightDark']; ?>";
		if (options.theme) addStyleLink('/besogo/css/board-'+options.theme+'.css');
			if (options.height && options.width && options.resize === 'fixed')
			{
				div.style.height = options.height + 'px';
				div.style.width = options.width + 'px';
			}
		options.reviewMode = false;
		options.reviewEnabled = <?php echo $reviewEnabled ? 'true' : 'false'; ?>;
	<?php
		if($requestSolution)
			echo 'options.reviewEnabled = true;';
		?>
	if(authorProblem)
		options.reviewEnabled = true;
	besogo.create(div, options);
	besogo.editor.setAutoPlay(true);
	besogo.editor.registerAddTimeForMovePlayed(addTimeForMovePlayed);
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
	if (mode == 2)
		$("#targetLockOverlay").css('top', '235px');
	<?php
		for($i=0; $i<count($dynamicCommentCoords[0]); $i++)
		{
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

	.besogo-panels {
		display: none;
		flex-basis: 50%;
	}

		#msgFilters{
			display:inline-block;
			margin:0 4px 8px
	}

	#showFilters,
	.showFilters {
				<?php
			$displayNone = false;
			if ($set['Set']['id'] == 1 || (empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags)))
				$displayNone = true;
			else if($tsumegoFilters->query && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags))
				$displayNone = true;
			else if($tsumegoFilters->query == 'difficulty' && empty($tsumegoFilters->sets) && empty($tsumegoFilters->tags))
				$displayNone = true;
			else if($tsumegoFilters->query == 'tags' && empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks))
				$displayNone = true;
			if($displayNone)
				echo 'display:none;';
		?>margin: 8px 4px;
	}
	</style>
