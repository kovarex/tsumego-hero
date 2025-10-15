(function() {
'use strict';
var besogo = window.besogo = window.besogo || {}; // Establish our namespace
besogo.VERSION = '0.0.2-alpha';

besogo.create = function(container, options) {
	
	 
    var editor, // Core editor object
        boardDisplay,
        resizer, // Auto-resizing function
        boardDiv, // Board display container
        panelsDiv, // Parent container of panel divs
        makers = // Map to panel creators
        {
          control: besogo.makeControlPanel,
          comment: besogo.makeCommentPanel,
          tool: besogo.makeToolPanel,
          tool2: besogo.makeToolPanel,
          tree: besogo.makeTreePanel,
          file: besogo.makeFilePanel
        },
        insideText = container.textContent || container.innerText || '',
        i, panelName; // Scratch iteration variables

	//container.setAttribute('lang', 'xxx');
    container.className += ' besogo-container'; // Marks this div as initialized
    // Process options and set defaults
    options = options || {}; // Makes option checking simpler
    options.size = besogo.parseSize(options.size || 19);
    options.coord = options.coord || 'none';
    options.tool = options.tool || 'auto';
    options.tool2 = options.tool2 || 'auto';
    if (options.panels === '')
      options.panels = [];
  
    options.panels = options.panels || 'tree+control';
	//options.panels = options.panels || 'control+names+comment+tool+tree+file';
    if (typeof options.panels === 'string')
      options.panels = options.panels.split('+');
  
	options.panels2 = options.panels2 || 'tool2';
    if (typeof options.panels2 === 'string')
      options.panels2 = options.panels2.split('+');
  
    options.path = options.path || '';
    if (options.shadows === undefined)
      options.shadows = 'auto';
    else if (options.shadows === 'off')
      options.shadows = false;

    // Make the core editor object
    editor = besogo.makeEditor(options.size.x, options.size.y);
    container.besogoEditor = editor;
    editor.setTool(options.tool);
    editor.setTool(options.tool2);
    editor.setCoordStyle(options.coord);
    if (options.realstones) // Using realistic stones
    {
      editor.REAL_STONES = true;
      editor.SHADOWS = options.shadows;
    }
    else // SVG stones
      editor.SHADOWS = (options.shadows && options.shadows !== 'auto');


    if (options.sgf) // Load SGF file from URL or SGF string
    {
      let validURL = false;
      try
      {
        new URL(options.sgf);
        validURL = true;
      } catch (e) {}
      try
      {
        if (validURL)
          fetchParseLoad(options.sgf, editor, options.path);
        else
        {
          parseAndLoad(options.sgf, editor);
          navigatePath(editor, options.path);
        }
      }
      catch(e)
      {
          console.error(e);
          // Silently fail on network error
      }
    }
    else if (insideText.match(/\s*\(\s*;/)) // Text content looks like an SGF file
    {
      parseAndLoad(insideText, editor);
      navigatePath(editor, options.path); // Navigate editor along path
    }

    if (typeof options.variants === 'number' || typeof options.variants === 'string')
      editor.setVariantStyle(+options.variants); // Converts to number

    while (container.firstChild) // Remove all children of container
      container.removeChild(container.firstChild);

    boardDiv = makeDiv('besogo-board'); // Create div for board display
    boardDisplay = besogo.makeBoardDisplay(boardDiv, editor); // Create board display

    if (!options.nokeys) // Add keypress handler unless nokeys option is truthy
      addKeypressHandler(container, editor, boardDisplay);

    if (!options.nowheel)
    // Add mousewheel handler unless nowheel option is truthy
      addWheelHandler(boardDiv, editor);

    if (options.panels.length > 0) // Only create if there are panels to add
    {
      panelsDiv = makeDiv('besogo-panels');
      for (i = 0; i < options.panels.length; i++)
      {
        panelName = options.panels[i];
        if (makers[panelName]) // Only add if creator function exists
          makers[panelName](makeDiv('besogo-' + panelName, panelsDiv), editor);
      }
      if (!panelsDiv.firstChild) // If no panels were added
      {
        container.removeChild(panelsDiv); // Remove the panels div
        panelsDiv = false; // Flags panels div as removed
      }
    }
	if (options.panels2.length > 0) // Only create if there are panels to add
    {
      panelsDiv = makeDiv('besogo-bottom-panels');
      for (i = 0; i < options.panels2.length; i++)
      {
        panelName = options.panels2[i];
        if (makers[panelName]) // Only add if creator function exists
          makers[panelName](makeDiv('besogo-' + panelName, panelsDiv), editor);
      }
      if (!panelsDiv.firstChild) // If no panels were added
      {
        container.removeChild(panelsDiv); // Remove the panels div
        panelsDiv = false; // Flags panels div as removed
      }
    }
	

    options.resize = options.resize || 'auto';
    if (options.resize === 'auto') { // Add auto-resizing unless resize option is truthy
        resizer = function() {
            var windowHeight = window.innerHeight, // Viewport height
                // Calculated width of parent element
                parentWidth = parseFloat(getComputedStyle(container.parentElement).width),
                maxWidth = +(options.maxwidth || -1),
                orientation = options.orient || 'auto',

                portraitRatio = +(options.portratio || 200) / 100,
                landscapeRatio = +(options.landratio || 200) / 100,
                minPanelsWidth = +(options.minpanelswidth || 350),
                minPanelsHeight = +(options.minpanelsheight || 400),
                minLandscapeWidth = +(options.transwidth || 600),

                // Initial width parent
                width = (maxWidth > 0 && maxWidth < parentWidth) ? maxWidth : parentWidth,
                height; // Initial height is undefined

            // Determine orientation if 'auto' or 'view'
            if (orientation !== 'portrait' && orientation !== 'landscape') {
                if (width < minLandscapeWidth || (orientation === 'view' && width < windowHeight)) {
                    orientation = 'portrait';
                } else {
                    orientation = 'landscape';
                }
            }

            if (orientation === 'portrait') { // Portrait mode
                if (!isNaN(portraitRatio)) {
                    height = portraitRatio * width;
                    if (panelsDiv) {
                        height = (height - width < minPanelsHeight) ? width + minPanelsHeight : height;
                    }
                } // Otherwise, leave height undefined
            } else if (orientation === 'landscape') { // Landscape mode
                if (!panelsDiv) { // No panels div
                    height = width; // Square overall
                } else if (isNaN(landscapeRatio)) {
                    height = windowHeight;
                } else { // Otherwise use ratio
                    height = width / landscapeRatio;
                }

                if (panelsDiv) {
                    // Reduce height to ensure minimum width of panels div
                    height = (width < height + minPanelsWidth) ? (width - minPanelsWidth) : height;
                }
            }

            setDimensions(width, height);
            container.style.width = width + 'px';
        };
        window.addEventListener("resize", resizer);
        resizer(); // Initial div sizing
    } else if (options.resize === 'fixed') {
        setDimensions(container.clientWidth, container.clientHeight);
    } else if (options.resize === 'fill') {
        resizer = function() {
            var // height = window.innerHeight, // Viewport height
                height = parseFloat(getComputedStyle(container.parentElement).height),
                // Calculated width of parent element
                width = parseFloat(getComputedStyle(container.parentElement).width),

                minPanelsWidth = +(options.minpanelswidth || 350),
                minPanelsHeight = +(options.minpanelsheight || 300),

                // Calculated dimensions for the panels div
                panelsWidth = 0, // Will be set if needed
                panelsHeight = 0;

            if (width >= height) { // Landscape mode
                container.style['flex-direction'] = 'row';
                if (panelsDiv) {
                    panelsWidth = (width - height >= minPanelsWidth) ? (width - height) : minPanelsWidth;
                }
                panelsDiv.style.height = height + 'px';
                panelsDiv.style.width = panelsWidth + 'px';
                boardDiv.style.height = height + 'px';
                boardDiv.style.width = (width - panelsWidth) + 'px';
            } else { // Portrait mode
                container.style['flex-direction'] = 'column';
                if (panelsDiv) {
                    panelsHeight = (height - width >= minPanelsHeight) ? (height - width) : minPanelsHeight;
                }
                panelsDiv.style.height = panelsHeight + 'px';
                panelsDiv.style.width = width + 'px';
                boardDiv.style.height = (height - panelsHeight) + 'px';
                boardDiv.style.width = width + 'px';
            }
        };
        window.addEventListener("resize", resizer);
        resizer(); // Initial div sizing
    }

    // Sets dimensions with optional height param
    function setDimensions(width, height) {
        if (height && width > height) { // Landscape mode
            container.style['flex-direction'] = 'row';
            boardDiv.style.height = height + 'px';
            boardDiv.style.width = height + 'px';
            if (panelsDiv) {
                panelsDiv.style.height = height + 'px';
                panelsDiv.style.width = (width - height) + 'px';
            }
        } else { // Portrait mode (implied if height is missing)
            container.style['flex-direction'] = 'column';
            boardDiv.style.height = width + 'px';
            boardDiv.style.width = width + 'px';
            if (panelsDiv) {
                if (height) { // Only set height if param present
                    panelsDiv.style.height = (height - width) + 'px';
                }
                panelsDiv.style.width = width + 'px';
            }
        }
    }

    // Creates and adds divs to specified parent or container
    function makeDiv(className, parent) {
        var div = document.createElement("div");
        if (className) {
            div.className = className;
        }
        parent = parent || container;
        parent.appendChild(div);
        return div;
    }
}; // END function besogo.create

// Parses size parameter from SGF format
besogo.parseSize = function(input) {
    var matches,
        sizeX,
        sizeY;

    input = (input + '').replace(/\s/g, ''); // Convert to string and remove whitespace

    matches = input.match(/^(\d+):(\d+)$/); // Check for #:# pattern
    if (matches) { // Composed value pattern found
        sizeX = +matches[1]; // Convert to numbers
        sizeY = +matches[2];
    } else if (input.match(/^\d+$/)) { // Check for # pattern
        sizeX = +input; // Convert to numbers
        sizeY = +input; // Implied square
    } else { // Invalid input format
        sizeX = sizeY = 19; // Default size value
    }
    if (sizeX > 52 || sizeX < 1 || sizeY > 52 || sizeY < 1) {
        sizeX = sizeY = 19; // Out of range, set to default
    }

    return { x: sizeX, y: sizeY };
};

// Automatically converts document elements into besogo instances
besogo.autoInit = function()
{
  var allDivs = document.getElementsByTagName('div'), // Live collection of divs
      targetDivs = [], // List of divs to auto-initialize
      options, // Structure to hold options
      attrs; // Scratch iteration variables

  for (let i = 0; i < allDivs.length; i++) // Iterate over all divs
    if ((hasClass(allDivs[i], 'besogo-editor') || // Has an auto-init class
         hasClass(allDivs[i], 'besogo-viewer') ||
         hasClass(allDivs[i], 'besogo-diagram')) &&
        !hasClass(allDivs[i], 'besogo-container')) // Not already initialized
      targetDivs.push(allDivs[i]);

  for (let i = 0; i < targetDivs.length; i++) // Iterate over target divs
  {
    options = {}; // Clear the options struct
    if (hasClass(targetDivs[i], 'besogo-editor'))
    {
      options.panels = ['control', 'comment', 'tool', 'tree', 'file'];
      options.tool = 'auto';
      options.tool2 = 'auto';
    }
    else if (hasClass(targetDivs[i], 'besogo-viewer'))
    {
      options.panels = ['control', 'comment'];
      options.tool = 'navOnly';
      options.tool2 = 'navOnly';
    } else if (hasClass(targetDivs[i], 'besogo-diagram'))
    {
      options.panels = [];
      options.tool = 'navOnly';
      options.tool2 = 'navOnly';
    }

    attrs = targetDivs[i].attributes;
    for (let j = 0; j < attrs.length; j++) // Load attributes as options
      options[attrs[j].name] = attrs[j].value;
    besogo.create(targetDivs[i], options);
  }

  function hasClass(element, str) { return (element.className.split(' ').indexOf(str) !== -1); }
};

// Sets up keypress handling
function addKeypressHandler(container, editor, boardDisplay)
{
  if (!container.getAttribute('tabindex'))
    container.setAttribute('tabindex', '0'); // Set tabindex to allow div focusing

  container.addEventListener('keydown', function(evt)
  {
    evt = evt || window.event;
    switch (evt.keyCode)
    {
      case 33: editor.prevNode(10); break; // page up
      case 34: editor.nextNode(10); break; // page down
      case 35: editor.nextNode(-1); break; // end
      case 36: editor.prevNode(-1); break; // home
      case 37: // left
        if (evt.shiftKey)
          editor.prevBranchPoint();
        else
          editor.prevNode(1);
        break;
      case 38: editor.nextSibling(-1); break; // up
      case 39: editor.nextNode(1); break; // right
      case 40: editor.nextSibling(1); break; // down
      case 46: editor.cutCurrent(); break; // delete
      case 16:
        if (!editor.isShift())
        {
          editor.setShift(true); // shift
          boardDisplay.redrawHover(editor.getCurrent());
        }
        break;
    }
    if (evt.keyCode >= 33 && evt.keyCode <= 40)
      evt.preventDefault(); // Suppress page nav controls
  });

  container.addEventListener('keyup', function(evt)
  {
    evt = evt || window.event;
    switch (evt.keyCode)
    {
      case 16:
        editor.setShift(false);
        boardDisplay.redrawHover(editor.getCurrent());
        break; // shift
    }
  });
}

// Sets up mousewheel handling
function addWheelHandler(boardDiv, editor)
{
  boardDiv.addEventListener('wheel', function(evt)
  {
    evt = evt || window.event;
    if (evt.deltaY > 0)
    {
      editor.nextNode(1);
      evt.preventDefault();
    }
    else if (evt.deltaY < 0)
    {
      editor.prevNode(1);
      evt.preventDefault();
    }
  });
}

// Parses SGF string and loads into editor
function parseAndLoad(text, editor)
{
  var sgf;
  try
  {
    sgf = besogo.parseSgf(text);
  }
  catch (error)
  {
    return; // Silently fail on parse error
  }
  besogo.loadSgf(sgf, editor);
}

// Fetches text file at url from same domain
function fetchParseLoad(url, editor, path)
{
  var http = new XMLHttpRequest();

  http.onreadystatechange = function()
  {
    if (http.readyState === 4 && http.status === 200) // Successful fetch
    {
      parseAndLoad(http.responseText, editor);
      navigatePath(editor, path);
    }
  };
  http.overrideMimeType('text/plain'); // Prevents XML parsing and warnings
  http.open("GET", url, true); // Asynchronous load
  http.send();
}

function navigatePath(editor, path)
{
  var subPaths;
  path = path.split(/[Nn]+/); // Split into parts that start in next mode
  for (let i = 0; i < path.length; i++)
  {
    subPaths = path[i].split(/[Bb]+/); // Split on switches into branch mode
    executeMoves(subPaths[0], false); // Next mode moves
    for (let j = 1; j < subPaths.length; j++) // Intentionally starting at 1
      executeMoves(subPaths[j], true); // Branch mode moves
  }

  function executeMoves(part, branch)
  {
    part = part.split(/\D+/); // Split on non-digits
    for (let i = 0; i < part.length; i++)
      if (part[i]) // Skip empty strings
        if (branch)
        { // Branch mode
          if (editor.getCurrent().children.length)
          {
            editor.nextNode(1);
            editor.nextSibling(part[i] - 1);
          }
        }
        else
          editor.nextNode(+part[i]); // Converts to number
  }
}

})(); // END closure
besogo.makeBoardDisplay = function(container, editor)
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

  initializeBoard(editor.getCoordStyle()); // Initialize SVG element and draw the board
  container.appendChild(svg); // Add the SVG element to the document
  editor.addListener(update); // Register listener to handle editor/game state updates
  redrawAll(editor.getCurrent()); // Draw stones, markup and hover layer

  // Set listener to detect touch interfaces
  container.addEventListener('touchstart', setTouchFlag);

  return {
      redrawHover: redrawHover,
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

    if (!TOUCH_FLAG) {
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

  function redrawAll(current) {
      redrawStones(current);
      redrawMarkup(current);
      if(reviewEnabled2) redrawNextMoves(current);
      redrawHover(current);
  }

  // Initializes the SVG element and draws the board
  function drawBoard(coord)
  {
    var boardWidth,
        boardHeight,
        string = ""; // Path string for inner board lines

    BOARD_MARGIN = (coord === 'none' ? 0 : COORD_MARGIN) + EXTRA_MARGIN;
    boardWidth = 2*BOARD_MARGIN + sizeX*CELL_SIZE;
    boardHeight = 2*BOARD_MARGIN + sizeY*CELL_SIZE;

    svg = besogo.svgEl("svg", { // Initialize the SVG element
        width: "100%",
        height: "100%",
        viewBox: "0 0 " + boardWidth + " " + boardHeight
    });

    svg.appendChild(besogo.svgEl("rect", { // Fill background color
        width: boardWidth,
        height: boardHeight,
        'class': 'besogo-svg-board'
    }) );

    svg.appendChild(besogo.svgEl("rect", { // Draw outer square of board
        width: CELL_SIZE*(sizeX - 1),
        height: CELL_SIZE*(sizeY - 1),
        x: svgPos(1),
        y: svgPos(1),
        'class': 'besogo-svg-lines'
    }) );

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
      drawCoords(coord); // Draw the coordinate labels
  }

  // Draws coordinate labels on the board
  function drawCoords(coord)
  {
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
        drawCoordLabel(x, svgPos(1) - shift, labelXa[i]);
        drawCoordLabel(x, svgPos(sizeY) + shift, labelXb[i]);
      }

      for (let i = 1; i <= sizeY; i++) // Draw row coordinate labels
      {
        y = svgPos(i);
        drawCoordLabel(svgPos(1) - shift, y, labelYa[i]);
        drawCoordLabel(svgPos(sizeX) + shift, y, labelYb[i]);
      }

      function drawCoordLabel(x, y, label)
      {
        var element = besogo.svgEl("text", {
            x: x,
            y: y,
            dy: ".65ex", // Seems to work for vertically centering these fonts
            "font-size": 32,
            "text-anchor": "middle", // Horizontal centering
            "font-family": "Helvetica, Arial, sans-serif",
            fill: 'black'
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

    if (sizeX % 2 && sizeY % 2) { // Draw center hoshi if both dimensions are odd
        cx = (sizeX - 1)/2 + 1; // Calculate the center of the board
        cy = (sizeY - 1)/2 + 1;
        drawStar(cx, cy);

        if (sizeX >= 17 && sizeY >= 17) { // Draw side hoshi if at least 17x17 and odd
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

        if (!TOUCH_FLAG) { // Skip hover listeners for touch interfaces
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
      // Call click handler in editor
      editor.click(i, j, event.ctrlKey, event.shiftKey);
      if(!TOUCH_FLAG)
        (handleOver(i, j))(); // Ensures that any updated tool is visible
		if(!reviewEnabled2){
			setTimeout(function(){
				if(isMutable) editor.nextNode(1);
			}, 360);
		}
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
    if (lastHoverPosition == null)
      return;
    if (element = hoverLayer[fromXY(lastHoverPosition.x, lastHoverPosition.y)]) // Make tool action visible on hover over
      element.setAttribute('visibility', 'visible');
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
            group.appendChild(besogo.realStone(x, y, color, randIndex[fromXY(i, j)]));
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
    {
      for (let j = 1; j <= sizeY; j++)
      {
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
          group.appendChild(element);
          markupLayer[fromXY(i, j)] = element;
        }
      }
    }

    // Mark last move with plus if not already marked
    if (lastMove && lastMove.x !== 0 && lastMove.y !== 0 &&
        !markupLayer[fromXY(lastMove.x, lastMove.y)]) // Last move not marked
    {
      var color = checkVariants(variants, current, lastMove.x, lastMove.y) ? besogo.PURP : besogo.BLUE;
      var moveToUse = lastMove;
      if (current.cameFrom)
        moveToUse = current.cameFrom.getMoveToGetToVirtualChild(current);
      var element = besogo.svgCircle(svgPos(moveToUse.x),
                                     svgPos(moveToUse.y),
                                     current.nextIsBlack() ? "black" : "white",
                                     20, 4);
      group.appendChild(element);
      markupLayer[fromXY(moveToUse.x, moveToUse.y)] = element;
    }

    svg.replaceChild(group, markupGroup); // Replace the markup group
    markupGroup = group;
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

  function redrawNextMoves(current)
  {
    var group = besogo.svgEl("g");
    for (let i = 0; i < current.children.length; ++i)
    {
      var child = current.children[i];
      var element = besogo.svgFilledCircle(svgPos(child.move.x), svgPos(child.move.y), child.getCorrectColor(), 15);
      group.appendChild(element);
    }
    if (current.virtualChildren)
      for (let i = 0; i < current.virtualChildren.length; ++i)
      {
        var redirect = current.virtualChildren[i];
        var element = besogo.svgFilledCircle(svgPos(redirect.move.x), svgPos(redirect.move.y), redirect.target.getCorrectColor(), 8);
        group.appendChild(element);
      }
    redrawNextMoveStatuses(group, current);
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
  function checkVariants(variants, current, x, y) {
      var i, move;
      for (i = 0; i < variants.length; i++) {
          if (variants[i] !== current) { // Skip current (within siblings)
              move = variants[i].move;
              if (move && move.x === x && move.y === y) {
                  return true;
              }
          }
      }
      return false;
  }

  function getHoverElement(current, x, y, stone)
  {
    var color = (stone === -1) ? "white" : "black"; // White on black
    switch(editor.getTool())
    {
      case 'auto': return besogo.svgStone(x, y, current.nextMove());
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

  function svgPos(x) {  // Converts (x, y) coordinates to SVG position
      return BOARD_MARGIN + CELL_SIZE/2 + (x-1) * CELL_SIZE;
  }

  function fromXY(x, y) { // Converts (x, y) coordinates to linear index
      return (x - 1)*sizeY + (y - 1);
  }
};
besogo.makeCommentPanel = function(container, editor)
{
  'use strict';
  var infoTexts = {}, // Holds text nodes for game info properties
      statusLabel = null,
      statusTable = null,
      gameInfoTable = document.createElement('table'),
      gameInfoEdit = document.createElement('table'),
      commentBox = document.createElement('div'),
      commentEdit = document.createElement('textarea'),
      statusBasedCheckbox = null,
      correctButton = makeCorrectVariantButton(),
      playerInfoOrder = 'PW WR WT PB BR BT'.split(' '),
      infoOrder = 'HA KM RU TM OT GN EV PC RO DT RE ON GC AN US SO CP'.split(' '),
      noneSelection = null,
      deadSelection = null,
      koSelection = null,
      koExtraThreats = null,
      koApproaches = null,
      sekiSelection = null,
      sekiSente = null,
      aliveSelection = null,
      jumpToBranchWithoutStatusButton = createJumpToBranchWithoutStatusButton(),
      goalKillSelection = null,
      goalLiveSelection = null,
      infoIds =
      {
          PW: 'White Player',
          WR: 'White Rank',
          WT: 'White Team',
          PB: 'Black Player',
          BR: 'Black Rank',
          BT: 'Black Team',

          HA: 'Handicap',
          KM: 'Komi',
          RU: 'Rules',
          TM: 'Timing',
          OT: 'Overtime',

          GN: 'Game Name',
          EV: 'Event',
          PC: 'Place',
          RO: 'Round',
          DT: 'Date',

          RE: 'Result',
          ON: 'Opening',
          GC: 'Comments',

          AN: 'Annotator',
          US: 'Recorder',
          SO: 'Source',
          CP: 'Copyright'
      };

  statusLabel = createStatusLabel();
  statusTable = createStatusTable();
  let parentDiv = document.createElement('div');
  container.appendChild(parentDiv);

  let correctButtonSpan = document.createElement('span');
  statusBasedCheckbox = createCheckBox(correctButtonSpan, 'Correct controlled by status', function(event)
  {
    editor.getCurrent().getRoot().setGoal(event.target.checked ? GOAL_KILL : GOAL_NONE);
    updateGoal();
    updateCorrectButton();
    updateStatusEditability();
    besogo.updateCorrectValues(editor.getCurrent().getRoot());
    editor.notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
  });
  statusBasedCheckbox.type = 'checkbox';
  correctButtonSpan.appendChild(correctButton);
  parentDiv.appendChild(correctButtonSpan);
  parentDiv.appendChild(createGoalTable());
  parentDiv.appendChild(statusLabel);
  parentDiv.appendChild(statusTable);
  parentDiv.appendChild(jumpToBranchWithoutStatusButton);
  container.appendChild(makeCommentButton());
  //container.appendChild(gameInfoTable);
  //container.appendChild(gameInfoEdit);
  infoTexts.C = document.createTextNode('');
  container.appendChild(commentBox);
  commentBox.appendChild(infoTexts.C);
  container.appendChild(commentEdit);

  commentEdit.onblur = function() { editor.setComment(commentEdit.value); };
  commentEdit.addEventListener('keydown', function(evt) {
    evt = evt || window.event;
    evt.stopPropagation(); // Stop keydown propagation when in focus
  });

  editor.addListener(update);
  update({ navChange: true});
  gameInfoEdit.style.display = 'none'; // Hide game info editting table initially

  function preventFocus(event)
  {
    if (event.relatedTarget) // Revert focus back to previous blurring element
      event.relatedTarget.focus();
    else
      this.blur(); // No previous focus target, blur instead
  }

  function createInputWithLabel(type, target, name, group, onClick)
  {
    let selection = document.createElement('input');
    selection.type = type;
    selection.id = name;
    if (group)
      selection.name = group;
    selection.onclick = onClick
    target.appendChild(selection);

    let label = document.createElement('label');
    label.textContent = name;
    label.htmlFor = name;
    target.appendChild(label);

    return selection;
  }

  function createRadioButton(target, name, group, onClick)
  {
    return createInputWithLabel('radio', target, name, group, onClick);
  }

  function createCheckBox(target, name, onClick)
  {
    return createInputWithLabel('checkbox', target, name, null, onClick);
  }

  function setEnabledCarefuly(element, enabled)
  {
    if (!enabled)
      if (document.activeElement == element)
        document.getElementById("target").focus();
    element.disabled = !enabled;
  }

  function createRadioButtonRow(table, name, statusType, otherInput = null)
  {
    let row = table.insertRow(-1);
    let cell = row.insertCell(0);
    let result = createRadioButton(cell,
                                   name,
                                   'status',
                                   function()
                                   {
                                      editor.getCurrent().setStatusSource(besogo.makeStatusSimple(statusType));
                                      updateStatusEditability();
                                      updateStatusLabel();
                                      editor.notifyListeners({ treeChange: true});
                                   });
    let cell2 = row.insertCell(-1);
    if (otherInput)
      cell2.appendChild(otherInput);
    return result;
  }

  function createStatusLabel()
  {
    let label = document.createElement('label');
    label.style.fontSize = 'x-large';
    return label;
  }

  function createStatusTable()
  {
    let table = document.createElement('table');

    noneSelection = createRadioButtonRow(table, 'none', STATUS_NONE);
    deadSelection = createRadioButtonRow(table, 'dead', STATUS_DEAD);

    let koSettingsSpan = document.createElement('span');

    let koApproachesLabel = document.createElement('label');
    koApproachesLabel.textContent = 'Approaches: ';
    koSettingsSpan.appendChild(koApproachesLabel);

    koApproaches = document.createElement('input');
    koApproaches.type = 'text';
    koApproaches.oninput = function(event)
    {
      if (!editor.getCurrent().statusSource)
        return;
      if (editor.getCurrent().statusSource.blackFirst.type != STATUS_KO)
        return;
      let newStatus = besogo.makeStatusSimple(STATUS_KO);
      newStatus.setApproachKo(Number(event.target.value), editor.getCurrent().statusSource.blackFirst.extraThreats);
      editor.getCurrent().setStatusSource(newStatus);
      updateStatusLabel();
    }
    koSettingsSpan.appendChild(koApproaches);

    let koExtraThreatsLabel = document.createElement('label');
    koExtraThreatsLabel.textContent = 'Threats: ';
    koSettingsSpan.appendChild(koExtraThreatsLabel);

    koExtraThreats = document.createElement('input');
    koExtraThreats.type = 'text';
    koExtraThreats.oninput = function(event)
    {
      if (!editor.getCurrent().statusSource)
        return;
      if (editor.getCurrent().statusSource.blackFirst.type != STATUS_KO)
        return;
      let newStatus = besogo.loadStatusFromString('KO' + event.target.value);
      newStatus.setApproachKo(Number(koApproaches.value), newStatus.blackFirst.extraThreats);
      editor.getCurrent().setStatusSource(newStatus);
      updateStatusLabel();
    }
    koSettingsSpan.appendChild(koExtraThreats);

    koSelection = createRadioButtonRow(table, 'ko', STATUS_KO, koSettingsSpan);

    let sekiSenteSpan = document.createElement('span');
    sekiSente = createCheckBox(sekiSenteSpan, 'sente', function(event)
    {
      editor.getCurrent().setStatusSource(besogo.loadStatusFromString('SEKI' + (event.target.checked ? '+' : '')));
      updateStatusLabel();
    });

    sekiSelection = createRadioButtonRow(table, 'seki', STATUS_SEKI, sekiSenteSpan);
    aliveSelection = createRadioButtonRow(table, 'alive', STATUS_ALIVE);

    return table;
  }

  function updateStatusEditability()
  {
    let editable = !editor.getCurrent().hasChildIncludingVirtual() && editor.getRoot().goal != GOAL_NONE;
    setEnabledCarefuly(noneSelection, editable);
    setEnabledCarefuly(deadSelection, editable);
    setEnabledCarefuly(koSelection, editable);
    setEnabledCarefuly(koExtraThreats,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(koApproaches,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(sekiSelection, editable);
    setEnabledCarefuly(sekiSente,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_SEKI);
    setEnabledCarefuly(aliveSelection, editable);
  }

  function getStatusText()
  {
    return 'Status: ' + editor.getCurrent().status.strLong();
  }

  function updateStatusLabel()
  {
    statusLabel.textContent = getStatusText();
  }

  function updateStatus()
  {
    updateStatusLabel();
    updateStatusEditability();
    if (!editor.getCurrent().status ||
        editor.getCurrent().status.blackFirst.type == STATUS_NONE)
    {
      noneSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_DEAD)
    {
      deadSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_KO)
    {
      koSelection.checked = true;
      koExtraThreats.value = editor.getCurrent().status.blackFirst.getKoStr();
      koApproaches.value = editor.getCurrent().status.blackFirst.getApproachCount();
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_SEKI)
    {
      sekiSelection.checked = true;
      sekiSente.checked = editor.getCurrent().status.blackFirst.sente;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_ALIVE)
    {
      aliveSelection.checked = true;
      return;
    }
  }

  function updateGoal()
  {
    let goal = editor.getRoot().goal;
    goalKillSelection.checked = (goal == GOAL_KILL);
    setEnabledCarefuly(goalKillSelection, goal != GOAL_NONE);
    goalLiveSelection.checked = (goal == GOAL_LIVE);
    setEnabledCarefuly(goalLiveSelection, goal != GOAL_NONE);
  }

  function update(msg)
  {
    updateStatus();
    updateGoal();

    var temp; // Scratch for strings

    if (msg.navChange)
    {
      temp = editor.getCurrent().comment || '';
      updateText(commentBox, temp, 'C');
      if (editor.getCurrent() === editor.getRoot() &&
          gameInfoTable.firstChild &&
          gameInfoEdit.style.display === 'none')
        gameInfoTable.style.display = 'table';
      else
        gameInfoTable.style.display = 'none';
      commentEdit.style.display = 'none';
      commentBox.style.display = 'block';
    }
    else if (msg.comment !== undefined)
    {
      updateText(commentBox, msg.comment, 'C');
      commentEdit.value = msg.comment;
    }

    updateCorrectButton();
    updateJumpToBranchWithoutStatusButton();
    statusBasedCheckbox.checked = (editor.getCurrent().getRoot().goal != GOAL_NONE);
  }

  function updateGameInfoTable(gameInfo)
  {
    var table = document.createElement('table');

    table.className = 'besogo-gameInfo';
    for (let i = 0; i < infoOrder.length ; i++) // Iterate in specified order
    {
      var id = infoOrder[i];

      if (gameInfo[id]) // Only add row if property exists
      {
        var row = document.createElement('tr');
        table.appendChild(row);

        var cell = document.createElement('td');
        cell.appendChild(document.createTextNode(infoIds[id]));
        row.appendChild(cell);

        cell = document.createElement('td');
        var text = document.createTextNode(gameInfo[id]);
        cell.appendChild(text);
        row.appendChild(cell);
      }
    }

    if (!table.firstChild || gameInfoTable.style.display === 'none')
      table.style.display = 'none'; // Do not display empty table or if already hidden
    container.replaceChild(table, gameInfoTable);
    gameInfoTable = table;
  }

  function updateGameInfoEdit(gameInfo)
  {
    var table = document.createElement('table'),
        infoTableOrder = playerInfoOrder.concat(infoOrder),
        row, cell, text;

    table.className = 'besogo-gameInfo';
    for (let i = 0; i < infoTableOrder.length ; i++)
    {
      var id = infoTableOrder[i];
      row = document.createElement('tr');
      table.appendChild(row);

      cell = document.createElement('td');
      cell.appendChild(document.createTextNode(infoIds[id]));
      row.appendChild(cell);

      cell = document.createElement('td');
      text = document.createElement('input');
      if (gameInfo[id])
        text.value = gameInfo[id];
      text.onblur = function(t, id)
      {
        // Commit change on blur
        return function() { editor.setGameInfo(t.value, id); };
      }(text, id);
      text.addEventListener('keydown', function(evt)
      {
        evt = evt || window.event;
        evt.stopPropagation(); // Stop keydown propagation when in focus
      });
      cell.appendChild(text);
      row.appendChild(cell);
    }
    if (gameInfoEdit.style.display === 'none')
      table.style.display = 'none'; // Hide if already hidden
    container.replaceChild(table, gameInfoEdit);
    gameInfoEdit = table;
  }

  function updateText(parent, text, id)
  {
    var textNode = document.createTextNode(text);
    parent.replaceChild(textNode, infoTexts[id]);
    infoTexts[id] = textNode;
  }

  function makeInfoButton()
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Info';
    button.title = 'Show/hide game info';

    button.onclick = function()
    {
      if (gameInfoTable.style.display === 'none' && gameInfoTable.firstChild)
        gameInfoTable.style.display = 'table';
      else
        gameInfoTable.style.display = 'none';
      gameInfoEdit.style.display = 'none';
    };
    return button;
  }

  function makeInfoEditButton()
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Edit Info';
    button.title = 'Edit game info';

    button.onclick = function()
    {
      if (gameInfoEdit.style.display === 'none')
        gameInfoEdit.style.display = 'table';
      else
        gameInfoEdit.style.display = 'none';
      gameInfoTable.style.display = 'none';
    };
    return button;
  }

  function makeCommentButton()
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Comment';
    button.title = 'Edit comment';

    button.onclick = function()
    {
      if (commentEdit.style.display === 'none') // Comment edit box hidden
      {
        commentBox.style.display = 'none'; // Hide static comment display
        gameInfoTable.style.display = 'none'; // Hide game info table
        commentEdit.value = editor.getCurrent().comment;
        commentEdit.style.display = 'block'; // Show comment edit box
      }
      else // Comment edit box open
      {
        commentEdit.style.display = 'none'; // Hide comment edit box
        commentBox.style.display = 'block'; // Show static comment display
      }
    };
    return button;
  }

  function makeCorrectVariantButton()
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Incorrect';
    button.title = 'Change incorrect state';
    button.addEventListener('focus', preventFocus);

    button.onclick = function()
    {
      editor.getCurrent().setCorrectSource(!editor.getCurrent().correctSource, editor);
    };
    return button;
  }

  function createGoalRadioButton(parent, name, goal)
  {
    return createRadioButton(parent,
                             name,
                             'goal',
                             function()
                             {
                               editor.getCurrent().getRoot().setGoal(goal);
                               editor.notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
                             });
  }

  function createGoalTable()
  {
    let table = document.createElement('table');

    let row = table.insertRow(-1);
    table.appendChild(row);
    let cell = row.insertCell(-1);
    let label = document.createElement('label');
    label.textContent = 'Goal: ';
    label.style.fontSize = 'x-large';
    cell.appendChild(label);
    cell = row.insertCell(-1);

    goalKillSelection = createGoalRadioButton(cell, 'kill', GOAL_KILL);
    row = table.insertRow(-1);
    cell = row.insertCell(-1);
    cell = row.insertCell(-1);
    goalLiveSelection = createGoalRadioButton(cell, 'live', GOAL_LIVE);

    return table;
  }

  function updateCorrectButton()
  {
    let current = editor.getCurrent();
    correctButton.disabled = editor.getRoot().goal != GOAL_NONE ||
                             current.children.length ||
                             current.virtualChildren.length
    if (current.correct)
      correctButton.value = 'Make incorrect';
    else
      correctButton.value = 'Make correct';
  }

  function createJumpToBranchWithoutStatusButton()
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Jump to branch without status';
    button.title = 'bla bla';
    button.addEventListener('focus', preventFocus);

    button.onclick = function()
    {
      let leaf = editor.getRoot().getLeafWithoutStatus();
      if (leaf)
        editor.setCurrent(leaf);
    };
    return button;
  }

  function updateJumpToBranchWithoutStatusButton()
  {
    let current = editor.getCurrent();
    let count = editor.getRoot().getCountOfLeafsWithoutStatus();
    jumpToBranchWithoutStatusButton.disabled = editor.getRoot().goal == GOAL_NONE || count == 0;
    jumpToBranchWithoutStatusButton.value = 'Jump to branch without status (' + count + ')';
  }
};
besogo.makeControlPanel = function(container, editor)
{
  'use strict';
  var leftElements = [], // SVG elements for previous node buttons
      rightElements = [], // SVG elements for next node buttons
      siblingElements = [], // SVG elements for sibling buttons
      variantStyleButton, // Button for changing variant style
      childVariantElement; // SVG element for child style variants

  drawNavButtons();
  //drawStyleButtons();

  editor.addListener(update);
  update({ navChange: true, variantStyle: editor.getVariantStyle() }); // Initialize
  
  // Callback for variant style and nav state changes
  function update(msg)
  {
    var current;

    if (msg.navChange || msg.treeChange) // Update the navigation buttons
    {
      current = editor.getCurrent();
      if (current.parent)
      {
        arraySetColor(leftElements, 'black');
        if (current.parent.children.length > 1) // Has siblings
          arraySetColor(siblingElements, 'black');
        else
          arraySetColor(siblingElements, besogo.GREY);
      }
      else
      {
        arraySetColor(leftElements, besogo.GREY);
        arraySetColor(siblingElements, besogo.GREY);
      }
      if (current.children.length)
        arraySetColor(rightElements, 'black');
      else
        arraySetColor(rightElements, besogo.GREY);
    }

    function arraySetColor(list, color) // Changes fill color of list of svg elements
    {
      for (let i = 0; i < list.length; i++)
        list[i].setAttribute('fill', color);
    }
  }

  function drawNavButtons()
  {
    leftElements.push(makeNavButton('First node',
                                    '5,10 5,90 25,90 25,50 95,90 95,10 25,50 25,10',
                                    function() { editor.prevNode(-1); }));
    /*
	leftElements.push(makeNavButton('Jump back',
                                    '95,10 50,50 50,10 5,50 50,90 50,50 95,90',
                                    function() {editor.prevNode(10); }));
	*/
    leftElements.push(makeNavButton('Previous node',
                                    '85,10 85,90 15,50',
                                    function() { editor.prevNode(1); }));
    rightElements.push(makeNavButton('Next node',
                                     '15,10 15,90 85,50',
                                     function() { editor.nextNode(1); }));
	/*								 
    rightElements.push(makeNavButton('Jump forward',
                                     '5,10 50,50 50,10 95,50 50,90 50,50 5,90',
                                     function() { editor.nextNode(10); }));
	*/								 
    rightElements.push(makeNavButton('Last node',
                                     '95,10 95,90 75,90 75,50 5,90 5,10 75,50 75,10',
                                     function() { editor.nextNode(-1); }));
	/*
    siblingElements.push(makeNavButton('Previous sibling',
                                       '10,85 90,85 50,15',
                                       function() { editor.nextSibling(-1); }));
    siblingElements.push(makeNavButton('Next sibling',
                                       '10,15 90,15 50,85',
                                       function() { editor.nextSibling(1); }));
	*/
    function makeNavButton(tooltip, pointString, action) // Creates a navigation button
    {
      var button = document.createElement('button'),
          svg = makeButtonContainer(),
          element = besogo.svgEl("polygon",
                                 {
                                     points: pointString,
                                     stroke: 'none',
                                     fill: 'black'
                                 });

      button.title = tooltip;
      button.onclick = action;
      button.appendChild(svg);
      svg.appendChild(element);
      container.appendChild(button);

      return element;
    }
  }

  function drawStyleButtons()
  {
    var svg, element, coordStyleButton;

    svg = makeButtonContainer();
    element = besogo.svgEl("path", {
        d: 'm75,25h-50l50,50',
        stroke: 'black',
        "stroke-width": 5,
        fill: 'none'
    });
    svg.appendChild(element);
    childVariantElement = besogo.svgEl('circle', {
        cx: 25,
        cy: 25,
        r: 20,
        stroke: 'none'
    });
    coordStyleButton = document.createElement('button');
    coordStyleButton.onclick = function() { editor.toggleCoordStyle(); };
    coordStyleButton.title = 'Toggle coordinates';
    container.appendChild(coordStyleButton);
    svg = makeButtonContainer();
    coordStyleButton.appendChild(svg);
    svg.appendChild(besogo.svgLabel(50, 50, 'black', '四4'));
  }

  // Makes an SVG container for the button graphics
  function makeButtonContainer()
  {
    return besogo.svgEl('svg',
                        {
                          width: '100%',
                          height: '100%',
                          viewBox: "0 0 100 100"
                        });
  }
};
(function() {
'use strict';

// Parent object to hold coordinate system helper functions
besogo.coord = {};

// Null function for no coordinate system
besogo.coord.none = function(sizeX, sizeY) {
    return false;
};

// Western, chess-like, "A1" coordinate system
besogo.coord.western = function(sizeX, sizeY) {
    var labels = { x: [], y: [] }, i;
    for (i = 1; i <= sizeX; i++) {
        labels.x[i] = numberToLetter(i);
    }
    for (i = 1; i <= sizeY; i++) {
        labels.y[i] = (sizeY - i + 1) + '';
    }
    return labels;
};

// Simple purely numeric coordinate system
besogo.coord.numeric = function(sizeX, sizeY) {
    var labels = { x: [], y: [] }, i;
    for (i = 1; i <= sizeX; i++) {
        labels.x[i] = i + '';
    }
    for (i = 1; i <= sizeY; i++) {
        labels.y[i] = i + '';
    }
    return labels;
};

// Pierre Audouard corner-relative coordinate system
besogo.coord.pierre = function(sizeX, sizeY) {
    var labels = { x: [], xb: [], y: [], yb: [] }, i;
    for (i = 1; i <= sizeX / 2; i++) {
        labels.x[i] = 'a' + i;
        labels.x[sizeX - i + 1] = 'b' + i;
        labels.xb[i] = 'd' + i;
        labels.xb[sizeX - i + 1] = 'c' + i;
    }
    if (sizeX % 2) {
        i = Math.ceil(sizeX / 2);
        labels.x[i] = 'a';
        labels.xb[i] = 'c';
    }
    for (i = 1; i <= sizeY / 2; i++) {
        labels.y[i] = 'a' + i;
        labels.y[sizeY - i + 1] = 'd' + i;
        labels.yb[i] = 'b' + i;
        labels.yb[sizeY - i + 1] = 'c' + i;
    }
    if (sizeY % 2) {
        i = Math.ceil(sizeY / 2);
        labels.y[i] = 'd';
        labels.yb[i] = 'b';
    }
    return labels;
};

// Corner-relative, alpha-numeric, coordinate system
besogo.coord.corner = function(sizeX, sizeY) {
    var labels = { x: [], y: [] }, i;
    for (i = 1; i <= sizeX; i++) {
        if (i < (sizeX / 2) + 1) {
            labels.x[i] = numberToLetter(i);
        } else {
            labels.x[i] = (sizeX - i + 1) + '';
        }
    }
    for (i = 1; i <= sizeY; i++) {
        labels.y[i] = (sizeY - i + 1) + '';
        if (i > (sizeY / 2)) {
            labels.y[i] = numberToLetter(sizeY - i + 1);
        } else {
            labels.y[i] = i + '';
        }
    }
    return labels;
};

// Corner-relative, numeric and CJK, coordinate system
besogo.coord.eastcor = function(sizeX, sizeY) {
    var labels = { x: [], y: [] }, i;
    for (i = 1; i <= sizeX; i++) {
        if (i < (sizeX / 2) + 1) {
            labels.x[i] = numberToCJK(i);
        } else {
            labels.x[i] = (sizeX - i + 1) + '';
        }
    }
    for (i = 1; i <= sizeY; i++) {
        labels.y[i] = (sizeY - i + 1) + '';
        if (i > (sizeY / 2)) {
            labels.y[i] = numberToCJK(sizeY - i + 1);
        } else {
            labels.y[i] = i + '';
        }
    }
    return labels;
};

// Eastern, numeric and CJK, coordinate system
besogo.coord.eastern = function(sizeX, sizeY) {
    var labels = { x: [], y: [] }, i;
    for (i = 1; i <= sizeX; i++) {
        labels.x[i] = i + ''; // Columns are numeric
    }
    for (i = 1; i <= sizeY; i++) {
        labels.y[i] = numberToCJK(i);
    }

    return labels;
};

// Helper for converting numeric coord to letter (skipping I)
function numberToLetter(number) {
    return 'ABCDEFGHJKLMNOPQRSTUVWXYZ'.charAt((number - 1) % 25);
}

// Helper for converting numeric coord to CJK symbol
function numberToCJK(number) {
    var label = '',
        cjk = '一二三四五六七八九';

    if (number >= 20) { // 20 and larger
        label = cjk.charAt(number / 10 - 1) + '十';
    } else if (number >= 10) { // 10 through 19
        label = '十';
    }
    if (number % 10) { // Ones digit if non-zero
        label = label + cjk.charAt((number - 1) % 10);
    }
    return label;
}

})(); // END closure
besogo.makeEditor = function(sizeX, sizeY)
{
  'use strict';
  // Creates an associated game state tree
  var root = besogo.makeGameRoot(sizeX, sizeY),
      current = root, // Navigation cursor

      listeners = [], // Listeners of general game/editor state changes

      // Enumeration of editor tools/modes
      TOOLS = ['navOnly', // read-only navigate mode
          'auto', // auto-mode: navigate or auto-play color
          'addB', // setup black stone
          'clrMark', // remove markup
          'circle', // circle markup
          'square', // square markup
          'triangle', // triangle markup
          'cross', // "X" cross markup
          'block', // filled square markup
          'label'], // label markup
      tool = 'auto', // Currently active tool (default: auto-mode)
      label = "1", // Next label that will be applied

      navHistory = [], // Navigation history

      gameInfo = {}, // Game info properties

      // Order of coordinate systems
      COORDS = 'none numeric western eastern pierre corner eastcor'.split(' '),
      coord = 'none', // Selected coordinate system

      // Variant style: even/odd - children/siblings, <2 - show auto markup for variants
      variantStyle = 0, // 0-3, 0 is default
      edited = false,
      shift = false;

  return {
    addListener: addListener,
    click: click,
    nextNode: nextNode,
    prevNode: prevNode,
    nextSibling: nextSibling,
    prevBranchPoint: prevBranchPoint,
    toggleCoordStyle: toggleCoordStyle,
    getCoordStyle: getCoordStyle,
    setCoordStyle: setCoordStyle,
    getVariantStyle: getVariantStyle,
    setVariantStyle: setVariantStyle,
    getGameInfo: getGameInfo,
    setGameInfo: setGameInfo,
    setComment: setComment,
    getTool: getTool,
    setTool: setTool,
    getLabel: getLabel,
    setLabel: setLabel,
    getVariants: getVariants, // Returns variants of current node
    getCurrent: getCurrent,
    setCurrent: setCurrent,
    cutCurrent: cutCurrent,
    promote: promote,
    demote: demote,
    getRoot: getRoot,
    loadRoot: loadRoot, // Loads new game state
    wasEdited: wasEdited,
    resetEdited: resetEdited,
    notifyListeners: notifyListeners,
    setShift: setShift,
    isShift: isShift,
    applyTransformation : applyTransformation
  };

  // Returns the active tool
  function getTool() { return tool; }

  // Sets the active tool, returns false if failed
  function setTool(set)
  {
    // Toggle label mode if already label tool already selected
    if (set === 'label' && set === tool)
    {
      if (/^-?\d+$/.test(label)) // If current label is integer
        setLabel('A'); // Toggle to characters
      else
        setLabel('1'); // Toggle back to numbers
      return true; // Notification already handled by setLabel
    }
    // Set the tool only if in list and actually changed
    if (TOOLS.indexOf(set) !== -1 && tool !== set)
    {
      tool = set;
      notifyListeners({ tool: tool, label: label }); // Notify tool change
      return true;
    }
    return false;
  }

  // Gets the next label to apply
  function getLabel() { return label; }

  // Sets the next label to apply and sets active tool to label
  function setLabel(set)
  {
    if (typeof set === 'string')
    {
      set = set.replace(/\s/g, ' ').trim(); // Convert all whitespace to space and trim
      label = set || "1"; // Default to "1" if empty string
      tool = 'label'; // Also change current tool to label
      notifyListeners({ tool: tool, label: label }); // Notify tool/label change
    }
  }

  // Toggle the coordinate style
  function toggleCoordStyle()
  {
    coord = COORDS[(COORDS.indexOf(coord) + 1) % COORDS.length];
    notifyListeners({ coord: coord });
  }

  // Gets the current coordinate style
  function getCoordStyle() { return coord; }

  // Sets the coordinate system style
  function setCoordStyle(setCoord)
  {
    if (besogo.coord[setCoord])
    {
      coord = setCoord;
      notifyListeners({ coord: setCoord });
    }
  }

  // Returns the variant style
  function getVariantStyle() { return variantStyle; }

  // Directly sets the variant style
  function setVariantStyle(style)
  {
    if (style === 0 || style === 1 || style === 2 || style === 3)
    {
      variantStyle = style;
      notifyListeners({ variantStyle: variantStyle, markupChange: true });
    }
  }

  function getGameInfo() { return gameInfo; }

  function setGameInfo(info, id) {
    if (id)
      gameInfo[id] = info;
    else
      gameInfo = info;
    notifyListeners({ gameInfo: gameInfo });
  }

  function setComment(text)
  {
    text = text.trim(); // Trim whitespace and standardize line breaks
    text = text.replace(/\r\n/g,'\n').replace(/\n\r/g,'\n').replace(/\r/g,'\n');
    text.replace(/\f\t\v\u0085\u00a0/g,' '); // Convert other whitespace to space
    current.comment = text;
    notifyListeners({ comment: text });
  }

  // Returns variants of the current node according to the set style
  function getVariants()
  {
    if (variantStyle >= 2) // Do not show variants if style >= 2
        return [];
    if (variantStyle === 1) // Display sibling variants
      // Root node does not have parent nor siblings
      return current.parent ? current.parent.children : [];
    return current.children; // Otherwise, style must be 0, display child variants
  }

  // Returns the currently active node in the game state tree
  function getCurrent() { return current; }

  // Returns the root of the game state tree
  function getRoot() { return root; }

  function loadRoot(load)
  {
    root = load;
    current = load;
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
    edited = false;
  }

  // Navigates forward num nodes (to the end if num === -1)
  function nextNode(num)
  {
    if (current.children.length + current.virtualChildren.length === 0) // Check if no children
      return; // Do nothing if no children (avoid notification)
    while (current.children.length + current.virtualChildren.length > 0 && num !== 0)
    {
      if (navHistory.length) // Non-empty navigation history
        current = navHistory.pop();
      else // Empty navigation history
      {
        if (current.children.length)
        {
          current.children[0].cameFrom = null;
          current = current.children[0]; // Go to first child
        }
        else
        {
          var target = current.virtualChildren[0].target;
          target.cameFrom = current;
          current = target;
        }
      }
      num--;
    }
    // Notify listeners of navigation (with no tree edits)
    notifyListeners({ navChange: true }, true); // Preserve history
	
    if(editor.soundEnabled)
      document.getElementsByTagName("audio")[0].play();
    if(!current && current.lastMove == -root.firstMove && !current.hasChildIncludingVirtual())
      displayResult('F');
  }

  // Navigates backward num nodes (to the root if num === -1)
  function prevNode(num)
  {
    if (!current.parent) // Check if root
      return; // Do nothing if already at root (avoid notification)
    while (current.parent && num !== 0)
    {
      navHistory.push(current); // Save current into navigation history
      if (current.cameFrom)
        current = current.cameFrom;
      else
        current = current.parent;
      num--;
    }
    // Notify listeners of navigation (with no tree edits)
    notifyListeners({ navChange: true }, true); // Preserve history
  }

  // Cyclically switches through siblings
  function nextSibling(change)
  {
    var siblings,
        i = 0;

    if (current.parent)
    {
      siblings = current.parent.children;

      // Exit early if only child
      if (siblings.length === 1)
          return;

      // Find index of current amongst siblings
      i = siblings.indexOf(current);

      // Apply change cyclically
      i = (i + change) % siblings.length;
      if (i < 0)
        i += siblings.length;

      current = siblings[i];
      // Notify listeners of navigation (with no tree edits)
      notifyListeners({ navChange: true });
    }
  }

  // Return to the previous branch point
  function prevBranchPoint(change)
  {
    if (current.parent === null) // Check if root
      return; // Do nothing if already at root

    navHistory.push(current); // Save starting position in case we do not find a branch point

    while (current.parent && current.parent.children.length === 1) // Traverse backwards until we find a sibling
      current = current.parent;

    if (current.parent)
    {
      current = current.parent;
      notifyListeners({ navChange: true });
    }
    else
      current = navHistory.pop(current);
  }

  // Sets the current node
  function setCurrent(node)
  {
    if (current !== node)
    {
      current = node;
      // Notify listeners of navigation (with no tree edits)
      notifyListeners({ navChange: true });
    }
  }

  // Removes current branch from the tree
  function cutCurrent()
  {
    var parent = current.parent;
    if (tool === 'navOnly')
      return; // Tree editing disabled in navOnly mode
    if (parent)
    {
      current.destroy();
      current = parent;
      besogo.updateCorrectValues(current.getRoot());
      // Notify navigation and tree edited
      notifyListeners({ treeChange: true, navChange: true });
    }
  }

  // Raises current variation to a higher precedence
  function promote()
  {
    if (tool === 'navOnly')
      return; // Tree editing disabled in navOnly mode
    if (current.parent && current.parent.promote(current))
      notifyListeners({ treeChange: true }); // Notify tree edited
  }

  // Drops current variation to a lower precedence
  function demote()
  {
    if (tool === 'navOnly')
      return; // Tree editing disabled in navOnly mode
    if (current.parent && current.parent.demote(current))
      notifyListeners({ treeChange: true }); // Notify tree edited
  }

  // Handle click with application of selected tool
  function click(i, j, ctrlKey, shiftKey)
  {
    switch(tool)
    {
      case 'navOnly':
        navigate(i, j, shiftKey);
        break;
      case 'auto':
        if (!navigate(i, j, shiftKey) && !shiftKey) // Try to navigate to (i, j)
          playMove(i, j, 0, ctrlKey); // Play auto-color move if navigate fails
        break;
      case 'addB':
        if (ctrlKey)
          playMove(i, j, -1, true); // Play black
        else if (shiftKey)
          placeSetup(i, j, 1); // Set white
        else
          placeSetup(i, j, -1); // Set black
        break;
      case 'clrMark':
        setMarkup(i, j, 0);
        break;
      case 'circle':
        setMarkup(i, j, 1);
        break;
      case 'square':
        setMarkup(i, j, 2);
        break;
      case 'triangle':
        setMarkup(i, j, 3);
        break;
      case 'cross':
        setMarkup(i, j, 4);
        break;
      case 'block':
        setMarkup(i, j, 5);
        break;
      case 'label':
        setMarkup(i, j, label);
        break;
    }

    if(soundEnabled && isMutable)
      document.getElementsByTagName("audio")[0].play();
    
    if(current.correct)
    {
      toggleBoardLock(true);
      enableReviewButton();
      displayResult('S');
    }
  }

  // Navigates to child with move at (x, y), searching tree if shift key pressed
  // Returns true is successful, false if not
  function navigate(x, y, shiftKey)
  {
    var children = current.children;

    // Look for move across children
    for (let i = 0; i < children.length; i++)
    {
      let move = children[i].move;
      if (shiftKey)  // Search for move in branch
      {
        if (jumpToMove(x, y, children[i]))
          return true;
      }
      else if (move && move.x === x && move.y === y)
      {
        current = children[i]; // Navigate to child if found
        notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
        return true;
      }
    }

    if (current.virtualChildren)
      for (let i = 0; i < current.virtualChildren.length; i++)
      {
        var child = current.virtualChildren[i];
        let move = child.move;
        if (move.x === x && move.y === y)
        {
          child.target.cameFrom = current;
          current = child.target;
          notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
          return true;
        }
      }

    if (shiftKey && jumpToMove(x, y, root, current))
      return true;
    return false;
  }

  // Recursive function for jumping to move with depth-first search
  function jumpToMove(x, y, start, end)
  {
    var i, move,
        children = start.children;

    if (end && end === start)
      return false;

    move = start.move;
    if (move && move.x === x && move.y === y)
    {
      current = start;
      notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
      return true;
    }

    for (i = 0; i < children.length; i++)
      if (jumpToMove(x, y, children[i], end))
        return true;
    return false;
  }

  // Plays a move at the given color and location
  // Set allowAll to truthy to allow illegal moves
  function playMove(i, j, color, allowAll)
  {
    // Check if current node is immutable or root
    if (!current.isMutable('move') || !current.parent)
    {
      var next = current.makeChild(); // Create a new child node
      if (next.playMove(i, j, color, allowAll)) // Play in new node
      {
        // Keep (add to game state tree) only if move succeeds
        current.registerChild(next);
        current = next;
        // Notify tree change, navigation, and stone change
        notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
        edited = true;
        if(soundEnabled)
          document.getElementsByTagName("audio")[0].play();
        displayResult('F');
      }
    // Current node is mutable and not root
    }
    else if (current.playMove(i, j, color, allowAll))
    { // Play in current
        // Only need to update if move succeeds
		
      current.registerInVirtualMoves();
      besogo.updateCorrectValues(current.getRoot());
      notifyListeners({ treeChange: true, stoneChange: true });
      edited = true;
    }
	isMutable = current.isMutable('move');
  }

  // Places a setup stone at the given color and location
  function placeSetup(i, j, color)
  {
    if (current.getStone(i, j)) // Compare setup to current
      color = 0; // Same as current indicates removal desired
    else if (!color) // Color and current are both empty
      return; // No change if attempting to set empty to empty
    // Check if current node can accept setup stones
    if (!current.isMutable('setup'))
    {
      var next = current.makeChild(); // Create a new child node
      if (next.placeSetup(i, j, color)) // Place setup stone in new node
      {
        // Keep (add to game state tree) only if change occurs
        current.addChild(next);
        current = next;
        // Notify tree change, navigation, and stone change
        notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
      }
    }
    else if(current.placeSetup(i, j, color)) // Try setup in current
        // Only need to update if change occurs
      notifyListeners({ stoneChange: true }); // Stones changed
  }

  // Sets the markup at the given location and place
  function setMarkup(i, j, mark)
  {
    if (mark === current.getMarkup(i, j))
      if (mark !== 0) // Compare mark to current
        mark = 0; // Same as current indicates removal desired
      else // Mark and current are both empty
        return; // No change if attempting to set empty to empty

    if (current.addMarkup(i, j, mark)) // Try to add the markup
    {
      var temp; // For label incrementing
      if (typeof mark === 'string')
      { // If markup is a label, increment the label
        if (/^-?\d+$/.test(mark)) // Integer number label
        {
          temp = +mark; // Convert to number
          // Increment and convert back to string
          setLabel( "" + (temp + 1) );
        }
        else if (/[A-Za-z]$/.test(mark))
        { // Ends with [A-Za-z]
          // Get the last character in the label
          temp = mark.charAt(mark.length - 1);
          if (temp === 'z') // Cyclical increment
            temp = 'A'; // Move onto uppercase letters
          else if (temp === 'Z')
            temp = 'a'; // Move onto lowercase letters
          else
            temp = String.fromCharCode(temp.charCodeAt() + 1);
          // Replace last character of label with incremented char
          setLabel(mark.slice(0, mark.length - 1) + temp);
        }
      }
      notifyListeners({ markupChange: true }); // Notify markup change
    }
  }

  // Adds a listener (by call back func) that will be notified on game/editor state changes
  function addListener(listener) { listeners.push(listener); }

  // Notify listeners with the given message object
  //  Data sent to listeners:
  //    tool: changed tool selection
  //    label: changed next label
  //    coord: changed coordinate system
  //    variantStyle: changed variant style
  //    gameInfo: changed game info
  //    comment: changed comment in current node
  //  Flags sent to listeners:
  //    treeChange: nodes added or removed from tree
  //    navChange: current switched to different node
  //    stoneChange: stones modified in current node
  //    markupChange: markup modified in current node
  function notifyListeners(msg, keepHistory)
  {
    if (msg.treeChange || msg.stoneChange)
      edited = true;
    if (!keepHistory && msg.navChange)
      navHistory = []; // Clear navigation history
    for (let i = 0; i < listeners.length; i++)
      listeners[i](msg);
  }

  function wasEdited()
  {
    return edited;
  }

  function resetEdited()
  {
    edited = false;
  }

  function setShift(value)
  {
    shift = value;
  }

  function isShift()
  {
    return shift;
  }
  
  function applyTransformation(transformation)
  {
    root.applyTransformation(root, transformation);
    root.firstMove = transformation.applyOnColor(root.firstMove);
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
    edited = true;
  }
};
besogo.makeFilePanel = function(container, editor) {
    'use strict';
    var fileChooser, // Reference to the file chooser element
        element, // Scratch variable for creating elements
        WARNING = "Everything not saved will be lost";

    makeNewBoardButton(9); // New 9x9 board button
    makeNewBoardButton(13); // New 13x13 board button
    makeNewBoardButton(19); // New 19x19 board button
    makeNewBoardButton('?'); // New custom board button

    // Hidden file chooser element
    fileChooser = makeFileChooser();
    container.appendChild(fileChooser);

    // Load file button
    element = document.createElement('input');
    element.type = 'button';
    element.value = 'Open';
    element.title = 'Import SGF';
    element.onclick = function()  // Bind click to the hidden file chooser
    {
      if (editor.wasEdited() && !confirm("Changes were made, throw it away?"))
        return;
      fileChooser.click();
    };
    container.appendChild(element);

    // Save file button
    element = document.createElement('input');
    element.type = 'button';
    element.value = 'Save';
    element.title = 'Export SGF';
    element.onclick = function()
    {
      var fileName = prompt('Save file as', 'export');
      if (fileName) // Canceled or empty string does nothing
      {
        saveFile(fileName + ".sgf", besogo.composeSgf(editor));
        editor.resetEdited();
      }
    };
    container.appendChild(element);

    // Save file button
    element = document.createElement('input');
    element.type = 'button';
    element.value = 'Save expanded';
    element.title = 'Export SGF export with all virtual variations expanded';
    element.onclick = function()
    {
      let checkResult = editor.getRoot().checkTsumegoHeroCompatibility()
      if (checkResult)
      {
        editor.setCurrent(checkResult.node);
        window.alert(checkResult.message);
        return;
      }

      var fileName = prompt('Save file as', 'export');
      if (fileName) // Canceled or empty string does nothing
      {
        saveFile(fileName + ".sgf", besogo.composeSgf(editor, true));
        editor.resetEdited();
      }
    };
    container.appendChild(element);


    // Makes a new board button
    function makeNewBoardButton(size)
    {
      var button = document.createElement('input');
      button.type = 'button';
      button.value = size + "x" + size;
      if (size === '?')
      { // Make button for custom sized board
        button.title = "New custom size board";
        button.onclick = function()
        {
          var input = prompt("Enter custom size for new board" + "\n" + (editor.wasEdited() ? WARNING : ''), "19:19");
          if (input)  // Canceled or empty string does nothing
          {
            var size = besogo.parseSize(input);
            editor.loadRoot(besogo.makeGameRoot(size.x, size.y));
            editor.setGameInfo({});
          }
        };
      }
      else
      { // Make button for fixed size board
        button.title = "New " + size + "x" + size + " board";
        button.onclick = function()
        {
          if (!editor.wasEdited() || confirm(button.title + "?\n" + WARNING))
          {
            editor.loadRoot(besogo.makeGameRoot(size, size));
            editor.setGameInfo({});
          }
        };
      }
      container.appendChild(button);
    }

    // Creates the file selector
    function makeFileChooser()
    {
      var chooser = document.createElement('input');
      chooser.type = 'file';
      chooser.style.display = 'none'; // Keep hidden
      chooser.onchange = readFile; // Read, parse and load on file select
      return chooser;
    }

    // Reads, parses and loads an SGF file
    function readFile(evt)
    {
      var file = evt.target.files[0], // Selected file
          reader = new FileReader();

      var newChooser = makeFileChooser(); // Create new file input to reset selection

      container.replaceChild(newChooser, fileChooser); // Replace with the reset selector
      fileChooser = newChooser;

      reader.onload = function(e) // Parse and load game tree
      {
        var sgf;
        try
        {
          sgf = besogo.parseSgf(e.target.result);
        }
        catch (error)
        {
          alert('SGF parse error at ' + error.at + ':\n' + error.message);
          return;
        }
        besogo.loadSgf(sgf, editor);
      };
      reader.readAsText(file); // Initiate file read
    }

    // Composes SGF file and initializes download
    function saveFile(fileName, text)
    {
      var link = document.createElement('a'),
          blob = new Blob([text], { encoding:"UTF-8", type:"text/plain;charset=UTF-8" });

      link.download = fileName; // Set download file name
      link.href = URL.createObjectURL(blob);
      link.style.display = 'none'; // Make link hidden
      container.appendChild(link); // Add link to ensure that clicking works
      link.click(); // Click on link to initiate download
      container.removeChild(link); // Immediately remove the link
    }
};
const BLACK = -1;
const WHITE = 1;
const EMPTY = 0;

besogo.makeGameRoot = function(sizeX = 19, sizeY = 19)
{
  var root = { // Inherited attributes of root node
          blackCaps: 0,
          whiteCaps: 0,
          moveNumber: 0
      };

  // Initializes non-inherited attributes
  function initNode(node, parent)
  {
    node.parent = parent;
    node.board = parent ? Object.create(parent.board) : [];
    node.children = [];
    node.virtualChildren = [];

    node.move = null;
    node.setupStones = [];
    node.virtualParents = [];
    node.markup = [];
    node.comment = ''; // Comment on this node
    node.hash = 0;
    node.correctSource = false;
    node.correct = false;
    node.cameFrom = null;
    node.statusSource = null;
    node.status = null;
  }
  initNode(root, null); // Initialize root node with null parent
  root.relevantMoves = [];
  root.nodeHashTable = besogo.makeNodeHashTable();
  root.goal = GOAL_NONE;
  root.status = besogo.makeStatusSimple(STATUS_NONE);
  root.firstMove = BLACK;

  root.playMove = function(x, y, color = false, allow = false)
  {
    if (!this.isMutable('move'))
      return false; // Move fails if node is immutable
    return this.playMoveWithoutMutableCheck(x, y, color, allow);
  }

  // Plays a move, returns true if successful
  // Set allow to truthy to allow overwrite, suicide and ko
  root.playMoveWithoutMutableCheck = function(x, y, color = false, allow = false)
  {
    var captures = 0, // Number of captures made by this move
        overwrite = false, // Flags whether move overwrites a stone
        prevMove; // Previous move for ko check

    if (!color) // Falsy color indicates auto-color
      color = this.nextMove();

    if (x < 1 || y < 1 || x > sizeX || y > sizeY)
    {
      // Register as pass move if out of bounds
      this.move =
      {
        x: 0, y: 0, // Log pass as position (0, 0)
        color: color,
        captures: 0, // Pass never captures
        overwrite: false // Pass is never an overwrite
      };
      this.lastMove = color; // Store color of last move
      this.moveNumber++; // Increment move number
      return true; // Pass move successful
    }

    var previousColor = this.getStone(x, y);

    if (previousColor)  // Check for overwrite
    {
      if (!allow)
        return false; // Reject overwrite move if not allowed
      overwrite = true; // Otherwise, flag overwrite and proceed
    }

    var pending = []; // Initialize pending capture array
    var suicidePending = [];

    this.setStone(x, y, color); // Place the move stone

    // Check for captures of surrounding chains
    captureStones(this, x - 1, y, color, pending);
    captureStones(this, x + 1, y, color, pending);
    captureStones(this, x, y - 1, color, pending);
    captureStones(this, x, y + 1, color, pending);

    captures = pending.length; // Capture count

    prevMove = this.parent ? this.parent.move : null; // Previous move played
    if (!allow && prevMove && // If previous move exists, ...
        prevMove.color === -color && // was of the opposite color, ...
        prevMove.overwrite === false && // not an overwrite, ...
        prevMove.captures === 1 && // captured exactly one stone, and if ...
        captures === 1 && // this move captured exactly one stone at the location ...
        !this.getStone(prevMove.x, prevMove.y)) //of the previous move
    {
      this.setStone(x, y, previousColor);
      for (let i = 0; i < pending.length; ++i)
        this.setStone(pending[i].x, pending[i].y, -color);
      return false; // Reject ko move if not allowed
    }

    if (captures === 0)  // Check for suicide if nothing was captured
    {
      captureStones(this, x, y, -color, suicidePending); // Invert color for suicide check
      captures = -suicidePending.length; // Count suicide as negative captures
      if (captures < 0 && !allow)
      {
        this.setStone(x, y, previousColor);
        for (let i = 0; i < pending.length; ++i)
          this.setStone(pending[i].x, pending[i].y, -color);
        for (let i = 0; i < suicidePending.length; ++i)
          this.setStone(suicidePending[i].x, suicidePending[i].y, color);
        return false; // Reject suicidal move if not allowed
      }
    }

    if (color * captures < 0) // Capture by black or suicide by white
      this.blackCaps += Math.abs(captures); // Tally captures for black
    else // Capture by white or suicide by black
      this.whiteCaps += Math.abs(captures); // Tally captures for white

    // Log the move
    this.move =
    {
      x: x, y: y,
      color: color,
      captures: captures,
      overwrite: overwrite
    };
    this.lastMove = color; // Store color of last move
    this.moveNumber++; // Increment move number
    return true;
  };

  // Check for and perform capture of opposite color chain at (x, y)
  function captureStones(board, x, y, color, captures)
  {
    var pending = [];

    // Captured chain found
    if (!recursiveCapture(board, x, y, color, pending))
      for (let i = 0; i < pending.length; i++)  // Remove captured stones
      {
        board.setStone(pending[i].x, pending[i].y, EMPTY);
        captures.push(pending[i]);
      }
  }

  // Recursively builds a chain of pending captures starting from (x, y)
  // Stops and returns true if chain has liberties
  function recursiveCapture(board, x, y, color, pending)
  {
    if (x < 1 || y < 1 || x > sizeX || y > sizeY)
      return false; // Stop if out of bounds
    if (board.getStone(x, y) === color)
      return false; // Stop if other color found
    if (!board.getStone(x, y))
      return true; // Stop and signal that liberty was found
    for (let i = 0; i < pending.length; i++)
      if (pending[i].x === x && pending[i].y === y)
        return false; // Stop if already in pending captures

    pending.push({ x: x, y: y }); // Add new stone into chain of pending captures

    // Recursively check for liberties and expand chain
    if (recursiveCapture(board, x - 1, y, color, pending) ||
        recursiveCapture(board, x + 1, y, color, pending) ||
        recursiveCapture(board, x, y - 1, color, pending) ||
        recursiveCapture(board, x, y + 1, color, pending))
      return true; // Stop and signal liberty found in subchain
    return false; // Otherwise, no liberties found
  }

  // Get next to move
  root.nextMove = function()
  {
    if (this.lastMove) // If a move has been played
      return -this.lastMove; // Then next is opposite of last move
    else
      return this.getRoot().firstMove; // otherwise, black plays first
  };

  root.nextIsBlack = function() { return this.nextMove() == BLACK; }

  // Places a setup stone, returns true if successful
  root.placeSetup = function(x, y, color)
  {
    let prevColor = (this.parent && this.parent.getStone(x, y)) || EMPTY;

    if (x < 1 || y < 1 || x > sizeX || y > sizeY)
      return false; // Do not allow out of bounds setup
    if (!this.isMutable('setup') || this.getStone(x, y) === color)
      // Prevent setup changes in immutable node or quit early if no change
      return false;

    this.setStone(x, y, color); // Place the setup stone
    this.setupStones[this.fromXY(x, y)] = color - prevColor; // Record the necessary change
    return true;
  };

  // Adds markup, returns true if successful
  root.addMarkup = function(x, y, mark)
  {
    if (x < 1 || y < 1 || x > sizeX || y > sizeY)
      return false; // Do not allow out of bounds markup
    if (this.getMarkup(x, y) === mark) // Quit early if no change to make
      return false;
    this.markup[this.fromXY(x, y)] = mark;
    return true;
  };

  root.getStone = function(x, y) { return this.board[x + '-' + y] || EMPTY; };
  root.setStone = function(x, y, color) { this.board[x + '-' + y] = color; }

  // Gets the setup stone placed at (x, y), returns false if none
  root.getSetup = function(x, y)
  {
    if (!this.setupStones[this.fromXY(x, y)]) // No setup stone placed
      return false;
    else // Determine net effect of setup stone
      switch(this.getStone(x, y))
      {
        case EMPTY: return 'AE';
        case BLACK: return 'AB';
        case WHITE: return 'AW';
      }
  };

  // Gets the markup at (x, y)
  root.getMarkup = function(x, y)
  {
    return this.markup[this.fromXY(x, y)] || EMPTY;
  };

  // Determines the type of this node
  root.getType = function()
  {
    if (this.move) // Logged move implies move node
      return 'move';

    for (let i = 0; i < this.setupStones.length; i++)
      if (this.setupStones[i]) // Any setup stones implies setup node
        return 'setup';

    return 'empty'; // Otherwise, "empty" (neither move nor setup)
  };

  root.getCorrectColor = function()
  {
    if (this.correct)
      return 'green';
    return 'red';
  };

  // Checks if this node can be modified by a 'type' action
  root.isMutable = function(type)
  {
    // Can only add a move to an empty node with no children
    if (type === 'move' && this.getType() === 'empty' && this.children.length === 0)
      return true;

    // Can only add setup stones to a non-move node (children are allowed to be able to edit existing problem)
    if (type === 'setup' && this.getType() !== 'move')
      return true;
    return false;
  };

  // Gets siblings of this node
  root.getSiblings = function()
  {
    return (this.parent && this.parent.children) || [];
  };

  // Makes a child node of this node, but does NOT add it to children
  root.makeChild = function()
  {
    var child = Object.create(this); // Child inherits properties
    initNode(child, this); // Initialize other properties

    return child;
  };

  root.registerMove = function(x, y)
  {
    let child = this.makeChild();
    if (!child.playMove(x, y))
      console.assert("Move couldn't be played");
    this.registerChild(child);
    return child;
  }

  root.registerChild = function(child)
  {
    this.addChild(child);
    child.registerInVirtualMoves();
    besogo.updateCorrectValues(this.getRoot());
  }

  // Adds a child to this node
  root.addChild = function(child)
  {
    if (this.statusSource)
      this.statusSource = null;
    this.children.push(child);
    this.correct = false;
    this.correctSource = false;
    return child;
  };

  // Remove child node from this node, returning false if failed
  root.removeChild = function(child)
  {
    let i = this.children.indexOf(child);
    if (i == -1)
      return false;
    this.children.splice(i, 1);
    return true;
  };

  root.removeVirtualParent = function(virtualParent)
  {
    let i = this.virtualParents.indexOf(virtualParent);
    if (i == -1)
      return false;
    this.virtualParents.splice(i, 1);
    return true;
  };

  root.destroy = function(root = this.getRoot(), removeFromParent = true)
  {
    for (let i = 0; i < this.children.length; ++i)
      this.children[i].destroy(root, false);
    this.children = [];

    for (let i = 0; i < this.virtualParents.length; ++i)
      this.virtualParents[i].removeVirtualChild(this);
    this.virtualParents = [];

    for (let i = 0; i < this.virtualChildren.length; ++i)
      this.virtualChildren[i].target.removeVirtualParent(this);
    this.virtualChildren = [];
    root.nodeHashTable.erase(this);

    if (removeFromParent)
      this.parent.removeChild(this);
  };

  root.removeVirtualChild = function(child)
  {
    for (let i = 0; i < this.virtualChildren.length; ++i)
    {
      var node = this.virtualChildren[i];
      if (node.target == child)
      {
        this.virtualChildren.splice(i, 1);
        return true;
      }
    }
    return false;
  };

  root.getMoveToGetToVirtualChild = function(child)
  {
    for (let i = 0; i < this.virtualChildren.length; ++i)
      if (this.virtualChildren[i].target == child)
        return this.virtualChildren[i].move;
    return null;
  }

  // Raises child variation to a higher precedence
  root.promote = function(child)
  {
    var i = this.children.indexOf(child);
    if (i > 0) // Child exists and not already first
    {
      this.children[i] = this.children[i - 1];
      this.children[i - 1] = child;
      return true;
    }
    return false;
  };

  // Drops child variation to a lower precedence
  root.demote = function(child)
  {
    var i = this.children.indexOf(child);
    if (i !== -1 && i < this.children.length - 1) // Child exists and not already last
    {
      this.children[i] = this.children[i + 1];
      this.children[i + 1] = child;
      return true;
    }
    return false;
  };

  // Gets board size
  root.getSize = function() { return {x: sizeX, y: sizeY}; };

  // Convert (x, y) coordinates to linear index
  root.fromXY = function(x, y)
  {
    return (x - 1) * sizeY + (y - 1);
  }

  root.toXY = function(value)
  {
    var result = [];
    result.y = value % sizeY + 1;
    result.x = Math.floor(value/sizeY) + 1;
    return result;
  }

  root.getHash = function()
  {
    if (this.hash)
      return this.hash;
    return this.updateHash();
  }

  function hashCode(str)
  {
    let hash = 0;
    for (let i = 0, len = str.length; i < len; i++)
    {
      let chr = str.charCodeAt(i);
      hash = (hash << 5) - hash + chr;
      hash |= 0; // Convert to 32bit integer
    }
    return hash;
  }

  root.updateHash = function()
  {
    this.hash = 1;
    for (var key in this.board)
      this.hash += hashCode(key) * this.board[key];
    return this.hash
  }

  function keyCount(a)
  {
    var i = 0;
    for (var key in a) ++i;
    return i;
  }

  function compareAssociativeArrays(a, b)
  {
    if (keyCount(a) != keyCount(b))
      return false;
    for (var key in a)
      if (a[key] != b[key])
        return false;
     return true;
  }

  root.getForbiddenMoveBecauseOfKo = function()
  {
    if (!this.move)
      return null;
    if (this.move.captures != 1)
      return null;
    let whiteSurrounds = 0;
    let emptySpace = null;
    if (this.move.x > 1)
    {
      let stone = this.getStone(this.move.x - 1, this.move.y);
      if (stone == -this.move.color)
        ++whiteSurrounds;
      else if (stone == 0)
        emptySpace = {x: this.move.x - 1, y: this.move.y};
    }
    else
      ++whiteSurrounds;

    if (this.move.x < sizeX)
    {
      let stone = this.getStone(this.move.x + 1, this.move.y);
      if (stone == -this.move.color)
        ++whiteSurrounds;
      else if (stone == 0)
        emptySpace = {x: this.move.x + 1, y: this.move.y};
    }
    else
      ++whiteSurrounds;

    if (this.move.y > 1)
    {
      let stone = this.getStone(this.move.x, this.move.y - 1);
      if (stone == -this.move.color)
        ++whiteSurrounds;
      else if (stone == 0)
        emptySpace = {x: this.move.x, y: this.move.y - 1};
    }
    else
      ++whiteSurrounds;

    if (this.move.y < sizeY)
    {
      let stone = this.getStone(this.move.x, this.move.y + 1);
      if (stone == -this.move.color)
        ++whiteSurrounds;
      else if (stone == 0)
        emptySpace = {x: this.move.x, y: this.move.y + 1};
    }
    else
      ++whiteSurrounds;

    if (whiteSurrounds != 3)
      return null;
    return emptySpace;
  }

  root.hasSameKoStateAs = function(other)
  {
    let thisKo = this.getForbiddenMoveBecauseOfKo(this.move);
    let otherKo = other.getForbiddenMoveBecauseOfKo(other.move);
    if (!thisKo && !otherKo)
      return true;
    if (!thisKo && otherKo)
      return false;
    if (thisKo && !otherKo)
      return false;
    return thisKo.x == otherKo.x || thisKo.y == otherKo.y;
  }

  root.samePositionAs = function(other)
  {
    if (this.nextIsBlack() != other.nextIsBlack())
      return false;
    if (!compareAssociativeArrays(this.board, other.board))
      return false;
    return this.hasSameKoStateAs(other);
  }

  root.treeSize = function()
  {
    var result = 1;
    for (let i = 0; i < this.children.length; ++i)
      result += this.children[i].treeSize();
    return result;
  }

  root.getRoot = function()
  {
    let i = this;
    while (i.parent)
      i = i.parent;
    return i;
  }

  root.registerInVirtualMoves = function()
  {
    let myRoot = this.getRoot();
    let index = this.fromXY(this.move.x, this.move.y);
    myRoot.relevantMoves[index] = true;
    besogo.addVirtualChildren(myRoot, this);
  }

  root.setCorrectSource = function(value, editor)
  {
    if (value === this.correctSource)
      return;
    if (this.children.length > 0 || this.virtualChildren.length > 0)
      return;
    this.correctSource = value;
    besogo.updateCorrectValues(this.getRoot());
    editor.notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
  }

  root.checkTsumegoHeroCompatibility = function()
  {
    if (!this.nextIsBlack() &&
        this.children.length == 0 &&
        this.virtualChildren.length == 0 &&
        !this.correctSource)
      return {node: this, message: "Last black move not marked correct"};

    for (let i = 0; i < this.children.length; ++i)
    {
      let result = this.children[i].checkTsumegoHeroCompatibility()
      if (result)
        return result;
    }
    for (let i = 0; i < this.virtualChildren.length; ++i)
    {
      let result = this.virtualChildren[i].target.checkTsumegoHeroCompatibility()
      if (result)
        return result;
    }
    return null;
  }

  root.setStatusSource = function(statusSource)
  {
    if (this.hasChildIncludingVirtual())
      return false;
    this.statusSource = statusSource;
    besogo.updateCorrectValues(this.getRoot());
    return true;
  }

  root.checkConsistency = function()
  {
    for (let i = 0; i < this.children.length; ++i)
      this.children[i].checkConsistency();

    if (this.statusSource)
      console.assert(!this.hasChildIncludingVirtual());
    if (this.correctSource)
      console.assert(!this.hasChildIncludingVirtual());
    if (this.nodeHashTable)
      console.assert(this.parent == null);
    console.assert(this.status != null);
  }

  root.hasChildIncludingVirtual = function()
  {
    return this.children.length != 0 || this.virtualChildren.length != 0;
  }

  root.setGoal = function(goal)
  {
    console.assert(this.parent == null);
    this.goal = goal;
    besogo.updateCorrectValues(this);
  }

  root.applyTransformation = function(rootNode, transformation)
  {
    rootNode.nodeHashTable.erase(this);

    let oldSetupStones = this.setupStones;
    this.setupStones = [];
    this.board = this.parent ? Object.create(this.parent.board) : [];
    for (let i = 0; i < oldSetupStones.length; ++i)
      if (oldSetupStones[i])
      {
        let position = this.toXY(i);
        let newPosition = transformation.apply(position, {x: sizeX, y: sizeY});
        this.placeSetup(newPosition.x, newPosition.y, transformation.applyOnColor(oldSetupStones[i]));
      }
    let oldMove = this.move;
    this.move = null;
    if (oldMove)
    {
      let newMove = transformation.apply(oldMove, {x: sizeX, y: sizeY});
      this.playMoveWithoutMutableCheck(newMove.x, newMove.y, transformation.applyOnColor(oldMove.color));
      --this.moveNumber;
    }
    this.updateHash();
    rootNode.nodeHashTable.push(this);

    for (let i = 0; i < this.children.length; ++i)
      this.children[i].applyTransformation(rootNode, transformation);
  }

  root.figureFirstToMove = function()
  {
    if (this.children.length == 0)
      return;
    for (let i = 0; i < this.children.length; ++i)
      if (this.children[i].move && this.children[i].move.color)
      {
        this.firstMove =  this.children[i].move.color
        return;
      }
  }
  root.getCountOfLeafsWithoutStatus = function()
  {
    let result = 0;
    if (!this.hasChildIncludingVirtual() && !this.statusSource)
      ++result;
    for (let i = 0; i < this.children.length; ++i)
      result += this.children[i].getCountOfLeafsWithoutStatus();
    return result;
  }

  root.getLeafWithoutStatus = function()
  {
    if (!this.hasChildIncludingVirtual() && !this.statusSource)
      return this;
    for (let i = 0; i < this.children.length; ++i)
    {
      let result = this.children[i].getLeafWithoutStatus();
      if (result)
        return result;
    }
    return null;
  }

  return root;
};
besogo.addTest("GameRoot", "Empty", function()
{
  let root = besogo.makeGameRoot();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "OneMove", function()
{
  let root = besogo.makeGameRoot();
  root.registerMove(5, 5);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);
});

besogo.addTest("GameRoot", "RemoveOneChild", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  child.destroy();
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.virtualChildren.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});

besogo.addTest("GameRoot", "RemoveVariation", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  CHECK_EQUALS(root.nodeHashTable.size(), 1);

  child.registerMove(6, 6);
  CHECK(child.hasChildIncludingVirtual());
  CHECK_EQUALS(root.children.length, 1);
  CHECK_EQUALS(child.children.length, 1);
  CHECK_EQUALS(root.nodeHashTable.size(), 2);
  child.destroy();
  
  CHECK_EQUALS(root.children.length, 0);
  CHECK_EQUALS(root.nodeHashTable.size(), 0);
});


besogo.addTest("GameRoot", "TwoOrderOfMovesLeadToTheSameNode", function()
{
  let root = besogo.makeGameRoot();
  let finalChild = root.registerMove(1, 1).registerMove(1, 2).registerMove(2, 1).registerMove(2, 2);
  let otherOrder = root.registerMove(2, 1).registerMove(2, 2).registerMove(1, 1);
  
  CHECK_EQUALS(otherOrder.virtualChildren.length, 1);
  CHECK(otherOrder.virtualChildren[0].target == finalChild);
  CHECK_EQUALS(finalChild.virtualParents.length, 1);
  CHECK(finalChild.virtualParents[0] == otherOrder);
});// Load a parsed SGF object into a game state tree
besogo.loadSgf = function(sgf, editor)
{
  'use strict';
  var size = { x: 19, y: 19 }, // Default size (may be changed by load)
      root;

  loadRootProps(sgf); // Load size, variants style and game info
  root = besogo.makeGameRoot(size.x, size.y);

  loadNodeTree(sgf, root); // Load the rest of game tree
  root.figureFirstToMove();
  besogo.updateTreeAsProblem(root);
  editor.loadRoot(root); // Load root into the editor

  // Loads the game tree
  function loadNodeTree(sgfNode, gameNode)
  {
    var i, nextGameNode;

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
        break;
      case 'TR': // Add triangle markup
        applyPointList(prop.values, node, markupFunc, 3);
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
besogo.makeNodeHashTable = function()
{
  var nodeHashTable = [];
  nodeHashTable.table = [];

  nodeHashTable.push = function(node)
  {
    var hash = node.getHash();
    if (!this.table[hash])
      this.table[hash] = []
    this.table[hash].push(node);
  }

  nodeHashTable.erase = function(node)
  {
    var hash = node.getHash();
    var hashPoint = this.table[hash];
    if (!hashPoint)
      throw new Error('Node to be removed not found.');

    for (let i = 0; i < hashPoint.length; ++i)
      if (hashPoint[i] == node)
      {
        hashPoint.splice(i, 1);
        return;
      }
    throw new Error('Node to be removed not found.');
  }

  nodeHashTable.getSameNode = function(node)
  {
    var hash = node.getHash();
    var hashPoint = this.table[hash];
    if (!hashPoint)
      return null;
    for (let i = 0; i < hashPoint.length; ++i)
      if (node.samePositionAs(hashPoint[i]))
        return hashPoint[i];
    return null;
  }
  
  nodeHashTable.size = function()
  {
    let result = 0;
    for (var index in this.table)
      result += this.table[index].length;
    return result;
  }

  return nodeHashTable;
}
besogo.parseSgf = function(text)
{
  'use strict';
  var at = 0, // Current position
      ch = text.charAt(at); // Current character at position

  findOpenParens(); // Find beginning of game tree
  return parseTree(); // Parse game tree

  // Builds and throws an error
  function error(msg) {
      throw {
          name: "Syntax Error",
          message: msg,
          at: at,
          text: text
      };
  }

  // Advances text position by one
  function next(check) {
      if (check && check !== ch) { // Verify current character if param given
          error( "Expected '" + check + "' instead of '" + ch + "'");
      }
      at++;
      ch = text.charAt(at);
      return ch;
  }

  // Skips over whitespace until non-whitespace found
  function white() {
      while (ch && ch <= ' ') {
          next();
      }
  }

  // Skips all chars until '(' or end found
  function findOpenParens() {
      while (ch && ch !== '(') {
          next();
      }
  }

  // Returns true if line break (CR, LF, CR+LF, LF+CR) found
  // Advances the cursor ONCE for double character (CR+LF, LF+CR) line breaks
  function lineBreak() {
      if (ch === '\n') { // Line Feed (LF)
          if (text.charAt(at + 1) === '\r') { // LF+CR, double character line break
              next(); // Advance cursor only once (pointing at second character)
          }
          return true;
      } else if (ch === '\r') { // Carriage Return (CR)
          if (text.charAt(at + 1) === '\n') { // CR+LF, double character line break
              next(); // Advance cursor only once (pointing at second character)
          }
          return true;
      }
      return false; // Did not find a line break or advance
  }

  // Parses a sub-tree of the game record
  function parseTree() {
      var rootNode, // Root of this sub-tree
          currentNode, // Pointer to parent of the next node
          nextNode; // Scratch for parsing the next node or sub-tree

      next('('); // Double-check opening parens at start of sub-tree
      white(); // Skip whitespace before root node

      if (ch !== ";") { // Error on sub-tree missing root node
          error("Sub-tree missing root");
      }
      rootNode = parseNode(); // Get the first node of this sub-tree
      white(); // Skip whitespace before parsing next node

      currentNode = rootNode; // Parent of the next node parsed
      while (ch === ';') { // Get sequence of nodes within this sub-tree
          nextNode = parseNode(); // Parse the next node
          // Add next node as child of current
          currentNode.children.push(nextNode);
          currentNode = nextNode; // Advance current pointer to this child
          white(); // Skip whitespace between/after sequence nodes
      }

      // Look for sub-trees of this sub-tree
      while (ch === "(") {
          nextNode = parseTree(); // Parse the next sub-tree
          // Add sub-tree as child of last sequence node
          currentNode.children.push(nextNode); // Do NOT advance current
          white(); // Skip whitespace between/after sub-trees
      }
      next(')'); // Expect closing parenthesis at end of this sub-tree

      return rootNode;
  }

  // Parses a node and its properties
  function parseNode() {
      var property, // Scratch for parsing properties
          node = { props: [], children: [] }; // Node to construct

      next(';'); // Double-check semi-colon at start of node
      white(); // Skip whitespace before properties
      // Parse properties until end of node detected
      while ( ch && ch !== ';' && ch !== '(' && ch !== ')') {
          property = parseProperty(); // Parse the property and values
          node.props.push(property); // Add property to node
          white(); // Skip whitespace between/after properties
      }

      return node;
  }

  // Parses a property and its values
  function parseProperty() {
      var property = { id: '', values: [] }; // Property to construct

      // Look for property ID within letters
      while ( ch && /[A-Za-z]/.test(ch) ) {
          if (/[A-Z]/.test(ch)) { // Ignores lower case letters
              property.id += ch; // Only adds upper case letters
          }
          next();
      }
      if (!property.id) { // Error if id empty
          error('Missing property ID');
      }

      white(); // Skip whitespace before values
      while(ch === '[') { // Look for values of this property
          property.values.push( parseValue() );
          white(); // Skip whitespace between/after values
      }
      if (property.values.length === 0) { // Error on empty list of values
          error('Missing property values');
      }

      return property;
  }

  // Parses a value
  function parseValue() {
      var value = '';
      next('['); // Double-check opening bracket at start of value

      // Read until end of value (unescaped closing bracket)
      while ( ch && ch !== ']' ) {
          if ( ch === '\\' ) { // Backslash escape handling
              next('\\');
              if (lineBreak()) { // Soft (escaped) line break
                  // Nothing, soft line breaks are removed
              } else if (ch <= ' ') { // Other whitespace
                  value += ' '; // Convert to space
              } else {
                  value += ch; // Pass other escaped characters verbatim
              }
          } else { // Non-escaped character
              if (lineBreak()) { // Hard (non-escaped) line break
                  value += '\n'; // Convert all new lines to just LF
              } else if (ch <= ' ') { // Other whitespace
                  value += ' '; // Convert to space
              } else {
                  value += ch; // Other characters
              }
          }
          next();
      }
      next(']'); // Expect closing bracket at end of value

      return value;
  }
};
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
          if (tree.correct || !tree.virtualChildren[i].target.correct)
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

    let correctToSave;
    if (root.goal == GOAL_NONE)
      correctToSave = node.correctSource;
    else
      correctToSave = !node.hasChildIncludingVirtual() && node.correct;

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
const GOAL_NONE = 0;
const GOAL_KILL = 1;
const GOAL_LIVE = 2;

const STATUS_NONE = 0;
const STATUS_DEAD = 1;
const STATUS_KO = 2;
const STATUS_SEKI = 3;
const STATUS_ALIVE = 4;
const STATUS_ALIVE_NONE = 5;

besogo.makeStatusInternal = function(type)
{
  var status = [];
  status.type = type;
  if (type == STATUS_SEKI)
    status.sente = false;

  status.str = function()
  {
    if (this.type == STATUS_DEAD)
      return "DEAD";
    if (this.type == STATUS_KO)
      return result = this.getKoApproachesStr() + 'KO' + this.getKoStr();

    if (this.type == STATUS_SEKI)
    {
      return "SEKI" + (this.sente ? '+' : '');
    }
    if (this.type == STATUS_ALIVE)
      return "ALIVE";
  }

  status.getApproachCount = function()
  {
    if (!this.approaches)
      return 0;
    return this.approaches;
  }

  status.strLong = function()
  {
    if (this.type == STATUS_KO)
      return result = this.str() + ' (' + this.getKoApproachStrLong() + this.getKoStrLong() + ')';
    if (this.type == STATUS_SEKI)
      return this.str() + (this.sente ? " in sente" : " in gote");
    return this.str();
  }

  status.getKoApproachesStr = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.approaches || this.approaches == 0)
      return '';

    let result = '';
    if (this.approaches > 0)
      result += "A+";
    else
      result += "A-";

    if (this.approaches > 0)
      result += this.approaches;
    else if (this.approaches < 0)
      result += -this.approaches;
    return result;
  }

  status.getKoStr = function()
  {
    console.assert(this.type == STATUS_KO);
    let result = '';
    if (!this.extraThreats || this.extraThreats >= 0)
      result += "+";
    else
      result += "-";

    if (this.extraThreats > 0)
      result += (this.extraThreats + 1)
    else if (this.extraThreats < -1)
      result += -this.extraThreats;
    return result;
  }

  status.getKoApproachStrLong = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.approaches || this.approaches == 0)
      return '';
    if (this.approaches > 0)
      return 'White needs to do ' + this.approaches + ' approach move' + (this.approaches > 1 ? 's' : '') + ' to start a direct ko, ';
    if (this.approaches < 0)
      return 'Black needs to do ' + -this.approaches + ' approach move' + (this.approaches < -1 ? 's' : '') + ' to start a direct ko, ';
  }

  status.getKoStrLong = function()
  {
    console.assert(this.type == STATUS_KO);
    if (!this.extraThreats || this.extraThreats == 0)
      return 'Black takes first';
    if (this.extraThreats == -1)
      return 'White takes first';
    if (this.extraThreats > 0)
      return 'White needs ' + this.extraThreats + ' threat' + (this.extraThreats > 1 ? 's' : '') + ' to start the ko';
    if (this.extraThreats < 0)
      return 'Black needs ' + (-this.extraThreats - 1) + ' threat' + (this.extraThreats < -2 ? 's' : '') + ' to start the ko';
  }

  status.setKo = function(extraThreats)
  {
    this.type = STATUS_KO;
    this.extraThreats = extraThreats;
  }

  status.setApproachKo = function(approaches, extraThreats = 0)
  {
    this.type = STATUS_KO;
    this.approaches = approaches;
    this.extraThreats = extraThreats;
  }

  status.setSeki = function(sente)
  {
    this.type = STATUS_SEKI;
    this.sente = sente;
  }

  status.better = function(other, goal)
  {
    if (this.type != other.type)
      return goal == GOAL_KILL ? (this.type < other.type) : (this.type > other.type);
    if (this.type == STATUS_KO)
      if (this.approaches != other.approaches)
        return this.approaches > other.approaches;
      else
        return this.extraThreats > other.extraThreats;
    if (this.type == STATUS_SEKI)
      return this.sente && !other.sente;
    return false;
  }
  return status;
}

besogo.makeStatusSimple = function(blackFirstType)
{
  return besogo.makeStatus(besogo.makeStatusInternal(blackFirstType));
}

besogo.loadStatusFromString = function(str)
{
  var status = [];
  var parts = str.split('/');
  if (parts.length == 1)
    return besogo.makeStatus(besogo.loadStatusInternalFromString(str));
  return besogo.makeStatus(besogo.loadStatusInternalFromString(parts[0]),
                           besogo.loadStatusInternalFromString(parts[1]));
}

besogo.loadGoalFromString = function(str)
{
  if (str == "KILL")
    return GOAL_KILL;
  if (str == "LIVE")
    return GOAL_LIVE;
  return GOAL_NONE;
}

besogo.goalStr = function(goal)
{
  if (goal == GOAL_KILL)
    return "KILL";
  if (goal == GOAL_LIVE)
    return "LIVE";
  return '';
}

besogo.loadStatusInternalFromString = function(str)
{
  if (str == "DEAD")
    return besogo.makeStatusInternal(STATUS_DEAD);
  if (str == "SEKI")
    return besogo.makeStatusInternal(STATUS_SEKI);
  if (str == "SEKI+")
  {
    let status = besogo.makeStatusInternal(STATUS_SEKI);
    status.setSeki(true);
    return status;
  }
  if (str == "ALIVE")
    return besogo.makeStatusInternal(STATUS_ALIVE);

  var approaches = 0;
  if (str[0] == "A" && (str[1] == "+" || str[1] == "-"))
  {
    let i = 2;
    while (!isNaN(parseInt(str[i])))
    {
      approaches *= 10;
      approaches += parseInt(str[i]);
      ++i;
    }
    if (str[1] == "-")
      approaches = -approaches;
    str = str.substr(i, str.length - i);
  }

  if (str.length >= 2 && str[0] == "K" && str[1] == "O")
  {
    let result = besogo.makeStatusInternal(STATUS_KO);
    result.approaches = approaches;
    if (str.length == 2)
      return result;
    if (str[2] == "+")
    {
      if (str.length == 3)
      {
        result.extraThreats = 0;
        return result;
      }
      let number = Number(str.substr(3, str.length - 3));
      result.extraThreats = number - 1;
      return result;
    }
    if (str[2] == "-")
    {
      if (str.length == 3)
      {
        result.extraThreats = -1;
        return result;
      }
      let number = Number(str.substr(3, str.length - 3));
      result.extraThreats = -number;
      return result;
    }
  }
}

besogo.makeStatus = function(blackFirst = null, whiteFirst = null)
{
  var status = [];
  status.blackFirst = blackFirst ? blackFirst : besogo.makeStatusInternal(STATUS_NONE);
  status.whiteFirst = whiteFirst ? whiteFirst : besogo.makeStatusInternal(STATUS_ALIVE);

  status.str = function()
  {
    var result = "";
    if (this.whiteFirst.type != STATUS_ALIVE)
    {
      result += this.whiteFirst.str();
      result += "/";
    }
    result += this.blackFirst.str();
    return result;
  }

  status.strLong = function()
  {
    var result = "";
    if (this.whiteFirst.type != STATUS_ALIVE)
    {
      result += this.whiteFirst.strLong();
      result += "/";
    }
    result += this.blackFirst.strLong();
    return result;
  }

  status.better = function(other, goal = GOAL_KILL)
  {
    if (this.blackFirst.type == STATUS_NONE)
      return false;
    return this.blackFirst.better(other.blackFirst, goal);
  }

  status.setKo = function(extraThreats)
  {
    this.blackFirst.setKo(extraThreats);
  }

  status.setApproachKo = function(approaches, extraThreats = 0)
  {
    this.blackFirst.setApproachKo(approaches, extraThreats);
  }

  status.setSeki = function(sente)
  {
    this.blackFirst.setSeki(sente);
  }

  return status;
}
besogo.addTest("Status", "None", function()
{
  let status = besogo.makeStatusSimple(STATUS_NONE);
  CHECK_EQUALS(status.blackFirst.type, STATUS_NONE);
});

besogo.addTest("Status", "StatusKoThreatsSimple", function()
{
  let status1 = besogo.makeStatus();
  status1.setKo(0);
  CHECK_EQUALS(status1.str(), "KO+");

  let status2 = besogo.makeStatus();
  status2.setKo(-1);
  CHECK_EQUALS(status2.str(), "KO-");

  // regarldess of goal, ko takes first is better
  CHECK(status1.better(status2, GOAL_KILL));
  CHECK(!status2.better(status1, GOAL_KILL));

  CHECK(status1.better(status2, GOAL_LIVE));
  CHECK(!status2.better(status1, GOAL_LIVE));
});

besogo.addTest("Status", "StatusKoThreatsHigher", function()
{
  let status1 = besogo.makeStatus();
  status1.setKo(1);
  CHECK_EQUALS(status1.str(), "KO+2");

  let status2 = besogo.makeStatus();
  status2.setKo(0);
  CHECK_EQUALS(status2.str(), "KO+");

  CHECK(status1.better(status2));
  CHECK(!status2.better(status1));
});

besogo.addTest("Status", "StatusKoThreatsSaveLoad", function()
{
  for (let extraThreats = -3; extraThreats < 4; ++extraThreats)
  {
    let status1 = besogo.makeStatus();
    status1.setKo(extraThreats);
    let str = status1.str();
    let status2 = besogo.loadStatusFromString(str);
    CHECK_EQUALS(status2.blackFirst.extraThreats, extraThreats);
  }
});

besogo.addTest("Status", "SaveLoadKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  let str = status.str();
  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_KO);
});

besogo.addTest("Status", "DeadBetterThanko", function()
{
  let status1 = besogo.makeStatusSimple(STATUS_DEAD);
  let status2 = besogo.makeStatusSimple(STATUS_KO);

  // when goal is to kill, dead is better
  CHECK(status1.better(status2, GOAL_KILL));
  CHECK(!status2.better(status1, GOAL_KILL));

  // when goal is to live, dead is worse
  CHECK(!status1.better(status2, GOAL_LIVE));
  CHECK(status2.better(status1, GOAL_LIVE));
});

besogo.addTest("Status", "ApproachKoGood", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(1);
  CHECK_EQUALS(status.str(), "A+1KO+");
});

