#checks the direct-ko approach count against the owner's approach labels (A+n / A-n leaves of the export) and the
#10kKO leaves: for each such leaf with a ko on the board, the approach count of both sides by the direct-ko test.
#usage: batch_approach.py [limit] [--wide]
import json, os, sys, time, itertools, re
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
os.environ.setdefault('ENGINE', '/tmp/claude-0/proto/research/kodesign/structure/engine_ks')
from check_tree import *
from tsumego import add_wide, Engine, Solver, Problem

limit = int(sys.argv[1]) if len(sys.argv) > 1 and not sys.argv[1].startswith('--') else 999
engine = Engine()
engine.command('endplay 1')

def kos(board):
    out = []
    for q, c in board.stones.items():
        stones, libs = board.chain(q)
        if len(stones) != 1 or len(libs) != 1:
            continue
        p = next(iter(libs))
        child = board.copy()
        if child.play(opponent(c), p, ignore_ko=True) and child.ko == q:
            out.append((c, q, p, board.ko == p))
    return out

def run_leaf(record, path_nodes, label):
    pass

results = []
count = 0
for l in open('/tmp/claude-0/dump2/status_problems.jsonl'):
    r = json.loads(l)
    sgf_root = parse_sgf(r['sgf'])
    try:
        root, S, size = build(sgf_root)
    except Exception:
        continue
    goal = sgf_root.get('G')
    if goal not in ('KILL', 'LIVE'):
        continue
    leaves = [n for n in all_nodes(root) if not n.children and n.status_text and (re.match(r'A[+-]\d+KO', n.status_text.strip()) or n.status_text.strip() == '10kKO')]
    if not leaves:
        continue
    tree_points = [n.move for n in all_nodes(root) if n.move]
    box = region_of(size, list(root.board.stones) + tree_points)
    defender = opponent(S) if goal == 'KILL' else S
    attacker = opponent(defender)
    target = groups_of(root.board, defender)[0]
    problem = Problem(root.board, focused_region(root.board, box, target, tree_points))
    solver = Solver(engine, problem, attacker, target, 100000)
    solver.activate()
    engine.command('endplay 1')
    name = lambda p: point_name(p, size)
    for leaf in leaves:
        if count >= limit:
            break
        count += 1
        label = leaf.status_text.strip()
        m = re.match(r'A([+-])(\d+)KO', label)
        board, to_move = leaf.board, leaf.to_move
        moves = []
        n = leaf
        while n.parent_node is not None:
            moves.append(name(n.move))
            n = n.parent_node
        moves = ' '.join(reversed(moves))
        ks = kos(board)
        ks.sort(key=lambda k: (not k[3],))
        entry = {'id': r['set_connection_id'], 'line': moves, 'label': label, 'to_move': to_move}
        if not ks:
            entry['note'] = 'no ko on the board'
            results.append(entry)
            print(json.dumps(entry)); sys.stdout.flush()
            continue
        holder, q, p, banned = ks[0]
        entry['ko'] = '%s %s capturable at %s%s' % (holder, name(q), name(p), ' banned' if banned else '')
        if len(ks) > 1:
            entry['other_kos'] = len(ks) - 1
        #candidate approach points: empty liberties of the chains next to the ko stone or its capture point, and of
        #the target's chains, inside the region
        chains = set()
        for pt in (q, p):
            for nb in board.neighbours(pt):
                if nb in board.stones:
                    chains.add(nb)
        for t in target:
            if t in board.stones:
                chains.add(t)
        cands = set()
        for c in chains:
            stones, libs = board.chain(c)
            cands |= {x for x in libs if x in problem.region and x != p and x != q}
        cands = sorted(cands)
        entry['cands'] = ' '.join(name(c) for c in cands)
        start = time.time()
        def direct(X, pre):
            b = board.copy()
            if holder != X:
                if not b.play(X, p, ignore_ko=True):
                    return None
            for t in pre:
                if not b.play(X, t, ignore_ko=True):
                    return None
            b.ko = None
            text = problem.board_text(b)
            a = engine.command('solve %s -1 %d %s' % (X, 100000, text))
            f = dict(item.split('=') for item in a.split()[1:])
            if X == attacker:
                return {'K': True, 'N': False}.get(f['A'])
            return {'N': True, 'K': False}.get(f['D'])
        for X in (S, opponent(S)):
            found = None
            for k in range(0, 4):
                for combo in itertools.combinations(cands, k):
                    d = direct(X, combo)
                    if d:
                        found = (k, ' '.join(name(c) for c in combo))
                        break
                if found or time.time() - start > 60:
                    break
            entry['n_' + ('solver' if X == S else 'opponent')] = found[0] if found else None
            entry['app_' + ('solver' if X == S else 'opponent')] = found[1] if found else None
        if m:
            want_side = 'opponent' if m.group(1) == '+' else 'solver'
            entry['label_n'] = int(m.group(2))
            got = entry['n_' + want_side]
            other = entry['n_' + ('solver' if want_side == 'opponent' else 'opponent')]
            entry['match'] = got == int(m.group(2)) and other == 0
        else:
            entry['match'] = bool(entry['n_solver']) and bool(entry['n_opponent'])
        entry['seconds'] = round(time.time() - start, 1)
        results.append(entry)
        print(json.dumps(entry)); sys.stdout.flush()
json.dump(results, open('/tmp/claude-0/proto/research/kodesign/structure/batch_approach.json', 'w'), indent=1)
