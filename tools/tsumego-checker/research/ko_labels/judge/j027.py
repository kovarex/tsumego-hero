import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
from harness import *
p = Pos('8937', 'B19 E17 A18 A17 C19 A15 D18')
p.show(wide=False)
board, t = p.board, p.to_move
text, ko = p.text(board)
print('solver', p.solver_color, 'attacker', p.attacker, 'to move', t)
for rule in (0, 2, 1):
    p.solver.activate_engine()
    p.engine.command('cycle %d' % rule)
    t0 = time.time()
    print('cycle', rule, 'solve:', p.engine.command('solve %s %d %d %s' % (t, ko, 200000, text)), '%.2fs' % (time.time() - t0))
p.engine.command('cycle 0')
p.engine.command('bentfour 1')
print('bentfour on:', p.engine.command('solve %s %d %d %s' % (t, ko, 200000, text)))
p.engine.command('bentfour 0')
for s, o in ((0, 3), (0, 4), (0, 6)):
    for end in (0, 1):
        r, ans = p.count(board, t, s, o, extra='end=%d,pv=1' % end, budget=1000000)
        print('count s=%d o=%d end=%d: solver %s %s' % (s, o, end, {True:'WINS',False:'fails',None:'??'}[r], ans[:200]))
        sys.stdout.flush()