besogo.addTest("Status", "ApproachKoBad", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1);
  CHECK_EQUALS(status.str(), "A-1KO+");
});

besogo.addTest("Status", "ApproachKoGoodBetterThanApproachKoBad", function()
{
  let statusBad = besogo.makeStatusSimple(STATUS_KO);
  statusBad.setApproachKo(-1);
  let statusGood = besogo.makeStatusSimple(STATUS_KO);
  statusGood.setApproachKo(1);
  CHECK(statusGood.better(statusBad));
  CHECK(!statusBad.better(statusGood));
});

besogo.addTest("Status", "SaveLoadApproachKo", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1);
  CHECK_EQUALS(status.str(), "A-1KO+");

  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "A-1KO+");
  CHECK_EQUALS(statusLoaded.blackFirst.approaches, -1);
  CHECK_EQUALS(statusLoaded.blackFirst.extraThreats, 0);
});

besogo.addTest("Status", "SaveLoadApproachKoWithNegativeExtraThreats", function()
{
  let status = besogo.makeStatusSimple(STATUS_KO);
  status.setApproachKo(-1, -1);
  CHECK_EQUALS(status.str(), "A-1KO-");

  let statusLoaded = besogo.loadStatusFromString(status.str());
  CHECK_EQUALS(statusLoaded.str(), "A-1KO-");
  CHECK_EQUALS(statusLoaded.blackFirst.approaches, -1);
  CHECK_EQUALS(statusLoaded.blackFirst.extraThreats, -1);
});

