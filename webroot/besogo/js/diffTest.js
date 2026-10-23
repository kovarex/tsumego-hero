function findDiffChange(node, property)
{
  if (!node.diffInfo)
    return null;
  for (let change of node.diffInfo.changes)
    if (change.property == property)
      return change;
  return null;
}

besogo.addTest("Diff", "SameTreesHaveNoChanges", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1).registerMove(1, 2).comment = 'same';
  let shown = besogo.makeGameRoot();
  let reply = shown.registerMove(1, 1).registerMove(1, 2);
  reply.comment = 'same';

  shown.incorporateDiff(previous);
  CHECK(!shown.diffInfo);
  CHECK(!shown.children[0].diffInfo);
  CHECK(!reply.diffInfo);
});

besogo.addTest("Diff", "CommentChange", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1).comment = 'old';
  let shown = besogo.makeGameRoot();
  let move = shown.registerMove(1, 1);
  move.comment = 'new';

  shown.incorporateDiff(previous);
  CHECK_EQUALS(move.diffInfo.type, DIFF_NO_CHANGE);
  let change = findDiffChange(move, 'comment');
  CHECK(change);
  CHECK_EQUALS(change.before, 'old');
  CHECK_EQUALS(change.after, 'new');
});

besogo.addTest("Diff", "SwitchToStatusBased", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1);
  let shown = besogo.makeGameRoot();
  shown.registerMove(1, 1);
  shown.setGoal(GOAL_KILL);

  shown.incorporateDiff(previous);
  let change = findDiffChange(shown, 'goal');
  CHECK(change);
  CHECK_EQUALS(change.before, GOAL_NONE);
  CHECK_EQUALS(change.after, GOAL_KILL);
});

besogo.addTest("Diff", "StatusChange", function()
{
  let previous = besogo.makeGameRoot();
  previous.setGoal(GOAL_KILL);
  previous.registerMove(1, 1).setStatusSource(besogo.loadStatusFromString('DEAD'));
  let shown = besogo.makeGameRoot();
  shown.setGoal(GOAL_KILL);
  let move = shown.registerMove(1, 1);
  move.setStatusSource(besogo.loadStatusFromString('SEKI'));

  shown.incorporateDiff(previous);
  let change = findDiffChange(move, 'status');
  CHECK(change);
  CHECK_EQUALS(change.before, 'DEAD');
  CHECK_EQUALS(change.after, 'SEKI (in gote)');
  CHECK(!findDiffChange(shown, 'goal'));
});

besogo.addTest("Diff", "StatusOfAShortenedLineIsComparedBeforeTheRemovedMovesAreMerged", function()
{
  let previous = besogo.makeGameRoot();
  previous.setGoal(GOAL_KILL);
  previous.registerMove(1, 1).registerMove(2, 2).setStatusSource(besogo.loadStatusFromString('DEAD'));
  let shown = besogo.makeGameRoot();
  shown.setGoal(GOAL_KILL);
  let move = shown.registerMove(1, 1);
  move.setStatusSource(besogo.loadStatusFromString('DEAD'));

  shown.incorporateDiff(previous);
  let change = findDiffChange(move, 'status');
  CHECK(change);
  CHECK_EQUALS(change.before, null);
  CHECK_EQUALS(change.after, 'DEAD');
  CHECK_EQUALS(move.children.length, 1);
  CHECK_EQUALS(move.children[0].diffInfo.type, DIFF_REMOVED);
});

besogo.addTest("Diff", "MarkedCorrectAndResultChange", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1);
  let shown = besogo.makeGameRoot();
  let move = shown.registerMove(1, 1);
  move.setCorrectSource(true);

  shown.incorporateDiff(previous);
  let marked = findDiffChange(move, 'markedCorrect');
  CHECK(marked);
  CHECK_EQUALS(marked.before, false);
  CHECK_EQUALS(marked.after, true);
  let result = findDiffChange(move, 'correct');
  CHECK(result);
  CHECK_EQUALS(result.before, CORRECT_BAD);
  CHECK_EQUALS(result.after, CORRECT_GOOD);
});

besogo.addTest("Diff", "SetupStonesAndMarkupChange", function()
{
  let previous = besogo.makeGameRoot();
  previous.placeSetup(3, 3, BLACK);
  previous.addMarkup(4, 4, 3);
  let shown = besogo.makeGameRoot();
  shown.placeSetup(3, 3, BLACK);
  shown.placeSetup(4, 3, WHITE);
  shown.addMarkup(4, 4, 'A');

  shown.incorporateDiff(previous);
  let setup = findDiffChange(shown, 'setup');
  CHECK(setup);
  CHECK_EQUALS(setup.before.length, 1);
  CHECK_EQUALS(setup.after.length, 2);
  CHECK_EQUALS(setup.after[1].value, 'AW');
  let markup = findDiffChange(shown, 'markup');
  CHECK(markup);
  CHECK_EQUALS(markup.before[0].value, 3);
  CHECK_EQUALS(markup.after[0].value, 'A');
});

