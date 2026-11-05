/* eslint-disable no-restricted-globals -- still uses jQuery until it is converted */
//The collections page (Sets/index.php): its filter tiles and the active filters above the cards.
//The template gives the data of the request in setsIndexPage, the code is the same for everyone and is cached.
const query = setsIndexPage.query;
const queryRefresh = setsIndexPage.queryRefresh;
const allRanks = setsIndexPage.ranks;

//The topics and the tags are picked from tiles: the names of the tiles, the picked ones in the order they
//were picked, the ones picked when the page came, and whether each tile is shown as picked.
const tileKinds = {
	topics: { tiles: setsIndexPage.topicTiles, active: [], initial: [], picked: [], color: "var(--color-tile-green)", chip: "dropdown__tile--green", cookie: "filtered_sets", selectAll: false },
	tags: { tiles: setsIndexPage.tagTiles, active: [], initial: [], picked: [], color: "var(--color-tile-brown)", chip: "dropdown__tile--brown", cookie: "filtered_tags", selectAll: false },
};
const topics = tileKinds.topics;
const tags = tileKinds.tags;

//The cards of the topics query: the id and name of each, a collection split into parts has a card of each.
//A topic picked on this page shows only its cards until the page reloads with it.
const topicCards = setsIndexPage.topics;
let activeTopicIds = [];

let activeRankTiles = [];
let activeRankRange = null;
let rankDragStart = null;
let rankDragMoved = false;

function byRankOrder(a, b){
	return allRanks.indexOf(a) - allRanks.indexOf(b);
}

//The indexes of the two ends of a range of ranks, weakest first.
function rangeIndexes(range){
	const from = allRanks.indexOf(range.from);
	const to = allRanks.indexOf(range.to);
	return from <= to ? [from, to] : [to, from];
}

// ── The topic and tag tiles ──

//Shows a tile picked or not.
function paintTile(kindName, name, select){
	const kind = tileKinds[kindName];
	const position = kind.tiles.lastIndexOf(name);
	$("#tile-" + kindName + position).css("background", select ? kind.color : "var(--surface-translucent)");
	kind.picked[position] = select;
}

//The id of the first card of a topic, the one its tile stands for.
function topicCardId(name){
	const card = topicCards.find(function(card){ return card.name === name; });
	return card ? card.id : undefined;
}

function toggleTile(kindName, index, name, e){
	e.stopPropagation();
	const kind = tileKinds[kindName];
	if(!kind.picked[index]){
		paintTile(kindName, name, true);
		kind.active.push(name);
		if(kindName === "topics" && query === "topics")
			for(const card of topicCards)
				if(card.name === name)
					activeTopicIds.push(card.id);
	}else{
		paintTile(kindName, name, false);
		kind.active = kind.active.filter(function(activeName){ return activeName !== name; });
		if(kindName === "topics" && query === "topics")
			unselectTopic(topicCardId(name));
	}
	drawActiveCollections();
	drawActiveTiles();
}

function unselectTopic(id){
	activeTopicIds = activeTopicIds.filter(function(activeId){ return activeId !== id; });
}

//Removes the chip of a picked topic or tag. The page reloads when the cards are of another kind, or when
//the server already filtered them by this kind.
function removeActiveTile(kindName, index){
	const kind = tileKinds[kindName];
	const name = kind.active[index];
	if(kindName === "topics")
		unselectTopic(topicCardId(name));
	kind.tiles.forEach(function(tile, i){
		if(tile === name)
			$("#tile-" + kindName + i).css("background", "#ffffff1a");
	});
	kind.active = kind.active.filter(function(activeName, i){ return i !== index; });

	drawActiveCollections();
	drawActiveTiles();
	if(query !== kindName || queryRefresh)
		window.location.href = "/sets";
}

function toggleSelectAll(kindName){
	const kind = tileKinds[kindName];
	kind.selectAll = !kind.selectAll;
	$("#tile-" + kindName + "-select-all").html(kind.selectAll ? "Unselect all" : "Select all");
	kind.active = kind.selectAll ? kind.tiles.slice() : [];
	if(kindName === "topics")
		activeTopicIds = topicCards.map(function(card){ return card.id; });
	kind.tiles.forEach(function(tile, i){
		$("#tile-" + kindName + i).css("background", kind.selectAll ? kind.color : "#ffffff1a");
		kind.picked[i] = kind.selectAll;
	});
	drawActiveTiles();
	drawActiveCollections();
}

