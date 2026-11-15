#the ko-label questions page: each section asks the owner one question, with its options, our recommendation and
#positions drawn as numbered diagrams; the solver checks under each diagram run when the page is rendered, and a
#failed or undecided check, an illegal move or a text-rule violation stops the render
#usage: PROBLEMS=status_problems.jsonl ENGINE=engine questions.py <page.json> <out.html> [--only <section id>]
#       (ENGINE: the checker's engine, built from engine.cpp)
#       (a section file in place of page.json renders that section alone)
#page: {title, intro: [paragraph], sections: ["<section id>.json", ...]}, the section files next to page.json
#section: {id, heading, question, options: [{label, meaning}], recommendation, text: [paragraph], positions: [P],
#          table: {caption, columns, rows}}
#P: {problem, leaf, tree, heading, area: "narrow" | "wide" | {frame: [points]}, target: [points], text, diagrams: [D]}
#   leaf: moves from the problem start, never shown; tree: the tree's S[] there, null when it is not a leaf of the tree;
#   area: where the solver searches (narrow: the checker's area, wide: two rings more, frame: points or ranges A16:D19)
#D: {start (default: the leaf), moves, first, caption, labels: {point: letter}, text, checks: [C]}, drawn as report.py
#   draws: 'pass' is a move elsewhere, 'A18!' a retake after a ko threat elsewhere
#C: {count: [s, o], opts, expect: wins | fails} | {balance: d, opts} | {status: DEAD | KO | ALIVE | BENT4 | SEKI | SEKI+ |
#   DDKO | ADKO | DSKO | ASKO}, run at the position after the diagram's moves with the side to move there; status: the
#   class of Solver.status (SEKI+: a seki in which the solver keeps sente, design D11; DDKO and ADKO: dead or alive in a
#   double ko, DSKO and ASKO: by superko, design D10); s and o are the outside ko threats of the problem's
#   first player (the solver) and of its opponent; balance d: the solver wins at s - o = d and fails at d - 1, each at
#   the smallest pair; opts: solvex options with point names (pw=1,end=1,fw=A18;B19; sk=1: a seki counts as dead)
#diagrams are numbered through the section; text names them "Diagram 2" and their stones "Black 7" (in a position,
#stones of its own diagrams), and never lists moves
import html, json, os, re, sys, time
USAGE = 'usage: PROBLEMS=<status problems .jsonl> ENGINE=<engine with solvex> questions.py <page.json> <out.html> [--only <section id>]'
if not os.environ.get('PROBLEMS') or not os.environ.get('ENGINE'):
    sys.exit(USAGE)
if not os.path.isfile(os.environ['ENGINE']) or b'solvex' not in open(os.environ['ENGINE'], 'rb').read():
    sys.exit('ENGINE=%s has no solvex command; use the checker\'s engine, built from engine.cpp' % os.environ['ENGINE'])
RESEARCH = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(RESEARCH))
sys.path.insert(0, RESEARCH)
import report
import tsumego
from tsumego import Engine, Solver, add_wide, focused_region, region_of, groups_of, STATUS_UNKNOWN
from tsumego import Problem as SearchProblem
from check_tree import all_nodes, opponent

names = report.names
#a search undecided at the first budget gets one retry at the second, then it is undecided
BUDGETS = (400000, 2000000)
WORDS = ['no', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
         'thirteen', 'fourteen', 'fifteen']
#every class Solver.status returns, besides '?' (undecided)
STATUSES = ('DEAD', 'KO', 'ALIVE', 'BENT4', 'SEKI', 'SEKI+', 'DDKO', 'ADKO', 'DSKO', 'ASKO')
CYCLES = {0: 'a repeated position kills if a ko was retaken against the ban, and is seki otherwise',
          1: 'any repeated position counts as a kill', 2: 'any repeated position counts as life',
          3: 'the side that repeats a position loses'}
PAGE_KEYS = {'title', 'intro', 'sections'}
SECTION_KEYS = {'id', 'heading', 'question', 'options', 'recommendation', 'text', 'positions', 'table'}
POSITION_KEYS = {'problem', 'leaf', 'tree', 'heading', 'area', 'target', 'text', 'diagrams'}
DIAGRAM_KEYS = {'start', 'moves', 'first', 'caption', 'labels', 'text', 'checks'}
CHECK_KEYS = {'count': {'count', 'opts', 'expect'}, 'balance': {'balance', 'opts'}, 'status': {'status'}}
#a point name followed by '!' is the spec's retake notation, and reads as "a good move" to a go player
NOTATION = re.compile(report.COORD + '!')
#research leaves by (problem, line), for the leaf's case name and its fix on the site
CASES = {(c['id'], ' '.join(c['line'].split())): c['case'] for c in report.results}
problems, cache = {}, {}
shared_engine = None

class CheckError(Exception):
    pass

#S[] labels and besogo ko labels stay on one line (A-1KO+ would otherwise break at its hyphen)
LABEL = re.compile(r'S\[[^\]\s]*\]|(?<![\w\[])(?:A[+-]\d+)?KO[+-]\d*(?![\w\]])|\b10kKO\b')

def esc(text):
    return html.escape(str(text))

def words_html(text):
    return LABEL.sub(lambda m: '<span class="nobreak">%s</span>' % m.group(0), esc(text))

