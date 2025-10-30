besogo.updateTreeAsProblem = function(root)
{
  root.prunnedMoveCount = 0;
  besogo.pruneTree(root, root);
  //if (root.prunnedMoveCount) window.alert("Pruned move count: " + root.prunnedMoveCount + " (out of original " + (root.prunnedMoveCount + root.treeSize()) + ")");
  besogo.addRelevantMoves(root, root)
  var test = 0;
  for (let i = 0; i < root.relevantMoves.length; ++i)
    if (root.relevantMoves[i])
      ++test;
  besogo.addVirtualChildren(root, root, false);
  besogo.updateCorrectValues(root);
};

besogo.addRelevantMoves = function(root, node)
{
  for (let i = 0; i < node.setupStones.length; ++i)
    if (node.setupStones[i])
      root.relevantMoves[i] = true;
  if (node.move)
  {
    var move = [];
    move.x = node.move.x;
    move.y = node.move.y;
    root.relevantMoves[root.fromXY(node.move.x, node.move.y)] = true;
  }
  for (let i = 0; i < node.children.length; ++i)
    besogo.addRelevantMoves(root, node.children[i]);
}

besogo.addVirtualChildren = function(root, node, addHash = true)
{
  if (besogo.vChildrenEnabled)
  {
    if (node.virtualChildren.length > 0)
      return;

    if (addHash)
      root.nodeHashTable.push(node);

    var sizeX = root.getSize().x;
    var sizeY = root.getSize().y;
    for (let i = 0; i < root.relevantMoves.length; ++i)
    {
      if (!root.relevantMoves[i])
        continue;
      var move = root.toXY(i);
      if (!node.getStone(move.x, move.y))
      {
        var testChild = node.makeChild()
        if (!testChild.playMove(move.x, move.y))
        {
          node.removeChild(testChild);
          continue;
        }

        var sameNode = root.nodeHashTable.getSameNode(testChild);
        if (sameNode && sameNode.parent != node)
        {
          var redirect = [];
          redirect.target = sameNode;
          redirect.move = [];
          redirect.move.x = move.x;
          redirect.move.y = move.y;
          redirect.move.captures = testChild.move.captures;
          redirect.move.color = node.nextMove();
          node.virtualChildren.push(redirect);
          redirect.target.virtualParents.push(node);
          node.correctSource = false;
        }
      }
    }

    for (let i = 0; i < node.children.length; ++i)
      besogo.addVirtualChildren(root, node.children[i], addHash);
  }
}

besogo.pruneTree = function(root, node)
{
  root.nodeHashTable.push(node);
  for (let i = 0; i < node.children.length;)
  {
    var child = node.children[i];
    if (root.nodeHashTable.getSameNode(child))
    {
      root.prunnedMoveCount += child.treeSize();
      node.removeChild(child);
    }
    else
    {
      besogo.pruneTree(root, child);
      ++i;
    }
  }
};

besogo.clearCorrectValues = function(node)
{
  node.correct = CORRECT_EMPTY;
  node.status = null;
  if (node.hasNonLocalChildIncludingVirtual())
    node.statusSource = null;
  for (let i = 0; i < node.children.length; ++i)
    besogo.clearCorrectValues(node.children[i]);
}

besogo.updateCorrectValues = function(root)
{
  besogo.clearCorrectValues(root);
  besogo.updateStatusValuesInternal(root, root, root.goal);
  if (root.goal == GOAL_NONE)
    besogo.updateCorrectValuesInternal(root, root);
  else
    besogo.updateCorrectValuesBasedOnStatus(root, root.goal, root.status, true /* isCorrectBranch */);
  root.unvisit();
}

besogo.updateStatusResult = function(solversMove, child, status, goal)
{
  if (child.status.isNone())
    return status;

  if (child.status.better(status, goal) == solversMove)
    return child.status;
  else
    return status;
}

