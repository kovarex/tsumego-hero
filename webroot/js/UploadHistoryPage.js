/* eslint-disable no-restricted-globals -- still uses jQuery until it is converted */
//The upload history of a problem or a player (Sgfs/view.php): the downloads, the deletion and the
//highlight of a version and the one its diff compares with. The template gives the SGFs of the versions in
//uploadHistoryPage, the code is the same for everyone and is cached.
(function() {
	if (typeof uploadHistoryPage === "undefined")
		return;

	$(document).on("click", "[data-download]", function() {
		const sgfID = $(this).closest("tr").attr("id");
		saveAs(new Blob([uploadHistoryPage.sgfs[sgfID]], { type: "sgf" }), this.getAttribute("data-download"));
	});

	$(document).on("click", "[data-delete]", function() {
		if (confirm("Are you sure?"))
			window.location.href = "/sgfs/view/" + uploadHistoryPage.problemID + "?delete=" + this.getAttribute("data-delete");
	});

	function paintRow(sgfID, background) {
		$("#" + sgfID + " td").css("background", background);
	}

	$(".data-table").on("mouseenter", "tr[id]", function() {
		paintRow(this.id, "linear-gradient(#f7f7f7, #b9b9b9)");
	}).on("mouseleave", "tr[id]", function() {
		paintRow(this.id, "");
	}).on("mouseenter", "[data-compared-with]", function() {
		paintRow($(this).closest("tr").attr("id"), "linear-gradient(#fff, #c8c8c8)");
		paintRow(this.getAttribute("data-compared-with"), "linear-gradient(#fff, #c8c8c8)");
	}).on("mouseleave", "[data-compared-with]", function() {
		paintRow($(this).closest("tr").attr("id"), "");
		paintRow(this.getAttribute("data-compared-with"), "");
	});
}());
