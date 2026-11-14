from harness import *
from profile import prof
pts = 'A19 B19 C19 D19 E19 F19 A18 B18 C18 D18 E18 A17 B17 C17 D17 E17 A16 A15'
p = region_pos('2406', 'D17 D19 E19 B19 B18 C19 A17', pts)
p.show(wide=False)
board, t = p.board, p.to_move
for (s, o) in ((0, 0), (0, 1), (0, 2), (1, 0)):
    r, ans = p.count(board, t, s, o, extra='end=1,pv=1', budget=400000)
    print('leaf', (s, o), {True: 'win', False: 'fail', None: '?'}[r], ans)
text, ko = p.text(board)
p.solver.activate_engine()
print('modes', p.engine.command('solve %s %d %d %s' % (t, ko, 200000, text)))