#"Problem 10391" in a table cell links to the problem on the site, as the position headers do
PROBLEM = re.compile(r'\bProblem (\d+)\b')

def problem_links(text):
    return PROBLEM.sub(lambda m: '<a href="https://tsumego.com/%s">%s</a>' % (m.group(1), m.group(0)), text)

def problem_of(pid):
    if pid not in problems:
        problems[pid] = report.Problem(pid)
    return problems[pid]

def engine():
    #one engine process for every position; the wider areas share tsumego's second one
    global shared_engine
    if shared_engine is None:
        shared_engine = Engine()
    return shared_engine

def read_json(path, errors):
    try:
        return json.load(open(path))
    except FileNotFoundError:
        errors.append('%s: no such file' % path)
    except ValueError as error:
        errors.append('%s: not valid JSON (%s)' % (path, error))
    return None

#text rules
def sentence_count(text):
    return max(1, len(re.findall(r'[.!?]["”)]?(?=\s|$)', text.strip())))

def text_errors(text, where, stones, diagrams, words=130, sentences=None):
    #move lists, references to missing diagrams or to stones no diagram in scope has, and the length rules
    if not isinstance(text, str) or not text.strip():
        return ['%s: empty or not text' % where]
    errors = []
    plain = re.sub('<[^>]+>', '', text)
    for match in report.SEQUENCE.finditer(plain):
        errors.append('%s: a move sequence in the text ("%s"); draw it in a diagram' % (where, match.group(0)))
    for match in NOTATION.finditer(plain):
        errors.append('%s: "%s" is the spec\'s retake notation; name the point, or draw the retake' % (where, match.group(0)))
    for match in report.STONE.finditer(plain):
        #stones None: a diagram in scope did not replay, its error is reported and the stones are checked once it does
        if stones is not None and (match.group(1), int(match.group(2))) not in stones:
            errors.append('%s: "%s" names no numbered %s stone in the diagrams it can refer to' % (where, match.group(0), match.group(1)))
    for match in report.DIAGRAM.finditer(plain):
        for number in re.findall(r'\d+', match.group(0)):
            if not 1 <= int(number) <= diagrams:
                errors.append('%s: "%s" but the section has %d diagram(s)' % (where, match.group(0), diagrams))
    if words and len(plain.split()) > words:
        errors.append('%s: %d words; keep it under %d' % (where, len(plain.split()), words))
    if sentences and sentence_count(plain) > sentences:
        errors.append('%s: %d sentences; a caption has one or two' % (where, sentence_count(plain)))
    return errors

#positions and their solvers
def frame_points(problem, entries):
    points = set()
    for entry in entries:
        if not isinstance(entry, str):
            raise ValueError('frame: %r is not a point name' % (entry,))
        if ':' in entry:
            low, high = (problem.point(t) for t in entry.split(':'))
            points |= {(x, y) for x in range(min(low[0], high[0]), max(low[0], high[0]) + 1)
                       for y in range(min(low[1], high[1]), max(low[1], high[1]) + 1)}
        else:
            points.add(problem.point(entry))
    return points

def frame_name(problem, points):
    #the frame by its corners, and how many points it has when it is not the whole rectangle
    xs, ys = [p[0] for p in points], [p[1] for p in points]
    corners = '%s-%s' % (problem.name((min(xs), max(ys))), problem.name((max(xs), min(ys))))
    whole = (max(xs) - min(xs) + 1) * (max(ys) - min(ys) + 1) == len(points)
    return 'search frame ' + corners, '' if whole else ' (%d points)' % len(points)

def target_words(problem, board, points):
    #the target's stones grouped into chains, each named by its top stone
    parts, seen = [], set()
    for p in sorted(points, key=lambda q: (q[1], q[0])):
        if p in seen:
            continue
        chain, todo = {p}, [p]
        while todo:
            q = todo.pop()
            for r in board.neighbours(q):
                if r in points and r not in chain:
                    chain.add(r)
                    todo.append(r)
        seen |= chain
        colour = names[board.stones[p]]
        parts.append('the %s stone at %s' % (colour, problem.name(p)) if len(chain) == 1 else
                     'the %s chain at %s (%d stones)' % (colour, problem.name(p), len(chain)))
    return ' and '.join(parts)

def make_solver(problem, area, target_names, leaf_board):
    #the checker's formulation of the problem: the defender's largest group as target, searched in the area
    if problem.goal not in ('KILL', 'LIVE'):
        raise ValueError('problem %s has no G[KILL] or G[LIVE]' % problem.id)
    root, solver_colour = problem.root, problem.first
    defender = opponent(solver_colour) if problem.goal == 'KILL' else solver_colour
    attacker = opponent(defender)
    tree_points = [n.move for n in all_nodes(root) if n.move]
    box = region_of(problem.size, list(root.board.stones) + tree_points)
    stones = groups_of(root.board, defender)[0]
    target = stones
    if target_names is not None:
        if not isinstance(target_names, list) or not target_names or not all(isinstance(t, str) for t in target_names):
            raise ValueError('target: a list of point names')
        target = [problem.point(t) for t in target_names]
        for name, p in zip(target_names, target):
            if leaf_board.stones.get(p) != defender:
                raise ValueError('target %s is not a %s stone at the leaf' % (name, names[defender]))
    Solver.deadline = None
    if area in ('narrow', 'wide'):
        solver = Solver(engine(), SearchProblem(root.board, focused_region(root.board, box, stones, tree_points)), attacker, target, BUDGETS[0])
        solver.solver_color = solver_colour
        add_wide(solver, root.board, list(root.board.stones) + tree_points, target, tree_points)
        solver.wide.solver_color = solver_colour
        if area == 'wide':
            solver = solver.wide
    else:
        #an explicit region without fringe, as harness.region_pos builds it
        saved = tsumego.DYNAMIC
        tsumego.DYNAMIC = False
        try:
            search = SearchProblem(leaf_board, area)
        finally:
            tsumego.DYNAMIC = saved
        solver = Solver(engine(), search, attacker, target, BUDGETS[0])
        solver.solver_color = solver_colour
    outside = [problem.name(p) for p in target if p not in solver.problem.region]
    if outside:
        raise ValueError('target stone(s) %s outside the search area' % ', '.join(outside))
    return solver

