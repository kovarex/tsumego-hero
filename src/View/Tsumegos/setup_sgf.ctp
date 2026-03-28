<?php echo $this->element('besogo_scripts'); ?>
<div id="something"></div>
<script>
	let options = {};
	options.sgf2 = <?php echo json_encode($sgf, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
	options.panels = [];
	options.rootPath = '/besogo/';
	besogo.create(document.getElementById('something'), options);
	let root = besogo.editor.getRoot();
	let correctMoves = root.exportCorrectMoves();
	let firstColor = 'N';
	if (root.nextMove())
		firstColor = root.nextMove() == BLACK ? 'B' : 'W';
	window.location.href = '/tsumegos/setupSgfStep2/<?php echo (int)$sgfID ?>/' + firstColor + '/' + correctMoves;
</script>
