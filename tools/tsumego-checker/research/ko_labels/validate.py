#the validation run of design section 12: the full ko label (section 7) of every leaf whose S[] is a ko status, and the
#class step on the SEKI and SEKI+ leaves; one JSON line per leaf, and leaves already in the output are skipped, so a run
#can be continued
#usage: validate.py <budget> <problems.jsonl> <worker> <workers> <out.jsonl> [--limit=<seconds per leaf>] [--narrow]
#       [--keys=<file>]: only the leaves listed there, one '<problem id><tab><moves from the start>' per line
#       [--skip-open-class]: no label where the class itself is undecided ('?'); the leaf is recorded as such
import json, os, re, sys, time
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from check_tree import *
from tsumego import label_summary, status_text

KO_STATUS = re.compile(r'^(KO([+-]\d*)?|A[+-]\d+KO([+-]\d*)?|10kKO|ADKO|DDKO|DSKO|ASKO|BENT4)$')
CLASS_ONLY = {'SEKI', 'SEKI+'}

def leaves_of(root, size):
    #(node, moves from the start) for every leaf with an S[]
    out, stack = [], [(root, [])]
    while stack:
        node, moves = stack.pop()
        for child in node.children:
            stack.append((child, moves + [point_name(child.move, size)]))
        if node.status_text and not node.children:
            out.append((node, moves))
    return out

def main(budget, path, worker, workers, out_path, limit, narrow, keys=None, skip_open_class=False):
    done = set()
    if os.path.exists(out_path):
        for line in open(out_path):
            item = json.loads(line)
            done.add((item['id'], item['line']))
    out = open(out_path, 'a')
    engine = Engine()
    for number, line in enumerate(open(path)):
        if number % workers != worker:
            continue
        record = json.loads(line)
        sgf = record.get('sgf') or ''
        if 'G[KILL]' not in sgf and 'G[LIVE]' not in sgf:
            continue
        pid = record['set_connection_id']
        sgf_root = parse_sgf(sgf)
        root, solver_color, size = build(sgf_root)
        goal = sgf_root.get('G')
        label.status_mode = True
        todo = [(node, moves) for node, moves in leaves_of(root, size)
                if (KO_STATUS.match(node.status_text.strip()) or node.status_text.strip() in CLASS_ONLY)
                and (pid, ' '.join(moves)) not in done and (keys is None or (pid, ' '.join(moves)) in keys)]
        if not todo:
            continue
        tree_points = [n.move for n in all_nodes(root) if n.move]
        box = region_of(size, list(root.board.stones) + tree_points)
        color = opponent(solver_color) if goal == 'KILL' else solver_color
        attacker = solver_color if goal == 'KILL' else opponent(solver_color)
        stones = groups_of(root.board, color)[0]
        solver = Solver(engine, Problem(root.board, focused_region(root.board, box, stones, tree_points)), attacker, stones, budget)
        solver.solver_color = solver_color
        if not narrow:
            add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
            solver.wide.solver_color = solver_color
        for node, moves in todo:
            authored = node.status_text.strip()
            item = {'id': pid, 'line': ' '.join(moves), 'authored': authored, 'goal': goal, 'area': 'narrow' if narrow else 'both'}
            start = time.time()
            Solver.deadline = start + limit
            nodes = solver.nodes
            try:
                #the class with the statuses an undecided refinement test leaves open, never the class alone
                record = solver.status_record(node.board, node.to_move)
                item['class'] = record['status']
                item['class_open'] = record.get('open') or []
                item['status_text'] = status_text(record)
                if authored not in CLASS_ONLY and skip_open_class and record['status'] == '?':
                    item['label_skipped'] = 'class undecided'
                elif authored not in CLASS_ONLY:
                    label_record = solver.ko_label(node.board, node.to_move, node.move, start=' '.join(moves))
                    item['label'] = label_summary(label_record, authored, goal)
            except TimeoutError:
                item['timed_out'] = True
            except Exception as error:
                item['error'] = repr(error)
                engine = Engine()
                solver.engine = engine
                if solver.wide is not None:
                    solver.wide.engine = engine
            item['seconds'] = round(time.time() - start, 1)
            item['nodes'] = solver.nodes - nodes
            out.write(json.dumps(item) + '\n')
            out.flush()

if __name__ == '__main__':
    options = dict(a[2:].split('=', 1) if '=' in a else (a[2:], '1') for a in sys.argv[6:])
    keys = set(tuple(line.rstrip('\n').split('\t', 1)) for line in open(options['keys'])) if 'keys' in options else None
    main(int(sys.argv[1]), sys.argv[2], int(sys.argv[3]), int(sys.argv[4]), sys.argv[5], float(options.get('limit', 60)), 'narrow' in options, keys, 'skip-open-class' in options)
