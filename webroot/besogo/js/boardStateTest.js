// Board state and prototype chain tests

besogo.addTest("BoardState", "PrototypeChainInheritance", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(1, 1, BLACK);

	let m1 = root.registerMove(3, 3);
	let m2 = m1.registerMove(4, 4);

	CHECK_EQUALS(m2.getStone(1, 1), BLACK);
	CHECK_EQUALS(m2.getStone(3, 3), BLACK);
	CHECK_EQUALS(m2.getStone(4, 4), WHITE);

	CHECK_EQUALS(root.getStone(3, 3), EMPTY);
	CHECK_EQUALS(root.getStone(4, 4), EMPTY);
	CHECK_EQUALS(m1.getStone(4, 4), EMPTY);
});

besogo.addTest("BoardState", "CapturedStoneVisibleInParent", function()
{
	let root = besogo.makeGameRoot(5, 5);
	root.placeSetup(2, 1, BLACK);
	root.placeSetup(1, 2, BLACK);
	root.placeSetup(3, 2, BLACK);
	root.placeSetup(2, 2, WHITE);

	CHECK_EQUALS(root.getStone(2, 2), WHITE);

	let m1 = root.registerMove(2, 3);
	CHECK_EQUALS(m1.getStone(2, 2), EMPTY);
	CHECK_EQUALS(root.getStone(2, 2), WHITE);
});

besogo.addTest("BoardState", "SuicideRejected", function()
{
	let root = besogo.makeGameRoot(5, 5);
	root.placeSetup(1, 1, WHITE);
	root.placeSetup(2, 2, WHITE);
	root.placeSetup(1, 3, WHITE);

	let child = root.makeChild();
	let result = child.playMove(1, 2);
	CHECK(!result);
});

besogo.addTest("BoardState", "KoMoveRejected", function()
{
	let root = besogo.makeGameRoot(5, 5);
	root.placeSetup(2, 1, BLACK);
	root.placeSetup(3, 1, WHITE);
	root.placeSetup(1, 2, BLACK);
	root.placeSetup(2, 2, WHITE);
	root.placeSetup(4, 2, WHITE);
	root.placeSetup(2, 3, BLACK);
	root.placeSetup(3, 3, WHITE);

	let capture = root.registerMove(3, 2);
	CHECK_EQUALS(capture.getStone(2, 2), EMPTY);
	CHECK_EQUALS(capture.getStone(3, 2), BLACK);
	CHECK_EQUALS(capture.move.captures, 1);

	let child = capture.makeChild();
	let koResult = child.playMove(2, 2);
	CHECK(!koResult);
});

besogo.addTest("BoardState", "SnapbackAllowed", function()
{
	let root = besogo.makeGameRoot(5, 5);
	root.placeSetup(2, 1, WHITE);
	root.placeSetup(1, 2, WHITE);
	root.placeSetup(3, 2, WHITE);

	let m1 = root.registerMove(2, 2);
	CHECK(m1.getStone(2, 2) !== EMPTY);
	CHECK_EQUALS(m1.getStone(2, 2), BLACK);
});

besogo.addTest("BoardState", "MultipleSetupStones", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(1, 1, BLACK);
	root.placeSetup(2, 2, WHITE);
	root.placeSetup(3, 3, BLACK);
	root.placeSetup(4, 4, WHITE);

	CHECK_EQUALS(root.getStone(1, 1), BLACK);
	CHECK_EQUALS(root.getStone(2, 2), WHITE);
	CHECK_EQUALS(root.getStone(3, 3), BLACK);
	CHECK_EQUALS(root.getStone(4, 4), WHITE);
	CHECK_EQUALS(root.getStone(5, 5), EMPTY);
});

besogo.addTest("BoardState", "GetStoneOutOfBounds", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.getStone(0, 0), EMPTY);
	CHECK_EQUALS(root.getStone(10, 10), EMPTY);
	CHECK_EQUALS(root.getStone(-1, 5), EMPTY);
});

