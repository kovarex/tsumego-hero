// Tests for coord.js

besogo.addTest("Coord", "NoneReturnsFalse", function()
{
	let result = besogo.coord.none(9, 9);
	CHECK(result === false);
});

besogo.addTest("Coord", "WesternXLabelsSkipI", function()
{
	let labels = besogo.coord.western(19, 19);
	// A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, skips I, J=9
	CHECK_EQUALS(labels.x[1], "A");
	CHECK_EQUALS(labels.x[2], "B");
	CHECK_EQUALS(labels.x[8], "H");
	CHECK_EQUALS(labels.x[9], "J"); // Skips I!
	CHECK_EQUALS(labels.x[19], "T");
});

besogo.addTest("Coord", "WesternYLabelsDescending", function()
{
	let labels = besogo.coord.western(9, 9);
	CHECK_EQUALS(labels.y[1], "9"); // Top row = highest number
	CHECK_EQUALS(labels.y[9], "1"); // Bottom row = 1
});

besogo.addTest("Coord", "NumericSimple", function()
{
	let labels = besogo.coord.numeric(9, 9);
	CHECK_EQUALS(labels.x[1], "1");
	CHECK_EQUALS(labels.x[9], "9");
	CHECK_EQUALS(labels.y[1], "1");
	CHECK_EQUALS(labels.y[9], "9");
});

besogo.addTest("Coord", "PierreHasXbAndYb", function()
{
	let labels = besogo.coord.pierre(9, 9);
	CHECK(labels.xb !== undefined);
	CHECK(labels.yb !== undefined);
	// First column from left
	CHECK_EQUALS(labels.x[1], "a1");
	// Last column from right
	CHECK_EQUALS(labels.x[9], "b1");
	// Center column on odd board
	CHECK_EQUALS(labels.x[5], "a");
});

besogo.addTest("Coord", "PierreCenterOnOddBoard", function()
{
	let labels = besogo.coord.pierre(9, 9);
	// Center x
	CHECK_EQUALS(labels.x[5], "a");
	CHECK_EQUALS(labels.xb[5], "c");
	// Center y
	CHECK_EQUALS(labels.y[5], "d");
	CHECK_EQUALS(labels.yb[5], "b");
});

besogo.addTest("Coord", "CornerAlphaNumeric", function()
{
	let labels = besogo.coord.corner(9, 9);
	CHECK(labels.x[1] !== undefined);
	CHECK(labels.y[1] !== undefined);
});

besogo.addTest("Coord", "EastcorHasCJKLabels", function()
{
	let labels = besogo.coord.eastcor(9, 9);
	// First column should be CJK
	CHECK_EQUALS(labels.x[1], "一");
});

besogo.addTest("Coord", "EasternYisCJK", function()
{
	let labels = besogo.coord.eastern(9, 9);
	CHECK_EQUALS(labels.y[1], "一");
	CHECK_EQUALS(labels.y[2], "二");
	CHECK_EQUALS(labels.y[9], "九");
});

besogo.addTest("Coord", "EasternXisNumeric", function()
{
	let labels = besogo.coord.eastern(9, 9);
	CHECK_EQUALS(labels.x[1], "1");
	CHECK_EQUALS(labels.x[9], "9");
});

besogo.addTest("Coord", "Western19x19FullRange", function()
{
	let labels = besogo.coord.western(19, 19);
	// 19 columns: A-T (skipping I)
	CHECK_EQUALS(labels.x.filter(function(x) { return x !== undefined; }).length, 19);
	CHECK_EQUALS(labels.y.filter(function(y) { return y !== undefined; }).length, 19);
});

besogo.addTest("Coord", "EasternCJKTen", function()
{
	let labels = besogo.coord.eastern(19, 19);
	CHECK_EQUALS(labels.y[10], "十");
	CHECK_EQUALS(labels.y[11], "十一");
	CHECK_EQUALS(labels.y[19], "十九");
});

besogo.addTest("Coord", "NumericNonSquare", function()
{
	let labels = besogo.coord.numeric(9, 13);
	CHECK_EQUALS(labels.x[9], "9");
	CHECK_EQUALS(labels.y[13], "13");
});
