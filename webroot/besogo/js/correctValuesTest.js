// Correct value propagation tests — documents exact semantics of updateCorrectValues
// KEY RULES (GOAL_NONE, correctSource-based):
// - correctSource=true on a leaf → CORRECT_GOOD
// - No correctSource, no children → CORRECT_BAD
// - Solver's turn: one GOOD child → GOOD
// - Opponent's turn: ALL children GOOD → GOOD; any BAD → BAD

besogo.addTest("CorrectValues", "NextMoveAlternates", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.nextMove(), BLACK);

	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(m1.nextMove(), WHITE);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(m2.nextMove(), BLACK);

	let m3 = m2.registerMove(3, 3);
	CHECK_EQUALS(m3.nextMove(), WHITE);

	let m4 = m3.registerMove(4, 4);
	CHECK_EQUALS(m4.nextMove(), BLACK);
});

besogo.addTest("CorrectValues", "SolversMoveIdentification", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.nextMove() == root.firstMove, true);

	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(m1.nextMove() == root.firstMove, false);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(m2.nextMove() == root.firstMove, true);
});

besogo.addTest("CorrectValues", "LeafWithoutSourceIsBad", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);
});

besogo.addTest("CorrectValues", "LeafWithSourceIsGood", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	m1.setCorrectSource(true);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "SetCorrectSourceOnlyOnLeaves", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);

	m1.setCorrectSource(true);
	CHECK_EQUALS(m1.correctSource, false);

	m2.setCorrectSource(true);
	CHECK_EQUALS(m2.correctSource, true);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "SolverPicksBest_OneGoodChildSuffices", function()
{
	let root = besogo.makeGameRoot(9, 9);

	let m1_good = root.registerMove(1, 1);
	m1_good.setCorrectSource(true);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);

	let m1_bad = root.registerMove(2, 2);
	CHECK_EQUALS(m1_bad.correct, CORRECT_BAD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);

	let m1_bad2 = root.registerMove(3, 3);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "SolverAllBad_ResultIsBad", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = root.registerMove(2, 2);

	CHECK_EQUALS(m1.correct, CORRECT_BAD);
	CHECK_EQUALS(m2.correct, CORRECT_BAD);
	CHECK_EQUALS(root.correct, CORRECT_BAD);
});

besogo.addTest("CorrectValues", "OpponentPicksWorst_AllGoodRequired", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	let m2_a = m1.registerMove(2, 2);
	let m2_b = m1.registerMove(3, 3);

	let m3_a = m2_a.registerMove(4, 4);
	m3_a.setCorrectSource(true);
	CHECK_EQUALS(m2_a.correct, CORRECT_GOOD);

	CHECK_EQUALS(m2_b.correct, CORRECT_BAD);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);

	let m3_b = m2_b.registerMove(5, 5);
	m3_b.setCorrectSource(true);
	CHECK_EQUALS(m2_b.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "OpponentOneBadChild_ResultIsBad", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	let m2_good = m1.registerMove(2, 2);
	let m3 = m2_good.registerMove(3, 3);
	m3.setCorrectSource(true);
	CHECK_EQUALS(m2_good.correct, CORRECT_GOOD);

	let m2_bad = m1.registerMove(4, 4);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);
});

besogo.addTest("CorrectValues", "DeepPropagation_3Levels", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	let m3 = m2.registerMove(3, 3);
	let m4 = m3.registerMove(4, 4);

	CHECK_EQUALS(root.correct, CORRECT_BAD);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);
	CHECK_EQUALS(m2.correct, CORRECT_BAD);
	CHECK_EQUALS(m3.correct, CORRECT_BAD);
	CHECK_EQUALS(m4.correct, CORRECT_BAD);

	let m5 = m4.registerMove(5, 5);
	m5.setCorrectSource(true);

	CHECK_EQUALS(m5.correct, CORRECT_GOOD);
	CHECK_EQUALS(m4.correct, CORRECT_GOOD);
	CHECK_EQUALS(m3.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "MixedBranches_ComplexTree", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	let m2a = m1.registerMove(2, 2);
	let m3a = m2a.registerMove(3, 3);
	m3a.setCorrectSource(true);
	CHECK_EQUALS(m2a.correct, CORRECT_GOOD);

	let m2b = m1.registerMove(4, 4);
	let m3b = m2b.registerMove(5, 5);
	CHECK_EQUALS(m3b.correct, CORRECT_BAD);
	CHECK_EQUALS(m2b.correct, CORRECT_BAD);

	CHECK_EQUALS(m1.correct, CORRECT_BAD);
	CHECK_EQUALS(root.correct, CORRECT_BAD);

	let m1b = root.registerMove(6, 6);
	CHECK_EQUALS(m1b.correct, CORRECT_BAD);
	CHECK_EQUALS(root.correct, CORRECT_BAD);

	let m3b_fixed = m2b.registerMove(7, 7);
	m3b_fixed.setCorrectSource(true);

	CHECK_EQUALS(m2b.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "ClearAndRecalculate", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	m2.setCorrectSource(true);

	CHECK_EQUALS(root.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);

	besogo.clearCorrectValues(root);
	CHECK_EQUALS(root.correct, CORRECT_EMPTY);
	CHECK_EQUALS(m1.correct, CORRECT_EMPTY);
	CHECK_EQUALS(m2.correct, CORRECT_EMPTY);

	besogo.updateCorrectValues(root);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "CommentPlusAutoMarksCorrectSource", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	m1.comment = "+good move";

	besogo.updateCorrectValues(root);

	CHECK_EQUALS(m1.correctSource, true);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.comment, "good move");
});

