// Load a parsed SGF object into a game state tree
var NORMAL_LOAD = 0;
var OPEN_FOR_DIFF = 1;

besogo.loadSgf = function(sgf, editor, mode = NORMAL_LOAD)
{
  let size = { x: 19, y: 19 }, // Default size (may be changed by load)
      root;
  let bCounter = 0;
  let wCounter = 0;
  loadRootProps(sgf); // Load size, variants style and game info
  root = besogo.makeGameRoot(size.x, size.y);

  loadNodeTree(sgf, root); // Load the rest of game tree
  root.figureFirstToMove();
  besogo.updateTreeAsProblem(root);

  besogo.scaleParameters = besogo.makeScaleParameters(size);
  besogo.scaleParameters.setupLimitsFromTree(root);
  besogo.scaleParameters.normalizeToTopLeft(size);
  besogo.scaleParameters.initializeRotation(-1);

  if (mode == NORMAL_LOAD)
    editor.loadRoot(root); // Load root into the editor
  else
  {
    besogo.scaleParameters.transform(root); // when applying diff, we need to transform now, as the other root is already normalised.
    editor.getRoot().incorporateDiff(root);
    editor.notifyListeners({treeChange: true, navChange: true, stoneChange: true});
  }

  if (besogo.scaleParameters.checkFullBoard(size))
    besogo.boardParameters.fullBoard = true;

  let boardCoordSize = size.x - 1;
  besogo.coordArea['lowestX'] = 0;
  besogo.coordArea['lowestY'] = 0;
  besogo.coordArea['highestX'] = boardCoordSize - besogo.scaleParameters.distanceToRight() + 3;
  besogo.coordArea['highestY'] = boardCoordSize - besogo.scaleParameters.distanceToBottom() + 3;
  if (besogo.coordArea['highestX'] > 11)
    besogo.coordArea['highestX'] = boardCoordSize;
  if (besogo.coordArea['highestY'] > 11)
    besogo.coordArea['highestY'] = boardCoordSize;

  if (size.x !== 19)
  {
    besogo.coordArea['highestX'] = boardCoordSize;
    besogo.coordArea['highestY'] = boardCoordSize;
  }

  return besogo.scaleParameters;

  // Loads the game tree
  function loadNodeTree(sgfNode, gameNode)
  {
    let i, nextGameNode;

    // Load properties from the SGF node into the game state node
    for (i = 0; i < sgfNode.props.length; i++)
      loadProp(gameNode, sgfNode.props[i]);

    // Recursively load the rest of the tree
    for (i = 0; i < sgfNode.children.length; i++)
    {
      nextGameNode = gameNode.makeChild();
      gameNode.addChild(nextGameNode);
      loadNodeTree(sgfNode.children[i], nextGameNode);
    }
  }

  // Loads property into node
  function loadProp(node, prop)
  {
    var setupFunc = 'placeSetup',
        markupFunc = 'addMarkup',
        move;
    switch(prop.id)
    {
      case 'B': // Play a black move
        move = lettersToCoords(prop.values[0]);
        node.playMove(move.x, move.y, -1, true);
        break;
      case 'W': // Play a white move
        move = lettersToCoords(prop.values[0]);
        node.playMove(move.x, move.y, 1, true);
        break;
      case 'AB': // Setup black stones
        applyPointList(prop.values, node, setupFunc, -1);
        break;
      case 'AW': // Setup white stones
        applyPointList(prop.values, node, setupFunc, 1);
        break;
      case 'AE': // Setup empty stones
        applyPointList(prop.values, node, setupFunc, 0);
        break;
      case 'CR': // Add circle markup
        applyPointList(prop.values, node, markupFunc, 1);
        break;
      case 'SQ': // Add square markup
        applyPointList(prop.values, node, markupFunc, 2);
	  if(besogo.multipleChoice)
        applyPointList2(prop.values, node, setupFunc, 1, besogo.multipleChoiceSetup);
        break;
      case 'TR': // Add triangle markup
        applyPointList(prop.values, node, markupFunc, 3);
      if(besogo.multipleChoice)
        applyPointList2(prop.values, node, setupFunc, -1, besogo.multipleChoiceSetup);
        break;
      case 'M': // Intentional fallthrough treats 'M' as 'MA'
      case 'MA': // Add 'X' cross markup
        applyPointList(prop.values, node, markupFunc, 4);
        break;
      case 'SL': // Add 'selected' (small filled square) markup
        applyPointList(prop.values, node, markupFunc, 5);
        break;
      case 'L': // Intentional fallthrough treats 'L' as 'LB'
      case 'LB': // Add label markup
        applyPointList(prop.values, node, markupFunc, 'label');
        break;
      case 'C': // Comment placed on node
        if (node.comment)
          node.comment += '\n' + prop.values.join().trim();
        else
          node.comment = prop.values.join().trim();
        break;
      case 'S':
        node.statusSource = besogo.loadStatusFromString(prop.values.join().trim());
        break;
      case 'G':
        node.goal = besogo.loadGoalFromString(prop.values.join().trim());
        break;
    }
  }

  // Extracts point list and calls func on each
  // Set param to 'label' to signal handling of label markup property
  function applyPointList(values, node, func, param)
  {
    var i, x, y, // Scratch iteration variables
        point, // Current point in iteration
        otherPoint, // Bottom-right point of compressed point lists
        label; // Label extracted from value

    for (i = 0; i < values.length; i++)
    {
      point = lettersToCoords(values[i].slice(0, 2));
      if (param === 'label') // Label markup property
      {
        label = values[i].slice(3).replace(/\n/g, ' ');
        node[func](point.x, point.y, label); // Apply with extracted label
      }
      else // Not a label markup property
        if (values[i].charAt(2) === ':') // Expand compressed point list
        {
          otherPoint = lettersToCoords(values[i].slice(3));
          if (otherPoint.x === point.x && otherPoint.y === point.y)
            // Redundant compressed pointlist
            node[func](point.x, point.y, param);
          else if (otherPoint.x < point.x || otherPoint.y < point.y)
          {
            // Only apply to corners if not arranged properly
            node[func](point.x, point.y, param);
            node[func](otherPoint.x, otherPoint.y, param);
          }
          else // Iterate over the compressed points
            for (x = point.x; x <= otherPoint.x; x++)
                for (y = point.y; y <= otherPoint.y; y++)
                    node[func](x, y, param);
        }
        else // Apply on single point
          node[func](point.x, point.y, param);
    }
  }

  //multiple choice random stone placement (triangle to black, square to white)
  function applyPointList2(values, node, func, param, multipleChoice=null)
  {
    var x, y, // Scratch iteration variables
        point, // Current point in iteration
        otherPoint, // Bottom-right point of compressed point lists
        label,
    counter;
    let bOrW = 0;
    if (param==-1)
      bOrW = 0;
    else if (param==1)
      bOrW = 1;
    for (let i = 0; i < values.length; i++)
    {
      if (param==-1)
        counter = bCounter;
      else if (param==1)
        counter = wCounter;

      if (multipleChoice[bOrW][counter]==1){
      point = lettersToCoords(values[i].slice(0, 2));
      if (param === 'label') // Label markup property
      {
        label = values[i].slice(3).replace(/\n/g, ' ');
        node[func](point.x, point.y, label); // Apply with extracted label
      }
      else if (values[i].charAt(2) === ':') // Expand compressed point list
      {
        otherPoint = lettersToCoords(values[i].slice(3));
        if (otherPoint.x === point.x && otherPoint.y === point.y)
        // Redundant compressed pointlist
        node[func](point.x, point.y, param);
        else if (otherPoint.x < point.x || otherPoint.y < point.y)
        {
        // Only apply to corners if not arranged properly
        node[func](point.x, point.y, param);
        node[func](otherPoint.x, otherPoint.y, param);
        }
        else // Iterate over the compressed points
        for (x = point.x; x <= otherPoint.x; x++)
          for (y = point.y; y <= otherPoint.y; y++)
            node[func](x, y, param);
      }
      else // Apply on single point
        node[func](point.x, point.y, param);
	  }
	  if (multipleChoice !== null){
		  if (param == -1)
			bCounter++;
		  else if (param == 1)
			wCounter++;
	  }
    }
  }

  // Loads root properties (size, variant style and game info)
  function loadRootProps(node)
  {
    var gameInfoIds = ['PB', 'BR', 'BT', 'PW', 'WR', 'WT', // Player info
            'HA', 'KM', 'RU', 'TM', 'OT', // Game parameters
            'DT', 'EV', 'GN', 'PC', 'RO', // Event info
            'GC', 'ON', 'RE', // General comments
            'AN', 'CP', 'SO', 'US' ], // IP credits
        gameInfo = {}, // Structure for game info properties
        i, id, value; // Scratch iteration variables

    for (i = 0; i < node.props.length; i++)
    {
      id = node.props[i].id; // Property ID
      value = node.props[i].values.join().trim(); // Join the values array
      if (id === 'SZ') // Size property
        size = besogo.parseSize(value);
      else if (id === 'ST')
        editor.setVariantStyle( +value ); // Converts value to number
      else if (gameInfoIds.indexOf(id) !== -1) // Game info property
      {
        if (id !== 'GC') // Treat all but GC as simpletext
          value = value.replace(/\n/g, ' '); // Convert line breaks to spaces
        if (value) // Skip load of empty game info strings
          gameInfo[id] = value;
      }
    }
    editor.setGameInfo(gameInfo);
  }

  // Converts letters to numerical coordinates
  function lettersToCoords(letters)
  {
    if (letters.match(/^[A-Za-z]{2}$/)) // Verify input is two letters
      return { x: charToNum(letters.charAt(0)), y: charToNum(letters.charAt(1)) };
    else // Anything but two letters
      return { x: 0, y: 0 }; // Return (0, 0) coordinates
  }

  function charToNum(c) // Helper for lettersToCoords
  {
    if (c.match(/[A-Z]/)) // Letters A-Z to 27-52
      return c.charCodeAt(0) - 'A'.charCodeAt(0) + 27;
    else  // Letters a-z to 1-26
      return c.charCodeAt(0) - 'a'.charCodeAt(0) + 1;
  }
};