besogo.addTest("Status", "StatusSekiSimple", function()
{
  let status1 = besogo.makeStatus();
  status1.setSeki(false);
  CHECK_EQUALS(status1.str(), "SEKI");

  let status2 = besogo.makeStatus();
  status2.setSeki(true);
  CHECK_EQUALS(status2.str(), "SEKI+");

  CHECK(!status1.better(status2));
  CHECK(status2.better(status1));
});

besogo.addTest("Status", "SaveLoadSeki", function()
{
  let status = besogo.makeStatusSimple(STATUS_SEKI);
  let str = status.str();
  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_SEKI);
  CHECK(!status.blackFirst.sente);
});

besogo.addTest("Status", "SaveLoadSeki+", function()
{
  let status = besogo.makeStatusSimple(STATUS_SEKI);
  status.setSeki(true);
  let str = status.str();
  CHECK(str == 'SEKI+');

  let statusLoaded = besogo.loadStatusFromString(str);
  CHECK_EQUALS(status.blackFirst.type, STATUS_SEKI);
  CHECK(status.blackFirst.sente);
});

besogo.addTest("Status", "InitStatusOnLoadWithoutNone", function()
{
  let editor = besogo.makeEditor();
  let childDead = editor.getRoot().registerMove(1, 1);
  childDead.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));

  let childKo = editor.getRoot().registerMove(2, 1);
  childKo.setStatusSource(besogo.makeStatusSimple(STATUS_KO));

  CHECK_EQUALS(editor.getRoot().children.length, 2);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 2);
});

