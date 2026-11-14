#ko structure experiments: approach count by the direct-ko test, who takes first, the direct position P_d,
#counted thresholds. Uses engine_ks (engine.cpp + endplay flag + counted PV).
#usage: ks.py <id> "<moves>" <command> [args] [--wide] [--budget=N] [--endplay]
#commands:
#  info                         ko(s) at the position, who holds, who takes first
#  solve                        modes A and D
#  count s o                    counted search, solver s threats, opponent o
#  T [lo hi]                    counted threshold for the solver: smallest k with a win at s-o=k
#  direct X "<pre-placed X moves>"   direct-ko test for side X (B/W) after pre-placing the moves
#  approach X N "<cands>"       smallest n<=N such that some n candidates make the ko direct for X
#  pd X "<approaches>" [T]      the direct position after X's approaches (Y retook), and its threshold
import json, os, sys, time, itertools
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
os.environ.setdefault('ENGINE', '/tmp/claude-0/proto/research/kodesign/structure/engine_ks')
from check_tree import *
from tsumego import add_wide, Engine, Solver, Problem, other_colour
from go_figure import LETTERS

args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = set(a for a in sys.argv[1:] if a.startswith('--') and '=' not in a)
options = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
budget = int(options.get('budget', '200000'))
pid, line = args[0], args[1]
for l in open('/tmp/claude-0/dump2/status_problems.jsonl'):
    r = json.loads(l)
    if r['set_connection_id'] == pid:
        break
sgf_root = parse_sgf(r['sgf'])
root, S, size = build(sgf_root)
goal = sgf_root.get('G')
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
defender = opponent(S) if goal == 'KILL' else S
attacker = opponent(defender)
target = groups_of(root.board, defender)[0]
problem = Problem(root.board, focused_region(root.board, box, target, tree_points))
engine = Engine()
solver = Solver(engine, problem, attacker, target, budget)
solver.solver_color = S
add_wide(solver, root.board, list(root.board.stones) + tree_points, target, tree_points)
solver.wide.solver_color = S
use = solver.wide if '--wide' in flags else solver
if 'box' in options:
    #a hand-made frame: only the points inside the box (corners like A15:E19) take part, everything else is the
    #attacker's wall (stones outside included)
    from go_figure import LETTERS as _L
    a_, b_ = options['box'].split(':')
    bx1, bx2 = sorted((_L.index(a_[0]), _L.index(b_[0])))
    by1, by2 = sorted((size - int(a_[1:]), size - int(b_[1:])))
    inbox = lambda p: bx1 <= p[0] <= bx2 and by1 <= p[1] <= by2
    boxed = Problem(root.board, {p for p in box if inbox(p)})
    boxed.fringe = set()
    orig_text = Problem.board_text
    def boxed_text(self, board):
        t = list(orig_text(self, board))
        for y in range(size):
            for x in range(size):
                if not inbox((x, y)):
                    t[y * size + x] = '#'
        return ''.join(t)
    boxed.board_text = boxed_text.__get__(boxed)
    use = Solver(Engine(), boxed, attacker, target, budget)
    use.solver_color = S
    if '--endplay' in flags:
        use.activate_engine(); use.engine.command('endplay 1')
if '--endplay' in flags:
    for s_ in (solver, solver.wide):
        s_.activate_engine()
        s_.engine.command('endplay 1')
name = lambda p: point_name(p, size)
def parse(t):
    return None if t == 'pass' else (LETTERS.index(t[0].upper()), size - int(t[1:]))

def play_line(board, to_move, text):
    board = board.copy()
    for t in text.split():
        retake = t.endswith('!')
        if not board.play(to_move, parse(t.rstrip('!')), ignore_ko=retake):
            sys.exit('illegal %s' % t)
        to_move = opponent(to_move)
    return board, to_move

board, to_move = play_line(root.board, S, line)

def raw_solve(s, board, to_move):
    s.activate_engine()
    text = s.problem.board_text(board)
    ko = s.problem.index(board.ko) if board.ko else -1
    a = s.engine.command('solve %s %d %d %s' % (to_move, ko, s.budget, text))
    return dict(item.split('=') for item in a.split()[1:])

def raw_count(s, board, to_move, black, white):
    s.activate_engine()
    text = s.problem.board_text(board)
    ko = s.problem.index(board.ko) if board.ko else -1
    a = s.engine.command('solvecount %s %d %d %d %d %s' % (to_move, ko, s.budget * 5, black, white, text))
    f = dict(item.split('=') for item in a.split()[1:])
    pv = []
    if f.get('pv', '-') != '-':
        for item in f['pv'].split(','):
            bang = item.endswith('!')
            item = item.rstrip('!')
            if item == 'pass':
                pv.append('pass')
                continue
            i = int(item)
            pv.append('pass' if i < 0 else name(s.problem.from_index(i)) + ('!' if bang else ''))
    return f, pv

def solver_wins(s, board, to_move, st, ot):
    black, white = (st, ot) if S == 'B' else (ot, st)
    f, pv = raw_count(s, board, to_move, black, white)
    if f['N'] == 'U':
        return None, pv, f
    return (f['N'] == 'K') == (S == attacker), pv, f

def kos(board):
    #single stones in atari whose capture is a ko: (holder colour, holder point, capture point, capture banned now);
    #the fought ko first: the one banned now (just captured), or the one named with --ko=<holder point>
    out = _kos(board)
    want = options.get('ko')
    out.sort(key=lambda k: (not (want and name(k[1]) == want), not k[3]))
    return out

