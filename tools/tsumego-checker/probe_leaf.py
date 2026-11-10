#the solver's verdict on one leaf of a status problem, as status_eval computes it: probe_leaf.py <problems.jsonl> <id> "<line>"
import json, sys
from check_tree import *
from tsumego import add_wide
from go_figure import LETTERS
for l in open(sys.argv[1]):
    record = json.loads(l)
    if record['set_connection_id'] == sys.argv[2]:
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
solver = Solver(Engine(), problem, attacker, stones, 200000)
solver.solver_color = solver_color
add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
solver.wide.solver_color = solver_color
node = root
for text in sys.argv[3].split():
    p = (LETTERS.index(text[0]), size - int(text[1:]))
    node = next(c for c in node.children if c.move == p)
status, best = solver.status(node.board, node.to_move)
raw = status
status = ko_taken_by(node.board, opponent(node.to_move) if node.move is not None else None, solver_color, status)
sign = solver.ko_sign(node.board, node.to_move) if status == STATUS_KO else None
print(sys.argv[2], sys.argv[3], 'authored', node.status_text, 'solver', status, sign or '')
