//The collections page (Sets/index.php): its filter tiles and the active filters above the cards.
//The template gives the data of the request in setsIndexPage, the code is the same for everyone and is cached.
let query = setsIndexPage.query;
let queryRefresh = setsIndexPage.queryRefresh;
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
for (const set of setsIndexPage.topics)
		{
			allTopicIds.push(set.id);
			allTopicNames.push(set.name);
		}

		function toggleTopic(index, name, e) {
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
				};

		function toggleTag(index, name, e) {
					e.stopPropagation();
					if(!tileTagsBool[index]){
						handleTiles(name, "tags", true);
						activeTagTiles.push(name);
					}else{
						handleTiles(name, "tags", false);
						let newActiveTagTiles = [];
						for(let i=0;i<activeTagTiles.length;i++){
							if(activeTagTiles[i] !== name)
								newActiveTagTiles.push(activeTagTiles[i]);
						}
						activeTagTiles = newActiveTagTiles;
					}
					drawActiveCollections();
					drawActiveTiles();
				};

		setsIndexPage.topicTiles.forEach(function(name, index){
			allTopicTiles.push(name);
			tileTopicsBool.push(false);
			$("#tile-topics" + index).click(function(e){ toggleTopic(index, name, e); });
		});

		allRanks = setsIndexPage.ranks;
		serverRankLabels = setsIndexPage.rankLabels;

		allRanks.forEach(function(rankName, index){
			$("#tile-difficulty" + index).click(function(e){ toggleRank(index, rankName, e); });
		});

		setsIndexPage.tagTiles.forEach(function(name, index){
			allTagTiles.push(name);
			tileTagsBool.push(false);
			$("#tile-tags" + index).click(function(e){ toggleTag(index, name, e); });
		});

		for (const set of setsIndexPage.filteredSets)
		{
			filteredSets.push(set);
			activeTopicTiles.push(set);
			handleTiles(set, "topics", true);
		}
		for (const tag of setsIndexPage.filteredTags)
		{
			filteredTags.push(tag);
			activeTagTiles.push(tag);
			handleTiles(tag, "tags", true);
		}
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
	if (setsIndexPage.query === 'topics')
	{
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
	}
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
		for(let i=0;i<allTagTiles.length;i++)
			$("#tile-tags"+i).css("background", "#ffffff1a");
		for(let i=0;i<tileTagsBool.length;i++)
			tileTagsBool[i] = false;
	}else{
		$("#tile-tags-select-all").html("Unselect all");
		activeTagTiles = allTagTiles;
		for(let i=0;i<allTagTiles.length;i++)
			$("#tile-tags"+i).css("background", "var(--color-tile-brown)");
		for(let i=0;i<tileTagsBool.length;i++)
			tileTagsBool[i] = true;
	}
	selectAllTags = !selectAllTags;
	drawActiveTiles();
	drawActiveCollections();
});
