<script src="/besogo/js/besogo.js"></script>
<script src="/besogo/js/transformation.js"></script>
<script src="/besogo/js/treeProblemUpdater.js?v=2"></script>
<script src="/besogo/js/nodeHashTable.js"></script>
<script src="/besogo/js/editor.js?v=2"></script>
<script src="/besogo/js/gameRoot.js?v=2"></script>
<script src="/besogo/js/status.js"></script>
<script src="/besogo/js/svgUtil.js"></script>
<script src="/besogo/js/cookieUtil.js"></script>
<script src="/besogo/js/parseSgf.js"></script>
<script src="/besogo/js/loadSgf.js"></script>
<script src="/besogo/js/saveSgf.js"></script>
<script src="/besogo/js/boardDisplay.js"></script>
<script src="/besogo/js/coord.js"></script>
<script src="/besogo/js/toolPanel.js"></script>
<script src="/besogo/js/filePanel.js"></script>
<script src="/besogo/js/controlPanel.js"></script>
<script src="/besogo/js/commentPanel.js"></script>
<script src="/besogo/js/treePanel.js"></script>
<script src="/besogo/js/diffInfo.js"></script>
<script src="/besogo/js/scaleParameters.js"></script>
<div id="something"></div>
<script>
	let options = {};
	options.sgf2 = <?php echo json_encode($sgf, JSON_UNESCAPED_UNICODE); ?>;
	options.panels = [];
	options.rootPath = '/besogo/';
	besogo.create(document.getElementById('something'), options);
	let root = besogo.editor.getRoot();
	let correctMoves = root.exportCorrectMoves();
	let firstColor = 'N';
	if (root.nextMove())
		firstColor = root.nextMove() == BLACK ? 'B' : 'W';
	window.location.href = '/tsumegos/setupSgfStep2/<?php echo $sgfID ?>/' + firstColor + '/' + correctMoves;
</script>
