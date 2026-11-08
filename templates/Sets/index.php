<?php

use App\Utility\Constants;
use App\Utility\Rating;
use App\Utility\SetsSelector;
use App\Utility\TsumegoFilters;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var array $setTiles
 * @var TsumegoFilters $tsumegoFilters
 * @var array $difficultyTiles
 * @var array $tagTiles
 * @var SetsSelector $setsSelector
 * @var string $lightDark
 * @var bool $queryRefresh
 * @var string $rankFrom
 * @var string $rankTo
 * @var array $rankFilterLabels
 */
?>
	<div class="legacy-center set-search-menu query-<?php echo h($tsumegoFilters->query); ?>">
		<div class="set-buttons-left">
			<div class="set-buttons">
				<button id="topics-button" class="btn btn--filter btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Topics
					</div>
					<div class="set-button-icon" id="set-button-icon-topics">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div id="dropdown-topics" class="dropdown__menu dropdown__menu--filters legacy-left">
					<?php
						for($i = 0; $i < count($setTiles); $i++)
							echo '<div class="dropdown__tile" id="tile-topics' . $i . '">' . h($setTiles[$i]) . '</div>';
					?>
					<div class="dropdown__tile-actions">
						<div class="dropdown__tile dropdown__tile-actions__select" id="tile-topics-select-all">Select all</div>
						<?php if($tsumegoFilters->query)
						{ ?>
							<a class="dropdown__tile dropdown__tile-actions__submit" id="tile-topics-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="set-buttons">
				<button id="difficulty-button" class="btn btn--filter btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Difficulty
					</div>
					<div class="set-button-icon" id="set-button-icon-difficulty">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div id="dropdown-difficulty" class="dropdown__menu dropdown__menu--filters legacy-left">
					<?php
						for($i = 0; $i < count($difficultyTiles); $i++)
							echo '<div class="dropdown__tile dropdown__tile--difficulty" id="tile-difficulty' . $i . '" style="--rank-color:' . Rating::rankColorVar($difficultyTiles[$i]) . '">' . h($difficultyTiles[$i]) . '</div>';
					?>
					<div class="difficulty-range">
						<div class="difficulty-range__scale" id="difficulty-range-scale" title="Click to show everything up to a rank, or drag to pick a range">
							<div class="difficulty-range__scale-track">
								<div class="difficulty-range__scale-fill" id="difficulty-range-fill"></div>
							</div>
						</div>
						<div class="difficulty-range__controls">
							<span class="difficulty-range__caption">Add a range</span>
							<label class="difficulty-range__label" for="difficulty-from">from</label>
							<select id="difficulty-from" class="difficulty-range__select">
								<option value="">Any</option>
								<?php
									foreach ($difficultyTiles as $rank)
										echo '<option value="' . h($rank) . '" style="color:' . Rating::rankColorVar($rank) . '"' . ($rank === $rankFrom ? ' selected' : '') . '>' . h($rank) . '</option>';
								?>
							</select>
							<label class="difficulty-range__label" for="difficulty-to">to</label>
							<select id="difficulty-to" class="difficulty-range__select">
								<option value="">Any</option>
								<?php
									foreach ($difficultyTiles as $rank)
										echo '<option value="' . h($rank) . '" style="color:' . Rating::rankColorVar($rank) . '"' . ($rank === $rankTo ? ' selected' : '') . '>' . h($rank) . '</option>';
								?>
							</select>
						</div>
					</div>
					<div class="dropdown__tile-actions">
						<div class="dropdown__tile dropdown__tile-actions__select" id="tile-difficulty-reset">Reset</div>
						<a class="dropdown__tile dropdown__tile-actions__submit" id="tile-difficulty-submit" href="">Search</a>
					</div>
				</div>
			</div>
			<div class="set-buttons">
				<button id="tags-button" class="btn btn--filter btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Tags
					</div>
					<div class="set-button-icon" id="set-button-icon-tags">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div id="dropdown-tags" class="dropdown__menu dropdown__menu--filters legacy-left">
					<?php
						for($i = 0; $i < count($tagTiles); $i++)
							echo '<div class="dropdown__tile" id="tile-tags' . $i . '">' . h($tagTiles[$i]) . '</div>';
					?>
					<div class="dropdown__tile-actions">
						<div class="dropdown__tile dropdown__tile-actions__select" id="tile-tags-select-all">Select all</div>
						<?php if($tsumegoFilters->query)
						{ ?>
							<a class="dropdown__tile dropdown__tile-actions__submit" id="tile-tags-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<div class="set-buttons" id="problems-found">
			Problems found: <?php echo $setsSelector->problemsFound; ?>
		</div>
		<form method="post" action="/sets/changeCollectionSize">
		<div class="set-buttons-right">
			<div class="set-buttons">Collection size:
				<input id="set-size-input" type="number" value="<?php echo $tsumegoFilters->collectionSize; ?>" step="<?php echo Constants::$COLLECTION_SIZE_STEP; ?>" min="<?php echo Constants::$MIN_COLLECTION_SIZE; ?>" max="<?php echo Constants::$MAX_COLLECTION_SIZE; ?>" name="collection_size">
			</div>
			<div class="set-buttons">
			<input type="submit" id="submit-size-button" class="btn btn--filter btn-link collapsed set-search set-search__submit" value="Submit">
			</div>
		</div>
		</form>
	</div>
	<div class="active-tiles-container"></div>
	<div class="legacy-center set-index display1">
	<?php
	if($lightDark == 'light')
		$lightDarkBoxes = '4';
	else
		$lightDarkBoxes = '3';
	// The page has hundreds of cards, so it includes the card template itself: as an element, each
	// card would go through the view's events and the callbacks of every helper. The closure keeps
	// the variables of the template to it.
	$setCardFile = $this->elementFile('set_card');
	$renderCard = function (array $set, string $backgroundImage) use ($setCardFile): void {
		include $setCardFile;
	};
	foreach ($setsSelector->sets as $i => $set)
	{
		if ($lightDark == 'light')
		{
			$lightDarkBoxes = '3';
			if ($set['partition'] >= 4)
				$lightDarkBoxes = '6';
		}
		$backgroundImage = 'linear-gradient(rgba(169, 169, 169, 0.' . $lightDarkBoxes . '0), rgba(0, 0, 0, 0.' . $lightDarkBoxes . '5));';

		$renderCard([
			'id' => $set['id'],
			'name' => $set['name'],
			'amount' => $set['amount'],
			'color' => $set['color'],
			'difficulty' => $set['difficulty'] ?? null, // the collections of a tag have none
			'solved' => $set['solved_percent'],
			'partition' => $set['partition'],
		], $backgroundImage);
	} ?>
	</div>
	<?php
	//All webroot/js/SetsIndexPage.js needs to know about this request
	$setsIndexPage = [
		'query' => $tsumegoFilters->query,
		'queryRefresh' => $queryRefresh,
		'topics' => array_values(array_map(fn($set) => ['id' => (string) $set['id'], 'name' => $set['name']], $setsSelector->sets)),
		'topicTiles' => array_values($setTiles),
		'ranks' => array_values($difficultyTiles),
		'rankLabels' => array_values($rankFilterLabels),
		'tagTiles' => array_values($tagTiles),
		'filteredSets' => array_values($tsumegoFilters->sets),
		'filteredTags' => array_values($tsumegoFilters->tags),
	];
	?>
	<script>
		const setsIndexPage = <?php echo json_encode($setsIndexPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
	</script>
	<?php echo ViteManifest::legacyScript('sets-index'); ?>
