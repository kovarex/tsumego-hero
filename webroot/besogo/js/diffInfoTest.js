// Tests for diffInfo.js

besogo.addTest("DiffInfo", "DefaultTypeIsNoChange", function()
{
	let diff = besogo.makeDiffInfo();
	CHECK_EQUALS(diff.type, DIFF_NO_CHANGE);
});

besogo.addTest("DiffInfo", "ExplicitTypeAssigned", function()
{
	let diff = besogo.makeDiffInfo(DIFF_ADDED);
	CHECK_EQUALS(diff.type, DIFF_ADDED);
});

besogo.addTest("DiffInfo", "PreviousStatusNullByDefault", function()
{
	let diff = besogo.makeDiffInfo();
	CHECK(diff.previousStatus === null);
});

besogo.addTest("DiffInfo", "MakeAddedMoveDiffInfo", function()
{
	let diff = besogo.makeAddedMoveDiffInfo();
	CHECK_EQUALS(diff.type, DIFF_ADDED);
	CHECK(diff.previousStatus === null);
});

besogo.addTest("DiffInfo", "MakeRemovedMoveDiffInfo", function()
{
	let diff = besogo.makeRemovedMoveDiffInfo();
	CHECK_EQUALS(diff.type, DIFF_REMOVED);
	CHECK(diff.previousStatus === null);
});

besogo.addTest("DiffInfo", "ConstantsAreDistinct", function()
{
	CHECK(DIFF_NO_CHANGE !== DIFF_ADDED);
	CHECK(DIFF_ADDED !== DIFF_REMOVED);
	CHECK(DIFF_NO_CHANGE !== DIFF_REMOVED);
});
