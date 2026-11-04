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
	<?php //The hearts, the title with the description, the hero powers ?>
	<div class="play-header">
		<div class="play-header__side">
			<?php if (!Auth::isInMistakeTrainingMode()): ?>
				<div id="health">
					<?php if (Auth::isLoggedIn()): ?>
						<span id="health-counter" class="health-counter"><?php echo (int) Auth::getRemainingHealth() . ' / ' . (int) $maxHealth; ?></span>
						<?php for ($i = 0; $i < $maxHealth; $i++): ?><img title="Heart" id="heart<?php echo $i; ?>"><?php endfor; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="play-header__title">
			<div class="play-header__stack">
				<div class="play-header__cell">
					<div id="playTitle">
						<?php echo $this->element('Tsumegos/play_title'); ?>
						<br>
					</div>
				</div>
				<div class="play-header__cell">
					<div id="titleDescription" class="<?php echo Auth::isInRatingMode() || Auth::isInTimeMode() ? 'titleDescription2' : 'titleDescription1'; ?>">
						<a id="descriptionText"><?php echo h($displayDescription); ?></a>
						<?php if (isset($tsumego->hint) && $tsumego->hint != ''): ?>
							<span class="hint italic">(<?php echo h($tsumego->hint); ?>)</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="play-header__side">
			<?php if (!Auth::isInMistakeTrainingMode()) HeroPowers::render(); ?>
		</div>
	</div>
	<?php if (Auth::isInMistakeTrainingMode() && !empty($mistakeTrainingReplay)): ?>
		<div class="alert alert-info mistake-training-replay" role="status">Already solved today. This try does not count.</div>
	<?php endif; ?>
	<?php // The XP of the training shows under "Correct!", so its column stays empty, but the rank of the problem shows as in the level mode ?>
	<div<?php echo Auth::isInMistakeTrainingMode() ? ' class="play-status--training"' : ''; ?>>
		<?php $tsumegoXPAndRating->render(); ?>
	</div>
	<div class="text-center">
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
		<div class="problem-nav">
			<div class="problem-nav__inner">
				<?php
				foreach ($tsumegoButtons as $index => $tsumegoButton)
				{
					$tsumegoButton->render();
					if ($index == 0 || $index == count($tsumegoButtons) - 2)
						echo '<span class="gap10"></span>';
				}
				?>
			</div>
		</div>
	<?php endif; ?>
	<div class="text-center">
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
	<div class="tag-container">
		<div data-tag-editor-root data-props="<?php echo htmlspecialchars($tagEditorProps); ?>"></div>
	</div>
	<br>
	<?php
	//More is on the line of Filters, so it takes no space of its own.
	$hasDuplicates = count($setConnections) > 1 && Auth::getMode() != Constants::$TIME_MODE;
	//The comments are in More too; what they hold shows on the toggle once they may be read, its place
	//kept until then so More doesn't move at the solve
	$commentCounts = Auth::isLoggedIn() ? TsumegoIssueTable::getCommentSectionCounts((int) $tsumego->id) : null;
	$commentsVisible = Auth::isAdmin() || $solved;
	?>
	<?php //Filters only when the problems are filtered ?>
	<a id="showFilters" class="play-filters-toggle selectable-text mr-5"<?php echo empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags) ? ' style="display: none;"' : ''; ?> data-toggle="msgFilters">Filters<img class="toggle-arrow" src="/img/greyArrow1.png"></a>
	<a id="editToggle" class="selectable-text" data-toggle="editSection">More<img class="toggle-arrow" src="/img/greyArrow1.png"><?php
	if ($commentCounts !== null && ($summary = TsumegoIssueTable::commentSummary($commentCounts)) !== '')
		echo '<span id="moreCommentSummary" class="play-more__summary"' . ($commentsVisible ? '' : ' style="visibility: hidden;"') . '>' . h($summary) . '</span>';
	?></a>
	<br><br>
	<div class="filters-outer">
		<div id="msgFilters" class="play-filters" style="display: none;">
			<div class="active-tiles-container tiles-view"></div>
		</div>
	</div>
	<?php echo $this->element('Tsumegos/play_edit', ['hasDuplicates' => $hasDuplicates, 'commentCounts' => $commentCounts]); ?>
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
