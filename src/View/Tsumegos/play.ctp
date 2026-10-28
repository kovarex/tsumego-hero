<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\TimeMode;
use App\Utility\TimeModeUtil;
use App\Utility\Trash;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Utility\TsumegoUtil;
use App\Utility\TsumegoXPAndRating;
use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * @var View $this
 * @var bool $accountBlocked
 * @var bool $alternative_response
 * @var int $amountOfOtherCollection
 * @var array $boardSelection
 * @var int $checkBSize
 * @var string $playerColor 'black' or 'white'
 * @var string $corner
 * @var bool $swapColors
 * @var float|int|string|null $difficulty
 * @var string $emptyHeart
 * @var bool $fav
 * @var string $file
 * @var int $firstRanks
 * @var string $fullHeart
 * @var string $getTitle
 * @var bool $goldenTsumego
 * @var bool $hasSgfProposal
 * @var int $i
 * @var bool $isAllowedToContribute
 * @var bool $isAllowedToTag
 * @var bool $canAddMoreTags
 * @var array|null $classroomAssignment Set by the route of a class assignment, which is the row of buttons
 * @var bool $solvedForAssignment Set with $classroomAssignment: the player solved the problem for the assignment
 * @var bool $isSandbox
 * @var bool $isSemeai
 * @var string $lightDark
 * @var int $maxHealth
 * @var bool $mistakeTrainingReplay Set by the training route: the problem was solved today already
 * @var int $multipleChoiceSquares
 * @var int $multipleChoiceTriangles
 * @var string $nextLink
 * @var bool $noSkipNextLink
 * @var bool $passEnabled
 * @var string $previousLink
 * @var string $queryTitle
 * @var bool $requestSolution
 * @var string $reviewEnabled
 * @var array $set
 * @var array $setConnection
 * @var array $setConnections
 * @var array $sgf
 * @var array $t
 * @var array $tagData
 * @var TimeMode $timeMode
 * @var TsumegoButton $tsumegoButton
 * @var TsumegoButtons|null $tsumegoButtons
 * @var TsumegoFilters $tsumegoFilters
 * @var TsumegoXPAndRating $tsumegoXPAndRating
 * @var array $tv
 * @var string $userSetsJson
 */


	$this->start('css');
	echo ViteManifest::css('besogo-css');
	$this->end();

	$this->start('script');
	echo ViteManifest::legacyScript('besogo');
	$this->end();

	if ($lightDark == 'dark')
	{
		$playGreenColor = '#0cbb0c';
	}
	else
	{
		$playGreenColor = 'green';
	}

	$swapColorWords = function (string $text): string
	{
		return preg_replace_callback(
			'/\b(Black|black|White|white)\b/',
			fn($m) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$m[1]],
			$text
		);
	};
	$displayDescription = $t['Tsumego']['description'];
	// True-color convention: descriptions already match the actual board stones.
	// When the board is inverted ($swapColors), swap Black<->White to match what the player sees.
	if ($swapColors)
		$displayDescription = $swapColorWords($displayDescription);

	// Variant answers are stored in true colors (they describe the stones on the board as authored).
	// When the board is inverted ($swapColors), swap Black<->White so the answers match what the player sees.
	$displayAnswers = $tv['TsumegoVariant'] ?? null;
	if ($displayAnswers !== null && $swapColors)
		foreach (['answer1', 'answer2', 'answer3', 'answer4'] as $answerKey)
			if (isset($displayAnswers[$answerKey]))
				$displayAnswers[$answerKey] = $swapColorWords($displayAnswers[$answerKey]);

	// Whether the problem is solved for the current mode. In time mode every
	// problem is presented fresh, so this stays false until it is solved.
	$solved = TsumegoUtil::isSolvedForCurrentMode($t);
	// Reset opens the problem again on the route it is played from, so a retry in the
	// training or in a class assignment stays there
	$classroomAssignment ??= null;
	// A problem solved before is played again for a class assignment, which counts the solve on its own
	$assignmentReplay = $classroomAssignment !== null && $solved && empty($solvedForAssignment);
	if ($classroomAssignment !== null)
		$resetLink = ClassroomAssignment::playLink((int) $classroomAssignment['id'], (int) $setConnection['SetConnection']['id']);
	else
		$resetLink = (Auth::isInMistakeTrainingMode() ? MistakeTraining::$PLAY_LINK_PREFIX : '/') . (int) $setConnection['SetConnection']['id'];
	if (!empty($t['Tsumego']['deleted']))
		echo $this->element('trash_notice', [
			'what' => 'problem',
			'deleted' => $t['Tsumego']['deleted'],
			'restoreAction' => '/trash/restore-problem/' . (int) $t['Tsumego']['id']]);
	if (!empty($set['Set']['deleted']))
		echo $this->element('trash_notice', [
			'what' => 'collection',
			'deleted' => $set['Set']['deleted'],
			'restoreAction' => '/trash/restore-set/' . (int) $set['Set']['id']]);
	?>
	<table width="100%" border="0" class="play-header-table">
	<tr>
	<td align="center" width="29%">
		<?php if (!Auth::isInMistakeTrainingMode()): ?>
		<div id="health">
			<?php
			if (Auth::isLoggedIn())
			{
				echo '<span id="health-counter" class="health-counter">' . (int)Auth::getRemainingHealth() . ' / ' . (int)$maxHealth . '</span>';
				for ($i = 0; $i < $maxHealth; $i++)
					echo '<img title="Heart" id="heart'.$i.'">';
			}
			?>
		</div>
		<?php endif; ?>
	</td>
	<td align="center" width="42%">
	<table>
	<tr>
		<td align="center">
			<div id="playTitle">
				<?php echo $classroomAssignment !== null
					? Play::renderAssignmentTitle($classroomAssignment, $tsumegoButtons, $setConnection, $set, $amountOfOtherCollection)
					: Play::renderTitle($setConnection, $set, $tsumegoFilters, $tsumegoButtons, $amountOfOtherCollection, $difficulty, $timeMode, $queryTitle, $t); ?>
				<br>
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
		else
			echo '<div id="titleDescription" class="titleDescription1">';
		echo '<a id="descriptionText">'.h($displayDescription).'</a> ';
		if (isset($t['Tsumego']['hint']) && $t['Tsumego']['hint']!='')
			echo '<span class="hint italic">('.h($t['Tsumego']['hint']).')</span>';
		if($tv!=null)
		{
			if($tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_SCORE_ESTIMATING)
			{
				$capturesLabels = 'Black captures: '.$tv['TsumegoVariant']['answer2'].' | White captures: '.$tv['TsumegoVariant']['answer3'];
				if ($swapColors)
					$capturesLabels = $swapColorWords($capturesLabels);
				echo '<br>';
				echo '<span id="scoreEstimatingLabels">'.h($capturesLabels).' | Komi: '.h($tv['TsumegoVariant']['answer1']).'</span>';
			}
		}
		if(Auth::isAdmin()) { ?>
		<a class="grey-link" id="modify-description" href="#" onclick="adminCommentOpened = !adminCommentOpened; $('.modify-description-panel').toggle(0);">(Edit)</a>
		<div class="modify-description-panel" style="display: none;">
			<form id="tsumego-edit" method="post" action="/tsumegos/edit/<?php echo $t['Tsumego']['id']; ?>">
				<input type="hidden" name="tsumego_id" value="<?php echo $t['Tsumego']['id']; ?>">
				<input type="hidden" name="redirect" value="<?php echo h($_SERVER['REQUEST_URI']); ?>">
				<input type="hidden" name="color_swapped" value="<?php echo $swapColors ? '1' : '0'; ?>">
				<table>
					<tr>
						<td><label for="description">Description:</label></td>
						<td>
							<textarea name="description" id="description" rows="3" style="width: 100%;"><?php echo h($displayDescription); ?></textarea>
							<br><small style="color: #888;">Describe the problem. <?php echo ($playerColor === 'white') ? 'White' : 'Black'; ?> is to move.</small>
						</td>
					</tr>
					<tr>
						<td><label for="hint">Hint:</label></td>
						<td><textarea name="hint" id="hint" rows="1" style="width: 100%;"><?php echo h($t['Tsumego']['hint']); ?></textarea></td>
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
						<td><input type="text" name="author" id="author" value="<?php echo h($t['Tsumego']['author']); ?>"></td>
					</tr>
					<?php if (empty($t['Tsumego']['deleted'])) { ?>
					<tr>
						<td><label for="delete">Delete:</label></td>
						<td>
							<input type="text" name="delete" id="delete" value="" placeholder="Type delete">
							<br><small style="color: #888;">Moves the problem to the trash, it can be restored for <?php echo Trash::$RETENTION_DAYS; ?> days.</small>
						</td>
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
		<?php if (!Auth::isInMistakeTrainingMode()) HeroPowers::render(); ?>
	</td>
	</tr>
	</table>
	<?php if (Auth::isInMistakeTrainingMode() && !empty($mistakeTrainingReplay)): ?>
		<div class="alert alert-info mistake-training-replay" role="status">Already solved today. This try does not count.</div>
	<?php endif; ?>
	<?php // The XP of the training shows under "Correct!", so its column stays empty, but the rank of the problem shows as in the level mode ?>
	<div<?php echo Auth::isInMistakeTrainingMode() ? ' class="play-status--training"' : ''; ?>>
		<?php $tsumegoXPAndRating->render(); ?>
	</div>
	<div align="center">
		<div id="theComment"></div>
	</div>

	<!-- BOARD -->
	<div id="target"></div>
	<div id="targetLockOverlay"></div>
	<?php if(!is_null($t['Tsumego']['semeai_type']) && $t['Tsumego']['semeai_type'] != 0 || ($tv!=null && $tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_MULTIPLE_CHOICE)){ ?>
	<div align="center">
	<br>
		<a href="<?php echo $resetLink; ?>" title="reset problem" id="besogo-next-button">Reset</a>
		<br><br>
	</div>
	<?php }else if($tv!=null && $tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_SCORE_ESTIMATING){ ?>
	<div align="center">
		<?php if($t['Tsumego']['set_id']==262){ ?>
			<br>
			<a id="submitScoreEstimatingBlackWins" href="#"><?php echo $swapColors ? 'White wins' : 'Black wins'; ?></a>
			<?php if(substr($tv['TsumegoVariant']['answer1'],-2) !== '.5') { ?>
				<a id="submitScoreEstimatingJigo" href="#">Jigo</a>
			<?php } ?>
			<a id="submitScoreEstimatingWhiteWins" href="#"><?php echo $swapColors ? 'Black wins' : 'White wins'; ?></a>
		<?php }else{ ?>
			<a href="<?php echo $resetLink; ?>" title="reset problem" id="besogo-next-button">Reset</a>
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
	<div class="problem-nav">
		<div class="problem-nav__inner">
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
					$proposalSentColor = 'color:var(--text-softer-color);';
				}
			}
			$getTitle = str_replace('&','and',$set['Set']['title']);
			$getTitle .= ' '.$set['SetConnection']['num'];
		$tagEditorProps = json_encode([
			'userId' => Auth::getUserID() ? (int) Auth::getUserID() : null,
			'isAdmin' => Auth::isAdmin(),
			'tsumegoId' => (int) $t['Tsumego']['id'],
			'isTimeMode' => Auth::isInTimeMode(),
			'problemSolved' => $solved,
			'canAddMoreTags' => $canAddMoreTags,
			'isAllowedToTag' => $isAllowedToTag,
			'isAllowedToContribute' => $isAllowedToContribute,
			'initialTags' => array_map(fn($row) => [
				'id' => (int) $row['id'],
				'name' => $row['name'],
				'isAdded' => (bool) ($row['tag_connection_id'] !== null),
				'isApproved' => (bool) ($row['approved'] ?? false),
				'isMine' => (bool) ($row['is_mine'] ?? false),
				'isHint' => (bool) ($row['hint'] ?? false),
			], $tagData),
		]);
		?>
		<div class="tag-container" align="center">
			<div
				data-tag-editor-root
				data-props="<?php echo htmlspecialchars($tagEditorProps); ?>"
			></div>
		</div>
		<br>
		<?php
			echo '<a id="openSgfLink" href="/editor/?setConnectionID='.$setConnection['SetConnection']['id'].'&sgfID='.$sgf['Sgf']['id'].(Auth::isAdmin() ? '' : '&proposal=1').'" class="selectable-text mr-5" style="'.$proposalSentColor.'">'.$makeProposal.'</a>';
			echo '<a id="showx3" class="selectable-text mr-5">Download SGF</a>';
			echo '<a id="findSimilarProblems" class="selectable-text mr-5">Find Similar Problems</a>';
			echo '<a id="showFilters" class="selectable-text">Filters<img id="greyArrowFilter" src="/img/greyArrow1.png"></a>';
			echo ' <label class="preview-toggle" title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">🔍 Preview</label>';
			echo '<br><br>';
			echo '<div class="filters-outer">
				<div id="msgFilters">
					<div class="active-tiles-container tiles-view"></div>
				</div>
			</div>';
	}
		if (count($setConnections) > 1 && Auth::getMode() != Constants::$TIME_MODE)
		{
			echo '<div class="duplicateTable">Is duplicate group:<br>';
			echo implode(', ', array_map(function ($setConnection)
			{
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
				echo '<a id="showx99" class="selectable-text mr-5">Admin-Request Solution</a>';
				echo '<a id="showx4" class="selectable-text mr-5">Admin-Download</a>';
				echo '<a id="show4" class="selectable-text mr-5">Admin-Upload<img id="greyArrow4" src="/img/greyArrow1.png"></a>';
				echo '<a id="showx6" class="selectable-text mr-5 '.$adHighlight.'">History</a>';
				echo '<a id="showx8" class="selectable-text mr-5">Rating</a>';
				echo '<a id="show5" class="selectable-text">Settings<img id="greyArrow5" src="/img/greyArrow1.png"></a>';
				$checked = fn(bool $isChecked): string => $isChecked ? 'checked="checked"' : '';
				$variantType = $tv ? (int) $tv['TsumegoVariant']['tsumego_variant_type_id'] : null;
			if(Auth::isAdmin())
			{
				echo '<div id="msg4">
						<br>
						<form action="/sgf/upload/' . $setConnection['SetConnection']['id'] . '" method="POST" enctype="multipart/form-data">
							<input id="admin-upload-button" type="file" name="adminUpload" />
							<input id="admin-upload-description" type="text" name="description" maxlength="' . Sgf::DESCRIPTION_MAX_LENGTH . '" placeholder="What changed and why (optional)" />
							<input id="admin-upload-submit" value="Submit" type="submit"/>
						</form>
					</div>
					<div id="msg5">
						<br>
						<form action="/tsumegos/settings/' . $t['Tsumego']['id'] . '" method="POST">
							<table>
								<tr>
									<td>Alternative Response Mode</td>
									<td><input type="radio" id="alternative-response-on" name="alternative_response" value="1" ' . $checked($alternative_response == 1) . '><label for="alternative-response-on">on</label></td>
									<td><input type="radio" id="alternative-response-off" name="alternative_response" value="0" ' . $checked($alternative_response != 1) . '><label for="alternative-response-off">off</label></td>
								</tr>
								<tr>
									<td>Enable passing</td>
									<td><input type="radio" id="pass-off" name="pass" value="0" ' . $checked($passEnabled != 1) . '><label for="pass-off">no</label></td>
									<td><input type="radio" id="pass-on" name="pass" value="1" ' . $checked($passEnabled == 1) . '><label for="pass-on">yes</label></td>
								</tr>';
				if($isSandbox)
					echo '<tr>
									<td>Question</td>
									<td><input type="radio" id="variant-type-none" name="variant_type" value="" ' . $checked($variantType === null) . '><label for="variant-type-none">none</label></td>
									<td><input type="radio" id="variant-type-multiple-choice" name="variant_type" value="' . TsumegoVariant::TYPE_MULTIPLE_CHOICE . '" ' . $checked($variantType === TsumegoVariant::TYPE_MULTIPLE_CHOICE) . '><label for="variant-type-multiple-choice">multiple choice</label></td>
									<td><input type="radio" id="variant-type-score-estimating" name="variant_type" value="' . TsumegoVariant::TYPE_SCORE_ESTIMATING . '" ' . $checked($variantType === TsumegoVariant::TYPE_SCORE_ESTIMATING) . '><label for="variant-type-score-estimating">score estimating</label></td>
								</tr>';
				echo '</table>
							<br>
							<input value="Submit" type="submit"/>
						</form>
					</div>
					<br><br>';
			}
			if($isSandbox && $tv!=null)
			{
				if($tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_MULTIPLE_CHOICE)
				{
						echo '<a id="showMC" class="selectable-text selectable-text--inline">Edit multiple choice
						<img id="greyArrowMC" src="/img/greyArrow1.png"></a><br><br>';
						echo '<div id="showxMC">';
						echo '<form action="/tsumegos/variant/' . $t['Tsumego']['id'] . '" method="post">';
						for ($answer = 1; $answer <= 4; $answer++)
							echo '<div class="input text"><label for="variant-answer' . $answer . '">Answer ' . $answer . ': </label><input name="answer' . $answer . '" value="' . h($tv['TsumegoVariant']['answer' . $answer]) . '" placeholder="Answer ' . $answer . '" type="text" id="variant-answer' . $answer . '"></div>';
						echo '<div class="input textfield"><label for="variant-explanation">Explanation: </label><input name="explanation" value="' . h($tv['TsumegoVariant']['explanation']) . '" placeholder="Explanation" type="text" id="variant-explanation"></div>';
						echo '<br>';
						echo '<label>Correct: </label>';
						for ($answer = 1; $answer <= 4; $answer++)
							echo '<input type="radio" name="correct_answer" id="variant-correct-answer' . $answer . '" value="' . $answer . '"' . ($tv['TsumegoVariant']['correct_answer'] == $answer ? ' checked="checked"' : '') . '><label for="variant-correct-answer' . $answer . '">' . $answer . '</label>';
						echo '<br><br>';
						echo '<div class="submit"><input type="submit" value="Submit"></div>';
						echo '</form>';
						echo '<br><br>';
						echo '</div>';
	}else{
						echo '<a id="showSE" class="selectable-text selectable-text--inline">Edit correct answer
						<img id="greyArrowSE" src="/img/greyArrow1.png"></a><br><br>';
						echo '<div id="showxSE">';
						echo '<input type="button" value="Black wins" id="besogo-se-edit-black">';
						echo '<input type="button" value="White wins" id="besogo-se-edit-white">';
						echo '<input type="button" value="-" id="besogo-se-edit-less">';
						echo '<input type="button" value="+" id="besogo-se-edit-more">';
						echo '<br><br>';
						echo '<form action="/tsumegos/variant/' . $t['Tsumego']['id'] . '" method="post">';
						echo '<table><tr><td>';
						echo '<label for="scoreEstEditField">Correct answer: </label>';
						echo '</td><td>';
						echo '<input name="winner" value="'.h($tv['TsumegoVariant']['winner']).'" placeholder="Score" id="scoreEstEditField" type="text">';
						echo '</td></tr><tr><td>';
						echo '<label for="scoreEstEditField2">Komi: </label>';
						echo '</td><td>';
						echo '<input name="answer1" value="'.h($tv['TsumegoVariant']['answer1']).'" placeholder="Komi" id="scoreEstEditField2" type="text">';
						echo '</td></tr><tr><td>';
						echo '<label for="scoreEstEditField3">Black captures: </label>';
						echo '</td><td>';
						echo '<input name="answer2" value="'.h($tv['TsumegoVariant']['answer2']).'" placeholder="Black captures" id="scoreEstEditField3" type="text">';
						echo '</td></tr><tr><td>';
						echo '<label for="scoreEstEditField4">White captures: </label>';
						echo '</td><td>';
						echo '<input name="answer3" value="'.h($tv['TsumegoVariant']['answer3']).'" placeholder="White captures" id="scoreEstEditField4" type="text">';
						echo '</td></tr></table>';
						echo '<br>';
						echo '<div class="submit"><input type="submit" value="Submit" id="scoreEstEditSubmit"></div>';
						echo '</form>';
						echo '<br>';
						echo '</div>';
	}
	}
			}else echo '<br>';
	} ?>

		<table class="sandboxTable" width="62%">
		<tr>
				<td><?php echo $this->element('TsumegoComments/section', [
						'tsumegoId' => $t['Tsumego']['id'],
						'solved' => $solved,
					]); ?></td>
					</tr>
					</table>
			</div>
		<?php
	}
		?>
	<?php if(!is_null($t['Tsumego']['semeai_type']) && $t['Tsumego']['semeai_type'] != 0 || $tv!=null&&$tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_MULTIPLE_CHOICE){ ?>
		<label>
		<input type="checkbox" class="toast__toggle" id="alertCheckbox" autocomplete="off" />
		<div class="toast" id="multipleChoiceAlerts">
		<div class="toast__banner">
		Infomation
		<span class="toast__close">x</span>
		</div>
		<span class="toast__body toast__body--left">
		<div id="multipleChoiceText"></div>
		<div class="clear1"></div>
		</span>
					</div>
		</label>
	<?php }else{ ?>
		<label>
			<input type="checkbox" class="toast__toggle" id="potionAlertCheckbox" autocomplete="off" />
			<div class="toast" id="potionAlerts">
			<div class="toast__banner" align="center">
			Hero Power
			<span class="toast__close">x</span>
		</div>
			<span class="toast__body">
	<?php
			echo '<img id="hpIcon1" src="/img/hp5.png">
			You found a potion, your hearts have been restored.<br>'
		?>
			<br class="clear1"/></span>
					</div>
		</label>
		<label>
		<input type="checkbox" class="toast__toggle" id="customAlertCheckbox" autocomplete="off" />
		<div class="toast" id="customAlerts">
		<div class="toast__banner">
		Message
		<span class="toast__close">x</span>
		</div>
		<span class="toast__body toast__body--wide">
		<div id="customText"></div>
		<div class="clear1"></div>
		</span>
		</div>
		</label>
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
		?>

<script type="text/javascript">
	var ko = false,
		lastMove = false;
	var lastHover = false,
		lastX = -1,
		lastY = -1;
	var move = 0;
	var branch = "";
	var hadMisplay = false;
	var hoverLocked = false;
	var tryAgainTomorrow = false;
	var currentHealth = <?php echo Auth::getRemainingHealth(); ?>;
	var maxHealth = <?php echo $maxHealth; ?>;
	var fullHeart = '<?php echo $fullHeart; ?>';
	var emptyHeart = '<?php echo $emptyHeart; ?>';
	var heartResetTime = new Date(new Date().setUTCHours(24,0,0,0)).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
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
	var clockStart = 0;
	var bonusSeconds = 0;
	var timeToFirstClick = null;
	var freePlayMode = false;
	var reviewEnabled = false;
	var theComment = "";
	// The effective mode the server plays by, so the board cannot disagree with it.
	var mode = <?php echo Auth::getMode(); ?>;
	var timeModeEnabled = true;
	var authorProblem = false;
	var tsumegoID = <?php echo $t['Tsumego']['id'] ?>;
	var playGreenColor = '<?php echo $playGreenColor; ?>';
	var tStatus = "<?php echo $t['Tsumego']['status']; ?>";
	var failAlreadyReported = false;
	// A problem solved before, played again for a class assignment: the solve is sent for the
	// assignment, though it changes nothing else
	var assignmentReplay = <?php echo Util::boolString($assignmentReplay); ?>;
	// The fail sent when the line was lost, which the player isn't shown until the line is over
	var unrevealedFail = null;
	window._submitResultPromise = null;

	var userXP = <?php echo Auth::getWithDefault('xp', 0); ?>;
	var previousButtonLink = "<?php echo $previousLink; ?>";
	var nextButtonLink = "<?php echo $nextLink; ?>";
	// A training try ends at the first mistake, Reset opens the problem again for the next one
	var resetButtonLink = "<?php echo $resetLink; ?>";
	var noSkipNextButtonLink = "<?php echo $noSkipNextLink; ?>";
	var timeModeTimer = (mode == 3 ? new TimeModeTimer(<?php echo $timeMode->secondsToSolve; ?>) : null);
	var setID = <?php echo $set['Set']['id'] ?>;
	var isMutable = true;
	var deleteNextMoveGroup = false;
	var file = <?php echo json_encode($file, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
	var tsumegoFileLink = "<?php echo $t['Tsumego']['id']; ?>";
	var author = <?php echo json_encode($t['Tsumego']['author'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
	var userSets = <?php echo $userSetsJson; ?>;
	var besogoMode2Solved = false;
	var disableAutoplay = false;
	var besogoNoLogin = false;
	var sprintSeconds = <?php echo Constants::$SPRINT_SECONDS; ?>;
	var problemSolved = <?php echo Util::boolString($solved); ?>;
	var playerRatingCalculationModifier = <?php echo Constants::$PLAYER_RATING_CALCULATION_MODIFIER; ?>;
	let multipleChoiceLibertiesB = 0;
	let multipleChoiceLibertiesW = 0;
	let multipleChoiceLibertyCount = <?php echo $t['Tsumego']['liberty_count']; ?>+"";
	let multipleChoiceSemeaiType = <?php echo $t['Tsumego']['semeai_type']; ?>+"";
	let multipleChoiceInsideLiberties = <?php echo $t['Tsumego']['inside_liberties']; ?>+"";
	let multipleChoiceEnabled = false;
	let hasChosen = false;
	let enableDownloads = false;
	let adminCommentOpened = false;
	let customMultipleChoiceAnswer = 0;
	var boardLockValue = 0;
	let mText = "";
	let passEnabled = <?php echo $t['Tsumego']['pass']; ?>+"";
	let besogoRotation = -1;
	let msgFilterSelected = false;
	const activeTopicTiles = [];
	const activeDifficultyTiles = [];
	const activeTagTiles = [];

	function updateCurrentNavigationButton(status)
	{
		current = document.getElementById("currentNavigationButton");
		// The navigation buttons don't exist in all of the modes
		if (!current)
			return;
		current.parentElement.parentElement.className = 'problem-nav__item problem-nav__item--' + status + ' problem-nav__item--current';
	}

	// The mistake training button of the open problem as the server stored its result:
	// the colour, the rung badge and the tooltip that explains them
	function updateMistakeTrainingButton(button, xp)
	{
		let current = document.getElementById("currentNavigationButton");
		if (!current)
			return;
		updateCurrentNavigationButton(button.status);
		let link = current.parentElement;
		let badge = link.querySelector(".problem-nav__rung");
		if (badge)
			badge.outerHTML = button.badge;
		let label = link.querySelector(".tooltip-label");
		label.className = "tooltip-label status" + button.status;
		label.textContent = button.label;
		link.querySelector(".tooltip-desc").textContent = button.description;

		// A solve says under "Correct!" what it did to the problem: its next review, or its graduation
		let status = document.getElementById("status");
		status.querySelectorAll(".mistake-training-outcome").forEach(old => old.remove());
		let outcome;
		if (button.status === 'S' && problemSolved)
		{
			outcome = document.createElement("p");
			outcome.className = "mistake-training-outcome";
			let earned = xp ? xp.review + xp.graduation : 0;
			outcome.textContent = (earned > 0 ? "+" + earned + " XP \u00b7 " : "") + button.description;
			status.appendChild(outcome);
			if (xp && xp.clear > 0)
			{
				let clear = document.createElement("p");
				clear.className = "mistake-training-outcome mistake-training-outcome--clear";
				clear.textContent = "All clear today: +" + xp.clear + " XP";
				outcome.after(clear);
			}

			// The last problem of the day is solved, so Next leads to the end of the training
			if (!nextButtonLink)
			{
				nextButtonLink = <?php echo json_encode(MistakeTraining::$QUEUE_LINK); ?>;
				let next = document.getElementById("besogo-next-button-inactive");
				if (next)
				{
					next.id = "besogo-next-button";
					next.href = nextButtonLink;
					next.title = "next problem";
				}
			}
		}
	}

	<?php
		$tsumegoXPAndRating->renderJavascript();
		if (!Auth::isInMistakeTrainingMode())
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
				echo 'activeTopicTiles.push('.json_encode($setName, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');';
		if($tsumegoFilters->query != 'difficulty')
			foreach ($tsumegoFilters->rankFilterLabels() as $rankLabel)
				echo 'activeDifficultyTiles.push('.json_encode($rankLabel, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');';
		if($tsumegoFilters->query != 'tags')
			foreach ($tsumegoFilters->tags as $tag)
				echo 'activeTagTiles.push('.json_encode($tag, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');';
		?>
	drawActiveTiles();

	function drawActiveTiles(){
		$(".active-tiles-container").html("");
		for(let i=0;i<activeTopicTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--green dropdown__tile--context" id="active-tiles-element'+i+'" onclick="removeActiveTopic('+i+')">'+activeTopicTiles[i]+'</div>');
		for(let i=0;i<activeDifficultyTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--purple dropdown__tile--context" id="active-tiles-element'+i+'" onclick="removeActiveDifficulty('+i+')">'+activeDifficultyTiles[i]+'</div>');
		for(let i=0;i<activeTagTiles.length;i++)
			$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--brown dropdown__tile--context" id="active-tiles-element'+i+'" onclick="removeActiveTag('+i+')">'+activeTagTiles[i]+'</div>');
		if(activeTopicTiles.length>0 || activeDifficultyTiles.length>0 || activeTagTiles.length>0)
			$(".active-tiles-container").append('<a class="dropdown__tile dropdown__tile--muted" id="unselect-active-tiles" href="">clear</a><div class="clearfix"></div>');
	}

	$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e){
		e.preventDefault();
		$(".active-tiles-container").html("");
		setCookie("filtered_sets", "clear");
		setCookie("filtered_ranks", "clear");
		setCookie("filtered_tags", "clear");
		window.location.reload();
	});

	function removeActiveTopic(index){
		activeTopicTiles.splice(index, 1);
		setCookie("filtered_sets", activeTopicTiles.length == 0 ? "clear" : activeTopicTiles.join("@"));
		window.location.reload();
	}

	function removeActiveDifficulty(index){
		activeDifficultyTiles.splice(index, 1);
		setCookie("filtered_ranks", activeDifficultyTiles.length == 0 ? "clear" : activeDifficultyTiles.join("@"));
		window.location.reload();
	}

	function removeActiveTag(index){
		activeTagTiles.splice(index, 1);
		setCookie("filtered_tags", activeTagTiles.length == 0 ? "clear" : activeTagTiles.join("@"));
		window.location.reload();
	}

<?php
	if($tv!=null){
	if($tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_MULTIPLE_CHOICE && $tv['TsumegoVariant']['explanation']!=""){
		echo 'mText = '.json_encode($tv['TsumegoVariant']['explanation'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).';';
	}
	}

			if(Auth::isLoggedIn()){
		echo 'var besogoUserId = '.Auth::getUserID().';';
						}else{
		echo 'besogoNoLogin = true;';
	}

	// Author mode is decided by the account link, the author string can be a legacy alias.
	$authorUserId = $t['Tsumego']['author_user_id'] ?? null;
	if ($authorUserId !== null && $authorUserId == Auth::getUserID())
		echo 'authorProblem = true;';
	if($requestSolution)
		echo 'authorProblem = true;';
	if(Auth::isInTimeMode())
		echo 'var besogoMode3Next = 0;'; // probably whatever, the id doesn't matter in time mode
	?>



	function elapsedSeconds()
	{
		return (performance.now() - clockStart) / 1000;
	}

	function startClock()
	{
		clockStart = performance.now();
		if (timeModeTimer)
			timeModeTimer.start();
	}

	// The server adds up the seconds of the tries until the problem is solved, so each result sends
	// only the time since the previous one. A try after a reset on the page would count the earlier
	// tries again otherwise (forum#149).
	function takeSecondsOfTry()
	{
		let seconds = Math.max(elapsedSeconds() - bonusSeconds, timeToFirstClick || 0, 0.01);
		clockStart = performance.now();
		bonusSeconds = 0;
		timeToFirstClick = null;
		return seconds;
	}
	$(".adminCommentPanel").hide();
	$(".tsumegoNavi-middle2").hide();
	$(".tsumegoNavi-middle2").hide();
	$(".reviewNavi").hide();
	$("#reviewButton").hide();
	$("#reviewButton2").hide();

	<?php if(Auth::isInRatingMode()) echo 'var ratingModeDifficultySelector = new RatingModeDifficultySelector();';	?>
	<?php if($tv!=null&&$tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_SCORE_ESTIMATING){ ?>
		$("#submitScoreEstimatingSE").click(function(e){
			e.preventDefault();
			if(!hasChosen){
				let correctNum = <?php echo json_encode($tv['TsumegoVariant']['winner'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
			let scoreResult = <?php echo json_encode($tv['TsumegoVariant']['winner'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
			let scoreResult = <?php echo json_encode($tv['TsumegoVariant']['winner'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
			let scoreResult = <?php echo json_encode($tv['TsumegoVariant']['winner'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
	if(Auth::isInLevelMode()){
	}elseif(Auth::isInRatingMode()){
		echo '
			$(".problem-nav").hide();
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

			if(Auth::isAdmin())
		echo '$("#show5").css("display", "inline-block");';

	echo 'var goldenTsumego = '.Util::boolString($goldenTsumego).';';

	if (!Auth::isInMistakeTrainingMode() && ($t['Tsumego']['status'] == 'F' || $t['Tsumego']['status'] == 'X')) {
		echo 'var locked=true; tryAgainTomorrow = true;';
		echo 'toggleBoardLock(true);';
	} else echo 'var locked=false;';

	if ($accountBlocked)
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
	?>

	$(document).ready(function(){
	<?php
		if($t['Tsumego']['set_id']==210)
			echo '$("#author-notice").hide();';

		if(Auth::isInTimeMode())
			echo 'notMode3 = false;';
	?>
	<?php
		if (!Auth::isInTimeMode() && !Auth::isInMistakeTrainingMode() && ($t['Tsumego']['status'] == 'F' || $t['Tsumego']['status'] == 'X')){
		echo '
				document.getElementById("status").innerHTML = \'<b class="message--locked">This problem is locked until \' + heartResetTime + \'</b>\';
				tryAgainTomorrow = true;
				document.getElementById("status").style.color = "var(--feedback-error)";
				$(".besogo-board").addClass("besogo-board-red-glow");
				var resetBtn = document.getElementById("besogo-reset-button");
				if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; }';
	}
		if($isSemeai){
			echo '
				tryAgainTomorrow = true;
				locked = true;
		';
	}

		$reviewEnabled = false;
		if($solved){
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
	?>

		$('.besogo-board').click(function(e){
			if(locked && nextButtonLink)
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
		<?php if($tv['TsumegoVariant']['tsumego_variant_type_id'] == TsumegoVariant::TYPE_MULTIPLE_CHOICE){ ?>
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
		let solutionRequest = true;
		<?php if($solved || $isSandbox) { ?>
			displaySettings();
			solutionRequest = false;
		<?php } ?>
		if(authorProblem)
			displaySettings();
		<?php if (\App\Policy\AdminPolicy::canRequestSolution(Auth::getIdentity())) {
			if (!$requestSolution) { ?>
			if(solutionRequest)
				displaySolutionRequest();
			$("#showx99").click(function(){
				window.location.href = "/tsumegos/play/"+<?php echo $t['Tsumego']['id']; ?>+"?requestSolution="+<?php echo Auth::getUserID(); ?>;
				});
		<?php }
		} ?>

		<?php if(!is_null($t['Tsumego']['semeai_type']) && $t['Tsumego']['semeai_type'] != 0 || $tv!=null){ ?>
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
			jsCreateDownloadFile(<?php echo json_encode($getTitle, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#showx4").click(function(){
			jsCreateDownloadFile(<?php echo json_encode((string)$setConnection['SetConnection']['num'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
		});
		$("#findSimilarProblems").click(function(){
			$('.loader-container').css({"display": "flex"});
			window.location.href="/tsumegos/duplicatesearch/<?php echo $setConnection['SetConnection']['id']; ?>";
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
		$("#findSimilarProblems").css("display", "inline-block");
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
		$("#showx8").attr("href", "<?php echo '/tsumegos/history/'.$setConnection['SetConnection']['id']; ?>");
		$("#showx4").css("display", "inline-block");
		$("#show4").css("display", "inline-block");
		$("#show5").css("display", "inline-block");
		$("#showx6").css("display", "inline-block");
		$("#showx8").css("display", "inline-block");
	<?php } ?>
	}

	function jsCreateDownloadFile(name){
		if(enableDownloads){
			var blob = new Blob([<?php echo json_encode($sgf['Sgf']['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>],{
				type: "sgf",
			});
			saveAs(blob, name+".sgf");
	}
	}

	function redrawHearts()
	{
		if (!document.getElementById("heart0"))
			return;
		for (var i = 0; i < maxHealth; i++)
		{
			var full = i < currentHealth;
			var el = document.getElementById("heart" + i);
			el.src = "/img/" + (full ? fullHeart : emptyHeart) + ".png";
			el.title = full ? "Heart" : "Empty Heart";
		}
		var counter = document.getElementById("health-counter");
		if (counter)
			counter.textContent = currentHealth + " / " + maxHealth;
	}
	redrawHearts();

	function rejuvenation(){
		$.ajax({
				url: '/hero/rejuvenation',
				type: 'POST',
			success: function(response) {
					currentHealth = maxHealth;
					redrawHearts();
					disableRejuvenation();
					enableIntuition();
					tryAgainTomorrow = false;
					locked = false;
					toggleBoardLock(false);
					document.getElementById("status").innerHTML = '';
					$(".besogo-board").removeClass("besogo-board-red-glow");
					var resetBtn = document.getElementById("besogo-reset-button-inactive");
					if (resetBtn) { resetBtn.id = "besogo-reset-button"; resetBtn.onclick = function() { window.location.reload(); }; }
			}
		});
	}

	$(document).keydown(function(event){
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if(mode!=2){
			if(!msg2selected && !adminCommentOpened && !besogo.editor.getReviewMode()){
				// Don't navigate if focus is on a textarea or text input
				var activeElement = document.activeElement;
				var isTextInput = activeElement && (activeElement.tagName === 'TEXTAREA' || (activeElement.tagName === 'INPUT' && activeElement.type === 'text'));
				if (!isTextInput) {
					// an empty link is a training edge with nowhere to go
					if(keycode == '37' && previousButtonLink) {
						window.location.href = previousButtonLink;
					}else if(keycode == '39' && nextButtonLink){
						window.location.href =  nextButtonLink;
					}
				}
			}
		}
	});

	<?php
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

	// Maps an internal board coordinate to its pixel position on the board SVG.
	// Matches boardDisplay.js svgPos (CELL_SIZE=88, COORD_MARGIN=75).
	function boardPixel(x) {
		var coordStyle = besogo.editor.getCoordStyle();
		var cell = 88;
		var margin = coordStyle === "none" ? 0 : 75;
		return margin + cell / 2 + (x - 1) * cell;
	}

	// Returns the coordinate labels for the path from the root to `node`.
	function pathLabels(node) {
		var size = besogo.scaleParameters.boardCoordSize || 19;
		var labels = besogo.coord.western(size, size);
		var out = [];
		while (node) {
			if (node.move) out.unshift(labels.x[node.move.x] + labels.y[node.move.y]);
			node = node.parent;
		}
		return out;
	}

	// Draws one numbered realistic stone onto a cloned board SVG, ringed in blue
	// when it is the hovered move.
	function drawNumberedStone(svg, x, y, isBlack, moveNumber, hovered) {
		var px = boardPixel(x);
		var py = boardPixel(y);
		svg.appendChild(besogo.realStone(px, py, isBlack ? -1 : 1, 0, besogo.editor.BLACK_STONES || "", besogo.editor.WHITE_STONES || ""));
		var text = besogo.svgEl("text", {
			x: px, y: py, dy: ".35ex", "text-anchor": "middle",
			"font-size": 22, "font-weight": "bold",
			fill: isBlack ? "white" : "black",
			"font-family": "Helvetica,Arial,sans-serif"
		});
		text.appendChild(document.createTextNode(String(moveNumber)));
		svg.appendChild(text);
		if (hovered)
			svg.appendChild(besogo.svgCircle(px, py, "blue", 44, 4));
	}

	// Draws the comment sequence moves as written, through to the hovered one.
	// This shows the commenters line even when it is not a path in the game tree.
	// An explicit b/w marker is honored, otherwise the colors alternate from the
	// puzzle first-move colour.
	function overlaySequenceOnBoard(svg, coords, colors, hoverIndex, firstMove, startMoveNumber) {
		var nextIsBlack = firstMove !== 1;
		for (var i = 0; i <= hoverIndex && i < coords.length; i++) {
			var xy = besogo.editor.coordLabelToXY(coords[i]);
			if (!xy) continue;
			var isBlack = colors && colors[i] ? colors[i] === "b" : nextIsBlack;
			nextIsBlack = !isBlack;
			drawNumberedStone(svg, xy.x, xy.y, isBlack, startMoveNumber + i + 1, i === hoverIndex);
		}
	}

	// Draws the commenter moves using their resolved tree nodes, with the real game
	// move numbers.
	function overlayNodesOnBoard(svg, nodes, hoverIndex) {
		for (var i = 0; i <= hoverIndex && i < nodes.length; i++) {
			var node = nodes[i];
			if (node && node.move)
				drawNumberedStone(svg, node.move.x, node.move.y, node.move.color === -1, node.moveNumber, i === hoverIndex);
		}
	}

	function showCoordPopup(coord, event, position, sequence) {
		createCoordPopup();
		var mainBoard = document.querySelector(".besogo-board svg");
		if (!mainBoard) return;

		// The preview board is the main board cloned at the previewed node: the
		// comment anchor, or for a sequence (such as R19-P19-Q19) the node right
		// before the hovered move. The current node is restored afterwards.
		var isSequence = sequence && sequence.coords && sequence.coords.length > 0;
		var restoreNode = null;
		var previewNode = null;
		// The sequence resolved along the game tree, null when it is not a tree line
		var hover = null;

		if (isSequence) {
			restoreNode = besogo.editor.getCurrent();
			var startNode = position ? besogo.editor.findNodeForPosition(position) : null;
			// Tree-first: reconstruct the line from the game tree, filling in the
			// opponents moves the commenter omitted. Fall back to drawing the moves as
			// written when the sequence does not match a tree line (e.g. a proposed
			// new line).
			var resolved = besogo.editor.resolveSequenceHover(sequence.coords, sequence.index, startNode, sequence.colors);
			if (resolved.found && resolved.beforeNode)
				hover = resolved;
			previewNode = hover ? hover.beforeNode : (startNode || besogo.editor.getRoot());
			besogo.editor.setCurrent(previewNode);
		} else {
			var anchoredNode = position ? besogo.editor.findNodeForPosition(position) : null;
			if (anchoredNode) {
				restoreNode = besogo.editor.getCurrent();
				previewNode = anchoredNode;
				besogo.editor.setCurrent(anchoredNode);
			}
			besogo.editor.displayHoverCoord(coord);
		}

		// Clear any pending hide
		if (coordPopupTimeout) {
			clearTimeout(coordPopupTimeout);
			coordPopupTimeout = null;
	}

		// Show popup near cursor (clone the board at the previewed position)
		var clone = mainBoard.cloneNode(true);
		clone.style.width = "400px";
		clone.style.height = "400px";
		coordPopupDiv.innerHTML = "";
		// Show what is being previewed. In tree mode this is the reconstructed
		// game line (with the opponents moves filled in), otherwise the commenter
		// moves as written, through to the hovered move.
		var caption = coord;
		if (isSequence) {
			var line = hover ? pathLabels(previewNode) : sequence.coords.slice(0, sequence.index + 1);
			if (hover && hover.hoverNode.move) {
				var hoverLabel = besogo.editor.coordXYToLabel(hover.hoverNode.move.x, hover.hoverNode.move.y);
				if (hoverLabel) line.push(hoverLabel);
			}
			if (line.length > 0) caption = line.join(" > ");
		}
		var label = document.createElement("div");
		label.id = "coord-popup-label";
		label.style.cssText = "font:600 14px/1.4 Helvetica,Arial,sans-serif;color:#3a2410;text-align:center;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px;";
		label.textContent = caption;
		coordPopupDiv.appendChild(label);
		// Draw the commenter moves as numbered stones so the turn numbers always
		// show: the resolved tree nodes in tree mode, otherwise the moves as written.
		if (isSequence) {
			if (hover && hover.nodes.length > 0)
				overlayNodesOnBoard(clone, hover.nodes, sequence.index);
			else
				overlaySequenceOnBoard(clone, sequence.coords, sequence.colors, sequence.index,
					besogo.editor.getRoot().firstMove || -1, previewNode.moveNumber || 0);
		} else {
			// Single coordinate: ring the point on the popup board so it matches
			// the sequence look.
			var singleXY = besogo.editor.coordLabelToXY(coord);
			if (singleXY) clone.appendChild(besogo.svgCircle(boardPixel(singleXY.x), boardPixel(singleXY.y), "blue", 44, 4));
		}
		coordPopupDiv.appendChild(clone);

		// Restore the original position after capturing the preview
		if (restoreNode) {
			besogo.editor.setCurrent(restoreNode);
			besogo.editor.displayHoverCoord(-1);
		}

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

	?>

	function addTimeForMovePlayed(multiplier = 1)
	{
		bonusSeconds += <?php echo TimeModeUtil::$SECONDS_ADDED_PER_MOVE_PLAYED; ?> * multiplier;
	}

	// The outcome values come from the App\Utility\PlayOutcome enum
	var playOutcome = {
		solvedClean: '<?php echo PlayOutcome::SolvedClean->value; ?>',
		solvedAfterMistake: '<?php echo PlayOutcome::SolvedAfterMistake->value; ?>',
		failed: '<?php echo PlayOutcome::Failed->value; ?>',
		timedOut: '<?php echo PlayOutcome::TimedOut->value; ?>',
	};

	function isSolved(outcome)
	{
		return outcome === playOutcome.solvedClean || outcome === playOutcome.solvedAfterMistake;
	}

	// xpStatus is not rendered in mistake training, the widget has nothing to animate there
	function accountWidgetShowsResults()
	{
		return typeof accountWidget !== 'undefined' && accountWidget && typeof xpStatus !== 'undefined';
	}

	function submitResult(outcome)
	{
		showResultResponse(outcome, sendResult(outcome));
	}

	// Null for a guest, whose results aren't recorded
	function sendResult(outcome)
	{
		if (besogoNoLogin)
			return null;
		// a replay for an assignment changes nothing the account bar shows
		if (accountWidgetShowsResults() && !assignmentReplay)
			accountWidget.rememberResult(isSolved(outcome));
		let data = {
			tsumego_id: tsumegoID,
			// the part of the set the problem is played from decides the XP reduction of its resets
			set_connection_id: <?php echo (int) $setConnection['SetConnection']['id']; ?>,
			seconds: takeSecondsOfTry(),
			outcome: outcome,
			// another tab may have switched the stored mode since this page was opened
			mode: mode,
		};

		let response = fetch('/tsumegos/result', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			body: JSON.stringify(data),
			// the request outlives the page, so leaving right after a lost line still counts it
			keepalive: true,
		}).then(response => response.json());
		window._submitResultPromise = response.catch(err => console.error('submitResult failed:', err));
		return response;
	}

	// Shows the player what the result changed: the account bar, the XP, a potion and achievements
	function showResultResponse(outcome, response)
	{
		if (!response)
			return;
		if (accountWidgetShowsResults())
			accountWidget.animate(isSolved(outcome));
		if (!isSolved(outcome) && typeof xpStatus !== 'undefined' && xpStatus)
			xpStatus.addFail();
		window._submitResultPromise = response.then(result => {
			if (typeof accountWidget !== 'undefined' && accountWidget)
			{
				accountWidget.rating = result.new_rating;
				accountWidget.xp = result.new_xp;
				accountWidget.level = result.new_level;
				try { accountWidget.setup(); } catch(e) { console.error('accountWidget.setup failed:', e); }
			}
			if (result.xp_gained && typeof xpStatus !== 'undefined' && xpStatus)
			{
				xpStatus.set('solved', true);
				xpStatus.update();
			}
			if (result.potion_triggered)
			{
				currentHealth = maxHealth;
				redrawHearts();
				$("#potionAlerts").fadeIn(500);
			}
			if (result.achievement_updates && result.achievement_updates.length
				&& typeof showAchievementPopup === 'function')
					result.achievement_updates.forEach(showAchievementPopup);
			// The training row says what today did with the problem, which this result changed
			if (result.mistake_training_button)
				updateMistakeTrainingButton(result.mistake_training_button, result.mistake_training_xp);
		}).catch(err => {
			console.error('submitResult failed:', err);
		});
	}

	// Called by the board the moment the line can no longer end correct. The fail is sent
	// right away, so leaving or refreshing before the line is played out doesn't escape it,
	// but the player is shown nothing (hearts, account bar) until the line is over, as
	// that would give the refutation away.
	function lineLost()
	{
		if (failAlreadyReported || problemSolved)
			return;
		failAlreadyReported = true;
		hadMisplay = true;
		unrevealedFail = { response: sendResult(playOutcome.failed) };
	}

	// Shows the fail sent when the line was lost, once the line is over
	function revealFail()
	{
		currentHealth--;
		showResultResponse(playOutcome.failed, unrevealedFail.response);
		unrevealedFail = null;
	}

	function displayResult(result)
	{
		let success = result == 'S';
		document.getElementById("status").style.color = "<?php echo $playGreenColor; ?>";
		if (timeModeTimer)
			timeModeTimer.stop();

		if (success)
		{
			if (!problemSolved)
			{
				problemSolved = true;
				window.dispatchEvent(new Event('tag-editor-solved'));
				if (typeof xpStatus !== "undefined" && xpStatus)
					xpStatus.set('solved', true);
				submitResult(hadMisplay ? playOutcome.solvedAfterMistake : playOutcome.solvedClean);
			}
			else if (assignmentReplay)
			{
				sendResult(hadMisplay ? playOutcome.solvedAfterMistake : playOutcome.solvedClean);
				assignmentReplay = false;
			}
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
				noXP = true;
			}
			else
			{//mode 2 correct
				besogoMode2Solved = true;
				noXP = true;
			}
			toggleBoardLock(true);
			displaySettings();
		}
		else //incorrect
		{
			if (unrevealedFail)
				revealFail();
			else if (!failAlreadyReported && !problemSolved)
			{
				failAlreadyReported = true;
				hadMisplay = true;
				currentHealth--;
				submitResult(playOutcome.failed);
			}
			else if (assignmentReplay)
				hadMisplay = true;
			// Don't lock board - let user keep trying
			if (mode != 2)
			{
				branch = "no";
				document.getElementById("status").style.color = "var(--feedback-error)";
				document.getElementById("status").innerHTML = "<h2>Incorrect!</h2>";
				$(".besogo-board").addClass("besogo-board-red-glow");
				if (mode==3)
				{
					timeModeEnabled = false;
					$("#time-mode-countdown").css("color","var(--feedback-error)");
					toggleBoardLock(true);
				}
				if (mode==<?php echo Constants::$MISTAKE_TRAINING_MODE; ?>)
				{
					// The board locks, but the problem stays in today's training until it is solved
					toggleBoardLock(true);
					document.getElementById("status").innerHTML = '<h2>Incorrect!</h2><p class="mistake-training-note">Press Reset to try again.</p>';
				}
				noLastMark = true;
				if (!noXP)
				{
					if(!freePlayMode)
					{
						hoverLocked = false;
						if (mode == 1)
							redrawHearts();
					}
					freePlayMode = true;
					if (mode == 1)
					{
						if (currentHealth < 0)
						{
							updateCurrentNavigationButton('F');
							document.getElementById("status").innerHTML = '<b class="message--locked">This problem is locked until ' + heartResetTime + '</b>';
							tryAgainTomorrow = true;
							toggleBoardLock(true);
							var resetBtn = document.getElementById("besogo-reset-button");
							if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; }
						}
					}
					// leaving for the plain problem page would drop the player out of the training
					if (goldenTsumego && mode != <?php echo Constants::$MISTAKE_TRAINING_MODE; ?>)
					{
						window.location.href = '/' + '<?php echo $setConnection['SetConnection']['id']; ?>';
						return;
					}
				}
			}
			else
			{//mode 2 incorrect
				branch = "no";
				document.getElementById("status").style.color = "var(--feedback-error)";
				document.getElementById("status").innerHTML = "<h2>Incorrect!</h2>";
				noLastMark = true;
				besogoMode2Solved = true;
				$(".besogo-board").addClass("besogo-board-red-glow");
				if (!noXP)
				{
					playedWrong = true;
					hoverLocked = false;
					freePlayMode = true;
				}
			}
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
			$(".toast__banner").addClass("toast__banner--error");
		else
			$(".toast__banner").addClass("toast__banner--success");
		$(".toast__banner").html(topic+"<span class=\"toast__close\">x</span>");
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
			hadMisplay = true;
			currentHealth--;
			redrawHearts();
			submitResult(playOutcome.failed);
		}
		else if (unrevealedFail)
		{
			// the line is given up before it was played out
			revealFail();
			redrawHearts();
		}
		failAlreadyReported = false;
	}
	</script>
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
		if (!is_null($t['Tsumego']['semeai_type']) && $t['Tsumego']['semeai_type'] != 0)
		{
			[$sStatusB, $sStatusW] = Util::calculateLibertyStatus(
				$multipleChoiceSquares,
				$multipleChoiceTriangles,
				$t['Tsumego']['minimum_liberties'],
				$t['Tsumego']['maximum_liberties'],
				$t['Tsumego']['liberty_count'],
				$t['Tsumego']['variance']);
			echo 'options.multipleChoice = true;';
			echo 'let sStatusB = '.$sStatusB.';';
			echo 'let sStatusW = '.$sStatusW.';';
			echo 'multipleChoiceLibertiesB = sStatusB;';
			echo 'multipleChoiceLibertiesW = sStatusW;';
			echo 'multipleChoiceLibertyCount = '.$t['Tsumego']['liberty_count'].';';
			echo 'let mVariance = '.$multipleChoiceTriangles.';';
			echo 'let a1 = []; let a2 = [];
			for(i=0; i<mVariance; i++)
			{
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
			echo 'options.multipleChoiceCustom = '.json_encode(TsumegoVariant::BOARD_TYPE_NAMES[$tv['TsumegoVariant']['tsumego_variant_type_id']], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).';';
			echo 'let a5 = [];
			a5.push('.json_encode($displayAnswers['answer1'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');
			a5.push('.json_encode($displayAnswers['answer2'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');
			a5.push('.json_encode($displayAnswers['answer3'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');
			a5.push('.json_encode($displayAnswers['answer4'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE).');
			customMultipleChoiceAnswer = '.(int)$tv['TsumegoVariant']['correct_answer'].';
			options.multipleChoiceCustomSetup = a5;';
		}
	?>
		options.corner = "<?php echo $corner; ?>";
		options.playerColor = "<?php echo $playerColor; ?>";
		options.swapColors = <?php echo $swapColors ? 'true' : 'false'; ?>;
		options.rootPath = '/besogo/';
		options.theme = '<?php echo $boardSelection['texture']; ?>';
		options.themeParameters = ['<?php echo $boardSelection['black']; ?>', '<?php echo $boardSelection['white']; ?>'];
		options.coord = 'western';
		options.sgf = '/placeholder.sgf';
		options.sgf2 = <?php echo json_encode($sgf['Sgf']['sgf'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
		options.light = <?php echo json_encode($lightDark, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
	if (tryAgainTomorrow) { var resetBtn = document.getElementById("besogo-reset-button"); if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; } }
	besogo.editor.setAutoPlay(true);
	besogo.editor.registerAddTimeForMovePlayed(addTimeForMovePlayed);
	besogo.editor.registerDisplayResult(displayResult);
	besogo.editor.registerLineLost(lineLost);
	besogo.editor.addListener(function (changes)
	{
		// the clock starts with the first move the player really plays
		if (timeToFirstClick !== null || !changes || !changes.navChange)
			return;
		let current = besogo.editor.getCurrent();
		if (current.move && current.move.color === besogo.editor.getRoot().firstMove)
			timeToFirstClick = elapsedSeconds();
	});

	startClock();
	var showComment = function(commentText)
		{
			$("#theComment").css("display", commentText.length == 0 ? "none" : "block");
			$("#theComment").text(besogo.boardInverted ? besogo.swapColorWords(commentText) : commentText);
		};
	besogo.editor.registerShowComment(showComment);
	showComment(besogo.editor.getCurrent().comment || '');

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
	</script>
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
			$displayNone = empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags);
			if ($displayNone)
				echo 'display:none;';
		?>margin: 8px 4px;
	}
	</style>