besogo.addTest("Status", "InitStatusOnLoad", function()
{
  let editor = besogo.makeEditor();
  let childDead = editor.getRoot().registerMove(1, 1);
  childDead.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));

  let childKo = editor.getRoot().registerMove(2, 1);
  childKo.setStatusSource(besogo.makeStatusSimple(STATUS_KO));

  let childNone = editor.getRoot().registerMove(3, 1);
  CHECK_EQUALS(editor.getRoot().children.length, 3);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 3);
});

besogo.addTest("Status", "SetStatusSourceOnNonLeaf", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  let childOfChild = child.registerMove(6, 6);
  CHECK(child.hasChildIncludingVirtual());
  CHECK(child.statusSource == null);
  child.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));
  CHECK(child.statusSource == null);
});

besogo.addTest("Status", "AddChildToNodeWithStatusSource", function()
{
  let root = besogo.makeGameRoot();
  let child = root.registerMove(5, 5);
  CHECK(!child.statusSource);
  child.setStatusSource(besogo.makeStatusSimple(STATUS_DEAD));
  CHECK(child.statusSource);
  child.registerMove(6, 6);
  CHECK(!child.statusSource);
});

besogo.addTest("Status", "LoadingSgfWithStatusSourceNotOnLeafGetsFixed", function()
{
  let editor = besogo.makeEditor();
  let root = editor.getRoot();
  let child = root.registerMove(5, 5);
  child.registerMove(6, 6);
  // setting it up "illegaly" to get to a wrong state.
  child.statusSource = besogo.makeStatusSimple(STATUS_DEAD);

  let editor2 = besogo.makeEditor();
  besogo.loadSgf(besogo.parseSgf(besogo.composeSgf(editor)), editor2);

  CHECK_EQUALS(editor2.getRoot().children.length, 1);
  CHECK(editor2.getRoot().children[0].statusSource == null);
});