//Shows the cards of the picked topics only, all of them when none is picked.
function drawActiveCollections(){
	if(query !== "topics")
		return;
	if(activeTopicIds.length < 1){
		for(const card of topicCards)
			$(".topic-box" + card.id).css("display", "grid");
		return;
	}
	for(const card of topicCards)
		if(!activeTopicIds.includes(card.id))
			$(".topic-box" + card.id).css("display", "none");
	const pickedNames = activeTopicIds.map(function(id){
		return topicCards.find(function(card){ return card.id === id; }).name;
	});
	topics.tiles.forEach(function(tile, i){
		topics.picked[i] = pickedNames.includes(tile);
	});
	for(const id of activeTopicIds)
		$(".topic-box" + id).fadeIn(250);
}

//A chip of an active filter, a click on it removes the filter.
function chip(className, id, text, remove, index){
	return $("<div>").addClass("dropdown__tile " + className).attr("id", id).attr("data-remove", remove).attr("data-index", index).text(text);
}

//The chips of the active filters above the cards, and the cookies that carry the filters to the server.
//A topic or tag picked on this page has a chip only when the cards are of its kind.
function drawActiveTiles(initialDraw = false){
	const container = $(".active-tiles-container");
	container.html("");

	if(initialDraw){
		topics.initial.push(...topics.active);
		tags.initial.push(...tags.active);
	}
	topics.active.forEach(function(name, i){
		if(query == "topics" || topics.initial.includes(name))
			container.append(chip(topics.chip, "active-tiles-element" + i, name, "topics", i));
	});
	// Rank picks are kept from every page, so their chips are not bound to the
	// difficulty query the way the topic and tag chips are.
	activeRankTiles.forEach(function(rank, i){
		if(!rankInRange(rank))
			container.append(chip("dropdown__tile--difficulty", "active-tiles-rank" + i, rank, "rank", i).attr("style", "--rank-color:var(--rank-" + rank + ")"));
	});
	if(activeRankRange)
		container.append(chip("dropdown__tile--purple", "active-tiles-difficulty", rangeLabel(activeRankRange), "range", 0));
	tags.active.forEach(function(name, i){
		if(query == "tags" || tags.initial.includes(name))
			container.append(chip(tags.chip, "active-tiles-element" + i, name, "tags", i));
	});
	setCookie(topics.cookie, topics.active.length == 0 ? "clear" : topics.active.join("@"));
	setCookie("filtered_ranks", selectedRankCookieValue());
	setCookie(tags.cookie, tags.active.length == 0 ? "clear" : tags.active.join("@"));
	if(container.children().length > 0)
		container.append('<a class="dropdown__tile dropdown__tile--muted" href="" id="unselect-active-tiles">clear</a>');
}

$(".active-tiles-container").on("click", "[data-remove]", function(){
	const index = Number(this.getAttribute("data-index"));
	const remove = this.getAttribute("data-remove");
	if(remove === "rank")
		removeRankTile(index);
	else if(remove === "range")
		clearRankRange();
	else
		removeActiveTile(remove, index);
});

$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e){
	e.preventDefault();
	$(".active-tiles-container").html("");
	setCookie("filtered_sets", "clear");
	setCookie("filtered_ranks", "clear");
	setCookie("filtered_tags", "clear");
	window.location.href = "/sets";
});

// ── The ranks: separate ranks picked from tiles, or one range from the selects or the scale ──

function clearRangeSelects(){
	$("#difficulty-from").val("");
	$("#difficulty-to").val("");
}

function clearRankRange(){
	activeRankRange = null;
	clearRangeSelects();
	setCookie("filtered_ranks", selectedRankCookieValue());
	window.location.href = "/sets";
}

function initRankFilter(){
	for(const label of setsIndexPage.rankLabels){
		if(label.includes("-")){
			if(activeRankRange === null){
				const parts = label.split("-");
				activeRankRange = { from: parts[0], to: parts[1] };
			}
		}else
			activeRankTiles.push(label);
	}
	activeRankTiles.sort(byRankOrder);
	paintRankTiles();
}

