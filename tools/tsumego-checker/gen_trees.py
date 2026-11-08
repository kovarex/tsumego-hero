#Builds tsumego.com style SGFs from solved first-move problems (correct move marked +, wrong moves with the solver's
#refutation), plus copies with one injected error each, to measure what check_tree.py catches
import json, sys, os, random
from tsumego import *
from first_moves import opponent, rank, groups_of

def sgf_point(p):
    return chr(97 + p[0]) + chr(97 + p[1])

def write(path, data, branches):
    stones = ''.join('[%s]' % v for v in data.get('AB', []))
    white = ''.join('[%s]' % v for v in data.get('AW', []))
    body = ''.join('(' + b + ')' for b in branches)
    open(path, 'w').write('(;GM[1]FF[4]SZ[19]AB%sAW%s%s)' % (stones, white, body))

def main(results_path, out_dir, budget, limit):
    random.seed(1)
    os.makedirs(out_dir, exist_ok=True)
    engine = Engine()
    made = 0
    for line in open(results_path):
        result = json.loads(line)
        if result['verdict'] not in ('confirmed', 'alternatives') or result['goal'] is None or result['best_rank'] != 2:
            continue
        data = json.load(open(result['path']))
        size = 19
        board = Board(size)
        for p in expand_points(data.get('AB', []), size):
            board.stones[p] = 'B'
        for p in expand_points(data.get('AW', []), size):
            board.stones[p] = 'W'
        solver_color, goal = result['solver'], result['goal']
        answer = tuple(result['answer'])
        box = region_of(size, list(board.stones) + [answer])
        color = opponent(solver_color) if goal == 'KILL' else solver_color
        stones = [g for g in groups_of(board, color) if len(g) == result['target']][0]
        attacker = solver_color if goal == 'KILL' else opponent(solver_color)
        problem = Problem(board, focused_region(board, box, stones, [answer]))
        solver = Solver(engine, problem, attacker, stones, budget)
        alternatives = {tuple(p) for p in result['alternatives']}
        wrong = []
        for p in sorted(problem.region):
            if p in board.stones or p == answer or p in alternatives:
                continue
            child = board.copy()
            if not child.play(solver_color, p):
                continue
            status, refutation = solver.status(child, opponent(solver_color))
            if rank(status, goal) == 0 and refutation:
                wrong.append((p, refutation))
        if len(wrong) < 2:
            continue
        wrong = random.sample(wrong, min(3, len(wrong)))
        sc, oc = solver_color, opponent(solver_color)
        name = os.path.basename(result['path'])[:-5]
        correct_branch = ';%s[%s]C[+]' % (sc, sgf_point(answer))
        alternative_branches = [';%s[%s]C[+]' % (sc, sgf_point(p)) for p in sorted(alternatives)]
        wrong_branches = [';%s[%s];%s[%s]' % (sc, sgf_point(p), oc, sgf_point(r)) for p, r in wrong]
        write(os.path.join(out_dir, name + '-clean.sgf'), data, [correct_branch] + alternative_branches + wrong_branches)
        #1: a wrong move marked correct
        p, r = wrong[0]
        write(os.path.join(out_dir, name + '-wrong-marked-correct.sgf'), data,
              [correct_branch] + alternative_branches + [';%s[%s]C[+]' % (sc, sgf_point(p))] + wrong_branches[1:])
        #2: the correct move missing (only the wrong branches remain)
        write(os.path.join(out_dir, name + '-correct-missing.sgf'), data, [correct_branch.replace('C[+]', '')] + wrong_branches)
        #3: a refutation that doesn't refute: the opponent answers somewhere the solver can still win from
        weak = None
        child = board.copy()
        child.play(sc, p)
        for q in sorted(problem.region):
            if q in child.stones or q == r:
                continue
            grand = child.copy()
            if grand.play(oc, q) and rank(solver.status(grand, sc)[0], goal) == 2:
                weak = q
                break
        if weak:
            write(os.path.join(out_dir, name + '-weak-refutation.sgf'), data,
                  [correct_branch] + alternative_branches + [';%s[%s];%s[%s]' % (sc, sgf_point(p), oc, sgf_point(weak))] + wrong_branches[1:])
        made += 1
        if made >= limit:
            break
    print('made', made)

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4]))
