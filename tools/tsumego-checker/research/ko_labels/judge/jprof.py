#judge: Design 3's forced-pass profile on a problem leaf (engine_x); one search at a time
#usage: jprof.py <id> "<leaf moves>" "<spec>" ...   spec = moves-from-leaf|side-forced-to-pass|k list|s,o list|extra
import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
from harness import *
pid, leaf = sys.argv[1], sys.argv[2]
wide = '--wide' in sys.argv
budget = 400000
for a in sys.argv:
    if a.startswith('--budget='): budget = int(a.split('=')[1])
p = Pos(pid, leaf)
print('problem', pid, 'leaf', leaf, 'solver', p.solver_color, 'attacker', p.attacker, 'to move', p.to_move)
for spec in [a for a in sys.argv[3:] if not a.startswith('--')]:
    parts = spec.split('|')
    moves, side, ks, pairs = parts[0], parts[1], parts[2], parts[3]
    extra = parts[4] if len(parts) > 4 else ''
    board, to_move = p.after(moves)
    for k in ks.split(','):
        for pr in pairs.split(';'):
            s, o = (int(v) for v in pr.split(','))
            opts = []
            if side in 'BW' and int(k) > 0:
                opts.append('p%s=%s' % (side.lower(), k))
            opts.append('pv=1')
            if extra:
                opts.append(extra)
            t0 = time.time()
            r, ans = p.count(board, to_move, s, o, wide=wide, budget=budget, extra=','.join(opts))
            f = dict(item.split('=', 1) for item in ans.split()[1:])
            print('  [%s] %s to move, %s forced %s, s=%d o=%d %s: solver %s  nodes=%s %.2fs pv=%s' % (
                moves or 'leaf', to_move, side, k, s, o, extra, {True: 'WINS', False: 'fails', None: '??'}[r], f.get('nodes'), time.time() - t0, f.get('pv', '')[:150]))
            sys.stdout.flush()
