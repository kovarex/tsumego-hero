#The review checklist as a page of go diagrams: the tree's path from the start position, and the solver's line from
#the end of that path, both numbered like a printed go figure ("5 at 3" for moves on an occupied point)
import json, os, sys, html
from tsumego import Board, parse_sgf, point, expand_points
from check_tree import build

LETTERS = 'ABCDEFGHJKLMNOPQRST'
rows = json.load(open(sys.argv[1]))
problems = {}
for line in open(os.environ.get('PROBLEMS', 'problems.jsonl')):
    record = json.loads(line)
    problems[record['set_connection_id']] = record
confirmed = set(sys.argv[3].split(',')) if len(sys.argv) > 3 else set()

def parse_moves(text):
    #"B A18, W B19, … (ko fight)" -> [('B', (0, 1)), ...]
    moves = []
    for part in text.split(','):
        part = part.strip()
        if not part or part.startswith('…'):
            break
        colour, name = part.split()
        moves.append((colour, None if name == 'pass' else (LETTERS.index(name[0]), 19 - int(name[1:]))))
    return moves

def figure(board, moves, frame, caption):
    #board: position the figure starts from; moves: numbered on top of it, captures are not removed
    x1, x2, y1, y2 = frame
    cell, margin = 26, 30
    width, height = margin * 2 + cell * (x2 - x1), margin * 2 + cell * (y2 - y1)
    size = board.size
    out = ['<svg class="board" viewBox="0 0 %d %d" role="img" aria-label="%s">' % (width, height, html.escape(caption))]
    out.append('<rect class="wood" x="0" y="0" width="%d" height="%d" rx="4"/>' % (width, height))
    px = lambda x: margin + (x - x1) * cell
    py = lambda y: margin + (y - y1) * cell
    for x in range(x1, x2 + 1):
        top = py(y1) if y1 == 0 else py(y1) - cell / 2
        bottom = py(y2) if y2 == size - 1 else py(y2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if x in (0, size - 1) else '', px(x), top, px(x), bottom))
        out.append('<text class="coord" x="%d" y="%d">%s</text>' % (px(x), height - 8, LETTERS[x]))
    for y in range(y1, y2 + 1):
        left = px(x1) if x1 == 0 else px(x1) - cell / 2
        right = px(x2) if x2 == size - 1 else px(x2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if y in (0, size - 1) else '', left, py(y), right, py(y)))
        out.append('<text class="coord" x="%d" y="%d">%d</text>' % (11, py(y) + 4, size - y))
    for (x, y), colour in board.stones.items():
        if x1 <= x <= x2 and y1 <= y <= y2:
            out.append('<circle class="%s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(x), py(y)))
    first = {}
    notes = []
    for number, (colour, p) in enumerate(moves, 1):
        if p is None:
            notes.append('%d pass' % number)
            continue
        if p in first or p in board.stones:
            notes.append('%d at %s' % (number, first.get(p) or LETTERS[p[0]] + str(size - p[1])))
            continue
        first[p] = str(number)
        x, y = p
        out.append('<circle class="%s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(x), py(y)))
        out.append('<text class="number %s" x="%d" y="%d">%d</text>' % ('on-black' if colour == 'B' else 'on-white', px(x), py(y) + 4, number))
    out.append('</svg>')
    note = ('<div class="notes">%s</div>' % html.escape(', '.join(notes))) if notes else ''
    return '<figure>%s<figcaption>%s</figcaption>%s</figure>' % (''.join(out), html.escape(caption), note)

cards = {}
for sc, title, goal, path_text, tree, solver, pv_text, kind in rows:
    record = problems[sc]
    root, solver_colour, size = build(parse_sgf(record['sgf']))
    start = root.board
    path = parse_moves(path_text)
    line = parse_moves(pv_text)
    leaf = start.copy()
    for colour, p in path:
        leaf.play(colour, p)
    points = list(start.stones) + [p for _, p in path + line if p]
    frame = (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
             max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))
    severity = 'minor' if kind == 'status-almost' else 'major'
    what = {'status-almost': 'Bent four vs dead', 'status-mismatch': 'Status differs', 'correct-move-fails': 'Correct move fails',
            'missing-correct-move': 'Missing correct move', 'wrong-move-works': 'Wrong move works',
            'refutation-does-not-refute': 'Refutation fails'}.get(kind, kind)
    finding = '''<div class="finding">
  <div class="verdict"><span class="pill %s">%s</span> <span class="said">Tree: <b>%s</b></span> <span class="said">Solver: <b>%s</b></span></div>
  <div class="figures">%s%s</div>
</div>''' % (severity, what, html.escape(tree), html.escape(solver),
             figure(start, path, frame, 'Tree path, black first: the tree says %s here' % tree if kind.startswith('status') else
                    'Tree path, black first: %s' % ('the last move is not in the tree' if kind == 'missing-correct-move' else 'the tree says ' + tree)),
             figure(leaf, line, frame, "Solver's line from there" + (' (ko fight goes on)' if '…' in pv_text else '')))
    card = cards.setdefault(sc, {'title': title, 'goal': goal, 'findings': []})
    card['findings'].append(finding)

body = []
for sc, card in sorted(cards.items(), key=lambda kv: int(kv[0])):
    body.append('''<section class="card" id="p%s">
  <header><label><input type="checkbox" class="done" data-id="%s"> <a href="https://tsumego.com/%s">%s</a></label> <span class="meta">%s · %s</span></header>
  %s
</section>''' % (sc, sc, sc, sc, html.escape(card['title']), 'black to kill' if card['goal'] == 'KILL' else 'black to live', '\n'.join(card['findings'])))

page = '''<title>Tsumego Review Checklist</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
/* Layout: one column of problem cards, each finding shows two go figures side by side (stacked on phones) */
:root {
  --paper: #f6f7f8; --ink: #1b2126; --muted: #5b6670; --rule: #d6dbe0; --surface: #ffffff; --accent: #1d5b78;
  --wood: #ddb97f; --wood-line: #5e4728; --major: #a83a32; --minor: #9a6a00;
  --body: "IBM Plex Sans", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  --mono: "IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --paper: #12171b; --ink: #e3e8ec; --muted: #9aa6b0; --rule: #2c353d; --surface: #1a2127; --accent: #7cc0de;
  --wood: #b8935a; --wood-line: #3a2a14; --major: #ef8a80; --minor: #e2b54f; color-scheme: dark; } }
:root[data-theme="dark"] {
  --paper: #12171b; --ink: #e3e8ec; --muted: #9aa6b0; --rule: #2c353d; --surface: #1a2127; --accent: #7cc0de;
  --wood: #b8935a; --wood-line: #3a2a14; --major: #ef8a80; --minor: #e2b54f; color-scheme: dark; }
body { background: var(--paper); color: var(--ink); font: 15px/1.5 var(--body); }
.page { max-width: 900px; margin: 0 auto; padding-block: 32px 64px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 18px; }
h1 { font-size: 1.6rem; margin: 0; }
.intro { color: var(--muted); max-width: 70ch; margin: 0; }
.card { background: var(--surface); border: 1px solid var(--rule); border-radius: 6px; padding: 14px 16px; display: grid; gap: 14px; }
.card header { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: baseline; }
.card header label { font: 600 1.05rem/1.3 var(--body); display: flex; gap: 8px; align-items: center; }
.card header a { color: var(--accent); }
.meta { color: var(--muted); font-size: 0.9rem; }
.finding { display: grid; gap: 8px; border-top: 1px solid var(--rule); padding-top: 12px; }
.verdict { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; }
.pill { font: 500 0.75rem/1.6 var(--mono); padding: 0 8px; border-radius: 999px; border: 1px solid currentColor; }
.pill.major { color: var(--major); } .pill.minor { color: var(--minor); }
.figures { display: flex; flex-wrap: wrap; gap: 16px; }
figure { margin: 0; display: grid; gap: 4px; min-width: 0; }
figcaption { font-size: 0.85rem; color: var(--muted); }
.notes { font: 0.8rem/1.4 var(--mono); color: var(--muted); max-width: 40ch; }
.board { width: 100%; max-width: 330px; height: auto; }
.board .wood { fill: var(--wood); }
.board .grid { stroke: var(--wood-line); stroke-width: 1; }
.board .grid.edge { stroke-width: 2; }
.board .coord { font: 10px var(--mono); fill: var(--wood-line); text-anchor: middle; }
.board .black { fill: #16191c; stroke: #16191c; }
.board .white { fill: #f5f5f1; stroke: #3b3a35; stroke-width: 1; }
.board .number { font: 600 11px var(--mono); text-anchor: middle; }
.board .on-black { fill: #f5f5f1; } .board .on-white { fill: #16191c; }
.done:checked + a { text-decoration: line-through; }
input.done { width: 18px; height: 18px; }
</style>
<div class="page">
  <h1>Tsumego Review Checklist</h1>
  <p class="intro">Findings from the solver that wait for your review. Left: the tree's path from the start position, black first. Right: how the solver continues from the end of that path. Repeated moves on one point are listed under each figure. Tick a problem off when you have looked at it; the ticks stay in this browser only.</p>
  ''' + '\n'.join(body) + '''
</div>
<script>
document.querySelectorAll('input.done').forEach(function (box) {
  var key = 'review-' + box.dataset.id;
  try { box.checked = localStorage.getItem(key) === '1'; } catch (e) {}
  box.addEventListener('change', function () { try { localStorage.setItem(key, box.checked ? '1' : '0'); } catch (e) {} });
});
</script>
'''
open(sys.argv[2], 'w').write(page)
print(len(cards), 'problems', len(rows), 'findings')