def tree_label(problem, leaf):
    #the S[] of the tree's node where the leaf's moves end (export), None when the moves leave the tree
    node, colour = problem.root, problem.first
    for token in leaf.split():
        if token.endswith('!'):
            return None
        #a pass follows the tree's pass node of that colour, as in 15513
        point = None if token == 'pass' else problem.point(token)
        for child in node.children:
            move = child.sgf.move()
            if move and move[0] == colour and child.move == point:
                node = child
                break
        else:
            return None
        colour = opponent(colour)
    return node.status_text

def misplaced_retake(problem, moves):
    #the first move marked '!' that does not retake the ko just taken, or None
    board, colour = problem.root.board.copy(), problem.first
    for token in moves.split():
        p = None if token == 'pass' else problem.point(token)
        if token.endswith('!') and (p is None or p != board.ko):
            return token
        board.play(colour, p, ignore_ko=token.endswith('!'))
        colour = opponent(colour)
    return None

def prepare_position(section_id, index, spec, offset, errors):
    heading = spec.get('heading') if isinstance(spec, dict) else None
    where = '%s, position %d%s' % (section_id, index, ' ("%s")' % heading if heading else '')
    #stones stay None (unknown) until the leaf replays
    position = {'spec': spec, 'where': where, 'problem': None, 'solver': None, 'diagrams': [], 'stones': None,
                'frame': None, 'area_label': '', 'setup': [], 'tree': None, 'case': None}
    if not isinstance(spec, dict):
        errors.append('%s: not an object' % where)
        return position
    for key in sorted(set(spec) - POSITION_KEYS):
        errors.append('%s: unknown key %r' % (where, key))
    for key in ('problem', 'leaf', 'tree', 'heading', 'diagrams'):
        if key not in spec:
            errors.append('%s: no %s' % (where, key))
    if not isinstance(spec.get('diagrams'), list) or not spec.get('diagrams'):
        errors.append('%s: diagrams must be a non-empty list' % where)
        return position
    pid = str(spec.get('problem'))
    if pid not in report.records:
        errors.append('%s: no problem %s in the export' % (where, pid))
        return position
    problem = position['problem'] = problem_of(pid)
    leaf = spec.get('leaf', '')
    if not isinstance(leaf, str):
        errors.append('%s: leaf must be a string of moves' % where)
        return position
    try:
        leaf_board, _, _ = problem.replay(leaf, '')
        retake = misplaced_retake(problem, leaf)
        if retake:
            raise ValueError('%s is marked as a retake after a ko threat, but it retakes no ko' % retake)
    except ValueError as error:
        errors.append('%s, leaf: %s' % (where, error))
        return position
    position['stones'] = set()
    #the tree's S[] now: the export's, or the owner's fix on the site since
    export = tree_label(problem, leaf)
    case = position['case'] = CASES.get((pid, ' '.join(leaf.split())))
    current = report.fixed[case]['status'] if case in report.fixed else export
    position['tree'] = (current, export if current != export else None, report.fixed.get(case, {}).get('date'))
    if 'tree' in spec and spec['tree'] != current:
        errors.append('%s: tree says %s, but the tree has %s at this leaf' % (
            where, 'S[%s]' % spec['tree'] if spec['tree'] else 'null', 'S[%s]' % current if current else 'no S[] (not a leaf of the tree)'))
    area = spec.get('area', 'narrow')
    try:
        if area in ('narrow', 'wide'):
            region = area
            position['area_label'] = '' if area == 'narrow' else ' (wider area)'
            if area == 'wide':
                position['setup'].append('The solver searches the wider area: the checker\'s area and two more rings of empty points.')
        elif isinstance(area, dict) and set(area) == {'frame'} and isinstance(area['frame'], list) and area['frame']:
            region = position['frame'] = frame_points(problem, area['frame'])
            name, size = frame_name(problem, region)
            position['area_label'] = ' (%s)' % name
            position['setup'].append('The solver searches only the %s%s, outlined on the diagrams; outside it the board counts as %s\'s wall.' % (
                name, size, names[opponent(problem.first) if problem.goal == 'LIVE' else problem.first]))
        else:
            raise ValueError('area must be "narrow", "wide" or {"frame": [points]}')
        position['solver'] = make_solver(problem, region, spec.get('target'), leaf_board)
        if spec.get('target') is not None:
            position['setup'].append('Target: %s.' % target_words(problem, leaf_board, set(position['solver'].target)))
    except ValueError as error:
        errors.append('%s: %s' % (where, error))
    for number, diagram_spec in enumerate(spec['diagrams'], offset + 1):
        diagram = prepare_diagram(position, diagram_spec, leaf, number, errors)
        position['diagrams'].append(diagram)
    return position

