#everything about one position of a status problem, for checking a disagreement by hand:
#usage: evidence.py <problem id> "<moves from the start>" [--tree] [--moves] [--pv] [--ko] [--label] [--budget=N] [--problems=FILE]
#--label: the full besogo ko label of the position with each part, its reason, its lines and undecided parts
#(research/ko_labels/design.md, section 7)
#moves: points like A18, 'pass', or 'A18!' for a retake against the ko ban (it stands for a ko threat and its answer).
#they alternate from the first player; the tree's own moves are followed while they match, other moves are allowed.
import json, os, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from check_tree import *
from tsumego import add_wide, other_colour
from go_figure import LETTERS

args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = set(a for a in sys.argv[1:] if a.startswith('--') and '=' not in a)
options = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
budget = int(options.get('budget', '200000'))
problems_path = options.get('problems') or os.environ.get('PROBLEMS', 'problems.jsonl')
wanted, line_text = args[0], (args[1] if len(args) > 1 else '')
names = {'B': 'Black', 'W': 'White'}

for l in open(problems_path):
    record = json.loads(l)
    if record['set_connection_id'] == wanted:
        break
else:
    sys.exit('no problem %s' % wanted)
sgf_root = parse_sgf(record['sgf'])
root, solver_color, size = build(sgf_root)
goal = sgf_root.get('G')
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
attacker = solver_color if goal == 'KILL' else opponent(solver_color)
stones = groups_of(root.board, color)[0]
problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
engine = Engine()
solver = Solver(engine, problem, attacker, stones, budget)
solver.solver_color = solver_color
add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
solver.wide.solver_color = solver_color
Solver.deadline = None
name = lambda p: point_name(p, size)
def parse(text):
    if text == 'pass':
        return None
    return (LETTERS.index(text[0].upper()), size - int(text[1:]))

#play the moves, following the tree while it matches
board, to_move, node, path = root.board.copy(), solver_color, root, []
on_tree = True
for text in line_text.split():
    retake = text.endswith('!')
    p = parse(text.rstrip('!'))
    child = None
    if on_tree and node is not None and not retake:
        child = next((c for c in node.children if c.move == p), None)
    if child is not None:
        node, board = child, child.board.copy()
    else:
        on_tree = False
        node = None
        if not board.play(to_move, p, ignore_ko=retake):
            sys.exit('illegal move %s for %s after %s' % (text, names[to_move], ' '.join(t for t, _ in path)))
    path.append((text, to_move))
    to_move = opponent(to_move)

print('Problem %s  https://tsumego.com/%s  %s' % (wanted, wanted, record['title']))
print('Description: %s' % (record['description'] or '').strip())
print('Goal G[%s]: %s plays first and is the solver; the target is the %s group %s (%s %s)' % (
    goal, names[solver_color], names[color], ' '.join(sorted(name(p) for p in stones)),
    names[attacker], 'attacks' if goal == 'KILL' else 'attacks, the solver defends'))
print('Statuses are from the solver\'s side: KO+ the solver (%s) takes first, KO- the opponent takes first,' % names[solver_color])
print('KO+n the opponent needs n-1 threats to start, KO-n the solver needs n-1 threats to start.')

def leaves(n, moves):
    if not n.children:
        yield moves, n.status_text
    for c in n.children:
        yield from leaves(c, moves + [name(c.move)])

if '--tree' in flags:
    print('\nTree leaves (moves from the start: status):')
    current = ' '.join(t for t, _ in path)
    for moves, status in leaves(root, []):
        text = ' '.join(moves)
        print('  %s %s: %s' % ('>>' if text == current else '  ', text or '(start)', status or '(no status)'))

def show(board, title, last=None):
    xs = [p[0] for p in list(board.stones) + list(solver.wide.problem.region)]
    ys = [p[1] for p in list(board.stones) + list(solver.wide.problem.region)]
    x1, x2, y1, y2 = max(0, min(xs) - 1), min(size - 1, max(xs) + 1), max(0, min(ys) - 1), min(size - 1, max(ys) + 1)
    print('\n%s' % title)
    print('    ' + ' '.join(LETTERS[x] for x in range(x1, x2 + 1)))
    for y in range(y1, y2 + 1):
        row = []
        for x in range(x1, x2 + 1):
            p = (x, y)
            stone = board.stones.get(p)
            if stone:
                c = 'X' if stone == 'B' else 'O'
                if p not in solver.wide.problem.region:
                    c = c.lower()
            elif p == board.ko:
                c = '*'
            elif p in problem.region:
                c = '.'
            elif p in solver.wide.problem.region:
                c = '+'
            else:
                c = '-'
            row.append(c)
        print('%3d %s' % (size - y, ' '.join(row)))
    print('    X black, O white (x/o: outside both search areas); . empty in the search area, + empty only in the wider')
    print('    area, - empty outside both (the solver treats it as the attacker\'s wall); * the ko point (no retake now)')
    if last:
        print('    last move: %s' % last)

