<?php

use App\Utility\Constants;
use App\Utility\Rating;
use App\Utility\SetsSelector;
use App\Utility\TsumegoFilters;

/**
 * @var AppView $this
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
 * @var array $tagList
 */
?>
	<div align="center" class="set-search-menu query-<?php echo h($tsumegoFilters->query); ?>">
		<div class="set-buttons-left">
			<div class="set-buttons">
				<button id="topics-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Topics
					</div>
					<div class="set-button-icon" id="set-button-icon-topics">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div align="left" id="dropdown-topics" class="dropdown__menu dropdown__menu--filters">
					<?php
						for($i=0; $i<count($setTiles); $i++){
							if($setTiles[$i] != '[continuation]')
								echo '<div class="dropdown__tile" id="tile-topics'.$i.'">'.h($setTiles[$i]).'</div>';
						}
					?>
					<div class="dropdown__tile-actions">
						<div class="dropdown__tile dropdown__tile-actions__select" id="tile-topics-select-all">Select all</div>
						<?php if($tsumegoFilters->query){ ?>
							<a class="dropdown__tile dropdown__tile-actions__submit" id="tile-topics-submit" href="">Search</a>
						<?php } ?>
					</div>
				</div>
			</div>
			<div class="set-buttons">
				<button id="difficulty-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Difficulty
					</div>
					<div class="set-button-icon" id="set-button-icon-difficulty">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div align="left" id="dropdown-difficulty" class="dropdown__menu dropdown__menu--filters">
					<?php
						for($i=0; $i<count($difficultyTiles); $i++){
							if($difficultyTiles[$i] != '[continuation]')
								echo '<div class="dropdown__tile dropdown__tile--difficulty" id="tile-difficulty'.$i.'" style="--rank-color:'.Rating::rankColorVar($difficultyTiles[$i]).'">'.h($difficultyTiles[$i]).'</div>';
						}
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
				<button id="tags-button" class="btn btn-link collapsed set-search" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
					<div class="set-button-text">
							Tags
					</div>
					<div class="set-button-icon" id="set-button-icon-tags">
						<?php echo $this->element('filter_chevron'); ?>
					</div>
				</button>
				<div align="left" id="dropdown-tags" class="dropdown__menu dropdown__menu--filters">
					<?php
						for($i=0; $i<count($tagTiles); $i++){
							if($tagTiles[$i] != '[continuation]')
								echo '<div class="dropdown__tile" id="tile-tags'.$i.'">'.h($tagTiles[$i]).'</div>';
						}
					?>
					<div class="dropdown__tile-actions">
						<div class="dropdown__tile dropdown__tile-actions__select" id="tile-tags-select-all">Select all</div>
						<?php if($tsumegoFilters->query){ ?>
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
			<input type="submit" id="submit-size-button" class="btn btn-link collapsed set-search" style="width:70px;" value="Submit">
			</div>
		</div>
		</form>
	</div>
	<div class="active-tiles-container"></div>
	<div align="center" class="set-index display1">
	<?php
	if($lightDark=='light')
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
	foreach ($setsSelector->sets as $i => $set) {
		if ($lightDark == 'light')
		{
			$lightDarkBoxes = '3';
			if ($set['partition'] >= 4)
				$lightDarkBoxes = '6';
		}
		$backgroundImage = 'linear-gradient(rgba(169, 169, 169, 0.'.$lightDarkBoxes.'0), rgba(0, 0, 0, 0.'.$lightDarkBoxes.'5));';

		$renderCard([
			'id' => $set['id'],
			'name' => $set['name'],
			'amount' => $set['amount'],
			'color' => $set['color'],
			'difficulty' => $set['difficulty'],
			'solved' => $set['solved_percent'],
			'partition' => $set['partition'],
		], $backgroundImage);
	} ?>
	</div>
	<script>

		let query = <?php echo json_encode($tsumegoFilters->query, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
		let queryRefresh = <?php echo json_encode($queryRefresh, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
		let filteredSets = [];
		let filteredTags = [];
		let searchText1 = "";
		let searchText2 = "";
		let searchText3 = "";
		let initialTopicTiles = [];
		let initialTagTiles = [];
		let topicsToggle = false;
		let difficultyToggle = false;
		let tagsToggle = false;
		let tileTopicsBool = [];
		let activeTopicTiles = [];
		let allTopicTiles = [];
		let activeTopicIds = [];
		let allTopicIds = [];
		let allTopicNames = [];
		let allRanks = [];
		let activeRankTiles = [];
		let activeRankRange = null;
		let serverRankLabels = [];
		let rankDragStart = null;
		let rankDragMoved = false;
		let tileTagsBool = [];
		let activeTagTiles = [];
		let allTagTiles = [];
		let activeTagIds = [];
		let allTagIds = [];
		let allTagNames = [];
		<?php
			$jsFlags = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;
			foreach ($setsSelector->sets as $set)
			{
				echo 'allTopicIds.push("' . $set['id'] . '");';
				echo 'allTopicNames.push(' . json_encode($set['name'], $jsFlags) . ');';
			}

		echo 'function toggleTopic(index, name, e) {
			e.stopPropagation();
			if(!tileTopicsBool[index]){
				handleTiles(name, "topics", true);
				activeTopicTiles.push(name);
				if(query === "topics") {
					for(let i=0;i<allTopicNames.length;i++)
						if(allTopicNames[i] === name)
							activeTopicIds.push(allTopicIds[i]);
						}
					}else{
						handleTiles(name, "topics", false);
						let newActiveTopicTiles = [];
						for(let i=0;i<activeTopicTiles.length;i++){
							if(activeTopicTiles[i] !== name)
								newActiveTopicTiles.push(activeTopicTiles[i]);
						}
						activeTopicTiles = newActiveTopicTiles;
						if(query === "topics"){
							for(let i=0;i<allTopicNames.length;i++){
								if(allTopicNames[i] === name){
									unselectTopic(allTopicIds[i]);
									break;
								}
							}
						}
					}
					drawActiveCollections();
					drawActiveTiles();
				};';

		echo 'function toggleTag(index, name, e) {
					e.stopPropagation();
					if(!tileTagsBool[index]){
						handleTiles(name, "tags", true);
						activeTagTiles.push(name);
						if(query === "tags"){
							for(let i=0;i<allTagNames.length;i++)
								if(allTagNames[i] === name)
									activeTagIds.push(allTagIds[i]);
						}
					}else{
						handleTiles(name, "tags", false);
						let newActiveTagTiles = [];
						for(let i=0;i<activeTagTiles.length;i++){
							if(activeTagTiles[i] !== name)
								newActiveTagTiles.push(activeTagTiles[i]);
						}
						activeTagTiles = newActiveTagTiles;
						if(query === "tags"){
							for(let i=0;i<allTagNames.length;i++){
								if(allTagNames[i] === name){
									unselectTag(allTagIds[i]);
									break;
								}
							}
						}
					}
					drawActiveCollections();
					drawActiveTiles();
				};';

			foreach ($setTiles as $index => $setName) {
				$jsName = json_encode($setName, $jsFlags);
				echo 'allTopicTiles.push('.$jsName.');';
				echo 'tileTopicsBool.push(false);';
				echo '$("#tile-topics'.$index.'").click(function(e){ toggleTopic('.$index.', '.$jsName.', e); });';
			}

			echo 'allRanks = ' . json_encode($difficultyTiles, $jsFlags) . ';';
			echo 'serverRankLabels = ' . json_encode(array_values($rankFilterLabels), $jsFlags) . ';';

			foreach ($difficultyTiles as $index => $rankName) {
				$jsRankName = json_encode($rankName, $jsFlags);
				echo '$("#tile-difficulty'.$index.'").click(function(e){ toggleRank('.$index.', '.$jsRankName.', e); });';
			}

			foreach ($tagList as $tag) {
				echo 'allTagIds.push("'.$tag['id'].'");';
				echo 'allTagNames.push('.json_encode($tag['name'], $jsFlags).');';
			}

			foreach ($tagTiles as $index => $tagName) {
				$jsTagName = json_encode($tagName, $jsFlags);
				echo 'allTagTiles.push('.$jsTagName.');';
				echo 'tileTagsBool.push(false);';
				echo '$("#tile-tags'.$index.'").click(function(e){ toggleTag('.$index.', '.$jsTagName.', e); });';
			}

			foreach ($tsumegoFilters->sets as $set) {
				$jsSet = json_encode($set, $jsFlags);
				echo 'filteredSets.push('.$jsSet.');';
				echo 'activeTopicTiles.push('.$jsSet.');';
				echo 'handleTiles('.$jsSet.', "topics", true);';
			}
			foreach ($tsumegoFilters->tags as $tag) {
				$jsTag = json_encode($tag, $jsFlags);
				echo 'filteredTags.push('.$jsTag.');';
				echo 'activeTagTiles.push('.$jsTag.');';
				echo 'handleTiles('.$jsTag.', "tags", true);';
			}
		?>
		$(document).ready(function(){
			setCookie("query", query);
			$("#tile-topics-select-all").html(selectAllTopics ? "Unselect all" : "Select all");
			$("#tile-tags-select-all").html(selectAllTags ? "Unselect all" : "Select all");
			initRankFilter();
			colorRankSelects();
			drawActiveCollections();
			drawActiveTiles(true);
		});
		function handleTiles(name, query, select){
			let color = "var(--surface-translucent)";
			if(select){
				if(query === "topics"){
					color = "var(--color-tile-green)";
				}else if(query === "tags"){
					color = "var(--color-tile-brown)";
				}
			}
			let position = -1;
			if(query === "topics"){
				for(let i=0;i<allTopicTiles.length;i++)
					if(name === allTopicTiles[i])
						position = i;
				$("#tile-"+query+position).css("background", color);
				tileTopicsBool[position] = select;
			}else if(query === "tags"){
				for(let i=0;i<allTagTiles.length;i++)
					if(name === allTagTiles[i])
						position = i;
				$("#tile-"+query+position).css("background", color);
				tileTagsBool[position] = !tileTagsBool[position];
			}
		}
		function drawActiveCollections(){
			<?php if($tsumegoFilters->query == 'topics'){ ?>
				if(activeTopicIds.length<1){
					for(let i=0;i<allTopicIds.length;i++)
						$(".topic-box"+allTopicIds[i]).css("display", "grid");
				}else{
					for(let i=0;i<allTopicIds.length;i++)
						if(!activeTopicIds.includes(allTopicIds[i]))
							$(".topic-box"+allTopicIds[i]).css("display", "none");
					let pos = [];
					let posX = [];
					for(let i=0;i<activeTopicIds.length;i++){
						for(let j=0;j<allTopicIds.length;j++){
							if(activeTopicIds[i] === allTopicIds[j] && !posX.includes(allTopicIds[j])){
								posX.push(allTopicIds[j]);
								pos.push(j);
							}
						}
					}
					let posXX = [];
					for(let i=0;i<pos.length;i++)
						posXX.push(allTopicNames[pos[i]]);
					for(let i=0;i<tileTopicsBool.length;i++)
						tileTopicsBool[i] = false;
					for(let i=0;i<allTopicTiles.length;i++)
						if(posXX.includes(allTopicTiles[i]))
							tileTopicsBool[i] = true;
					for(let i=0;i<activeTopicIds.length;i++)
						$(".topic-box"+activeTopicIds[i]).fadeIn(250);
				}
			<?php }else if ( $tsumegoFilters->query == 'tags'){ ?>
				if(activeTagIds.length<1){
					for(let i=0;i<allTagIds.length;i++)
						$(".tag-box"+allTagIds[i]).css("display", "grid");
				}else{
					for(let i=0;i<allTagIds.length;i++)
						if(!activeTagIds.includes(allTagIds[i]))
							$(".tag-box"+allTagIds[i]).css("display", "none");
					let pos = [];
					let posX = [];
					for(let i=0;i<activeTagIds.length;i++){
						for(let j=0;j<allTagIds.length;j++){
							if(activeTagIds[i] === allTagIds[j] && !posX.includes(allTagIds[j])){
								posX.push(allTagIds[j]);
								pos.push(j);
							}
						}
					}
					let posXX = [];
					for(let i=0;i<pos.length;i++)
						posXX.push(allTagNames[pos[i]]);
					for(let i=0;i<tileTagsBool.length;i++)
						tileTagsBool[i] = false;
					for(let i=0;i<allTagTiles.length;i++)
						if(posXX.includes(allTagTiles[i]))
							tileTagsBool[i] = true;
					for(let i=0;i<activeTagIds.length;i++)
						$(".tag-box"+activeTagIds[i]).fadeIn(250);
				}
			<?php } ?>
		}
		function drawActiveTiles(initialDraw = false){
			$(".active-tiles-container").html("");

			if(initialDraw){
				for(let i=0;i<activeTopicTiles.length;i++)
					initialTopicTiles.push(activeTopicTiles[i]);
				for(let i=0;i<activeTagTiles.length;i++)
					initialTagTiles.push(activeTagTiles[i]);
			}
			for(let i=0;i<activeTopicTiles.length;i++){
				let initialTile = false;
				for(let j=0;j<initialTopicTiles.length;j++)
					if(activeTopicTiles[i] === initialTopicTiles[j])
						initialTile = true;
				if(query == "topics" || initialTile)
					$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--green" id="active-tiles-element'+i+'" onclick="removeActiveTopic('+i+')">'+activeTopicTiles[i]+'</div>');
			}
			// Rank picks are kept from every page, so their chips are not bound to the
			// difficulty query the way the topic and tag chips are.
			for(let i=0;i<activeRankTiles.length;i++)
				if(!rankInRange(activeRankTiles[i]))
					$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--difficulty" style="--rank-color:var(--rank-'+activeRankTiles[i]+')" id="active-tiles-rank'+i+'" onclick="removeRankTile('+i+')">'+activeRankTiles[i]+'</div>');
			if(activeRankRange)
				$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--purple" id="active-tiles-difficulty" onclick="clearRankRange()">'+rangeLabel(activeRankRange)+'</div>');
			for(let i=0;i<activeTagTiles.length;i++){
				let initialTile = false;
				for(let j=0;j<initialTagTiles.length;j++)
					if(activeTagTiles[i] === initialTagTiles[j])
						initialTile = true;
				if(query == "tags" || initialTile)
					$(".active-tiles-container").append('<div class="dropdown__tile dropdown__tile--brown" id="active-tiles-element'+i+'" onclick="removeActiveTag('+i+')">'+activeTagTiles[i]+'</div>');
			}
			setCookie("filtered_sets", activeTopicTiles.length == 0 ? 'clear' : activeTopicTiles.join('@'));
			setCookie("filtered_ranks", selectedRankCookieValue());
			setCookie("filtered_tags", activeTagTiles.length == 0 ? 'clear' : activeTagTiles.join('@'));
			if($(".active-tiles-container").children().length > 0)
				$(".active-tiles-container").append('<a class="dropdown__tile dropdown__tile--muted" href="" id="unselect-active-tiles">clear</a>');
		}

		$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e) {
			e.preventDefault();
			$(".active-tiles-container").html("");
			setCookie("filtered_sets", "clear");
			setCookie("filtered_ranks", "clear");
			setCookie("filtered_tags", "clear");
			window.location.href = "/sets";
		});

		function removeActiveTopic(index){
			let removeMap = 0;
			for(let i=0;i<allTopicTiles.length;i++){
				if(allTopicTiles[i] === activeTopicTiles[index])
					removeMap = i;
			}
			for(let i=0;i<allTopicNames.length;i++){
				if(allTopicNames[i] === allTopicTiles[removeMap]){
					unselectTopic(allTopicIds[i]);
					break;
				}
			}
			for(let i=0;i<allTopicTiles.length;i++){
				if(allTopicTiles[i] === activeTopicTiles[index])
					$("#tile-topics"+i).css("background", "#ffffff1a");
			}
			let newTiles = [];
			for(let i=0;i<activeTopicTiles.length;i++){
				if(i !== index)
					newTiles.push(activeTopicTiles[i]);
			}
			activeTopicTiles = newTiles;

			drawActiveCollections();
			drawActiveTiles();
			if(query !== "topics" || queryRefresh)
				window.location.href = "/sets";
		}

		function clearRankRange(){
			activeRankRange = null;
			$("#difficulty-from").val("");
			$("#difficulty-to").val("");
			setCookie("filtered_ranks", selectedRankCookieValue());
			window.location.href = "/sets";
		}

		function initRankFilter(){
			for(let i=0;i<serverRankLabels.length;i++){
				let label = serverRankLabels[i];
				if(label.includes("-")){
					if(activeRankRange === null){
						let parts = label.split("-");
						activeRankRange = { from: parts[0], to: parts[1] };
					}
				}else
					activeRankTiles.push(label);
			}
			activeRankTiles.sort(function(a, b){ return allRanks.indexOf(a) - allRanks.indexOf(b); });
			paintRankTiles();
		}

		function rankInRange(rank){
			if(!activeRankRange)
				return false;
			let rangeFrom = allRanks.indexOf(activeRankRange.from);
			let rangeTo = allRanks.indexOf(activeRankRange.to);
			if(rangeFrom > rangeTo){
				let swap = rangeFrom;
				rangeFrom = rangeTo;
				rangeTo = swap;
			}
			let index = allRanks.indexOf(rank);
			return index >= rangeFrom && index <= rangeTo;
		}

		function rankChips(){
			let chips = [];
			for(let i=0;i<activeRankTiles.length;i++)
				if(!rankInRange(activeRankTiles[i]))
					chips.push(activeRankTiles[i]);
			if(activeRankRange)
				chips.push(rangeLabel(activeRankRange));
			chips.sort(function(a, b){ return allRanks.indexOf(a.split("-")[0]) - allRanks.indexOf(b.split("-")[0]); });
			return chips;
		}

		function paintRankTiles(){
			for(let i=0;i<allRanks.length;i++){
				let selected = activeRankTiles.indexOf(allRanks[i]) !== -1 || rankInRange(allRanks[i]);
				$("#tile-difficulty"+i).toggleClass("is-selected", selected);
			}
			updateRangeScale();
		}

		function updateRangeScale(){
			let fill = document.getElementById("difficulty-range-fill");
			if(!fill)
				return;
			let range = activeRankRange;
			if(!range && activeRankTiles.length === 1)
				range = { from: activeRankTiles[0], to: activeRankTiles[0] };
			if(!range){
				fill.style.clipPath = "inset(0 100% 0 0)";
				return;
			}
			let from = allRanks.indexOf(range.from);
			let to = allRanks.indexOf(range.to);
			if(from > to){
				let swap = from;
				from = to;
				to = swap;
			}
			let left = from / allRanks.length * 100;
			let right = (to + 1) / allRanks.length * 100;
			fill.style.clipPath = "inset(0 " + (100 - right) + "% 0 " + left + "%)";
		}

		function syncRangeSelects(){
			if(activeRankRange)
				return;
			if(activeRankTiles.length === 1){
				$("#difficulty-from").val(activeRankTiles[0]);
				$("#difficulty-to").val(activeRankTiles[0]);
			}else{
				$("#difficulty-from").val("");
				$("#difficulty-to").val("");
			}
			colorRankSelects();
		}

		function colorRankSelects(){
			["difficulty-from", "difficulty-to"].forEach(function(id){
				let select = document.getElementById(id);
				let rank = select.value;
				select.style.color = rank ? "var(--rank-" + rank + ")" : "";
			});
		}

		function toggleRank(index, name, e){
			e.stopPropagation();
			if(rankInRange(name)){
				// A range is one filter of its own; picking one of its ranks switches
				// back to separate ranks so the pick is visible.
				activeRankRange = null;
				$("#difficulty-from").val("");
				$("#difficulty-to").val("");
				activeRankTiles = activeRankTiles.filter(function(rank){ return rank !== name; });
			}
			let position = activeRankTiles.indexOf(name);
			if(position === -1)
				activeRankTiles.push(name);
			else
				activeRankTiles.splice(position, 1);
			activeRankTiles.sort(function(a, b){ return allRanks.indexOf(a) - allRanks.indexOf(b); });
			syncRangeSelects();
			paintRankTiles();
			drawActiveTiles();
		}

		function rangeLabel(range){
			return range.from === range.to ? range.from : range.from + "-" + range.to;
		}

		function selectedRankCookieValue(){
			let chips = rankChips();
			return chips.length === 0 ? "clear" : chips.join("@");
		}

		function removeRankTile(index){
			activeRankTiles.splice(index, 1);
			setCookie("filtered_ranks", selectedRankCookieValue());
			window.location.href = "/sets";
		}

		function removeActiveTag(index){
			let removeMap = 0;
			for(let i=0;i<allTagTiles.length;i++){
				if(allTagTiles[i] === activeTagTiles[index])
					removeMap = i;
			}
			for(let i=0;i<allTagNames.length;i++){
				if(allTagNames[i] === allTagTiles[removeMap]){
					unselectTag(allTagIds[i]);
					break;
				}
			}
			for(let i=0;i<allTagTiles.length;i++){
				if(allTagTiles[i] === activeTagTiles[index])
					$("#tile-tags"+i).css("background", "#ffffff1a");
			}
			let newTiles = [];
			for(let i=0;i<activeTagTiles.length;i++){
				if(i !== index)
					newTiles.push(activeTagTiles[i]);
			}
			activeTagTiles = newTiles;

			drawActiveCollections();
			drawActiveTiles();
			if(query !== "tags" || queryRefresh)
				window.location.href = "/sets";
		}

		function unselectTopic(id){
			let newActiveIds = [];
			for(let i=0;i<activeTopicIds.length;i++){
				if(activeTopicIds[i] !== id){
					newActiveIds.push(activeTopicIds[i]);
				}
			}
			activeTopicIds = newActiveIds;
		}
		function unselectTag(id){
			let newActiveIds = [];
			for(let i=0;i<activeTagIds.length;i++){
				if(activeTagIds[i] != id){
					newActiveIds.push(activeTagIds[i]);
				}
			}
			activeTagIds = newActiveIds;
		}

		$("#topics-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-topics .set-filter-chevron').classList.toggle('rotateArrow');
			if(!topicsToggle){
				topicsToggle = true;
				$("#dropdown-topics").fadeIn(250);
				if(difficultyToggle){
					document.querySelector('#set-button-icon-difficulty .set-filter-chevron').classList.toggle('rotateArrow');
					difficultyToggle = false;
					$("#dropdown-difficulty").fadeOut(250);
				}
				if(tagsToggle){
					document.querySelector('#set-button-icon-tags .set-filter-chevron').classList.toggle('rotateArrow');
					tagsToggle = false;
					$("#dropdown-tags").fadeOut(250);
				}
			}else{
				topicsToggle = false;
				$("#dropdown-topics").fadeOut(250);
			}
		});
		$("#difficulty-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-difficulty .set-filter-chevron').classList.toggle('rotateArrow');
			if(!difficultyToggle){
				difficultyToggle = true;
				$("#dropdown-difficulty").fadeIn(250);
				if(topicsToggle){
					document.querySelector('#set-button-icon-topics .set-filter-chevron').classList.toggle('rotateArrow');
					topicsToggle = false;
					$("#dropdown-topics").fadeOut(250);
				}
				if(tagsToggle){
					document.querySelector('#set-button-icon-tags .set-filter-chevron').classList.toggle('rotateArrow');
					tagsToggle = false;
					$("#dropdown-tags").fadeOut(250);
				}
			}else{
				difficultyToggle = false;
				$("#dropdown-difficulty").fadeOut(250);
			}
		});
		$("#tags-button").click(function(e){
			e.stopPropagation();
			this.querySelector('#set-button-icon-tags .set-filter-chevron').classList.toggle('rotateArrow');
			if(!tagsToggle){
				tagsToggle = true;
				$("#dropdown-tags").fadeIn(250);
				if(topicsToggle){
					document.querySelector('#set-button-icon-topics .set-filter-chevron').classList.toggle('rotateArrow');
					topicsToggle = false;
					$("#dropdown-topics").fadeOut(250);
				}
				if(difficultyToggle){
					document.querySelector('#set-button-icon-difficulty .set-filter-chevron').classList.toggle('rotateArrow');
					difficultyToggle = false;
					$("#dropdown-difficulty").fadeOut(250);
				}
			}else{
				tagsToggle = false;
				$("#dropdown-tags").fadeOut(250);
			}
		});
		$(".dropdown__menu").click(function(e){
			e.stopPropagation();
		});
		$(".whitebox2").click(function(e){
			if(topicsToggle){
				document.querySelector('#set-button-icon-topics .set-filter-chevron').classList.toggle('rotateArrow');
				topicsToggle = false;
				$("#dropdown-topics").fadeOut(250);
			}
			if(difficultyToggle){
				document.querySelector('#set-button-icon-difficulty .set-filter-chevron').classList.toggle('rotateArrow');
				difficultyToggle = false;
				$("#dropdown-difficulty").fadeOut(250);
			}
			if(tagsToggle){
				document.querySelector('#set-button-icon-tags .set-filter-chevron').classList.toggle('rotateArrow');
				tagsToggle = false;
				$("#dropdown-tags").fadeOut(250);
			}
		});

		$("#tile-topics-submit").click(function(e){
			setCookie("query", "topics");
			window.location.href = "/sets";
		});
		$("#tile-difficulty-submit").click(function(e){
			e.preventDefault();
			setCookie("query", "difficulty");
			setCookie("filtered_ranks", selectedRankCookieValue());
			window.location.href = "/sets";
		});
		$("#tile-difficulty-reset").click(function(e){
			e.stopPropagation();
			activeRankTiles = [];
			activeRankRange = null;
			$("#difficulty-from").val("");
			$("#difficulty-to").val("");
			paintRankTiles();
			drawActiveTiles();
			setCookie("filtered_ranks", "clear");
			window.location.href = "/sets";
		});
		$("#difficulty-from, #difficulty-to").change(function(e){
			e.stopPropagation();
			let from = $("#difficulty-from").val();
			let to = $("#difficulty-to").val();
			activeRankRange = null;
			if(from || to){
				let fromIndex = from ? allRanks.indexOf(from) : 0;
				let toIndex = to ? allRanks.indexOf(to) : allRanks.length - 1;
				if(fromIndex > toIndex){
					let swap = fromIndex;
					fromIndex = toIndex;
					toIndex = swap;
				}
				activeRankRange = { from: allRanks[fromIndex], to: allRanks[toIndex] };
			}
			colorRankSelects();
			paintRankTiles();
			drawActiveTiles();
		});

		function rankIndexFromPointer(event){
			let track = document.querySelector(".difficulty-range__scale-track");
			let rect = track.getBoundingClientRect();
			let ratio = (event.clientX - rect.left) / rect.width;
			let index = Math.floor(ratio * allRanks.length);
			return Math.max(0, Math.min(allRanks.length - 1, index));
		}

		function affectedSelectId(index){
			if(!activeRankRange)
				return "difficulty-to";
			let from = allRanks.indexOf(activeRankRange.from);
			let to = allRanks.indexOf(activeRankRange.to);
			if(from > to){
				let swap = from;
				from = to;
				to = swap;
			}
			if(index <= from)
				return "difficulty-from";
			if(index >= to)
				return "difficulty-to";
			return index - from <= to - index ? "difficulty-from" : "difficulty-to";
		}

		function previewRangeSelect(index){
			restoreRangeSelects();
			let select = document.getElementById(affectedSelectId(index));
			select.value = allRanks[index];
			select.style.color = "var(--rank-" + allRanks[index] + ")";
		}

		function restoreRangeSelects(){
			if(activeRankRange){
				$("#difficulty-from").val(activeRankRange.from === allRanks[0] ? "" : activeRankRange.from);
				$("#difficulty-to").val(activeRankRange.to);
				colorRankSelects();
			}else
				syncRangeSelects();
		}

		function applyRankRange(fromIndex, toIndex){
			let from = Math.min(fromIndex, toIndex);
			let to = Math.max(fromIndex, toIndex);
			activeRankRange = { from: allRanks[from], to: allRanks[to] };
			$("#difficulty-from").val(from === 0 ? "" : activeRankRange.from);
			$("#difficulty-to").val(activeRankRange.to);
			colorRankSelects();
			paintRankTiles();
			drawActiveTiles();
		}

		function applyNearestRangeEnd(index){
			if(!activeRankRange){
				// Nothing picked yet: clicking the scale means "everything up to here".
				applyRankRange(0, index);
				return;
			}
			let from = allRanks.indexOf(activeRankRange.from);
			let to = allRanks.indexOf(activeRankRange.to);
			if(from > to){
				let swap = from;
				from = to;
				to = swap;
			}
			if(index <= from)
				applyRankRange(index, to);
			else if(index >= to)
				applyRankRange(from, index);
			else if(index - from <= to - index)
				applyRankRange(index, to);
			else
				applyRankRange(from, index);
		}

		$("#difficulty-range-scale")
			.on("pointerdown", function(e){
				e.stopPropagation();
				rankDragStart = rankIndexFromPointer(e);
				rankDragMoved = false;
				try {
					this.setPointerCapture(e.pointerId);
				} catch (error) {
					// capture only keeps the drag alive outside the track
				}
			})
			.on("pointermove", function(e){
				let index = rankIndexFromPointer(e);
				if(rankDragStart === null){
					previewRangeSelect(index);
					return;
				}
				if(index !== rankDragStart)
					rankDragMoved = true;
				if(rankDragMoved)
					applyRankRange(rankDragStart, index);
			})
			.on("pointerup", function(e){
				e.stopPropagation();
				if(rankDragStart === null)
					return;
				let start = rankDragStart;
				let index = rankIndexFromPointer(e);
				let moved = rankDragMoved;
				rankDragStart = null;
				rankDragMoved = false;
				if(moved || start !== index)
					applyRankRange(start, index);
				else
					applyNearestRangeEnd(start);
			})
			.on("pointerleave", function(){
				if(rankDragStart === null)
					restoreRangeSelects();
			})
			.on("pointercancel", function(){
				rankDragStart = null;
				rankDragMoved = false;
				restoreRangeSelects();
			});
		$("#tile-tags-submit").click(function(e){
			setCookie("query", "tags");
			window.location.href = "/sets";
		});

		function isAllSelected(active, all){
			return all.length > 0 && all.every(function(t){ return active.indexOf(t) !== -1; });
		}
		let selectAllTopics = isAllSelected(activeTopicTiles, allTopicTiles);
		let selectAllTags = isAllSelected(activeTagTiles, allTagTiles);

		$("#tile-topics-select-all").click(function(e){
			e.stopPropagation();
			if(selectAllTopics){
				$("#tile-topics-select-all").html("Select all");
				activeTopicTiles = [];
				activeTopicIds = allTopicIds;
				for(let i=0;i<allTopicTiles.length;i++)
					$("#tile-topics"+i).css("background", "#ffffff1a");
				for(let i=0;i<tileTopicsBool.length;i++)
					tileTopicsBool[i] = false;
			}else{
				$("#tile-topics-select-all").html("Unselect all");
				activeTopicTiles = allTopicTiles;
				activeTopicIds = allTopicIds;
				for(let i=0;i<allTopicTiles.length;i++)
					$("#tile-topics"+i).css("background", "var(--color-tile-green)");
				for(let i=0;i<tileTopicsBool.length;i++)
					tileTopicsBool[i] = true;
			}
			selectAllTopics = !selectAllTopics;
			drawActiveTiles();
			drawActiveCollections();
		});
		$("#tile-tags-select-all").click(function(e){
			e.stopPropagation();
			if(selectAllTags){
				$("#tile-tags-select-all").html("Select all");
				activeTagTiles = [];
				activeTagIds = allTagIds;
				for(let i=0;i<allTagTiles.length;i++)
					$("#tile-tags"+i).css("background", "#ffffff1a");
				for(let i=0;i<tileTagsBool.length;i++)
					tileTagsBool[i] = false;
			}else{
				$("#tile-tags-select-all").html("Unselect all");
				activeTagTiles = allTagTiles;
				activeTagIds = allTagIds;
				for(let i=0;i<allTagTiles.length;i++)
					$("#tile-tags"+i).css("background", "var(--color-tile-brown)");
				for(let i=0;i<tileTagsBool.length;i++)
					tileTagsBool[i] = true;
			}
			selectAllTags = !selectAllTags;
			drawActiveTiles();
			drawActiveCollections();
		});
	</script>