def prepare_diagram(position, spec, leaf, number, errors):
    where = '%s, Diagram %d' % (position['where'], number)
    diagram = {'spec': spec, 'number': number, 'where': where, 'ok': False, 'lines': []}
    if not isinstance(spec, dict):
        errors.append('%s: not an object' % where)
        return diagram
    for key in sorted(set(spec) - DIAGRAM_KEYS):
        errors.append('%s: unknown key %r' % (where, key))
    if not spec.get('caption'):
        errors.append('%s: no caption' % where)
    drawn = dict(spec)
    drawn.setdefault('start', leaf)
    drawn.setdefault('moves', '')
    drawn['caption'] = spec.get('caption') or ''
    if not isinstance(drawn['start'], str) or not isinstance(drawn['moves'], str):
        errors.append('%s: start and moves must be strings of moves' % where)
        return diagram
    if 'first' in spec and (not isinstance(spec['first'], int) or spec['first'] < 1):
        errors.append('%s: first must be a move number' % where)
        return diagram
    problem = position['problem']
    try:
        board, to_move, numbered = problem.replay(drawn['start'], drawn['moves'], spec.get('first'))
        if not isinstance(spec.get('labels', {}), dict):
            raise ValueError('labels must map points to letters')
        for token, letter in spec.get('labels', {}).items():
            problem.point(token)
            if not isinstance(letter, str) or not 1 <= len(letter) <= 2:
                raise ValueError('label %r at %s: one or two characters' % (letter, token))
        retake = misplaced_retake(problem, drawn['start'] + ' ' + drawn['moves'])
        if retake:
            raise ValueError('%s is marked as a retake after a ko threat, but it retakes no ko' % retake)
    except ValueError as error:
        errors.append('%s: %s' % (where, error))
        return diagram
    current = board.copy()
    for _, colour, p, retake in numbered:
        current.play(colour, p, ignore_ko=retake)
        to_move = opponent(colour)
    diagram.update(drawn=drawn, ok=True, board=current, to_move=to_move)
    position['stones'] |= {(names[colour], n) for n, colour, _, _ in numbered}
    return diagram

def prepare_section(spec, errors):
    section_id = spec.get('id') if isinstance(spec.get('id'), str) else '?'
    section = {'spec': spec, 'positions': [], 'count': 0}
    for key in sorted(set(spec) - SECTION_KEYS):
        errors.append('%s: unknown key %r' % (section_id, key))
    if not re.fullmatch(r'[a-z][a-z0-9-]*', section_id):
        errors.append('%s: the id must be lowercase letters, digits and hyphens' % section_id)
    for key in ('heading', 'question', 'recommendation'):
        if not isinstance(spec.get(key), str) or not spec.get(key).strip():
            errors.append('%s: no %s' % (section_id, key))
    options = spec.get('options')
    if not isinstance(options, list) or not options or not all(isinstance(o, dict) and set(o) == {'label', 'meaning'} for o in options):
        errors.append('%s: options must be a list of {label, meaning}' % section_id)
        options = []
    count = 0
    for index, position_spec in enumerate(spec.get('positions', []), 1):
        position = prepare_position(section_id, index, position_spec, count, errors)
        section['positions'].append(position)
        #numbered by the spec, so a position that fails early does not shift the numbers after it
        listed = position_spec.get('diagrams') if isinstance(position_spec, dict) else None
        count += len(listed) if isinstance(listed, list) else 0
    section['count'] = count
    #the text rules: section text may name any diagram and stone of the section, a position's text its own stones
    for position in section['positions']:
        if position['stones'] is not None and not all(d['ok'] for d in position['diagrams']):
            position['stones'] = None
    known = [p['stones'] for p in section['positions']]
    every = None if None in known else set().union(*known)
    for key in ('heading', 'question', 'recommendation'):
        if isinstance(spec.get(key), str) and spec.get(key).strip():
            errors.extend(text_errors(spec[key], '%s %s' % (section_id, key), every, count))
    for i, option in enumerate(options, 1):
        errors.extend(text_errors(option['label'], '%s option %d label' % (section_id, i), every, count))
        errors.extend(text_errors(option['meaning'], '%s option %d meaning' % (section_id, i), every, count))
    for i, paragraph in enumerate(spec.get('text', []), 1):
        errors.extend(text_errors(paragraph, '%s paragraph %d' % (section_id, i), every, count))
    for position in section['positions']:
        position_spec, stones = position['spec'], position['stones']
        if not isinstance(position_spec, dict):
            continue
        if position_spec.get('heading'):
            errors.extend(text_errors(position_spec['heading'], position['where'] + ' heading', stones, count))
        for i, paragraph in enumerate(position_spec.get('text', []), 1):
            errors.extend(text_errors(paragraph, '%s paragraph %d' % (position['where'], i), stones, count))
        for diagram in position['diagrams']:
            diagram_spec = diagram['spec']
            if not isinstance(diagram_spec, dict):
                continue
            if diagram_spec.get('caption'):
                errors.extend(text_errors(diagram_spec['caption'], diagram['where'] + ' caption', stones, count, sentences=2))
            for i, paragraph in enumerate(diagram_spec.get('text', []), 1):
                errors.extend(text_errors(paragraph, '%s paragraph %d' % (diagram['where'], i), stones, count))
    table = spec.get('table')
    if table is not None:
        if not isinstance(table, dict) or set(table) - {'caption', 'columns', 'rows'} or not table.get('columns') or not isinstance(table.get('rows'), list):
            errors.append('%s table: {caption, columns, rows}' % section_id)
        else:
            for cell in [table.get('caption', 'table')] + table['columns']:
                errors.extend(text_errors(cell, '%s table' % section_id, every, count, words=0))
            for r, row in enumerate(table['rows'], 1):
                if not isinstance(row, list) or len(row) != len(table['columns']):
                    errors.append('%s table row %d: %d cells for %d columns' % (section_id, r, len(row) if isinstance(row, list) else 0, len(table['columns'])))
                    continue
                for cell in row:
                    errors.extend(text_errors(str(cell), '%s table row %d' % (section_id, r), every, count, words=0))
    return section

