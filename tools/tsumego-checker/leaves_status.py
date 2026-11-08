#status of every leaf of a problem's tree: leaves_status.py <records.jsonl> <id> <KILL|LIVE> <budget>
import json, sys
from check_tree import *
path, wanted, goal, budget = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4])
for line in open(path):
    record = json.loads(line)
    if record['set_connection_id'] == wanted:
        break
root, solver_color, size = build(parse_sgf(record['sgf']))
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
attacker = solver_color if goal == 'KILL' else opponent(solver_color)
stones = groups_of(root.board, color)[0]
problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
solver = Solver(Engine(), problem, attacker, stones, budget)
solver.solver_color = solver_color
print('target', sorted(point_name(p, size) for p in stones), 'region', len(problem.region))
stack = [(root, [])]
while stack:
    node, moves = stack.pop()
    for child in node.children:
        stack.append((child, moves + [point_name(child.move, size)]))
    if not node.children:
        comment = (node.sgf.get('C') or '').strip()
        print('%-28s %-8s %s' % (' '.join(moves), 'correct' if comment.startswith('+') else 'wrong', solver.status(node.board, node.to_move)[0]))
