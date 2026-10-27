// Runs the method on a board display of the editor, made in a container outside the page. The display sets a few
// globals of besogo, they are restored afterwards.
function withBoardDisplay(editor, method)
{
	let originalEditor = besogo.editor;
	let originalScaleParameters = besogo.scaleParameters;
	let originalBoardParameters = besogo.boardParameters;
	let originalBoardCanvasSvg = besogo.boardCanvasSvg;
	besogo.editor = editor; // the display asks it whether to rotate the board
	besogo.scaleParameters = besogo.makeScaleParameters(editor.getRoot().getSize());
	besogo.boardParameters = [];
	try
	{
		let container = document.createElement("div");
		besogo.makeBoardDisplay(container, editor);
		method(container);
	}
	finally
	{
		besogo.editor = originalEditor;
		besogo.scaleParameters = originalScaleParameters;
		besogo.boardParameters = originalBoardParameters;
		besogo.boardCanvasSvg = originalBoardCanvasSvg;
	}
}

// The board a new display draws for the current node of the editor
function drawnBoard(editor)
{
	let fresh = besogo.makeEditor(19, 19, {});
	fresh.SHADOWS = editor.SHADOWS;
	fresh.loadRoot(editor.getRoot());
	fresh.setCurrent(editor.getCurrent());
	let board = null;
	withBoardDisplay(fresh, container => board = container.innerHTML);
	return board;
}

// Checks that the board shown is the one a new display draws for the current node of the editor
function checkBoard(container, editor)
{
	let shown = container.innerHTML;
	let drawn = drawnBoard(editor);
	if (shown == drawn)
		return;
	let start = 0;
	while (shown[start] == drawn[start])
		++start;
	// Only the place where they differ, without the brackets of the tags, which the page of the tests would take as html
	let excerpt = board => board.substr(Math.max(0, start - 40), 160).replace(/</g, "[").replace(/>/g, "]");
	CHECK_EQUALS(excerpt(shown), excerpt(drawn));
}

besogo.addTest("BoardDisplay", "BoardAfterMovesIsTheOneANewDisplayDraws", function()
{
	// The line has captures, kos and a cycle through a virtual child
	let editor = loadEditor(ETERNAL_LIFE_SGF);
	editor.SHADOWS = true;
	withBoardDisplay(editor, container =>
	{
		let lastMove = editor.getRoot();
		while (lastMove.children.length)
			lastMove = lastMove.children[0];
		while (editor.getCurrent() != lastMove)
		{
			editor.nextNode(1);
			checkBoard(container, editor);
		}

		let retake = lastMove.virtualChildren[0];
		editor.click(retake.move.x, retake.move.y, false, false);
		CHECK(editor.getCurrent() == retake.target);
		checkBoard(container, editor);

		editor.prevNode(1);
		checkBoard(container, editor);
		editor.prevNode(-1);
		CHECK(editor.getCurrent() == editor.getRoot());
		checkBoard(container, editor);
		editor.nextNode(-1);
		checkBoard(container, editor);
	});
});

besogo.addTest("BoardDisplay", "StoneChangingColorIsRedrawn", function()
{
	let root = besogo.makeGameRoot();
	let black = root.registerMove(1, 1);
	let white = root.registerMove(2, 2).registerMove(1, 1);
	let editor = besogo.makeEditor(19, 19, {});
	editor.loadRoot(root);
	withBoardDisplay(editor, container =>
	{
		editor.setCurrent(black);
		checkBoard(container, editor);
		editor.setCurrent(white);
		checkBoard(container, editor);
		editor.setCurrent(black);
		checkBoard(container, editor);
	});
});

besogo.addTest("BoardDisplay", "HoverShowsTheStoneOnlyUnderTheMouse", function()
{
	let editor = besogo.makeEditor(19, 19, {});
	editor.loadRoot(besogo.makeGameRoot());
	withBoardDisplay(editor, container =>
	{
		let hoverGroup = container.querySelector("svg > g[opacity='0.35']");
		let eventTarget = container.querySelectorAll("svg > rect[fill='transparent']")[(3 - 1) * 19 + (3 - 1)];
		let stoneUnderTheMouse = () =>
		{
			CHECK_EQUALS(hoverGroup.children.length, 1);
			let stone = hoverGroup.children[0];
			CHECK_EQUALS(stone.getAttribute("cx"), Number(eventTarget.getAttribute("x")) + Number(eventTarget.getAttribute("width")) / 2);
			return stone.getAttribute("class");
		};
		CHECK_EQUALS(hoverGroup.children.length, 0);

		eventTarget.dispatchEvent(new MouseEvent("mouseover"));
		CHECK_EQUALS(stoneUnderTheMouse(), "besogo-svg-blackStone");

		editor.click(5, 5, false, false);
		CHECK_EQUALS(stoneUnderTheMouse(), "besogo-svg-whiteStone");

		eventTarget.dispatchEvent(new MouseEvent("mouseout"));
		CHECK_EQUALS(hoverGroup.children.length, 0);
	});
});