besogo.addTest("Diff", "RemovedMoveKeepsItsComment", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1).comment = 'gone';
  let shown = besogo.makeGameRoot();

  shown.incorporateDiff(previous);
  CHECK_EQUALS(shown.children.length, 1);
  CHECK_EQUALS(shown.children[0].diffInfo.type, DIFF_REMOVED);
  CHECK_EQUALS(shown.children[0].comment, 'gone');
});

besogo.addTest("Diff", "GameInfoChange", function()
{
  let changes = besogo.compareGameInfo({ GN: 'new name', SO: 'book' }, { GN: 'old name', SO: 'book', PC: 'Prague' });
  CHECK_EQUALS(changes.length, 2);
  CHECK_EQUALS(changes[0].key, 'GN');
  CHECK_EQUALS(changes[0].before, 'old name');
  CHECK_EQUALS(changes[0].after, 'new name');
  CHECK_EQUALS(changes[1].key, 'PC');
  CHECK_EQUALS(changes[1].after, '');
});

besogo.addTest("Diff", "ChildDoesNotShowTheChangesOfItsParent", function()
{
  let previous = besogo.makeGameRoot();
  previous.comment = 'old';
  previous.registerMove(1, 1);
  let shown = besogo.makeGameRoot();
  shown.comment = 'new';
  let move = shown.registerMove(1, 1);

  shown.incorporateDiff(previous);
  CHECK(findDiffChange(shown, 'comment'));
  CHECK(!move.diffInfo);
});

besogo.addTest("Diff", "BranchOrderChange", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1);
  previous.registerMove(2, 2);
  previous.registerMove(3, 3);
  let shown = besogo.makeGameRoot();
  shown.registerMove(3, 3);
  shown.registerMove(1, 1);
  shown.registerMove(2, 2);

  shown.incorporateDiff(previous);
  let change = findDiffChange(shown, 'branchOrder');
  CHECK(change);
  CHECK_EQUALS(JSON.stringify(change.before), JSON.stringify([{x: 1, y: 1}, {x: 2, y: 2}, {x: 3, y: 3}]));
  CHECK_EQUALS(JSON.stringify(change.after), JSON.stringify([{x: 3, y: 3}, {x: 1, y: 1}, {x: 2, y: 2}]));
});

besogo.addTest("Diff", "AddedAndRemovedBranchesAreNotAReorder", function()
{
  let previous = besogo.makeGameRoot();
  previous.registerMove(1, 1);
  previous.registerMove(2, 2);
  previous.registerMove(3, 3);
  let shown = besogo.makeGameRoot();
  shown.registerMove(4, 4);
  shown.registerMove(1, 1);
  shown.registerMove(3, 3);

  shown.incorporateDiff(previous);
  CHECK(!findDiffChange(shown, 'branchOrder'));
});

besogo.addTest("Diff", "SgfTextsIdentical", function()
{
  let texts = besogo.compareSgfTexts('(;GM[1]AB[aa];B[bb])', '(;GM[1]AB[aa];B[bb])');
  CHECK(texts.identical);
});

besogo.addTest("Diff", "SgfTextsDifferOnlyInSpacing", function()
{
  let texts = besogo.compareSgfTexts('(;GM[1]AB[aa][bb];B[cc])', '(;GM[1]\nAB [aa]\n[bb]\n;B[cc])');
  CHECK(!texts.identical);
  CHECK(texts.sameTokens);
});

besogo.addTest("Diff", "SgfTextsDifferInAProperty", function()
{
  // the compared version turned into the shown one, with the unchanged tokens around cut to '…'
  let texts = besogo.compareSgfTexts('(;GM[1]FF[4]SZ[19]AB[aa];B[bb];W[cc];B[dd])', '(;GM[1]FF[3]SZ[19]AB[aa];B[bb];W[cc];B[dd])');
  CHECK(!texts.sameTokens);
  CHECK_EQUALS(JSON.stringify(texts.parts), JSON.stringify([
    { type: 'same', text: '…' },
    { type: 'same', text: ';' },
    { type: 'same', text: 'GM[1]' },
    { type: 'removed', text: 'FF[3]' },
    { type: 'added', text: 'FF[4]' },
    { type: 'same', text: 'SZ[19]' },
    { type: 'same', text: 'AB[aa]' },
    { type: 'same', text: '…' }]));
});
