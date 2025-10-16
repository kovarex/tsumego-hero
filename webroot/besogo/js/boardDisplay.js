besogo.makeBoardDisplay = function(container, editor, corner)
{
  var CELL_SIZE = 88, // Including line width
      COORD_MARGIN = 75, // Margin for coordinate labels
      EXTRA_MARGIN = 6, // Extra margin on the edge of board
      BOARD_MARGIN, // Total board margin

      // Board size parameters
      sizeX = editor.getCurrent().getSize().x,
      sizeY = editor.getCurrent().getSize().y,

      svg, // Holds the overall board display SVG element
      stoneGroup, // Group for stones
      markupGroup, // Group for markup
      nextMoveGroup, // Group for next move markers
      hoverGroup, // Group for hover layer
      markupLayer, // Array of markup layer elements
      hoverLayer, // Array of hover layer elements

      randIndex, // Random index for stone images
      lastHoverPosition = null,
      TOUCH_FLAG = false; // Flag for touch interfaces

  try
  {
    besogo.scaleParameters['boardCoordSize'] = sizeX;
  }
  catch (error)
  {
    if (besogo.isEmbedded)
	  besogo.editor.displayError('Error: file not readable.');
  }
  
  
  //Not a 19x19 board, so it should be full board view
  //or not embedded viewer
  if (sizeX != 19 || !besogo.isEmbedded)
  {
    besogo.scaleParameters['orientation'] = 'full-board';
    besogo.boardParameters['fullBoard'] = true;
    besogo.controlButtonLock = false;
  }

  besogo.boardParameters['fullBoard'] = false;
  besogo.boardParameters['corner'] = null;
  besogo.boardParameters['fullBoardWidth'] = 0;
  besogo.boardParameters['fullBoardHeight'] = 0;
  besogo.boardParameters['boardWidth'] = 0;
  besogo.boardParameters['boardHeight'] = 0;
  besogo.boardParameters['boardWidth2'] = 0;
  besogo.boardParameters['boardHeight2'] = 0;
  besogo.boardParameters['boardWidth3'] = 0;
  besogo.boardParameters['boardHeight3'] = 0;
  besogo.boardParameters['playerColor'] = 'black';

  initializeBoard(editor.getCoordStyle()); // Initialize SVG element and draw the board
  container.appendChild(svg); // Add the SVG element to the document
  editor.addListener(update); // Register listener to handle editor/game state updates
  redrawAll(editor.getCurrent()); // Draw stones, markup and hover layer

  // Set listener to detect touch interfaces
  container.addEventListener('touchstart', setTouchFlag);

  return {
      redrawHover: redrawHover,
    displayHoverCoord: displayHoverCoord
    };

  // Function for setting the flag for touch interfaces
  function setTouchFlag ()
  {
    TOUCH_FLAG = true; // Set flag to prevent needless function calls
    hoverLayer = []; // Drop hover layer references, kills events
    svg.removeChild(hoverGroup); // Remove hover group from SVG
    // Remove self when done
    container.removeEventListener('touchstart', setTouchFlag);
  }

  // Initializes the SVG and draws the board
  function initializeBoard(coord)
  {
    drawBoard(coord); // Initialize the SVG element and draw the board

    stoneGroup = besogo.svgEl("g");
    markupGroup = besogo.svgEl("g");
    nextMoveGroup = besogo.svgEl("g");

    svg.appendChild(stoneGroup); // Add placeholder group for stone layer
    svg.appendChild(markupGroup); // Add placeholder group for markup layer
    svg.appendChild(nextMoveGroup);

    if (!TOUCH_FLAG)
    {
      hoverGroup = besogo.svgEl("g");
      svg.appendChild(hoverGroup);
    }

    addEventTargets(); // Add mouse event listener layer

    if (editor.REAL_STONES) // Generate index for realistic stone images
      randomizeIndex();
  }

  // Callback for board display redraws
  function update(msg)
  {
    var current = editor.getCurrent(),
        currentSize = current.getSize(),
        reinit = false, // Board redraw flag
        oldSvg = svg;

    // Check if board size has changed
    if (currentSize.x !== sizeX || currentSize.y !== sizeY || msg.coord)
    {
      sizeX = currentSize.x;
      sizeY = currentSize.y;
      initializeBoard(msg.coord || editor.getCoordStyle()); // Reinitialize board
      container.replaceChild(svg, oldSvg);
      reinit = true; // Flag board redrawn
    }

    // Redraw stones only if needed
    if (reinit || msg.navChange || msg.stoneChange)
      redrawAll(current);
    else if (msg.markupChange || msg.treeChange)
    {
      redrawMarkup(current);
      redrawHover(current);
    }
    else if (msg.tool || msg.label)
      redrawHover(current);
  }

  function redrawAll(current)
  {
    redrawStones(current);
    redrawMarkup(current);
    redrawNextMoves(current, besogo.isEmbedded && !editor.getReviewMode());
    redrawHover(current);
  }

  // Initializes the SVG element and draws the board
  function drawBoard(coord)
  {
    var string = ""; // Path string for inner board lines

    BOARD_MARGIN = (coord === 'none' ? 0 : COORD_MARGIN);
    boardWidth = 2*BOARD_MARGIN + sizeX*CELL_SIZE;
    boardHeight = 2*BOARD_MARGIN + sizeY*CELL_SIZE;

    let modifiedSizeX = besogo.scaleParameters.highest.x;
    let modifiedSizeY = besogo.scaleParameters.highest.y;

    if (besogo.scaleParameters.orientation !== 'full-board')
    {
      besogo.boardParameters['corner'] = besogo.scaleParameters.orientation;

      board_margin_x = BOARD_MARGIN - 75;
      board_margin_y = BOARD_MARGIN - 75;

      modifiedSizeX = besogo.scaleParameters.highest.x;
      modifiedSizeY = besogo.scaleParameters.highest.y;

      if (besogo.scaleParameters.distanceToRight() !== 0)
        numberOfXEdges = 1;
      else
        numberOfXEdges = 2;

      if (besogo.scaleParameters.distanceToBottom() !== 0)
        numberOfYEdges = 1;
      else
        numberOfYEdges = 2;

      let boardWidth = numberOfXEdges * BOARD_MARGIN + modifiedSizeX * CELL_SIZE;
      let boardHeight = numberOfYEdges * BOARD_MARGIN + modifiedSizeY * CELL_SIZE;
      besogo.boardParameters['boardWidth'] = boardWidth;
      besogo.boardParameters['boardHeight'] = boardHeight;
      besogo.boardParameters['fullBoardWidth'] = 2 * BOARD_MARGIN + besogo.scaleParameters.size.x * CELL_SIZE;
      besogo.boardParameters['fullBoardHeight'] = 2 * BOARD_MARGIN + besogo.scaleParameters.size.y * CELL_SIZE;
    }
    else
      besogo.scaleParameters.boardCanvasSize = 'full board';

    svg = besogo.svgEl("svg", { // Initialize the SVG element
        width: "100%",
        height: "100%",
        viewBox: "0 0 " + boardWidth + " " + boardHeight //top-left
    });
    besogo.boardCanvasSvg = svg;
    besogo.boardParameters['boardWidth2'] = besogo.boardParameters['fullBoardWidth'] - besogo.boardParameters['boardWidth'];
    besogo.boardParameters['boardWidth3'] = besogo.boardParameters['fullBoardWidth'] - besogo.boardParameters['boardWidth2'];

    besogo.boardParameters['boardHeight2'] = besogo.boardParameters['fullBoardWidth'] - besogo.boardParameters['boardHeight'];
    besogo.boardParameters['boardHeight3'] = besogo.boardParameters['fullBoardWidth'] - besogo.boardParameters['boardHeight2'];

    if (besogo.scaleParameters.orientation == 'full-board')
    {
      if (corner === 'full-board')
        svg.setAttribute('viewBox', 0 + ' ' + 0 + ' ' + besogo.boardParameters['fullBoardWidth'] + ' ' + besogo.boardParameters['boardHeight']);
    }
    else if (corner === 'top-left')
      svg.setAttribute('viewBox', 0 + ' ' + 0 + ' ' + besogo.boardParameters['boardWidth3'] + ' ' + besogo.boardParameters['boardHeight3']);
    else if (corner === 'top-right')
      svg.setAttribute('viewBox', besogo.boardParameters['boardWidth2'] + ' ' + 0 + ' ' + besogo.boardParameters['boardWidth3'] + ' ' + besogo.boardParameters['boardHeight3']);
    else if (corner === 'bottom-left')
      svg.setAttribute('viewBox', 0 + ' ' + besogo.boardParameters['boardHeight2'] + ' ' + besogo.boardParameters['boardWidth3'] + ' ' + besogo.boardParameters['boardHeight3']);
    else if (corner === 'bottom-right')
      svg.setAttribute('viewBox', besogo.boardParameters['boardWidth2'] + ' ' + besogo.boardParameters['boardHeight2'] + ' ' + besogo.boardParameters['boardWidth3'] + ' ' + besogo.boardParameters['boardHeight3']);
    svg.appendChild(besogo.svgEl("rect", { // Draw outer square of board
          width: CELL_SIZE*(sizeX - 1),
          height: CELL_SIZE*(sizeY - 1),
          x: svgPos(1),
          y: svgPos(1),
          'class': 'besogo-svg-lines'
      }));

    for (let i = 2; i <= (sizeY - 1); i++) // Horizontal inner lines
      string += "M" + svgPos(1) + "," + svgPos(i) + "h" + CELL_SIZE*(sizeX - 1);
    for (let i = 2; i <= (sizeX - 1); i++) // Vertical inner lines
      string += "M" + svgPos(i) + "," + svgPos(1) + "v" + CELL_SIZE*(sizeY - 1);

    svg.appendChild( besogo.svgEl("path", { // Draw inner lines of board
        d: string,
        'class': 'besogo-svg-lines'
    }) );

    drawHoshi(); // Draw the hoshi points

    if (coord !== 'none')
      drawCoords(coord, besogo.scaleParameters['orientation']); // Draw the coordinate labels
  }

  // Draws coordinate labels on the board
  function drawCoords(coord, corner)
  {
    let fill = 'black';
    if(besogo.theme==='texture26')
      fill = '#bbb';
    else if(besogo.theme==='texture25')
      fill = '#000';
    else if(besogo.theme==='texture54')
      fill = '#aaa';
    else if(besogo.theme==='texture41')
      fill = '#bbb';
    else if(besogo.theme==='texture40')
      fill = '#bbb';
    else if(besogo.theme==='texture38')
      fill = '#bbb';
    else if(besogo.theme==='texture22')
      fill = '#bbb';
    var labels = besogo.coord[coord](sizeX, sizeY),
        labelXa = labels.x, // Top edge labels
        labelXb = labels.xb || labels.x, // Bottom edge
        labelYa = labels.y, // Left edge
        labelYb = labels.yb || labels.y, // Right edge
        shift = COORD_MARGIN + 10,
        i, x, y; // Scratch iteration variable

    for (let i = 1; i <= sizeX; i++) // Draw column coordinate labels
    {
      x = svgPos(i);
      drawCoordLabel(x, svgPos(1) - shift, labelXa[i], fill);
      drawCoordLabel(x, svgPos(sizeY) + shift, labelXb[i], fill);
    }
    for (let i = 1; i <= sizeY; i++) // Draw row coordinate labels
    {
      y = svgPos(i);
      drawCoordLabel(svgPos(1) - shift, y, labelYa[i], fill);
      drawCoordLabel(svgPos(sizeX) + shift, y, labelYb[i], fill);
    }

    function drawCoordLabel(x, y, label, color)
    {
      var element = besogo.svgEl("text", {
          x: x,
          y: y,
          dy: ".65ex", // Seems to work for vertically centering these fonts
          "font-size": 32,
          "text-anchor": "middle", // Horizontal centering
          "font-family": "Helvetica, Arial, sans-serif",
          fill: color
      });
      element.appendChild( document.createTextNode(label) );
      svg.appendChild(element);
    }
  }

  // Draws hoshi onto the board at procedurally generated locations
  function drawHoshi()
  {
    var cx, cy, // Center point calculation
        pathStr = ""; // Path string for drawing star points

    if (sizeX % 2 && sizeY % 2)  // Draw center hoshi if both dimensions are odd
    {
      cx = (sizeX - 1)/2 + 1; // Calculate the center of the board
      cy = (sizeY - 1)/2 + 1;
      drawStar(cx, cy);

      if (sizeX >= 17 && sizeY >= 17) // Draw side hoshi if at least 17x17 and odd
      {
        drawStar(4, cy);
        drawStar(sizeX - 3, cy);
        drawStar(cx, 4);
        drawStar(cx, sizeY - 3);
      }
    }

    if (sizeX >= 11 && sizeY >= 11) // Corner hoshi at (4, 4) for larger sizes
    {
      drawStar(4, 4);
      drawStar(4, sizeY - 3);
      drawStar(sizeX - 3, 4);
      drawStar(sizeX - 3, sizeY - 3);
    }
    else if (sizeX >= 8 && sizeY >= 8) // Corner hoshi at (3, 3) for medium sizes
    {
      drawStar(3, 3);
      drawStar(3, sizeY - 2);
      drawStar(sizeX - 2, 3);
      drawStar(sizeX - 2, sizeY - 2);
    } // No corner hoshi for smaller sizes

    if (pathStr) // Only need to add if hoshi drawn
      svg.appendChild(besogo.svgEl('path', { // Drawing circles via path points
          d: pathStr, // Hack to allow radius adjustment via stroke-width
          'stroke-linecap': 'round', // Makes the points round
          'class': 'besogo-svg-hoshi'
      }) );

    function drawStar(i, j) // Extend path string to draw star point
    {
      pathStr += "M" + svgPos(i) + ',' + svgPos(j) + 'l0,0'; // Draws a point
    }
  }

  // Remakes the randomized index for stone images
  function randomizeIndex()
  {
    var maxIndex = besogo.BLACK_STONES * besogo.WHITE_STONES;

    randIndex = [];
    for (let i = 1; i <= sizeX; i++)
      for (let j = 1; j <= sizeY; j++)
        randIndex[fromXY(i, j)] = Math.floor(Math.random() * maxIndex);
  }

  // Adds a grid of squares to register mouse events
  function addEventTargets()
  {
    for (let i = 1; i <= sizeX; i++)
      for (let j = 1; j <= sizeY; j++)
      {
        var element = besogo.svgEl("rect", { // Make a transparent event target
            x: svgPos(i) - CELL_SIZE/2,
            y: svgPos(j) - CELL_SIZE/2,
            width: CELL_SIZE,
            height: CELL_SIZE,
            opacity: 0
        });

          // Add event listeners, using closures to decouple (i, j)
        element.addEventListener("click", handleClick(i, j));

        if (!TOUCH_FLAG)  // Skip hover listeners for touch interfaces
        {
          element.addEventListener("mouseover", handleOver(i, j));
          element.addEventListener("mouseout", handleOut(i, j));
        }

        svg.appendChild(element);
      }
  }

  function handleClick(i, j) // Returns function for click handling
  {
    return function(event)
    {
      if(besogo.intuitionActive)
      {
        deleteNextMoveGroup = false;
        editor.setReviewMode(false);
        besogo.intuitionActive = false;
      }
      editor.click(i, j, event.ctrlKey, event.shiftKey);
      if (!TOUCH_FLAG)
        (handleOver(i, j))(); // Ensures that any updated tool is visible
    };
  }

  function handleOver(i, j) // Returns function for mouse over
  {
    return function()
    {
      lastHoverPosition = [];
      lastHoverPosition.x = i;
      lastHoverPosition.y = j;
      updateHoverState();
    };
  }

  function updateHoverState()
  {
	let displayHover = false;
	if (!besogo.isEmbedded)
		displayHover = true;
	else if (!besogo.multipleChoice && boardLockValue==0)
		displayHover = true;
	if (displayHover)
	{
		if (lastHoverPosition == null)
			return;
		if (element = hoverLayer[fromXY(lastHoverPosition.x, lastHoverPosition.y)]) // Make tool action visible on hover over
			element.setAttribute('visibility', 'visible');
	}
  }

  function handleOut(i, j)  // Returns function for mouse off
  {
    return function()
    {
      lastHoverPosition = null;
      if (element = hoverLayer[fromXY(i, j)]) // Make tool action invisible on hover off
        element.setAttribute('visibility', 'hidden');
    };
  }

  // Redraws the stones
  function redrawStones(current)
  {
    var group = besogo.svgEl("g"), // New stone layer group
        shadowGroup, // Group for shadow layer
        i, j, x, y, color; // Scratch iteration variables

    // Add group for shawdows
    if (editor.SHADOWS)
    {
      shadowGroup = besogo.svgShadowGroup();
      group.appendChild(shadowGroup);
    }

    for (i = 1; i <= sizeX; i++)
      for (j = 1; j <= sizeY; j++)
      {
        color = current.getStone(i, j);
        if (color)
        {
          x = svgPos(i);
          y = svgPos(j);

          if (editor.REAL_STONES) // Realistic stone
            group.appendChild(besogo.realStone(x, y, color, randIndex[fromXY(i, j)], editor.BLACK_STONES, editor.WHITE_STONES));
          else // SVG stone
            group.appendChild(besogo.svgStone(x, y, color));

          // Draw shadows
          if (editor.SHADOWS)
          {
            shadowGroup.appendChild(besogo.svgShadow(x - 2, y - 4));
            shadowGroup.appendChild(besogo.svgShadow(x + 2, y + 4));
          }
        }
      }
    let koPosition = current.getForbiddenMoveBecauseOfKo();
    if (koPosition)
      group.appendChild(besogo.svgSquare(svgPos(koPosition.x), svgPos(koPosition.y), "black", 2));

    svg.replaceChild(group, stoneGroup); // Replace the stone group
    stoneGroup = group;
  }

  // Redraws the markup
  function redrawMarkup(current)
  {
    var group = besogo.svgEl("g"), // Group holding markup layer elements
        lastMove = current.move,
        variants = editor.getVariants();

    markupLayer = []; // Clear the references to the old layer

    for (let i = 1; i <= sizeX; i++)
      for (let j = 1; j <= sizeY; j++)
        if (mark = current.getMarkup(i, j))
        {
          var x = svgPos(i);
          var y = svgPos(j);
          var stone = current.getStone(i, j);
          var color = (stone === -1) ? "white" : "black"; // White on black
          if (lastMove && lastMove.x === i && lastMove.y === j) // Mark last move blue or violet if also a variant
            color = checkVariants(variants, current, i, j) ? besogo.PURP : besogo.BLUE;
          else if (checkVariants(variants, current, i, j))
            color = besogo.RED; // Natural variant marks are red
          var element = null;
          if (typeof mark === 'number') // Markup is a basic shape
          {
            switch(mark)
            {
              case 1: element = besogo.svgCircle(x, y, color); break;
              case 2: element = besogo.svgSquare(x, y, color); break;
              case 3: element = besogo.svgTriangle(x, y, color); break;
              case 4: element = besogo.svgCross(x, y, color); break;
              case 5: element = besogo.svgBlock(x, y, color); break;
            }
          }
          else
          { // Markup is a label
            if (!stone) // If placing label on empty spot
            {
              element = makeBacker(x, y);
              group.appendChild(element);
            }
            element = besogo.svgLabel(x, y, color, mark);
          }
      if (!besogo.multipleChoice)
      {
        group.appendChild(element);
        markupLayer[fromXY(i, j)] = element;
      }
      else
      {
      //group.appendChild(element);
      //markupLayer[fromXY(i, j)] = element;
      }
    }

    // Mark last move with circle if not already marked
    if (lastMove && lastMove.x !== 0 && lastMove.y !== 0 &&
        !markupLayer[fromXY(lastMove.x, lastMove.y)]) // Last move not marked
    {
      let moveToUse = lastMove;
      if (current.cameFrom && current.cameFrom.getMoveToGetToVirtualChild(current))
        moveToUse = current.cameFrom.getMoveToGetToVirtualChild(current);
      let color = current.nextIsBlack() ? "black" : "white";
      let element = besogo.svgCircle(svgPos(moveToUse.x), svgPos(moveToUse.y), color, 20, 4);
      group.appendChild(element);
      markupLayer[fromXY(moveToUse.x, moveToUse.y)] = element;
      if (current.diffInfo)
        if (current.diffInfo.type == DIFF_ADDED)
          group.appendChild(besogo.svgPlus(svgPos(moveToUse.x), svgPos(moveToUse.y), color));
        else if (current.diffInfo.type == DIFF_REMOVED)
          group.appendChild(besogo.svgCross(svgPos(moveToUse.x), svgPos(moveToUse.y), color));
    }

    svg.replaceChild(group, markupGroup); // Replace the markup group
    markupGroup = group;
  }

  function redrawNextMovesDiffInfo(group, current)
  {
    for (let i = 0; i < current.children.length; ++i)
      redrawNextMoveDiffInfo(group, current.children[i], current.children[i].move);
    for (let i = 0; i < current.virtualChildren.length; ++i)
      redrawNextMoveDiffInfo(group, current.virtualChildren[i].target, current.virtualChildren[i].move);
  }

  function redrawNextMoveDiffInfo(group, node, move)
  {
    if (!node.diffInfo)
      return;
    if (node.diffInfo.type == DIFF_ADDED)
    {
      var plus = besogo.svgPlus(svgPos(move.x), svgPos(move.y), 'black');
      group.appendChild(plus);
    }
    else if (node.diffInfo.type == DIFF_REMOVED)
    {
      var cross = besogo.svgCross(svgPos(move.x), svgPos(move.y), 'black');
      group.appendChild(cross);
    }
  }

  function redrawNextMoveStatus(group, node, move)
  {
    if (node.status.blackFirst.type == STATUS_NONE)
      return;
    var label = besogo.svgLabel(svgPos(move.x), svgPos(move.y + 0.25), 'black', node.status.str(), 25);
    group.appendChild(label);
  }

  function redrawNextMoveStatuses(group, current)
  {
    for (let i = 0; i < current.children.length; ++i)
      redrawNextMoveStatus(group, current.children[i], current.children[i].move);
    for (let i = 0; i < current.virtualChildren.length; ++i)
      redrawNextMoveStatus(group, current.virtualChildren[i].target, current.virtualChildren[i].move);
  }

  function displayHoverCoord(c = -1)
  {
	if(besogoRotation!==-1)
		return null;
    let x = -1;
    let y = -1;
    if (c!=-1)
    {
      let c1 = c.charAt(0).toUpperCase();
      let c2 = c.substring(1);
      c1 = c1.charCodeAt(0)-65;
      if (c1>7)
        c1--;
      c2 = besogo.scaleParameters['boardCoordSize']-c2;

      x = c1;
      y = c2;

      if (besogo.coordArea['lowestX']>besogo.coordArea['highestX'])
      {
        buffer = besogo.coordArea['lowestX'];
        besogo.coordArea['lowestX'] = besogo.coordArea['highestX'];
        besogo.coordArea['highestX'] = buffer;
      }
      if (besogo.coordArea['lowestY']>besogo.coordArea['highestY'])
      {
        buffer = besogo.coordArea['lowestY'];
        besogo.coordArea['lowestY'] = besogo.coordArea['highestY'];
        besogo.coordArea['highestY'] = buffer;
      }

      let found = false;
      let spin = 0;
      while (spin<4)
      {
        if (!found)
        {
          if (spin==1 || spin==3)
            if (besogo.scaleParameters['boardCanvasSize']!=='horizontal half board')
              x = besogo.scaleParameters['boardCoordSize'] - x - 1;
            else
              y = besogo.scaleParameters['boardCoordSize'] - y - 1;
          else if (spin==2)
            y = besogo.scaleParameters['boardCoordSize'] - y - 1;
          if (x >= besogo.coordArea['lowestX'] && x <= besogo.coordArea['highestX'] && y >= besogo.coordArea['lowestY'] && y <= besogo.coordArea['highestY'])
            found = true;
        }
        spin++;
      }
    }

    var group = besogo.svgEl("g");
    var circleSize = 15;
    var virtualCircleSize = 15;
    if (editor.getReviewMode())
    {
      var current = editor.getCurrent();
      for (let i = 0; i < current.children.length; ++i)
      {
        var child = current.children[i];
        var element = besogo.svgFilledCircle(svgPos(child.move.x), svgPos(child.move.y), child.getCorrectColor(), circleSize);
        group.appendChild(element);
      }
      if (current.virtualChildren)
        for (let i = 0; i < current.virtualChildren.length; ++i)
        {
          var redirect = current.virtualChildren[i];
          var element = besogo.svgFilledCircle(svgPos(redirect.move.x), svgPos(redirect.move.y), redirect.target.getCorrectColor(), virtualCircleSize);
          group.appendChild(element);
        }
    }

    if (x!=-1)
    {
      x+=1;
      y+=1;
      var element = besogo.svgFilledCircle(svgPos(x), svgPos(y), "blue", circleSize);
    }
    else
      var element = besogo.svgFilledCircle(1, 1, "red", 0);
    group.appendChild(element);
    svg.replaceChild(group, nextMoveGroup); // Replace the markup group
    nextMoveGroup = group;
  }

  function redrawNextMoves(current, clear = false)
  {
    var group = besogo.svgEl("g");
    if (!clear)
    {
      var circleSize = 15;
      var virtualCircleSize = 8;
      for (let i = 0; i < current.children.length; ++i)
      {
        var child = current.children[i];
        var element = besogo.svgFilledCircle(svgPos(child.move.x), svgPos(child.move.y), child.getCorrectColor(), circleSize);
        group.appendChild(element);
      }
      if (current.virtualChildren)
        for (let i = 0; i < current.virtualChildren.length; ++i)
        {
          var redirect = current.virtualChildren[i];
          var element = besogo.svgFilledCircle(svgPos(redirect.move.x), svgPos(redirect.move.y), redirect.target.getCorrectColor(), virtualCircleSize);
          group.appendChild(element);
        }
      redrawNextMoveStatuses(group, current);
      redrawNextMovesDiffInfo(group, current);
    }
    svg.replaceChild(group, nextMoveGroup); // Replace the markup group
    nextMoveGroup = group;
  }

  function makeBacker(x, y) { // Makes a label markup backer at (x, y)
      return besogo.svgEl("rect", {
          x: x - CELL_SIZE/2,
          y: y - CELL_SIZE/2,
          height: CELL_SIZE,
          width: CELL_SIZE,
          opacity: 0.85,
          stroke: "none",
          'class': 'besogo-svg-board besogo-svg-backer'
      });
  }

  // Checks if (x, y) is in variants
  function checkVariants(variants, current, x, y)
  {
    var i, move;
    for (i = 0; i < variants.length; i++)
      if (variants[i] !== current)  // Skip current (within siblings)
      {
        move = variants[i].move;
        if (move && move.x === x && move.y === y)
          return true;
      }
    return false;
  }

  function getHoverElement(current, x, y, stone)
  {
    var color = (stone === -1) ? "white" : "black"; // White on black
    switch(editor.getTool())
    {
      case 'auto': return !stone ? besogo.svgStone(x, y, current.nextMove()) : null;
      case 'addB':
        if (stone)
          return besogo.svgCross(x, y, besogo.RED);
        var element = besogo.svgEl('g');
        element.appendChild(besogo.svgStone(x, y, editor.isShift() ? 1 : -1));
        element.appendChild(besogo.svgPlus(x, y, besogo.RED));
        return element;
      case 'clrMark': break; // Nothing
      case 'circle': return besogo.svgCircle(x, y, color);
      case 'square': return besogo.svgSquare(x, y, color);;
      case 'triangle': return besogo.svgTriangle(x, y, color);
      case 'cross': return besogo.svgCross(x, y, color);
      case 'block': return besogo.svgBlock(x, y, color);
      case 'label': return besogo.svgLabel(x, y, color, editor.getLabel());
    }
    return null;
  }

  // Redraws the hover layer
  function redrawHover(current)
  {
    if (TOUCH_FLAG)
      return; // Do nothing for touch interfaces

    var group = besogo.svgEl("g"); // Group holding hover layer elements

    hoverLayer = []; // Clear the references to the old layer

    if (editor.isPerformingAutoPlay())
    {
      hoverLayer = []; // Clear the references to the old layer
      updateHoverState();
      return;
    }

    group.setAttribute('opacity', '0.35');

    if (editor.getTool() === 'navOnly')
    { // Render navOnly hover by iterating over children
      var children = current.children;
      for (let i = 0; i < children.length; i++)
      {
        var stone = children[i].move;
        if (stone && stone.x !== 0) // Child node is move and not a pass
        {
          var x = svgPos(stone.x);
          var y = svgPos(stone.y);
          var element = besogo.svgStone(x, y, stone.color);
          element.setAttribute('visibility', 'hidden');
          group.appendChild(element);
          hoverLayer[ fromXY(stone.x, stone.y) ] = element;
        }
      }
    }
    else // Render hover for other tools by iterating over grid
      for (let i = 1; i <= sizeX; i++)
        for (let j = 1; j <= sizeY; j++)
        {
          var x = svgPos(i);
          var y = svgPos(j);
          var stone = current.getStone(i, j);
          if (element = getHoverElement(current, x, y, stone))
          {
            element.setAttribute('visibility', 'hidden');
            group.appendChild(element);
            hoverLayer[fromXY(i, j)] = element;
          }
        }

    svg.replaceChild(group, hoverGroup); // Replace the hover layer group
    hoverGroup = group;
    updateHoverState();
  }

  // Converts (x, y) coordinates to SVG position
  function svgPos(x)
  {
    return BOARD_MARGIN + CELL_SIZE/2 + (x-1) * CELL_SIZE;
  }

  // Converts (x, y) coordinates to linear index
  function fromXY(x, y)
  {
    return (x - 1)*sizeY + (y - 1);
  }


};
