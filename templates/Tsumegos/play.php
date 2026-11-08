<?php

use App\Model\Entity\TsumegoVariant;
use App\Model\Table\TsumegoIssueTable;
use App\Model\Table\TsumegoVariantTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Play;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Utility\TsumegoXPAndRating;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var string $playerColor 'black' or 'white'
 * @var bool $swapColors
 * @var bool $hasSgfProposal
 * @var bool $isAllowedToContribute
 * @var bool $isAllowedToTag
 * @var bool $canAddMoreTags
 * @var bool $isSandbox
 * @var string $lightDark
 * @var int $maxHealth
 * @var bool $mistakeTrainingReplay Set by the training route: the problem was solved today already
 * @var \App\Model\Entity\Set $set
 * @var \App\Model\Entity\SetConnection $setConnection
 * @var array $setConnections
 * @var \App\Model\Entity\Sgf $sgf
 * @var \App\Model\Entity\Tsumego $tsumego
 * @var \App\Model\Entity\TsumegoSemeai|null $semeai The values of a semeai problem, null for any other problem
 * @var array $tagData
 * @var TsumegoButton $tsumegoButton
 * @var TsumegoButtons|null $tsumegoButtons
 * @var TsumegoFilters $tsumegoFilters
 * @var TsumegoXPAndRating $tsumegoXPAndRating
 * @var TsumegoVariant|null $tv
 * @var array{page: array<string, mixed>, problem: array<string, mixed>} $playPage What the page's script reads, see Play::clientData()
 * @var string $displayDescription The description in the colors the player sees
 * @var string $displayHint The hint in the colors the player sees
 * @var bool $solved Solved for the current mode
 * @var string $resetLink
 */


	$this->start('css');
	echo ViteManifest::css('besogo-css');
	//The texture of the board, in the head so the board is drawn with it from the first paint
	if (!empty($playPage['problem']['board']['texture']))
		echo '<link rel="stylesheet" type="text/css" href="/besogo/css/board-' . h($playPage['problem']['board']['texture']) . '.css">';
	$this->end();

	$this->start('script');
	echo ViteManifest::legacyScript('besogo');
	$this->end();

	if ($tsumego->deleted !== null)
		echo $this->element('trash_notice', [
			'what' => 'problem',
			'deleted' => $tsumego->deleted->format('Y-m-d H:i:s'),
			'restoreAction' => '/trash/restore-problem/' . (int) $tsumego->id]);
	if (!empty($set->deleted))
		echo $this->element('trash_notice', [
			'what' => 'collection',
			'deleted' => $set->deleted->format('Y-m-d H:i:s'),
			'restoreAction' => '/trash/restore-set/' . (int) $set->id]);
	?>
	<?php
	//More is on the line of Filters, so it takes no space of its own.
	$hasDuplicates = count($setConnections) > 1 && Auth::getMode() != Constants::$TIME_MODE;
	//The comments open from their own button once they may be read: what they hold shows on it,
	//its place kept until then so the buttons don't move at the solve
	$commentCounts = Auth::isLoggedIn() ? TsumegoIssueTable::getCommentSectionCounts((int) $tsumego->id) : null;
	$commentsVisible = Auth::isAdmin() || $solved;
	?>
	<?php //The board column: where the problem is, its goal and stake, the one row of messages, the board and the
	//problem buttons. Beside it on a wide screen, under it on a phone: the player's hearts, the actions and what
	//they open (hero powers, comments, the info of the problem). ?>
	<div class="play-screen">
	<?php //First in the page, so the hearts and the hero powers exist when the board's script sets them up; the grid shows it beside the board, or under it on a phone ?>
	<aside class="play-screen__panel">
		<?php if (!Auth::isInMistakeTrainingMode() && Auth::isLoggedIn()): ?>
			<?php //A heart for each one the player can have, full or empty (PlayBoard.js redrawHearts()); they overlap more the more there are, so the row fits the panel (play.css), each one on top of the one to its right ?>
			<div class="play-player">
				<div id="health" title="Hearts: <?php echo (int) Auth::getRemainingHealth() . ' of ' . (int) $maxHealth; ?>" style="--hearts: <?php echo (int) $maxHealth; ?>">
					<span class="play-player__hearts"><?php for ($i = 0; $i < $maxHealth; $i++): ?><img title="Heart" id="heart<?php echo $i; ?>" alt="" style="z-index: <?php echo $maxHealth - $i; ?>"><?php endfor; ?></span>
					<span id="health-counter" class="health-counter"><?php echo (int) Auth::getRemainingHealth() . ' / ' . (int) $maxHealth; ?></span>
				</div>
			</div>
		<?php endif; ?>
		<?php //The hero powers: a row of their own on a wide screen, behind the ⚡ button on a phone (play.css) ?>
		<?php if (!Auth::isInMistakeTrainingMode() && Auth::isLoggedIn()): ?>
			<div id="heroPowersTray" class="play-powers" style="display: none;">
				<?php HeroPowers::render(); ?>
			</div>
		<?php endif; ?>
		<?php //The actions keep their places in every state, so no button moves under the finger; on a phone the board leaves room for them under it (besogo.js keepBoardInWindowHeight()) ?>
		<div class="play-actions" data-keep-in-view="(width < 64rem)">
			<?php if (!Auth::isInMistakeTrainingMode() && Auth::isLoggedIn()): ?>
				<a id="heroPowersToggle" class="play-actions__button" href="#" data-toggle="heroPowersTray" title="Hero powers" aria-label="Hero powers">&#9889;</a>
			<?php endif; ?>
			<?php if ($commentCounts !== null): ?>
				<a id="commentsToggle" class="play-actions__button<?php echo $commentsVisible ? '' : ' play-actions__button--disabled'; ?>" href="#" data-toggle="commentsPanel"
					title="<?php echo $commentsVisible ? 'Comments' : 'The comments open once the problem is solved'; ?>" aria-label="Comments"<?php echo $commentsVisible ? '' : ' aria-disabled="true"'; ?>>&#128172;<?php
					if (($summary = TsumegoIssueTable::commentSummary($commentCounts)) !== '')
						echo '<span id="moreCommentSummary" class="play-more__summary"' . ($commentsVisible ? '' : ' style="visibility: hidden;"') . '>' . h($summary) . '</span>';
				?></a>
			<?php endif; ?>
			<a id="editToggle" class="play-actions__button" href="#" data-toggle="editSection" title="The problem: info, tags, similar problems" >More</a>
			<?php //Filters only when the problems are filtered ?>
			<a id="showFilters" class="play-actions__button play-filters-toggle"<?php echo empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags) ? ' style="display: none;"' : ''; ?> href="#" data-toggle="msgFilters">Filters</a>
		</div>
		<div class="filters-outer">
			<div id="msgFilters" class="play-filters" style="display: none;">
				<div class="active-tiles-container tiles-view"></div>
			</div>
		</div>
		<?php if ($commentCounts !== null): ?>
			<div id="commentsPanel" class="play-panel" style="display: none;">
				<?php echo $this->element('TsumegoComments/section', ['tsumegoId' => $tsumego->id, 'solved' => $solved, 'counts' => $commentCounts]); ?>
			</div>
		<?php endif; ?>
		<?php
			$tagEditorProps = json_encode([
				'userId' => Auth::getUserID() ? (int) Auth::getUserID() : null,
				'isAdmin' => Auth::isAdmin(),
				'tsumegoId' => (int) $tsumego->id,
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
		<?php //The More panel: the problem's tags first, then its info and settings ?>
		<?php echo $this->element('Tsumegos/play_edit', ['hasDuplicates' => $hasDuplicates, 'commentCounts' => $commentCounts, 'tagEditorProps' => $tagEditorProps]); ?>
	</aside>
	<div class="play-screen__main">
		<div class="play-head">
			<div id="playTitle">
				<?php echo $this->element('Tsumegos/play_title'); ?>
			</div>
			<div class="play-head__goal">
				<div id="titleDescription" class="<?php echo Auth::isInRatingMode() || Auth::isInTimeMode() ? 'titleDescription2' : 'titleDescription1'; ?>">
					<a id="descriptionText"><?php echo h($displayDescription); ?></a>
					<?php if ($displayHint !== ''): ?>
						<span id="hintText" class="hint italic">(<?php echo h($displayHint); ?>)</span>
					<?php endif; ?>
				</div>
			</div>
			<?php // The XP of the training shows under "Correct!", so it stays empty, but the rank of the problem shows as in the level mode ?>
			<div class="play-head__stake<?php echo Auth::isInMistakeTrainingMode() ? ' play-status--training' : ''; ?>">
				<?php $tsumegoXPAndRating->render(); ?>
			</div>
		</div>
		<?php if (Auth::isInMistakeTrainingMode() && !empty($mistakeTrainingReplay)): ?>
			<div class="alert alert-info mistake-training-replay" role="status">Already solved today. This try does not count.</div>
		<?php endif; ?>
		<?php //Every message of the problem has one place, right above the board: Correct, Incorrect, what a status problem adds, the comment of a move ?>
		<?php //The comment of the move played ("Correct, but what about this answer?") shares the row, so it doesn't move the board either ?>
		<div class="play-message">
			<?php $tsumegoXPAndRating->renderStatus(); ?>
			<div id="theComment"></div>
		</div>

		<!-- BOARD -->
		<script type="text/javascript">
			//All the page knows about the player and the problem, see Play::clientData()
			const playPage = <?php echo json_encode($playPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
		</script>
		<div id="target" class="play-board"></div>
		<?php //Drawn right away, so the first paint has the board; the rest of the page's code waits for the whole page ?>
		<?php echo ViteManifest::legacyScript('play-board'); ?>
		<div id="targetLockOverlay"></div>
		<?php //A question of the problem is answered with its buttons, Reset tries again ?>
		<?php if ($semeai !== null || ($tv != null && $tv->tsumego_variant_type_id == TsumegoVariantTable::TYPE_MULTIPLE_CHOICE)): ?>
			<div class="text-center">
				<br>
				<a href="<?php echo $resetLink; ?>" title="reset problem" id="besogo-next-button">Reset</a>
				<br><br>
			</div>
		<?php endif; ?>
		<div id="errorMessageOuter" class="text-center">
			<div id="errorMessage"></div>
		</div>

		<?php if (isset($tsumegoButtons)): ?>
			<?php
			//How far each button is from the open problem, so a phone shows fewer around it; the first and the last always show
			$currentIndex = null;
			foreach ($tsumegoButtons as $index => $tsumegoButton)
				if ($tsumegoButton->isCurrentlyOpened)
					$currentIndex = $index;
			?>
			<div class="problem-nav">
				<div class="problem-nav__inner">
					<?php
					foreach ($tsumegoButtons as $index => $tsumegoButton)
					{
						$isEnd = $index == 0 || $index == count($tsumegoButtons) - 1;
						$tsumegoButton->distance = $currentIndex === null || $isEnd ? 0 : abs($index - $currentIndex);
						$tsumegoButton->render();
						if ($index == 0 || $index == count($tsumegoButtons) - 2)
							echo '<span class="gap10"></span>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	</div>
	<?php //The messages over the board: the explanation of a question, a potion, a message of the page ?>
	<?php if ($semeai !== null || $tv != null && $tv->tsumego_variant_type_id == TsumegoVariantTable::TYPE_MULTIPLE_CHOICE): ?>
		<label>
			<input type="checkbox" class="toast__toggle" id="alertCheckbox" autocomplete="off" />
			<div class="toast" id="multipleChoiceAlerts">
				<div class="toast__banner">
					Information
					<span class="toast__close">x</span>
				</div>
				<span class="toast__body toast__body--left">
					<div id="multipleChoiceText"></div>
					<div class="clear1"></div>
				</span>
			</div>
		</label>
	<?php else: ?>
		<label>
			<input type="checkbox" class="toast__toggle" id="potionAlertCheckbox" autocomplete="off" />
			<div class="toast" id="potionAlerts">
				<div class="toast__banner toast__banner--center">
					Hero Power
					<span class="toast__close">x</span>
				</div>
				<span class="toast__body">
					<img id="hpIcon1" src="/img/hp5.png">
					You found a potion, your hearts have been restored.<br>
					<br class="clear1"/>
				</span>
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
	<?php endif; ?>

	<div class="loader-container">
		<div class="loader-content">
			<div class="loader-text">searching...</div>
		</div>
	</div>
	<audio><source src="/sounds/newStone.ogg"></audio>

	<?php echo ViteManifest::legacyScript('play'); ?>