show(root.board, 'Start position (%s to play):' % names[solver_color])
if path:
    shown = ' '.join(('%s %s' % (names[c][0], t)) for t, c in path)
    print('\nMoves: %s%s' % (shown, '' if on_tree else '   (leaves the tree)'))
    leaf_status = node.status_text if node is not None else None
    kind = 'leaf' if node is not None and not node.children else 'tree node' if node is not None else 'off-tree position'
    print('This is a %s%s' % (kind, ', S[%s]' % leaf_status if leaf_status else ''))
show(board, 'Position (%s to play):' % names[to_move], last=('%s %s' % (names[path[-1][1]], path[-1][0])) if path else None)

def raw(s, board, to_move):
    text = s.problem.board_text(board)
    ko = s.problem.index(board.ko) if board.ko else -1
    s.activate_engine()
    answer = s.engine.command('solve %s %d %d %s' % (to_move, ko, s.budget, text))
    fields = dict(item.split('=') for item in answer.split()[1:])
    return fields

def mode_text(fields):
    words = {'K': 'kills', 'N': 'does not kill', 'U': 'undecided'}
    return 'attacker %s when the defender may ignore the ko ban (A); attacker %s when the attacker may (D)' % (
        words[fields['A']], words[fields['D']])

start = time.time()
status, best = solver.status(board, to_move)
last_mover = path[-1][1] if path else None
final = ko_taken_by(board, last_mover, solver_color, status)
print('\nSolver verdict for the %s group: %s%s' % (names[color], final, ' (the solver\'s own result was %s; a line ending with the solver capturing a ko counts as a ko)' % status if final != status else ''))
normal = raw(solver, board, to_move)
wide = raw(solver.wide, board, to_move)
print('  search area: %s' % mode_text(normal))
print('  wider area:  %s' % mode_text(wide))
if final == 'KO':
    sign = solver.ko_sign(board, to_move)
    if sign is None and status != 'KO':
        sign = 'KO+'
    print('  ko sign: %s' % (sign or 'undecided'))

def solver_line(s, board, to_move, result, plies=12):
    line, passes = [], 0
    board = board.copy()
    for ply in range(plies):
        status, best = s.status(board, to_move)
        candidates = [best] + sorted(s.problem.region) + [None]
        chosen = None
        for p in candidates:
            if p is not None and (p in board.stones or p == board.ko):
                continue
            child = board.copy()
            if not child.play(to_move, p):
                continue
            if s.status(child, opponent(to_move))[0] == result:
                chosen = (p, child)
                break
        if chosen is None:
            break
        p, board = chosen
        line.append('%s %s' % (names[to_move][0], name(p)))
        passes = passes + 1 if p is None else 0
        to_move = opponent(to_move)
        if passes >= 2 or not any(q in board.stones for q in s.target):
            break
    return line

if '--pv' in flags and status != '?':
    print('\nSolver line keeping %s (the side to play tries the engine\'s move first; the other side\'s moves are any that keep it):' % status)
    print('  ' + ' '.join(solver_line(solver, board, to_move, status)))

if '--moves' in flags:
    print('\nOne-move table: the verdict after each legal %s move in the search area (then %s to play):' % (names[to_move], names[opponent(to_move)]))
    results = {}
    for p in sorted(problem.region) + [None]:
        if p is not None and (p in board.stones or p == board.ko):
            continue
        child = board.copy()
        if not child.play(to_move, p):
            continue
        child_status = solver.status(child, opponent(to_move))[0]
        child_status = ko_taken_by(child, to_move, solver_color, child_status)
        results.setdefault(child_status, []).append(name(p))
    for key in ('DEAD', 'BENT4', 'DSKO', 'DDKO', 'KO', 'SEKI', 'SEKI+', 'ADKO', 'ASKO', 'ALIVE', '?'):
        if key in results:
            print('  %-5s after %s' % (key, ' '.join(results[key])))

if '--ko' in flags:
    print('\nKo tables: does the solver (%s) reach its goal when it has s ko threats and the opponent o?' % names[solver_color])
    print('(threats are moves elsewhere the other side must answer; a retake against the ko ban uses one)')
    for label, s in (('search area', solver), ('wider area', solver.wide)):
        row = []
        for k in range(-3, 4):
            x, y = max(k, 0), max(-k, 0)
            result = s.solver_wins(board, to_move, x, y)
            row.append('%+d:%s' % (k, {True: 'yes', False: 'no', None: '?'}[result]))
        print('  %-12s s-o = %s' % (label, '  '.join(row)))
    print('  the signs: KO+n wins from s-o=-(n-1), KO+ from 0, KO- from 1, KO-n from n')
    sign = solver.ko_sign(board, to_move)
    print('  sign from the tables: %s' % (sign or 'undecided'))
    for k in (0, 1):
        x, y = max(k, 0), max(-k, 0)
        wins, line = solver.wide.counted_line(board, to_move, x, y, end=0)
        text = solver.wide.line_names(line)
        print('  wider area, s=%d o=%d: solver %s; line: %s' % (x, y, {True: 'wins', False: 'fails', None: 'undecided'}[wins], text or '(none)'))