#the checks
def parse_options(text, problem, solver):
    #solvex options with point names -> (the engine's text, the options in words)
    if text in ('', '-'):
        return '-', []
    if not isinstance(text, str):
        raise CheckError('opts must be a string like "pw=1,end=1"')
    items, words, seen = [], [], set()
    for item in text.split(','):
        key, equals, value = item.strip().partition('=')
        if not equals or key not in ('pb', 'pw', 'fb', 'fw', 'kb', 'kw', 'end', 'cyc', 'pv', 'sk'):
            raise CheckError('unknown option %r' % item)
        if key in seen:
            raise CheckError('option %s given twice' % key)
        seen.add(key)
        colour = names['B' if key.endswith('b') else 'W'] if key[0] in 'pfk' else None
        if key in ('fb', 'fw', 'kb', 'kw'):
            points = [t.strip() for t in value.split(';') if t.strip()]
            if not points:
                raise CheckError('%s: no points' % key)
            indices = []
            for token in points:
                try:
                    p = problem.point(token)
                except ValueError as error:
                    raise CheckError('%s: %s' % (key, error))
                if p not in solver.problem.region and p not in solver.problem.fringe:
                    raise CheckError('%s: %s is outside the search area, so barring it changes nothing' % (key, token))
                indices.append(str(solver.problem.index(p)))
            items.append('%s=%s' % (key, ';'.join(indices)))
            listed = points[0] if len(points) == 1 else '%s or %s' % (', '.join(points[:-1]), points[-1])
            words.append('%s may not play at %s' % (colour, listed) if key[0] == 'f' else '%s may not use %s as a ko threat' % (colour, listed))
            continue
        if not re.fullmatch(r'\d+', value):
            raise CheckError('%s: %r is not a number' % (key, value))
        number = int(value)
        if key in ('pb', 'pw'):
            if not 0 <= number <= 8:
                raise CheckError('%s: from 0 to 8 forced passes' % key)
            items.append('%s=%d' % (key, number))
            if number:
                words.append('%s must pass its first %s' % (colour, 'turn' if number == 1 else '%s turns' % WORDS[number]))
        elif key == 'end':
            if number > 1:
                raise CheckError('end: 0 or 1')
            items.append('end=%d' % number)
            if number:
                words.append('with the end playout')
        elif key == 'cyc':
            if number > 3:
                raise CheckError('cyc: from 0 to 3')
            items.append('cyc=%d' % number)
            words.append(CYCLES[number])
        elif key == 'sk':
            #design D11's sekikill: only life without a seki counts as life, as in the checker's SEKI and SEKI+ tests
            if number > 1:
                raise CheckError('sk: 0 or 1')
            items.append('sk=%d' % number)
            if number:
                words.append('a seki counts as dead')
        elif number > 1:
            raise CheckError('pv: 0 or 1')
    return ','.join(items) or '-', words

def counted(solver, board, to_move, s, o, options):
    #does the solver reach its goal with s and o outside threats: True, False, or None when undecided
    text = solver.problem.board_text(board)
    ko = solver.problem.index(board.ko) if board.ko else -1
    key = (id(solver), text, ko, to_move, s, o, options)
    if key in cache:
        return cache[key]
    black, white = (s, o) if solver.solver_color == 'B' else (o, s)
    result = None
    for budget in BUDGETS:
        try:
            solver.activate_engine()
            answer = solver.engine.command('solvex %s %d %d %d %d %s %s' % (to_move, ko, budget, black, white, options, text))
        except OSError as error:
            raise CheckError('the engine stopped (%s)' % error)
        fields = dict(item.split('=', 1) for item in answer.split()[1:] if '=' in item)
        if fields.get('N') not in ('K', 'N', 'U'):
            raise CheckError('the engine answered %r' % answer)
        if fields['N'] != 'U':
            result = (fields['N'] == 'K') == (solver.solver_color == solver.attacker)
            break
    cache[key] = result
    return result

