#Checks a tsumego.com style SGF tree against the exact solver:
#correct moves that fail, wrong moves that work, refutations that don't refute, missing correct moves, leaf statuses
import json, sys, time, os
#ids already done by an earlier run, one per line
SKIP_IDS = set(open(os.environ['SKIP']).read().split()) if os.environ.get('SKIP') else set()
from tsumego import *
from first_moves import opponent, solver_wins, rank

#solver's rank of an authored status (S[]) for the goal: 2 success, 1 ko, 0 failure
STATUS_RANKS = {
    'KILL': {'DEAD': 2, 'BENT4': 2, 'DDKO': 2, 'DSKO': 2, '10kKO': 1, 'KO': 1, 'SEKI': 0, 'SEKI+': 0, 'ADKO': 0, 'ASKO': 0, 'ALIVE': 0},
    'LIVE': {'ALIVE': 2, 'ASKO': 2, 'ADKO': 2, 'SEKI': 2, 'SEKI+': 2, '10kKO': 1, 'KO': 1, 'DSKO': 0, 'DDKO': 0, 'BENT4': 0, 'DEAD': 0},
}

def status_rank(text, goal):
    text = text.strip()
    if '/' in text:
        text = text.split('/')[1] #black first part, as besogo compares it
    if text.startswith('A') and 'KO' in text:
        return 1
    if text.startswith('KO'):
        return 1
    return STATUS_RANKS[goal].get(text)

class TreeNode:
    def __init__(self, sgf_node, board, to_move, depth):
        self.sgf = sgf_node
        self.board = board
        self.to_move = to_move
        self.depth = depth
        self.children = []
        self.move = None
        self.label = None #2 correct, 0 wrong, from the tree
        self.status_text = sgf_node.get('S')
        self.parent_node = None

def build(sgf_root):
    size = int(sgf_root.get('SZ', '19').split(':')[0])
    board = Board(size)
    for key, color in (('AB', 'B'), ('AW', 'W')):
        for p in expand_points(sgf_root.properties.get(key, []), size):
            board.stones[p] = color
    for p in expand_points(sgf_root.properties.get('AE', []), size):
        board.stones.pop(p, None)
    first = None
    for child in sgf_root.children:
        if child.move():
            first = child.move()[0]
            break
    first = first or sgf_root.get('PL', 'B')
    root = TreeNode(sgf_root, board, first, 0)
    todo = [root]
    while todo:
        node = todo.pop(0)
        for child_sgf in node.sgf.children:
            move = child_sgf.move()
            child_board = node.board.copy()
            if move:
                color, value = move
                p = point(value, size)
                if not child_board.play(color, p):
                    continue
                to_move = opponent(color)
            else:
                to_move = node.to_move
            for key, color in (('AB', 'B'), ('AW', 'W')):
                for q in expand_points(child_sgf.properties.get(key, []), size):
                    child_board.stones[q] = color
            child = TreeNode(child_sgf, child_board, to_move, node.depth + 1)
            child.move = point(move[1], size) if move else None
            child.parent_node = node
            node.children.append(child)
            todo.append(child)
    link_transpositions(root)
    return root, first, size

def position_key(board, to_move):
    return (frozenset(board.stones.items()), board.ko, to_move)

def link_transpositions(root):
    #like besogo: a node repeating a position of another line is that line (pruneTree), and a move not in the tree
    #that leads to a position of the tree is followed there (virtual children)
    order, todo = [], [root]
    while todo:
        node = todo.pop()
        order.append(node)
        todo.extend(reversed(node.children))
    first = {}
    for node in order:
        node.key = position_key(node.board, node.to_move)
        node.alias = None
        ancestor = node.parent_node
        ancestors = set()
        while ancestor is not None:
            ancestors.add(id(ancestor))
            ancestor = ancestor.parent_node
        if node.key in first and id(first[node.key]) not in ancestors:
            node.alias = first[node.key]
        else:
            first.setdefault(node.key, node)
    relevant = set(root.board.stones)
    for node in order:
        if node.move:
            relevant.add(node.move)
    for node in order:
        node.virtual = []
        if node.alias:
            continue
        own = {child.move for child in node.children}
        for p in relevant:
            if p in own or p in node.board.stones:
                continue
            child_board = node.board.copy()
            if not child_board.play(node.to_move, p):
                continue
            target = first.get(position_key(child_board, opponent(node.to_move)))
            if target is not None and target is not node:
                node.virtual.append(target)
    return first

