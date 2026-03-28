// Virtual children / transposition tests

besogo.addTest("VirtualChildren", "TranspositionCreatesVirtualChild", function()
{
	let root = besogo.makeGameRoot(9, 9);

	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(1, 2);
	let a3 = a2.registerMove(2, 1);
	let a4 = a3.registerMove(2, 2);

	let b1 = root.registerMove(2, 1);
	let b2 = b1.registerMove(2, 2);
	let b3 = b2.registerMove(1, 1);

	CHECK(b3.virtualChildren.length > 0);
});

besogo.addTest("VirtualChildren", "VirtualChildCorrectValuePropagation", function()
{
	let root = besogo.makeGameRoot(9, 9);

	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(1, 2);
	let a3 = a2.registerMove(2, 1);
	let a4 = a3.registerMove(2, 2);
	a4.setCorrectSource(true);

	CHECK_EQUALS(a3.correct, CORRECT_GOOD);
	CHECK_EQUALS(a2.correct, CORRECT_GOOD);
	CHECK_EQUALS(a1.correct, CORRECT_GOOD);

	let b1 = root.registerMove(2, 1);
	let b2 = b1.registerMove(2, 2);
	let b3 = b2.registerMove(1, 1);

	CHECK(b3.virtualChildren.length > 0);
	CHECK_EQUALS(b3.correct, CORRECT_GOOD);
	CHECK_EQUALS(b2.correct, CORRECT_GOOD);
	CHECK_EQUALS(b1.correct, CORRECT_GOOD);
});

besogo.addTest("VirtualChildren", "VirtualParentTracksBidirectional", function()
{
	let root = besogo.makeGameRoot(9, 9);

	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(1, 2);
	let a3 = a2.registerMove(2, 1);
	let a4 = a3.registerMove(2, 2);

	let b1 = root.registerMove(2, 1);
	let b2 = b1.registerMove(2, 2);
	let b3 = b2.registerMove(1, 1);

	// Virtual child target should have virtualParents back-reference
	if (b3.virtualChildren.length > 0)
	{
		let target = b3.virtualChildren[0].target;
		CHECK(target.virtualParents.length > 0);
		CHECK(target.virtualParents.indexOf(b3) >= 0);
	}
});

besogo.addTest("VirtualChildren", "NoTranspositionForDifferentPositions", function()
{
	let root = besogo.makeGameRoot(9, 9);

	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(2, 2);

	let b1 = root.registerMove(3, 3);
	let b2 = b1.registerMove(4, 4);

	// Different positions — should NOT create virtual children
	CHECK_EQUALS(a2.virtualChildren.length, 0);
	CHECK_EQUALS(b2.virtualChildren.length, 0);
});

besogo.addTest("VirtualChildren", "ThreePathTransposition", function()
{
	// Two paths to the same 4-stone position, existing node has children
	let root = besogo.makeGameRoot(9, 9);

	// Path A: B(1,1) W(2,2) B(3,3) W(4,4) — then add a continuation
	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(2, 2);
	let a3 = a2.registerMove(3, 3);
	let a4 = a3.registerMove(4, 4);
	let a5 = a4.registerMove(5, 5); // continuation child

	// Path B: B(3,3) W(4,4) B(1,1) W(2,2) — same position as a4
	let b1 = root.registerMove(3, 3);
	let b2 = b1.registerMove(4, 4);
	let b3 = b2.registerMove(1, 1);
	let b4 = b3.registerMove(2, 2);

	// b4 matches a4, and a4 has child a5, so b4 should have virtual children
	CHECK(b4.virtualChildren.length > 0);
});

besogo.addTest("VirtualChildren", "HashTableSizeAccurate", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK_EQUALS(root.nodeHashTable.size(), 0);

	let m1 = root.registerMove(1, 1);
	CHECK_EQUALS(root.nodeHashTable.size(), 1);

	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(root.nodeHashTable.size(), 2);

	let m3 = m2.registerMove(3, 3);
	CHECK_EQUALS(root.nodeHashTable.size(), 3);
});

besogo.addTest("VirtualChildren", "DestroyRemovesFromHashTable", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(root.nodeHashTable.size(), 2);

	m2.destroy();
	CHECK_EQUALS(root.nodeHashTable.size(), 1);

	m1.destroy();
	CHECK_EQUALS(root.nodeHashTable.size(), 0);
});