besogo.addTest("Status", "EmptyGameRootHasStatus", function()
{
  let root = besogo.makeGameRoot();
  CHECK(root.status);
});
(function() {
'use strict';

// Color palette
besogo.RED  = '#be0119'; // Darker red (marked variant)
besogo.LRED = '#ff474c'; // Lighter red (auto-marked variant)
besogo.BLUE = '#0165fc'; // Bright blue (last move)
besogo.PURP = '#9a0eea'; // Red + blue (variant + last move)
besogo.GREY = '#929591'; // Between white and black
besogo.GOLD = '#dbb40c'; // Tool selection
besogo.TURQ = '#06c2ac'; // Turqoise (nav selection)

besogo.BLACK_STONES = 4; // Number of black stone images
besogo.WHITE_STONES = 11; // Number of white stone images

// Makes an SVG element with given name and attributes
besogo.svgEl = function(name, attributes) {
    var attr, // Scratch iteration variable
        element = document.createElementNS("http://www.w3.org/2000/svg", name);

    for ( attr in (attributes || {}) ) { // Add attributes if supplied
        if (attributes.hasOwnProperty(attr)) {
            element.setAttribute(attr, attributes[attr]);
        }
    }
    return element;
};

// Makes an SVG group for containing the shadow layer
besogo.svgShadowGroup = function() {
    var group = besogo.svgEl('g'),
        filter = besogo.svgEl('filter', { id: 'blur' }),
        blur = besogo.svgEl('feGaussianBlur', {
            in: 'SourceGraphic',
            stdDeviation: '2'
        });

    filter.appendChild(blur);
    group.appendChild(filter);
    return group;
};

// Makes a stone shadow
besogo.svgShadow = function(x, y) {
    return besogo.svgEl("circle", {
        cx: x,
        cy: y,
        r: 43,
        stroke: 'none',
        fill: 'black',
        opacity: 0.32,
        filter: 'url(#blur)'
    });
};

// Makes a photo realistic stone element
besogo.realStone = function(x, y, color, index) {
    var element;

    if (color < 0) {
        color = 'black' + (index % besogo.BLACK_STONES);
    } else {
        color = 'white' + (index % besogo.WHITE_STONES);
    }
    color = '/besogo/img/' + color + '.png';

    element =  besogo.svgEl("image", {
        x: (x - 44),
        y: (y - 44),
        height: 88,
        width: 88
    });
    element.setAttributeNS('http://www.w3.org/1999/xlink', 'href', color);

    return element;
};

// Makes a stone element
besogo.svgStone = function(x, y, color, radius = 42)
{
  var className = "besogo-svg-greyStone"; // Grey stone by default

  if (color === -1) // Black stone
    className = "besogo-svg-blackStone";
  else if (color === 1) // White stone
    className = "besogo-svg-whiteStone";

  return besogo.svgEl("circle",
  {
      cx: x,
      cy: y,
      r: radius,
      'class': className
  });
};

// Makes a circle at (x, y)
besogo.svgCircle = function(x, y, color, radius = 27, strokeWidth = 8)
{
  return besogo.svgEl("circle", {
      cx: x,
      cy: y,
      r: radius,
      stroke: color,
      "stroke-width": strokeWidth,
      fill: "none"
  });
};

besogo.svgFilledCircle = function(x, y, color, radius = 27)
{
  return besogo.svgEl("circle", {
      cx: x,
      cy: y,
      r: radius,
      fill: color
  });
};

// Makes a square at (x, y)
besogo.svgSquare = function(x, y, color, strokeWidth = 8)
{
    return besogo.svgEl("rect",
      {
        x: (x - 23),
        y: (y - 23),
        width: 46,
        height: 46,
        stroke: color,
        "stroke-width": strokeWidth,
        fill: "none"
      });
};

// Makes an equilateral triangle at (x, y)
besogo.svgTriangle = function(x, y, color) {
    // Approximates an equilateral triangle centered on (x, y)
    var pointString = "" + x + "," + (y - 30) + " " +
        (x - 26) + "," + (y + 15) + " " +
        (x + 26) + "," + (y + 15);

    return besogo.svgEl("polygon", {
        points: pointString,
        stroke: color,
        "stroke-width": 8,
        fill: "none"
    });
};

// Makes an "X" cross at (x, y)
besogo.svgCross = function(x, y, color) {
    var path = "m" + (x - 24) + "," + (y - 24) + "l48,48m0,-48l-48,48";

    return besogo.svgEl("path", {
        d: path,
        stroke: color,
        "stroke-width": 8,
        fill: "none"
    });
};

// Makes an "+" plus sign at (x, y)
besogo.svgPlus = function(x, y, color) {
    var path = "m" + x + "," + (y - 28) + "v56m-28,-28h56";

    return besogo.svgEl("path", {
        d: path,
        stroke: color,
        "stroke-width": 8,
        fill: "none"
    });
};

// Makes a small filled square at (x, y)
besogo.svgBlock = function(x, y, color) {
    return besogo.svgEl("rect", {
        x: x - 18,
        y: y - 18,
        width: 36,
        height: 36,
        stroke: "none",
        "stroke-width": 8,
        fill: color
    });
};

// Makes a label at (x, y)
besogo.svgLabel = function(x, y, color, label, size = null) {
    var element;

    // Trims label to 3 characters
    if (label.length > 6)
        label = label.slice(0, 2) + '…';

    // Set font size according to label length
    if (!size)
      switch(label.length)
      {
        case 1:
          size = 72;
          break;
        case 2:
          size = 56;
          break;
        case 3:
          size = 36;
          break;
        default:
          size = 20;
          break;
      }

    element = besogo.svgEl("text", {
        x: x,
        y: y,
        dy: ".65ex", // Seems to work for vertically centering these fonts
        "font-size": size,
        "text-anchor": "middle", // Horizontal centering
        "font-family": "Helvetica, Arial, sans-serif",
        fill: color
    });
    element.appendChild( document.createTextNode(label) );

    return element;
};

})(); // END closure
besogo.addTest = function(suite, name, method)
{
  if (besogo.tests == null)
    besogo.tests = [];
  var test = [];
  test.name = name;
  test.suite = suite;
  test.method = method;
  besogo.tests.push(test);
};

