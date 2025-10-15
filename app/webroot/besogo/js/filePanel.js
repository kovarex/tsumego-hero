besogo.makeFilePanel = function (container, editor) {
  "use strict";
  var fileChooser, // Reference to the file chooser element
    diffFileChooser,
    element, // Scratch variable for creating elements
    WARNING = "Everything not saved will be lost",
    compareButton = null;

  if (!besogo.isEmbedded && besogo.onSite == null) {
    makeNewBoardButton(9); // New 9x9 board button
    makeNewBoardButton(13); // New 13x13 board button
    makeNewBoardButton(19); // New 19x19 board button
    makeNewBoardButton("?"); // New custom board button
  }

  // Hidden file chooser element
  fileChooser = makeFileChooser();
  container.appendChild(fileChooser);

  diffFileChooser = makeDiffFileChooser();
  container.appendChild(diffFileChooser);

  if (!besogo.isEmbedded && besogo.onSite == null) {
    // Load file button
    element = document.createElement("input");
    element.type = "button";
    element.value = "Open";
    element.title = "Import SGF";
    element.onclick = function () // Bind click to the hidden file chooser
    {
      if (editor.wasEdited() && !confirm("Changes were made, throw it away?"))
        return;
      fileChooser.click();
    };
    container.appendChild(element);
  }

  if (!besogo.isEmbedded && besogo.onSite == null) {
    // Load file button
    compareButton = document.createElement("input");
    compareButton.type = "button";
    compareButton.value = "Compare";
    compareButton.title = "Import SGF";
    compareButton.disabled = true;
    compareButton.onclick = function () // Bind click to the hidden file chooser
    {
      diffFileChooser.click();
    };
    container.appendChild(compareButton);
  }

  // Download file button
  element = document.createElement("input");
  element.type = "button";
  element.title = "Export SGF";
  if (!besogo.isEmbedded) {
    element.value = "Download";
    element.onclick = function () {
      if (!checkCompatibility()) return;
      let fileName = prompt("Save file as", "export");
      if (fileName) {
        // Canceled or empty string does nothing
        saveFile(fileName + ".sgf", besogo.composeSgf(editor));
        editor.resetEdited();
      }
    };
    container.appendChild(element);
  }

  if (!besogo.isEmbedded && besogo.onSite == null) {
    // Save file button
    element = document.createElement("input");
    element.type = "button";
    element.value = "Save expanded";
    element.title = "Export SGF export with all virtual variations expanded";
    element.onclick = function () {
      if (!checkCompatibility()) return;
      var fileName = prompt("Save file as", "export");
      if (fileName) {
        // Canceled or empty string does nothing
        saveFile(fileName + ".sgf", besogo.composeSgf(editor, true));
        editor.resetEdited();
      }
    };
    container.appendChild(element);
  }

  if (!besogo.isEmbedded && besogo.onSite !== null) {
    element = document.createElement("input");
    element.type = "button";
    element.value = "Save";
    element.title = "Go back to Tsumego Hero and save the problem";
    element.onclick = function () {
      if (!checkCompatibility()) return;
      saveFile("export", besogo.composeSgf(editor), 2);
      editor.resetEdited();
    };
    container.appendChild(element);
  }

  // Makes a new board button
  function makeNewBoardButton(size) {
    var button = document.createElement("input");
    button.type = "button";
    button.value = size + "x" + size;
    if (size === "?") {
      // Make button for custom sized board
      button.title = "New custom size board";
      button.onclick = function () {
        var input = prompt(
          "Enter custom size for new board" +
            "\n" +
            (editor.wasEdited() ? WARNING : ""),
          "19:19"
        );
        if (input) {
          // Canceled or empty string does nothing
          var size = besogo.parseSize(input);
          editor.loadRoot(besogo.makeGameRoot(size.x, size.y));
          editor.setGameInfo({});
        }
      };
    } else {
      // Make button for fixed size board
      button.title = "New " + size + "x" + size + " board";
      button.onclick = function () {
        if (!editor.wasEdited() || confirm(button.title + "?\n" + WARNING)) {
          editor.loadRoot(besogo.makeGameRoot(size, size));
          editor.setGameInfo({});
        }
      };
    }
    container.appendChild(button);
  }

  // Creates the file selector
  function makeDiffFileChooser() {
    let chooser = document.createElement("input");
    chooser.type = "file";
    chooser.style.display = "none"; // Keep hidden
    chooser.onchange = readDiffFile; // Read, parse and load on file select
    return chooser;
  }

  // Creates the file selector
  function makeFileChooser() {
    let chooser = document.createElement("input");
    chooser.type = "file";
    chooser.style.display = "none"; // Keep hidden
    chooser.onchange = readFile; // Read, parse and load on file select
    return chooser;
  }

  // Reads, parses and loads an SGF file
  function readDiffFile(evt) {
    let file = evt.target.files[0], // Selected file
      reader = new FileReader();
    let newChooser = makeDiffFileChooser(); // Create new file input to reset selection

    container.replaceChild(newChooser, diffFileChooser); // Replace with the reset selector
    diffFileChooser = newChooser;

    reader.onload = function (
      e // Parse and load game tree
    ) {
      let sgf;
      try {
        sgf = besogo.parseSgf(e.target.result);
      } catch (error) {
        alert("SGF parse error at " + error.at + ":\n" + error.message);
        return;
      }
      besogo.loadSgf(sgf, editor, OPEN_FOR_DIFF);
    };
    reader.readAsText(file); // Initiate file read
  }

  // Reads, parses and loads an SGF file
  function readFile(evt) {
    let file = evt.target.files[0], // Selected file
      reader = new FileReader();

    let newChooser = makeFileChooser(); // Create new file input to reset selection

    container.replaceChild(newChooser, fileChooser); // Replace with the reset selector
    fileChooser = newChooser;

    reader.onload = function (
      e // Parse and load game tree
    ) {
      let sgf;
      try {
        sgf = besogo.parseSgf(e.target.result);
      } catch (error) {
        alert("SGF parse error at " + error.at + ":\n" + error.message);
        return;
      }
      besogo.loadSgf(sgf, editor);
      compareButton.disabled = false;
    };
    reader.readAsText(file); // Initiate file read
  }

  function checkCompatibility() {
    let checkResult = editor
      .getRoot()
      .checkTsumegoHeroCompatibility(editor.getRoot());
    if (!checkResult) return true;
    editor.setCurrent(checkResult.node);
    window.alert(checkResult.message);
    return false;
  }

  // Composes SGF file and initializes download
  function saveFile(fileName, text, redirect = 0) {
    var link = document.createElement("a"),
      blob = new Blob([text], {
        encoding: "UTF-8",
        type: "text/plain;charset=UTF-8",
      });

    link.download = fileName; // Set download file name
    link.href = URL.createObjectURL(blob);
    link.style.display = "none"; // Make link hidden
    if (redirect === 0) {
      container.appendChild(link); // Add link to ensure that clicking works
      link.click(); // Click on link to initiate download
      container.removeChild(link); // Immediately remove the link
    } else {
      text = text.replaceAll(";", "@");
      text = text.replaceAll("\n", "€");
      text = text.replaceAll("+", "%2B");
      text = text.replaceAll("ß", "ss");

      document.getElementById("sgfInput").value = text;
      document.getElementById("sgfForm").submit();
    }
  }
};
