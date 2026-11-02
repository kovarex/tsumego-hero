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
	<table width="100%" border="0" class="play-header-table">
	<tr>
	<td align="center" width="29%">
		<?php if (!Auth::isInMistakeTrainingMode()): ?>
		<div id="health">
			<?php
			if (Auth::isLoggedIn())
			{
				echo '<span id="health-counter" class="health-counter">' . (int) Auth::getRemainingHealth() . ' / ' . (int) $maxHealth . '</span>';
				for ($i = 0; $i < $maxHealth; $i++)
					echo '<img title="Heart" id="heart' . $i . '">';
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
				<?php echo $this->element('Tsumegos/play_title'); ?>
				<br>
			</div>
		</td>
	</tr>
	<tr>
		<td align="center">
		<?php
		if (Auth::isInLevelMode())
			echo '<div id="titleDescription" class="titleDescription1">';
		elseif (Auth::isInRatingMode() || Auth::isInTimeMode())
			echo '<div id="titleDescription" class="titleDescription2">';
		else
			echo '<div id="titleDescription" class="titleDescription1">';
		echo '<a id="descriptionText">' . h($displayDescription) . '</a> ';
		if (isset($tsumego->hint) && $tsumego->hint != '')
			echo '<span class="hint italic">(' . h($tsumego->hint) . ')</span>';
		?>
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
	<?php if($semeai !== null || ($tv != null && $tv->tsumego_variant_type_id == TsumegoVariantTable::TYPE_MULTIPLE_CHOICE))
	{ ?>
	<div align="center">
	<br>
		<a href="<?php echo $resetLink; ?>" title="reset problem" id="besogo-next-button">Reset</a>
		<br><br>
	</div>
	<?php } ?>
	<div id="errorMessageOuter" align="center">
		<div id="errorMessage"></div>
	</div>
	<?php
	if (Auth::isInLevelMode() || Auth::isInTimeMode())
		$naviAdjust1 = 38;
	elseif (Auth::isInRatingMode())
		$naviAdjust1 = 25;
	?>

<?php if (isset($tsumegoButtons))
{ ?>
	<div class="problem-nav">
		<div class="problem-nav__inner">
			<?php
			foreach ($tsumegoButtons as $index => $tsumegoButton)
			{
				$tsumegoButton->render();
				if($index == 0 || $index == count($tsumegoButtons) - 2)
					echo '<span class="gap10"></span>';
	}
			?>
		</div>
	</div>
	<?php } ?>
	<div align="center">
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
	<div class="tag-container" align="center">
		<div data-tag-editor-root data-props="<?php echo htmlspecialchars($tagEditorProps); ?>"></div>
	</div>
	<br>
	<?php
	//Shown on the line of Filters, so it takes no space of its own. To anyone with something in it right
	//away (an admin, a contributor, a problem in more collections), to the others once the downloads come.
	$hasDuplicates = count($setConnections) > 1 && Auth::getMode() != Constants::$TIME_MODE;
	$moreRightAway = Auth::isAdmin() || $isAllowedToContribute || $hasDuplicates;
	//The comments are in More too; what they hold shows on the toggle, once they may be read
	$commentCounts = Auth::isLoggedIn() ? TsumegoIssueTable::getCommentSectionCounts((int) $tsumego->id) : null;
	$commentsVisible = Auth::isAdmin() || $solved;
	?>
	<a id="showFilters" class="selectable-text mr-5" data-toggle="msgFilters">Filters<img class="toggle-arrow" src="/img/greyArrow1.png"></a>
	<a id="editToggle" class="selectable-text<?php echo $moreRightAway ? '' : ' play-edit-toggle--after-solve'; ?>" data-toggle="editSection">More<img class="toggle-arrow" src="/img/greyArrow1.png"><?php
	if ($commentCounts !== null && ($summary = TsumegoIssueTable::commentSummary($commentCounts)) !== '')
		echo '<span id="moreCommentSummary" class="play-more__summary"' . ($commentsVisible ? '' : ' style="display: none;"') . '>' . h($summary) . '</span>';
	?></a>
	<br><br>
	<div class="filters-outer">
		<div id="msgFilters" style="display: none;">
			<div class="active-tiles-container tiles-view"></div>
		</div>
	</div>
	<?php
	echo $this->element('Tsumegos/play_edit', ['hasDuplicates' => $hasDuplicates, 'commentCounts' => $commentCounts]);
	if (Auth::isLoggedIn())
	{
		?>
			</div>
		<?php
	}
		?>
	<?php if($semeai !== null || $tv != null && $tv->tsumego_variant_type_id == TsumegoVariantTable::TYPE_MULTIPLE_CHOICE)
	{ ?>
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
	<?php }
		else
		{ ?>
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

	<div class="loader-container">
		<div class="loader-content">
			<div class="loader-text">
				searching...
		</div>
		</div>
			</div>
		<?php
	echo '<audio><source src="/sounds/newStone.ogg"></audio>';
	echo '';
		?>

<script type="text/javascript">
	//All the page knows about the player and the problem, see Play::clientData()
	const playPage = <?php echo json_encode($playPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
	</script>
	<?php echo ViteManifest::legacyScript('play'); ?>
	<style>
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
