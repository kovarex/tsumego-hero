//The upload history of a problem or a player (Sgfs/view.php): the downloads, the deletion and the
//highlight of a version and the one its diff compares with. The template gives the SGFs of the versions in
//uploadHistoryPage, the code is the same for everyone and is cached.
(function() {
	if (typeof uploadHistoryPage === "undefined")
		return;

	//Calls the handlers whose selector an element from the target of event up to container matches, with
	//the element as this: the deepest element first, and the handlers of one element in their order, as
	//jQuery's delegated events. With enterOrLeave only those the pointer came into or went out of, as
	//jQuery's delegated mouseenter and mouseleave do.
	function delegate(container, event, handlers, enterOrLeave = false) {
		for (let node = event.target; node && node !== container; node = node.parentElement)
			for (const [selector, handler] of handlers)
				if (node.matches(selector) && !(enterOrLeave && event.relatedTarget && (event.relatedTarget === node || node.contains(event.relatedTarget))))
					handler.call(node, event);
	}

	const rowID = element => element.closest("tr")?.id;

	document.addEventListener("click", event => delegate(document, event, [
		["[data-download]", function() {
			saveAs(new Blob([uploadHistoryPage.sgfs[rowID(this)]], { type: "sgf" }), this.getAttribute("data-download"));
		}],
		["[data-delete]", function() {
			if (confirm("Are you sure?"))
				window.location.href = "/sgfs/view/" + uploadHistoryPage.problemID + "?delete=" + this.getAttribute("data-delete");
		}]]));

	function paintRow(sgfID, background) {
		const row = sgfID ? document.getElementById(sgfID) : null;
		if (row)
			row.querySelectorAll("td").forEach(cell => cell.style.background = background);
	}

	document.querySelectorAll(".data-table").forEach(table => {
		table.addEventListener("mouseover", event => delegate(table, event, [
			["tr[id]", function() {
				paintRow(this.id, "linear-gradient(#f7f7f7, #b9b9b9)");
			}],
			["[data-compared-with]", function() {
				paintRow(rowID(this), "linear-gradient(#fff, #c8c8c8)");
				paintRow(this.getAttribute("data-compared-with"), "linear-gradient(#fff, #c8c8c8)");
			}]], true));
		table.addEventListener("mouseout", event => delegate(table, event, [
			["tr[id]", function() {
				paintRow(this.id, "");
			}],
			["[data-compared-with]", function() {
				paintRow(rowID(this), "");
				paintRow(this.getAttribute("data-compared-with"), "");
			}]], true));
	});
}());
