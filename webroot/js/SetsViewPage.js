/* eslint-disable no-restricted-globals -- still uses jQuery until it is converted */
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

$(".active-tiles-container").on("click", "[data-filter]", function() {
	const index = Number(this.dataset.index);
	if (this.dataset.filter === "set")
		removeActiveFilter(activeSets, "filtered_sets", index);
	else if (this.dataset.filter === "rank")
		removeActiveFilter(activeRanks, "filtered_ranks", index);
	else
		removeActiveFilter(activeTags, "filtered_tags", index);
});

$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e) {
	e.preventDefault();
	setCookie("filtered_sets", "clear");
	setCookie("filtered_ranks", "clear");
	setCookie("filtered_tags", "clear");
	window.location.reload();
});

//A link that folds a panel in and out, with the arrow beside it pointing the way it goes.
function foldingPanel(link, panel, arrow) {
	let open = false;
	$(link).click(function() {
		if (open)
			$(panel).fadeOut(250);
		else
			$(panel).fadeIn(250);
		open = !open;
		const arrowImage = document.getElementById(arrow);
		if (arrowImage)
			arrowImage.src = open ? "/img/greyArrow2.png" : "/img/greyArrow1.png";
	});
}

$("#msg2x").hide();
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
		$(view.button).toggleClass("btn--inactive", shown);
		if (shown) {
			$(view.info).fadeIn(250);
			$(view.detail).fadeIn(200);
		} else {
			$(view.info).hide();
			$(view.detail).hide();
		}
		$(view.score).css("border", shown ? "1px solid var(" + view.accent + ")" : "none");
	}
}

$("[data-score-view]").click(function() {
	showScoreView(this.dataset.scoreView);
});
// the numbers are shown at first, by the markup itself
$("#ratioInfo").hide();
$("#timeInfo").hide();
$("#numbersButton").addClass("btn--inactive");

// the sharing is saved as soon as it is switched
$(".set-view__share-toggle input[type=checkbox]").change(function() {
	this.form.submit();
});

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
