#status after every move of the side to move, from a line of a problem
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
print(' '.join(moves), to_move, 'to move:', solver.status(board, to_move)[0])
for p in sorted(problem.region | problem.fringe) + [None]:
    child = board.copy()
    if p in child.stones or not child.play(to_move, p):
        continue
    status = solver.status(child, opponent(to_move))[0]
    if status != 'DEAD':
        print('  ', name(p), status)
