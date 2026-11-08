//The page of a collection (Sets/view.php): the filters of its problems, the views of the player's scores,
//the reset of the progress and the link sharing. The template gives the data of the request in
//setsViewPage, the code is the same for everyone and is cached.
const activeSets = setsViewPage.activeSets;
const activeRanks = setsViewPage.activeRanks;
const activeTags = setsViewPage.activeTags;

//Removing a filter tile saves the rest of its kind and reloads the page with them.
function removeActiveFilter(filters, cookie, index) {
	filters.splice(index, 1);
	setCookie(cookie, filters.length == 0 ? "clear" : filters.join("@"));
	window.location.reload();
}

//The tiles of the active filters: a click on one (or on clear) inside the container
document.querySelectorAll(".active-tiles-container").forEach(container => container.addEventListener("click", function(e) {
	const tile = e.target.closest("[data-filter]");
	if (tile && container.contains(tile)) {
		const index = Number(tile.dataset.index);
		if (tile.dataset.filter === "set")
			removeActiveFilter(activeSets, "filtered_sets", index);
		else if (tile.dataset.filter === "rank")
			removeActiveFilter(activeRanks, "filtered_ranks", index);
		else
			removeActiveFilter(activeTags, "filtered_tags", index);
	}
	const clear = e.target.closest("#unselect-active-tiles");
	if (clear && container.contains(clear)) {
		e.preventDefault();
		setCookie("filtered_sets", "clear");
		setCookie("filtered_ranks", "clear");
		setCookie("filtered_tags", "clear");
		window.location.reload();
	}
}));

//A link that folds a panel in and out, with the arrow beside it pointing the way it goes.
function foldingPanel(link, panel, arrow) {
	let open = false;
	document.querySelectorAll(link).forEach(element => element.addEventListener("click", function() {
		if (open)
			Display.fadeOut(panel, 250);
		else
			Display.fadeIn(panel, 250);
		open = !open;
		const arrowImage = document.getElementById(arrow);
		if (arrowImage)
			arrowImage.src = open ? "/img/greyArrow2.png" : "/img/greyArrow1.png";
	}));
}

//On a phone the head of the collection is short: More opens the whole description with its cover, the
//stats and the views of the scores open from their own button (sets.css)
for (let [button, openClass] of [[".set-view__expand", "set-view--expanded"], [".set-view__stats-toggle", "set-view--stats-open"]])
	document.querySelectorAll(button).forEach(element => element.addEventListener("click", () => {
		let info = element.closest(".set-view__info");
		let open = info.classList.toggle(openClass);
		element.setAttribute("aria-expanded", open ? "true" : "false");
		if (element.classList.contains("set-view__expand"))
			element.textContent = open ? "Less" : "More";
	}));

Display.hide("#msg2x");
foldingPanel("#showx", "#msg2x", "greyArrow1");
foldingPanel("#showFilters", "#msgFilters", "greyArrowFilter");

//What the problem buttons show under their picture: the numbers, the solved/failed attempts or the
//best times, each with its explanation and the score of the collection it belongs to.
const scoreViews = {
	numbers: { button: "#numbersButton", info: "#numbersInfo", detail: ".problem-nav__number", score: ".setViewCompleted", accent: "--set-accent-green" },
	ratio: { button: "#ratioButton", info: "#ratioInfo", detail: ".problem-nav__ratio", score: ".setViewAccuracy", accent: "--set-accent-purple" },
	time: { button: "#timeButton", info: "#timeInfo", detail: ".problem-nav__time", score: ".setViewTime", accent: "--set-accent-orange" },
};

function showScoreView(name) {
	for (const [viewName, view] of Object.entries(scoreViews)) {
		const shown = viewName === name;
		document.querySelectorAll(view.button).forEach(button => button.classList.toggle("btn--inactive", shown));
		if (shown) {
			Display.fadeIn(view.info, 250);
			Display.fadeIn(view.detail, 200);
		} else {
			Display.hide(view.info);
			Display.hide(view.detail);
		}
		document.querySelectorAll(view.score).forEach(score => score.style.border = shown ? "1px solid var(" + view.accent + ")" : "none");
	}
}

document.querySelectorAll("[data-score-view]").forEach(button => button.addEventListener("click", function() {
	showScoreView(this.dataset.scoreView);
}));
// the numbers are shown at first, by the markup itself
Display.hide("#ratioInfo");
Display.hide("#timeInfo");
document.querySelectorAll("#numbersButton").forEach(button => button.classList.add("btn--inactive"));

// the sharing is saved as soon as it is switched
document.querySelectorAll(".set-view__share-toggle input[type=checkbox]").forEach(checkbox => checkbox.addEventListener("change", function() {
	this.form.submit();
}));

(function() {
	const button = document.getElementById("SetShareCopy");
	const field = document.getElementById("SetShareLink");
	const status = document.getElementById("SetShareStatus");
	if (!button || !field)
		return;
	field.addEventListener("click", () => field.select());
	button.addEventListener("click", () => {
		const copied = navigator.clipboard && window.isSecureContext
			? navigator.clipboard.writeText(field.value)
			: Promise.reject(new Error("no clipboard"));
		copied.then(() => {
			button.textContent = "Copied";
			status.textContent = "The link is in your clipboard.";
			setTimeout(() => {
				button.textContent = "Copy";
				status.textContent = "";
			}, 2000);
		}).catch(() => {
			// The browser refused, so leave the address selected for a manual copy.
			field.select();
			status.textContent = "Press Ctrl+C to copy the address.";
		});
	});
}());
