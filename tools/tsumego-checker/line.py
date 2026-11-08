#the solver's main line from a position of a problem: the winner's proof moves, the loser's longest resistance
import json, sys, os
from check_tree import *
from tsumego import add_wide
wanted, moves, goal, budget = sys.argv[1], sys.argv[2].split(), sys.argv[3], int(sys.argv[4])
for line in open(os.environ.get('PROBLEMS', 'problems.jsonl')):
    record = json.loads(line)
    if record['set_connection_id'] == wanted:
        break
root, solver_color, size = build(parse_sgf(record['sgf']))
letters = 'ABCDEFGHJKLMNOPQRST'
name = lambda p: 'pass' if p is None else letters[p[0]] + str(size - p[1])
parse = lambda text: None if text == 'pass' else (letters.index(text[0]), size - int(text[1:]))
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
stones = groups_of(root.board, color)[0]
problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
solver = Solver(Engine(), problem, solver_color if goal == 'KILL' else opponent(solver_color), stones, budget)
board, to_move = root.board.copy(), solver_color
for text in moves:
    assert board.play(to_move, parse(text)), text
    to_move = opponent(to_move)
played = []
for ply in range(14):
    status, best = solver.status(board, to_move)
    #the stored best move is only a hint: keep it only if it keeps the result, else search for one that does
    if best is not None:
        child = board.copy()
        if not child.play(to_move, best) or solver.status(child, opponent(to_move))[0] != status:
            best = None
    if best is None:
        #the losing side: its reply that keeps the result longest is not stored, take any move that keeps the status
        candidates = []
        for p in sorted(problem.region):
            child = board.copy()
            if p not in child.stones and child.play(to_move, p):
                candidates.append((solver.status(child, opponent(to_move))[0] == status, p))
        best = next((p for keeps, p in candidates if keeps), None)
    played.append('%s%s' % ('B' if to_move == 'B' else 'W', name(best)))
    if best is None or not board.play(to_move, best):
        break
    to_move = opponent(to_move)
    if status != solver.status(board, to_move)[0]:
        played.append('(status changed)')
        break
print(' '.join(moves), '->', status, ':', ' '.join(played))
for y in range(0, 7):
    print('%2d ' % (size - y) + ' '.join({'B': 'X', 'W': 'O'}.get(board.stones.get((x, y)), '.') for x in range(0, 7)))