besogo.updateStatusValuesInternal = function(root, node, goal)
{
  if (node.statusSource)
  {
    console.assert(!node.hasNonLocalChildIncludingVirtual());
    node.status = node.statusSource;
    return;
  }
  if (node.status)
    return;

  // If we encounter this node again while resolving status, it will be marked as super ko
  // result and we won't go into an infinite loop.
  node.status = besogo.makeStatusSimple(STATUS_ALIVE_IN_SUPER_KO);

  for (let i = 0; i < node.children.length; ++i)
    besogo.updateStatusValuesInternal(root, node.children[i], goal);
  for (let i = 0; i < node.virtualChildren.length; ++i)
  {
	try
    {
	    besogo.updateStatusValuesInternal(root, node.virtualChildren[i].target, goal);
    }
	  catch(e)
	  {
	    if (besogo.isEmbedded)
		    besogo.editor.displayError("Error: too much recursion.");
	  }
  }
  // once the child resolution is done, this node can go back to none, so it its status is assigned in a normal way
  node.status = besogo.makeStatusSimple(STATUS_NONE);

  let solversMove = (node.nextMove() == root.firstMove);

  if (solversMove == (goal == GOAL_KILL))
    node.status = besogo.makeStatusSimple(STATUS_ALIVE_NONE);
  else
    node.status = besogo.makeStatusSimple(STATUS_DEAD_NONE);

  for (let i = 0; i < node.children.length; ++i)
    node.status = besogo.updateStatusResult(solversMove, node.children[i], node.status, goal);
  for (let i = 0; i < node.virtualChildren.length; ++i)
    node.status = besogo.updateStatusResult(solversMove, node.virtualChildren[i].target, node.status, goal);

  if (node.status.blackFirst.type == STATUS_ALIVE_NONE || node.status.blackFirst.type == STATUS_DEAD_NONE)
    node.status = besogo.makeStatusSimple(STATUS_NONE);
}

besogo.updateCorrectValuesInternal = function(root, node)
{
  node.visited = true;
  if (node.comment.startsWith("+"))
  {
    if (!node.correctSource)
    {
      node.correctSource = true;
      node.comment = node.comment.substr(1);
    }
    node.correct = CORRECT_GOOD;
    return true;
  }

  if (node.correctSource)
  {
    node.correct = CORRECT_GOOD;
    return true;
  }

  if (node.correct != CORRECT_EMPTY)
    return node.correct;

  let hasLoss = false;
  let hasWin = false;
  let hasUndefined = false;

  for (let i = 0; i < node.children.length; ++i)
    if (!node.children[i].visited && !node.children[i].localEdit)
    {
      let parentStatus = besogo.updateCorrectValuesInternal(root, node.children[i]);
      if (parentStatus == CORRECT_GOOD)
        hasWin = true;
      else if (parentStatus == CORRECT_BAD)
        hasLoss = true;
      else if (parentStatus == CORRECT_UNDEFINED)
        hasUndefined = true;
      else
        console.assert(false);
    }

  for (let i = 0; i < node.virtualChildren.length; ++i)
    if (!node.virtualChildren[i].visited && !node.virtualChildren[i].localEdit)
    {
      let parentStatus = besogo.updateCorrectValuesInternal(root, node.virtualChildren[i].target);
      if (parentStatus == CORRECT_GOOD)
        hasWin = true;
      else if (parentStatus == CORRECT_BAD)
        hasLoss = true;
      else if (parentStatus == CORRECT_UNDEFINED)
        hasUndefined = true;
      else
        console.assert(false);
    }

  let solversMove = (node.nextMove() == root.firstMove);
  if (solversMove)
    node.correct = hasWin ? CORRECT_GOOD : CORRECT_BAD; // on my move, one winning is enough for the branch to be correct
  else if (hasUndefined && !hasLoss)
    node.correct = CORRECT_UNDEFINED; // if there is loss possibilty on opponents move, it is loss whatever, so underfined only if it could  change that all variants are good for solver
  else
    node.correct = (hasWin && !hasLoss) ? CORRECT_GOOD : CORRECT_BAD;

  return node.correct;
};

besogo.updateCorrectValuesBasedOnStatus = function(node, goal, parentStatus, isCorrectBranch)
{
  // lets just remove the extra + when we only care about status when determining correct variants (to avoid the + being accumulated)
  if (node.comment.startsWith("+"))
    node.comment = node.comment.substr(1);

  if (node.correct != CORRECT_EMPTY)
    return;
  if (node.status.isNone())
    node.correct = CORRECT_UNDEFINED;
  else if (parentStatus.isNone())
    if (!node.status.isNone())
      node.correct = CORRECT_GOOD;
    else
      node.correct = CORRECT_UNDEFINED;
  else
    node.correct = (isCorrectBranch == CORRECT_GOOD && !parentStatus.better(node.status, goal)) ? CORRECT_GOOD : CORRECT_BAD;

  for (let i = 0; i < node.children.length; ++i)
    if (!node.children[i].localEdit)
      besogo.updateCorrectValuesBasedOnStatus(node.children[i], goal, node.status, node.correct)
};
