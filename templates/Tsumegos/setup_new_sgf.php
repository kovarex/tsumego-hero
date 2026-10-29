<?php

/**
 * @var View $this
 * @var int $setConnectionID
 * @var string $sgf
 * @var ?string $sourceSgfID
 * @var ?string $transformationFromSource
 * @var string $description
 */

use App\Utility\ViteManifest;
$this->start('script');
echo ViteManifest::legacyScript('besogo');
$this->end();
?>
<div id="uploadProblemBox" hidden>
	<p id="uploadProblem"></p>
	<a href="/<?php echo (int) $setConnectionID; ?>">Back to the problem</a>
</div>
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
	function submitSgf(root)
	{
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
		addInput(form, 'sourceSgfID', <?php echo json_encode((string) ($sourceSgfID ?? '')); ?>);
		addInput(form, 'transformationFromSource', <?php echo json_encode((string) ($transformationFromSource ?? ''), JSON_HEX_TAG); ?>);
		addInput(form, 'description', <?php echo json_encode($description, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);

		document.body.appendChild(form);
		form.submit();
	}

	let root = besogo.editor.getRoot();
	// the board editor refuses to save these, so an uploaded file has to pass the same check
	let problem = root.checkTsumegoHeroCompatibility(root);
	if (problem)
	{
		document.getElementById('uploadProblem').textContent = 'The SGF was not saved: ' + problem.message + ' (move ' + problem.node.moveNumber + ')';
		document.getElementById('uploadProblemBox').hidden = false;
	}
	else
		submitSgf(root);
</script>
