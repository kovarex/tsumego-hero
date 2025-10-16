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
    nextOpen = [],
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
    commentParamList = [],
    displayResult = null,
    showComment = null;

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
    resetToStart: resetToStart,
    registerShowComment: registerShowComment,
    displayHoverCoord: displayHoverCoord,
    commentPosition: commentPosition,
    commentTreeSearch: commentTreeSearch,
    commentTreeSearchExtendPath: commentTreeSearchExtendPath,
    getOrientation: getOrientation,
    dynamicCommentCoords: dynamicCommentCoords,
    adjustCommentCoords: adjustCommentCoords,
    isMoveInTree: isMoveInTree,
    searchNodesForTreePosition: searchNodesForTreePosition,
    setFullEditor: setFullEditor,
    displayError: displayError,
    compareFoundCommentMoves: compareFoundCommentMoves,
    createSignatures: createSignatures,
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
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
    edited = false;
  }

  // Navigates forward num nodes (to the end if num === -1)
  function nextNode(num, select = 0, autoPlay = false) {
    if (!current.hasChildDependingOnAutoPlay(autoPlay))
      // Check if no children
      return; // Do nothing if no children (avoid notification)
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

        // The opponent wants to auto play into a move we already visited, potentially forcing the solver to solve part of a variation already done
        // in this case, we just stop and call this sub-variation finished and move on.
        if (
          autoPlay &&
          ((next.target && next.target.visited) || next.visited)
        ) {
          finish(current);
          return;
        }

        if (!next.target) {
          next.cameFrom = null;
          current = next; // Go to the selected child
        } else {
          next.target.cameFrom = current;
          current = next.target;
        }
      }
      current.visited = true;
      num--;
    }

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
    while (current.parent && num !== 0) {
      navHistory.push(current); // Save current into navigation history
      if (current.cameFrom) current = current.cameFrom;
      else current = current.parent;
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
    } else if (boardLockValue == 1) {
      if (!multipleChoiceEnabled) {
        if (nextButtonLink != 0)
          window.location.href =
            "/tsumegos/play/" + nextButtonLink + inFavorite;
        else if (mode == 1) {
          window.location.href = "/sets/view/" + nextButtonLinkSet;
        }
      }
    }
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
    setTimeout(function () {
      let success = !node.localEdit && node.correct == CORRECT_GOOD;
      if (
        !node.localEdit &&
        !success &&
        node.status &&
        showComment &&
        node.comment == ""
      ) {
        let root = node.getRoot();
        if (
          root.goal != GOAL_NONE &&
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
    }, 240);
  }

  function navigateToNode(node, byClicking = false) {
    if (besogo.isEmbedded) besogo.soundsEnabled = soundsEnabled;

    if (byClicking && besogo.soundsEnabled && !reviewMode)
      document.getElementsByTagName("audio")[0].play();
    current = node; // Navigate to child if found
    current.visited = true;
    if (
      autoPlay &&
      !reviewMode &&
      node.move.color == node.getRoot().firstMove &&
      node.hasNonLocalChildIncludingVirtual()
    ) {
      performingAutoPlay = true;
      setTimeout(function () {
        // when autoplay was cancelled, it was reset in the meantime, so we forget about this
        if (!performingAutoPlay) return;
        performingAutoPlay = false;
        if (!isMutable) return;

        let selectOpponentMove = 0;
        {
          let selectionCount =
            current.countOfNonLocalChildrenIncludingVirtualWithNoBetterStatus();
          if (selectionCount > 1)
            selectOpponentMove = Math.floor(Math.random() * selectionCount);
        }

        //if alternative response mode is turned on
        if (besogo.alternativeResponse) {
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
    tryToFinish(node);
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
    notifyListeners({ treeChange: true, navChange: true, stoneChange: true });
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

  function setSoundEnabled(value) {
    soundEnabled = value;
  }

  function registerDisplayResult(value) {
    displayResult = value;
  }

  function addToRequired(node, cameFrom) {
    if (node.localEdit)
      return;
    let element = [];
    element.node = node;
    element.cameFrom = cameFrom;
    remainingRequiredNodes.push(element);
  }

  function navigateToRemainingRequiredIfNeeded(timer) {
    // remove visited nodes from the "to-do" list
    while (
      remainingRequiredNodes.length > 0 &&
      remainingRequiredNodes[0].node.visited
    )
      remainingRequiredNodes.pop();
    if (remainingRequiredNodes.length == 0) return false;
    performingAutoPlay = true;

    setTimeout(function () {
      // when autoplay was cancelled, it was reset in the meantime, so we forget about this
      if (!performingAutoPlay) return;

      performingAutoPlay = false;
      let element = remainingRequiredNodes.pop();

      element.node.cameFrom = element.cameFrom;
      navigateToNode(element.node);
      if (showComment)
        showComment(
          "Correct, but what about this answer?\n" + element.node.comment
        );
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

  function commentPosition(positionParams) {
    commentParamList = [];
    var positionParams2 = [];
    if (typeof positionParams[9] !== "undefined") {
      positionParams2 = positionParams[9].split("+");
      for (let i = 0; i < positionParams2.length; i++)
        positionParams2[i] = positionParams2[i].split("/");
    }

    let hasParent = true;
    if (positionParams[2] == -1) hasParent = false;

    //normalizing orientation
    let counter = 0;
    if (positionParams[8] === "top-right") {
      counter = 0;
      while (counter <= 5) {
        if (counter % 2 == 0)
          positionParams[counter] = 20 - positionParams[counter];
        counter++;
      }
      for (let i = 0; i < positionParams2.length; i++)
        positionParams2[i][0] = 20 - positionParams2[i][0];
    } else if (positionParams[8] === "bottom-left") {
      counter = 0;
      while (counter <= 5) {
        if (counter % 2 != 0)
          positionParams[counter] = 20 - positionParams[counter];
        counter++;
      }
      for (let i = 0; i < positionParams2.length; i++)
        positionParams2[i][1] = 20 - positionParams2[i][1];
    } else if (positionParams[8] === "bottom-right") {
      counter = 0;
      while (counter <= 5) {
        positionParams[counter] = 20 - positionParams[counter];
        counter++;
      }
      for (let i = 0; i < positionParams2.length; i++) {
        positionParams2[i][0] = 20 - positionParams2[i][0];
        positionParams2[i][1] = 20 - positionParams2[i][1];
      }
    }
    //apply transformation after normalizing
    if (besogo.scaleParameters["orientation"] !== "full-board") {
      if (besogo.boardParameters["corner"] === "top-right") {
        counter = 0;
        while (counter <= 5) {
          if (counter % 2 == 0)
            positionParams[counter] = 20 - positionParams[counter];
          counter++;
        }
        for (let i = 0; i < positionParams2.length; i++)
          positionParams2[i][0] = 20 - positionParams2[i][0];
      } else if (besogo.boardParameters["corner"] === "bottom-left") {
        counter = 0;
        while (counter <= 5) {
          if (counter % 2 != 0)
            positionParams[counter] = 20 - positionParams[counter];
          counter++;
        }
        for (let i = 0; i < positionParams2.length; i++)
          positionParams2[i][1] = 20 - positionParams2[i][1];
      } else if (besogo.boardParameters["corner"] === "bottom-right") {
        counter = 0;
        while (counter <= 5) {
          positionParams[counter] = 20 - positionParams[counter];
          counter++;
        }
        for (let i = 0; i < positionParams2.length; i++) {
          positionParams2[i][0] = 20 - positionParams2[i][0];
          positionParams2[i][1] = 20 - positionParams2[i][1];
        }
      }
    }

    if (positionParams[8] !== "0") {
      commentTreeSearch(
        root,
        0,
        0,
        nextOpen,
        positionParams,
        true,
        positionParams2
      );

      if (positionParams2.length !== 0) {
        let foundMatch = false;
        for (let i = 0; i < commentParamList.length; i++) {
          if (
            compareFoundCommentMoves(
              positionParams2,
              commentParamList[i],
              i
            ) !== false
          ) {
            foundMatch = i;
            break;
          }
        }
        if (foundMatch !== false) setCurrent(commentParamList[foundMatch]);
        else setCurrent(root);
      }
    } else setCurrent(root);
  }

  function compareFoundCommentMoves(
    positionParams2,
    commentParams,
    currentIndex
  ) {
    let newP = commentParams;
    let counter = 0;
    let match = true;
    while (newP !== null && newP.move !== null) {
      if (typeof positionParams2[counter] === "undefined") return false;
      if (newP.move.x != positionParams2[counter][0]) return false;
      if (newP.move.y != positionParams2[counter++][1]) return false;
      newP = newP.parent;
    }
    if (positionParams2.length != counter) return false;
    return currentIndex;
  }
  function commentTreeSearch(
    node,
    x,
    y,
    nextOpen,
    positionParams,
    hasParent = true,
    positionParams2 = null
  ) {
    var children = node.children,
      position,
      path,
      childPath,
      i; // Scratch iteration variable
    if (children.length === 0)
      // Reached end of branch
      path = "m" + x + "," + y; // Start path at end of branch
    // Current node has children
    else {
      position = nextOpen[x + 1] || 0; // First open spot in next column
      position = position < y ? y : position; // Bring level with current y
      if (y < position - 1)
        // Check if first child natural drop > 1
        y = position - 1; // Bring current y within 1 of first child drop
      // Place first child and extend path
      path =
        commentTreeSearch(
          children[0],
          x + 1,
          position,
          nextOpen,
          positionParams,
          hasParent,
          positionParams2
        ) + commentTreeSearchExtendPath(x, y, nextOpen);
      // Place other children (intentionally starting at i = 1)
      for (i = 1; i < children.length; i++) {
        position = nextOpen[x + 1];
        childPath =
          commentTreeSearch(
            children[i],
            x + 1,
            position,
            nextOpen,
            positionParams,
            hasParent,
            positionParams2
          ) + commentTreeSearchExtendPath(x, y, nextOpen, position - 1);
        // End path at beginning of branch
      }
    }
    let childrenMoveX = 0;
    let childrenMoveY = 0;
    if (node.children.length === 0) {
      childrenMoveX = positionParams[4];
      childrenMoveY = positionParams[5];
    } else {
      childrenMoveX = node.children[0].move.x;
      childrenMoveY = node.children[0].move.y;
    }
    if (node.parent !== null && node.parent.move !== null) {
      if (
        node.move.x == positionParams[0] &&
        node.move.y == positionParams[1] &&
        node.parent.move.x == positionParams[2] &&
        node.parent.move.y == positionParams[3] &&
        childrenMoveX == positionParams[4] &&
        childrenMoveY == positionParams[5] &&
        node.moveNumber == positionParams[6]
      ) {
        if (positionParams2.length === 0) setCurrent(node);
      }
    } else if (node.move !== null) {
      if (
        node.move.x == positionParams[0] &&
        node.move.y == positionParams[1] &&
        childrenMoveX == positionParams[4] &&
        childrenMoveY == positionParams[5] &&
        node.moveNumber == positionParams[6]
      ) {
        if (positionParams2.length === 0) setCurrent(node);
      }
    }

    if (positionParams2.length !== 0 && node.move !== null) {
      if (
        node.move.x == positionParams2[0][0] &&
        node.move.y == positionParams2[0][1]
      )
        commentParamList.push(node);
    }

    nextOpen[x] = y + 1;
    return path;
  }

  function commentTreeSearchExtendPath(x, y, nextOpen, prevChildPos) {
    // Extends path from child to current
    var childPos = nextOpen[x + 1] - 1; // Position of child
    if (childPos === y)
      // Child is horizontally level with current
      return "h-120"; // Horizontal line back to current
    else if (childPos === y + 1)
      // Child is one drop from current
      return "l-120,-120"; // Diagonal drop line back to current
    else if (prevChildPos && prevChildPos !== y)
      // Previous is already dropped, extend back to previous child drop line
      return "l-60,-60v-" + 120 * (childPos - prevChildPos);
    // Extend double-bend drop line back to parent
    else return "l-60,-60v-" + 120 * (childPos - y - 1) + "l-60,-60";
  }

  function getOrientation() {
    let array = [];
    array[0] = besogo.boardParameters["corner"];
    array[1] = besogo.scaleParameters["orientation"];
    return array;
  }

  function dynamicCommentCoords(id, content) {
    if (besogo.dynamicCommentCoords.length === 0) {
      besogo.dynamicCommentCoords[0] = [];
      besogo.dynamicCommentCoords[1] = [];
    }
    besogo.dynamicCommentCoords[0].push(id);
    besogo.dynamicCommentCoords[1].push(content);
  }

  function adjustCommentCoords() {
    let buffer;
    let c1;
    let c2;
    let found = false;
    let spin = 0;
    let convertedCoords = besogo.coord["western"](
      besogo.scaleParameters["boardCoordSize"],
      besogo.scaleParameters["boardCoordSize"]
    );

    if (typeof besogo.dynamicCommentCoords[0] !== "undefined") {
      if (besogo.coordArea["lowestX"] > besogo.coordArea["highestX"]) {
        buffer = besogo.coordArea["lowestX"];
        besogo.coordArea["lowestX"] = besogo.coordArea["highestX"];
        besogo.coordArea["highestX"] = buffer;
      }
      if (besogo.coordArea["lowestY"] > besogo.coordArea["highestY"]) {
        buffer = besogo.coordArea["lowestY"];
        besogo.coordArea["lowestY"] = besogo.coordArea["highestY"];
        besogo.coordArea["highestY"] = buffer;
      }
      for (let i = 0; i < besogo.dynamicCommentCoords[0].length; i++) {
        c1 = besogo.dynamicCommentCoords[1][i].charAt(0).toUpperCase();
        c2 = besogo.dynamicCommentCoords[1][i].substring(1);
        c1 = c1.charCodeAt(0) - 65;
        if (c1 > 7) c1--;
        c2 = besogo.scaleParameters["boardCoordSize"] - c2;
        found = false;
        spin = 0;
        while (spin < 4) {
          if (!found) {
            if (spin == 1 || spin == 3) {
              if (
                besogo.scaleParameters["boardCanvasSize"] !==
                "horizontal half board"
              )
                c1 = besogo.scaleParameters["boardCoordSize"] - c1 - 1;
              else c2 = besogo.scaleParameters["boardCoordSize"] - c2 - 1;
            } else if (spin == 2)
              c2 = besogo.scaleParameters["boardCoordSize"] - c2 - 1;
            if (
              c1 >= besogo.coordArea["lowestX"] &&
              c1 <= besogo.coordArea["highestX"] &&
              c2 >= besogo.coordArea["lowestY"] &&
              c2 <= besogo.coordArea["highestY"]
            )
              found = true;
          }
          spin++;
        }
        c1 = convertedCoords.x[c1 + 1];
        c2 = convertedCoords.y[c2 + 1];
        $("#" + besogo.dynamicCommentCoords[0][i]).text(c1 + c2);
      }
    }
  }

  function isMoveInTree(cu) {
    let moveX;
    let moveY;
    let treeX = cu.navTreeX;
    let treeY = cu.navTreeY;
    let depth = -1;
    let found = null;
    let notInTreeCoords = [];
    notInTreeCoords["x"] = [];
    notInTreeCoords["y"] = [];
    let returnArray = [];
    let convertedCoords = besogo.coord["western"](
      besogo.scaleParameters["boardCoordSize"],
      besogo.scaleParameters["boardCoordSize"]
    );
    let exitCounter = 0;

    if (treeX !== 0) {
      let cu2 = cu;
      while (found === null) {
        found = searchNodesForTreePosition(cu2);
        cu2 = cu2.parent;
        treeX--;
        depth++;
        exitCounter++;
        if (exitCounter > 1000) break;
      }
      while (depth > 0) {
        moveX = convertedCoords.x[cu.move.x];
        moveY = convertedCoords.y[cu.move.y];
        notInTreeCoords["x"].push(moveX);
        notInTreeCoords["y"].push(moveY);
        cu = cu.parent;
        depth--;
      }
    } else found = null;

    if (exitCounter > 1000) found = null;
    returnArray[0] = found;
    returnArray[1] = notInTreeCoords;

    return returnArray;
  }

  function searchNodesForTreePosition(cu) {
    let found = null;
    for (let i = 0; i < besogo.nodes.length; i++)
      if (cu == besogo.nodes[i]) found = besogo.nodes[i];
    return found;
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

  //signatures are strings that represent a 3x3 grid around correct moves for pattern search
  function createSignatures() {
    let board = getCurrent();
    let correctMoves = getRoot().children;
    let signatures = "";
    let playerColor = besogo.playerColor === "white" ? [1, -1] : [-1, 1];
    let size = besogo.scaleParameters.boardCoordSize;
    for (let i = 0; i < correctMoves.length; i++) {
      if (correctMoves[i].correct === 1) {
        let array = [
          ["_", "_", "_"],
          ["_", "_", "_"],
          ["_", "_", "_"],
        ];
        let x = correctMoves[i].move.x - 1;
        let y = correctMoves[i].move.y - 1;
        let counterX = 0;
        let counterY = 0;
        for (let j = y; j < y + 3; j++) {
          counterX = 0;
          for (let k = x; k < x + 3; k++) {
            if (k > 0 && j > 0 && k <= size && j <= size) {
              if (board.getStone(k, j) === playerColor[0])
                array[counterY][counterX] = "b";
              else if (board.getStone(k, j) === playerColor[1])
                array[counterY][counterX] = "w";
              else array[counterY][counterX] = "-";
            } else array[counterY][counterX] = "_";
            counterX++;
          }
          counterY++;
        }
        signatures += createSignature(array) + "/";
      }
    }
    document.cookie =
      "signatures=" +
      signatures +
      tsumegoFileLink +
      ";SameSite=none;Secure=false";
    //if(idForSignature!==-1) window.location.href = "/tsumegos/play/"+idForSignature+"?idForTheThing="+idForSignature2;
  }

  function createSignature(a) {
    let value = 0;
    let highestValue = 0;
    let highestSignature = "";
    for (let i = 0; i < 8; i++) {
      value = evaluateSignature(a);
      if (value > highestValue) {
        highestSignature =
          a[0][0] +
          a[0][1] +
          a[0][2] +
          a[1][0] +
          a[1][1] +
          a[1][2] +
          a[2][0] +
          a[2][1] +
          a[2][2];
        highestValue = value;
      }
      a = i !== 3 ? rotateSignature(a) : mirrorSignature(a);
    }
    return highestSignature;
  }

  //evaluation to find out which oritentation should be saved
  function evaluateSignature(a) {
    let values = [0, 0, 0];
    let types = ["_", "b", "w"];
    for (let i = 0; i < 3; i++)
      for (let j = 0; j < 3; j++)
        for (let k = 0; k < 3; k++)
          if (a[j][k] === types[i]) values[i] += getSignatureFieldValue(j, k);
    let valueString = "1";
    for (let i = 0; i < 3; i++)
      valueString += values[i] < 10 ? "0" + values[i] : values[i];
    return parseInt(valueString);
  }

  function getSignatureFieldValue(x, y) {
    if (x == 0 && y == 0) return 10;
    let value = 1;
    for (let i = 2; i >= 0; i--)
      for (let j = 2; j >= 0; j--)
        if (i == x && j == y) return value;
        else value++;
    return 0;
  }

  function mirrorSignature(matrix) {
    const n = matrix.length;
    for (let i = 0; i < n; i++)
      for (let j = 0; j < Math.floor(n / 2); j++)
        [matrix[i][j], matrix[i][n - 1 - j]] = [
          matrix[i][n - 1 - j],
          matrix[i][j],
        ];
    return matrix;
  }

  function rotateSignature(matrix) {
    const n = matrix.length;
    for (let i = 0; i < n; i++)
      for (let j = i; j < n; j++)
        [matrix[i][j], matrix[j][i]] = [matrix[j][i], matrix[i][j]];
    for (let i = 0; i < n; i++) matrix[i].reverse();
    return matrix;
  }

  function outputSignature(a) {
    let str = "";
    for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) str += a[i][j];
    console.log(str.substring(0, 3));
    console.log(str.substring(3, 6));
    console.log(str.substring(6, 9));
  }
};