def status_of(solver, board, to_move):
    #the checker's class with Solver.status, None when undecided at both budgets
    both = [s for s in (solver, solver.wide) if s is not None]
    try:
        found = solver.status(board, to_move)[0]
        if found == STATUS_UNKNOWN:
            for s in both:
                s.budget = BUDGETS[1]
                s.cache.clear()
            found = solver.status(board, to_move)[0]
    except (OSError, KeyError, ValueError) as error:
        raise CheckError('the engine failed in the status search (%r)' % error)
    finally:
        for s in both:
            s.budget = BUDGETS[0]
    return None if found == STATUS_UNKNOWN else found

def threat_words(colour, s, o):
    first, second = names[colour], names[opponent(colour)]
    if s == o:
        return 'neither side has a ko threat' if s == 0 else 'each side has %s ko threat%s' % (WORDS[s], '' if s == 1 else 's')
    return '%s has %s and %s %s' % (first, 'no ko threats' if s == 0 else '%s ko threat%s' % (WORDS[s], '' if s == 1 else 's'),
                                    second, WORDS[o] if o else 'none')

def balance_words(colour, d):
    me, other = names[colour], names[opponent(colour)]
    threats = lambda k: '%s threat%s' % (WORDS[abs(k)], '' if abs(k) == 1 else 's')
    if d > 0:
        win = 'when it has %s more than %s' % (threats(d), other)
    elif d == 0:
        win = 'with as many ko threats as %s' % other
    else:
        win = 'even with %s fewer than %s' % (threats(d), other)
    fail = 'with an equal number' if d == 1 else 'with %s %s' % (threats(d - 1), 'more' if d > 1 else 'fewer')
    return '%s wins %s, and fails %s' % (me, win, fail)

def status_words(status, defender, own_target, solver):
    who = names[defender] if own_target else 'the target'
    if status == 'SEKI+':
        #design D11: the seki holds when the solver passes once, or the opponent must answer there
        keeps = 'keeps sente' if own_target and defender == solver else '%s keeps sente' % names[solver]
        return '%s lives in seki, whatever the ko threats, and %s' % (who, keeps)
    return {'DEAD': '%s dies, whatever the ko threats' % who, 'ALIVE': '%s lives, whatever the ko threats' % who,
            'KO': 'a ko, decided by the ko threats', 'BENT4': 'bent four in the corner',
            'SEKI': '%s lives in seki, whatever the ko threats' % who,
            'DDKO': '%s dies in a double ko, whatever the ko threats' % who,
            'ADKO': '%s lives in a double ko, whatever the ko threats' % who,
            'DSKO': '%s dies by superko' % who, 'ASKO': '%s lives by superko' % who}[status]

def pair_words(colour, s, o):
    return '%s %d, %s %d' % (names[colour], s, names[opponent(colour)], o)

def evaluate(position, diagram, check):
    #one check at the position after the diagram's moves: (context, verdict) for the line under the diagram
    if not isinstance(check, dict):
        raise CheckError('a check is an object')
    kinds = [kind for kind in CHECK_KEYS if kind in check]
    if len(kinds) != 1:
        raise CheckError('a check has exactly one of count, balance or status')
    kind = kinds[0]
    if set(check) - CHECK_KEYS[kind]:
        raise CheckError('unknown key(s) %s for a %s check' % (', '.join(sorted(set(check) - CHECK_KEYS[kind])), kind))
    problem, solver = position['problem'], position['solver']
    board, to_move = diagram['board'], diagram['to_move']
    colour = problem.first
    defender = opponent(colour) if problem.goal == 'KILL' else colour
    context = '%s to play' % names[to_move]
    if kind == 'status':
        if check['status'] not in STATUSES:
            raise CheckError('status must be one of %s' % ', '.join(STATUSES))
        found = status_of(solver, board, to_move)
        if found is None:
            raise CheckError('the status search is undecided at %d nodes' % BUDGETS[-1])
        if found != check['status']:
            raise CheckError('the solver says %s, the check expects %s' % (found, check['status']))
        return context, status_words(found, defender, position['spec'].get('target') is None, colour)
    options, words = parse_options(check.get('opts', ''), problem, solver)
    if kind == 'count':
        pair = check['count']
        if not (isinstance(pair, list) and len(pair) == 2 and all(isinstance(v, int) and 0 <= v <= 15 for v in pair)):
            raise CheckError('count must be [s, o] with threats from 0 to 15')
        if check.get('expect') not in ('wins', 'fails'):
            raise CheckError('expect must be "wins" or "fails"')
        s, o = pair
        result = counted(solver, board, to_move, s, o, options)
        if result is None:
            raise CheckError('undecided at %d nodes, at %s' % (BUDGETS[-1], pair_words(colour, s, o)))
        if result != (check['expect'] == 'wins'):
            raise CheckError('the solver %s at %s, the check expects %s' % ('wins' if result else 'fails', pair_words(colour, s, o), check['expect']))
        return ', '.join([context, threat_words(colour, s, o)] + words), '%s %s' % (names[colour], 'wins' if result else 'fails')
    d = check['balance']
    if not isinstance(d, int) or not -14 <= d <= 15:
        raise CheckError('balance must be a whole number from -14 to 15')
    win, fail = (max(d, 0), max(-d, 0)), (max(d - 1, 0), max(1 - d, 0))
    for pair, wanted in ((win, True), (fail, False)):
        result = counted(solver, board, to_move, pair[0], pair[1], options)
        if result is None:
            raise CheckError('undecided at %d nodes, at %s' % (BUDGETS[-1], pair_words(colour, *pair)))
        if result != wanted:
            raise CheckError('the solver %s at %s, so the balance is not %d' % ('wins' if result else 'fails', pair_words(colour, *pair), d))
    return ', '.join([context] + words), balance_words(colour, d)

