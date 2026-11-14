#plain solve (modes A/D) and counted thresholds at a few positions; usage: jsolve.py <id> "<leaf>" "<moves from leaf>" ...
import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
from harness import *
pid, leaf = sys.argv[1], sys.argv[2]
p = Pos(pid, leaf)
print('problem', pid, 'solver', p.solver_color, 'attacker', p.attacker, 'goal', p.goal)
for moves in sys.argv[3:]:
    board, t = p.after(moves)
    for wide in (False, True):
        text, ko = p.text(board, wide)
        sv = p.solver.wide if wide else p.solver
        sv.activate_engine()
        a = sv.engine.command('solve %s %d %d %s' % (t, ko, 200000, text))
        res = []
        for d in (-1, 0, 1, 2):
            r, ans = p.count(board, t, max(d, 0), max(-d, 0), wide=wide, budget=400000, extra='end=1')
            res.append('%+d:%s' % (d, {True: 'y', False: 'n', None: '?'}[r]))
        print('[%s] %s to move %s: %s | counted %s' % (moves or 'leaf', t, 'wide' if wide else 'narrow', a, ' '.join(res)))
        sys.stdout.flush()
