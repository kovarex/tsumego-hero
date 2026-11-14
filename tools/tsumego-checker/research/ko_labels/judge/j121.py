#judge checks of the c121 model through engine_x (Design 3's forced-pass profile) - one search at a time
import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
import m121
from m121 import count, idx
A18 = idx('A18')
def run(label, line, s, o, opts='-', budget=400000):
    r, n, t, pv = count(line, s, o, opts, budget)
    print('%-58s line=%-22s s=%d o=%d opts=%-18s -> %s nodes=%d %.2fs pv=%s' % (label, line or 'leaf', s, o, opts, {'K':'Black wins','N':'Black fails','U':'undecided'}[r], n, t, pv[:120]))
    sys.stdout.flush()
run('P_S, W passes 1, s-o=0', 'A17!', 0, 0, 'pw=1,pv=1')
run('P_S, W passes 1, s-o=1', 'A17!', 1, 0, 'pw=1,pv=1')
run('P_S, W passes 1, outright (0,3)', 'A17!', 0, 3, 'pw=1')
run('P_S, W passes 2, outright (0,3)', 'A17!', 0, 3, 'pw=2,pv=1')
run('frozen: W passes 1 and barred from A18, (0,3)', 'A17!', 0, 3, 'pw=1,fw=%d,pv=1' % A18)
run('P_O=leaf, B passes 1, B outright with (4,0)?', '', 4, 0, 'pb=1,pv=1')
run('direct ko A17! pass E17 A18, s-o=0', 'A17! pass E17 A18', 0, 0, 'pv=1')
run('direct ko A17! pass E17 A18, s-o=1', 'A17! pass E17 A18', 1, 0, 'pv=1')
run('A17! H19 E17 A18, s-o=1', 'A17! H19 E17 A18', 1, 0, 'pv=1')
run('A17! H19 E17 A18, s-o=2', 'A17! H19 E17 A18', 2, 0, 'pv=1')
