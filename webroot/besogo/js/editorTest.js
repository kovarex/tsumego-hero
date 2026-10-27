// Boundless Sky 45/100: an eternal life. The last B[bo] lets White take at a1 again (W[as]), which brings back the
// position after move 16, so the tree has a cycle through a virtual child.
const ETERNAL_LIFE_SGF = "(;SZ[19]" +
	"AB[br][cq][dp][cp][dn][cn][dm][bl][ck][dk][fj][fl][gn][hn][in][io][kn][hp][hq][hr][hs][gr][fr][fs][ir][jr][js]" +
	"AW[cr][bs][dq][eq][er][fq][gq][gp][go][fo][eo][ep][en][em][bn][cl][ho][ip][iq][kp][kq][kr][oq]PL[B]" +
	";B[es];W[bq];B[ar];W[bp];B[bo];W[co];B[do];W[ao];B[ds];W[dr];B[cs];W[co];B[bm];W[aq];B[bo];W[as]" +
	";B[br];W[co];B[ar];W[bs];B[bo])";

function loadEditor(sgf)
{
	let editor = besogo.makeEditor(19, 19, {});
	besogo.loadSgf(besogo.parseSgf(sgf), editor);
	return editor;
}

// Runs the method on an editor set up like the play page (autoplay, no review), with the timers of the editor
// (the autoplay, the required answers) queued instead of waited for: flush() runs them in order.
function withPlayEditor(root, alternativeResponse, method)
{
	let editor = besogo.makeEditor(19, 19, {});
	editor.loadRoot(root);
	editor.setAutoPlay(true);
	editor.setReviewMode(false);
	let results = [];
	let comments = [];
	editor.registerDisplayResult(result => results.push(result));
	editor.registerAddTimeForMovePlayed(() => {});
	editor.registerShowComment(comment => comments.push(comment));

	let originalSetTimeout = window.setTimeout;
	let originalRandom = Math.random;
	let originalIsMutable = window.isMutable;
	let originalAlternativeResponse = besogo.alternativeResponse;
	let queue = [];
	window.setTimeout = callback => queue.push(callback);
	Math.random = () => 0; // the autoplay picks the first reply
	window.isMutable = true;
	besogo.alternativeResponse = alternativeResponse;
	try
	{
		method(editor, () => { while (queue.length) queue.shift()(); }, results, comments);
	}
	finally
	{
		window.setTimeout = originalSetTimeout;
		Math.random = originalRandom;
		window.isMutable = originalIsMutable;
		besogo.alternativeResponse = originalAlternativeResponse;
	}
}

besogo.addTest("Editor", "LastNodeStopsWhereAnEternalLifeComesRound", function()
{
	let editor = loadEditor(ETERNAL_LIFE_SGF);
	editor.nextNode(-1);

	let last = editor.getCurrent();
	CHECK_EQUALS(last.moveNumber, 21);
	CHECK_EQUALS(last.virtualChildren.length, 1);
	CHECK_EQUALS(last.virtualChildren[0].target.moveNumber, 16);
});

besogo.addTest("Editor", "FirstNodeLeavesAnEternalLifeCycle", function()
{
	let editor = loadEditor(ETERNAL_LIFE_SGF);
	editor.nextNode(-1);
	let retake = editor.getCurrent().virtualChildren[0];
	editor.click(retake.move.x, retake.move.y, false, false);
	CHECK(editor.getCurrent() == retake.target);

	editor.prevNode(-1);
	CHECK(editor.getCurrent() == editor.getRoot());
});

besogo.addTest("Editor", "AutoplayWithoutAReplyItMayPickEndsTheLine", function()
{
	let root = besogo.makeGameRoot();
	root.goal = GOAL_KILL;
	let wrongMove = root.registerMove(1, 1);
	let killingMove = root.makeChild();
	killingMove.playMove(3, 3);
	killingMove.statusSource = besogo.makeStatusSimple(STATUS_DEAD);
	root.registerChild(killingMove);

	let reply = wrongMove.makeChild();
	reply.playMove(1, 2);
	reply.statusSource = besogo.makeStatusSimple(STATUS_DEAD);
	wrongMove.registerChild(reply);

	// a better answer of White, explored by the solver, which the autoplay doesn't play as it isn't part of the problem
	let localEditMove = wrongMove.makeChild();
	localEditMove.localEdit = true;
	localEditMove.playMove(2, 2);
	localEditMove.statusSource = besogo.makeStatusSimple(STATUS_ALIVE);
	wrongMove.registerChild(localEditMove);

	CHECK_EQUALS(wrongMove.correct, CORRECT_BAD);
	CHECK(wrongMove.selectNonLocalChildIncludingVirtualWithNoBetterStatus(0) == null);

	withPlayEditor(root, false, function(editor, flush, results)
	{
		editor.click(1, 1, false, false);
		flush();
		CHECK(editor.getCurrent() == wrongMove);
		CHECK_EQUALS(results.join(), "F");
	});
});

besogo.addTest("Editor", "RequiredAnswerVisitedElsewhereDoesntDropTheOthers", function()
{
	let root = besogo.makeGameRoot();
	let firstMove = root.registerMove(1, 1);
	let answers = [firstMove.registerMove(1, 2), firstMove.registerMove(2, 1), firstMove.registerMove(2, 2)];
	answers[0].registerMove(5, 5).setCorrectSource(true);
	answers[1].registerMove(6, 6).setCorrectSource(true);
	answers[2].registerMove(7, 7).setCorrectSource(true);

	withPlayEditor(root, true, function(editor, flush, results, comments)
	{
		editor.click(1, 1, false, false);
		flush();
		CHECK(editor.getCurrent() == answers[0]); // the other answers are required now

		answers[1].visited = true; // reached through a transposition
		editor.click(5, 5, false, false);
		flush();
		CHECK_EQUALS(results.length, 0);
		CHECK(editor.getCurrent() == answers[2]);
		CHECK(comments[comments.length - 1].startsWith("Correct, but what about this answer?"));

		editor.click(7, 7, false, false);
		flush();
		CHECK_EQUALS(results.join(), "S");
	});
});

besogo.addTest("Editor", "ResetCancelsTheWaitForTheNextRequiredAnswer", function()
{
	let root = besogo.makeGameRoot();
	let firstMove = root.registerMove(1, 1);
	let answers = [firstMove.registerMove(1, 2), firstMove.registerMove(2, 1)];
	answers[0].registerMove(5, 5).setCorrectSource(true);
	answers[1].registerMove(6, 6).setCorrectSource(true);

	withPlayEditor(root, true, function(editor, flush)
	{
		editor.click(1, 1, false, false);
		flush();
		editor.click(5, 5, false, false); // correct, the other answer is to come
		CHECK(editor.isPerformingAutoPlay());

		editor.resetToStart();
		editor.click(1, 1, false, false);
		// the cancelled wait used to end the wait for this reply, which then never came
		flush();
		CHECK(editor.getCurrent() == answers[0]);
	});
});
