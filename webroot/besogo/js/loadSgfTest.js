// Tests for loadSgf.js — uses a mock editor since loadSgf requires editor interface

// Helper: creates a minimal mock editor for loadSgf testing
function makeMockEditor()
{
	let mock = {};
	mock._root = null;
	mock._variantStyle = 0;
	mock._gameInfo = {};
	mock._listeners = [];

	mock.loadRoot = function(root) { mock._root = root; };
	mock.getRoot = function() { return mock._root; };
	mock.setVariantStyle = function(v) { mock._variantStyle = v; };
	mock.getVariantStyle = function() { return mock._variantStyle; };
	mock.setGameInfo = function(info) { mock._gameInfo = info; };
	mock.getGameInfo = function() { return mock._gameInfo; };
	mock.notifyListeners = function() {};

	return mock;
}

besogo.addTest("LoadSgf", "LoadsSimpleSgf", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9])");

	besogo.loadSgf(sgf, editor);

	CHECK(editor._root !== null);
	CHECK_EQUALS(editor._root.getSize().x, 9);
	CHECK_EQUALS(editor._root.getSize().y, 9);
});

besogo.addTest("LoadSgf", "LoadsNonSquareBoard", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9:13])");

	besogo.loadSgf(sgf, editor);

	CHECK_EQUALS(editor._root.getSize().x, 9);
	CHECK_EQUALS(editor._root.getSize().y, 13);
});

besogo.addTest("LoadSgf", "LoadsMoveSequence", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9];B[ee];W[dd])");

	besogo.loadSgf(sgf, editor);

	let root = editor._root;
	CHECK_EQUALS(root.children.length, 1);

	let m1 = root.children[0];
	CHECK_EQUALS(m1.getStone(5, 5), -1); // Black at e,e = (5,5)
	CHECK_EQUALS(m1.children.length, 1);

	let m2 = m1.children[0];
	CHECK_EQUALS(m2.getStone(4, 4), 1); // White at d,d = (4,4)
});

besogo.addTest("LoadSgf", "LoadsSetupStones", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]AB[aa][bb]AW[cc])");

	besogo.loadSgf(sgf, editor);

	let root = editor._root;
	CHECK_EQUALS(root.getStone(1, 1), -1); // Black at a,a
	CHECK_EQUALS(root.getStone(2, 2), -1); // Black at b,b
	CHECK_EQUALS(root.getStone(3, 3), 1);  // White at c,c
});

besogo.addTest("LoadSgf", "LoadsBranching", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9];B[ee](;W[dd])(;W[dc]))");

	besogo.loadSgf(sgf, editor);

	let root = editor._root;
	let m1 = root.children[0]; // B[ee]
	CHECK_EQUALS(m1.children.length, 2);

	// First branch: W[dd] = white at (4,4)
	CHECK_EQUALS(m1.children[0].getStone(4, 4), 1);
	// Second branch: W[dc] = white at (4,3)
	CHECK_EQUALS(m1.children[1].getStone(4, 3), 1);
});

besogo.addTest("LoadSgf", "LoadsComment", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]C[Hello World])");

	besogo.loadSgf(sgf, editor);

	CHECK_EQUALS(editor._root.comment, "Hello World");
});

besogo.addTest("LoadSgf", "LoadsGameInfo", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]PB[Alice]PW[Bob]KM[6.5])");

	besogo.loadSgf(sgf, editor);

	CHECK_EQUALS(editor._gameInfo.PB, "Alice");
	CHECK_EQUALS(editor._gameInfo.PW, "Bob");
	CHECK_EQUALS(editor._gameInfo.KM, "6.5");
});

besogo.addTest("LoadSgf", "DefaultSizeIs19", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4])");

	besogo.loadSgf(sgf, editor);

	CHECK_EQUALS(editor._root.getSize().x, 19);
	CHECK_EQUALS(editor._root.getSize().y, 19);
});

besogo.addTest("LoadSgf", "LoadsVariantStyle", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]ST[2])");

	besogo.loadSgf(sgf, editor);

	CHECK_EQUALS(editor._variantStyle, 2);
});

besogo.addTest("LoadSgf", "PassMove", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9];B[])");

	besogo.loadSgf(sgf, editor);

	let root = editor._root;
	CHECK_EQUALS(root.children.length, 1);
	// Pass move: coordinates are (0,0)
	let m1 = root.children[0];
	CHECK_EQUALS(m1.moveNumber, 1);
});

besogo.addTest("LoadSgf", "MarkupCR", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]CR[ee])");

	besogo.loadSgf(sgf, editor);

	let markup = editor._root.getMarkup(5, 5);
	CHECK_EQUALS(markup, 1); // CR = 1
});

besogo.addTest("LoadSgf", "MarkupLabel", function()
{
	let editor = makeMockEditor();
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]LB[ee:A])");

	besogo.loadSgf(sgf, editor);

	let markup = editor._root.getMarkup(5, 5);
	CHECK_EQUALS(markup, "A");
});

besogo.addTest("LoadSgf", "SetupEmptyStones", function()
{
	let editor = makeMockEditor();
	// Place black stone, then remove with AE
	let sgf = besogo.parseSgf("(;GM[1]FF[4]SZ[9]AB[ee]AE[ee])");

	besogo.loadSgf(sgf, editor);

	// AE should set empty
	let root = editor._root;
	CHECK_EQUALS(root.getStone(5, 5), 0);
});
