import sys
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/judge')
sys.argv = ['x']
exec(open('/tmp/claude-0/proto/research/kodesign/judge/j121.py').read().split("run('P_S, W passes 1, s-o=0'")[0])
run('held-ko test: P_S, W barred from A18, no forced pass, (0,3)', 'A17!', 0, 3, 'fw=%d,pv=1' % A18, 2000000)
run('held-ko test: P_S, W barred from A18, no forced pass, (0,0)', 'A17!', 0, 0, 'fw=%d,pv=1' % A18, 2000000)
