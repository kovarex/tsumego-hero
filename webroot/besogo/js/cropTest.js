// The part of the board a problem shows (besogo.cropToStones()): its stones turned to the top left corner, from that
// corner to one line past the farthest stone or move, up to the far edge when only two lines or fewer would be left out
function croppedBoard(sgf)
{
	besogo.loadSgf(besogo.parseSgf(sgf), besogo.makeEditor(19, 19, {}));
	let scale = besogo.scaleParameters;
	besogo.cropToStones(scale);
	return scale.highest.x + "x" + scale.highest.y;
}

// The stones and the moves of a corner problem reach the fifth column and the third row
besogo.addTest("Crop", "ACornerProblemShowsOneLinePastItsStones", function()
{
	CHECK_EQUALS(croppedBoard("(;SZ[19]AB[ab][bb][cb]AW[ac][bc][cc][dc][db];B[ca];W[da];B[ea]C[+])"), "6x4");
});

// The same problem in the other corners is turned to the top left one first, so it shows the same part of the board
besogo.addTest("Crop", "AProblemInAnotherCornerShowsTheSame", function()
{
	CHECK_EQUALS(croppedBoard("(;SZ[19]AB[sr][rr][qr]AW[sq][rq][qq][pq][pr];B[qs];W[ps];B[os]C[+])"), "6x4");
});

// Stones in the middle, on the tenth and the eleventh lines, show from the nearer corner to the twelfth line
besogo.addTest("Crop", "AProblemInTheMiddleShowsOneLinePastItsStones", function()
{
	CHECK_EQUALS(croppedBoard("(;SZ[19]AB[jj][kj]AW[jk];B[kk]C[+])"), "11x11");
});

// Stones along the top edge up to the 15th line show to the 16th, leaving out three lines; up to the 16th line, the
// cut would leave out only two, so the board shows up to its edge
besogo.addTest("Crop", "TheFarEdgeShowsWhenTwoLinesOrFewerWouldBeLeftOut", function()
{
	CHECK_EQUALS(croppedBoard("(;SZ[19]AB[ab][ob]AW[ac];B[bb]C[+])"), "16x4");
	CHECK_EQUALS(croppedBoard("(;SZ[19]AB[ab][pb]AW[ac];B[bb]C[+])"), "19x4");
});

// A small board follows the same rule
besogo.addTest("Crop", "ASmallBoardReachesItsEdgeByTheSameRule", function()
{
	CHECK_EQUALS(croppedBoard("(;SZ[9]AB[ab][bb]AW[ac][bc];B[cb]C[+])"), "4x4");
	CHECK_EQUALS(croppedBoard("(;SZ[9]AB[ab][eb]AW[ac][bc];B[cb]C[+])"), "6x4");
	CHECK_EQUALS(croppedBoard("(;SZ[9]AB[ab][fb]AW[ac][bc];B[cb]C[+])"), "9x4");
});
