// Convert game state tree into SGF string
besogo.composeSgf = function(editor, expand = false)
{
  return '(' + composeNode(editor.getRoot(), editor.getRoot(), expand) + ')';

  // Recursively composes game node tree
  function composeNode(root, tree, expand, moveOverride = null)
  {
    var string = ';', // Node starts with semi-colon
        children = tree.children,
        i; // Scratch iteration variable

    if (!tree.parent) // Null parent means node is root
        // Compose root-specific properties
      string += composeRootProps(tree);
    string += composeNodeProps(root, tree, moveOverride); // Compose general properties

    // Recurse composition on child nodes
    if (children.length === 1 && (!expand || tree.virtualChildren.length == 0)) // Continue sequence if only one child
      string += '\n' + composeNode(root, children[0], expand);
    else
    {
      for (i = 0; i < children.length; i++)
        string += '\n(' + composeNode(root, children[i], expand) + ')';

      // Don't export alternative virtual white moves when normal moves are available
      // This is mainly because tsumego-hero can't support it
      if (expand && (tree.nextIsBlack() || tree.children.length == 0))
        for (i = 0; i < tree.virtualChildren.length; i++)
          if (tree.correct == CORRECT_GOOD || tree.virtualChildren[i].target.correct ==  CORRECT_BAD)
            string += '\n(' + composeNode(root, tree.virtualChildren[i].target, expand, tree.virtualChildren[i].move) + ')';
    }
    return string;
  }

  // Composes root specific properties
  function composeRootProps(tree)
  {
    var string = 'FF[4]GM[1]CA[UTF-8]AP[besogo:' + besogo.VERSION + ']',
        x = tree.getSize().x,
        y = tree.getSize().y,
        gameInfo = editor.getGameInfo(), // Game info structure
        hasGameInfo = false, // Flag for existence of game info
        id; // Scratch iteration variable

    if (x === y) // Square board size
      string += 'SZ[' + x + ']';
    else // Non-square board size
      string += 'SZ[' + x + ':' + y + ']';
    string += 'ST[' + editor.getVariantStyle() + ']\n'; // Line break after header

    for (id in gameInfo) // Compose game info properties
      if (gameInfo.hasOwnProperty(id) && gameInfo[id])
      { // Skip empty strings
        string += id + '[' + escapeText(gameInfo[id]) + ']';
        hasGameInfo = true;
      }
    string += (hasGameInfo ? '\n' : ''); // Line break if game info exists

    return string;
  }

  // Composes other properties
  function composeNodeProps(root, node, moveOverride)
  {
    var string = '',
        props, // Scratch variable for property structures
        stone, i, j; // Scratch iteration variables

    // Compose either move or setup properties depending on type of node
    if (node.getType() === 'move')  // Compose move properties
    {
      var move = moveOverride ? moveOverride : node.move;
      string += (move.color === 1) ? 'W' : 'B';
      string += '[' + coordsToLetters(move.x, move.y) + ']';
    }
    else if (node.getType() === 'setup') // Compose setup properties
    {
      props = { AB: [], AW: [], AE: [] };
      for (i = 1; i <= node.getSize().x; i++)
        for (j = 1; j <= node.getSize().y; j++)
        {
          stone = node.getSetup(i, j);
          if (stone) // If setup stone placed, add to structure
            props[ stone ].push({ x: i, y: j });
        }
      string += composePointLists(props);
    }

    // Compose markup properties
    props = { CR: [], SQ: [], TR: [], MA: [], SL: [], LB: [] };
    for (i = 1; i <= node.getSize().x; i++)
      for (j = 1; j <= node.getSize().y; j++)
      {
        stone = node.getMarkup(i, j);
        if (stone) // If markup placed
          if (typeof stone === 'string') // String is label mark
            props.LB.push({ x: i, y: j, label: stone });
          else
          { // Numerical code for markup
            // Convert numerical code to property ID
            stone = (['CR', 'SQ', 'TR', 'MA', 'SL'])[stone - 1];
            props[stone].push({ x: i, y: j });
          }
      }
    string += composePointLists(props);

    let correctToSave = (root.goal == GOAL_NONE) && node.correctSource;
    if (node.comment || correctToSave) // Compose comment property
    {
      string += (string ? '\n' : ''); // Add line break if other properties exist
      string += 'C[' + escapeText((correctToSave ? '+' : '') + node.comment) + ']';
    }

    if (node.statusSource)
    {
      string += (string ? '\n' : '');
      string += 'S[' + node.statusSource.str() + ']';
    }

    if (node.parent == null && node.goal != GOAL_NONE)
    {
      string += (string ? '\n' : '');
      string += 'G[' + besogo.goalStr(node.goal) + ']';
    }

    return string;
  }

  // Composes properties from structure of point lists
  // Each member should be an array of points for property ID = key
  // Each point should specify point with (x, y) and may have optional label
  function composePointLists(lists) {
    var string = '',
        id, points, i; // Scratch iteration variables

    for (id in lists) // Object own keys specifies property IDs
      if (lists.hasOwnProperty(id))
      {
        points = lists[id]; // Corresponding members are point lists
        if (points.length > 0) // Only add property if list non-empty
        {
          string += id;
          for (i = 0; i < points.length; i++)
          {
            string += '[' + coordsToLetters(points[i].x, points[i].y);
            if (points[i].label) // Add optional composed label
              string += ':' + escapeText(points[i].label);
            string += ']';
          }
        }
      }
    return string;
  }

  // Escapes backslash and close bracket for text output
  function escapeText(input)
  {
    input = input.replace(/\\/g, '\\\\'); // Escape backslash
    return input.replace(/\]/g, '\\]'); // Escape close bracket
  }

  // Converts numerical coordinates to letters
  function coordsToLetters(x, y)
  {
    if (x === 0 || y === 0)
      return '';
    return numToChar(x) + numToChar(y);
  }

  function numToChar(num) // Helper for coordsToLetters
  {
    if (num > 26) // Numbers 27-52 to A-Z
      return String.fromCharCode('A'.charCodeAt(0) + num - 27);

    // Numbers 1-26 to a-z
    return String.fromCharCode('a'.charCodeAt(0) + num - 1);
  }
};
