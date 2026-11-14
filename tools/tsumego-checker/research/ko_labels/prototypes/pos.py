#print the position of a problem after moves, with the search area, no solving
#usage: pos.py <id> "<moves>"
import json, os, sys
sys.path.insert(0, '/home/user/tsumego-hero/tools/tsumego-checker')
from check_tree import *
from tsumego import add_wide
from go_figure import LETTERS
pid, line = sys.argv[1], (sys.argv[2] if len(sys.argv) > 2 else '')
for l in open('/tmp/claude-0/dump2/status_problems.jsonl'):
    r = json.loads(l)
    if r['set_connection_id'] == pid:
        break
sgf_root = parse_sgf(r['sgf'])
root, solver_color, size = build(sgf_root)
goal = sgf_root.get('G')
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
attacker = solver_color if goal == 'KILL' else opponent(solver_color)
stones = groups_of(root.board, color)[0]
problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
name = lambda p: point_name(p, size)
def parse(t):
    return None if t == 'pass' else (LETTERS.index(t[0].upper()), size - int(t[1:]))
board, to_move = root.board.copy(), solver_color
for t in line.split():
    retake = t.endswith('!')
    if not board.play(to_move, parse(t.rstrip('!')), ignore_ko=retake):
        sys.exit('illegal ' + t)
    to_move = opponent(to_move)
print(pid, r['title'], '| goal', goal, '| solver', solver_color, '| target', ' '.join(sorted(name(p) for p in stones)), '| to move', to_move, '| ko', name(board.ko) if board.ko else '-')
print('desc:', (r['description'] or '').strip()[:200])
xs = [p[0] for p in list(board.stones) + list(problem.region)]; ys = [p[1] for p in list(board.stones) + list(problem.region)]
x1, x2, y1, y2 = max(0, min(xs) - 1), min(size - 1, max(xs) + 1), max(0, min(ys) - 1), min(size - 1, max(ys) + 1)
print('    ' + ' '.join(LETTERS[x] for x in range(x1, x2 + 1)))
for y in range(y1, y2 + 1):
    row = []
    for x in range(x1, x2 + 1):
        p = (x, y); s = board.stones.get(p)
        row.append('X' if s == 'B' else 'O' if s == 'W' else '*' if p == board.ko else '.' if p in problem.region else '-')
    print('%3d %s' % (size - y, ' '.join(row)))
if '--tree' in sys.argv:
    def leaves(n, moves):
        if not n.children:
            yield moves, n.status_text
        for c in n.children:
            yield from leaves(c, moves + [name(c.move)])
    for moves, st in leaves(root, []):
        print('  ', ' '.join(moves), ':', st)
