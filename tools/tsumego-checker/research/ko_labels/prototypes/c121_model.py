#problem 15678 leaf c121, a hand-built model board for the engine, target = the top White stones D18-G18 only.
#The open parts of the real board are modelled so the engine can't misread them:
# - B14-B13-C13 (open to the bottom in reality) is made unconditionally alive (eyes A11, C11), A13/A14/A15/B15/B16 stay playable
# - behind E16 an unconditionally alive White group (eyes G15, G13): White E17+E16 joining it = the top stones escape
#   (E15 is wall so Black's D-chain stays safe; F17-G17 touches the wall at H17)
# - H19, J19, K19, J18 playable (White's H19 trick and its crawl), beyond is Black's wall
#usage: model.py "<moves from the leaf>" [s,o ...] [--plain] [--budget=N] [--noesc] [--noh19] [--line=s,o] [--moves]
#moves: points, 'pass', 'X!' = retake against the ko ban (one threat). They alternate starting with Black at the leaf.
import sys, os, time
sys.path.insert(0, '/tmp/claude-0/proto')
from tsumego import Engine

args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = set(a for a in sys.argv[1:] if a.startswith('--') and '=' not in a)
options = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
budget = int(options.get('budget', '2000000'))
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
if '--noesc' in flags:   #no escape behind E16: E16 becomes wall (like the narrow search area)
    rows[16] = "O.OX#OOO"
if '--noh19' in flags:   #no room on the right of G19: J19 becomes wall, as in the narrow search area
    rows[19] = "X.XO..X.#"
    rows[18] = "OXXOOOOX#"
grid = {}
for y in range(N):
    r = N - y
    text = rows.get(r, '')
    for x in range(N):
        grid[(x, y)] = text[x] if x < len(text) else '#'

def idx(name):
    x = LET.index(name[0]); y = N - int(name[1:])
    return y * N + x
def pname(i):
    if i is None or i < 0: return 'pass'
    return '%s%d' % (LET[i % N], N - i // N)

#a tiny go board for playing the line
board = [grid[(i % N, i // N)] for i in range(N * N)]
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
            if (b[r] == '.' or (b[r] == '#' and c == 'X')) and r not in libs: libs.add(r)  #the wall is Black's: Black chains touching it are safe
            elif b[r] == c and r not in seen: seen.add(r); todo.append(r)
    return seen, libs
ko = idx('A17')
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
    newko = cap[0] if len(set(cap)) == 1 and len(s) == 1 and len(l) == 1 else None
    return b, newko

line = args[0].split() if args else []
to = 'X'
used = {'X': 0, 'O': 0}
for t in line:
    ign = t.endswith('!')
    p = None if t.rstrip('!') == 'pass' else idx(t.rstrip('!'))
    b2, k2 = play(board, to, p, ko, ign)
    if b2 is None: sys.exit('illegal %s' % t)
    if ign: used[to] += 1
    board, ko = b2, k2
    to = 'O' if to == 'X' else 'X'
tomove = 'B' if to == 'X' else 'W'
text = ''.join(board)
target = ','.join(str(idx(n)) for n in ('D18', 'E18', 'F18', 'G18'))
eng = Engine('/tmp/claude-0/proto/research/scratch/15678-c121-judge-a/engine_keep')
init = ''.join(grid[(i % N, i // N)] for i in range(N * N))
print(eng.command('problem %d B %s %s' % (N, init, target)))
#White capturing the corner stones (B18 with them) joins the top stones to the corner: counted as life
print(eng.command('keep %s' % ('-' if '--nokeep' in flags else str(idx('B18')))))

def show():
    for r in (19, 18, 17, 16, 15, 14, 13):
        y = N - r
        print('%3d %s' % (r, ' '.join(('*' if (y * N + x) == ko and board[y*N+x] == '.' else board[y * N + x]) for x in range(11))))
    print('    ' + ' '.join(LET[:11]))
show()
print('moves from the leaf: %s; %s to play; ko ban at %s; retakes used B%d W%d' % (' '.join(line) or '(none)', tomove, pname(ko) if ko is not None else '-', used['X'], used['O']))
kotext = str(ko) if ko is not None else '-1'
t0 = time.time()
if '--plain' in flags:
    a = eng.command('solve %s %s %d %s' % (tomove, kotext, budget, text))
    print('plain:', a, '(%.0f s)' % (time.time() - t0))
for pair in args[1:]:
    s, o = (int(v) for v in pair.split(','))
    t1 = time.time()
    a = eng.command('solvecount %s %s %d %d %d %s' % (tomove, kotext, budget, s, o, text))
    f = dict(item.split('=') for item in a.split()[1:])
    res = {'K': 'Black kills', 'N': 'Black fails', 'U': 'undecided'}[f['N']]
    print('  s=%d o=%d: %s  best=%s nodes=%s (%.0f s)' % (s, o, res, pname(int(f['best'])), f['nodes'], time.time() - t1))
    sys.stdout.flush()
if '--moves' in flags:
    s, o = (int(v) for v in options.get('mo', '0,0').split(','))
    print('one-move table with s=%d o=%d (counted threats left after the move):' % (s, o))
    out = {}
    for i in range(N * N):
        if board[i] != '.': continue
        for ign in (False, True):
            if ign and i != ko: continue
            if ign and (s if tomove == 'B' else o) == 0: continue
            b2, k2 = play(board, to, i, ko, ign)
            if b2 is None: continue
            s2, o2 = s, o
            if ign:
                if tomove == 'B': s2 -= 1
                else: o2 -= 1
            other = 'W' if tomove == 'B' else 'B'
            a = eng.command('solvecount %s %s %d %d %d %s' % (other, str(k2) if k2 is not None else '-1', budget, s2, o2, ''.join(b2)))
            f = dict(item.split('=') for item in a.split()[1:])
            out.setdefault(f['N'], []).append(pname(i) + ('!' if ign else ''))
    b2 = list(board)
    other = 'W' if tomove == 'B' else 'B'
    a = eng.command('solvecount %s -1 %d %d %d %s' % (other, budget, s, o, ''.join(b2)))
    f = dict(item.split('=') for item in a.split()[1:])
    out.setdefault(f['N'], []).append('pass')
    for k, v in out.items():
        print('  %s: %s' % ({'K': 'Black kills', 'N': 'Black fails', 'U': 'undecided'}[k], ' '.join(v)))
print('(%.0f s total)' % (time.time() - t0))