function rankInRange(rank){
	if(!activeRankRange)
		return false;
	const [rangeFrom, rangeTo] = rangeIndexes(activeRankRange);
	const index = allRanks.indexOf(rank);
	return index >= rangeFrom && index <= rangeTo;
}

function rankChips(){
	const chips = activeRankTiles.filter(function(rank){ return !rankInRange(rank); });
	if(activeRankRange)
		chips.push(rangeLabel(activeRankRange));
	chips.sort(function(a, b){ return byRankOrder(a.split("-")[0], b.split("-")[0]); });
	return chips;
}

function paintRankTiles(){
	allRanks.forEach(function(rank, i){
		$("#tile-difficulty" + i).toggleClass("is-selected", activeRankTiles.includes(rank) || rankInRange(rank));
	});
	updateRangeScale();
}

function updateRangeScale(){
	const fill = document.getElementById("difficulty-range-fill");
	if(!fill)
		return;
	let range = activeRankRange;
	if(!range && activeRankTiles.length === 1)
		range = { from: activeRankTiles[0], to: activeRankTiles[0] };
	if(!range){
		fill.style.clipPath = "inset(0 100% 0 0)";
		return;
	}
	const [from, to] = rangeIndexes(range);
	const left = from / allRanks.length * 100;
	const right = (to + 1) / allRanks.length * 100;
	fill.style.clipPath = "inset(0 " + (100 - right) + "% 0 " + left + "%)";
}

function syncRangeSelects(){
	if(activeRankRange)
		return;
	if(activeRankTiles.length === 1){
		$("#difficulty-from").val(activeRankTiles[0]);
		$("#difficulty-to").val(activeRankTiles[0]);
	}else
		clearRangeSelects();
	colorRankSelects();
}

function colorRankSelects(){
	["difficulty-from", "difficulty-to"].forEach(function(id){
		const select = document.getElementById(id);
		const rank = select.value;
		select.style.color = rank ? "var(--rank-" + rank + ")" : "";
	});
}

function toggleRank(name, e){
	e.stopPropagation();
	if(rankInRange(name)){
		// A range is one filter of its own; picking one of its ranks switches
		// back to separate ranks so the pick is visible.
		activeRankRange = null;
		clearRangeSelects();
		activeRankTiles = activeRankTiles.filter(function(rank){ return rank !== name; });
	}
	const position = activeRankTiles.indexOf(name);
	if(position === -1)
		activeRankTiles.push(name);
	else
		activeRankTiles.splice(position, 1);
	activeRankTiles.sort(byRankOrder);
	syncRangeSelects();
	paintRankTiles();
	drawActiveTiles();
}

function rangeLabel(range){
	return range.from === range.to ? range.from : range.from + "-" + range.to;
}

function selectedRankCookieValue(){
	const chips = rankChips();
	return chips.length === 0 ? "clear" : chips.join("@");
}

function removeRankTile(index){
	activeRankTiles.splice(index, 1);
	setCookie("filtered_ranks", selectedRankCookieValue());
	window.location.href = "/sets";
}

function rankIndexFromPointer(event){
	const rect = document.querySelector(".difficulty-range__scale-track").getBoundingClientRect();
	const index = Math.floor((event.clientX - rect.left) / rect.width * allRanks.length);
	return Math.max(0, Math.min(allRanks.length - 1, index));
}

//The select a pointer over the scale moves: the end of the range nearer to it.
function affectedSelectId(index){
	if(!activeRankRange)
		return "difficulty-to";
	const [from, to] = rangeIndexes(activeRankRange);
	if(index <= from)
		return "difficulty-from";
	if(index >= to)
		return "difficulty-to";
	return index - from <= to - index ? "difficulty-from" : "difficulty-to";
}

function previewRangeSelect(index){
	restoreRangeSelects();
	const select = document.getElementById(affectedSelectId(index));
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
	const from = Math.min(fromIndex, toIndex);
	const to = Math.max(fromIndex, toIndex);
	activeRankRange = { from: allRanks[from], to: allRanks[to] };
	$("#difficulty-from").val(from === 0 ? "" : activeRankRange.from);
	$("#difficulty-to").val(activeRankRange.to);
	colorRankSelects();
	paintRankTiles();
	drawActiveTiles();
}

