#review page for the status leaves where the solver and the tree disagree (ALIVE / DEAD / KO / BENT4): the tree's
#path, and the solver's line from the leaf. usage: status_examples.py '<status results glob>' <problems.jsonl> <out.html>
import glob, html, json, os, sys
from check_tree import *
from tsumego import add_wide
from go_figure import figure, LETTERS

DECIDED = ('ALIVE', 'DEAD', 'KO', 'BENT4')
PER_GROUP = int(os.environ.get('PER_GROUP', '5')) #drawn cases per (tree, solver) pair, the rest are listed

def solver_line(solver, board, to_move, result, plies=12):
    #a line that keeps the solver's verdict: the engine's move for the side to play, else the first region move that
    #keeps it; stops when the target is captured, at two passes, or once the verdict is a plain result after a pass
    line, passes = [], 0
    board = board.copy()
    for ply in range(plies):
        status, best = solver.status(board, to_move)
        candidates = [best] + sorted(solver.problem.region) + [None]
        chosen = None
        for p in candidates:
            if p is not None and (p in board.stones or p == board.ko):
                continue
            child = board.copy()
            if not child.play(to_move, p):
                continue
            if solver.status(child, opponent(to_move))[0] == result:
                chosen = (p, child)
                break
        if chosen is None:
            break
        p, board = chosen
        line.append((to_move, p))
        passes = passes + 1 if p is None else 0
        to_move = opponent(to_move)
        if passes >= 2 or not any(q in board.stones for q in solver.target):
            break
    while line and line[-1][1] is None:
        line.pop()
    return line

records = {}
for line in open(sys.argv[2]):
    record = json.loads(line)
    records[record['set_connection_id']] = record
cases = []
for path in glob.glob(sys.argv[1]):
    for line in open(path):
        result = json.loads(line)
        for leaf in result.get('leaves', []):
            if leaf['engine'] in DECIDED and leaf['expected'] in DECIDED and leaf['engine'] != leaf['expected']:
                cases.append((result['set_connection_id'], result['goal'], leaf['line'], leaf['authored'].strip(), leaf['expected'], leaf['engine']))
cases.sort(key=lambda c: (int(c[0]), c[2]))

groups = {}
engine = Engine()
names = {'B': 'black', 'W': 'white'}
for number, (sc, goal, line_text, authored, expected, verdict) in enumerate(cases):
    pair = (expected, verdict)
    group = groups.setdefault(pair, {'cards': [], 'listed': []})
    link = '<a href="https://tsumego.com/%s">%s</a> %s' % (sc, sc, html.escape(line_text or '(root)'))
    if len(group['cards']) >= PER_GROUP:
        group['listed'].append(link)
        continue
    sgf_root = parse_sgf(records[sc]['sgf'])
    root, solver_color, size = build(sgf_root)
    tree_points = [n.move for n in all_nodes(root) if n.move]
    box = region_of(size, list(root.board.stones) + tree_points)
    color = opponent(solver_color) if goal == 'KILL' else solver_color
    attacker = solver_color if goal == 'KILL' else opponent(solver_color)
    stones = groups_of(root.board, color)[0]
    problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
    solver = Solver(engine, problem, attacker, stones, 200000)
    solver.solver_color = solver_color
    add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
    solver.wide.solver_color = solver_color
    board, to_move, path = root.board.copy(), solver_color, []
    for text in line_text.split():
        p = (LETTERS.index(text[0]), size - int(text[1:]))
        board.play(to_move, p)
        path.append((to_move, p))
        to_move = opponent(to_move)
    try:
        line = solver_line(solver, board, to_move, verdict if verdict != 'BENT4' else 'BENT4')
    except Exception as error:
        print(sc, line_text, 'line failed', repr(error), file=sys.stderr)
        line = []
    points = list(root.board.stones) + [p for _, p in path] + [p for _, p in line if p]
    frame = (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
             max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))
    target = names[color]
    outcome = {'ALIVE': 'the %s group lives', 'DEAD': 'the %s group dies', 'KO': 'the %s group depends on a ko', 'BENT4': 'the %s group is bent four'}[verdict] % target
    caption = "Solver's line, %s to play: %s" % (names[to_move], outcome)
    card = '''<section class="card" id="c%d">
  <header><label><input type="checkbox" class="done" data-id="%s-%s"> <a href="https://tsumego.com/%s">%s</a></label>
  <span class="meta">%s · %s</span></header>
  <div class="verdict"><span class="said">Tree: <b>%s</b></span> <span class="said">Solver: <b>%s</b></span></div>
  <div class="figures">%s%s</div>
</section>''' % (number, sc, html.escape(line_text.replace(' ', '-')), sc, sc, html.escape(records[sc]['title']),
                 'black to kill' if goal == 'KILL' else 'black to live', html.escape(authored), verdict,
                 figure(root.board, path, frame, 'Tree path: the tree says %s here' % authored),
                 figure(board, line, frame, caption if line else "Solver: %s (no line drawn)" % verdict))
    group['cards'].append(card)
    print(sc, line_text, authored, verdict, len(line), file=sys.stderr, flush=True)

body = ''
for pair in sorted(groups, key=lambda k: -(len(groups[k]['cards']) + len(groups[k]['listed']))):
    group = groups[pair]
    total = len(group['cards']) + len(group['listed'])
    body += '<h2>Tree %s, solver %s <span class="count">%d</span></h2>%s' % (pair[0], pair[1], total, '\n'.join(group['cards']))
    if group['listed']:
        body += '<p class="intro">Not drawn:</p><ul class="rest">%s</ul>' % ''.join('<li>%s</li>' % l for l in group['listed'])
style = open(__file__.replace('status_examples.py', 'status_examples_style.html')).read()
open(sys.argv[3], 'w').write(style.replace('BODY', body).replace('TOTAL', str(len(cases))))
print(len(cases), 'cases', {('%s/%s' % k): len(v['cards']) + len(v['listed']) for k, v in groups.items()})