CHECK = function(A)
{
  if (A)
    return;
  let error = [];
  error.message = "Check failed";
  error.stack = console.trace();
  throw error;
}

CHECK_EQUALS = function(A, B)
{
  if (A == B)
    return;
  let error = [];
  error.message = "Expected " + B + " but was " + A;
  error.stack = console.trace();
  throw error;
}
besogo.makeToolPanel = function(container, editor)
{
  'use strict';
  var element, // Scratch for building SVG images
      svg, // Scratch for building SVG images
      labelText, // Text area for next label input
      selectors = {}; // Holds selection rects
	var reviewMode = false;
	console.log(container);
  if(container.className!=='besogo-tool2'){  
	  svg = makeButtonSVG('auto', 'Auto-play/navigate\n' +
		  'crtl+click to force ko, suicide, overwrite\n' +
		  'shift+click to jump to move'); // Auto-play/nav tool button
	  svg.appendChild(makeYinYang(0, 0));

	  svg = makeButtonSVG('addB', 'Set black\nshift+click addWhite\nctrl+click to play'); // Add black button
	  element = besogo.svgEl('g');
	  element.appendChild(besogo.svgStone(-15, -15, -1, 15)); // Black stone
	  element.appendChild(besogo.svgStone(15, -15, 1, 15)); // White stone
	  element.appendChild(besogo.svgStone(-15, 15, 1, 15)); // White stone
	  element.appendChild(besogo.svgStone(15, 15, -1, 15)); // Black stone
	  svg.appendChild(element);
	  
	  svg = makeButtonSVG('circle', 'Circle'); // Circle markup button
	  svg.appendChild(besogo.svgCircle(0, 0, 'black'));
	  
	  svg = makeButtonSVG('square', 'Square'); // Square markup button
	  svg.appendChild(besogo.svgSquare(0, 0, 'black'));
	  
	  svg = makeButtonSVG('triangle', 'Triangle'); // Triangle markup button
	  svg.appendChild(besogo.svgTriangle(0, 0, 'black'));

	  svg = makeButtonSVG('cross', 'Cross'); // Cross markup button
	  svg.appendChild(besogo.svgCross(0, 0, 'black'));

	  svg = makeButtonSVG('block', 'Block'); // Block markup button
	  svg.appendChild(besogo.svgBlock(0, 0, 'black'));

	  svg = makeButtonSVG('clrMark', 'Clear mark'); // Clear markup button
	  element = besogo.svgEl('g');
	  element.appendChild(besogo.svgTriangle(0, 0, besogo.GREY));
	  element.appendChild(besogo.svgCross(0, 0, besogo.RED));
	  svg.appendChild(element);

	  svg = makeButtonSVG('label', 'Label'); // Label markup button
	  svg.appendChild(besogo.svgLabel(0, 0, 'black', 'A1'));

	  labelText = document.createElement("input"); // Label entry text field
	  labelText.type = "text";
	  labelText.title = 'Next label';
	  labelText.onblur = function() { editor.setLabel(labelText.value); };
	  labelText.addEventListener('keydown', function(evt)
	  {
		evt = evt || window.event;
		evt.stopPropagation(); // Stop keydown propagation when in focus
	  });
	  container.appendChild(labelText);

	  makeButtonText('Pass', 'Pass move', function()
	  {
		var tool = editor.getTool();
		if (tool !== 'navOnly' && tool !== 'auto')
		  editor.setTool('auto'); // Ensures that a move tool is selected
		editor.click(0, 0, false); // Clicking off the board signals a pass
	  });

	  makeButtonText('Raise', 'Raise variation', function() { editor.promote(); });
	  makeButtonText('Lower', 'Lower variation', function() { editor.demote(); });
	  makeButtonText('Cut', 'Remove branch', function() { editor.cutCurrent(); });
	  makeButtonText('H Flip', 'Flip horizontally', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.hFlip = true;
		editor.applyTransformation(transformation);
	  });
	  makeButtonText('V Flip', 'Flip vertically', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.vFlip = true;
		editor.applyTransformation(transformation);
	  });

	  makeButtonText('Rotate', 'Rotate the board clockwise', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.rotate = true;
		editor.applyTransformation(transformation);
	  });

	  makeButtonText('Invert', 'Invert colors of all stones and moves.', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.invertColors = true;
		editor.applyTransformation(transformation);
	  });
	  
	  makeButtonText('Invert firstMove', 'Invert the color of the first move', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.invertColors = true;
		editor.getRoot().firstMove = transformation.applyOnColor(editor.getRoot().firstMove);
		editor.notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
		editor.edited = true;
	  });

  
  }else{
	  makeButtonText('Invert', 'Invert colors of all stones and moves.', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.invertColors = true;
		editor.applyTransformation(transformation);
		
	  });
	  makeButtonText('Rotate', 'Rotate the board clockwise', function()
	  {
		let transformation = besogo.makeTransformation();
		transformation.rotate = true;
		editor.applyTransformation(transformation);
	  });
	  makeButtonText('Back', 'Previous problem', function()
	  {
		window.location.href = "/tsumegos/play/"+prevButtonLink;
	  });
	  makeButtonText('Reset', 'Resets the problem', function()
	  {
		editor.prevNode(-1);
		toggleBoardLock(false);
		reviewEnabled2 = false;
		document.getElementById("status").innerHTML = "";
		document.getElementById("theComment").style.cssText = "display:none;";
		$(".besogo-panels").css("display","none");
		$(".besogo-board").css("margin","0 315px");
	  });
	  makeButtonText('Next', 'Next problem', function()
	  {
		window.location.href = "/tsumegos/play/"+nextButtonLink;
	  });
	  makeButtonText('Review', 'Review mode', function()
	  {
		if(reviewEnabled){
			if(!reviewMode){
				$(".besogo-panels").css("display","flex");
				$(".besogo-board").css("margin","0");
				toggleBoardLock(false);
				deleteNextMoveGroup = true;
			}else{
				$(".besogo-panels").css("display","none");
				$(".besogo-board").css("margin","0 315px");
				deleteNextMoveGroup = false;
			}
			reviewMode = !reviewMode;
			reviewEnabled2 = !reviewEnabled2;
			editor.notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
		}
	  });
  }
  
  editor.addListener(toolStateUpdate); // Set up listener for tool state updates
  toolStateUpdate({ label: editor.getLabel(), tool: editor.getTool(), tool2: editor.getTool() }); // Initialize
  // Creates a button holding an SVG image
  function makeButtonSVG(tool, tooltip)
  {
    var button = document.createElement('button'),
        svg = besogo.svgEl('svg', { // Icon container
            width: '100%',
            height: '100%',
            viewBox: '-55 -55 110 110' }), // Centered on (0, 0)
        selected = besogo.svgEl("rect", { // Selection rectangle
            x: -50, // Center on (0, 0)
            y: -50,
            width: 100,
            height: 100,
            fill: 'none',
            'stroke-width': 8,
            stroke: besogo.GOLD,
            rx: 20, // Rounded rectangle
            ry: 20, // Thanks, Steve
            visibility: 'hidden'
        });

    container.appendChild(button);
    button.appendChild(svg);
    button.onclick = function()
    {
      if (tool === 'auto' && editor.getTool() === 'auto')
          editor.setTool('navOnly');
      else
          editor.setTool(tool);
    };
    button.title = tooltip;
    selectors[tool] = selected;
    svg.appendChild(selected);
    return svg; // Returns reference to the icon container
  }

  // Creates text button
  function makeButtonText(text, tip, callback)
  {
    var button = document.createElement('input');
    button.type = 'button';
    button.value = text;
    button.title = tip;
    button.onclick = callback;
    container.appendChild(button);
  }

  // Callback for updating tool state and label
  function toolStateUpdate(msg)
  {
    if (msg.label)
      labelText.value = msg.label;
    if (msg.tool)
      for (let tool in selectors) // Update which tool is selected
        if (selectors.hasOwnProperty(tool))
          if (msg.tool === tool)
            selectors[tool].setAttribute('visibility', 'visible');
          else
            selectors[tool].setAttribute('visibility', 'hidden');
  }

  // Draws a yin yang
  function makeYinYang(x, y) {
      var element = besogo.svgEl('g');

      // Draw black half circle on right side
      element.appendChild( besogo.svgEl("path", {
          d: "m" + x + "," + (y - 44) + " a44 44 0 0 1 0,88z",
          stroke: "none",
          fill: "black"
      }));

      // Draw white part of ying yang on left side
      element.appendChild( besogo.svgEl("path", {
          d: "m" + x + "," + (y + 44) + "a44 44 0 0 1 0,-88a22 22 0 0 1 0,44z",
          stroke: "none",
          fill: "white"
      }));

      // Draw round part of black half of ying yang
      element.appendChild( besogo.svgEl("circle", {
          cx: x,
          cy: y + 22,
          r: 22,
          stroke: "none",
          fill: "black"
      }));

      return element;
  }
};
besogo.makeTransformation = function()
{
  var transformation = [];

  transformation.hFlip = false;
  transformation.vFlip = false;
  transformation.rotate = false;
  transformation.invertColors = false;

  transformation.apply = function(position, size)
  {
    let result = [];
    result.x = position.x;
    result.y = position.y;
    if (this.hFlip)
      result.x = size.x - position.x + 1;
    if (this.vFlip)
      result.y = size.y - position.y + 1;
    if (this.rotate)
    {
      [result.x, result.y] = [size.x - result.y + 1, result.x];
    }
    return result;
  }

  transformation.applyOnColor = function(color)
  {
    if (this.invertColors)
      return -color;
    return color;
  }

  return transformation;
}
besogo.makeTreePanel = function(container, editor) {
  'use strict';
  var svg,
      pathGroup,
      bottomLayer,
      currentMarker,
      SCALE = 0.25; // Tree size scaling factor

  rebuildNavTree();
  editor.addListener(treeUpdate);


  // Callback for handling tree changes
  function treeUpdate(msg)
  {
    if (msg.treeChange) // Tree structure changed
      rebuildNavTree(); // Rebuild entire tree
    else if (msg.navChange) // Only navigation changed
      updateCurrentMarker(); // Update current location marker
    else if (msg.stoneChange) // Only stones in current changed
      updateCurrentNodeIcon();
  }

  // Updates the current marker in the tree
  function updateCurrentMarker()
  {
    let current = editor.getCurrent();
    setSelectionMarker(currentMarker);
    setCurrentMarker(current.navTreeMarker);
  }

  // Sets marker element to indicate the current node
  function setCurrentMarker(marker)
  {
    var width = container.clientWidth,
        height = container.clientHeight,
        top = container.scrollTop,
        left = container.scrollLeft,
        markX = (marker.getAttribute('x') - 5) * SCALE, // Computed position of marker
        markY = (marker.getAttribute('y') - 5) * SCALE,
        GRIDSIZE = 120 * SCALE; // Size of the square grid

    if (markX < left) // Ensure horizontal visibility of current marker
      container.scrollLeft = markX;
    else if (markX + GRIDSIZE > left + width)
      container.scrollLeft = markX + GRIDSIZE - width;
    if (markY < top) // Ensure vertical visibility of current marker
      container.scrollTop = markY;
    else if (markY + GRIDSIZE > top + height)
      container.scrollTop = markY + GRIDSIZE - height;

    marker.setAttribute('opacity', 1); // Always visible
    marker.onmouseover = null; // Clear hover over action
    marker.onmouseout = null; // Clear hover off action
    bottomLayer.appendChild(marker); // Moves marker to the background
    currentMarker = marker;
  }

  // Sets marker
  function setSelectionMarker(marker)
  {
    marker.setAttribute('opacity', 0); // Normally invisible
    marker.onmouseover = function() { marker.setAttribute('opacity', 0.5); }; // Show on hover over
    marker.onmouseout = function() { marker.setAttribute('opacity', 0); }; // Hide on hover off
    svg.appendChild(marker); // Move marker to foreground
  }

  // Rebuilds the entire navigation tree
  function rebuildNavTree()
  {
    var current = editor.getCurrent(), // Current location in game state tree
        root = editor.getRoot(), // Root node of game state
        nextOpen = [], // Tracks occupied grid positions
        oldSvg = svg, // Store the old SVG root
        background = besogo.svgEl("rect", { // Background color for tree
            height: '100%',
            width: '100%',
            'class': 'besogo-svg-board besogo-svg-backer'
        }),
        path, // Root path
        width, // Calculated dimensions of the SVG
        height;

    svg = besogo.svgEl("svg");
    bottomLayer = besogo.svgEl("g"); // Holder for the current marker
    pathGroup = besogo.svgEl("g"); // Holder for path elements

    svg.appendChild(background); // Background color first
    svg.appendChild(bottomLayer); // Bottom layer (for current marker) second
    svg.appendChild(pathGroup); // Navigation path third

    path = recursiveTreeBuild(root, 0, 0, nextOpen); // Build the tree
    pathGroup.appendChild(finishPath(path, 'black')); // Finish and add root path

    width = 120 * nextOpen.length; // Compute height and width of nav tree
    height = 120 * Math.max.apply(Math, nextOpen);
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.setAttribute('height', height * SCALE); // Scale down the actual SVG size
    svg.setAttribute('width', width * SCALE);

    if (oldSvg) // Replace SVG in container
      container.replaceChild(svg, oldSvg);
    else // SVG not yet added to container
      container.appendChild(svg);

    setCurrentMarker(current.navTreeMarker); // Set current marker and ensure visible
  }

  // Recursively builds the tree
  function recursiveTreeBuild(node, x, y, nextOpen)
  {
    var children = node.children,
        position,
        path,
        childPath,
        i; // Scratch iteration variable

    if (children.length === 0) // Reached end of branch
      path = 'm' + svgPos(x) + ',' + svgPos(y); // Start path at end of branch
    else  // Current node has children
    {
      position = (nextOpen[x + 1] || 0); // First open spot in next column
      position = (position < y) ? y : position; // Bring level with current y

      if (y < position - 1) // Check if first child natural drop > 1
        y = position - 1; // Bring current y within 1 of first child drop
      // Place first child and extend path
      path = recursiveTreeBuild(children[0], x + 1, position, nextOpen) +
             extendPath(x, y, nextOpen);

      // Place other children (intentionally starting at i = 1)
      for (i = 1; i < children.length; i++)
      {
        position = nextOpen[x + 1];
        childPath = recursiveTreeBuild(children[i], x + 1, position, nextOpen) +
            extendPath(x, y, nextOpen, position - 1);
        // End path at beginning of branch
        pathGroup.appendChild(finishPath(childPath, 'black'));
      }
    }
    svg.appendChild(makeNodeIcon(node, x, y));
    addSelectionMarker(node, x, y);

    nextOpen[x] = y + 1; // Claims (x, y)
    return path;
  }

  function makeNodeIcon(node, x, y) // Makes a node icon for the tree
  {
    var element;

    switch(node.getType())
    {
      case 'move': // Move node
        let color = node.move.color;
        element = besogo.svgEl("g");
        element.appendChild( besogo.svgStone(svgPos(x), svgPos(y), color) );
        color = (color === -1) ? "white" : "black";
        if (node.virtualChildren.length)
          element.appendChild(besogo.svgPlus(svgPos(x), svgPos(y), color));
        else
          element.appendChild( besogo.svgLabel(svgPos(x), svgPos(y), color, '' + node.moveNumber) );
        element.appendChild(besogo.svgCircle(svgPos(x), svgPos(y), node.getCorrectColor(), 50))
        break;
      case 'setup': // Setup node
        element = besogo.svgEl("g");
        element.appendChild(besogo.svgStone(svgPos(x), svgPos(y))); // Grey stone
        element.appendChild(besogo.svgPlus(svgPos(x), svgPos(y), besogo.RED));
        break;
      default: // Empty node
        element = besogo.svgStone(svgPos(x), svgPos(y)); // Grey stone
    }
    node.navTreeIcon = element; // Save icon reference in game state tree
    node.navTreeX = x; // Save position of the icon
    node.navTreeY = y;

    return element;
  }

  function updateCurrentNodeIcon() // Updates the current node icon
  {
    let current = editor.getCurrent();
    let oldIcon = current.navTreeIcon;
    let newIcon = makeNodeIcon(current, current.navTreeX, current.navTreeY);
    svg.replaceChild(newIcon, oldIcon);
  }

  function addSelectionMarker(node, x, y)
  {
    var element = besogo.svgEl("rect", { // Create selection marker
        x: svgPos(x) - 55,
        y: svgPos(y) - 55,
        width: 110,
        height: 110,
        fill: besogo.TURQ
    });
    element.onclick = function() { editor.setCurrent(node); };

    node.navTreeMarker = element; // Save selection marker in node
    setSelectionMarker(element); // Add as and set selection marker properties
  }

  function extendPath(x, y, nextOpen, prevChildPos) // Extends path from child to current
  {
    var childPos = nextOpen[x + 1] - 1; // Position of child
    if (childPos === y) // Child is horizontally level with current
      return 'h-120'; // Horizontal line back to current
    else if (childPos === y + 1) // Child is one drop from current
      return 'l-120,-120'; // Diagonal drop line back to current
    else if (prevChildPos && prevChildPos !== y)
      // Previous is already dropped, extend back to previous child drop line
      return 'l-60,-60v-' + (120 * (childPos - prevChildPos));
    else // Extend double-bend drop line back to parent
      return 'l-60,-60v-' + (120 * (childPos - y - 1)) + 'l-60,-60';
  }

  function finishPath(path, color) // Finishes path element
  {
    var element = besogo.svgEl("path", {
        d: path,
        stroke: color,
        "stroke-width": 8,
        fill: "none"
    });
    return element;
  }

  function svgPos(x) // Converts (x, y) coordinates to SVG position
  {
    return (x * 120) + 60;
  }
};
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
  delete node.correct;
  node.status = null;
  if (node.hasChildIncludingVirtual())
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
}

