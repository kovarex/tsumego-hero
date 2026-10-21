besogo.addTest("GameRoot", "Empty", function()
{
  let root = besogo.makeGameRoot();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "OneMove", function()
{
  let root = besogo.makeGameRoot();
  root.registerMove(5, 5);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);
});

besogo.addTest("GameRoot", "RemoveOneChild", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  child.destroy();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "RemoveVariation", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);

  child.registerMove(6, 6);
  CHECK(child.hasChildIncludingVirtual());
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(child.children.length, 1);
  CHECK_EQUALS(root.nodeHashTable.size(), 2);
  child.destroy();

  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});


besogo.addTest("GameRoot", "TwoOrderOfMovesLeadToTheSameNode", function()
{
  let root = besogo.makeGameRoot();
  let finalChild = root.registerMove(1, 1).registerMove(1, 2).registerMove(2, 1).registerMove(2, 2);
  let otherOrder = root.registerMove(2, 1).registerMove(2, 2).registerMove(1, 1);

  CHECK_EQUALS(otherOrder.virtualChildren.length, 1);
  CHECK(otherOrder.virtualChildren[0].target == finalChild);
  CHECK_EQUALS(finalChild.virtualParents.length, 1);
  CHECK(finalChild.virtualParents[0] == otherOrder);
});

besogo.addTest("GameRoot", "LocalEditDoesntBreakValidVariation", function()
{
	let root = besogo.makeGameRoot();
	let move1 = root.registerMove(1, 1);
	let move2 = move1.registerMove(1, 2);
	let move3 = move2.registerMove(1, 3);

	CHECK_EQUALS(move1.correct, CORRECT_BAD);
	CHECK_EQUALS(move2.correct, CORRECT_BAD);
	CHECK_EQUALS(move3.correct, CORRECT_BAD);
	move3.setCorrectSource(CORRECT_GOOD);
	CHECK_EQUALS(move1.correct, CORRECT_GOOD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move3.correct, CORRECT_GOOD);

	// moves are marked as visited, simulating we already went through these
	move1.visited = true;
	move2.visited = true;

	let localEditMove = move2.makeChild();
	localEditMove.localEdit = true;
	localEditMove.playMove(2, 3);
	move2.registerChild(localEditMove);

	CHECK(move2.children.length, 2);
	CHECK(move2.children[1].localEdit);
	CHECK_EQUALS(move1.correct, CORRECT_GOOD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move3.correct, CORRECT_GOOD);
});

besogo.addTest("GameRoot", "LocalEditDoesntBreakStatusBasedProblem", function()
{
	let root = besogo.makeGameRoot();
	root.goal = GOAL_KILL;
	let move1 = root.registerMove(1, 1);
	let move2 = move1.registerMove(1, 2);

	let move3 = move2.makeChild();
	move3.playMove(1, 3);
	move3.statusSource = besogo.makeStatusSimple(STATUS_DEAD);
	move2.registerChild(move3); // goal is to kill and this has status dead, so everything should be good

	CHECK_EQUALS(move1.correct, CORRECT_GOOD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move3.correct, CORRECT_GOOD);

	// moves are marked as visited, simulating we already went through these
	move1.visited = true;
	move2.visited = true;

	let localEditMove = move2.makeChild();
	localEditMove.localEdit = true;
	localEditMove.playMove(2, 3);
	move2.registerChild(localEditMove);

	CHECK(move2.children.length, 2);
	CHECK(move2.children[1].localEdit);
	CHECK_EQUALS(move1.correct, CORRECT_GOOD);
	CHECK_EQUALS(move2.correct, CORRECT_GOOD);
	CHECK_EQUALS(move3.correct, CORRECT_GOOD);
});

besogo.addTest("GameRoot", "KoComparisonRequiresBothCoordinatesToMatch", function()
{
	// Position A: ko point at (2,1)
	// B(1,1) W(2,1) B(3,1) on row 1, W(1,2) W(3,2) W(2,3) surround (2,2)
	// Black captures W(2,1) by playing at (2,2)
	let rootA = besogo.makeGameRoot(5, 5);
	rootA.placeSetup(1, 1, BLACK);
	rootA.placeSetup(3, 1, BLACK);
	rootA.placeSetup(2, 1, WHITE);
	rootA.placeSetup(1, 2, WHITE);
	rootA.placeSetup(3, 2, WHITE);
	rootA.placeSetup(2, 3, WHITE);
	let moveA = rootA.registerMove(2, 2); // Black captures W(2,1), ko at (2,1)

	// Position B: ko point at (2,5) — same x, different y
	let rootB = besogo.makeGameRoot(5, 5);
	rootB.placeSetup(1, 5, BLACK);
	rootB.placeSetup(3, 5, BLACK);
	rootB.placeSetup(2, 5, WHITE);
	rootB.placeSetup(1, 4, WHITE);
	rootB.placeSetup(3, 4, WHITE);
	rootB.placeSetup(2, 3, WHITE);
	let moveB = rootB.registerMove(2, 4); // Black captures W(2,5), ko at (2,5)

	// Same position should match
	CHECK(moveA.hasSameKoStateAs(moveA));

	// Different ko points sharing only x coordinate must NOT match
	CHECK(!moveA.hasSameKoStateAs(moveB));
});
