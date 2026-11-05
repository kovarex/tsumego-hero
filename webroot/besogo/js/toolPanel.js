besogo.makeToolPanel = function (container, editor) {
  "use strict";
  var element, // Scratch for building SVG images
    svg, // Scratch for building SVG images
    labelText, // Text area for next label input
    selectors = {}; // Holds selection rects

  var reviewButton = null;
  var tlIcon = null;
  var trIcon = null;
  var blIcon = null;
  var brIcon = null;
  var spinIcon = null;
  var favButtonLit = false;

  if (container.className == "besogo-tsumegoPlayTool")
    makeReviewToolButtons(container, editor);
  else if (!besogo.isEmbedded) makeEditorToolButtons(container, editor);
  else makeEditorToolButtons2(container, editor);
  besogo.editor.addListener(toolStateUpdate); // Set up listener for tool state updates

  toolStateUpdate({
    label: besogo.editor.getLabel(),
    tool: besogo.editor.getTool(),
    tsumegoPlayTool: besogo.editor.getTool(),
  }); // Initialize

  function makeReviewToolButtons(container, editor) {
    if (besogo.scaleParameters["orientation"] !== "full-board") {
      if (besogo.scaleParameters["boardCanvasSize"] === "horizontal half board")
        tlIcon = "boardOrientationT";
      else if (
        besogo.scaleParameters["boardCanvasSize"] === "vertical half board"
      )
        tlIcon = "boardOrientationL";
      else tlIcon = "boardOrientationTL";
      makeImageButton(
        "/img/" + tlIcon + ".png",
        "top-left",
        "boardOrientationTL",
        function () {
          revertRotations(
            besogo.boardParameters["corner"],
            besogo.scaleParameters["rotation"]
          );
          if (besogo.boardParameters["corner"] === "top-left") {
            //already there
          } else if (besogo.boardParameters["corner"] === "top-right") {
            let transformation = besogo.makeTransformation();
            transformation.hFlip = true;
            besogo.editor.applyTransformation(transformation);
            updateCoordArea("x", false);
          } else if (besogo.boardParameters["corner"] === "bottom-left") {
            let transformation = besogo.makeTransformation();
            transformation.vFlip = true;
            besogo.editor.applyTransformation(transformation);
            updateCoordArea(false, "y");
          } else if (besogo.boardParameters["corner"] === "bottom-right") {
            let transformation = besogo.makeTransformation();
            transformation.hFlip = true;
            besogo.editor.applyTransformation(transformation);
            transformation = besogo.makeTransformation();
            transformation.vFlip = true;
            besogo.editor.applyTransformation(transformation);
            updateCoordArea("x", "y");
          }
          if (besogo.scaleParameters["orientation"] !== "full-board")
            besogo.boardCanvasSvg.setAttribute(
              "viewBox",
              "0 0 " +
                besogo.boardParameters["boardWidth"] +
                " " +
                besogo.boardParameters["boardHeight"]
            );
          besogo.setStyle("#boardOrientationTL","opacity", "1");
          besogo.setStyle("#boardOrientationTR","opacity", ".62");
          besogo.setStyle("#boardOrientationBL","opacity", ".62");
          besogo.setStyle("#boardOrientationBR","opacity", ".62");
          besogo.setStyle("#boardSpinCounterClockwise","opacity", ".62");
          besogo.setStyle("#boardSpinClockwise","opacity", ".62");
          besogo.boardParameters["corner"] = "top-left";
        }
      );

      if (
        besogo.scaleParameters["boardCanvasSize"] !== "horizontal half board"
      ) {
        if (besogo.scaleParameters["boardCanvasSize"] === "vertical half board")
          trIcon = "boardOrientationR";
        else trIcon = "boardOrientationTR";
        makeImageButton(
          "/img/" + trIcon + ".png",
          "top-right",
          "boardOrientationTR",
          function () {
            revertRotations(
              besogo.boardParameters["corner"],
              besogo.scaleParameters["rotation"]
            );
            if (besogo.boardParameters["corner"] === "top-left") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", false);
            } else if (besogo.boardParameters["corner"] === "top-right") {
              //already there
            } else if (besogo.boardParameters["corner"] === "bottom-left") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", "y");
            } else if (besogo.boardParameters["corner"] === "bottom-right") {
              let transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea(false, "y");
            }
            if (besogo.scaleParameters["orientation"] !== "full-board")
              besogo.boardCanvasSvg.setAttribute(
                "viewBox",
                besogo.boardParameters["boardWidth2"] +
                  " " +
                  0 +
                  " " +
                  besogo.boardParameters["boardWidth3"] +
                  " " +
                  besogo.boardParameters["boardHeight3"]
              );
            besogo.setStyle("#boardOrientationTL","opacity", ".62");
            besogo.setStyle("#boardOrientationTR","opacity", "1");
            besogo.setStyle("#boardOrientationBL","opacity", ".62");
            besogo.setStyle("#boardOrientationBR","opacity", ".62");
            besogo.setStyle("#boardSpinCounterClockwise","opacity", ".62");
            besogo.setStyle("#boardSpinClockwise","opacity", ".62");
            besogo.boardParameters["corner"] = "top-right";
          }
        );
      }
      if (besogo.scaleParameters["boardCanvasSize"] !== "vertical half board") {
        if (
          besogo.scaleParameters["boardCanvasSize"] === "horizontal half board"
        )
          blIcon = "boardOrientationB";
        else blIcon = "boardOrientationBL";
        makeImageButton(
          "/img/" + blIcon + ".png",
          "bottom-left",
          "boardOrientationBL",
          function () {
            revertRotations(
              besogo.boardParameters["corner"],
              besogo.scaleParameters["rotation"]
            );
            if (besogo.boardParameters["corner"] === "top-left") {
              let transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea(false, "y");
            } else if (besogo.boardParameters["corner"] === "top-right") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", "y");
            } else if (besogo.boardParameters["corner"] === "bottom-left") {
              //already there
            } else if (besogo.boardParameters["corner"] === "bottom-right") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", false);
            }
            if (besogo.scaleParameters["orientation"] !== "full-board")
              besogo.boardCanvasSvg.setAttribute(
                "viewBox",
                0 +
                  " " +
                  besogo.boardParameters["boardHeight2"] +
                  " " +
                  besogo.boardParameters["boardWidth3"] +
                  " " +
                  besogo.boardParameters["boardHeight3"]
              );
            besogo.setStyle("#boardOrientationTL","opacity", ".62");
            besogo.setStyle("#boardOrientationTR","opacity", ".62");
            besogo.setStyle("#boardOrientationBL","opacity", "1");
            besogo.setStyle("#boardOrientationBR","opacity", ".62");
            besogo.setStyle("#boardSpinCounterClockwise","opacity", ".62");
            besogo.setStyle("#boardSpinClockwise","opacity", ".62");
            besogo.boardParameters["corner"] = "bottom-left";
          }
        );
      }
      if (
        besogo.scaleParameters["boardCanvasSize"] !== "vertical half board" &&
        besogo.scaleParameters["boardCanvasSize"] !== "horizontal half board"
      ) {
        makeImageButton(
          "/img/boardOrientationBR.png",
          "bottom-right",
          "boardOrientationBR",
          function () {
            revertRotations(
              besogo.boardParameters["corner"],
              besogo.scaleParameters["rotation"]
            );
            if (besogo.boardParameters["corner"] === "top-left") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", "y");
            } else if (besogo.boardParameters["corner"] === "top-right") {
              let transformation = besogo.makeTransformation();
              transformation.vFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea(false, "y");
            } else if (besogo.boardParameters["corner"] === "bottom-left") {
              let transformation = besogo.makeTransformation();
              transformation.hFlip = true;
              besogo.editor.applyTransformation(transformation);
              updateCoordArea("x", false);
            } else if (besogo.boardParameters["corner"] === "bottom-right") {
              //already there
            }
            if (besogo.scaleParameters["orientation"] !== "full-board")
              besogo.boardCanvasSvg.setAttribute(
                "viewBox",
                besogo.boardParameters["boardWidth2"] +
                  " " +
                  besogo.boardParameters["boardHeight2"] +
                  " " +
                  besogo.boardParameters["boardWidth3"] +
                  " " +
                  besogo.boardParameters["boardHeight3"]
              );
            besogo.setStyle("#boardOrientationTL","opacity", ".62");
            besogo.setStyle("#boardOrientationTR","opacity", ".62");
            besogo.setStyle("#boardOrientationBL","opacity", ".62");
            besogo.setStyle("#boardOrientationBR","opacity", "1");
            besogo.setStyle("#boardSpinCounterClockwise","opacity", ".62");
            besogo.setStyle("#boardSpinClockwise","opacity", ".62");
            besogo.boardParameters["corner"] = "bottom-right";
          }
        );
      }
    }

    spinIcon = "boardSpinCounterClockwise";
    makeImageButton(
      "/img/" + spinIcon + ".png",
      "rotate counter-clockwise",
      spinIcon,
      function () {
        besogo.editor.rotateBoard(false);
        besogo.setStyle("#boardSpinCounterClockwise","opacity", "1");
        besogo.setStyle("#boardSpinClockwise","opacity", ".62");
        besogo.setStyle("#boardOrientationTL","opacity", ".62");
        besogo.setStyle("#boardOrientationTR","opacity", ".62");
        besogo.setStyle("#boardOrientationBL","opacity", ".62");
        besogo.setStyle("#boardOrientationBR","opacity", ".62");
      }
    );

    spinIcon = "boardSpinClockwise";
    makeImageButton(
      "/img/" + spinIcon + ".png",
      "rotate clockwise",
      spinIcon,
      function () {
        besogo.editor.rotateBoard(true);
        besogo.setStyle("#boardSpinClockwise","opacity", "1");
        besogo.setStyle("#boardSpinCounterClockwise","opacity", ".62");
        besogo.setStyle("#boardOrientationTL","opacity", ".62");
        besogo.setStyle("#boardOrientationTR","opacity", ".62");
        besogo.setStyle("#boardOrientationBL","opacity", ".62");
        besogo.setStyle("#boardOrientationBR","opacity", ".62");
      }
    );

    if (!besogo.multipleChoice && besogo.multipleChoiceCustom === false) {
      if (passEnabled == 1) {
        makeButtonText(
          "Pass",
          "Pass move",
          function () {
            var tool = editor.getTool();
            if (
              tool !== "navOnly" &&
              tool !== "auto" &&
              tool !== "playB" &&
              tool !== "playW"
            ) {
              editor.setTool("auto"); // Ensures that a move tool is selected
            }
            editor.click(0, 0, false); // Clicking off the board signals a pass
          },
          "besogo-pass-button"
        );
      }
      if (mode == 1 || mode == 5) {
        // The first problem of the training has nothing before it
        if (previousButtonLink)
          makeHyperlinkText("Back","previous problem", previousButtonLink, "besogo-back-button");
        else
          makeHyperlinkText("Back","no previous problem", 0, "besogo-back-button-inactive");
      } else if (mode == 2) {
        makeHyperlinkText(
          "History",
          "history of rating mode",
          "/users/solveHistory/" + besogoUserId,
          "history-button"
        );
      } else if (mode == 3) {
        makeButtonText(
          "Skip",
          "skip problem",
          function () { window.location.href = nextButtonLink; },
          "besogo-next-button"
        );
      }

	  if (mode == 1 || mode == 2)
	  {
		  makeButtonText(
			"Reset",
			"reset the problem",
			function ()
			{
				resetParameters(besogo.editor.getCurrent().parent === null);
				besogo.editor.resetToStart();
				toggleBoardLock(false);
				besogo.editor.setReviewMode(false);
				document.getElementById("status").innerHTML = "";
				document.getElementById("theComment").style.cssText = "display:none;";
				let customAlerts = document.getElementById("customAlerts");
				if (customAlerts && getComputedStyle(customAlerts).display !== "none")
					customAlerts.animate([{ opacity: getComputedStyle(customAlerts).opacity }, { opacity: 0 }], 500).onfinish = function () { customAlerts.style.display = "none"; };
				document.querySelectorAll(".besogo-board").forEach(function (board) { board.classList.remove("besogo-board-red-glow"); });
				besogo.setStyle(".besogo-panels","display", "none");
				if (besogo.scaleParameters["boardCanvasSize"] === "full board")
				{
				  besogo.setStyle(".besogo-board","width", "60%");
				  besogo.setStyle(".besogo-board","margin", "0 auto");
				}
				else if (besogo.scaleParameters["boardCanvasSize"] === "horizontal half board")
				{
				  besogo.setStyle(".besogo-board","width", "78%");
				  besogo.setStyle(".besogo-board","margin", "0 auto");
				  besogo.setStyle(".besogo-board","height", trueBoardHeight);
				}
				else if (besogo.scaleParameters["boardCanvasSize"] === "vertical half board")
				{
				  besogo.setStyle(".besogo-board","width", "30%");
				  besogo.setStyle(".besogo-board","margin", "0 auto");
				}
				else
				{
				  besogo.setStyle(".besogo-board","width", "50%");
				  besogo.setStyle(".besogo-board","margin", "0 auto");
				}

				besogo.setStyle(".besogo-board",
				  "box-shadow",
				  "0 8px 14px 0 rgba(0, 0, 0, 0.3), 0 6px 20px 0 rgba(0, 0, 0, 0.2)"
				);
				besogo.editor.notifyListeners(
				{
				  treeChange: true,
				  navChange: true,
				  stoneChange: true,
				});
			  },
			"besogo-reset-button"
			);
	  }
	  else if (mode == 5)
	  {
		// A training try ends at the first mistake. Reset opens the problem again, so
		// the next try is a fresh one and the server counts it as such.
		makeButtonText(
		  "Reset",
		  "try the problem again",
		  function () { window.location.href = resetButtonLink; },
		  "besogo-reset-button"
		);
	  }
	  if (mode == 1 || mode == 2 || mode == 5)
	  {
		// The training has nowhere to go while its last problem is not solved
		if (nextButtonLink)
		  makeHyperlinkText("Next", "next problem", nextButtonLink, "besogo-next-button");
		else
		  makeHyperlinkText("Next", "solve this problem first", 0, "besogo-next-button-inactive");
	  }
      let reviewButtonId;
      if (editor.getReviewEnabled()) reviewButtonId = "besogo-review-button";
      else reviewButtonId = "besogo-review-button-inactive";
      let trueBoardWidth = null;
      let trueBoardHeight = null;
      let reviewBoardWidth = null;
      let reviewBoardHeight = null;
      let trueRatio = null;

      makeButtonText(
        "Review",
        "review mode",
        function () {
          if (trueBoardHeight === null) {
            trueBoardWidth = document.querySelector(".besogo-board").offsetWidth;
            trueBoardHeight = document.querySelector(".besogo-board").offsetHeight;
          }
          if (editor.getReviewEnabled()) {
            if (!editor.getReviewMode()) {
              besogo.setStyle(".besogo-panels","display", "flex");
              if (
                besogo.scaleParameters["boardCanvasSize"] ===
                "vertical half board"
              ) {
                if (besogo.boardParameters["corner"] === "top-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 1 ||
                    besogo.scaleParameters["rotation"] === 3
                  )
                    besogo.setStyle(".besogo-board","width", "50%");
                } else if (besogo.boardParameters["corner"] === "top-right") {
                  if (
                    besogo.scaleParameters["rotation"] === 0 ||
                    besogo.scaleParameters["rotation"] === 2
                  )
                    besogo.setStyle(".besogo-board","width", "50%");
                }
              } else if (
                besogo.scaleParameters["boardCanvasSize"] ===
                "horizontal half board"
              ) {
                if (besogo.scaleParameters["rotation"] === -1) {
                  besogo.setStyle(".besogo-board","width", "50%");
                } else if (besogo.boardParameters["corner"] === "top-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 0 ||
                    besogo.scaleParameters["rotation"] === 2
                  )
                    besogo.setStyle(".besogo-board","width", "50%");
                } else if (besogo.boardParameters["corner"] === "bottom-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 1 ||
                    besogo.scaleParameters["rotation"] === 3
                  )
                    besogo.setStyle(".besogo-board","width", "50%");
                }
              } else {
                besogo.setStyle(".besogo-board","width", "50%");
              }
              besogo.setStyle(".besogo-board","margin", "0");
              besogo.setStyle(".besogo-board",
                "box-shadow",
                "0 8px 14px 0 rgba(0, 0, 0, 0.3), 0 6px 20px 0 rgba(0, 0, 0, 0.2)"
              );

              if (reviewBoardHeight === null) {
                reviewBoardWidth = document.querySelector(".besogo-board").offsetWidth;
                reviewBoardHeight = document.querySelector(".besogo-board").offsetHeight;
                trueRatio = trueBoardWidth / trueBoardHeight;
                reviewBoardHeight = reviewBoardWidth / trueRatio;
              }
              toggleBoardLock(false);
              editor.setReviewMode(true);
              besogo.editor.notifyListeners({
                treeChange: true,
                navChange: true,
                stoneChange: true,
              }); //fixes tree showing after solve
            } else {
              besogo.setStyle(".besogo-panels","display", "none");
              if (besogo.scaleParameters["boardCanvasSize"] === "full board") {
                besogo.setStyle(".besogo-board","width", "60%");
                besogo.setStyle(".besogo-board","margin", "0 auto");
              } else if (
                besogo.scaleParameters["boardCanvasSize"] ===
                "vertical half board"
              ) {
                if (besogo.scaleParameters["rotation"] === -1) {
                  besogo.setStyle(".besogo-board","width", "30%");
                  besogo.setStyle(".besogo-board","margin", "0 auto");
                } else if (besogo.boardParameters["corner"] === "top-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 0 ||
                    besogo.scaleParameters["rotation"] === 2
                  ) {
                    besogo.setStyle(".besogo-board","width", "30%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  } else {
                    besogo.setStyle(".besogo-board","width", "78%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  }
                } else if (besogo.boardParameters["corner"] === "top-right") {
                  if (
                    besogo.scaleParameters["rotation"] === 1 ||
                    besogo.scaleParameters["rotation"] === 3
                  ) {
                    besogo.setStyle(".besogo-board","width", "30%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  } else {
                    besogo.setStyle(".besogo-board","width", "78%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  }
                }
              } else if (
                besogo.scaleParameters["boardCanvasSize"] ===
                "horizontal half board"
              ) {
                if (besogo.scaleParameters["rotation"] === -1) {
                  besogo.setStyle(".besogo-board","width", "78%");
                  besogo.setStyle(".besogo-board","margin", "0 auto");
                } else if (besogo.boardParameters["corner"] === "top-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 0 ||
                    besogo.scaleParameters["rotation"] === 2
                  ) {
                    besogo.setStyle(".besogo-board","width", "78%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  } else {
                    besogo.setStyle(".besogo-board","width", "30%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  }
                } else if (besogo.boardParameters["corner"] === "bottom-left") {
                  if (
                    besogo.scaleParameters["rotation"] === 1 ||
                    besogo.scaleParameters["rotation"] === 3
                  ) {
                    besogo.setStyle(".besogo-board","width", "78%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  } else {
                    besogo.setStyle(".besogo-board","width", "30%");
                    besogo.setStyle(".besogo-board","margin", "0 auto");
                  }
                }
              } else {
                besogo.setStyle(".besogo-board","width", "50%");
                besogo.setStyle(".besogo-board","margin", "0 auto");
              }
              editor.setReviewMode(false);
            }
            besogo.editor.setControlButtonLock(false);
            // Do NOT reset the board to the start position when toggling review
            // mode: preserve the node the user navigated to so they don't lose
            // their place in the tree.
            document.getElementById("status").innerHTML = "";
            besogo.editor.notifyListeners({
              treeChange: true,
              navChange: true,
              stoneChange: true,
            });
          }
        },
        reviewButtonId
      );
    } else if (besogo.multipleChoiceCustom === "multiple_choice") {
      toggleBoardLock(true);
      let prevButtonId;
      prevButtonId = "besogo-back-button";

      makeHyperlinkText("Back","previous problem", previousButtonLink, prevButtonId);

      makeButtonText(
        besogo.multipleChoiceCustomSetup[0],
        "",
        function () {
          displayMultipleChoiceCustomResult(1);
        },
        "besogo-multipleChoice1"
      );

      makeButtonText(
        besogo.multipleChoiceCustomSetup[1],
        "",
        function () {
          displayMultipleChoiceCustomResult(2);
        },
        "besogo-multipleChoice2"
      );
      if (besogo.multipleChoiceCustomSetup[2] != "") {
        makeButtonText(
          besogo.multipleChoiceCustomSetup[2],
          "",
          function () {
            displayMultipleChoiceCustomResult(3);
          },
          "besogo-multipleChoice3"
        );
      }
      if (besogo.multipleChoiceCustomSetup[3] != "") {
        makeButtonText(
          besogo.multipleChoiceCustomSetup[3],
          "",
          function () {
            displayMultipleChoiceCustomResult(4);
          },
          "besogo-multipleChoice4"
        );
      }
      makeHyperlinkText("Next", "next problem", nextButtonLink, "besogo-next-button");
    } else {
      toggleBoardLock(true);
      let prevButtonId;
      prevButtonId = "besogo-back-button";

      makeHyperlinkText("Back","previous problem", previousButtonLink, prevButtonId);

      makeButtonText(
        besogo.boardInverted ? "White is dead" : "Black is dead",
        "",
        function () {
          displayMultipleChoiceResult(1);
        },
        "besogo-multipleChoice1"
      );

      makeButtonText(
        besogo.boardInverted ? "Black is dead" : "White is dead",
        "",
        function () {
          displayMultipleChoiceResult(2);
        },
        "besogo-multipleChoice2"
      );

      makeButtonText(
        "Unsettled",
        "",
        function () {
          displayMultipleChoiceResult(3);
        },
        "besogo-multipleChoice3"
      );

      makeButtonText(
        "Seki",
        "",
        function () {
          displayMultipleChoiceResult(4);
        },
        "besogo-multipleChoice4"
      );

      makeHyperlinkText("Next", "next problem", nextButtonLink, "besogo-next-button");
    }

    makeImageButton(
      "/img/colorOrientation.png",
      "change the color of your stones",
      "colorOrientation",
      function () {
        besogo.boardInverted = !besogo.boardInverted;
        let swapColorWords = besogo.swapColorWords;
        let transformation = besogo.makeTransformation();
        transformation.invertColors = true;
        // Any true-color text shown to the player must follow the board's colors.
        ["descriptionText", "theComment"].forEach(function (id) {
          let el = document.getElementById(id);
          if (el) el.textContent = swapColorWords(el.textContent);
        });
        // Multiple-choice answers (custom multiple-choice and semeai share these ids).
        ["besogo-multipleChoice1", "besogo-multipleChoice2", "besogo-multipleChoice3", "besogo-multipleChoice4"].forEach(function (id) {
          let el = document.getElementById(id);
          if (el) el.value = swapColorWords(el.value);
        });
        besogo.editor.applyTransformation(transformation);
      }
    );

    if (!besogoNoLogin) {
      favButtonLit = window.userSets && window.userSets.some(s => s.contains);
      let heart = document.createElement("span");
      heart.id = "favButton";
      heart.className = "fav-heart";
      heart.style.cssText = "cursor:pointer;font-size:18px;margin:12px 0 0 1px;user-select:none";
      heart.title = "mark as favorite";
      heart.textContent = favButtonLit ? '❤️' : '🤍';
      heart.addEventListener("click", function () {
        let anyContain = window.userSets && window.userSets.some(s => s.contains);
        if (anyContain) {
          // Remove from ALL sets
          let promises = [];
          window.userSets.forEach(s => {
            if (s.contains) {
              s.contains = false;
              promises.push(
                fetch("/sets/removeTsumego/" + s.id, {
                  method: "POST",
                  headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest" },
                  body: "tsumego_id=" + tsumegoID
                })
                .then(r => {
                  if (!r.ok)
                    throw new Error("Remove from favorites failed: " + r.status);
                  return r.json();
                })
                .then(data => {
                  if (data && data.contains !== undefined)
                    s.contains = data.contains;
                })
                .catch(err => {
                  s.contains = true;
                  throw err;
                })
              );
            }
          });
          updateFavButton(false);
          Promise.all(promises)
            .then(() => updateFavButton(window.userSets.some(x => x.contains)))
            .catch(err => {
              console.error('Failed to update favorites', err);
              updateFavButton(window.userSets.some(x => x.contains));
            });
        } else {
          // Add to the default set
          let fav = window.userSets && window.userSets.find(s => s.isDefault);
          if (fav)
            fav.contains = true;
          updateFavButton(true);

          fetch("/sets/addTsumego/favorites", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest" },
            body: "tsumego_id=" + tsumegoID
          })
          .then(r => {
            if (!r.ok)
              throw new Error("Add to favorites failed: " + r.status);
            return r.json();
          })
          .then(data => {
            if (!data)
              return;
            // The server names the set it added to, which it creates when the player has no default set
            let added = Array.isArray(window.userSets) && window.userSets.find(s => s.id == data.set_id);
            if (fav && fav !== added)
              fav.contains = false;
            if (added)
              added.contains = true;
            else if (Array.isArray(window.userSets) && data.set_id)
              window.userSets.push({ id: data.set_id, title: data.title, contains: true, isDefault: true });
            updateFavButton(window.userSets.some(x => x.contains));
          })
          .catch(err => {
            console.error('Failed to update favorites', err);

            if (fav)
              fav.contains = false;
            updateFavButton(window.userSets.some(x => x.contains));
          });
        }
      });
      container.appendChild(heart);

      // Dropdown arrow, only if user has more than one set to choose from
      if (userSets && userSets.length > 1) {
        let arrow = document.createElement("span");
        arrow.id = "favDropdownArrow";
        arrow.style.cssText = "cursor:pointer;font-size:18px;margin:15px 5px 0 0;vertical-align:middle;user-select:none";
        arrow.title = "save to set";
        arrow.textContent = "▼";
        arrow.addEventListener("click", function () { showFavDropdown(); });
        container.appendChild(arrow);
      }
    }
    if (besogo.scaleParameters["orientation"] !== "full-board")
      makeBlankSpace(2);
    makeAuthorText("author-notice");
  }

  function makeEditorToolButtons(container, editor) {
    svg = makeButtonSVG(
      "auto",
      "Auto-play/navigate\n" +
        "crtl+click to force ko, suicide, overwrite\n" +
        "shift+click to jump to move"
    ); // Auto-play/nav tool button
    svg.appendChild(makeYinYang(0, 0));

    svg = makeButtonSVG(
      "addB",
      "Set black\nshift+click addWhite\nctrl+click to play"
    ); // Add black button
    element = besogo.svgEl("g");
    element.appendChild(besogo.svgStone(-15, -15, -1, 15)); // Black stone
    element.appendChild(besogo.svgStone(15, -15, 1, 15)); // White stone
    element.appendChild(besogo.svgStone(-15, 15, 1, 15)); // White stone
    element.appendChild(besogo.svgStone(15, 15, -1, 15)); // Black stone
    svg.appendChild(element);

    svg = makeButtonSVG("circle", "Circle"); // Circle markup button
    svg.appendChild(besogo.svgCircle(0, 0, "black"));

    svg = makeButtonSVG("square", "Square"); // Square markup button
    svg.appendChild(besogo.svgSquare(0, 0, "black"));

    svg = makeButtonSVG("triangle", "Triangle"); // Triangle markup button
    svg.appendChild(besogo.svgTriangle(0, 0, "black"));

    svg = makeButtonSVG("cross", "Cross"); // Cross markup button
    svg.appendChild(besogo.svgCross(0, 0, "black"));

    svg = makeButtonSVG("block", "Block"); // Block markup button
    svg.appendChild(besogo.svgBlock(0, 0, "black"));

    svg = makeButtonSVG("clrMark", "Clear mark"); // Clear markup button
    element = besogo.svgEl("g");
    element.appendChild(besogo.svgTriangle(0, 0, besogo.GREY));
    element.appendChild(besogo.svgCross(0, 0, besogo.RED));
    svg.appendChild(element);

    svg = makeButtonSVG("label", "Label"); // Label markup button
    svg.appendChild(besogo.svgLabel(0, 0, "black", "A1"));

    labelText = document.createElement("input"); // Label entry text field
    labelText.type = "text";
    labelText.title = "Next label";
    labelText.onblur = function () {
      editor.setLabel(labelText.value);
    };
    labelText.addEventListener("keydown", function (evt) {
      evt = evt || window.event;
      evt.stopPropagation(); // Stop keydown propagation when in focus
    });
    container.appendChild(labelText);
    makeButtonText("Pass", "Pass move", function () {
      var tool = editor.getTool();
      if (
        tool !== "navOnly" &&
        tool !== "auto" &&
        tool !== "playB" &&
        tool !== "playW"
      ) {
        editor.setTool("auto"); // Ensures that a move tool is selected
      }
      editor.click(0, 0, false); // Clicking off the board signals a pass
    });
    makeButtonText("Cut", "Remove branch", function () {
      editor.cutCurrent();
    });
    makeButtonText("Raise", "Raise variation", function () {
      editor.promote();
    });
    makeButtonText("Lower", "Lower variation", function () {
      editor.demote();
    });
    makeButtonText("H Flip", "Flip horizontally", function () {
      let transformation = besogo.makeTransformation();
      transformation.hFlip = true;
      editor.applyTransformation(transformation);
    });
    makeButtonText("V Flip", "Flip vertically", function () {
      let transformation = besogo.makeTransformation();
      transformation.vFlip = true;
      editor.applyTransformation(transformation);
    });

    makeButtonText("Rotate", "Rotate the board clockwise", function () {
      let transformation = besogo.makeTransformation();
      transformation.rotateClockwise = true;
      editor.applyTransformation(transformation);
    });

    makeButtonText(
      "Invert",
      "Invert colors of all stones and moves.",
      function () {
        let transformation = besogo.makeTransformation();
        transformation.invertColors = true;
        editor.applyTransformation(transformation);
      }
    );

    makeButtonText(
      "Invert firstMove",
      "Invert the color of the first move",
      function () {
        let transformation = besogo.makeTransformation();
        transformation.invertColors = true;
        editor.getRoot().firstMove = transformation.applyOnColor(
          editor.getRoot().firstMove
        );
        editor.notifyListeners({
          treeChange: true,
          navChange: true,
          stoneChange: true,
        });
        editor.edited = true;
      }
    );
    makeSaveForm();
  }

  function makeEditorToolButtons2(container, editor) {
    //removed buttons - the other editor should be used
  }

  //remove all rotations when another transformation is clicked
  function revertRotations(corner, rotation = 0) {
    if (rotation === -1) return null;
    if (besogo.scaleParameters["boardCanvasSize"] === "vertical half board") {
      besogo.setStyle(".besogo-board","width", "30%");
      besogo.setStyle(".besogo-board","margin", "0 auto");
    } else if (
      besogo.scaleParameters["boardCanvasSize"] === "horizontal half board"
    ) {
      besogo.setStyle(".besogo-board","width", "78%");
      besogo.setStyle(".besogo-board","margin", "0 auto");
    }
    if (corner === "top-right") rotation++;
    else if (corner === "bottom-left") rotation--;
    else if (corner === "bottom-right") {
      rotation += 2;
      if (rotation === 4) rotation = 0;
      else if (rotation === 5) rotation = 1;
    }
    if (rotation > 3) rotation = 0;
    else if (rotation < 0) rotation = 3;

    if (rotation === 3) {
      let transformation = besogo.makeTransformation();
      transformation.rotateCounterClockwise = true;
      besogo.editor.applyTransformation(transformation);
    } else if (rotation === 1) {
      let transformation = besogo.makeTransformation();
      transformation.rotateClockwise = true;
      besogo.editor.applyTransformation(transformation);
    } else if (rotation === 2) {
      let transformation = besogo.makeTransformation();
      transformation.rotateClockwise = true;
      besogo.editor.applyTransformation(transformation);
      transformation = besogo.makeTransformation();
      transformation.rotateClockwise = true;
      besogo.editor.applyTransformation(transformation);
    }
    besogo.scaleParameters["rotation"] = -1;
    besogoRotation = -1;
  }

  // Creates a button holding an SVG image
  function makeButtonSVG(tool, tooltip) {
    var button = document.createElement("button"),
      svg = besogo.svgEl("svg", {
        // Icon container
        width: "100%",
        height: "100%",
        viewBox: "-55 -55 110 110",
      }), // Centered on (0, 0)
      selected = besogo.svgEl("rect", {
        // Selection rectangle
        x: -50, // Center on (0, 0)
        y: -50,
        width: 100,
        height: 100,
        fill: "none",
        "stroke-width": 8,
        stroke: besogo.GOLD,
        rx: 20, // Rounded rectangle
        ry: 20, // Thanks, Steve
        visibility: "hidden",
      });

    container.appendChild(button);
    button.appendChild(svg);
    button.onclick = function () {
      if (tool === "auto" && editor.getTool() === "auto")
        editor.setTool("navOnly");
      else editor.setTool(tool);
    };
    button.title = tooltip;
    selectors[tool] = selected;
    svg.appendChild(selected);
    return svg; // Returns reference to the icon container
  }

  // Creates text button
  function makeButtonText(text, tip, callback, id = null) {
    var button = document.createElement("input");
    button.type = "button";
    button.value = text;
    button.title = tip;
    if (id !== null) button.id = id;
    button.onclick = callback;
    container.appendChild(button);
    return button;
  }

  // Creates invisible form for submitting a file save
  function makeSaveForm() {
    const formNode = document.createElement("form");
    formNode.id = "sgfForm";
    formNode.method = "post";
    formNode.style.display = "none";
    if (besogo.editingOnlineTsumego) {
      formNode.action = window.origin + "/sgf/upload/" + besogo.setConnectionID;
    }
    const inputNode = document.createElement("input");
    inputNode.id = "sgfInput";
    inputNode.type = "text";
    inputNode.name = "sgfForBesogo";
    formNode.appendChild(inputNode);
    // The SGF version the tree was loaded from and the transformation (normalising, corner,
    // rotation, colours) from its coordinates to the saved ones, so comments can follow
    for (const [id, name] of [["sgfSourceInput", "sourceSgfID"], ["sgfTransformationInput", "transformationFromSource"], ["sgfDescriptionInput", "description"]]) {
      const hiddenNode = document.createElement("input");
      hiddenNode.id = id;
      hiddenNode.type = "hidden";
      hiddenNode.name = name;
      formNode.appendChild(hiddenNode);
    }
    container.appendChild(formNode);
    return formNode;
  }

  // Creates hyperlink button
  function makeHyperlinkText(text, tip, link, id) {
    var button = document.createElement("a");
    if (link !== 0) button.href = link;
    button.title = tip;
    button.id = id;
    button.text = text;
    container.appendChild(button);
    return button;
  }

  // Creates image button
  function makeImageButton(src, tip, id, callback) {
    var img = document.createElement("img");
    img.src = src;
    img.title = tip;
    img.id = id;
    img.onclick = callback;
    container.appendChild(img);
    return img;
  }

  function makeAuthorText(id, name) {
    var div = document.createElement("div");
    div.id = id;
    container.appendChild(div);
    besogo.setText("#author-notice", author != "" ? "File by " + author : "");
    return div;
  }

  //for centering navigation objects
  function makeBlankSpace(i = 1) {
    while (i > 0) {
      var div = document.createElement("div");
      div.className = "besogo-blank-space";
      container.appendChild(div);
      i--;
    }
    return div;
  }

  // Callback for updating tool state and label
  function toolStateUpdate(msg) {
    if (msg.label && labelText) labelText.value = msg.label;
    if (msg.tool)
      for (let tool in selectors) // Update which tool is selected
        if (selectors.hasOwnProperty(tool))
          if (msg.tool === tool)
            selectors[tool].setAttribute("visibility", "visible");
          else selectors[tool].setAttribute("visibility", "hidden");
  }

  // Draws a yin yang
  function makeYinYang(x, y) {
    var element = besogo.svgEl("g");

    // Draw black half circle on right side
    element.appendChild(
      besogo.svgEl("path", {
        d: "m" + x + "," + (y - 44) + " a44 44 0 0 1 0,88z",
        stroke: "none",
        fill: "black",
      })
    );

    // Draw white part of ying yang on left side
    element.appendChild(
      besogo.svgEl("path", {
        d: "m" + x + "," + (y + 44) + "a44 44 0 0 1 0,-88a22 22 0 0 1 0,44z",
        stroke: "none",
        fill: "white",
      })
    );

    // Draw round part of black half of ying yang
    element.appendChild(
      besogo.svgEl("circle", {
        cx: x,
        cy: y + 22,
        r: 22,
        stroke: "none",
        fill: "black",
      })
    );

    return element;
  }

  // updates the area of valid coordinates
  function updateCoordArea(x = false, y = false) {
    let convertedCoords = besogo.coord["western"](
      besogo.scaleParameters["boardCoordSize"],
      besogo.scaleParameters["boardCoordSize"]
    );
    if (x != false) {
      besogo.coordArea["lowestX"] =
        besogo.scaleParameters["boardCoordSize"] -
        besogo.coordArea["lowestX"] -
        1;
      besogo.coordArea["highestX"] =
        besogo.scaleParameters["boardCoordSize"] -
        besogo.coordArea["highestX"] -
        1;
      besogo.coordArea["lowestXconverted"] =
        convertedCoords.x[besogo.coordArea["lowestX"] + 1];
      besogo.coordArea["highestXconverted"] =
        convertedCoords.x[besogo.coordArea["highestX"] + 1];
    }
    if (y != false) {
      besogo.coordArea["lowestY"] =
        besogo.scaleParameters["boardCoordSize"] -
        besogo.coordArea["lowestY"] -
        1;
      besogo.coordArea["highestY"] =
        besogo.scaleParameters["boardCoordSize"] -
        besogo.coordArea["highestY"] -
        1;
      besogo.coordArea["lowestYconverted"] =
        convertedCoords.y[besogo.coordArea["lowestY"] + 1];
      besogo.coordArea["highestYconverted"] =
        convertedCoords.y[besogo.coordArea["highestY"] + 1];
    }
  }

  // Heart dropdown: show popover with all user sets
  function showFavDropdown() {
    let existing = document.getElementById("fav-dropdown");
    if (existing) { existing.remove(); return; }

    // where the arrow is on the page, not in the window
    let arrowBox = document.getElementById("favDropdownArrow").getBoundingClientRect();
    let div = document.createElement("div");
    div.id = "fav-dropdown";
    div.className = "dropdown";
    div.style.top = (arrowBox.top + window.pageYOffset - document.documentElement.clientTop + 22) + "px";
    div.style.left = (arrowBox.left + window.pageXOffset - document.documentElement.clientLeft - 150) + "px";
    document.body.append(div);

    if (!window.userSets || window.userSets.length === 0) {
      div.insertAdjacentHTML("beforeend", '<div class="dropdown__empty">No sets yet.</div>');
    } else {
      window.userSets.forEach(function (s) {
        let row = document.createElement("label");
        row.className = "dropdown__row";
        let cb = document.createElement("input");
        cb.type = "checkbox";
        cb.style.marginRight = "4px";
        cb.checked = s.contains;
        cb.addEventListener("change", function () {
          let url = this.checked
            ? "/sets/addTsumego/" + s.id
            : "/sets/removeTsumego/" + s.id;
          fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest" },
            body: "tsumego_id=" + tsumegoID
          }).then(r => r.json()).then(data => {
            if (data.contains !== undefined) {
              s.contains = data.contains;
              // Heart lit if ANY set still contains this tsumego
              let anyContain = window.userSets.some(x => x.contains);
              updateFavButton(anyContain);
            }
          });
        });
        row.append(cb, s.title);
        div.append(row);
      });
    }

    document.addEventListener("click", function closeOnClickOutside(e) {
      if (!(e.target instanceof Element && e.target.closest("#fav-dropdown, #favDropdownArrow"))) {
        let dropdown = document.getElementById("fav-dropdown");
        if (dropdown) dropdown.remove();
        document.removeEventListener("click", closeOnClickOutside);
      }
    });
  }

  // Update the heart button state and fire the like-burst on a fresh like
  function updateFavButton(liked) {
    var btn = document.getElementById("favButton");
    if (!btn)
      return;
    btn.textContent = liked ? '❤️' : '🤍';
    if (liked && !favButtonLit) {
      btn.classList.remove('pop');
      void btn.offsetWidth; // restart the pop animation
      btn.classList.add('pop');
      burstLikeAnimation(btn);
    }
    favButtonLit = liked;
  }

  // Rewarding burst: particles + shockwave ring from the heart
  function burstLikeAnimation(button) {
    var rect = button.getBoundingClientRect();
    var cx = rect.left + rect.width / 2;
    var cy = rect.top + rect.height / 2;
    var colors = ['#ff4466', '#ff8fab', '#ffd166', '#c77dff', '#4cc9f0', '#80ed99'];
    var count = 16;
    for (var i = 0; i < count; i++) {
      var p = document.createElement('span');
      var angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.6;
      var dist = 34 + Math.random() * 44;
      p.className = 'fav-burst-particle';
      p.style.left = cx + 'px';
      p.style.top = cy + 'px';
      p.style.setProperty('--dx', (Math.cos(angle) * dist).toFixed(1) + 'px');
      p.style.setProperty('--dy', (Math.sin(angle) * dist).toFixed(1) + 'px');

      var kind = i % 4;
      if (kind === 0) {
        // Emoji heart, no rotation so it stays sharp
        p.classList.add('fav-burst-particle--heart');
        p.textContent = '❤';
        p.style.fontSize = '16px';
      } else if (kind === 2) {
        // Colored sparkle glyph for a bit of the earlier sparkle feel
        p.classList.add('fav-burst-particle--sparkle');
        p.textContent = '✦';
        p.style.color = colors[i % colors.length];
        p.style.fontSize = '14px';
      } else {
        // Solid shapes render sharp (text glyphs looked soft when scaled)
        p.classList.add(kind === 1 ? 'fav-burst-particle--round' : 'fav-burst-particle--shape');
        var size = (6 + Math.random() * 6).toFixed(0);
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.background = colors[i % colors.length];
      }
      document.body.appendChild(p);
      (function (node) { setTimeout(function () { node.remove(); }, 600); })(p);
    }
    var ring = document.createElement('span');
    ring.className = 'fav-burst-ring';
    ring.style.left = cx + 'px';
    ring.style.top = cy + 'px';
    document.body.appendChild(ring);
    setTimeout(function () { ring.remove(); }, 550);
  }
};
