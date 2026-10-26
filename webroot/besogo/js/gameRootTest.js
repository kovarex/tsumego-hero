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

besogo.addTest("GameRoot", "CapturedStoneLeavesTheHash", function()
{
	// the hash of a move is the hash of its parent changed by the points of the move, the captures included
	let root = besogo.makeGameRoot();
	let capture = root.registerMove(2, 1).registerMove(1, 1).registerMove(1, 2);
	CHECK(!capture.getStone(1, 1));

	let samePosition = besogo.makeGameRoot();
	samePosition.placeSetup(2, 1, BLACK);
	samePosition.placeSetup(1, 2, BLACK);
	CHECK_EQUALS(capture.getHash(), samePosition.getHash());
});

besogo.addTest("GameRoot", "ShiftedPositionsHaveDifferentHashes", function()
{
	// The string hashes of the points grow with their coordinates, so the sums over these two boards were equal
	let root = besogo.makeGameRoot();
	let first = root.registerMove(1, 1).registerMove(2, 2);
	let second = root.registerMove(3, 3).registerMove(4, 4);
	CHECK(first.getHash() != second.getHash());
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

besogo.addTest("GameRoot", "DiffMarksAddedAndRemovedMoves", function()
{
	let newRoot = besogo.makeGameRoot(5, 5);
	newRoot.registerMove(1, 1);
	newRoot.registerMove(2, 2);

	let oldRoot = besogo.makeGameRoot(5, 5);
	oldRoot.registerMove(1, 1);
	oldRoot.registerMove(3, 3);

	newRoot.incorporateDiff(oldRoot);

	CHECK_EQUALS(newRoot.children.length, 3);
	CHECK(!newRoot.children[0].diffInfo);
	CHECK_EQUALS(newRoot.children[1].diffInfo.type, DIFF_ADDED);
	CHECK_EQUALS(newRoot.children[2].diffInfo.type, DIFF_REMOVED);
	CHECK_EQUALS(newRoot.children[2].move.x, 3);
});

besogo.addTest("GameRoot", "DiffSkipsRemovedMoveIllegalInTheNewPosition", function()
{
	// the new version has a stone where the old version played, so the removed move can't be shown
	let newRoot = besogo.makeGameRoot(5, 5);
	newRoot.placeSetup(3, 3, WHITE);
	newRoot.registerMove(2, 2);

	let oldRoot = besogo.makeGameRoot(5, 5);
	oldRoot.registerMove(3, 3);
	oldRoot.registerMove(4, 4);

	newRoot.incorporateDiff(oldRoot);

	CHECK_EQUALS(newRoot.children.length, 2);
	CHECK_EQUALS(newRoot.children[0].diffInfo.type, DIFF_ADDED);
	CHECK_EQUALS(newRoot.children[1].diffInfo.type, DIFF_REMOVED);
	CHECK_EQUALS(newRoot.children[1].move.x, 4);
});

besogo.addTest("GameRoot", "CanStillEndCorrectTellsTheLostLineApart", function()
{
	let root = besogo.makeGameRoot();
	let correct = root.registerMove(1, 1);
	correct.registerMove(1, 2).registerMove(2, 1).setCorrectSource(true);
	let wrong = root.registerMove(5, 5);
	let refutation = wrong.registerMove(5, 6);
	let wrongEnd = refutation.registerMove(5, 7);

	CHECK(root.canStillEndCorrect());
	CHECK(correct.canStillEndCorrect());
	CHECK(!wrong.canStillEndCorrect());
	CHECK(!refutation.canStillEndCorrect());
	CHECK(!wrongEnd.canStillEndCorrect());
});

besogo.addTest("GameRoot", "CanStillEndCorrectWhileTheOpponentMayPickAReplyThatLoses", function()
{
	// The move is wrong because of the refutation, but the autoplay may still pick the other reply
	let root = besogo.makeGameRoot();
	let move = root.registerMove(1, 1);
	let refutation = move.registerMove(1, 2);
	refutation.registerMove(1, 3);
	let losingReply = move.registerMove(2, 2);
	losingReply.registerMove(2, 3).setCorrectSource(true);

	CHECK_EQUALS(move.correct, CORRECT_BAD);
	CHECK(move.canStillEndCorrect());
	CHECK(!refutation.canStillEndCorrect());
	CHECK(losingReply.canStillEndCorrect());
});

besogo.addTest("GameRoot", "CanStillEndCorrectThroughATransposition", function()
{
	let root = besogo.makeGameRoot();
	let finalChild = root.registerMove(1, 1).registerMove(1, 2).registerMove(2, 1).registerMove(2, 2);
	finalChild.registerMove(3, 3).setCorrectSource(true);
	let otherOrderStart = root.registerMove(2, 1);
	let otherOrder = otherOrderStart.registerMove(2, 2).registerMove(1, 1);

	CHECK_EQUALS(otherOrder.children.length, 0);
	CHECK(otherOrder.canStillEndCorrect());
	CHECK(otherOrderStart.canStillEndCorrect());
});

besogo.addTest("GameRoot", "CanStillEndCorrectIsFalseOffTheTree", function()
{
	let root = besogo.makeGameRoot();
	root.registerMove(1, 1).registerMove(1, 2).registerMove(2, 1).setCorrectSource(true);
	let localEditMove = root.makeChild();
	localEditMove.localEdit = true;
	localEditMove.playMove(5, 5);
	root.registerChild(localEditMove);

	CHECK(!localEditMove.canStillEndCorrect());
	CHECK(root.canStillEndCorrect());
});

besogo.addTest("GameRoot", "TenThousandYearsKoBranchIsCorrectOnlyWhenLiving", function()
{
	let root = besogo.makeGameRoot();
	root.goal = GOAL_LIVE;

	let tenThousandYearsKoMove = root.makeChild();
	tenThousandYearsKoMove.playMove(1, 1);
	tenThousandYearsKoMove.statusSource = besogo.makeStatusSimple(STATUS_TEN_THOUSAND_YEARS_KO);
	root.registerChild(tenThousandYearsKoMove);

	let koMove = root.makeChild();
	koMove.playMove(2, 2);
	koMove.statusSource = besogo.makeStatusSimple(STATUS_KO);
	root.registerChild(koMove);

	// the attacker has to approach before a ten thousand years ko starts, so it is the better result for the defender
	CHECK_EQUALS(tenThousandYearsKoMove.correct, CORRECT_GOOD);
	CHECK_EQUALS(koMove.correct, CORRECT_BAD);

	// and the worse one for the attacker
	root.goal = GOAL_KILL;
	besogo.updateCorrectValues(root);
	CHECK_EQUALS(tenThousandYearsKoMove.correct, CORRECT_BAD);
	CHECK_EQUALS(koMove.correct, CORRECT_GOOD);
});