besogo.addTest("BoardState", "SetupStoneRemoval", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(3, 3, BLACK);
	CHECK_EQUALS(root.getStone(3, 3), BLACK);

	root.placeSetup(3, 3, 0);
	CHECK_EQUALS(root.getStone(3, 3), EMPTY);
});

besogo.addTest("BoardState", "SetupOverwrite", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(3, 3, BLACK);
	CHECK_EQUALS(root.getStone(3, 3), BLACK);

	root.placeSetup(3, 3, WHITE);
	CHECK_EQUALS(root.getStone(3, 3), WHITE);
});

besogo.addTest("BoardState", "CornerCapture", function()
{	// Capture a stone in the corner (only 2 liberties)
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(1, 1, WHITE);
	root.placeSetup(2, 1, BLACK);

	let m1 = root.registerMove(1, 2); // BLACK captures W(1,1)
	CHECK_EQUALS(m1.getStone(1, 1), EMPTY);
	CHECK(m1.move.captures > 0);
});

besogo.addTest("BoardState", "EdgeCapture", function()
{
	// Capture a stone on the edge (3 liberties normally)
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(5, 1, WHITE);
	root.placeSetup(4, 1, BLACK);
	root.placeSetup(6, 1, BLACK);

	let m1 = root.registerMove(5, 2); // BLACK captures W(5,1)
	CHECK_EQUALS(m1.getStone(5, 1), EMPTY);
});

besogo.addTest("BoardState", "GroupCapture", function()
{
	// Capture entire group of 3 stones
	let root = besogo.makeGameRoot(9, 9);
	root.placeSetup(2, 1, WHITE);
	root.placeSetup(3, 1, WHITE);
	root.placeSetup(4, 1, WHITE);
	root.placeSetup(1, 1, BLACK);
	root.placeSetup(5, 1, BLACK);
	root.placeSetup(2, 2, BLACK);
	root.placeSetup(3, 2, BLACK);

	let m1 = root.registerMove(4, 2); // BLACK captures 3 white stones
	CHECK_EQUALS(m1.getStone(2, 1), EMPTY);
	CHECK_EQUALS(m1.getStone(3, 1), EMPTY);
	CHECK_EQUALS(m1.getStone(4, 1), EMPTY);
});

besogo.addTest("BoardState", "SamePositionAs", function()
{
	// Two paths reaching same position via transposition
	let root = besogo.makeGameRoot(5, 5);

	// Path A: B(1,1) W(2,2) B(3,3) W(4,4)
	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(2, 2);
	let a3 = a2.registerMove(3, 3);
	let a4 = a3.registerMove(4, 4);

	// Path B: B(3,3) W(4,4) B(1,1) W(2,2)
	let b1 = root.registerMove(3, 3);
	let b2 = b1.registerMove(4, 4);
	let b3 = b2.registerMove(1, 1);
	let b4 = b3.registerMove(2, 2);

	// Both paths end with B@{1,1, 3,3}, W@{2,2, 4,4}
	CHECK(a4.samePositionAs(b4));
	CHECK(b4.samePositionAs(a4));
});

besogo.addTest("BoardState", "DifferentPositionNotSame", function()
{
	let root = besogo.makeGameRoot(5, 5);
	let a1 = root.registerMove(1, 1);
	let b1 = root.registerMove(2, 2);
	CHECK(!a1.samePositionAs(b1));
});

besogo.addTest("BoardState", "GetSize", function()
{
	let root9 = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root9.getSize().x, 9);
	CHECK_EQUALS(root9.getSize().y, 9);

	let root19 = besogo.makeGameRoot(19, 19);
	CHECK_EQUALS(root19.getSize().x, 19);
	CHECK_EQUALS(root19.getSize().y, 19);

	let rootRect = besogo.makeGameRoot(13, 9);
	CHECK_EQUALS(rootRect.getSize().x, 13);
	CHECK_EQUALS(rootRect.getSize().y, 9);
});
