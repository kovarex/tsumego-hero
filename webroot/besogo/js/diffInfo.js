var DIFF_NO_CHANGE = 0;
var DIFF_ADDED = 1;
var DIFF_REMOVED = 2;

// A position of the tree shown as a diff: a move that is only in the shown version (added), only in the
// version it is compared with (removed), or in both, where its other properties can still differ (changes).
besogo.makeDiffInfo = function(type = DIFF_NO_CHANGE)
{
  var diffInfo = [];
  diffInfo.type = type;
  diffInfo.changes = []; // {property, before, after}, see besogo.compareNodeProperties
  return diffInfo;
}

besogo.makeAddedMoveDiffInfo = function()
{
  return besogo.makeDiffInfo(DIFF_ADDED);
}

besogo.makeRemovedMoveDiffInfo = function()
{
  return besogo.makeDiffInfo(DIFF_REMOVED);
}

// The differences between a position and the same position of the previous version, as a list of
// {property, before, after}. The root also compares what applies to the whole problem.
besogo.compareNodeProperties = function(node, previousNode)
{
  let changes = [];
  let root = node.getRoot();
  let previousRoot = previousNode.getRoot();

  function compare(property, after, before)
  {
    if (JSON.stringify(after) !== JSON.stringify(before))
      changes.push({property: property, before: before, after: after});
  }

  if (!node.parent)
  {
    compare('goal', node.goal, previousNode.goal);
    compare('firstMove', node.firstMove, previousNode.firstMove);
    let size = node.getSize(), previousSize = previousNode.getSize();
    compare('size', size.x + 'x' + size.y, previousSize.x + 'x' + previousSize.y);
  }
  compare('setup', besogo.listSetupStones(node), besogo.listSetupStones(previousNode));
  compare('markup', besogo.listMarkup(node), besogo.listMarkup(previousNode));
  compare('comment', node.comment, previousNode.comment);
  // the + of a correct end is only saved while the correct moves aren't controlled by status
  if (root.goal == GOAL_NONE && previousRoot.goal == GOAL_NONE)
    compare('markedCorrect', !!node.correctSource, !!previousNode.correctSource);
  compare('status', node.statusSource ? node.statusSource.strLong() : null, previousNode.statusSource ? previousNode.statusSource.strLong() : null);
  compare('superkoMeansDead', !!node.superkoMeansDead, !!previousNode.superkoMeansDead);
  // what the tree below makes of the move, so a branch that became wrong or correct shows up
  if (node.move)
    compare('correct', node.correct, previousNode.correct);
  return changes;
}

// The setup stones placed in the node, as [{x, y, value}] with value AB, AW or AE.
besogo.listSetupStones = function(node)
{
  let result = [];
  let size = node.getSize();
  for (let x = 1; x <= size.x; ++x)
    for (let y = 1; y <= size.y; ++y)
    {
      let value = node.getSetup(x, y);
      if (value)
        result.push({x: x, y: y, value: value});
    }
  return result;
}

// The markup of the node, as [{x, y, value}] with value the markup code (1 circle, 2 square, 3 triangle,
// 4 cross, 5 selected) or the text of a label.
besogo.listMarkup = function(node)
{
  let result = [];
  let size = node.getSize();
  for (let x = 1; x <= size.x; ++x)
    for (let y = 1; y <= size.y; ++y)
    {
      let value = node.getMarkup(x, y);
      if (value)
        result.push({x: x, y: y, value: value});
    }
  return result;
}

// The game info (SGF root properties like GN or SO) that differs, as changes of the root.
besogo.compareGameInfo = function(gameInfo, previousGameInfo)
{
  let changes = [];
  let keys = Object.keys(Object.assign({}, previousGameInfo, gameInfo)).sort();
  for (let key of keys)
    if ((gameInfo[key] || '') !== (previousGameInfo[key] || ''))
      changes.push({property: 'gameInfo', key: key, before: previousGameInfo[key] || '', after: gameInfo[key] || ''});
  return changes;
}