def run_checks(section, errors):
    for position in section['positions']:
        if position['solver'] is None:
            continue
        for diagram in position['diagrams']:
            if not diagram['ok']:
                continue
            checks = diagram['spec'].get('checks', [])
            if not isinstance(checks, list):
                errors.append('%s: checks must be a list' % diagram['where'])
                continue
            for number, check in enumerate(checks, 1):
                where = '%s, check %d' % (diagram['where'], number)
                started = time.time()
                try:
                    context, verdict = evaluate(position, diagram, check)
                except CheckError as error:
                    errors.append('%s: %s' % (where, error))
                    print('  %s: FAILED (%s)' % (where, error), file=sys.stderr, flush=True)
                    continue
                diagram['lines'].append((position['area_label'], context, verdict))
                print('  %s: %s (%.1fs)' % (where, verdict, time.time() - started), file=sys.stderr, flush=True)

#the page
def prose(paragraphs, css='prose'):
    return '<div class="%s">%s</div>' % (css, ''.join('<p>%s</p>' % words_html(p) for p in paragraphs)) if paragraphs else ''

def outline(problem, points, frame):
    #the edges of the search frame's cells, inside the drawn part of the board; along the board's edge the line runs
    #just outside the edge, clear of the coordinates
    x1, x2, y1, y2 = frame
    cell, margin, edge = 26, 30, 6
    px = lambda x: margin + (x - x1) * cell
    py = lambda y: margin + (y - y1) * cell
    last = problem.size - 1
    segments = []
    for (x, y) in points:
        if not (x1 <= x <= x2 and y1 <= y <= y2):
            continue
        left = px(x) - (edge if x == 0 else cell / 2)
        right = px(x) + (edge if x == last else cell / 2)
        top = py(y) - (edge if y == 0 else cell / 2)
        bottom = py(y) + (edge if y == last else cell / 2)
        for neighbour, line in (((x - 1, y), (left, top, left, bottom)), ((x + 1, y), (right, top, right, bottom)),
                                ((x, y - 1), (left, top, right, top)), ((x, y + 1), (left, bottom, right, bottom))):
            if neighbour not in points:
                segments.append('<line class="frame-line" x1="%g" y1="%g" x2="%g" y2="%g"/>' % line)
    return ''.join(segments)

def render_figure(position, diagram, frame):
    problem, drawn = position['problem'], diagram['drawn']
    board, notes = report.svg(problem, drawn, frame, drawn['caption'])
    if position['frame']:
        #under the grid and the stones, right after the wood
        wood = re.search(r'<rect class="wood"[^>]*/>', board)
        board = board[:wood.end()] + outline(problem, position['frame'], frame) + board[wood.end():]
    note = '<div class="notes">%s</div>' % esc('; '.join(notes)) if notes else ''
    lines = ''.join('<li><span class="tag">Solver%s:</span> %s: <b>%s</b>.</li>' % (esc(area), esc(context), esc(verdict))
                    for area, context, verdict in diagram['lines'])
    checks = '<ul class="checks">%s</ul>' % lines if lines else ''
    #the board beside its words (stacked on phones): caption, the note of what the board cannot show, checks, text
    return '<figure class="diagram" id="%s-d%d">%s<figcaption><p class="caption"><b>Diagram %d.</b> %s</p>%s%s%s</figcaption></figure>' % (
        position['section_id'], diagram['number'], board, diagram['number'], words_html(drawn['caption']), note, checks,
        prose(diagram['spec'].get('text', []), 'prose figure-text'))

def render_position(position, index, frames):
    spec, problem = position['spec'], position['problem']
    current, export, date = position['tree']
    if current:
        tree = 'Tree: <code>S[%s]</code>' % esc(current)
        if date:
            tree += ', set on the site on %s (the export had %s)' % (esc(date), 'S[%s]' % esc(export) if export else 'none')
    else:
        tree = 'Not a leaf of the tree'
    meta = ['<a href="https://tsumego.com/%s">Problem %s</a>' % (problem.id, problem.id), esc(problem.record['title']), esc(problem.task())]
    if position['case']:
        meta.append('Research leaf %s' % esc(position['case']))
    meta.append(tree)
    setup = ''.join('<p class="setup">%s</p>' % words_html(line) for line in position['setup'])
    diagrams = ''.join(render_figure(position, d, frames[problem.id]) for d in position['diagrams'])
    return '<article class="position" id="%s-p%d"><header class="position-head"><h3>%s</h3><p class="meta">%s</p>%s</header>%s<div class="diagrams">%s</div></article>' % (
        position['section_id'], index, words_html(spec['heading']), ''.join('<span>%s</span>' % m for m in meta), setup,
        prose(spec.get('text', [])), diagrams)

