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
      koExtraThreatsCountLabel = null,
      koExtraThreatsPlusButton = null,
      koExtraThreatsMinusButton = null,
      koApproachesCountLabel = null,
      koApproachesPlusButton = null,
      koApproachesMinusButton = null,
      tenThousandYearsKoSelection = null,
      bent4InTheCornerSelection = null,
      deadInDoubleKoSelection = null,
      deadInSuperKoSelection = null,
      aliveInDoubleKoSelection = null,
      aliveInSuperKoSelection = null,
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
  if (!besogo.isEmbedded)
    parentDiv.appendChild(correctButtonSpan);

  parentDiv.appendChild(createGoalTable());
  if (!besogo.isEmbedded)
  {
	  parentDiv.appendChild(statusLabel);
	  parentDiv.appendChild(statusTable);
	  parentDiv.appendChild(jumpToBranchWithoutStatusButton);
	  container.appendChild(makeCommentButton());
  }
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

  function isKoStatus()
  {
    if (!editor.getCurrent().statusSource)
      return false;
    if (editor.getCurrent().statusSource.blackFirst.type != STATUS_KO)
      return false;
    return true;
  }

  function updateKoApproachesCountLabel()
  {
    koApproachesCountLabel.textContent = isKoStatus() ? editor.getCurrent().status.blackFirst.getApproachCount().toString() : ' ';
  }

  function updateKoExtraThreatsCountLabel()
  {
    koExtraThreatsCountLabel.textContent = isKoStatus() ? editor.getCurrent().status.blackFirst.getKoStr() : ' ';
  }

  function createStatusTable()
  {
    let table = document.createElement('table');

    noneSelection = createRadioButtonRow(table, 'none', STATUS_NONE);
    deadSelection = createRadioButtonRow(table, 'dead', STATUS_DEAD);
    bent4InTheCornerSelection = createRadioButtonRow(table, 'Bent 4 in the corner', STATUS_BENT_FOUR_IN_THE_CORNER);
    deadInSuperKoSelection = createRadioButtonRow(table, 'Dead in super ko', STATUS_DEAD_IN_SUPER_KO);
    deadInDoubleKoSelection = createRadioButtonRow(table, 'Dead in double ko', STATUS_DEAD_IN_DOUBLE_KO);

    let koSettingsSpan = document.createElement('span');

    let koApproachesLabel = document.createElement('label');
    koApproachesLabel.textContent = 'Approaches: ';
    koSettingsSpan.appendChild(koApproachesLabel);

    koApproachesCountLabel = document.createElement('label');
    koApproachesCountLabel.textContent = '0';
    koSettingsSpan.appendChild(koApproachesCountLabel);

    function changeApproacheCount(change)
    {
      if (!editor.getCurrent().statusSource)
        return;
      if (editor.getCurrent().statusSource.blackFirst.type != STATUS_KO)
        return;
      editor.getCurrent().statusSource.blackFirst.approaches = editor.getCurrent().statusSource.blackFirst.approaches + change;
      besogo.updateCorrectValues(editor.getRoot());
      updateKoApproachesCountLabel();
      updateStatusLabel();
      editor.notifyListeners({ treeChange: true});
    }

    koApproachesMinusButton = document.createElement('input');
    koApproachesMinusButton.type = 'button';
    koApproachesMinusButton.value = '-';
    koApproachesMinusButton.onclick = function(event) { changeApproacheCount(-1); }
    koSettingsSpan.appendChild(koApproachesMinusButton);

    koApproachesPlusButton = document.createElement('input');
    koApproachesPlusButton.type = 'button';
    koApproachesPlusButton.value = '+';
    koApproachesPlusButton.onclick = function(event) { changeApproacheCount(1); }
    koSettingsSpan.appendChild(koApproachesPlusButton);

    let koExtraThreatsLabel = document.createElement('label');
    koExtraThreatsLabel.textContent = 'Threats: ';
    koSettingsSpan.appendChild(koExtraThreatsLabel);

    koExtraThreatsCountLabel = document.createElement('label');
    koExtraThreatsCountLabel.textContent = '0';
    koSettingsSpan.appendChild(koExtraThreatsCountLabel);

    function changeExtraThreatsCount(change)
    {
       if (!editor.getCurrent().statusSource)
        return;
      if (editor.getCurrent().statusSource.blackFirst.type != STATUS_KO)
        return;
      editor.getCurrent().statusSource.blackFirst.extraThreats = editor.getCurrent().statusSource.blackFirst.extraThreats + change;
      besogo.updateCorrectValues(editor.getRoot());
      updateKoExtraThreatsCountLabel();
      updateStatusLabel();
      editor.notifyListeners({ treeChange: true});
    }

    koExtraThreatsMinusButton = document.createElement('input');
    koExtraThreatsMinusButton.type = 'button';
    koExtraThreatsMinusButton.value = '-';
    koExtraThreatsMinusButton.onclick = function(event) { changeExtraThreatsCount(-1); }
    koSettingsSpan.appendChild(koExtraThreatsMinusButton);

    koExtraThreatsPlusButton = document.createElement('input');
    koExtraThreatsPlusButton.type = 'button';
    koExtraThreatsPlusButton.value = '+';
    koExtraThreatsPlusButton.onclick = function(event) { changeExtraThreatsCount(1); }
    koSettingsSpan.appendChild(koExtraThreatsPlusButton);

    koSelection = createRadioButtonRow(table, 'ko', STATUS_KO, koSettingsSpan);
    tenThousandYearsKoSelection = createRadioButtonRow(table, '10 000 years ko', STATUS_TEN_THOUSAND_YEARS_KO);

    let sekiSenteSpan = document.createElement('span');
    sekiSente = createCheckBox(sekiSenteSpan, 'sente', function(event)
    {
      editor.getCurrent().setStatusSource(besogo.loadStatusFromString('SEKI' + (event.target.checked ? '+' : '')));
      updateStatusLabel();
    });

    sekiSelection = createRadioButtonRow(table, 'seki', STATUS_SEKI, sekiSenteSpan);
    aliveInDoubleKoSelection = createRadioButtonRow(table, 'Alive in double ko', STATUS_ALIVE_IN_DOUBLE_KO);
    aliveInSuperKoSelection = createRadioButtonRow(table, 'Alive in super ko', STATUS_ALIVE_IN_SUPER_KO);
    aliveSelection = createRadioButtonRow(table, 'alive', STATUS_ALIVE);

    return table;
  }

  function updateStatusEditability()
  {
    let editable = !editor.getCurrent().hasChildIncludingVirtual() && editor.getRoot().goal != GOAL_NONE;
    setEnabledCarefuly(noneSelection, editable);
    setEnabledCarefuly(deadSelection, editable);
    setEnabledCarefuly(bent4InTheCornerSelection, editable);
    setEnabledCarefuly(deadInSuperKoSelection, editable);
    setEnabledCarefuly(deadInDoubleKoSelection, editable);
    setEnabledCarefuly(koSelection, editable);
    setEnabledCarefuly(koExtraThreatsMinusButton,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(koExtraThreatsPlusButton,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(koApproachesMinusButton,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(koApproachesPlusButton,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_KO);
    setEnabledCarefuly(tenThousandYearsKoSelection, editable);
    setEnabledCarefuly(sekiSelection, editable);
    setEnabledCarefuly(sekiSente,
                       editable &&
                       editor.getCurrent().statusSource &&
                       editor.getCurrent().statusSource.blackFirst.type == STATUS_SEKI);
    setEnabledCarefuly(aliveInDoubleKoSelection, editable);
    setEnabledCarefuly(aliveInSuperKoSelection, editable);
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

    if (editor.getCurrent().status.blackFirst.type == STATUS_BENT_FOUR_IN_THE_CORNER)
    {
      bent4InTheCornerSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_DEAD_IN_SUPER_KO)
    {
      deadInSuperKoSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_DEAD_IN_DOUBLE_KO)
    {
      deadInDoubleKoSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_KO)
    {
      koSelection.checked = true;
      updateKoExtraThreatsCountLabel();
      updateKoApproachesCountLabel();
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_SEKI)
    {
      sekiSelection.checked = true;
      sekiSente.checked = editor.getCurrent().status.blackFirst.sente;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_TEN_THOUSAND_YEARS_KO)
    {
      tenThousandYearsKoSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_ALIVE_IN_DOUBLE_KO)
    {
      aliveInDoubleKoSelection.checked = true;
      return;
    }

    if (editor.getCurrent().status.blackFirst.type == STATUS_ALIVE_IN_SUPER_KO)
    {
      aliveInSuperKoSelection.checked = true;
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
    if (current.correct == CORRECT_GOOD)
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
