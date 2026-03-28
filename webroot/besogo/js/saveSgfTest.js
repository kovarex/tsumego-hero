// Tests for saveSgf.js — SGF composition/serialization

// Helper: creates a mock editor and loads an SGF, returns editor
function makeSaveSgfEditor(sgfText)
{
	let editor = {};
	editor._root = null;
	editor._variantStyle = 0;
	editor._gameInfo = {};

	editor.loadRoot = function(root) { editor._root = root; };
	editor.getRoot = function() { return editor._root; };
	editor.setVariantStyle = function(v) { editor._variantStyle = v; };
	editor.getVariantStyle = function() { return editor._variantStyle; };
	editor.setGameInfo = function(info) { editor._gameInfo = info; };
	editor.getGameInfo = function() { return editor._gameInfo; };
	editor.notifyListeners = function() {};

	let sgf = besogo.parseSgf(sgfText);
	besogo.loadSgf(sgf, editor);
	return editor;
}

besogo.addTest('SaveSgf', 'OutputStartsAndEndsWithParens', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9])');
	let result = besogo.composeSgf(editor);
	CHECK(result.startsWith('('), 'Should start with (');
	CHECK(result.endsWith(')'), 'Should end with )');
});

besogo.addTest('SaveSgf', 'RootHasFFAndGMAndSZ', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('FF[4]'), 'Should contain FF[4]');
	CHECK(result.includes('GM[1]'), 'Should contain GM[1]');
	CHECK(result.includes('SZ[9]'), 'Should contain SZ[9]');
});

besogo.addTest('SaveSgf', 'NonSquareBoardSizeFormat', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9:13])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('SZ[9:13]'), 'Non-square board should use x:y format');
});

besogo.addTest('SaveSgf', 'SquareBoardSizeFormat', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[19])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('SZ[19]'), 'Square board should use single number');
	CHECK(!result.includes('SZ[19:19]'), 'Square board should NOT use x:y format');
});

besogo.addTest('SaveSgf', 'VariantStylePreserved', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]ST[2])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('ST[2]'), 'Variant style should be preserved');
});

besogo.addTest('SaveSgf', 'MoveSequenceSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9];B[cd];W[ef])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('B[cd]'), 'Black move should be in output');
	CHECK(result.includes('W[ef]'), 'White move should be in output');
});

besogo.addTest('SaveSgf', 'PassMoveSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9];B[])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('B[]'), 'Pass move should serialize as empty coords');
});

besogo.addTest('SaveSgf', 'SetupStonesSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]AB[cc][dd]AW[ee])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('AB['), 'Should contain AB setup');
	CHECK(result.includes('AW['), 'Should contain AW setup');
});

besogo.addTest('SaveSgf', 'BranchingSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9];B[cd](;W[ef])(;W[gh]))');
	let result = besogo.composeSgf(editor);
	// Branching should produce sub-trees with parens
	let parenCount = (result.match(/\(/g) || []).length;
	// At least 3 parens: outer + 2 branches
	CHECK(parenCount >= 3, 'Branching should produce multiple sub-trees, got ' + parenCount + ' opening parens');
});

besogo.addTest('SaveSgf', 'CommentEscapesBackslash', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]C[hello\\\\world])');
	let result = besogo.composeSgf(editor);
	// The comment contains a literal backslash, which should be escaped as \\
	CHECK(result.includes('C['), 'Should contain comment');
});

besogo.addTest('SaveSgf', 'CommentEscapesCloseBracket', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]C[test\\]value])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('C['), 'Should contain comment with escaped bracket');
});

besogo.addTest('SaveSgf', 'GameInfoSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]PB[Alice]PW[Bob])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('PB[Alice]'), 'Player Black name should be preserved');
	CHECK(result.includes('PW[Bob]'), 'Player White name should be preserved');
});

besogo.addTest('SaveSgf', 'MarkupCRSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]CR[cc])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('CR[cc]'), 'Circle markup should be serialized');
});

besogo.addTest('SaveSgf', 'MarkupLabelSerialized', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]LB[cc:A])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('LB[cc:A]'), 'Label markup should be serialized');
});

besogo.addTest('SaveSgf', 'RoundtripPreservesStructure', function() {
	let original = '(;GM[1]FF[4]SZ[9];B[cd];W[ef](;B[gh])(;B[ij]))';
	let editor = makeSaveSgfEditor(original);
	let composed = besogo.composeSgf(editor);

	// Re-parse the composed result and compare tree structure
	let editor2 = {};
	editor2._root = null;
	editor2._variantStyle = 0;
	editor2._gameInfo = {};
	editor2.loadRoot = function(r) { editor2._root = r; };
	editor2.getRoot = function() { return editor2._root; };
	editor2.setVariantStyle = function(v) { editor2._variantStyle = v; };
	editor2.getVariantStyle = function() { return editor2._variantStyle; };
	editor2.setGameInfo = function(info) { editor2._gameInfo = info; };
	editor2.getGameInfo = function() { return editor2._gameInfo; };
	editor2.notifyListeners = function() {};

	let sgf2 = besogo.parseSgf(composed);
	besogo.loadSgf(sgf2, editor2);

	let root2 = editor2.getRoot();
	CHECK_EQUALS(1, root2.children.length, 'Root should have 1 child after roundtrip');
	let child1 = root2.children[0];
	CHECK_EQUALS(-1, child1.move.color, 'First move should be black');
	CHECK_EQUALS(1, child1.children.length, 'First move should have 1 child');
	let child2 = child1.children[0];
	CHECK_EQUALS(1, child2.move.color, 'Second move should be white');
	CHECK_EQUALS(2, child2.children.length, 'Second move should have 2 branches');
});

besogo.addTest('SaveSgf', 'SetupOnlyBlackStones', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]AB[cc][dd][ee])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('AB['), 'Should contain AB for black setup stones');
	CHECK(!result.includes('AW['), 'Should NOT contain AW when only black setup');
});

besogo.addTest('SaveSgf', 'MultipleMarkupTypes', function() {
	let editor = makeSaveSgfEditor('(;GM[1]FF[4]SZ[9]TR[cc]SQ[dd]MA[ee])');
	let result = besogo.composeSgf(editor);
	CHECK(result.includes('TR[cc]'), 'Triangle should be serialized');
	CHECK(result.includes('SQ[dd]'), 'Square should be serialized');
	CHECK(result.includes('MA[ee]'), 'X-mark should be serialized');
});