def label_report(record):
    #design 7: the label, one sentence per part, the counted table, the parts, the lines (numbered from this position, with
    #research/report.py's diagram specs), the other area's label, warnings and undecided parts with their nodes
    besogo = record.get('besogo') or {}
    print('\nKo label: %s%s, class %s%s' % (record['label'], ' (besogo: approaches %s, extra threats %s)' % (
        besogo.get('approaches'), besogo.get('extraThreats')) if besogo.get('type') == 'KO' else '', record.get('class'),
        ' (inferred)' if record.get('class_inferred') else ''))
    for key, title in (('strict_label', 'strict label (the full count, no approach rule)'),
                       ('strict_from_parts', 'strict label from the parts (the full count is undecided)'), ('net_label', 'net reading')):
        if record.get(key):
            print('  %s: %s' % (title, record[key]))
    print('\n' + record.get('text', ''))
    count = record.get('count')
    if count:
        row = '  '.join('%+d:%s' % (k, {True: 'yes', False: 'no', None: '?'}[count['table'].get(k, count['table'].get(str(k)))]) for k in range(-3, 4))
        print('\nCounted table: does %s win with s - o =   %s' % (names[record['solver']], row))
        print('  full count %s (bracket %s), shifted check %s, %d nodes, area %s' % (
            count['d_full'], count['bracket'], count['shifted_check'], count['nodes'], record['area']['count']))
    take = record.get('first_take')
    if take:
        print('First take: %s at %s (%s), preparation %s, confirmed %s' % (names.get(take['side'], '?'), take['point'], take['how'],
              ' '.join('%s %s' % move for move in take['preparation']) or '-', take['confirmed']))
    for side in ('S', 'O'):
        summary = (record.get('approaches') or {}).get(side)
        if summary:
            print('Approaches of %s: n=%s (bracket %s, outright %s), moves %s, alternatives %s, held-ko check %s, direct-ko value %s, %d nodes' % (
                'the solver' if side == 'S' else 'the opponent', summary['n'], summary['bracket'], summary['outright'],
                ' '.join(summary.get('round_moves') or summary['moves']), summary['alternatives'], summary['held_ok'], summary['v'], summary['nodes']))
    direct = record.get('direct_ko') or {}
    if direct.get('attribution'):
        print('Attribution of the extra threats: %s' % direct['attribution'])
    for threat in direct.get('local_threats') or []:
        print('  local threat of %s: %s (answers %s), d %d -> %d' % (names[threat['side']], ' '.join(threat['points']), threat['answers'],
              threat['d_before'], threat['d_after']))
    for stage in direct.get('stages') or []:
        print('  stage of %s: %s' % (names.get(stage['side'], stage['side']), stage))
    gain = record.get('gain') or {}
    if gain.get('side'):
        print('Gain of %s in the approach rounds: total %s, located %s, unlocated %s' % (names[gain['side']], gain['total'], gain['located'], gain['unlocated']))
        for item in gain['rounds']:
            print('  round %d: %s (value %s; d %s with it, %s with a move elsewhere), tried %s' % (item['round'], item['move'] or 'no gain',
                  item['value'], item['d_with'], item['d_without'], ', '.join('%s %s' % (t['move'], t['value']) for t in item['tried'])))
    for i, line in enumerate(record.get('lines') or [], 1):
        print('\nLine %d, %s: %s wins with %d threats against %d%s' % (i, line['name'], names.get(line['winner'], '?'), line['threats']['S'] if line['winner'] == record['solver'] else line['threats']['O'],
              line['threats']['O'] if line['winner'] == record['solver'] else line['threats']['S'], '' if line['decided'] else ' (stops: %s)' % line['stop']))
        print('  ' + line['text'])
        print('  diagram: %s' % json.dumps(line['diagram']))
    print('\nAreas: %s' % record.get('areas'))
    for warning in record.get('warnings') or []:
        print('Warning: %s' % warning)
    for item in record.get('undecided') or []:
        print('Undecided: %s, %s (%d nodes, %s area)' % (item['part'], item['search'], item['nodes'], item.get('area')))
    print('(%d nodes, %.1f s)' % (record['nodes'], record['seconds']))

if '--label' in flags:
    last_move = parse(path[-1][0].rstrip('!')) if path else None
    label_report(solver.ko_label(board, to_move, last_move, start=line_text.strip() or None))
print('\n(%.0f s, budget %d nodes per search)' % (time.time() - start, budget))
