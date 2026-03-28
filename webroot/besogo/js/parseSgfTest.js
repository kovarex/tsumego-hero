// Tests for parseSgf.js and saveSgf.js

besogo.addTest("ParseSgf", "SimpleGameTree", function()
{
	let sgf = "(;GM[1]FF[4]SZ[9])";
	let tree = besogo.parseSgf(sgf);
	CHECK(tree !== null);
	CHECK(tree.props.length > 0);

	// Check root has GM, FF, SZ properties
	let ids = tree.props.map(function(p) { return p.id; });
	CHECK(ids.indexOf("GM") >= 0);
	CHECK(ids.indexOf("FF") >= 0);
	CHECK(ids.indexOf("SZ") >= 0);
});

besogo.addTest("ParseSgf", "PropertyValues", function()
{
	let sgf = "(;SZ[19]KM[6.5])";
	let tree = besogo.parseSgf(sgf);

	for (let i = 0; i < tree.props.length; i++)
	{
		if (tree.props[i].id === "SZ")
			CHECK_EQUALS(tree.props[i].values[0], "19");
		if (tree.props[i].id === "KM")
			CHECK_EQUALS(tree.props[i].values[0], "6.5");
	}
});

besogo.addTest("ParseSgf", "MultipleValues", function()
{
	let sgf = "(;AB[aa][bb][cc])";
	let tree = besogo.parseSgf(sgf);

	let abProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "AB")
			abProp = tree.props[i];

	CHECK(abProp !== null);
	CHECK_EQUALS(abProp.values.length, 3);
	CHECK_EQUALS(abProp.values[0], "aa");
	CHECK_EQUALS(abProp.values[1], "bb");
	CHECK_EQUALS(abProp.values[2], "cc");
});

besogo.addTest("ParseSgf", "SequenceOfNodes", function()
{
	let sgf = "(;SZ[9];B[ee];W[dd])";
	let tree = besogo.parseSgf(sgf);

	// Root has children sequence
	CHECK_EQUALS(tree.children.length, 1);
	CHECK_EQUALS(tree.children[0].children.length, 1);
});

besogo.addTest("ParseSgf", "BranchingTree", function()
{
	let sgf = "(;SZ[9];B[ee](;W[dd])(;W[dc]))";
	let tree = besogo.parseSgf(sgf);

	// Root -> B[ee] -> 2 branches
	CHECK_EQUALS(tree.children.length, 1);
	let moveNode = tree.children[0];
	CHECK_EQUALS(moveNode.children.length, 2);
});

besogo.addTest("ParseSgf", "EscapedBracket", function()
{
	let sgf = "(;C[hello \\] world])";
	let tree = besogo.parseSgf(sgf);

	let cProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "C")
			cProp = tree.props[i];

	CHECK(cProp !== null);
	CHECK_EQUALS(cProp.values[0], "hello ] world");
});

besogo.addTest("ParseSgf", "EscapedBackslash", function()
{
	let sgf = "(;C[test\\\\end])";
	let tree = besogo.parseSgf(sgf);

	let cProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "C")
			cProp = tree.props[i];

	CHECK(cProp !== null);
	CHECK_EQUALS(cProp.values[0], "test\\end");
});

besogo.addTest("ParseSgf", "SoftLineBreakRemoved", function()
{
	let sgf = "(;C[hello\\\nworld])";
	let tree = besogo.parseSgf(sgf);

	let cProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "C")
			cProp = tree.props[i];

	CHECK(cProp !== null);
	CHECK_EQUALS(cProp.values[0], "helloworld");
});

besogo.addTest("ParseSgf", "HardLineBreakConverted", function()
{
	let sgf = "(;C[hello\nworld])";
	let tree = besogo.parseSgf(sgf);

	let cProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "C")
			cProp = tree.props[i];

	CHECK(cProp !== null);
	CHECK_EQUALS(cProp.values[0], "hello\nworld");
});

besogo.addTest("ParseSgf", "CompressedPointList", function()
{
	let sgf = "(;AB[aa:cc])";
	let tree = besogo.parseSgf(sgf);

	let abProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "AB")
			abProp = tree.props[i];

	CHECK(abProp !== null);
	CHECK_EQUALS(abProp.values[0], "aa:cc");
});

besogo.addTest("ParseSgf", "LowercaseLettersIgnored", function()
{
	// SGF spec says lowercase letters in property IDs should be ignored
	let sgf = "(;SiZe[9])";
	let tree = besogo.parseSgf(sgf);

	let ids = tree.props.map(function(p) { return p.id; });
	// Only uppercase letters are kept, so "SiZe" becomes "SZE" or "SZ"?
	// Actually the parser: uppercase only → "SZE" ... but parser just skips lowercase
	// so "SiZe" → S (skip i) Z (skip e) → "SZ"
	CHECK(ids.indexOf("SZ") >= 0);
});

besogo.addTest("ParseSgf", "WhitespaceSkipped", function()
{
	let sgf = "  (  ;  SZ  [9]  )  ";
	let tree = besogo.parseSgf(sgf);
	CHECK(tree !== null);
	CHECK(tree.props.length > 0);
});

besogo.addTest("ParseSgf", "EmptyTree", function()
{
	let sgf = "(;)";
	let tree = besogo.parseSgf(sgf);
	CHECK(tree !== null);
	CHECK_EQUALS(tree.children.length, 0);
});

besogo.addTest("ParseSgf", "DeepNesting", function()
{
	let sgf = "(;SZ[9];B[aa];W[bb];B[cc];W[dd];B[ee])";
	let tree = besogo.parseSgf(sgf);

	// Walk down the sequence
	let node = tree;
	let depth = 0;
	while (node.children.length > 0)
	{
		node = node.children[0];
		depth++;
	}
	CHECK_EQUALS(depth, 5); // 5 moves deep
});

besogo.addTest("ParseSgf", "MissingSemicolonThrows", function()
{
	let sgf = "(SZ[9])"; // Missing semicolon
	let threw = false;
	try
	{
		besogo.parseSgf(sgf);
	}
	catch (e)
	{
		threw = true;
	}
	CHECK(threw);
});

besogo.addTest("ParseSgf", "MissingPropertyIDThrows", function()
{
	let sgf = "(;[9])"; // No property ID before value
	let threw = false;
	try
	{
		besogo.parseSgf(sgf);
	}
	catch (e)
	{
		threw = true;
	}
	CHECK(threw);
});

besogo.addTest("ParseSgf", "LabelProperty", function()
{
	let sgf = "(;LB[aa:A][bb:B])";
	let tree = besogo.parseSgf(sgf);

	let lbProp = null;
	for (let i = 0; i < tree.props.length; i++)
		if (tree.props[i].id === "LB")
			lbProp = tree.props[i];

	CHECK(lbProp !== null);
	CHECK_EQUALS(lbProp.values.length, 2);
	CHECK_EQUALS(lbProp.values[0], "aa:A");
	CHECK_EQUALS(lbProp.values[1], "bb:B");
});

besogo.addTest("ParseSgf", "NestedSubtrees", function()
{
	let sgf = "(;SZ[9](;B[aa](;W[bb])(;W[cc]))(;B[dd]))";
	let tree = besogo.parseSgf(sgf);

	// Root has 2 children (subtrees)
	CHECK_EQUALS(tree.children.length, 2);
	// First subtree (B[aa]) has 2 children
	CHECK_EQUALS(tree.children[0].children.length, 2);
	// Second subtree (B[dd]) has 0 children
	CHECK_EQUALS(tree.children[1].children.length, 0);
});