def render_section(section, frames):
    spec = section['spec']
    out = ['<section class="question" id="%s"><h2>%s</h2>' % (esc(spec['id']), words_html(spec['heading']))]
    out.append('<p class="ask">%s</p>' % words_html(spec['question']))
    out.append('<div class="options"><h5>Options</h5><dl>%s</dl></div>' % ''.join(
        '<div class="option"><dt><code>%s</code></dt><dd>%s</dd></div>' % (esc(o['label']), words_html(o['meaning'])) for o in spec['options']))
    out.append('<div class="recommendation"><h5>Our recommendation</h5><p>%s</p></div>' % words_html(spec['recommendation']))
    out.append(prose(spec.get('text', [])))
    for index, position in enumerate(section['positions'], 1):
        out.append(render_position(position, index, frames))
    table = spec.get('table')
    if table:
        head = ''.join('<th scope="col">%s</th>' % words_html(c) for c in table['columns'])
        rows = ''.join('<tr>%s</tr>' % ''.join('<td>%s</td>' % problem_links(words_html(cell)) for cell in row) for row in table['rows'])
        caption = '<figcaption>%s</figcaption>' % words_html(table['caption']) if table.get('caption') else ''
        out.append('<figure class="table">%s<div class="table-wrap"><table class="data"><thead><tr>%s</tr></thead><tbody>%s</tbody></table></div></figure>' % (caption, head, rows))
    out.append('</section>')
    return '\n'.join(out)

def load(path, only, errors):
    #the page and its sections (only the one asked for with --only); a section file alone is a page of one section
    spec = read_json(path, errors)
    if spec is None:
        return None, []
    if not isinstance(spec, dict):
        errors.append('%s: not an object' % path)
        return None, []
    if 'sections' not in spec:
        if only and spec.get('id') != only:
            errors.append('%s is section %s, not %s' % (path, spec.get('id'), only))
        return {'title': 'Ko Label Questions', 'intro': []}, [spec]
    for key in sorted(set(spec) - PAGE_KEYS):
        errors.append('page: unknown key %r' % key)
    if not isinstance(spec.get('title'), str) or not spec['title'].strip():
        errors.append('page: no title')
    files = spec['sections']
    if only:
        if only + '.json' not in files:
            errors.append('page: no section file %s.json among %s' % (only, ', '.join(files)))
            return spec, []
        files = [only + '.json']
    sections = []
    for name in files:
        section = read_json(os.path.join(os.path.dirname(os.path.abspath(path)), name), errors)
        if section is None:
            continue
        if not isinstance(section, dict):
            errors.append('%s: not an object' % name)
            continue
        if not isinstance(section.get('id'), str) or section['id'] + '.json' != name:
            errors.append('%s: the section id %r does not match the file name' % (name, section.get('id')))
            continue
        sections.append(section)
    ids = [s['id'] for s in sections]
    for repeated in sorted({i for i in ids if ids.count(i) > 1}):
        errors.append('page: section %s appears twice' % repeated)
    return spec, sections

def main(arguments):
    if len(arguments) not in (2, 4) or (len(arguments) == 4 and arguments[2] != '--only'):
        sys.exit(USAGE)
    path, out_path = arguments[0], arguments[1]
    only = arguments[3] if len(arguments) == 4 else None
    errors = []
    page, specs = load(path, only, errors)
    if errors or page is None:
        for error in errors:
            print('ERROR', error, file=sys.stderr)
        sys.exit(1)
    intro = page.get('intro', [])
    if not isinstance(intro, list):
        errors.append('page: intro must be a list of paragraphs')
        intro = []
    for i, paragraph in enumerate(intro, 1):
        errors.extend(text_errors(paragraph, 'page intro paragraph %d' % i, set(), 0))
    sections = []
    for spec in specs:
        print('section %s' % spec.get('id'), file=sys.stderr, flush=True)
        section = prepare_section(spec, errors)
        for position in section['positions']:
            position['section_id'] = spec.get('id')
        run_checks(section, errors)
        sections.append(section)
    if errors:
        for error in errors:
            print('ERROR', error, file=sys.stderr)
        print('%d error(s); nothing written' % len(errors), file=sys.stderr)
        sys.exit(1)
    #one frame per problem, from its tree, every diagram drawn on it and the search frames
    extra = {}
    for section in sections:
        for position in section['positions']:
            points = extra.setdefault(position['problem'].id, set())
            for diagram in position['diagrams']:
                points |= report.diagram_points(position['problem'], diagram['drawn'])
            points |= position['frame'] or set()
    frames = {pid: report.frame_of(problem_of(pid), points) for pid, points in extra.items()}
    body = '\n'.join(render_section(section, frames) for section in sections)
    toc = ''.join('<li><a href="#%s">%s</a></li>' % (esc(s['spec']['id']), words_html(s['spec']['heading'])) for s in sections)
    checks = sum(len(d['lines']) for s in sections for p in s['positions'] for d in p['diagrams'])
    values = {'TITLE': esc(page['title']), 'INTRO': prose(intro, 'intro'), 'TOC': toc, 'BODY': body,
              'DATE': time.strftime('%Y-%m-%d'), 'CHECKS': str(checks), 'BUDGET': '{:,}'.format(BUDGETS[-1])}
    template = open(os.path.join(RESEARCH, 'questions_style.html')).read()
    open(out_path, 'w').write(re.sub(r'\{\{([A-Z]+)\}\}', lambda m: values[m.group(1)], template))
    print('wrote %s: %d section(s), %d solver check(s)' % (out_path, len(sections), checks), file=sys.stderr)

if __name__ == '__main__':
    main(sys.argv[1:])
