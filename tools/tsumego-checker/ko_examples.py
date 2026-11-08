#review page for the ko signs where the solver and the tree disagree: the tree's path, and the solver's line with the
#threat counts the two disagree on. usage: ko_examples.py '<status results glob>' <problems.jsonl> <out.html>
import glob, html, json, os, re, sys
from check_tree import *
from tsumego import add_wide
from go_figure import figure, LETTERS

def balance(sign):
    #the threats the solver's side needs beyond the opponent's: KO+ 0, KO+n -(n-1), KO- 1, KO-n n
    m = re.fullmatch(r'KO([+-])(\d*)', sign)
    n = int(m.group(2)) if m.group(2) else 1
    return -(n - 1) if m.group(1) == '+' else n

records = {}
for line in open(sys.argv[2]):
    record = json.loads(line)
    records[record['set_connection_id']] = record
cases = []
for path in glob.glob(sys.argv[1]):
    for line in open(path):
        result = json.loads(line)
        for leaf in result.get('leaves', []):
            authored = leaf['authored'].strip()
            if leaf.get('sign') and re.fullmatch(r'KO[+-]\d*', authored) and leaf['sign'] != authored:
                cases.append((result['set_connection_id'], result['goal'], leaf['line'], authored, leaf['sign']))

groups = {'taken': [], 'threats': [], 'other': []}
totals = {}
unsettled = []
PER_GROUP = int(os.environ.get('PER_GROUP', '5')) #the first few cases of each kind
engine = Engine()
for number, (sc, goal, line_text, authored, sign) in enumerate(sorted(cases, key=lambda c: (int(c[0]), c[2]))):
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
    #the lines come from the wider region, as the signs do
    add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
    solver.wide.solver_color = solver_color
    solver = solver.wide
    board, to_move, path = root.board.copy(), solver_color, []
    for text in line_text.split():
        p = (LETTERS.index(text[0]), size - int(text[1:]))
        board.play(to_move, p)
        path.append((to_move, p))
        to_move = opponent(to_move)
    last_took_ko = board.ko is not None and path and path[-1][0] != solver_color
    a, s = balance(authored), balance(sign)
    group = 'taken' if last_took_ko and a <= 0 < s else 'threats' if (a <= 0) == (s <= 0) else 'other'
    totals[group] = totals.get(group, 0) + 1
    #the disputed balance: the solver wins there by one reading and loses by the other
    disputed = min(a, s)
    x, y = max(disputed, 0), max(-disputed, 0)
    wins, line = solver.counted_line(board, to_move, x, y)
    if wins is None or wins != (s <= a):
        #the wider region does not back the sign at the disputed balance: the sign is not settled, leave it out
        unsettled.append((sc, line_text, authored, sign))
        totals[group] -= 1
        continue
    if len(groups[group]) >= PER_GROUP:
        continue #confirmed and counted, but not drawn
    while line and line[-1][1] is None:
        line.pop() #passes that only end the game
    points = list(root.board.stones) + [p for _, p in path] + [m[1] for m in line if m[1]]
    frame = (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
             max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))
    names = {'B': 'black', 'W': 'white'}
    threat_text = '%s has %d ko threat%s, %s %d' % (names[solver_color], x, '' if x == 1 else 's', names[opponent(solver_color)], y)
    verdict = '%s %s' % (names[solver_color], 'wins' if wins else 'fails')
    card = '''<section class="card" id="c%d">
  <header><label><input type="checkbox" class="done" data-id="%s-%s"> <a href="https://tsumego.com/%s">%s</a></label>
  <span class="meta">%s · %s</span></header>
  <div class="verdict"><span class="said">Tree: <b>%s</b></span> <span class="said">Solver: <b>%s</b></span></div>
  <div class="figures">%s%s</div>
</section>''' % (number, sc, html.escape(line_text.replace(' ', '-')), sc, sc, html.escape(records[sc]['title']),
                 'black to kill' if goal == 'KILL' else 'black to live', authored, sign,
                 figure(root.board, path, frame, 'Tree path: the tree says %s here' % authored),
                 figure(board, line, frame, "Solver's line when %s: %s" % (threat_text, verdict)))
    groups[group].append(card)
    print(sc, line_text, authored, sign, group, verdict, file=sys.stderr, flush=True)

intro = {
    'taken': ('The last move took the ko', "The tree's line ends with the opponent capturing a ko, so black cannot retake at once and needs a threat first. besogo calls that \"white takes first\" (KO-), but the leaf says KO+. The right figure plays the position with no threats on either side."),
    'threats': ('One or more threats apart', "Tree and solver agree on who is ahead, but not by how much. Usually a local threat: a move the opponent has to answer, which works like an extra ko threat. The right figure uses the threat counts where the two readings differ; moves that retake against the ko ban are listed under it as threat retakes."),
    'other': ('Other disagreements', "The tree and the solver disagree on who is ahead. The right figure uses the threat counts where the two readings differ."),
}
style = open(__file__.replace('ko_examples.py', 'ko_examples_style.html')).read()
body = ''
for key in ('taken', 'threats', 'other'):
    if groups[key]:
        title, text = intro[key]
        body += '<h2>%s <span class="count">%d of %d shown</span></h2><p class="intro">%s</p>%s' % (title, len(groups[key]), totals[key], text, '\n'.join(groups[key]))
page = style.replace('BODY', body).replace('TOTAL', str(sum(totals.values()))).replace('UNSETTLED', str(len(unsettled)))
open(sys.argv[3], 'w').write(page)
print(len(cases), 'cases', totals, 'not settled by the wider region', len(unsettled))
