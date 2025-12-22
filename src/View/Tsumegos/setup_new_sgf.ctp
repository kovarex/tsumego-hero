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

	function addInput(form, name, value)
	{
		let input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value;
		form.appendChild(input);
	}

	let options = {};
	options.sgf2 = "<?php echo $sgf; ?>";
	options.panels = [];
	options.rootPath = '/besogo/';
	besogo.create(document.getElementById('something'), options);
	let root = besogo.editor.getRoot();
	let correctMoves = root.exportCorrectMoves();
	let firstColor = 'N';
	if (root.nextMove())
		firstColor = root.nextMove() == BLACK ? 'B' : 'W';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/tsumegos/setupNewSgfStep2';

	addInput(form, 'sgf', "<?php echo $sgf; ?>");
	addInput(form, 'firstMoveColor', firstColor);
	addInput(form, 'correctMoves', correctMoves);
	addInput(form, 'setConnectionID', <?php echo $setConnectionID; ?>);

    document.body.appendChild(form);
    form.submit();
</script>