def label(node, solver_color, goal, visiting=None):
    #the tree's own verdict, like besogo: + comments (no goal) or statuses (goal) at the leaves
    visiting = visiting if visiting is not None else set()
    if node.alias is not None:
        node.label = label(node.alias, solver_color, goal, visiting)
        return node.label
    if id(node) in visiting:
        return None
    if node.children or node.virtual:
        visiting.add(id(node))
        values = [label(child, solver_color, goal, visiting) for child in node.children]
        values += [label(target, solver_color, goal, visiting) for target in node.virtual]
        visiting.discard(id(node))
        values = [v for v in values if v is not None]
        if not values:
            node.label = None
        elif node.to_move == solver_color:
            node.label = max(values)
        else:
            node.label = min(values)
        return node.label
    if label.status_mode:
        node.label = status_rank(node.status_text, goal) if node.status_text else None
    else:
        node.label = 2 if '\n'.join(node.sgf.properties.get('C', [])).strip().startswith('+') else 0
    return node.label

def all_nodes(node):
    yield node
    for child in node.children:
        yield from all_nodes(child)

def point_name(p, size):
    if p is None:
        return 'pass'
    letters = 'ABCDEFGHJKLMNOPQRST'
    return '%s%d' % (letters[p[0]], size - p[1])

def goal_hint(description):
    text = (description or '').lower()
    if 'to kill' in text or 'to capture' in text:
        return 'KILL'
    if 'to live' in text:
        return 'LIVE'
    return None

