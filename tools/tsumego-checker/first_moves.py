#Checks first-move-only collections (sanderland/tsumego JSON): does the known first move solve, which other first moves solve too
import json, sys, time, glob
from tsumego import *

def opponent(color):
    return 'W' if color == 'B' else 'B'

def solver_wins(status, goal):
    return status == (STATUS_DEAD if goal == 'KILL' else STATUS_ALIVE)

def rank(status, goal):
    #2 the solver succeeds, 1 ko, 0 fails, None unknown
    if status == STATUS_UNKNOWN or status is None:
        return None
    if status == STATUS_KO:
        return 1
    if status == STATUS_BENT4:
        return 2 if goal == 'KILL' else 0 #almost dead
    return 2 if solver_wins(status, goal) else 0

def detect_goal(engine, box, extra_points, board, solver_color, budget):
    #layer A: a target whose fate depends on who moves first; cheap pass over all candidates first, then the full budget
    candidates = []
    for goal, color in (('KILL', opponent(solver_color)), ('LIVE', solver_color)):
        for stones in groups_of(board, color)[:3]:
            candidates.append((goal, stones))
    found = []
    for pass_budget in (budget // 20, budget):
        found = []
        for goal, stones in candidates:
            attacker = solver_color if goal == 'KILL' else opponent(solver_color)
            problem = Problem(board, focused_region(board, box, stones, extra_points))
            solver = Solver(engine, problem, attacker, stones, pass_budget)
            first, _ = solver.status(board, solver_color)
            second, _ = solver.status(board, opponent(solver_color))
            unsettled = rank(first, goal) is not None and rank(second, goal) is not None and rank(first, goal) > rank(second, goal)
            found.append((unsettled, len(stones), goal, solver, first, second))
            if unsettled:
                solver.budget = budget
                return found[-1], found
    return None, found

def check(path, engine, budget):
    data = json.load(open(path))
    size = int(data.get('SZ', 19))
    board = Board(size)
    for p in expand_points(data.get('AB', []), size):
        board.stones[p] = 'B'
    for p in expand_points(data.get('AW', []), size):
        board.stones[p] = 'W'
    solver_color = 'W' if data.get('C', '').startswith('White') else 'B'
    answer = point(data['SOL'][0][1], size)
    box = region_of(size, list(board.stones) + [answer])
    start = time.time()
    chosen, tried = detect_goal(engine, box, [answer], board, solver_color, budget)
    result = {'path': path, 'answer': answer, 'solver': solver_color}
    if not chosen:
        result['verdict'] = 'goal-unclear'
        result['tried'] = [(t[2], t[1], t[4], t[5]) for t in tried]
        result['seconds'] = time.time() - start
        return result
    _, _, goal, solver, _, _ = chosen
    result['goal'] = goal
    result['target'] = len(solver.target)
    region = solver.problem.region
    result['region'] = len(region)
    statuses = {}
    for p in sorted(region):
        if p in board.stones:
            continue
        child = board.copy()
        if not child.play(solver_color, p):
            continue
        statuses[p] = solver.status(child, opponent(solver_color))[0]
    ranks = {p: rank(status, goal) for p, status in statuses.items()}
    known = [r for r in ranks.values() if r is not None]
    best = max(known) if known else None
    result['answer_status'] = statuses.get(answer)
    result['best_rank'] = best
    result['alternatives'] = [p for p, r in ranks.items() if r == best and p != answer and best and best > 0]
    result['better'] = [p for p, r in ranks.items() if best is not None and r == best and ranks.get(answer) is not None and r > ranks.get(answer)]
    result['unknown'] = [p for p, r in ranks.items() if r is None]
    result['kos'] = [p for p, s in statuses.items() if s == STATUS_KO]
    answer_rank = ranks.get(answer)
    if answer_rank is None:
        result['verdict'] = 'undecided'
    elif answer_rank == 0:
        result['verdict'] = 'answer-fails'
    elif result['better']:
        result['verdict'] = 'better-move-exists'
    elif result['alternatives']:
        result['verdict'] = 'alternatives'
    else:
        result['verdict'] = 'confirmed'
    result['nodes'] = solver.nodes
    result['seconds'] = time.time() - start
    return result

if __name__ == '__main__':
    budget = int(sys.argv[1])
    paths = sys.argv[2:]
    engine = Engine()
    for path in paths:
        print(json.dumps(check(path, engine, budget), default=str), flush=True)
