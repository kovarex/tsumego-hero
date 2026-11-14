#c121 model board (from the judge's model.py), driven through engine_x with solvex options
import sys, time
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
from tsumego import Engine
LET = 'ABCDEFGHJKLMNOPQRST'
N = 19
rows = {19: "X.XO..X..", 18: "OXXOOOOX.", 17: ".OOX.XX#X", 16: "O.OX.OOO", 15: "..OX#O.O",
        14: ".OXX#OOO", 13: ".OOO#O.O", 12: "OOOO#OOO", 11: ".O.O", 10: "OOOO"}
grid = {}
for y in range(N):
    text = rows.get(N - y, '')
    for x in range(N):
        grid[(x, y)] = text[x] if x < len(text) else '#'
def idx(name):
    return (N - int(name[1:])) * N + LET.index(name[0])
def pname(i):
    return 'pass' if i is None or i < 0 else '%s%d' % (LET[i % N], N - i // N)
def nbrs(i):
    x, y = i % N, i // N
    for dx, dy in ((1,0),(-1,0),(0,1),(0,-1)):
        a, b = x + dx, y + dy
        if 0 <= a < N and 0 <= b < N:
            yield b * N + a
def chain(b, i):
    c = b[i]; seen = {i}; todo = [i]; libs = set()
    while todo:
        q = todo.pop()
        for r in nbrs(q):
            if (b[r] == '.' or (b[r] == '#' and c == 'X')) and r not in libs: libs.add(r)
            elif b[r] == c and r not in seen: seen.add(r); todo.append(r)
    return seen, libs
def play(b, color, i, ko, ignore):
    if i is None: return b, None
    if b[i] != '.': return None, None
    if i == ko and not ignore: return None, None
    b = list(b); b[i] = color; opp = 'O' if color == 'X' else 'X'; cap = []
    for r in nbrs(i):
        if b[r] == opp:
            s, l = chain(b, r)
            if not l: cap += list(s)
    for q in set(cap): b[q] = '.'
    s, l = chain(b, i)
    if not l: return None, None
    return b, (cap[0] if len(set(cap)) == 1 and len(s) == 1 and len(l) == 1 else None)
eng = Engine('/tmp/claude-0/proto/research/kodesign/game/engine_x')
init = ''.join(grid[(i % N, i // N)] for i in range(N * N))
target = ','.join(str(idx(n)) for n in ('D18', 'E18', 'F18', 'G18'))
eng.command('problem %d B %s %s' % (N, init, target))
eng.command('keep %s' % idx('B18'))
def position(line):
    board, ko, to = [grid[(i % N, i // N)] for i in range(N * N)], idx('A17'), 'X'
    for t in line.split():
        ign = t.endswith('!')
        p = None if t.rstrip('!') == 'pass' else idx(t.rstrip('!'))
        b2, k2 = play(board, to, p, ko, ign)
        if b2 is None: sys.exit('illegal %s' % t)
        board, ko = b2, k2
        to = 'O' if to == 'X' else 'X'
    return ''.join(board), ko, ('B' if to == 'X' else 'W')
def count(line, s, o, opts='-', budget=2000000):
    text, ko, tm = position(line)
    t0 = time.time()
    a = eng.command('solvex %s %d %d %d %d %s %s' % (tm, ko if ko is not None else -1, budget, s, o, opts, text))
    f = dict(item.split('=', 1) for item in a.split()[1:])
    pv = ''
    if 'pv' in f and f['pv'] != '-':
        items = []
        for it in f['pv'].split('_'):
            if not it: continue
            if it in ('fp', 'cycle'): items.append(it); continue
            who, rest = it[0], it[1:]
            bang = rest.endswith('!'); rest = rest.rstrip('!')
            items.append(who + (pname(int(rest)) if rest != 'pass' else 'pass') + ('!' if bang else ''))
        pv = ' '.join(items)
    return f['N'], int(f['nodes']), time.time() - t0, pv
def threshold(line, lo, hi, opts='-', budget=2000000):
    for d in range(lo, hi + 1):
        r, n, t, pv = count(line, max(d, 0), max(-d, 0), opts, budget)
        print('  [%s] opts=%s d=%+d: %s nodes=%d %.1fs %s' % (line or 'leaf', opts, d, {'K': 'win', 'N': 'fail', 'U': '?'}[r], n, t, pv))
        sys.stdout.flush()
        if r == 'K':
            return d
        if r == 'U':
            return None
    return None
if __name__ == '__main__':
    line = sys.argv[1] if len(sys.argv) > 1 else ''
    lo, hi = int(sys.argv[2]), int(sys.argv[3])
    opts = sys.argv[4] if len(sys.argv) > 4 else '-'
    budget = int(sys.argv[5]) if len(sys.argv) > 5 else 2000000
    print(threshold(line, lo, hi, opts, budget))