def _kos(board):
    out = []
    for q, c in board.stones.items():
        stones, libs = board.chain(q)
        if len(stones) != 1 or len(libs) != 1:
            continue
        p = next(iter(libs))
        child = board.copy()
        if child.play(opponent(c), p, ignore_ko=True) and child.ko == q:
            out.append((c, q, p, board.ko == p))
    return out

def first_taker(board, to_move):
    #who makes the first ko capture: the side that just captured, else the side to move if it can capture, else the other
    ks = kos(board)
    if not ks:
        return None
    c, q, p, banned = ks[0]
    taker = opponent(c)  #the side that can capture
    if banned:
        return c  #the holder just captured
    if to_move == taker:
        return taker
    return taker  #the holder is to move and cannot take; the other side takes next

def describe(board, to_move):
    ks = kos(board)
    print('kos:', ', '.join('%s stone %s capturable at %s%s' % (c, name(q), name(p), ' (banned now)' if b else '') for c, q, p, b in ks) or 'none')
    if ks:
        print('takes first:', first_taker(board, to_move))

def hold(board, X):
    #the position where X has just won the ko once: X's capture applied (ignoring the ban) if Y's stone is the ko stone
    board = board.copy()
    for c, q, p, b in kos(board):
        if c != X:
            ok = board.play(X, p, ignore_ko=True)
            assert ok
            return board, p, q
        return board, q, p
    return board, None, None

def direct(s, board, X, pre):
    #X has won the ko (captured), plays the pre-placed moves while Y passes, Y passes once more; X to move must win
    #unconditionally: mode A when X attacks (attacker kills with unlimited defender threats), mode D when X defends
    q, xp, yp = hold(board, X)
    for t in pre:
        if not q.play(X, t, ignore_ko=True):
            return None
    q.ko = None
    f = raw_solve(s, q, X)
    if X == attacker:
        return {'K': True, 'N': False}.get(f['A'])
    return {'N': True, 'K': False}.get(f['D'])

start = time.time()
cmd = args[2]
if cmd == 'info':
    print('to move', to_move, 'solver', S, 'attacker', attacker, 'target', ' '.join(sorted(name(p) for p in target)))
    describe(board, to_move)
elif cmd == 'solve':
    print(raw_solve(use, board, to_move))
elif cmd == 'count':
    st, ot = int(args[3]), int(args[4])
    w, pv, f = solver_wins(use, board, to_move, st, ot)
    print('s=%d o=%d solver %s nodes=%s pv: %s' % (st, ot, {True: 'wins', False: 'fails', None: 'undecided'}[w], f['nodes'], ' '.join(pv)))
elif cmd == 'T':
    lo, hi = (int(args[3]), int(args[4])) if len(args) > 4 else (-3, 3)
    base = int(options.get('base', '0'))
    for k in range(lo, hi + 1):
        st, ot = max(k, 0) + base, max(-k, 0) + base
        w, pv, f = solver_wins(use, board, to_move, st, ot)
        print('s-o=%+d (s=%d o=%d): %s  nodes=%s  pv: %s' % (k, st, ot, {True: 'win', False: 'fail', None: '?'}[w], f['nodes'], ' '.join(pv)))
        sys.stdout.flush()
        if w:
            break
elif cmd == 'direct':
    X = args[3]
    pre = [parse(t) for t in (args[4].split() if len(args) > 4 else [])]
    print('direct for %s after %s: %s' % (X, ' '.join(args[4:5]) or '-', direct(use, board, X, pre)))
elif cmd == 'approach':
    X, N = args[3], int(args[4])
    cands = [parse(t) for t in args[5].split()]
    found = None
    for n in range(0, N + 1):
        for combo in itertools.combinations(cands, n):
            d = direct(use, board, X, combo)
            print('  %s: %s' % (' '.join(name(p) for p in combo) or '-', d))
            sys.stdout.flush()
            if d:
                found = (n, combo)
                break
        if found:
            break
    print('approaches for %s: %s' % (X, (found[0], ' '.join(name(p) for p in found[1])) if found else '> %d' % N))
elif cmd == 'pd':
    X = args[3]
    apps = [parse(t) for t in args[4].split()]
    Y = opponent(X)
    q, xp, yp = hold(board, X)
    seq = []
    for a in apps:
        assert q.play(X, a)
        seq.append(name(a))
        #Y retakes for free: captures X's ko stone at the capture point
        ks = [k for k in kos(q) if k[0] == X]
        assert ks, 'no ko to retake'
        assert q.play(Y, ks[0][2])
        seq.append(name(ks[0][2]))
        if a is not apps[-1]:
            ks = [k for k in kos(q) if k[0] == Y]
            assert q.play(X, ks[0][2], ignore_ko=True)
            seq.append(name(ks[0][2]) + '!')
            q.ko = None
            seq.append('pass')
    print('P_d: X=%s holds, then %s; %s to move, ban at %s' % (X, ' '.join(seq), X, name(q.ko) if q.ko else '-'))
    lo, hi = (int(args[5]), int(args[6])) if len(args) > 6 else (-2, 3)
    for k in range(lo, hi + 1):
        #threshold from X's side
        xs, ys = max(k, 0), max(-k, 0)
        st, ot = (xs, ys) if X == S else (ys, xs)
        w, pv, f = solver_wins(use, q, X, st, ot)
        xw = w if X == S or w is None else not w
        print('  X threats-Y threats=%+d: X %s nodes=%s pv: %s' % (k, {True: 'wins', False: 'fails', None: '?'}[xw], f['nodes'], ' '.join(pv)))
        sys.stdout.flush()
        if xw:
            break
print('(%.1f s)' % (time.time() - start))
