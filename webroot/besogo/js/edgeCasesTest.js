// Edge case tests for game tree and board operations

besogo.addTest("EdgeCases", "EmptyTreeCorrectValues", function()
{
	let root = besogo.makeGameRoot(9, 9);

	CHECK_EQUALS(root.correct, CORRECT_EMPTY);
	CHECK_EQUALS(root.children.length, 0);
});

besogo.addTest("EdgeCases", "SingleMoveTree", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m = root.registerMove(5, 5);
	CHECK_EQUALS(m.getStone(5, 5), -1);
	CHECK_EQUALS(m.moveNumber, 1);
	CHECK_EQUALS(root.children.length, 1);
});

besogo.addTest("EdgeCases", "PassMoveCountsAsMoveNode", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let passNode = root.registerMove(0, 0);
	CHECK_EQUALS(passNode.moveNumber, 1);
	CHECK_EQUALS(root.children.length, 1);
});

besogo.addTest("EdgeCases", "ToggleCorrectSourceMultipleTimes", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);

	m2.setCorrectSource(true);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);

	m2.setCorrectSource(false);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);

	m2.setCorrectSource(true);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
});

besogo.addTest("EdgeCases", "LargeBoard19x19", function()
{
	let root = besogo.makeGameRoot(19, 19);
	let m = root.registerMove(19, 19);
	CHECK_EQUALS(m.getStone(19, 19), -1);
	CHECK_EQUALS(m.getStone(1, 1), 0);
	CHECK_EQUALS(m.getSize().x, 19);
	CHECK_EQUALS(m.getSize().y, 19);
});

besogo.addTest("EdgeCases", "MoveNumberIncrement", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(m1.moveNumber, 1);
	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(m2.moveNumber, 2);
	let m3 = m2.registerMove(3, 3);
	CHECK_EQUALS(m3.moveNumber, 3);
});

besogo.addTest("EdgeCases", "CaptureUpdatesCapCount", function()
{
	// Surround and capture a white stone at (1,1)
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(1, 1, 1); // white at corner
	root.placeSetup(2, 1, -1); // black neighbor

	let m1 = root.registerMove(1, 2); // black captures
	CHECK_EQUALS(m1.getStone(1, 1), EMPTY);
	CHECK(m1.move.captures > 0);
});

besogo.addTest("EdgeCases", "RectangularBoard", function()
{
	let root = besogo.makeGameRoot(9, 13);
	let m = root.registerMove(1, 13);
	CHECK_EQUALS(m.getStone(1, 13), -1);
	CHECK_EQUALS(root.getSize().x, 9);
	CHECK_EQUALS(root.getSize().y, 13);
});

besogo.addTest("EdgeCases", "GetSizeOnChild", function()
{
	let root = besogo.makeGameRoot(13, 13);
	let m = root.registerMove(5, 5);
	CHECK_EQUALS(m.getSize().x, 13);
	CHECK_EQUALS(m.getSize().y, 13);
});

besogo.addTest("EdgeCases", "BranchingFromSameNode", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let a = root.registerMove(1, 1);
	let b = root.registerMove(2, 2);
	let c = root.registerMove(3, 3);

	CHECK_EQUALS(root.children.length, 3);
	CHECK_EQUALS(a.getStone(1, 1), -1);
	CHECK_EQUALS(b.getStone(2, 2), -1);
	CHECK_EQUALS(c.getStone(3, 3), -1);
});

besogo.addTest("EdgeCases", "RegisterMoveAddsChild", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.children.length, 0);
	root.registerMove(5, 5);
	CHECK_EQUALS(root.children.length, 1);
	CHECK_EQUALS(root.children[0].getStone(5, 5), -1);
});
