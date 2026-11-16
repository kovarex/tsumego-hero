#Accuracy on the site's status-based problems: every leaf with an authored S[] is a test case
#usage: status_eval.py <budget> <problems.jsonl> <worker> <workers>
import json, sys, time, os
#ids already done by an earlier run, one per line
SKIP_IDS = set(open(os.environ['SKIP']).read().split()) if os.environ.get('SKIP') else set()
from check_tree import *
#also find who is ahead in each ko (KO+, KO-, KO+2, ...), and the full label of each KO leaf (design 7.3)
KO_SIGN = os.environ.get('KO_SIGN', '1') == '1'
KO_LABEL = os.environ.get('KO_LABEL', '1') == '1'

def authored_class(text, goal):
    #DEAD / KO / ALIVE of the target, comparable with the solver's three outcomes; the statuses the solver tells apart
    #(bent four, seki, double ko, superko) stay as they are
    if text.strip() in SPECIAL_STATUSES:
        return text.strip()
    r = status_rank(text, goal)
    if r is None:
        return None
    if r == 1:
        return 'KO'
    success = r == 2
    return 'DEAD' if success == (goal == 'KILL') else 'ALIVE'

def main(budget, path, worker, workers):
    engine = Engine()
    for number, line in enumerate(open(path)):
        if number % workers != worker:
            continue
        record = json.loads(line)
        sgf = record.get('sgf') or ''
        if record['set_connection_id'] in SKIP_IDS:
            continue
        if 'G[KILL]' not in sgf and 'G[LIVE]' not in sgf:
            continue
        start = time.time()
        Solver.deadline = start + 120
        result = {'set_connection_id': record['set_connection_id'], 'url': 'https://tsumego.com/' + record['set_connection_id'], 'title': record['title'], 'leaves': []}
        try:
            sgf_root = parse_sgf(sgf)
            root, solver_color, size = build(sgf_root)
            goal = sgf_root.get('G')
            label.status_mode = True
            tree_points = [n.move for n in all_nodes(root) if n.move]
            box = region_of(size, list(root.board.stones) + tree_points)
            color = opponent(solver_color) if goal == 'KILL' else solver_color
            attacker = solver_color if goal == 'KILL' else opponent(solver_color)
            groups = groups_of(root.board, color)
            stones = groups[0]
            problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
            solver = Solver(engine, problem, attacker, stones, budget)
            solver.solver_color = solver_color
            add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
            solver.wide.solver_color = solver_color
            result['goal'] = goal
            ko_leaves = [] #(leaf record, node, moves) labelled once every leaf has its status
            stack = [(root, [])]
            while stack:
                node, line_moves = stack.pop()
                for child in node.children:
                    stack.append((child, line_moves + [point_name(child.move, size)]))
                if node.status_text and not node.children:
                    expected = authored_class(node.status_text, goal)
                    record = solver.status_record(node.board, node.to_move)
                    raw = record['status']
                    status = ko_taken_by(node.board, opponent(node.to_move) if node.move is not None else None, solver_color, raw, goal)
                    leaf = {'line': ' '.join(line_moves), 'authored': node.status_text, 'expected': expected, 'engine': status}
                    #the statuses an undecided test of the class leaves open beside it: engine is then one of several
                    left = [s for s in record['open'] if s != status]
                    if left:
                        leaf['engine_open'] = left
                        leaf['engine_text'] = status_text(dict(record, status=status, open=left))
                    if status == STATUS_KO and KO_SIGN:
                        leaf['sign'] = solver.ko_sign(node.board, node.to_move)
                        if leaf['sign'] is None and raw != STATUS_KO:
                            leaf['sign'] = 'KO+' #the solver's ko capture ends the line of its clean success: it took first (D14)
                    if status == STATUS_KO and KO_LABEL:
                        ko_leaves.append((leaf, node, line_moves))
                    result['leaves'].append(leaf)
            #the labels after the statuses: a label past the time limit ends the labels, not the statuses
            for leaf, node, line_moves in ko_leaves:
                try:
                    record = solver.ko_label(node.board, node.to_move, node.move, start=' '.join(line_moves))
                except TimeoutError:
                    leaf['label'] = {'timed_out': True}
                    result['labels_timed_out'] = True
                    break
                leaf['label'] = label_summary(record, node.status_text, goal)
        except TimeoutError:
            result['timed_out'] = True
        except Exception as error:
            result['error'] = repr(error)
            engine = Engine()
        result['seconds'] = time.time() - start
        print(json.dumps(result), flush=True)

if __name__ == '__main__':
    main(int(sys.argv[1]), sys.argv[2], int(sys.argv[3]), int(sys.argv[4]))