besogo.addTest("CorrectValues", "RemoveCorrectSourceFlipsToBad", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	m1.setCorrectSource(true);

	CHECK_EQUALS(root.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);

	m1.setCorrectSource(false);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);
	CHECK_EQUALS(root.correct, CORRECT_BAD);
});

besogo.addTest("CorrectValues", "AddChildClearsParentCorrectSource", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	m1.setCorrectSource(true);
	CHECK_EQUALS(m1.correctSource, true);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(m1.correctSource, false);
	CHECK_EQUALS(m1.correct, CORRECT_BAD);
});

besogo.addTest("CorrectValues", "WhiteFirstChangesRoles", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.firstMove = WHITE;

	CHECK_EQUALS(root.nextMove(), WHITE);
	CHECK_EQUALS(root.nextMove() == root.firstMove, true);

	let m1 = root.makeChild();
	m1.playMove(1, 1);
	root.registerChild(m1);

	CHECK_EQUALS(m1.lastMove, WHITE);
	CHECK_EQUALS(m1.nextMove(), BLACK);
	CHECK_EQUALS(m1.nextMove() == root.firstMove, false);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(m2.lastMove, BLACK);
	CHECK_EQUALS(m2.nextMove(), WHITE);
	CHECK_EQUALS(m2.nextMove() == root.firstMove, true);

	let m3 = m2.registerMove(3, 3);
	m3.setCorrectSource(true);

	CHECK_EQUALS(m3.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "GoalKill_StatusBasedEvaluation", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.goal = GOAL_KILL;

	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);

	let m3 = m2.makeChild();
	m3.playMove(3, 3);
	m3.statusSource = besogo.makeStatusSimple(STATUS_DEAD);
	m2.registerChild(m3);

	CHECK_EQUALS(m3.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "GoalKill_AliveIsBadBranch", function()
{
	let root = besogo.makeGameRoot(9, 9);
	root.goal = GOAL_KILL;

	let m1a = root.registerMove(1, 1);
	let m2a = m1a.registerMove(2, 2);
	let m3a = m2a.makeChild();
	m3a.playMove(3, 3);
	m3a.statusSource = besogo.makeStatusSimple(STATUS_DEAD);
	m2a.registerChild(m3a);

	let m1b = root.registerMove(4, 4);
	let m2b = m1b.registerMove(5, 5);
	let m3b = m2b.makeChild();
	m3b.playMove(6, 6);
	m3b.statusSource = besogo.makeStatusSimple(STATUS_ALIVE);
	m2b.registerChild(m3b);

	CHECK_EQUALS(m3a.correct, CORRECT_GOOD);
	CHECK_EQUALS(m2a.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1a.correct, CORRECT_GOOD);

	CHECK_EQUALS(m3b.correct, CORRECT_BAD);
	CHECK_EQUALS(m2b.correct, CORRECT_BAD);
	CHECK_EQUALS(m1b.correct, CORRECT_BAD);

	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "LocalEditDoesntAffectCorrectValues", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	m2.setCorrectSource(true);

	CHECK_EQUALS(root.correct, CORRECT_GOOD);

	let local = m1.makeChild();
	local.localEdit = true;
	local.playMove(3, 3);
	m1.registerChild(local);

	CHECK_EQUALS(root.correct, CORRECT_GOOD);
	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
});

besogo.addTest("CorrectValues", "DestroyChildUpdatesPropagation", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	let m2_good = m1.registerMove(2, 2);
	let m3 = m2_good.registerMove(3, 3);
	m3.setCorrectSource(true);

	let m2_bad = m1.registerMove(4, 4);

	CHECK_EQUALS(m1.correct, CORRECT_BAD);

	m2_bad.destroy();
	besogo.updateCorrectValues(root);

	CHECK_EQUALS(m1.correct, CORRECT_GOOD);
	CHECK_EQUALS(root.correct, CORRECT_GOOD);
});
