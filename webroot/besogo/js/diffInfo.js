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
  // the branches both versions have, in the order they are shown, so reordered branches show up
  compare('branchOrder', besogo.listSharedBranches(node, previousNode), besogo.listSharedBranches(previousNode, node));
  return changes;
}

// The moves of the node's children that the other node has a child for too, in order, as [{x, y}]
besogo.listSharedBranches = function(node, otherNode)
{
  return node.children
    .filter(function(child) { return otherNode.getCorrespondingChild(child); })
    .map(function(child) { return {x: child.move.x, y: child.move.y}; });
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

// The SGF files of a diff whose trees show no difference, compared as text, so that a change the
// tree doesn't show (a property besogo ignores, the order of properties...) is not hidden.
// Returns {identical, sameTokens, parts}: parts is the diff of the tokens (a property with its
// values, or ; ( )) as [{type: 'same' | 'removed' | 'added', text}], unchanged runs cut to
// SGF_TEXT_CONTEXT tokens around the changes and '…' between.
var SGF_TEXT_CONTEXT = 2;

besogo.compareSgfTexts = function(shownText, comparedText)
{
  let result = { identical: shownText === comparedText, sameTokens: false, parts: [] };
  if (result.identical)
    return result;
  let shown = besogo.tokenizeSgf(shownText), compared = besogo.tokenizeSgf(comparedText);
  result.sameTokens = JSON.stringify(shown) === JSON.stringify(compared);
  if (!result.sameTokens)
    result.parts = besogo.withContext(besogo.diffTokens(compared, shown));
  return result;
}

// Spacing and line breaks between the tokens and between the values of a property don't count
besogo.tokenizeSgf = function(text)
{
  let tokens = [];
  for (let match of text.matchAll(/[;()]|[A-Za-z]+\s*(?:\[(?:\\[\s\S]|[^\]\\])*\]\s*)+/g))
    tokens.push(match[0].trim().replace(/^([A-Za-z]+)\s+\[/, '$1[').replace(/\]\s+\[/g, ']['));
  return tokens;
}

// [{type, text}] turning the tokens before into the tokens after (longest common subsequence)
besogo.diffTokens = function(before, after)
{
  let start = 0;
  while (start < before.length && start < after.length && before[start] === after[start])
    ++start;
  let end = 0;
  while (end < before.length - start && end < after.length - start && before[before.length - 1 - end] === after[after.length - 1 - end])
    ++end;
  let removed = before.slice(start, before.length - end), added = after.slice(start, after.length - end);
  let parts = before.slice(0, start).map(function(text) { return { type: 'same', text: text }; });

  if (removed.length * added.length > 1000000) // too big to align, all of it changed
    parts = parts.concat(removed.map(function(text) { return { type: 'removed', text: text }; }),
                         added.map(function(text) { return { type: 'added', text: text }; }));
  else
  {
    // common[i][j]: length of the longest common subsequence of removed[i..] and added[j..]
    let common = [];
    for (let i = removed.length; i >= 0; --i)
    {
      common[i] = new Array(added.length + 1).fill(0);
      if (i < removed.length)
        for (let j = added.length - 1; j >= 0; --j)
          common[i][j] = removed[i] === added[j] ? common[i + 1][j + 1] + 1 : Math.max(common[i + 1][j], common[i][j + 1]);
    }
    let i = 0, j = 0;
    while (i < removed.length || j < added.length)
      if (i < removed.length && j < added.length && removed[i] === added[j])
      {
        parts.push({ type: 'same', text: removed[i] });
        ++i;
        ++j;
      }
      else if (i < removed.length && (j == added.length || common[i + 1][j] >= common[i][j + 1]))
        parts.push({ type: 'removed', text: removed[i++] });
      else
        parts.push({ type: 'added', text: added[j++] });
  }
  return parts.concat(before.slice(before.length - end).map(function(text) { return { type: 'same', text: text }; }));
}

besogo.withContext = function(parts)
{
  let result = [];
  for (let i = 0; i < parts.length; ++i)
  {
    let near = false;
    for (let k = Math.max(0, i - SGF_TEXT_CONTEXT); k <= Math.min(parts.length - 1, i + SGF_TEXT_CONTEXT); ++k)
      near = near || parts[k].type != 'same';
    if (near)
      result.push(parts[i]);
    else if (result.length == 0 || result[result.length - 1].text !== '…')
      result.push({ type: 'same', text: '…' });
  }
  return result;
}
