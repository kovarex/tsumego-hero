// Lists what differs between the shown version of a problem and the version it is compared with
// (the diff link of a proposal or of the upload history, or the Compare button). Hidden without a diff.
besogo.makeDiffPanel = function(container, editor)
{
  'use strict';
  var MARKUP_NAMES = { 1: 'circle', 2: 'square', 3: 'triangle', 4: 'cross', 5: 'selected' },
      SETUP_NAMES = { AB: 'black', AW: 'white', AE: 'empty' },
      CORRECT_NAMES = { [CORRECT_GOOD]: 'correct', [CORRECT_BAD]: 'wrong', [CORRECT_UNDEFINED]: 'undecided', [CORRECT_EMPTY]: 'none' },
      SHOWN_MOVES = 4, // the last moves that name a position in the list
      COLUMN_PANELS_WIDTH = 900, // from this width of the panels the changes get a column of their own,
      COLUMN_FLAT_PANELS_WIDTH = 600, // and from this one when the panels are wider than tall, below the board
      UNFOLDED_PANELS_HEIGHT = 500, // in one column with lower panels, the list starts folded to leave room for the tree
      panels = container.parentElement,
      foldedByUser = null, // whether the user folded the list, null until they click the summary
      versionInfo = null, // {description, authorLink} of the shown version, see requestVersionInfo
      versionInfoRequested = false,
      entries = []; // {node, element}

  editor.addListener(update);
  update({ treeChange: true });
  // the panels change size with the window, and they are beside the board or below it
  new ResizeObserver(updateLayout).observe(panels);

  function update(msg)
  {
    if (msg.treeChange)
      rebuild();
    else if (msg.navChange)
      markCurrent();
  }

  function rebuild()
  {
    let root = editor.getRoot();
    container.textContent = '';
    entries = [];
    container.style.display = root.isDiff ? '' : 'none';
    updateLayout();
    if (!root.isDiff)
      return;

    let counts = { added: 0, removed: 0, changed: 0 };
    let list = document.createElement('ul');
    list.className = 'besogo-diff-list';
    collect(root, list, counts);

    let summary = document.createElement('div');
    summary.className = 'besogo-diff-summary';
    summary.id = 'diffSummary';
    summary.title = 'Show or hide the list';
    summary.onclick = function() { foldedByUser = container.classList.toggle('besogo-diff--folded'); };
    if (counts.added + counts.removed + counts.changed == 0)
      summary.appendChild(document.createTextNode('No differences'));
    else
    {
      summary.title += '\nAdded and removed count moves, changed counts positions in both versions with another comment, status, markup...';
      addCount(summary, counts.added, 'added', treeIcon(besogo.svgPlus));
      addCount(summary, counts.removed, 'removed', treeIcon(besogo.svgCross));
      addCount(summary, counts.changed, 'changed', changedIcon());
    }
    container.appendChild(summary);
    requestVersionInfo();
    if (versionInfo && versionInfo.description)
      container.appendChild(describeVersion(versionInfo));
    container.appendChild(list);
    markCurrent();
  }

  // Walks the tree in the order of the tree panel, an added or removed variation is one entry
  function collect(node, list, counts)
  {
    let diffInfo = node.diffInfo;
    if (diffInfo && (diffInfo.type == DIFF_ADDED || diffInfo.type == DIFF_REMOVED))
    {
      let added = diffInfo.type == DIFF_ADDED;
      let size = node.treeSize();
      counts[added ? 'added' : 'removed'] += size;
      addEntry(list, node, [(added ? 'Added ' : 'Removed ') + plural(size, 'move') + (size > 1 ? ' from here' : '')], added ? 'besogo-diff-added' : 'besogo-diff-removed');
      return;
    }
    if (diffInfo && diffInfo.changes.length)
    {
      ++counts.changed;
      addEntry(list, node, diffInfo.changes.map(describeChange), 'besogo-diff-changed');
    }
    for (let i = 0; i < node.children.length; ++i)
      collect(node.children[i], list, counts);
  }

  function addEntry(list, node, lines, className)
  {
    let item = document.createElement('li');
    item.className = 'besogo-diff-entry ' + className;
    item.title = 'Go to this position';
    let position = document.createElement('div');
    position.className = 'besogo-diff-position';
    position.textContent = describePosition(node);
    item.appendChild(position);
    for (let line of lines)
      item.appendChild(line instanceof Node ? line : textElement('div', line));
    item.onclick = function() { editor.setCurrent(node); };
    list.appendChild(item);
    entries.push({ node: node, element: item });
  }

  function updateLayout()
  {
    let width = panels.clientWidth;
    let column = width >= COLUMN_PANELS_WIDTH || (width >= COLUMN_FLAT_PANELS_WIDTH && width > panels.clientHeight);
    panels.classList.toggle('besogo-panels--diff-column', editor.getRoot().isDiff && column);
    container.classList.toggle('besogo-diff--folded', foldedByUser !== null ? foldedByUser : panels.clientHeight < UNFOLDED_PANELS_HEIGHT);
  }

  // What the author of the shown version wrote about it (the proposal's description), fetched once
  function requestVersionInfo()
  {
    if (versionInfoRequested || !besogo.sourceSgf || !besogo.sourceSgf.id)
      return;
    versionInfoRequested = true;
    fetch('/sgf/info/' + besogo.sourceSgf.id)
      .then(function(response) { return response.ok ? response.json() : null; })
      .then(function(info)
      {
        versionInfo = info;
        if (info && info.description)
          rebuild();
      })
      .catch(function() {}); // the changes are shown without it
  }

  function describeVersion(info)
  {
    let element = document.createElement('div');
    element.className = 'besogo-diff-description';
    element.id = 'diffDescription';
    let author = document.createElement('span');
    author.className = 'besogo-diff-author';
    author.innerHTML = info.authorLink; // rendered and escaped by the server (User::renderLink)
    element.appendChild(author);
    element.appendChild(textElement('span', ' wrote: ', 'besogo-diff-property'));
    element.appendChild(document.createTextNode(info.description));
    return element;
  }

  // "3 added" with the icon of the tree
  function addCount(summary, count, text, icon)
  {
    let item = textElement('span', count + ' ' + text + ' ', 'besogo-diff-count');
    item.appendChild(icon);
    summary.appendChild(item);
  }

  // An added or removed move as the tree shows it
  function treeIcon(makeMark)
  {
    let icon = besogo.svgEl('svg', { viewBox: '-50 -50 100 100', 'class': 'besogo-diff-icon' });
    icon.appendChild(besogo.svgStone(0, 0, BLACK));
    icon.appendChild(makeMark(0, 0, 'white'));
    return icon;
  }

  // The dot of a changed position in the tree
  function changedIcon()
  {
    let icon = besogo.svgEl('svg', { viewBox: '-20 -20 40 40', 'class': 'besogo-diff-icon' });
    icon.appendChild(besogo.svgEl('circle', { cx: 0, cy: 0, r: 16, 'class': 'besogo-tree-diff-changed' }));
    return icon;
  }

  function markCurrent()
  {
    let current = editor.getCurrent();
    for (let entry of entries)
      entry.element.classList.toggle('besogo-diff-current', entry.node === current);
  }

  function describePosition(node)
  {
    let moves = [];
    for (let i = node; i.parent; i = i.parent)
      if (i.move)
        moves.unshift(i.move);
    if (moves.length == 0)
      return 'Start position';
    let shown = moves.slice(-SHOWN_MOVES).map(function(move, index)
    {
      let number = moves.length - Math.min(moves.length, SHOWN_MOVES) + index + 1;
      return number + '. ' + (move.color == BLACK ? 'B' : 'W') + ' ' + (editor.coordXYToLabel(move.x, move.y) || 'pass');
    });
    return (moves.length > SHOWN_MOVES ? '… ' : '') + shown.join(', ');
  }

  // One change as "Property: before → after", the stones and marks as what was removed and added
  function describeChange(change)
  {
    let element = document.createElement('div');
    element.className = 'besogo-diff-change';
    element.appendChild(textElement('span', propertyName(change) + ': ', 'besogo-diff-property'));
    if (change.property == 'setup' || change.property == 'markup')
    {
      let removed = pointsMissingIn(change.before, change.after);
      let added = pointsMissingIn(change.after, change.before);
      if (removed.length)
        element.appendChild(textElement('del', 'removed ' + formatValue(change.property, removed)));
      if (removed.length && added.length)
        element.appendChild(document.createTextNode(', '));
      if (added.length)
        element.appendChild(textElement('ins', 'added ' + formatValue(change.property, added)));
      return element;
    }
    element.appendChild(textElement('del', formatValue(change.property, change.before)));
    element.appendChild(document.createTextNode(' → '));
    element.appendChild(textElement('ins', formatValue(change.property, change.after)));
    return element;
  }

  function propertyName(change)
  {
    switch (change.property)
    {
      case 'goal': return 'Correct controlled by status';
      case 'firstMove': return 'First move';
      case 'size': return 'Board size';
      case 'gameInfo': return 'Game info ' + change.key;
      case 'setup': return 'Setup stones';
      case 'markup': return 'Markup';
      case 'comment': return 'Comment';
      case 'markedCorrect': return 'Marked correct';
      case 'status': return 'Status';
      case 'superkoMeansDead': return 'Super ko means dead';
      case 'correct': return 'Result';
      case 'branchOrder': return 'Order of the branches';
    }
    return change.property;
  }

  function formatValue(property, value)
  {
    switch (property)
    {
      case 'goal':
        if (value == GOAL_NONE)
          return 'no (correct ends are marked)';
        return 'yes, goal: ' + besogo.goalStr(value).toLowerCase();
      case 'firstMove': return value == BLACK ? 'black' : 'white';
      case 'setup': return formatPoints(value, function(value) { return SETUP_NAMES[value]; });
      case 'markup': return formatPoints(value, function(value) { return typeof value === 'string' ? 'label ' + value : MARKUP_NAMES[value]; });
      case 'comment':
      case 'gameInfo': return value === '' ? '(none)' : value;
      case 'status': return value === null ? '(none)' : value;
      case 'markedCorrect':
      case 'superkoMeansDead': return value ? 'yes' : 'no';
      case 'correct': return CORRECT_NAMES[value];
      case 'branchOrder': return value.map(function(move) { return editor.coordXYToLabel(move.x, move.y); }).join(', ');
    }
    return '' + value;
  }

  function pointsMissingIn(points, otherPoints)
  {
    return points.filter(function(point)
    {
      return !otherPoints.some(function(other) { return other.x == point.x && other.y == point.y && other.value === point.value; });
    });
  }

  function formatPoints(points, name)
  {
    if (points.length == 0)
      return '(none)';
    return points.map(function(point) { return name(point.value) + ' ' + editor.coordXYToLabel(point.x, point.y); }).join(', ');
  }

  function plural(count, word)
  {
    return count + ' ' + word + (count == 1 ? '' : 's');
  }

  // Texts of the SGF are the user's, so they only ever go in as text
  function textElement(tag, text, className = null)
  {
    let element = document.createElement(tag);
    element.textContent = text;
    if (className)
      element.className = className;
    return element;
  }
};
