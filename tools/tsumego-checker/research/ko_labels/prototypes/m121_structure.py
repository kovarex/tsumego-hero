#problem 15678 leaf c121 on judge A's hand-built model board (target D18-G18, White capturing B18 counts as life),
#with the structure tests: direct-ko test, approach search, the direct position P_d, counted thresholds.
#usage: m121.py <command> ... [--budget=N] [--noh19]
#  count "<moves from the leaf>" s o
#  direct X "<moves from the leaf>" "<pre-placed X moves>"
#  approach X N "<moves>" "<candidates>"
#  pd "<moves>" X "<approaches>" lo hi
import sys, os, time, itertools
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
from tsumego import Engine

args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = set(a for a in sys.argv[1:] if a.startswith('--') and '=' not in a)
options = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
budget = int(options.get('budget', '400000'))
LET = 'ABCDEFGHJKLMNOPQRST'
N = 19
rows = {
 19: "X.XO..X..",
 18: "OXXOOOOX.",
 17: ".OOX.XX#X",
 16: "O.OX.OOO",
 15: "..OX#O.O",
 14: ".OXX#OOO",
 13: ".OOO#O.O",
 12: "OOOO#OOO",
 11: ".O.O",
 10: "OOOO",
}
if '--noh19' in flags:
    rows[19] = "X.XO..X.#"
    rows[18] = "OXXOOOOX#"
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
    for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
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
    if i is None: return list(b), None
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
    newko = cap[0] if len(set(cap)) == 1 and len(s) == 1 and len(l) == 1 else None
    return b, newko
col = {'B': 'X', 'W': 'O'}
def run(moves):
    b = [grid[(i % N, i // N)] for i in range(N * N)]
    ko = idx('A17'); to = 'B'
    for t in moves.split():
        ign = t.endswith('!')
        p = None if t.rstrip('!') == 'pass' else idx(t.rstrip('!'))
        b2, k2 = play(b, col[to], p, ko, ign)
        if b2 is None: sys.exit('illegal %s' % t)
        b, ko = b2, k2
        to = 'W' if to == 'B' else 'B'
    return b, ko, to
def kos(b):
    out = []
    for i in range(N * N):
        if b[i] not in 'XO': continue
        s, l = chain(b, i)
        if len(s) != 1 or len(l) != 1: continue
        p = next(iter(l))
        if b[p] != '.': continue
        b2, k2 = play(b, 'O' if b[i] == 'X' else 'X', p, None, True)
        if b2 is not None and k2 == i:
            out.append(('B' if b[i] == 'X' else 'W', i, p))
    return out
eng = Engine('/tmp/claude-0/proto/research/kodesign/structure/engine_ks')
init = ''.join(grid[(i % N, i // N)] for i in range(N * N))
target = ','.join(str(idx(n)) for n in ('D18', 'E18', 'F18', 'G18'))
eng.command('problem %d B %s %s' % (N, init, target))
eng.command('keep %s' % idx('B18'))
def count(b, ko, to, s, o):
    a = eng.command('solvecount %s %d %d %d %d %s' % (to, ko if ko is not None else -1, budget, s, o, ''.join(b)))
    f = dict(item.split('=') for item in a.split()[1:])
    pv = []
    if f.get('pv', '-') != '-':
        for item in f['pv'].split(','):
            bang = item.endswith('!'); item = item.rstrip('!')
            pv.append('pass' if item == 'pass' or int(item) < 0 else pname(int(item)) + ('!' if bang else ''))
    return {'K': 'Black kills', 'N': 'Black fails', 'U': 'undecided'}[f['N']], f['nodes'], ' '.join(pv)
def hold(b, X):
    for c, q, p in kos(b):
        if c != X:
            b2, k2 = play(b, col[X], p, None, True)
            return b2
        return list(b)
    return list(b)
def direct(b, X, pre):
    q = hold(b, X)
    for t in pre:
        q2, _ = play(q, col[X], idx(t), None, True)
        if q2 is None: return None
        q = q2
    a = eng.command('solve %s -1 %d %s' % (X, budget, ''.join(q)))
    f = dict(item.split('=') for item in a.split()[1:])
    return f['A'] == 'K' if X == 'B' else f['D'] == 'N'
t0 = time.time()
cmd = args[0]
if cmd == 'count':
    b, ko, to = run(args[1])
    print(args[1] or '(leaf)', to, 'to move; s=%s o=%s:' % (args[2], args[3]), *count(b, ko, to, int(args[2]), int(args[3])))
elif cmd == 'direct':
    b, ko, to = run(args[2])
    print('kos', [(c, pname(q), pname(p)) for c, q, p in kos(b)])
    print('direct for %s after pre-placing %s: %s' % (args[1], args[3] if len(args) > 3 else '-', direct(b, args[1], (args[3].split() if len(args) > 3 else []))))
elif cmd == 'approach':
    X, n_max = args[1], int(args[2])
    b, ko, to = run(args[3])
    cands = args[4].split()
    for n in range(n_max + 1):
        hit = None
        for combo in itertools.combinations(cands, n):
            d = direct(b, X, combo)
            print('  %s: %s' % (' '.join(combo) or '-', d)); sys.stdout.flush()
            if d: hit = combo; break
        if hit is not None:
            print('approaches for %s: %d (%s)' % (X, n, ' '.join(hit))); break
elif cmd == 'pd':
    b, ko, to = run(args[1])
    X = args[2]; Y = 'W' if X == 'B' else 'B'
    q = hold(b, X); seq = []
    for a in args[3].split():
        q, _ = play(q, col[X], idx(a), None, True); seq.append(a)
        k = [k for k in kos(q) if k[0] == X][0]
        q, newko = play(q, col[Y], k[2], None, True); seq.append(pname(k[2]))
    print('P_d: %s holds, then %s; %s to move, ban %s' % (X, ' '.join(seq), X, pname(newko)))
    for k in range(int(args[4]), int(args[5]) + 1):
        s, o = max(k, 0), max(-k, 0)
        print('  s-o=%+d:' % k, *count(q, newko, X, s, o)); sys.stdout.flush()
print('(%.1f s)' % (time.time() - t0))