besogo.updateStatusResult = function(solversMove, child, status, goal)
{
  if (child.status.better(status, goal) == solversMove)
    return child.status;
  else
    return status;
}

besogo.updateStatusValuesInternal = function(root, node, goal)
{
  if (node.statusSource)
  {
    console.assert(!node.hasChildIncludingVirtual());
    node.status = node.statusSource;
    return;
  }
  if (node.status)
    return;

  for (let i = 0; i < node.children.length; ++i)
    besogo.updateStatusValuesInternal(root, node.children[i], goal);
  for (let i = 0; i < node.virtualChildren.length; ++i)
    besogo.updateStatusValuesInternal(root, node.virtualChildren[i].target, goal);

  let solversMove = (node.nextMove() == root.firstMove);

  if (solversMove == (goal == GOAL_KILL))
    node.status = besogo.makeStatusSimple(STATUS_ALIVE_NONE);
  else
    node.status = besogo.makeStatusSimple(STATUS_NONE);

  for (let i = 0; i < node.children.length; ++i)
    node.status = besogo.updateStatusResult(solversMove, node.children[i], node.status, goal);
  for (let i = 0; i < node.virtualChildren.length; ++i)
    node.status = besogo.updateStatusResult(solversMove, node.virtualChildren[i].target, node.status, goal);

  if (node.status.blackFirst.type == STATUS_ALIVE_NONE)
    node.status = besogo.makeStatusSimple(STATUS_NONE);
}

