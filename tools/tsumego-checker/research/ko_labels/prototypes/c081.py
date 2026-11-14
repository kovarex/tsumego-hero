from harness import *
from profile import prof
p = region_pos('15220', 'D19 E19 A16 B18 B19 A18 D18', 'A16 B16 C16 A17 B17 C17 A18 B18 C18 D18 A19 B19 C19 D19')
p.show(wide=False)
board, t = p.board, p.to_move
for (s, o) in ((0, 0), (0, 1), (1, 0)):
    r, ans = p.count(board, t, s, o, extra='end=1,pv=1', budget=400000)
    print('leaf', (s, o), {True: 'win', False: 'fail', None: '?'}[r], ans)
prof(p, 'A19 pass C19', 'B', [0, 1, 2], lo=-3, hi=3, extra='end=1')
prof(p, 'A19 pass C19 B19!', 'W', [0, 1, 2], lo=-3, hi=3, extra='end=1')
prof(p, 'A19', 'B', [0, 1], lo=-3, hi=3, extra='end=1')
