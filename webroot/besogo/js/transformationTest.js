// Tests for transformation.js

besogo.addTest("Transformation", "DefaultNoTransform", function()
{
	let t = besogo.makeTransformation();
	let pos = t.apply({x: 3, y: 5}, {x: 9, y: 9});
	CHECK_EQUALS(pos.x, 3);
	CHECK_EQUALS(pos.y, 5);
});

besogo.addTest("Transformation", "HorizontalFlip", function()
{
	let t = besogo.makeTransformation();
	t.hFlip = true;
	let pos = t.apply({x: 3, y: 5}, {x: 9, y: 9});
	// hFlip: x = 9 - 3 + 1 = 7
	CHECK_EQUALS(pos.x, 7);
	CHECK_EQUALS(pos.y, 5);
});

besogo.addTest("Transformation", "VerticalFlip", function()
{
	let t = besogo.makeTransformation();
	t.vFlip = true;
	let pos = t.apply({x: 3, y: 5}, {x: 9, y: 9});
	// vFlip: y = 9 - 5 + 1 = 5
	CHECK_EQUALS(pos.x, 3);
	CHECK_EQUALS(pos.y, 5);
});

besogo.addTest("Transformation", "VerticalFlipAsymmetric", function()
{
	let t = besogo.makeTransformation();
	t.vFlip = true;
	let pos = t.apply({x: 3, y: 2}, {x: 9, y: 9});
	// vFlip: y = 9 - 2 + 1 = 8
	CHECK_EQUALS(pos.x, 3);
	CHECK_EQUALS(pos.y, 8);
});

besogo.addTest("Transformation", "BothFlips", function()
{
	let t = besogo.makeTransformation();
	t.hFlip = true;
	t.vFlip = true;
	let pos = t.apply({x: 1, y: 1}, {x: 9, y: 9});
	// hFlip: x = 9, vFlip: y = 9
	CHECK_EQUALS(pos.x, 9);
	CHECK_EQUALS(pos.y, 9);
});

besogo.addTest("Transformation", "RotateClockwise", function()
{
	let t = besogo.makeTransformation();
	t.rotateClockwise = true;
	// rotateClockwise: [x, y] = [size.x - y + 1, x]
	let pos = t.apply({x: 2, y: 3}, {x: 9, y: 9});
	CHECK_EQUALS(pos.x, 7); // 9 - 3 + 1 = 7
	CHECK_EQUALS(pos.y, 2);
});

besogo.addTest("Transformation", "RotateCounterClockwise", function()
{
	let t = besogo.makeTransformation();
	t.rotateCounterClockwise = true;
	// rotateCounterClockwise: [y, x] = [size.y - x + 1, y]
	let pos = t.apply({x: 2, y: 3}, {x: 9, y: 9});
	CHECK_EQUALS(pos.x, 3);
	CHECK_EQUALS(pos.y, 8); // 9 - 2 + 1 = 8
});

besogo.addTest("Transformation", "InvertColorsBlackToWhite", function()
{
	let t = besogo.makeTransformation();
	t.invertColors = true;
	CHECK_EQUALS(t.applyOnColor(-1), 1); // BLACK -> WHITE
});

besogo.addTest("Transformation", "InvertColorsWhiteToBlack", function()
{
	let t = besogo.makeTransformation();
	t.invertColors = true;
	CHECK_EQUALS(t.applyOnColor(1), -1); // WHITE -> BLACK
});

besogo.addTest("Transformation", "NoInvertColorsPassthrough", function()
{
	let t = besogo.makeTransformation();
	CHECK_EQUALS(t.applyOnColor(-1), -1);
	CHECK_EQUALS(t.applyOnColor(1), 1);
});

besogo.addTest("Transformation", "CornerPosition", function()
{
	let t = besogo.makeTransformation();
	t.hFlip = true;
	let pos = t.apply({x: 1, y: 1}, {x: 19, y: 19});
	CHECK_EQUALS(pos.x, 19);
	CHECK_EQUALS(pos.y, 1);
});

besogo.addTest("Transformation", "CenterIsFixedOnOddBoard", function()
{
	let t = besogo.makeTransformation();
	t.hFlip = true;
	t.vFlip = true;
	let pos = t.apply({x: 5, y: 5}, {x: 9, y: 9});
	// Center of 9x9: hFlip: 9-5+1=5, vFlip: 9-5+1=5
	CHECK_EQUALS(pos.x, 5);
	CHECK_EQUALS(pos.y, 5);
});

besogo.addTest("Transformation", "NonSquareBoardFlip", function()
{
	let t = besogo.makeTransformation();
	t.hFlip = true;
	let pos = t.apply({x: 2, y: 3}, {x: 9, y: 13});
	CHECK_EQUALS(pos.x, 8); // 9 - 2 + 1 = 8
	CHECK_EQUALS(pos.y, 3); // unchanged
});