besogo.updateCorrectValuesInternal = function(root, node)
{
  if (node.comment.startsWith("+"))
  {
    if (!node.correctSource)
    {
      node.correctSource = true;
      //node.comment = node.comment.substr(1);
    }
    node.correct = true;
    return true;
  }

  if (node.correctSource)
  {
    node.correct = true;
    return true;
  }

  if (node.hasOwnProperty("correct"))
    return node.correct;

  var hasLoss = false;
  var hasWin = false;

  for (let i = 0; i < node.children.length; ++i)
    if (besogo.updateCorrectValuesInternal(root, node.children[i]))
      hasWin = true;
    else
      hasLoss = true;

  for (let i = 0; i < node.virtualChildren.length; ++i)
    if (besogo.updateCorrectValuesInternal(root, node.virtualChildren[i].target))
      hasWin = true;
    else
      hasLoss = true;

  let solversMove = (node.nextMove() == root.firstMove);
  if (solversMove)
    node.correct = hasWin;
  else
    node.correct = hasWin && !hasLoss;

  return node.correct;
};

besogo.updateCorrectValuesBasedOnStatus = function(node, goal, parentStatus, isCorrectBranch)
{
  // lets just remove the extra + when we only care about status when determining correct variants (to avoid the + being accumulated)
  //if (node.comment.startsWith("+")) node.comment = node.comment.substr(1);

  if (node.hasOwnProperty("correct"))
    return;
  node.correct = isCorrectBranch && !parentStatus.better(node.status, goal);

  for (let i = 0; i < node.children.length; ++i)
    besogo.updateCorrectValuesBasedOnStatus(node.children[i], goal, node.status, node.correct)
};
