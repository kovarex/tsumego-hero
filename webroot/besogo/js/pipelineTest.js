// Pipeline tests — integration tests for stone placement, captures, tree updates

besogo.addTest("Pipeline", "StoneCapturePropagatesCorrectly", function()
{
	let root = besogo.makeGameRoot(5, 5);
	root.placeSetup(2, 1, BLACK);
	root.placeSetup(1, 2, BLACK);
	root.placeSetup(3, 2, BLACK);
	root.placeSetup(2, 2, WHITE);

	let move1 = root.registerMove(2, 3);

	CHECK_EQUALS(move1.getStone(2, 2), EMPTY);
	CHECK_EQUALS(move1.getStone(2, 1), BLACK);
	CHECK_EQUALS(move1.getStone(1, 2), BLACK);
	CHECK_EQUALS(move1.getStone(3, 2), BLACK);
	CHECK_EQUALS(move1.getStone(2, 3), BLACK);
});

besogo.addTest("Pipeline", "CorrectValuesUpdateOnNewBranch", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let move1 = root.registerMove(3, 3);
	let move2 = move1.registerMove(4, 4);
	let move3 = move2.registerMove(5, 5);

	CHECK_EQUALS(move1.correct, CORRECT_BAD);
	CHECK_EQUALS(move2.correct, CORRECT_BAD);
	CHECK_EQUALS(move3.correct, CORRECT_BAD);

	move3.setCorrectSource(true);
	CHECK_EQUALS(move3.correct, CORRECT_GOOD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move1.correct, CORRECT_GOOD);

	let move3alt = move2.registerMove(6, 6);
	CHECK_EQUALS(move3alt.correct, CORRECT_BAD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move1.correct, CORRECT_GOOD);

	let move2alt = move1.registerMove(7, 7);
	CHECK_EQUALS(move2alt.correct, CORRECT_BAD);
	CHECK_EQUALS(move1.correct, CORRECT_BAD);
});

besogo.addTest("Pipeline", "NavigationPreservesBoard", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let move1 = root.registerMove(3, 3);
	let move2 = move1.registerMove(4, 4);
	let move3 = move2.registerMove(5, 5);

	CHECK_EQUALS(move3.getStone(3, 3), BLACK);
	CHECK_EQUALS(move3.getStone(4, 4), WHITE);
	CHECK_EQUALS(move3.getStone(5, 5), BLACK);

	CHECK_EQUALS(move1.getStone(3, 3), BLACK);
	CHECK_EQUALS(move1.getStone(4, 4), EMPTY);
	CHECK_EQUALS(move1.getStone(5, 5), EMPTY);

	CHECK_EQUALS(root.getStone(3, 3), EMPTY);
});

besogo.addTest("Pipeline", "VirtualChildrenCreatedOnRegister", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(2, 2);
	let a3 = a2.registerMove(3, 3);

	let b1 = root.registerMove(3, 3);
	let b2 = b1.registerMove(2, 2);

	let hasVirtual = b2.virtualChildren.length > 0;
	CHECK(hasVirtual);
});

besogo.addTest("Pipeline", "MultiCaptureWorks", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(2, 1, WHITE);
	root.placeSetup(1, 1, BLACK);
	root.placeSetup(3, 1, BLACK);
	root.placeSetup(1, 2, WHITE);
	root.placeSetup(1, 3, BLACK);

	let move = root.registerMove(2, 2);

	CHECK_EQUALS(move.getStone(2, 1), EMPTY);
	CHECK_EQUALS(move.getStone(1, 2), EMPTY);
	CHECK_EQUALS(move.getStone(2, 2), BLACK);
});

besogo.addTest("Pipeline", "ClearCorrectValuesResetsEntireTree", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	let m3 = m2.registerMove(3, 3);
	m3.setCorrectSource(true);

	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);

	besogo.clearCorrectValues(root);
	CHECK_EQUALS(root.correct, CORRECT_EMPTY);
	CHECK_EQUALS(m1.correct, CORRECT_EMPTY);
	CHECK_EQUALS(m2.correct, CORRECT_EMPTY);
	CHECK_EQUALS(m3.correct, CORRECT_EMPTY);

	besogo.updateCorrectValues(root);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
	CHECK_EQUALS(m3.correct, CORRECT_GOOD);
});

besogo.addTest("Pipeline", "GetRootFromDeepNode", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	let m3 = m2.registerMove(3, 3);
	let m4 = m3.registerMove(4, 4);
	let m5 = m4.registerMove(5, 5);

	CHECK(m5.getRoot() === root);
	CHECK(m3.getRoot() === root);
	CHECK(root.getRoot() === root);
});

besogo.addTest("Pipeline", "TreeSizeAccurate", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.treeSize(), 1);

	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(root.treeSize(), 2);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(root.treeSize(), 3);

	let m2alt = m1.registerMove(3, 3);
	CHECK_EQUALS(root.treeSize(), 4);
});
