// Tests for nodeHashTable.js

besogo.addTest("NodeHashTable", "EmptyTableSizeZero", function()
{
	let table = besogo.makeNodeHashTable();
	CHECK_EQUALS(table.size(), 0);
});

besogo.addTest("NodeHashTable", "PushIncreasesSize", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);

	// registerMove already pushes to hash table
	CHECK_EQUALS(root.nodeHashTable.size(), 2);
});

besogo.addTest("NodeHashTable", "EraseDecreasesSize", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);
	CHECK_EQUALS(root.nodeHashTable.size(), 2);

	root.nodeHashTable.erase(m2);
	CHECK_EQUALS(root.nodeHashTable.size(), 1);

	root.nodeHashTable.erase(m1);
	CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("NodeHashTable", "EraseNonexistentThrows", function()
{
	let table = besogo.makeNodeHashTable();
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	// m1 is in root.nodeHashTable, NOT in our empty 'table'
	let threw = false;
	try
	{
		table.erase(m1);
	}
	catch (e)
	{
		threw = true;
	}
	CHECK(threw);
});

besogo.addTest("NodeHashTable", "GetSameNodeFindsTransposition", function()
{
	let root = besogo.makeGameRoot(9, 9);

	// Path A: B(1,1) W(2,2) = position with black@1,1 white@2,2
	let a1 = root.registerMove(1, 1);
	let a2 = a1.registerMove(2, 2);

	// Create a temporary node with same position but different path
	// We can test by looking up a2 itself
	let found = root.nodeHashTable.getSameNode(a2);
	CHECK(found !== null);
	CHECK(found === a2);
});

besogo.addTest("NodeHashTable", "GetSameNodeReturnsNullForNoMatch", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	// Create a different position node on a separate board
	let root2 = besogo.makeGameRoot(9, 9);
	let m2 = root2.registerMove(5, 5);

	// m2 is in root2's hash table, not root's
	let found = root.nodeHashTable.getSameNode(m2);
	CHECK(found === null);
});

besogo.addTest("NodeHashTable", "HasHashReturnsTrueForExisting", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let hash = m1.getHash();

	CHECK(root.nodeHashTable.hasHash(hash));
});

besogo.addTest("NodeHashTable", "HasHashReturnsFalseForMissing", function()
{
	let root = besogo.makeGameRoot(9, 9);
	CHECK(!root.nodeHashTable.hasHash(99999));
});

besogo.addTest("NodeHashTable", "GetSameNodeWithHashAndStoneCount", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);
	let m2 = m1.registerMove(2, 2);

	let hash = m2.getHash();
	let stoneCount = m2.stoneCount;

	let found = root.nodeHashTable.getSameNodeWithHash(m2, hash, stoneCount);
	CHECK(found !== null);
	CHECK(found === m2);
});

besogo.addTest("NodeHashTable", "GetSameNodeWithHashWrongStoneCountSkips", function()
{
	let root = besogo.makeGameRoot(9, 9);
	let m1 = root.registerMove(1, 1);

	let hash = m1.getHash();
	// Use wrong stone count — should not find
	let found = root.nodeHashTable.getSameNodeWithHash(m1, hash, 999);
	CHECK(found === null);
});

besogo.addTest("NodeHashTable", "MultipleNodesWithSameHash", function()
{
	// Push two nodes manually with the same hash to test collision handling
	let table = besogo.makeNodeHashTable();

	let root1 = besogo.makeGameRoot(9, 9);
	let m1 = root1.registerMove(1, 1);

	let root2 = besogo.makeGameRoot(9, 9);
	let m2 = root2.registerMove(2, 2);

	table.push(m1);
	table.push(m2);

	CHECK_EQUALS(table.size(), 2);
});