//A click on the scale moves the end of the range nearer to it, or picks everything up to it.
function applyNearestRangeEnd(index){
	if(!activeRankRange){
		applyRankRange(0, index);
		return;
	}
	const [from, to] = rangeIndexes(activeRankRange);
	if(affectedSelectId(index) === "difficulty-from")
		applyRankRange(index, to);
	else
		applyRankRange(from, index);
}

// ── The three filter menus: one is open at a time, a click elsewhere on the page closes it ──

let openMenu = null;

function setMenuOpen(name, open){
	document.querySelector("#set-button-icon-" + name + " .set-filter-chevron").classList.toggle("rotateArrow");
	if(open)
		$("#dropdown-" + name).fadeIn(250);
	else
		$("#dropdown-" + name).fadeOut(250);
	openMenu = open ? name : null;
}

function closeOpenMenu(){
	if(openMenu !== null)
		setMenuOpen(openMenu, false);
}

["topics", "difficulty", "tags"].forEach(function(name){
	$("#" + name + "-button").click(function(e){
		e.stopPropagation();
		if(openMenu === name){
			setMenuOpen(name, false);
			return;
		}
		closeOpenMenu();
		setMenuOpen(name, true);
	});
});
$(".dropdown__menu").click(function(e){
	e.stopPropagation();
});
$(".whitebox2").click(function(){
	closeOpenMenu();
});

// ── The tiles and buttons of the menus ──

topics.tiles.forEach(function(name, index){
	topics.picked.push(false);
	$("#tile-topics" + index).click(function(e){ toggleTile("topics", index, name, e); });
});
allRanks.forEach(function(rankName, index){
	$("#tile-difficulty" + index).click(function(e){ toggleRank(rankName, e); });
});
tags.tiles.forEach(function(name, index){
	tags.picked.push(false);
	$("#tile-tags" + index).click(function(e){ toggleTile("tags", index, name, e); });
});

for(const name of setsIndexPage.filteredSets){
	topics.active.push(name);
	paintTile("topics", name, true);
}
for(const name of setsIndexPage.filteredTags){
	tags.active.push(name);
	paintTile("tags", name, true);
}

function isAllSelected(kind){
	return kind.tiles.length > 0 && kind.tiles.every(function(tile){ return kind.active.includes(tile); });
}
topics.selectAll = isAllSelected(topics);
tags.selectAll = isAllSelected(tags);

$("#tile-topics-select-all").click(function(e){
	e.stopPropagation();
	toggleSelectAll("topics");
});
$("#tile-tags-select-all").click(function(e){
	e.stopPropagation();
	toggleSelectAll("tags");
});

$("#tile-topics-submit").click(function(){
	setCookie("query", "topics");
	window.location.href = "/sets";
});
$("#tile-tags-submit").click(function(){
	setCookie("query", "tags");
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
	clearRangeSelects();
	paintRankTiles();
	drawActiveTiles();
	setCookie("filtered_ranks", "clear");
	window.location.href = "/sets";
});
$("#difficulty-from, #difficulty-to").change(function(e){
	e.stopPropagation();
	const from = $("#difficulty-from").val();
	const to = $("#difficulty-to").val();
	activeRankRange = null;
	if(from || to){
		const fromIndex = from ? allRanks.indexOf(from) : 0;
		const toIndex = to ? allRanks.indexOf(to) : allRanks.length - 1;
		activeRankRange = { from: allRanks[Math.min(fromIndex, toIndex)], to: allRanks[Math.max(fromIndex, toIndex)] };
	}
	colorRankSelects();
	paintRankTiles();
	drawActiveTiles();
});

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
		const index = rankIndexFromPointer(e);
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
		const start = rankDragStart;
		const index = rankIndexFromPointer(e);
		const moved = rankDragMoved;
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

$(document).ready(function(){
	setCookie("query", query);
	$("#tile-topics-select-all").html(topics.selectAll ? "Unselect all" : "Select all");
	$("#tile-tags-select-all").html(tags.selectAll ? "Unselect all" : "Select all");
	initRankFilter();
	colorRankSelects();
	drawActiveCollections();
	drawActiveTiles(true);
});
