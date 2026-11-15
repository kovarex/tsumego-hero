#Checks first-move-only collections (sanderland/tsumego JSON): does the known first move solve, which other first moves solve too
import json, sys, time, glob
from tsumego import *

def opponent(color):
    return 'W' if color == 'B' else 'B'

def solver_wins(status, goal):
    return status == (STATUS_DEAD if goal == 'KILL' else STATUS_ALIVE)

def rank(status, goal):
    #2 the solver succeeds, 1 ko, 0 fails, None unknown; a class given with the statuses its undecided tests leave open
    #(Solver.status) has a rank only when they all have it
    if status is None:
        return None
    status, left = status_parts(status)
    if left:
        ranks = {rank(other, goal) for other in [status] + left}
        return ranks.pop() if len(ranks) == 1 else None
    if status == STATUS_UNKNOWN:
        return None
    if status == STATUS_KO:
        return 1
    if status in (STATUS_BENT4, STATUS_DDKO, STATUS_DSKO):
        return 2 if goal == 'KILL' else 0 #almost dead
    if status in (STATUS_SEKI, STATUS_SEKI_SENTE, STATUS_ADKO, STATUS_ASKO):
        return 0 if goal == 'KILL' else 2 #the target lives
    return 2 if solver_wins(status, goal) else 0

def open_ranks(record, goal):
    #the ranks of a class record's status (Solver.status_record) and of every status its undecided tests leave open
    return {rank(status, goal) for status in [record['status']] + list(record['open'])}

def decided_rank(record, goal):
    #the rank when every status the record leaves open has it too, else None: a comparison an undecided test of the class
    #could change is undecided
    return rank(status_text(record), goal)

def ahead(first, second, goal):
    #the first class record ranks above the second whatever their undecided tests give
    pairs = [(f, s) for f in open_ranks(first, goal) for s in open_ranks(second, goal)]
    return all(f is not None and s is not None and f > s for f, s in pairs)

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
            first = solver.status_record(board, solver_color)
            second = solver.status_record(board, opponent(solver_color))
            unsettled = ahead(first, second, goal)
            found.append((unsettled, len(stones), goal, solver, status_text(first), status_text(second)))
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
        statuses[p] = solver.status_record(child, opponent(solver_color))
    ranks = {p: decided_rank(record, goal) for p, record in statuses.items()}
    known = [r for r in ranks.values() if r is not None]
    best = max(known) if known else None
    result['answer_status'] = status_text(statuses[answer]) if answer in statuses else None
    result['best_rank'] = best
    result['alternatives'] = [p for p, r in ranks.items() if r == best and p != answer and best and best > 0]
    result['better'] = [p for p, r in ranks.items() if best is not None and r == best and ranks.get(answer) is not None and r > ranks.get(answer)]
    result['unknown'] = [p for p, r in ranks.items() if r is None]
    result['kos'] = [p for p, record in statuses.items() if record['status'] == STATUS_KO]
    result['open'] = {'%d,%d' % p: status_text(record) for p, record in statuses.items() if record['open']}
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
