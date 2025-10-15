besogo.makeTreePanel = function(container, editor)
{
  'use strict';
  var svg,
      pathGroup,
      bottomLayer,
      currentMarker,
	  nodeCounter = 0,
	  nodesInitialized = false,
      SCALE = 0.25; // Tree size scaling factor

  if (editor.getReviewEnabled())
    rebuildNavTree();
  editor.addListener(treeUpdate);

  // Callback for handling tree changes
  function treeUpdate(msg)
  {
    if (!editor.getReviewMode())
      return;

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

    path = recursiveTreeBuild(root, 0, 0, nextOpen, root.isDiff); // Build the tree
	  nodesInitialized = true;
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
  function recursiveTreeBuild(node, x, y, nextOpen, isDiff)
  {
    var children = node.children,
        position,
        path,
        childPath,
        i; // Scratch iteration variable
	if(!nodesInitialized)
	{
		besogo.nodes.push(node);
		nodeCounter++;
	}

    if (children.length === 0) // Reached end of branch
      path = 'm' + svgPos(x) + ',' + svgPos(y); // Start path at end of branch
    else  // Current node has children
    {
      position = (nextOpen[x + 1] || 0); // First open spot in next column
      position = (position < y) ? y : position; // Bring level with current y

      if (y < position - 1) // Check if first child natural drop > 1
        y = position - 1; // Bring current y within 1 of first child drop
      // Place first child and extend path
      path = recursiveTreeBuild(children[0], x + 1, position, nextOpen, isDiff) + extendPath(x, y, nextOpen);

      // Place other children (intentionally starting at i = 1)
      for (i = 1; i < children.length; i++)
      {
        position = nextOpen[x + 1];
        childPath = recursiveTreeBuild(children[i], x + 1, position, nextOpen, isDiff) + extendPath(x, y, nextOpen, position - 1);
        // End path at beginning of branch
        pathGroup.appendChild(finishPath(childPath, 'black'));
      }
    }
    svg.appendChild(makeNodeIcon(node, x, y, isDiff));
    addSelectionMarker(node, x, y);
    nextOpen[x] = y + 1; // Claims (x, y)
    return path;
  }

  function makeNodeIcon(node, x, y, isDiff) // Makes a node icon for the tree
  {
    var element;

    switch(node.getType())
    {
      case 'move': // Move node
        let color = node.move.color;
        element = besogo.svgEl("g");
        element.appendChild(besogo.svgStone(svgPos(x), svgPos(y), color));
        color = (color === -1) ? "white" : "black";
        if (node.diffInfo && node.diffInfo.type != DIFF_NO_CHANGE)
          if (node.diffInfo.type == DIFF_ADDED)
            element.appendChild(besogo.svgPlus(svgPos(x), svgPos(y), color));
          else
            element.appendChild(besogo.svgCross(svgPos(x), svgPos(y), color));
        else if (!isDiff)
        {
          if (node.virtualChildren.length)
            element.appendChild(besogo.svgArrow(svgPos(x), svgPos(y), color));
          else
            element.appendChild(besogo.svgLabel(svgPos(x), svgPos(y), color, '' + node.moveNumber));
        }
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
    element.onclick = function() {
		editor.setCurrent(node);
	};
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
