#harness for the ko-label design experiments: loads a problem like evidence.py, plays moves, runs engine commands
import json, os, sys, time
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
os.environ['ENGINE'] = '/tmp/claude-0/proto/research/kodesign/game/engine_x'
from check_tree import *
from tsumego import add_wide, other_colour, Engine, Problem, Solver, focused_region, region_of, groups_of
from go_figure import LETTERS
PROBLEMS = '/tmp/claude-0/dump2/status_problems.jsonl'
names = {'B': 'Black', 'W': 'White'}

class Pos:
    def __init__(self, pid, line, budget=200000, engine=None):
        for l in open(PROBLEMS):
            record = json.loads(l)
            if record['set_connection_id'] == pid:
                break
        else:
            raise SystemExit('no problem %s' % pid)
        sgf_root = parse_sgf(record['sgf'])
        root, solver_color, size = build(sgf_root)
        self.goal = sgf_root.get('G')
        self.size = size
        self.solver_color = solver_color
        tree_points = [n.move for n in all_nodes(root) if n.move]
        box = region_of(size, list(root.board.stones) + tree_points)
        color = opponent(solver_color) if self.goal == 'KILL' else solver_color
        self.attacker = solver_color if self.goal == 'KILL' else opponent(solver_color)
        stones = groups_of(root.board, color)[0]
        problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
        self.engine = engine or Engine()
        self.solver = Solver(self.engine, problem, self.attacker, stones, budget)
        self.solver.solver_color = solver_color
        add_wide(self.solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
        self.solver.wide.solver_color = solver_color
        Solver.deadline = None
        board, to_move = root.board.copy(), solver_color
        for text in line.split():
            retake = text.endswith('!')
            p = self.parse(text.rstrip('!'))
            if not board.play(to_move, p, ignore_ko=retake):
                raise SystemExit('illegal %s' % text)
            to_move = opponent(to_move)
        self.board, self.to_move = board, to_move
    def parse(self, text):
        if text == 'pass':
            return None
        return (LETTERS.index(text[0].upper()), self.size - int(text[1:]))
    def name(self, p):
        return 'pass' if p is None else '%s%d' % (LETTERS[p[0]], self.size - p[1])
    def after(self, moves):
        board, to_move = self.board.copy(), self.to_move
        for text in moves.split():
            retake = text.endswith('!')
            p = self.parse(text.rstrip('!'))
            if not board.play(to_move, p, ignore_ko=retake):
                raise SystemExit('illegal %s' % text)
            to_move = opponent(to_move)
        return board, to_move
    def show(self, board=None, wide=True):
        board = board or self.board
        s = self.solver.wide if wide else self.solver
        reg = s.problem.region
        xs = [p[0] for p in list(board.stones) + list(reg)]
        ys = [p[1] for p in list(board.stones) + list(reg)]
        x1, x2, y1, y2 = max(0, min(xs) - 1), min(self.size - 1, max(xs) + 1), max(0, min(ys) - 1), min(self.size - 1, max(ys) + 1)
        print('    ' + ' '.join(LETTERS[x] for x in range(x1, x2 + 1)))
        for y in range(y1, y2 + 1):
            row = []
            for x in range(x1, x2 + 1):
                p = (x, y)
                st = board.stones.get(p)
                c = ('X' if st == 'B' else 'O') if st else ('*' if p == board.ko else ('.' if p in reg else '-'))
                row.append(c)
            print('%3d %s' % (self.size - y, ' '.join(row)))
    def raw(self, cmd, wide=False):
        s = self.solver.wide if wide else self.solver
        s.activate_engine()
        return s.engine.command(cmd)
    def pvnames(self, pv, wide=False):
        sv = self.solver.wide if wide else self.solver
        out = []
        for item in pv.split('_'):
            if not item or item == '-':
                continue
            if item in ('fp', 'cycle'):
                out.append(item)
                continue
            who, rest = item[0], item[1:]
            bang = rest.endswith('!')
            rest = rest.rstrip('!')
            p = None if rest == 'pass' else sv.problem.from_index(int(rest))
            out.append(who + self.name(p) + ('!' if bang else ''))
        return '_'.join(out)
    def idx(self, name, wide=False):
        sv = self.solver.wide if wide else self.solver
        return sv.problem.index(self.parse(name))
    def text(self, board, wide=False):
        s = self.solver.wide if wide else self.solver
        return s.problem.board_text(board), (s.problem.index(board.ko) if board.ko else -1)
    def count(self, board, to_move, s_threats, o_threats, wide=False, budget=None, extra=''):
        #solver wins? with s, o threats; extra = engine command suffix for the experimental command
        sv = self.solver.wide if wide else self.solver
        text, ko = self.text(board, wide)
        black, white = (s_threats, o_threats) if self.solver_color == 'B' else (o_threats, s_threats)
        b = budget or sv.budget * 5
        sv.activate_engine()
        ans = sv.engine.command('solvex %s %d %d %d %d %s %s' % (to_move, ko, b, black, white, extra or '-', text))
        f = dict(item.split('=') for item in ans.split()[1:])
        if 'pv' in f:
            f['pv'] = self.pvnames(f['pv'], wide)
            ans = ' '.join('%s=%s' % kv for kv in f.items())
        if f['N'] == 'U':
            return None, ans
        return (f['N'] == 'K') == (self.solver_color == self.attacker), ans
    def threshold(self, board, to_move, lo=-3, hi=4, wide=False, budget=None, extra='', verbose=True):
        #smallest d with the solver winning at (d+, d-); scans upward from lo
        results = {}
        for d in range(lo, hi + 1):
            r, ans = self.count(board, to_move, max(d, 0), max(-d, 0), wide, budget, extra)
            results[d] = r
            if verbose:
                print('   d=%+d: %s  (%s)' % (d, {True: 'win', False: 'fail', None: '?'}[r], ans))
                sys.stdout.flush()
            if r:
                break
        wins = [d for d, r in results.items() if r]
        return (min(wins) if wins else None), results

def corner_pos(pid, line, cols, rows, budget=200000):
    #the same position with a small hand-picked region (cols x rows from the top-left corner), for exact corner reads
    p = Pos(pid, line, budget)
    region = {(x, y) for x in range(cols) for y in range(rows)}
    problem = Problem(p.board, region)
    p.solver = Solver(p.engine, problem, p.attacker, p.solver.target, budget)
    p.solver.solver_color = p.solver_color
    p.solver.wide = p.solver
    return p

def region_pos(pid, line, points, budget=200000):
    #the same position with an explicit region (list of point names), no fringe
    p = Pos(pid, line, budget)
    region = {p.parse(t) for t in points.split()}
    import tsumego
    saved = tsumego.DYNAMIC
    tsumego.DYNAMIC = False
    problem = Problem(p.board, region)
    tsumego.DYNAMIC = saved
    p.solver = Solver(p.engine, problem, p.attacker, p.solver.target, budget)
    p.solver.solver_color = p.solver_color
    p.solver.wide = p.solver
    return p
