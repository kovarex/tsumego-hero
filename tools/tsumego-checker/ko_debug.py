#win table of a leaf with counted threats: ko_debug.py <id> "<line>" [problems.jsonl]
import json, sys, os
from check_tree import *
from tsumego import add_wide
wanted, moves = sys.argv[1], sys.argv[2].split()
path = sys.argv[3] if len(sys.argv) > 3 else os.environ.get('PROBLEMS', 'problems.jsonl')
for line in open(path):
    record = json.loads(line)
    if record['set_connection_id'] == wanted:
        break
sgf_root = parse_sgf(record['sgf'])
root, solver_color, size = build(sgf_root)
goal = sgf_root.get('G')
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
attacker = solver_color if goal == 'KILL' else opponent(solver_color)
stones = groups_of(root.board, color)[0]
problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
solver = Solver(Engine(), problem, attacker, stones, int(sys.argv[4]) if len(sys.argv) > 4 else 200000)
solver.solver_color = solver_color
letters = 'ABCDEFGHJKLMNOPQRST'
board, to_move = root.board.copy(), solver_color
for text in moves:
    assert board.play(to_move, (letters.index(text[0]), size - int(text[1:]))), text
    to_move = opponent(to_move)
print('solver', solver_color, 'goal', goal, 'to move', to_move, 'ko', board.ko, 'status', solver.status(board, to_move)[0])
print(problem.board_text(board).replace('#', ' ')[:0])
for y in range(size):
    row = ''.join({'B': 'X', 'W': 'O'}.get(board.stones.get((x, y)), '.') for x in range(size))
    if row.strip('.'):
        print('%2d %s' % (size - y, row[:9]))
print('solver threats x opponent threats -> solver wins')
for x in range(3):
    print(' '.join(str(solver.solver_wins(board, to_move, x, y))[0] for y in range(4)), ' x=%d' % x)