def check(text, engine, budget, scan_missing=True, description=None, missing_depth=3, verify=True):
    sgf_root = parse_sgf(text)
    root, solver_color, size = build(sgf_root)
    if size < 9:
        return {'verdict': 'unsupported-board', 'issues': [], 'notes': [], 'seconds': 0} #whole-board puzzles have other goals
    explicit_goal = sgf_root.get('G')
    label.status_mode = explicit_goal in ('KILL', 'LIVE')
    tree_points = [n.move for n in all_nodes(root) if n.move]
    box = region_of(size, list(root.board.stones) + tree_points)
    start = time.time()
    #layer A: candidate targets, unsettled at the root, most agreement with the tree's first moves
    hint = goal_hint(description)
    capture = 'capture' in (description or '').lower() #the goal is a chain, not a whole group
    goals = [explicit_goal] if label.status_mode else [hint] if hint else ['KILL', 'LIVE']
    best = None
    fallback = None #a formulation that agrees with every decided first move of the tree, without the unsettled proof
    for detect_budget in (budget // 10, budget):
        for goal in goals:
            color = opponent(solver_color) if goal == 'KILL' else solver_color
            label(root, solver_color, goal)
            #a capture goal is about a chain; to kill or live is about the largest group of that color
            candidates = chains_of(root.board, color, None)[:3] if capture else groups_of(root.board, color)[:1]
            for stones in candidates:
                attacker = solver_color if goal == 'KILL' else opponent(solver_color)
                problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
                solver = Solver(engine, problem, attacker, stones, detect_budget)
                first = rank(solver.status(root.board, solver_color)[0], goal)
                second = rank(solver.status(root.board, opponent(solver_color))[0], goal)
                agreement = decided = 0
                for child in root.children:
                    if child.label is not None:
                        r = rank(solver.status(child.board, child.to_move)[0], goal)
                        decided += r is not None
                        agreement += (r is not None and (r == 2) == (child.label == 2))
                if decided and agreement == decided and (fallback is None or (len(stones), agreement) > fallback[:2]):
                    fallback = (len(stones), agreement, goal, stones)
                if first is None or second is None or first <= second:
                    continue
                #the largest unsettled group is the problem's group; agreement with the tree only breaks ties
                candidate = (len(stones), agreement, goal, stones)
                if best is None or candidate[:2] > best[:2]:
                    best = candidate
        if best is not None:
            break
    report = {'solver': solver_color, 'issues': [], 'notes': [], 'status_mode': label.status_mode}
    if best is None and fallback is not None:
        best = fallback
        report['goal_assumed'] = True
    if best is None and len(goals) == 1:
        #the goal is stated (G[] or the description) but the solver couldn't prove the root unsettled: take the largest group
        color = opponent(solver_color) if goals[0] == 'KILL' else solver_color
        groups = groups_of(root.board, color)
        if groups:
            best = (len(groups[0]), 0, goals[0], groups[0])
            report['goal_assumed'] = True
    if best is None:
        report['verdict'] = 'goal-unclear'
        report['seconds'] = time.time() - start
        return report
    _, _, goal, stones = best
    label(root, solver_color, goal)
    attacker = solver_color if goal == 'KILL' else opponent(solver_color)
    problem = Problem(root.board, focused_region(root.board, box, stones, tree_points))
    solver = Solver(engine, problem, attacker, stones, budget)
    solver.solver_color = solver_color
    add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
    solver.wide.solver_color = solver_color
    report['goal'] = goal
    report['target'] = sorted(point_name(p, size) for p in stones)
    report['region'] = len(problem.region)

    positions = [] #(issue, node) to verify once the tree is done

    def add_issue(issue, node):
        report['issues'].append(issue)
        positions.append((issue, node))

    def is_detour(board, correct):
        #the move only makes an exchange before the tree's own answer: after every opponent answer that still leaves the
        #solver winning, one of the tree's correct moves from the same node wins too
        if not correct:
            return False
        for p in sorted(problem.region) + [None]:
            child = board.copy()
            if (p is not None and p in child.stones) or not child.play(opponent(solver_color), p):
                continue
            if rank(solver.status(child, solver_color)[0], goal) != 2:
                continue
            wins = False
            for c in correct:
                grand = child.copy()
                if c not in grand.stones and grand.play(solver_color, c) and rank(solver.status(grand, opponent(solver_color))[0], goal) == 2:
                    wins = True
                    break
            if not wins:
                return False
        return True

    def engine_rank(node):
        status, best_move = solver.status(node.board, node.to_move)
        return rank(status, goal), status, best_move

    def path(node_chain):
        return ' '.join(point_name(n.move, size) for n in node_chain if n is not root)

    tree_positions = {problem.board_text(n.board) + n.to_move for n in all_nodes(root)}
    try:
        stack = [(root, [root])]
        while stack:
            node, chain = stack.pop()
            if node.alias is not None:
                continue #the line goes on in the other branch, which is checked there
            for child in node.children:
                stack.append((child, chain + [child]))
            if not node.children and not node.virtual:
                #leaf status: the filler/fixer for status problems
                if label.status_mode:
                    r, status, _ = engine_rank(node)
                    last_mover = opponent(node.to_move) if node.move is not None else None
                    status = ko_taken_by(node.board, last_mover, solver_color, status)
                    r = rank(status, goal)
                    if node.status_text is None:
                        add_issue({'kind': 'missing-status', 'line': path(chain), 'engine': status}, node)
                        if status in (STATUS_DEAD, STATUS_ALIVE, STATUS_KO):
                            node.sgf.properties['S'] = [status]
                            report['fixed'] = True
                    elif (status == STATUS_BENT4) != (node.status_text.strip() == 'BENT4') and r is not None \
                            and r == status_rank(node.status_text, goal) and status != STATUS_UNKNOWN:
                        #bent four against plain dead (or the reverse): almost the same, but not quite
                        add_issue({'kind': 'status-almost', 'line': path(chain), 'authored': node.status_text, 'engine': status}, node)
                    elif r is not None and status_rank(node.status_text, goal) is not None and r != status_rank(node.status_text, goal) \
                        and not (node.status_text.strip() == 'BENT4' and status == STATUS_KO): #bent four is a ko the attacker wins by removing threats first
                        add_issue({'kind': 'status-mismatch', 'line': path(chain), 'authored': node.status_text, 'engine': status}, node)
                continue
            if not node.children:
                continue
            if node.to_move == solver_color:
                for child in node.children:
                    if child.label is None:
                        continue
                    r, status, refutation = engine_rank(child)
                    if r is None:
                        report['notes'].append({'kind': 'undecided', 'line': path(chain + [child])})
                    elif child.label == 2 and r == 0:
                        add_issue({'kind': 'correct-move-fails', 'line': path(chain + [child]), 'engine': status, 'refutation': point_name(refutation, size)}, child)
                    elif child.label == 0 and r == 2:
                        add_issue({'kind': 'wrong-move-works', 'line': path(chain + [child]), 'engine': status}, child)
                    elif r != child.label:
                        report['notes'].append({'kind': 'ko-difference', 'line': path(chain + [child]), 'tree': child.label, 'engine': status})
                #missing correct moves along the correct lines
                on_correct_line = all(n.label == 2 for n in chain)
                if scan_missing and on_correct_line and node.depth <= missing_depth and node.alias is None:
                    in_tree = {child.move for child in node.children}
                    for p in sorted(problem.region):
                        if p in node.board.stones or p in in_tree:
                            continue
                        child_board = node.board.copy()
                        if not child_board.play(solver_color, p):
                            continue
                        if problem.board_text(child_board) + opponent(solver_color) in tree_positions:
                            continue #a transposition into the tree, besogo follows it
                        fake = TreeNode(node.sgf, child_board, opponent(solver_color), node.depth + 1)
                        r, status, _ = engine_rank(fake)
                        if r == 2 and ko_taken_by(child_board, solver_color, solver_color, status) == STATUS_KO:
                            #the move captures a ko: the opponent retakes after a threat, so it is no clean answer (rule 2)
                            report['notes'].append({'kind': 'ko-capture-alternative', 'line': (path(chain) + ' ' + point_name(p, size)).strip()})
                            continue
                        if r == 2:
                            issue = {'kind': 'missing-correct-move', 'line': (path(chain) + ' ' + point_name(p, size)).strip(), 'engine': status}
                            correct = [child.move for child in node.children if child.label == 2]
                            if is_detour(child_board, correct):
                                report['notes'].append(dict(issue, kind='detour'))
                            else:
                                add_issue(issue, fake)
            else:
                #opponent's replies: in a wrong line each should refute, in a correct line none should
                for child in node.children:
                    if child.label is None:
                        continue
                    r, status, answer = engine_rank(child)
                    if node.label == 0 and r == 2:
                        add_issue({'kind': 'refutation-does-not-refute', 'line': path(chain + [child]), 'engine': status, 'solver-continues': point_name(answer, size)}, child)
    except TimeoutError:
        report['timed_out'] = True
    #every finding is solved again over a wider region with a bigger budget, and kept only if that agrees
    if positions and verify:
        verifier = Solver(wide_engine(), solver.wide.problem, attacker, stones, budget * 5)
        verifier.solver_color = solver_color
        Solver.deadline = time.time() + VERIFY_SECONDS
        for issue, node in positions:
            try:
                wide = verifier.status(node.board, node.to_move)[0]
            except TimeoutError:
                wide = 'timeout'
            if wide != issue['engine']:
                report['issues'].remove(issue)
                report['notes'].append(dict(issue, kind=issue['kind'] + '-not-confirmed', wide=wide))
            else:
                issue['confirmed_wide'] = True
    #findings mean something only when the formulation is proven: the opponent moving first refutes (the problem is unsettled)
    opponent_first = rank(solver.status(root.board, opponent(solver_color))[0], goal) if not report.get('timed_out') else None
    report['unsettled'] = opponent_first is not None and opponent_first < 2
    #statuses the solver doesn't tell apart (seki in sente or gote, super ko, double ko, bent four) can make a move
    #worse than the authored one although the solver sees the same outcome
    fine = {'SEKI', 'SEKI+', 'ASKO', 'ADKO', 'DSKO', 'DDKO', 'BENT4'}
    if label.status_mode and any((n.status_text or '').strip() in fine for n in all_nodes(root)):
        for issue in [i for i in report['issues'] if i['kind'] in ('wrong-move-works', 'missing-correct-move')]:
            report['issues'].remove(issue)
            report['notes'].append(dict(issue, kind=issue['kind'] + '-fine-status'))
    if not report['unsettled']:
        for issue in list(report['issues']):
            report['issues'].remove(issue)
            report['notes'].append(dict(issue, kind=issue['kind'] + '-unconfirmed'))
    report['verdict'] = 'issues' if report['issues'] else 'clean'

    if report.get('fixed'):
        report['fixed_sgf'] = serialize(sgf_root)
    report['nodes'] = solver.nodes
    report['seconds'] = time.time() - start
    return report

MAX_SECONDS = 30
VERIFY_SECONDS = 120

def site_main(budget, records_path, start, step, limit):
    #records from the site crawl: one problem per line, every step-th from start
    engine = Engine()
    seen = set()
    for number, line in enumerate(open(records_path)):
        if number % step != start or number >= limit:
            continue
        record = json.loads(line)
        if record['set_connection_id'] in seen:
            continue
        seen.add(record['set_connection_id'])
        if record['set_connection_id'] in SKIP_IDS:
            continue
        if not record.get('sgf'):
            continue
        Solver.deadline = time.time() + MAX_SECONDS
        try:
            report = check(record['sgf'], engine, budget, description=record['description'], missing_depth=2)
        except TimeoutError:
            report = {'verdict': 'timeout', 'issues': [], 'notes': [], 'seconds': MAX_SECONDS}
        except Exception as error:
            report = {'verdict': 'error', 'error': repr(error), 'issues': [], 'notes': [], 'seconds': 0}
            engine = Engine()
        report.update({'set_connection_id': record['set_connection_id'], 'title': record['title'], 'description': record['description'],
                       'url': 'https://tsumego.com/' + record['set_connection_id']})
        print(json.dumps(report), flush=True)

if __name__ == '__main__' and sys.argv[1] == 'site':
    site_main(int(sys.argv[2]), sys.argv[3], int(sys.argv[4]), int(sys.argv[5]), int(sys.argv[6]))
elif __name__ == '__main__':
    budget = int(sys.argv[1])
    engine = Engine()
    for path in sys.argv[2:]:
        report = check(open(path, encoding='utf-8', errors='replace').read(), engine, budget)
        report['file'] = path
        print(json.dumps(report), flush=True)
