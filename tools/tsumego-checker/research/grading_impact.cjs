//Which moves the site grades differently once leaf statuses are changed: runs besogo's own code (tests.html loads it)
//on the tree as it is and with the new S[] values, and lists the topmost nodes whose correct/wrong mark flips.
//usage: node grading_impact.js <scenarios.json> <out.json>
//scenarios: [{key, sgf, fixes: [{line: "B18 C19 ...", status: "KO+"}]}], lines in board notation from the start
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BESOGO = 'file://' + path.resolve(__dirname, '../../../webroot/besogo/tests.html');

function evaluate(scenario) {
  const LETTERS = 'ABCDEFGHJKLMNOPQRST';
  const names = { 1: 'correct', 2: 'wrong', 3: 'undefined', 0: 'empty' };
  function load(parsed) {
    let root = null;
    //the loader only hands the root to the editor; every other editor call does nothing here
    const editor = new Proxy({ loadRoot(r) { root = r; }, getRoot() { return root; } },
                             { get: (target, name) => name in target ? target[name] : () => {} });
    besogo.loadSgf(parsed, editor);
    return root;
  }
  function size(parsed) {
    const prop = parsed.props.find(p => p.id === 'SZ');
    const value = prop ? prop.values[0].split(':') : ['19'];
    return { x: Number(value[0]), y: Number(value[1] || value[0]) };
  }
  function sgfPoint(token, dims) {
    const x = LETTERS.indexOf(token[0].toUpperCase());
    const y = dims.y - Number(token.slice(1));
    return String.fromCharCode(97 + x) + String.fromCharCode(97 + y);
  }
  function setStatus(parsed, line, status) {
    //walks the parsed SGF by the moves of the line and writes S[] on the node reached
    const dims = size(parsed);
    let node = parsed;
    for (const token of line.split(' ').filter(Boolean)) {
      const point = sgfPoint(token, dims);
      const next = node.children.find(child => child.props.some(p => (p.id === 'B' || p.id === 'W') && p.values[0] === point));
      if (!next)
        throw new Error('line ' + line + ': no child ' + token);
      node = next;
    }
    const prop = node.props.find(p => p.id === 'S');
    if (prop)
      prop.values = [status];
    else
      node.props.push({ id: 'S', values: [status] });
    if (node.children.length)
      throw new Error('line ' + line + ' is not a leaf');
  }
  function marks(root) {
    //path text -> {correct, status, solver, number, move, path}
    const dims = root.getSize();
    const out = new Map();
    const walk = (node, path) => {
      for (const child of node.children) {
        if (child.localEdit)
          continue;
        const m = child.move;
        const token = !m ? '?' : (m.x === 0 ? 'pass' : LETTERS[m.x - 1] + (dims.y - m.y + 1));
        const childPath = path.concat([token]);
        out.set(childPath.join(' '), {
          correct: names[child.correct] || String(child.correct),
          status: child.status && !child.status.isNone() ? child.status.str() : '',
          solver: !!m && m.color === root.firstMove,
          number: childPath.length,
          path: childPath.join(' '),
        });
        walk(child, childPath);
      }
    };
    walk(root, []);
    return out;
  }
  const before = load(besogo.parseSgf(scenario.sgf));
  const parsed = besogo.parseSgf(scenario.sgf);
  for (const fix of scenario.fixes)
    setStatus(parsed, fix.line, fix.status);
  const after = load(parsed);
  const a = marks(before), b = marks(after);
  const flipped = [];
  for (const [path, mark] of a) {
    const other = b.get(path);
    if (!other)
      throw new Error('path missing after the fix: ' + path);
    if (other.correct !== mark.correct)
      flipped.push({ path, number: mark.number, solver: mark.solver, before: mark.correct, after: other.correct,
                     status_before: mark.status, status_after: other.status });
  }
  const paths = new Set(flipped.map(f => f.path));
  const top = flipped.filter(f => !paths.has(f.path.split(' ').slice(0, -1).join(' ')));
  return {
    key: scenario.key,
    root_before: before.status && !before.status.isNone() ? before.status.str() : '',
    root_after: after.status && !after.status.isNone() ? after.status.str() : '',
    flipped: top,
    flipped_total: flipped.length,
  };
}

(async () => {
  const scenarios = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const browser = await chromium.launch();
  const page = await browser.newPage();
  page.on('pageerror', error => console.error('page error:', error.message));
  await page.goto(BESOGO);
  await page.waitForFunction(() => window.besogoTestsFinished === true);
  const results = [];
  for (const scenario of scenarios) {
    try {
      results.push(await page.evaluate(evaluate, scenario));
    } catch (error) {
      results.push({ key: scenario.key, error: String(error.message || error).split('\n')[0] });
    }
  }
  await browser.close();
  fs.writeFileSync(process.argv[3], JSON.stringify(results, null, 1));
  console.log(results.length, 'scenarios,', results.filter(r => r.error).length, 'errors');
})();
