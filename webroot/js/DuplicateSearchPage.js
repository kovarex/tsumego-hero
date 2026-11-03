//The similar problem search (Tsumegos/duplicatesearch.php): each found problem, and the source, gets a board of its
//position in the preview cell of its row, with the stones that differ from the source marked.
if (document.getElementById("previewMaster"))
	document.querySelectorAll("a[data-sgf-preview]").forEach(function(a) {
		const td = a.closest("tr").querySelector("td[id^=preview]");
		if (!td)
			return;
		const data = JSON.parse(a.dataset.sgfPreview);
		createBoard(td, data.black, data.white, data.xMax, data.yMax, data.boardSize, data.diff || "");
	});
