<?php echo $this->element('besogo_scripts'); ?>
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
	options.sgf2 = <?php echo json_encode($sgf, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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

	addInput(form, 'sgf', <?php echo json_encode($sgf, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
	addInput(form, 'firstMoveColor', firstColor);
	addInput(form, 'correctMoves', correctMoves);
	addInput(form, 'setConnectionID', <?php echo (int)$setConnectionID; ?>);

    document.body.appendChild(form);
    form.submit();
</script>
