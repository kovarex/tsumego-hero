#forced-pass profiles: threshold of a position when one side must pass its next k turns
import sys
from harness import *
def prof(p, moves, side_passes, ks, lo=-4, hi=4, wide=False, budget=400000, extra=''):
    board, to_move = p.after(moves)
    out = {}
    for k in ks:
        opts = ('p%s=%d' % (side_passes.lower(), k)) + ',pv=1' + (',' + extra if extra else '')
        d = None
        last = None
        for dd in range(lo, hi + 1):
            r, ans = p.count(board, to_move, max(dd, 0), max(-dd, 0), wide=wide, budget=budget, extra=opts)
            last = ans
            if r is None:
                d = '?'
                break
            if r:
                d = dd
                break
        else:
            d = '+inf'
        if d == lo:
            d = '-inf(<=%d)' % lo
        out[k] = d
        print('   [%s] %s passes %d: d=%s   %s' % (moves, side_passes, k, d, last))
        sys.stdout.flush()
    return out
if __name__ == '__main__':
    pid, line = sys.argv[1], sys.argv[2]
    p = Pos(pid, line)
    for spec in sys.argv[3:]:
        moves, side, ks = spec.split('|')
        prof(p, moves, side, [int(k) for k in ks.split(',')])
