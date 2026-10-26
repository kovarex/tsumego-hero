besogo.makeEditor = function (sizeX = 19, sizeY = 19, options = []) {
  // Creates an associated game state tree
  var root = besogo.makeGameRoot(sizeX, sizeY),
    current = root, // Navigation cursor
    listeners = [], // Listeners of general game/editor state changes
    // Enumeration of editor tools/modes
    TOOLS = [
      "navOnly", // read-only navigate mode
      "auto", // auto-mode: navigate or auto-play color
      "addB", // setup black stone
      "clrMark", // remove markup
      "circle", // circle markup
      "square", // square markup
      "triangle", // triangle markup
      "cross", // "X" cross markup
      "block", // filled square markup
      "label",
    ], // label markup
    tool = "auto", // Currently active tool (default: auto-mode)
    label = "1", // Next label that will be applied
    navHistory = [], // Navigation history
    gameInfo = {}, // Game info properties
    // Order of coordinate systems
    COORDS = "none numeric western eastern pierre corner eastcor".split(" "),
    coord = "none", // Selected coordinate system
    // Variant style: even/odd - children/siblings, <2 - show auto markup for variants
    variantStyle = 0, // 0-3, 0 is default
    edited = false,
    autoPlay = false,
    shift = false,
    reviewMode =
      typeof options.reviewMode === "boolean" ? options.reviewMode : true,
    performingAutoPlay = false,
    reviewEnabled =
      typeof options.reviewEnabled === "boolean" ? options.reviewEnabled : true,
    soundEnabled = false, //not used
    fullEditor = false, // when true, it enables the moves to be considered when calculating correct status, and these are also allowed to be played by autoplay
    // when false, the moves are blue and considered to be just explore moves by the review/testing when solving and don't affect autoplay nor correct calculations
    remainingRequiredNodes = [],
    appliedTransformations = [], // Transformations applied since the root was loaded, see recordTransformation
    anchoredCommentNodes = new Map(),
    displayResult = null,
    lineLost = null,
    showComment = null,
    addTimeForMovePlayed = null;

  return {
    addListener: addListener,
    removeListener: removeListener,
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
    applyTransformation: applyTransformation,
    setAutoPlay: setAutoPlay,
    getReviewMode: getReviewMode,
    setReviewMode: setReviewMode,
    getReviewEnabled: getReviewEnabled,
    setReviewEnabled: setReviewEnabled,
    setControlButtonLock: setControlButtonLock,
    intuitionHeroPower: intuitionHeroPower,
    isPerformingAutoPlay: isPerformingAutoPlay,
    setSoundEnabled: setSoundEnabled,
    registerDisplayResult: registerDisplayResult,
    registerLineLost: registerLineLost,
    registerAddTimeForMovePlayed: registerAddTimeForMovePlayed,
    resetToStart: resetToStart,
    registerShowComment: registerShowComment,
    displayHoverCoord: displayHoverCoord,
    displayHoverCoordAt: displayHoverCoordAt,
    coordLabelToXY: coordLabelToXY,
    resolveSequenceHover: resolveSequenceHover,
    coordXYToLabel: coordXYToLabel,
    findNodeForPosition: findNodeForPosition,
    showCommentPosition: showCommentPosition,
    makeCommentPosition: makeCommentPosition,
    sgfLabelToView: sgfLabelToView,
    viewLabelToSgf: viewLabelToSgf,
    isColorInverted: isColorInverted,
    getTransformationFromSgf: getTransformationFromSgf,
    getAnchoredCommentCount: getAnchoredCommentCount,
    setAnchoredComments: setAnchoredComments,
    setFullEditor: setFullEditor,
    displayError: displayError,
    rotateBoard: rotateBoard,
    autoRotate: autoRotate,
  };

  // Returns the active tool
  function getTool() {
    return tool;
  }

  function displayHoverCoord(c) {
    besogo.boardDisplay.displayHoverCoord(c);
  }

  // Sets the active tool, returns false if failed
  function setTool(set) {
    // Toggle label mode if already label tool already selected
    if (set === "label" && set === tool) {
      if (/^-?\d+$/.test(label))
        // If current label is integer
        setLabel("A"); // Toggle to characters
      else setLabel("1"); // Toggle back to numbers
      return true; // Notification already handled by setLabel
    }
    // Set the tool only if in list and actually changed
    if (TOOLS.indexOf(set) !== -1 && tool !== set) {
      tool = set;
      notifyListeners({ tool: tool, label: label }); // Notify tool change
      return true;
    }
    return false;
  }

  // Gets the next label to apply
  function getLabel() {
    return label;
  }

  // Sets the next label to apply and sets active tool to label
  function setLabel(set) {
    if (typeof set === "string") {
      set = set.replace(/\s/g, " ").trim(); // Convert all whitespace to space and trim
      label = set || "1"; // Default to "1" if empty string
      tool = "label"; // Also change current tool to label
      notifyListeners({ tool: tool, label: label }); // Notify tool/label change
    }
  }

  // Toggle the coordinate style
  function toggleCoordStyle() {
    coord = COORDS[(COORDS.indexOf(coord) + 1) % COORDS.length];
    notifyListeners({ coord: coord });
  }

  // Gets the current coordinate style
  function getCoordStyle() {
    return coord;
  }

  // Sets the coordinate system style
  function setCoordStyle(setCoord) {
    if (besogo.coord[setCoord]) {
      coord = setCoord;
      notifyListeners({ coord: setCoord });
    }
  }

  // Returns the variant style
  function getVariantStyle() {
    return variantStyle;
  }

  // Directly sets the variant style
  function setVariantStyle(style) {
    if (style === 0 || style === 1 || style === 2 || style === 3) {
      variantStyle = style;
      notifyListeners({ variantStyle: variantStyle, markupChange: true });
    }
  }

  function getGameInfo() {
    return gameInfo;
  }

  function setGameInfo(info, id) {
    if (id) gameInfo[id] = info;
    else gameInfo = info;
    notifyListeners({ gameInfo: gameInfo });
  }

  function setComment(text) {
    text = text.trim(); // Trim whitespace and standardize line breaks
    text = text
      .replace(/\r\n/g, "\n")
      .replace(/\n\r/g, "\n")
      .replace(/\r/g, "\n");
    text.replace(/\f\t\v\u0085\u00a0/g, " "); // Convert other whitespace to space
    current.comment = text;
    notifyListeners({ comment: text });
  }

  // Returns variants of the current node according to the set style
  function getVariants() {
    if (variantStyle >= 2)
      // Do not show variants if style >= 2
      return [];
    if (variantStyle === 1)
      // Display sibling variants
      // Root node does not have parent nor siblings
      return current.parent ? current.parent.children : [];
    return current.children; // Otherwise, style must be 0, display child variants
  }

  // Returns the currently active node in the game state tree
  function getCurrent() {
    return current;
  }

  // Returns the root of the game state tree
  function getRoot() {
    return root;
  }

  function loadRoot(load) {
    root = load;
    current = load;
    appliedTransformations = [];
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true, transformChange: true });
    edited = false;
  }

  // Navigates forward num nodes (to the end if num === -1)
  function nextNode(num, select = 0, autoPlay = false) {
    if (!current.hasChildDependingOnAutoPlay(autoPlay))
      // Check if no children
      return; // Do nothing if no children (avoid notification)
    // A virtual child can lead back to a position already passed (eternal life, superko), the walk stops
    // there instead of going around the cycle forever
    let passed = new Set([current]);
    while (current.hasChildDependingOnAutoPlay(autoPlay) && num !== 0) {
      if (navHistory.length)
        // Non-empty navigation history
        current = navHistory.pop();
      // Empty navigation history
      else {
        let next;
        if (autoPlay)
          next =
            current.selectNonLocalChildIncludingVirtualWithNoBetterStatus(
              select
            );
        else
          next =
            current.children.length != 0
              ? current.children[0]
              : current.virtualChildren[0];

        // The autoplay has no reply it may pick (the best one for the opponent is a local edit), so the
        // line ends here instead of leaving the solver without an answer
        if (!next) {
          finish(current);
          return;
        }

        // The opponent wants to auto play into a move we already visited, potentially forcing the solver to solve part of a variation already done
        // in this case, we just stop and call this sub-variation finished and move on.
        if (
          autoPlay &&
          ((next.target && next.target.visited) || next.visited)
        ) {
          finish(current);
          return;
        }

        if (passed.has(next.target ? next.target : next)) break;

        if (!next.target) {
          next.cameFrom = null;
          current = next; // Go to the selected child
        } else {
          next.target.cameFrom = current;
          current = next.target;
        }
      }
      passed.add(current);
      current.visited = true;
      num--;
    }
    reportIfLineIsLost(current);

    if (besogo.soundsEnabled && !reviewMode)
      document.getElementsByTagName("audio")[0].play();
    // Notify listeners of navigation (with no tree edits)
    notifyListeners({ navChange: true }, true); // Preserve history

    //opponent move and end of variant
    if (besogo.isEmbedded && !current.hasNonLocalChildIncludingVirtual()) {
      besogo.soundsEnabled = soundsEnabled;
      if (besogo.soundsEnabled && !reviewMode)
        document.getElementsByTagName("audio")[0].play();
      tryToFinish(current);
    }
  }

  // Navigates backward num nodes (to the root if num === -1)
  function prevNode(num) {
    if (!current.parent)
      // Check if root
      return; // Do nothing if already at root (avoid notification)
    // Following cameFrom back through a virtual child can come round to a node already passed (eternal life,
    // superko), the walk goes on to its parent then, so it ends at the root
    let passed = new Set([current]);
    while (current.parent && num !== 0) {
      navHistory.push(current); // Save current into navigation history
      if (current.cameFrom && !passed.has(current.cameFrom))
        current = current.cameFrom;
      else current = current.parent;
      passed.add(current);
      num--;
    }
    // Notify listeners of navigation (with no tree edits)
    notifyListeners({ navChange: true }, true); // Preserve history
  }

  // Cyclically switches through siblings
  function nextSibling(change) {
    var siblings,
      i = 0;

    if (current.parent) {
      siblings = current.parent.children;

      // Exit early if only child
      if (siblings.length === 1) return;

      // Find index of current amongst siblings
      i = siblings.indexOf(current);

      // Apply change cyclically
      i = (i + change) % siblings.length;
      if (i < 0) i += siblings.length;

      current = siblings[i];
      // Notify listeners of navigation (with no tree edits)
      notifyListeners({ navChange: true });
    }
  }

  // Return to the previous branch point
  function prevBranchPoint(change) {
    if (current.parent === null)
      // Check if root
      return; // Do nothing if already at root

    navHistory.push(current); // Save starting position in case we do not find a branch point

    while (current.parent && current.parent.children.length === 1)
      // Traverse backwards until we find a sibling
      current = current.parent;

    if (current.parent) {
      current = current.parent;
      notifyListeners({ navChange: true });
    } else current = navHistory.pop(current);
  }

  // Sets the current node
  function setCurrent(node) {
    if (current !== node) {
      current = node;
      // Notify listeners of navigation (with no tree edits)
      notifyListeners({ navChange: true });
    }
  }

  // Removes current branch from the tree
  function cutCurrent() {
    var parent = current.parent;
    if (tool === "navOnly") return; // Tree editing disabled in navOnly mode
    if (parent) {
      current.destroy();
      current = parent;
      besogo.updateCorrectValues(current.getRoot());
      // Notify navigation and tree edited
      notifyListeners({ treeChange: true, navChange: true });
    }
  }

  // Raises current variation to a higher precedence
  function promote() {
    if (tool === "navOnly") return; // Tree editing disabled in navOnly mode
    if (current.parent && current.parent.promote(current))
      notifyListeners({ treeChange: true }); // Notify tree edited
  }

  // Drops current variation to a lower precedence
  function demote() {
    if (tool === "navOnly") return; // Tree editing disabled in navOnly mode
    if (current.parent && current.parent.demote(current))
      notifyListeners({ treeChange: true }); // Notify tree edited
  }

  // Handle click with application of selected tool
  function click(i, j, ctrlKey, shiftKey) {
    if (!besogo.isEmbedded) boardLockValue = 0;
    if (boardLockValue == 0) {
      if (performingAutoPlay) return;
      switch (tool) {
        case "navOnly":
          navigate(i, j, shiftKey);
          break;
        case "auto":
          if (!navigate(i, j, shiftKey) && !shiftKey)
            // Try to navigate to (i, j)
            playMove(i, j, 0, ctrlKey); // Play auto-color move if navigate fails
          break;
        case "addB":
          if (ctrlKey) playMove(i, j, -1, true); // Play black
          else if (shiftKey) placeSetup(i, j, 1); // Set white
          else placeSetup(i, j, -1); // Set black
          break;
        case "clrMark":
          setMarkup(i, j, 0);
          break;
        case "circle":
          setMarkup(i, j, 1);
          break;
        case "square":
          setMarkup(i, j, 2);
          break;
        case "triangle":
          setMarkup(i, j, 3);
          break;
        case "cross":
          setMarkup(i, j, 4);
          break;
        case "block":
          setMarkup(i, j, 5);
          break;
        case "label":
          setMarkup(i, j, label);
          break;
      }
    }
	else if (boardLockValue == 1 && window.problemSolved && nextButtonLink)
        window.location.href = nextButtonLink; // Problem is solved, clicking navigates to next puzzle
  }

  function transformTextColors(root, text) {
    let result = text;
    if (root.firstMove != BLACK) {
      result = result.replaceAll("White", "Bily");
      result = result.replace("white", "bily");
      result = result.replace("Black", "White");
      result = result.replace("black", "white");
      result = result.replace("Bily", "Black");
      result = result.replace("bily", "black");
    }
    return result;
  }

  function tryToFinish(node) {
    // added node.localEdit to fix an issue where the autoplay stops when it is not in the tree but finds a virtual child
    if (!node.hasNonLocalChildIncludingVirtual() || node.localEdit)
      finish(node);
  }

  function finish(node) {
    if (reviewMode) return;
    if (!autoPlay) return;
    if (!displayResult) return;
    let timer;
    if (node.comment === "") timer = 720;
    else timer = 3000;
    if (
      !node.localEdit &&
      node.correct == CORRECT_GOOD &&
      navigateToRemainingRequiredIfNeeded(timer)
    )
      return;
    let success = !node.localEdit && node.correct == CORRECT_GOOD;
    if (
      !node.localEdit &&
      !success &&
      node.status &&
      showComment &&
      node.comment == ""
    ) {
      let root = node.getRoot();
      if (root.goal != GOAL_NONE && node.status.isNone())
        // the board editor refuses to save this, so it is an error in the problem (forum#111)
        displayMessage(
          "This line ends without a status, which is an error in the problem. Please report it in the comments.",
          "Error in the problem"
        );
      else if (
        root.goal != GOAL_NONE &&
        !root.status.isNone() &&
        node.status.blackFirst.type != STATUS_ALIVE && // when the current is clearly dead or alive, we don't say the obvious
        node.status.blackFirst.type != STATUS_DEAD
      )
        displayMessage(
          transformTextColors(
            root,
            "It is " +
              node.status.strLong() +
              ", but it should be: " +
              root.status.strLong()
          ),
          "Not the best solution"
        );
    }
    displayResult(success ? "S" : "F");
  }

  function navigateToNode(node, byClicking = false) {
    if (besogo.isEmbedded) besogo.soundsEnabled = soundsEnabled;

    if (byClicking && besogo.soundsEnabled && !reviewMode)
      document.getElementsByTagName("audio")[0].play();
    current = node; // Navigate to child if found
    current.visited = true;
	if (autoPlay &&
    	!reviewMode &&
		node.move.color == node.getRoot().firstMove &&
		node.hasNonLocalChildIncludingVirtual())
	{
		performingAutoPlay = true;
		if (addTimeForMovePlayed)
			addTimeForMovePlayed();
      setTimeout(function ()
      {
        // when autoplay was cancelled, it was reset in the meantime, so we forget about this
        if (!performingAutoPlay)
        	return;
        performingAutoPlay = false;
        if (!isMutable)
        	return;

        let selectOpponentMove = 0;
        {
          let selectionCount =
            current.countOfNonLocalChildrenIncludingVirtualWithNoBetterStatus();
          if (selectionCount > 1)
            selectOpponentMove = Math.floor(Math.random() * selectionCount);
        }

        //if alternative response mode is turned on
        if (besogo.alternativeResponse)
        {
          for (let i = 0; i < current.children.length; i++)
            if (i !== selectOpponentMove)
              addToRequired(current.children[i], current);

          if (current.children.length == 0)
            // alternative responses are taken from the virtual moves only if direct moves aren't there
            for (let i = 0; i < current.virtualChildren.length; i++)
              if (i + current.children.length !== selectOpponentMove)
                addToRequired(current.virtualChildren[i].target, current);
        }
        nextNode(1, selectOpponentMove, true /* autoplay move*/);
      }, 240);
    }
    notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
    reportIfLineIsLost(node);
    tryToFinish(node);
  }

  // Tells the page the moment the line can no longer end correct, long before it is played out and the result shown
  function reportIfLineIsLost(node) {
    if (reviewMode || !autoPlay || !lineLost) return;
    if (!node.canStillEndCorrect()) lineLost();
  }

  // Navigates to child with move at (x, y), searching tree if shift key pressed
  // Returns true is successful, false if not
  function navigate(x, y, shiftKey) {
    var children = current.children;
    // Look for move across children
    for (let i = 0; i < children.length; i++) {
      let move = children[i].move;
      if (move && move.x === x && move.y === y) {
        children[i].cameFrom = null;
        navigateToNode(children[i], true /* by clicking */);
        return true;
      }
    }

    if (current.virtualChildren)
      for (let i = 0; i < current.virtualChildren.length; i++) {
        var child = current.virtualChildren[i];
        let move = child.move;
        if (move.x === x && move.y === y) {
          child.target.cameFrom = current;
          navigateToNode(child.target, true /* by clicking */);
          return true;
        }
      }

    if (shiftKey && jumpToMove(x, y, root, current)) return true;
    return false;
  }

  // Recursive function for jumping to move with depth-first search
  function jumpToMove(x, y, start, end) {
    var i,
      move,
      children = start.children;

    if (end && end === start) return false;

    move = start.move;
    if (move && move.x === x && move.y === y) {
      current = start;
      notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
      return true;
    }

    for (i = 0; i < children.length; i++)
      if (jumpToMove(x, y, children[i], end)) return true;
    return false;
  }

  // Plays a move at the given color and location
  // Set allowAll to truthy to allow illegal moves
  function playMove(i, j, color, allowAll) {
    allowAll = false;
    if (besogo.isEmbedded) besogo.soundsEnabled = soundsEnabled;
    // Check if current node is immutable or root
    if (!current.isMutable("move") || !current.parent) {
      var next = current.makeChild(); // Create a new child node
      if (!fullEditor) next.localEdit = true;
      if (next.playMove(i, j, color, allowAll)) {
        // Play in new node
        // Keep (add to game state tree) only if move succeeds
        current.registerChild(next);
        current = next;
        // Notify tree change, navigation, and stone change
        notifyListeners({
          treeChange: true,
          navChange: true,
          stoneChange: true,
        });
        edited = true;

        if (besogo.isEmbedded) {
          if (besogo.soundsEnabled && !reviewMode)
            document.getElementsByTagName("audio")[0].play();
          tryToFinish(current);
        }
      }
      // Current node is mutable and not root
    } else if (current.playMove(i, j, color, allowAll)) {
      // Play in current
      // Only need to update if move succeeds
      current.registerInVirtualMoves();
      besogo.updateCorrectValues(current.getRoot());
      notifyListeners({ treeChange: true, stoneChange: true });
      edited = true;
    }
  }

  // Places a setup stone at the given color and location
  function placeSetup(i, j, color) {
    if (current.getStone(i, j))
      // Compare setup to current
      color = 0; // Same as current indicates removal desired
    else if (!color)
      // Color and current are both empty
      return; // No change if attempting to set empty to empty
    // Check if current node can accept setup stones
    if (!current.isMutable("setup")) {
      var next = current.makeChild(); // Create a new child node
      if (next.placeSetup(i, j, color)) {
        // Place setup stone in new node
        // Keep (add to game state tree) only if change occurs
        current.addChild(next);
        current = next;
        // Notify tree change, navigation, and stone change
        notifyListeners({
          treeChange: true,
          navChange: true,
          stoneChange: true,
        });
      }
    } else if (current.placeSetup(i, j, color))
      // Try setup in current
      // Only need to update if change occurs
      notifyListeners({ stoneChange: true }); // Stones changed
  }

  // Sets the markup at the given location and place
  function setMarkup(i, j, mark) {
    if (mark === current.getMarkup(i, j))
      if (mark !== 0)
        // Compare mark to current
        mark = 0; // Same as current indicates removal desired
      // Mark and current are both empty
      else return; // No change if attempting to set empty to empty

    if (current.addMarkup(i, j, mark)) {
      // Try to add the markup
      var temp; // For label incrementing
      if (typeof mark === "string") {
        // If markup is a label, increment the label
        if (/^-?\d+$/.test(mark)) {
          // Integer number label
          temp = +mark; // Convert to number
          // Increment and convert back to string
          setLabel("" + (temp + 1));
        } else if (/[A-Za-z]$/.test(mark)) {
          // Ends with [A-Za-z]
          // Get the last character in the label
          temp = mark.charAt(mark.length - 1);
          if (temp === "z")
            // Cyclical increment
            temp = "A"; // Move onto uppercase letters
          else if (temp === "Z") temp = "a"; // Move onto lowercase letters
          else temp = String.fromCharCode(temp.charCodeAt() + 1);
          // Replace last character of label with incremented char
          setLabel(mark.slice(0, mark.length - 1) + temp);
        }
      }
      notifyListeners({ markupChange: true }); // Notify markup change
    }
  }

  // Adds a listener (by call back func) that will be notified on game/editor state changes
  function addListener(listener) {
    listeners.push(listener);
  }

  // Removes a previously registered listener (used for React cleanup)
  function removeListener(listener) {
    var index = listeners.indexOf(listener);
    if (index !== -1) listeners.splice(index, 1);
  }

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
  function notifyListeners(msg, keepHistory) {
    if (msg.treeChange || msg.stoneChange) edited = true;
    if (!keepHistory && msg.navChange) navHistory = []; // Clear navigation history
    if (msg.navChange && showComment) showComment(current.comment);
    for (let i = 0; i < listeners.length; i++) listeners[i](msg);
  }

  function wasEdited() {
    return edited;
  }

  function resetEdited() {
    edited = false;
  }

  function setShift(value) {
    shift = value;
  }

  function isShift() {
    return shift;
  }

  function applyTransformation(transformation) {
    root.applyTransformationOnRoot(transformation);
    recordTransformation(transformation);
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true, transformChange: true });
    edited = true;
  }

  function setAutoPlay(value) {
    autoPlay = value;
  }

  function getReviewMode() {
    return reviewMode;
  }

  function setReviewMode(value) {
    reviewMode = value;
  }

  function getReviewEnabled() {
    return reviewEnabled;
  }

  function setReviewEnabled(value) {
    reviewEnabled = value;
  }

  function setControlButtonLock(value) {
    besogo.controlButtonLock = value;
  }

  function intuitionHeroPower() {
    besogo.intuitionActive = true;
    deleteNextMoveGroup = true;
    besogo.editor.resetToStart();
    besogo.editor.setReviewMode(true);
    besogo.editor.notifyListeners({
      treeChange: true,
      navChange: true,
      stoneChange: true,
    });
  }

  function isPerformingAutoPlay() {
    return performingAutoPlay;
  }

  function setSoundEnabled(value)
  {
    soundEnabled = value;
  }

  function registerDisplayResult(value)
  {
    displayResult = value;
  }

  function registerLineLost(value)
  {
    lineLost = value;
  }

	function registerAddTimeForMovePlayed(value)
	{
		addTimeForMovePlayed = value;
	}

  function addToRequired(node, cameFrom) {
    if (node.localEdit)
      return;
    let element = [];
    element.node = node;
    element.cameFrom = cameFrom;
    remainingRequiredNodes.push(element);
  }

	function navigateToRemainingRequiredIfNeeded(timer)
	{
		// remove visited nodes from the "to-do" list
		remainingRequiredNodes = remainingRequiredNodes.filter(element => !element.node.visited);
		if (remainingRequiredNodes.length == 0)
			return false;
		performingAutoPlay = true;
		addTimeForMovePlayed(1.5);

		setTimeout(function ()
		{
			// when autoplay was cancelled, it was reset in the meantime, so we forget about this
			if (!performingAutoPlay)
				return;

			performingAutoPlay = false;
			let element = remainingRequiredNodes.pop();

			element.node.cameFrom = element.cameFrom;
			navigateToNode(element.node);
			if (showComment)
				showComment("Correct, but what about this answer?\n" + element.node.comment);
		}, timer);
		notifyListeners({ navChange: true }); // Notify navigation (with no tree edits)
		return true;
	}

  function resetToStart() {
    performingAutoPlay = false;
    prevNode(-1);
    remainingRequiredNodes = [];
    root.unvisit();
  }

  function registerShowComment(value) {
    showComment = value;
  }

  // ── Board transformation record ───────────────────────────────────
  // Every transformation (normalising the problem to the top-left, the chosen
  // corner, rotating a tall board, inverting the colours, the orientation
  // buttons) changes the tree's coordinates in place. Each one applied since
  // the root was loaded is recorded here, so a point of the stored SGF maps
  // exactly to the board as shown, and back. Comments keep their coordinates
  // in the space of the stored SGF and are converted through these.

  function recordTransformation(transformation) {
    var copy = besogo.makeTransformation();
    copy.hFlip = !!transformation.hFlip;
    copy.vFlip = !!transformation.vFlip;
    copy.rotateClockwise = !!transformation.rotateClockwise;
    copy.rotateCounterClockwise = !!transformation.rotateCounterClockwise;
    copy.invertColors = !!transformation.invertColors;
    appliedTransformations.push(copy);
  }

  // Maps a point of the stored SGF to the point on the board as shown.
  function sgfPointToView(point) {
    var size = root.getSize();
    var result = { x: point.x, y: point.y };
    for (var i = 0; i < appliedTransformations.length; i++)
      result = appliedTransformations[i].apply(result, size);
    return { x: result.x, y: result.y };
  }

  // Maps a point on the board as shown back to the point of the stored SGF.
  function viewPointToSgf(point) {
    var size = root.getSize();
    var result = { x: point.x, y: point.y };
    for (var i = appliedTransformations.length - 1; i >= 0; i--)
      result = appliedTransformations[i].applyInverse(result, size);
    return { x: result.x, y: result.y };
  }

  // Whether black and white are swapped compared to the stored SGF.
  function isColorInverted() {
    var inverted = false;
    for (var i = 0; i < appliedTransformations.length; i++)
      if (appliedTransformations[i].invertColors)
        inverted = !inverted;
    return inverted;
  }

  // The transformation from the stored SGF to the board as shown, written as
  // an optional transpose (x and y swapped) followed by the flips. The server
  // applies it in this form when the board is saved as a new SGF version.
  function getTransformationFromSgf() {
    var size = root.getSize();
    // (1, 2) lies on no symmetry axis, so its image identifies the transformation
    var probe = sgfPointToView({ x: 1, y: 2 });
    var transpose = probe.x === 2 || probe.x === size.x - 1;
    return {
      transpose: transpose,
      flipX: transpose ? probe.x === size.x - 1 : probe.x === size.x,
      flipY: transpose ? probe.y === size.y : probe.y === size.y - 1,
      invertColors: isColorInverted(),
    };
  }

  // Converts a western coordinate label (e.g. "R19") into its (x, y) point,
  // or null when it is not on the board.
  function coordLabelToXY(label) {
    var size = root.getSize();
    var letter = String(label).charAt(0).toUpperCase();
    if (letter < "A" || letter > "Z" || letter === "I") return null;
    var x = letter.charCodeAt(0) - 64;
    if (letter > "I") x--;
    var row = parseInt(String(label).substring(1), 10);
    var y = size.y - row + 1;
    if (!(x >= 1 && x <= size.x && y >= 1 && y <= size.y)) return null;
    return { x: x, y: y };
  }

  // Converts an (x, y) point into its western coordinate label (e.g. A17).
  function coordXYToLabel(x, y) {
    var size = root.getSize();
    if (x < 1 || x > size.x || y < 1 || y > size.y) return null;
    var labels = besogo.coord.western(size.x, size.y);
    return labels.x[x] + labels.y[y];
  }

  // Converts a coordinate label of the stored SGF to the label of the same
  // intersection on the board as shown (null when it is not on the board).
  function sgfLabelToView(label) {
    var point = coordLabelToXY(label);
    if (!point) return null;
    var view = sgfPointToView(point);
    return coordXYToLabel(view.x, view.y);
  }

  // Converts a coordinate label read on the board as shown to the label of
  // the same intersection in the stored SGF (null when it is not on the board).
  function viewLabelToSgf(label) {
    var point = coordLabelToXY(label);
    if (!point) return null;
    var sgf = viewPointToSgf(point);
    return coordXYToLabel(sgf.x, sgf.y);
  }

  // ── Position-anchored comment support ─────────────────────────────
  // A comment can be anchored to a node of the game tree. Its `position` is
  // "x/y/parentX/parentY/childX/childY/moveNumber/childCount/sgf|path", where
  // path lists the moves from the root to the node ("x/y+x/y"). All of the
  // coordinates are points of the stored SGF (missing moves are -1/-1).

  function makeCommentPosition(node) {
    if (!node || !node.move) return null;
    function sgfMove(move) {
      if (!move) return "-1/-1";
      var point = viewPointToSgf(move);
      return point.x + "/" + point.y;
    }
    var path = [];
    for (var n = node; n; n = n.parent)
      if (n.move) path.unshift(sgfMove(n.move));
    var parentMove = node.parent && node.parent.move ? node.parent.move : null;
    var childMove = node.children.length > 0 && node.children[0].move ? node.children[0].move : null;
    return sgfMove(node.move) + "/" + sgfMove(parentMove) + "/" + sgfMove(childMove) + "/" +
      node.moveNumber + "/" + node.children.length + "/sgf|" + path.join("+");
  }

  // Finds the direct child (or its continuation through null-move/setup nodes)
  // whose move matches the coordinate. Child moves among siblings are unique in
  // a game tree, so we must only descend along the actual path - NOT search the
  // whole subtree, which would match transpositions from other variations and
  // collapse different comments onto the same node.
  function findChildByCoord(node, coord) {
    if (!node) return null;
    for (var i = 0; i < node.children.length; i++) {
      var child = node.children[i];
      if (!child.move)
      {
        // setup node: the path passes through it - inspect its continuation
        var result = findChildByCoord(child, coord);
        if (result) return result;
      }
      else if (child.move.x === coord.x && child.move.y === coord.y)
        return child;
    }
    return null;
  }

  // Resolves a comment position to the game-tree node it anchors, or null when
  // it cannot be found (e.g. the tree changed underneath). Does not navigate or
  // mutate the editor state.
  function findNodeForPosition(positionString) {
    if (!positionString) return null;
    var path = positionString.split("|")[1];
    if (!path) return null;
    var node = root;
    var moves = path.split("+");
    for (var i = 0; i < moves.length; i++) {
      var parts = moves[i].split("/");
      if (parts.length < 2) return null;
      var point = sgfPointToView({ x: parseInt(parts[0], 10), y: parseInt(parts[1], 10) });
      node = findChildByCoord(node, point);
      if (!node) return null;
    }
    return node;
  }

  // Shows the position a comment is anchored to. When the tree changed since
  // the comment was written, the moves it no longer has are played as local
  // moves, as far as they are legal. Returns false when there is no position.
  function showCommentPosition(positionString) {
    var path = positionString ? positionString.split("|")[1] : null;
    if (!path) return false;
    var node = root;
    var moves = path.split("+");
    for (var i = 0; i < moves.length; i++) {
      var parts = moves[i].split("/");
      if (parts.length < 2) break;
      var point = sgfPointToView({ x: parseInt(parts[0], 10), y: parseInt(parts[1], 10) });
      var child = findChildByCoord(node, point);
      if (!child) {
        child = node.makeChild();
        child.localEdit = true;
        if (!child.playMove(point.x, point.y)) break;
        node.registerChild(child);
      }
      node = child;
    }
    current = node;
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
    return true;
  }

  // Finds the shallowest descendant of `node` (strictly below it) whose move
  // matches `coord`, or null when there is none. Searching for the shallowest
  // keeps the resolved path as close to the sequence as possible (fewest
  // unlisted intermediate moves inserted).
  function findDescendant(node, coord, color) {
    var bestNode = null;
    var bestDepth = Infinity;
    (function visit(n, depth) {
      for (var i = 0; i < n.children.length; i++) {
        var child = n.children[i];
        if (child.move) {
          if (child.move.x === coord.x && child.move.y === coord.y && depth < bestDepth) {
            if (color === undefined || child.move.color === color) {
              bestNode = child;
              bestDepth = depth;
            }
          }
        }
        visit(child, depth + 1);
      }
    })(node, 1);
    return bestNode;
  }

  // Maps a comment colour marker (b/w) to the besogo stone colour (-1/1).
  function commentColorToStone(color) {
    if (color === 'b') return -1;
    if (color === 'w') return 1;
    return undefined;
  }

  // Resolves a comment coordinate sequence for a hover on `hoverIndex`. The
  // coordinates are labels of the board as shown. The sequence is treated as a
  // subsequence: it finds the tree path that contains the coords in order
  // (allowing intermediate moves), and returns the node the hovered coordinate
  // lands on plus the node right before it. This lets hovering a move always
  // show the board just before it, even when the comment omits intermediate
  // moves or spans different branches.
  //
  // Returns { found, beforeNode, hoverNode, nodes }. `found` is true when the
  // hovered coordinate landed on a tree node; `beforeNode` is the node to show
  // (the move just before the hovered one and from which the hovered move is a
  // next move).
  function resolveSequenceHover(coords, hoverIndex, startNode, colors) {
    var current = startNode || root;
    var hoverNode = null;
    var beforeNode = null;
    var nodes = [];
    for (var i = 0; i < coords.length; i++) {
      var point = coordLabelToXY(coords[i]);
      if (!point) break;
      var found = findDescendant(current, point, commentColorToStone(colors && colors[i]));
      if (!found) break;
      nodes.push(found);
      if (i === hoverIndex) {
        hoverNode = found;
        beforeNode = found.parent || current;
      }
      current = found;
    }
    return { found: !!hoverNode, beforeNode: beforeNode, hoverNode: hoverNode, nodes: nodes };
  }

  // Highlights a single intersection on the main board by its internal (x, y).
  function displayHoverCoordAt(x, y) {
    if (besogo.boardDisplay && besogo.boardDisplay.displayHoverCoordAt)
      besogo.boardDisplay.displayHoverCoordAt(x, y);
  }

  // ── Anchored-comment badge support ────────────────────────────────
  // `setAnchoredComments` receives a Map of node -> comment count and tells
  // the tree panel to redraw (only effective when review mode is on).
  function getAnchoredCommentCount(node) {
    return anchoredCommentNodes.get(node) || 0;
  }

  function setAnchoredComments(nodeCounts) {
    anchoredCommentNodes = nodeCounts || new Map();
    notifyListeners({ treeChange: true });
  }

  function setFullEditor(value) {
    fullEditor = value;
  }

  function displayError(e) {
    $("#theComment").text(e);
    $("#theComment").css("display", "block");
    $("#theComment").css("color", "rgb(166, 27, 27)");
    $("#theComment").css("border", "thick double rgb(166, 27, 27)");
  }

  // Rotate the board 90° in the given direction.
  //
  // This does three things:
  //   1. Remaps coordinates in the game tree so stone placement still works
  //      correctly after the visual rotation (via applyTransformation).
  //   2. Adjusts CSS width for half-boards (via changeRotationWidth).
  //   3. Updates the SVG viewBox to show the rotated view (via rotateViewBox).
  //
  // A full board shows the whole board, so only the stones turn (step 1).
  //
  // Called by:
  //   - autoRotate() — automatic rotation of boards too tall to view without scrolling
  //   - toolPanel.js — manual rotation buttons (CCW/CW)
  function rotateBoard(clockwise) {
    // Remap coordinates in game tree so clicks map correctly after rotation
    var transformation = besogo.makeTransformation();
    if (clockwise)
      transformation.rotateClockwise = true;
    else
      transformation.rotateCounterClockwise = true;
    applyTransformation(transformation);

    if (besogo.scaleParameters["orientation"] === "full-board")
      return;

    var isHalfBoard =
      besogo.scaleParameters["boardCanvasSize"] === "horizontal half board" ||
      besogo.scaleParameters["boardCanvasSize"] === "vertical half board";

    if (isHalfBoard)
      changeRotationWidth(
        besogo.scaleParameters["boardCanvasSize"],
        besogo.boardParameters["corner"],
        besogo.scaleParameters["rotation"]
      );

    var corner = besogo.boardParameters["corner"];
    var rotation = besogo.scaleParameters["rotation"];

    // Initial rotation mapping: corner → rotation type when no rotation is set yet
    var initialMap = clockwise
      ? { "top-left": 3, "top-right": 2, "bottom-right": 1, "bottom-left": 0 }
      : { "top-left": 1, "top-right": 0, "bottom-right": 3, "bottom-left": 2 };

    if (rotation === -1) {
      rotateViewBox(initialMap[corner]);
    } else {
      var rotationDivider =
        corner === "top-left" || corner === "bottom-right" ? 1 : 0;
      var currentRotation = clockwise
        ? (rotation !== 0 ? rotation - 1 : 3)
        : (rotation !== 3 ? rotation + 1 : 0);
      rotateViewBox(currentRotation, rotation % 2 === rotationDivider);
    }
  }

  // If the board is too tall and slim, the problem can't be viewed without scrolling,
  // so it is turned counter-clockwise.
  // This applies for regular boards when the highest y is higher than x by at least 5 (x-y < -4).
  // And for vertical half boards when the highest y is higher than x by at least 11 (x-y < -10).
  function autoRotate() {
    var size = besogo.scaleParameters["boardCanvasSize"];
    var highest = besogo.scaleParameters["highest"];
    var tooTall =
      (size === "regular board" && highest.x - highest.y < -4) ||
      (size === "vertical half board" && highest.x - highest.y < -10);
    if (!tooTall)
      return;

    rotateBoard(false);

    // CSS width hack: vertical half boards need a wider container after rotation
    // to prevent layout overflow. Only needed on initial auto-rotate, not manual.
    if (size === "vertical half board")
      besogo.verticalHalfBoardRotationWidthToken = true;
  }

  //adjust the viewBox to the rotation
  function rotateViewBox(type = 0, swap = false) {
    var bp = besogo.boardParameters;
    var h = swap ? "Width" : "Height";
    var w = swap ? "Height" : "Width";

    // Each rotation type has a viewBox defined as [x, y, width, height]
    // using board parameter names that get swapped when the board dimensions are transposed
    var viewBoxParams = [
      [0,                  0,                  "board" + h,       "board" + w],
      [0,                  "board" + w + "2",  "board" + h + "3", "board" + w + "3"],
      ["board" + h + "2", "board" + w + "2",  "board" + h + "3", "board" + w + "3"],
      ["board" + h + "2", 0,                  "board" + h + "3", "board" + w + "3"],
    ];

    var values = viewBoxParams[type].map(function(p) {
      return typeof p === "number" ? p : bp[p];
    });
    besogo.boardCanvasSvg.setAttribute("viewBox", values.join(" "));
    besogo.scaleParameters["rotation"] = type;
    besogoRotation = type;
  }

  //the board width should adjust with the rotation sometimes
  function changeRotationWidth(canvas, corner, rotation) {
    var WIDE = { width: "78%", margin: "0 auto" };
    var NARROW = { width: "30%", margin: "0 auto" };

    // Determine if the board should be narrow or wide based on canvas type, corner, and rotation
    var isNarrow;
    if (rotation === -1) {
      isNarrow = canvas === "horizontal half board";
    } else if (canvas === "vertical half board") {
      var oddRotation = rotation === 1 || rotation === 3;
      isNarrow = corner === "top-left" ? oddRotation : (corner === "top-right" ? !oddRotation : false);
    } else if (canvas === "horizontal half board") {
      var oddRotation = rotation === 1 || rotation === 3;
      isNarrow = corner === "top-left" ? !oddRotation : (corner === "bottom-left" ? oddRotation : false);
    } else {
      return;
    }

    var style = isNarrow ? NARROW : WIDE;
    $(".besogo-board").css("width", style.width);
    $(".besogo-board").css("margin", style.margin);
  }
};
