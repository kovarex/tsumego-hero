import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
from harness import *
p = region_pos('15220', 'D19 E19 A16 B18 B19 A18 D18', 'A16 B16 C16 A17 B17 C17 A18 B18 C18 D18 A19 B19 C19 D19')
p.show(wide=False)
def run(moves, opts, pairs):
    board, t = p.after(moves)
    for s, o in pairs:
        t0 = time.time()
        r, ans = p.count(board, t, s, o, extra=opts, budget=400000)
        f = dict(item.split('=', 1) for item in ans.split()[1:])
        print('[%s] %s to move opts=%s s=%d o=%d: solver %s nodes=%s %.2fs pv=%s' % (moves or 'leaf', t, opts, s, o, {True:'WINS',False:'fails',None:'??'}[r], f.get('nodes'), time.time()-t0, f.get('pv','')[:140]))
        sys.stdout.flush()
run('', 'end=1,pv=1', [(0,0),(0,1),(1,0)])
run('', 'end=0,pv=1', [(0,0),(1,1)])
run('A19 pass C19', 'end=1,pb=1,pv=1', [(0,0),(4,0)])
run('A19 pass C19', 'end=1,pb=2,pv=1', [(4,0)])
run('A19 pass C19 B19!', 'end=1,pw=1,pv=1', [(0,0),(0,1),(0,4)])
run('A19 pass C19 B19!', 'end=1,pw=2,pv=1', [(0,4)])
run('A19 pass C19 B19! pass C19', 'end=1,pv=1', [(0,4)])
