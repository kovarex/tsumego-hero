from harness import *
from profile import prof
pts = 'A19 B19 C19 D19 E19 F19 A18 B18 C18 D18 E18 A17 B17 C17 D17 E17 A16 A15'
p = region_pos('2406', 'D17 D19 E19 B19 B18 C19 A17', pts)
b, t = p.after('D18 A19 A18')
p.show(b, wide=False)
prof(p, 'D18 A19 A18', 'B', [0, 1, 2, 3], lo=-4, hi=3, extra='end=1')
prof(p, 'D18 A19 A18 A19!', 'W', [0, 1], lo=-4, hi=3, extra='end=1')
