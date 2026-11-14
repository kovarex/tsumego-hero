#the research report: every position is a numbered diagram, the text refers to the diagrams and their numbered stones.
#usage: PROBLEMS=problems.jsonl report.py check <spec.json>...
#       PROBLEMS=problems.jsonl report.py render <specs dir> <out.html>
#A problem spec (<specs dir>/<problem>.json) has one card per leaf of the problem in the results, and optional blocks:
#  {problem, cards: [{case, headline, diagrams: [D], text: [paragraph]}],
#   grading: {text, diagrams}, tree_change: {text, diagrams}, also_check: {text, diagrams}}
#A topic spec (<specs dir>/topic-<key>.json) introduces a decision of topics.json: {topic, text, diagrams, suggestion}.
#A diagram D: {start, moves, first, caption, labels: {point: letter}, problem}: start is the position (moves from the
#problem's start, not numbered), moves are numbered from first (default: the move after start); 'pass' is a move
#elsewhere, 'A18!' a retake after a ko threat elsewhere; labels mark points; problem defaults to the spec's.
#Text names diagrams as "Diagram 2" and stones as "Black 7" or "White 12"; it never lists moves.
import html, json, os, re, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, here)
from check_tree import parse_sgf, build, opponent, all_nodes
from go_figure import LETTERS

RESEARCH = os.path.dirname(os.path.abspath(__file__))
records = {}
for line in open(os.environ.get('PROBLEMS', 'problems.jsonl')):
    record = json.loads(line)
    records[record['set_connection_id']] = record
results = json.load(open(os.path.join(RESEARCH, 'results', 'results.json')))
topics = json.load(open(os.path.join(RESEARCH, 'topics.json')))
names = {'B': 'Black', 'W': 'White'}
COORD = r'(?<![A-Za-z0-9])[A-HJ-T](?:1[0-9]|[1-9])(?![0-9A-Za-z])'
SEQUENCE = re.compile(COORD + r'!?(?:\s*,?\s+(?:pass\s+)?)' + COORD)
STONE = re.compile(r'\b(Black|White)(?:\'s)? (\d+)\b')
DIAGRAM = re.compile(r'\bDiagrams? (\d+)(?:(?:,| and| or| to|–|-) ?(\d+))*')

class Problem:
    def __init__(self, pid):
        if pid not in records:
            raise SystemExit('no problem %s in the export' % pid)
        self.id = pid
        self.record = records[pid]
        self.sgf = parse_sgf(self.record['sgf'])
        self.root, self.first, self.size = build(self.sgf)
        self.goal = self.sgf.get('G')
        self.points = set(self.root.board.stones) | {n.move for n in all_nodes(self.root) if n.move}

    def point(self, token):
        token = token.rstrip('!')
        try:
            x, y = LETTERS.index(token[0].upper()), self.size - int(token[1:])
        except (ValueError, IndexError):
            raise ValueError('not a point: %r' % token)
        if not (0 <= x < self.size and 0 <= y < self.size):
            raise ValueError('off the board: %r' % token)
        return (x, y)

    def name(self, p):
        return LETTERS[p[0]] + str(self.size - p[1])

    def task(self):
        return '%s to %s' % (names[self.first], 'kill' if self.goal == 'KILL' else 'live')

    def replay(self, start, moves, first=None):
        #plays start unnumbered, then the numbered moves; returns the board before the numbered moves, the side to
        #move there, and [(number, colour, point or None, retake)]
        board, to_move = self.root.board.copy(), self.first
        for token in start.split():
            p = None if token == 'pass' else self.point(token)
            if not board.play(to_move, p, ignore_ko=token.endswith('!')):
                raise ValueError('illegal start move %s for %s' % (token, names[to_move]))
            to_move = opponent(to_move)
        numbered, current, mover = [], board.copy(), to_move
        number = first if first is not None else len(start.split()) + 1
        for token in moves.split():
            p = None if token == 'pass' else self.point(token)
            if not current.play(mover, p, ignore_ko=token.endswith('!')):
                raise ValueError('illegal move %s for %s (number %d)' % (token, names[mover], number))
            numbered.append((number, mover, p, token.endswith('!')))
            mover = opponent(mover)
            number += 1
        return board, to_move, numbered

def diagram_points(problem, d):
    out = set()
    for token in (d.get('start', '') + ' ' + d.get('moves', '')).split():
        if token != 'pass':
            out.add(problem.point(token))
    for token in d.get('labels', {}):
        out.add(problem.point(token))
    return out

def frame_of(problem, extra):
    points = problem.points | extra
    size = problem.size
    return (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
            max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))

def play_through(problem, d):
    #the position after the numbered moves: stones, numbers on the stones still standing, captured stones, notes
    board, to_move, numbered = problem.replay(d.get('start', ''), d.get('moves', ''), d.get('first'))
    current, numbers, ghosts, notes = board.copy(), {}, {}, []
    for number, colour, p, retake in numbered:
        if p is None:
            notes.append('%d: %s plays elsewhere' % (number, names[colour]))
            current.play(colour, None)
            continue
        if retake:
            notes.append('%d retakes after a ko threat elsewhere' % number)
        before = dict(current.stones)
        current.play(colour, p, ignore_ko=retake)
        if p in ghosts:
            if ghosts[p][1]:
                notes.append('%d is played where %d was captured' % (number, ghosts[p][1]))
            del ghosts[p]
        numbers[p] = number
        captured = [q for q in before if q not in current.stones]
        for q in captured:
            ghosts[q] = (before[q], numbers.pop(q, None))
        if captured:
            notes.append('%d captures %s' % (number, 'one stone' if len(captured) == 1 else '%d stones' % len(captured)))
    return current, numbers, ghosts, notes, numbered

def svg(problem, d, frame, title):
    current, numbers, ghosts, notes, numbered = play_through(problem, d)
    x1, x2, y1, y2 = frame
    cell, margin = 26, 30
    size = problem.size
    width, height = margin * 2 + cell * (x2 - x1), margin * 2 + cell * (y2 - y1)
    px = lambda x: margin + (x - x1) * cell
    py = lambda y: margin + (y - y1) * cell
    #the board keeps its natural size (26 px a line) and only shrinks to fit, so a narrow frame is not blown up
    out = ['<svg class="board" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="%s">' % (width, height, width, height, html.escape(title))]
    out.append('<rect class="wood" x="0" y="0" width="%d" height="%d" rx="4"/>' % (width, height))
    for x in range(x1, x2 + 1):
        top = py(y1) if y1 == 0 else py(y1) - cell / 2
        bottom = py(y2) if y2 == size - 1 else py(y2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if x in (0, size - 1) else '', px(x), top, px(x), bottom))
        out.append('<text class="coord" x="%d" y="%d">%s</text>' % (px(x), height - 8, LETTERS[x]))
    for y in range(y1, y2 + 1):
        left = px(x1) if x1 == 0 else px(x1) - cell / 2
        right = px(x2) if x2 == size - 1 else px(x2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if y in (0, size - 1) else '', left, py(y), right, py(y)))
        out.append('<text class="coord" x="%d" y="%d">%d</text>' % (11, py(y) + 4, size - y))
    inside = lambda q: x1 <= q[0] <= x2 and y1 <= q[1] <= y2
    for q, (colour, number) in ghosts.items():
        if inside(q):
            out.append('<circle class="ghost %s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(q[0]), py(q[1])))
            if number:
                out.append('<text class="number ghost-number" x="%d" y="%d">%d</text>' % (px(q[0]), py(q[1]) + 4, number))
    if current.ko and inside(current.ko):
        out.append('<rect class="ko" x="%d" y="%d" width="26" height="26" rx="3"/>' % (px(current.ko[0]) - 13, py(current.ko[1]) - 13))
    for q, colour in current.stones.items():
        if inside(q):
            out.append('<circle class="%s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(q[0]), py(q[1])))
            if q in numbers:
                out.append('<text class="number %s" x="%d" y="%d">%d</text>' % ('on-black' if colour == 'B' else 'on-white', px(q[0]), py(q[1]) + 4, numbers[q]))
    for token, letter in d.get('labels', {}).items():
        q = problem.point(token)
        colour = current.stones.get(q)
        if colour is None:
            out.append('<circle class="label-ground" cx="%d" cy="%d" r="8"/>' % (px(q[0]), py(q[1])))
        out.append('<text class="label %s" x="%d" y="%d">%s</text>' % ({'B': 'on-black', 'W': 'on-white'}.get(colour, 'on-wood'), px(q[0]), py(q[1]) + 4, html.escape(letter)))
    out.append('</svg>')
    return ''.join(out), notes

def text_problems(text, block, where):
    #move lists, references to missing diagrams or to numbered stones that no diagram of the block has
    errors = []
    plain = re.sub('<[^>]+>', '', text)
    for m in SEQUENCE.finditer(plain):
        errors.append('%s: a move sequence in the text ("%s"); draw it in a diagram' % (where, m.group(0)))
    stones = set()
    for d in block.get('diagrams', []):
        try:
            for number, colour, p, retake in d['_numbered']:
                stones.add((names[colour], number))
        except KeyError:
            pass
    for m in STONE.finditer(plain):
        if (m.group(1), int(m.group(2))) not in stones:
            errors.append('%s: "%s" names no numbered %s stone in this block\'s diagrams' % (where, m.group(0), m.group(1)))
    count = len(block.get('diagrams', []))
    for m in DIAGRAM.finditer(plain):
        for number in re.findall(r'\d+', m.group(0)):
            if not 1 <= int(number) <= count:
                errors.append('%s: "%s" but this block has %d diagram(s)' % (where, m.group(0), count))
    return errors

def check_block(problem_of, block, where, errors, notes_out):
    for i, d in enumerate(block.get('diagrams', []), 1):
        problem = problem_of(d)
        if not d.get('caption'):
            errors.append('%s, diagram %d: no caption' % (where, i))
        for key in d:
            if key not in ('start', 'moves', 'first', 'caption', 'labels', 'problem', '_numbered'):
                errors.append('%s, diagram %d: unknown key %r' % (where, i, key))
        try:
            current, numbers, ghosts, notes, numbered = play_through(problem, d)
            d['_numbered'] = numbered
            for token in d.get('labels', {}):
                problem.point(token)
        except ValueError as error:
            errors.append('%s, diagram %d: %s' % (where, i, error))
            continue
        errors.extend(text_problems(d.get('caption', ''), block, '%s, diagram %d caption' % (where, i)))
        notes_out.append(ascii_board(problem, d, current, numbers, numbered, '%s, diagram %d' % (where, i)))
    for j, paragraph in enumerate(block.get('text', []), 1):
        errors.extend(text_problems(paragraph, block, '%s, paragraph %d' % (where, j)))
        if len(paragraph.split()) > 130:
            errors.append('%s, paragraph %d: %d words; keep paragraphs under 130' % (where, j, len(paragraph.split())))

def ascii_board(problem, d, current, numbers, numbered, title):
    frame = frame_of(problem, diagram_points(problem, d))
    x1, x2, y1, y2 = frame
    labels = {problem.point(t): l for t, l in d.get('labels', {}).items()}
    lines = ['%s (%s)' % (title, d.get('caption', '')), '    ' + ''.join('%4s' % LETTERS[x] for x in range(x1, x2 + 1))]
    for y in range(y1, y2 + 1):
        row = ''
        for x in range(x1, x2 + 1):
            q = (x, y)
            if q in numbers:
                cell = str(numbers[q]) + ('b' if current.stones[q] == 'B' else 'w')
            elif q in current.stones:
                cell = 'X' if current.stones[q] == 'B' else 'O'
            elif q in labels:
                cell = labels[q]
            else:
                cell = '.'
            row += '%4s' % cell
        lines.append('%3d ' % (problem.size - y) + row)
    moves = ', '.join('%d %s %s%s' % (n, names[c], problem.name(p) if p else 'pass', '!' if r else '') for n, c, p, r in numbered)
    lines.append('    numbered: ' + (moves or 'none') + '   (Xb = Black stone numbered X, Xw = White)')
    return '\n'.join(lines)

def check(path):
    spec = json.load(open(path))
    errors, boards = [], []
    if 'topic' in spec:
        keys = {t['key'] for t in topics}
        if spec['topic'] not in keys:
            errors.append('unknown topic %r' % spec['topic'])
        for d in spec.get('diagrams', []):
            if 'problem' not in d:
                errors.append('topic diagram without problem: %s' % d.get('caption'))
        problem_of = lambda d: Problem(d.get('problem', ''))
        check_block(problem_of, spec, 'topic', errors, boards)
        errors.extend(text_problems(spec.get('suggestion', ''), spec, 'suggestion'))
        return errors, boards
    problem = Problem(spec['problem'])
    problem_of = lambda d: Problem(d['problem']) if d.get('problem') else problem
    cases = {c['case'] for c in results if c['id'] == spec['problem']}
    seen = set()
    for card in spec.get('cards', []):
        where = 'card %s' % card.get('case')
        if card.get('case') not in cases:
            errors.append('%s: not a leaf of problem %s in the results' % (where, spec['problem']))
        seen.add(card.get('case'))
        if not card.get('headline'):
            errors.append('%s: no headline' % where)
        elif len(card['headline'].split()) > 35:
            errors.append('%s: headline of %d words; keep it under 35' % (where, len(card['headline'].split())))
        if not card.get('diagrams'):
            errors.append('%s: no diagram' % where)
        check_block(problem_of, card, where, errors, boards)
        errors.extend(text_problems(card.get('headline', ''), card, where + ' headline'))
    for case in sorted(cases - seen):
        errors.append('no card for leaf %s' % case)
    for key in ('grading', 'tree_change', 'also_check'):
        if key in spec:
            check_block(problem_of, spec[key], key, errors, boards)
    for key in spec:
        if key not in ('problem', 'cards', 'grading', 'tree_change', 'also_check'):
            errors.append('unknown key %r' % key)
    return errors, boards

if __name__ == '__main__' and sys.argv[1] == 'check':
    failed = False
    for path in sys.argv[2:]:
        errors, boards = check(path)
        print('\n\n'.join(boards))
        print()
        for error in errors:
            print('ERROR', error)
        print('%s: %s' % (path, 'OK' if not errors else '%d error(s)' % len(errors)))
        failed = failed or bool(errors)
    sys.exit(1 if failed else 0)

#the report page
def fix_label(c, topic):
    if topic:
        return 'S[%s] now · options: %s' % (c['authored'], ' or '.join('S[%s]' % o for o in topic['options'][c['case']]))
    if c['final'] == 'site_error':
        return 'S[%s] → S[%s]' % (c['authored'], c['correct_status'])
    return 'S[%s] is right' % c['authored']

def site_note(pid, cases, impact):
    #what the S[] fixes alone change in the site's grading, for a problem without a grading block
    fixed = [c for c in cases if c['final'] == 'site_error']
    if not fixed:
        return ''
    r = impact.get('all:' + pid) or impact.get(fixed[0]['case'])
    if r is None:
        raise SystemExit('no grading scenario for problem %s' % pid)
    if r['flipped']:
        raise SystemExit('problem %s: its fixes change which moves are correct, but its spec has no grading block' % pid)
    text = 'On the site: no move changes between correct and wrong'
    if r['root_before'] != r['root_after']:
        text += ", but the problem's overall result goes from S[%s] to S[%s]" % (r['root_before'], r['root_after'])
    return '<p class="site-note">%s.</p>' % html.escape(text)

def figures(problem_of, block, frames, prefix):
    out = []
    for i, d in enumerate(block.get('diagrams', []), 1):
        problem = problem_of(d)
        board, notes = svg(problem, d, frames[problem.id], d['caption'])
        note = '<div class="notes">%s</div>' % html.escape('; '.join(notes)) if notes else ''
        out.append('<figure>%s<figcaption><b>%sDiagram %d.</b> %s</figcaption>%s</figure>' % (
            board, prefix, i, html.escape(d['caption']).replace('−', '&minus;'), note))
    return '<div class="figures">%s</div>' % ''.join(out) if out else ''

def prose(paragraphs, css='prose'):
    return '<div class="%s">%s</div>' % (css, ''.join('<p>%s</p>' % html.escape(p) for p in paragraphs)) if paragraphs else ''

def render(specs_dir, out_path):
    impact = {r['key']: r for r in json.load(open(os.path.join(RESEARCH, 'results', 'impact.json')))}
    by_case = {c['case']: c for c in results}
    in_topic = {case: t for t in topics for case in t['cases']}
    specs, topic_specs = {}, {}
    for name in sorted(os.listdir(specs_dir)):
        if not name.endswith('.json'):
            continue
        spec = json.load(open(os.path.join(specs_dir, name)))
        errors, _ = check(os.path.join(specs_dir, name))
        if errors:
            raise SystemExit('%s: %d error(s), first: %s' % (name, len(errors), errors[0]))
        if 'topic' in spec:
            topic_specs[spec['topic']] = spec
        else:
            specs[spec['problem']] = spec
    problems = {}
    problem_of_id = lambda pid: problems.setdefault(pid, Problem(pid))
    #PREVIEW=1 renders what is there, leaving out problems and topics without a spec (for a look while writing)
    preview = os.environ.get('PREVIEW') == '1'
    missing = sorted({c['id'] for c in results} - set(specs), key=int)
    if missing and not preview:
        raise SystemExit('no spec for problems %s' % ', '.join(missing))
    missing = [t['key'] for t in topics if t['key'] not in topic_specs]
    if missing and not preview:
        raise SystemExit('no topic spec for %s' % ', '.join(missing))
    shown = lambda c: c['id'] in specs
    #one frame per problem, from its tree and every diagram drawn on it
    extra = {}
    def collect(block, default):
        for d in block.get('diagrams', []):
            pid = d.get('problem') or default
            extra.setdefault(pid, set()).update(diagram_points(problem_of_id(pid), d))
    for pid, spec in specs.items():
        for card in spec['cards']:
            collect(card, pid)
        for key in ('grading', 'tree_change', 'also_check'):
            if key in spec:
                collect(spec[key], pid)
    for spec in topic_specs.values():
        collect(spec, None)
    frames = {pid: frame_of(problem_of_id(pid), points) for pid, points in extra.items()}

    def problem_block(pid, cases, with_blocks=True, note=True):
        spec, problem = specs[pid], problem_of_id(pid)
        problem_of = lambda d: problem_of_id(d.get('problem') or pid)
        cards = {card['case']: card for card in spec['cards']}
        out = ['<section class="problem" id="p%s"><header class="problem-head"><h4><a href="https://tsumego.com/%s">%s</a> %s</h4><span class="task">%s</span></header>' % (
            pid, pid, pid, html.escape(problem.record['title']), problem.task())]
        for c in sorted(cases, key=lambda c: c['case']):
            card = cards[c['case']]
            out.append('<article class="leaf" id="%s"><div class="leaf-head"><label><input type="checkbox" class="done" data-id="%s"> Leaf %s</label><code class="fix">%s</code></div><p class="headline">%s</p>%s%s</article>' % (
                c['case'], c['case'], c['case'], html.escape(fix_label(c, in_topic.get(c['case']))), html.escape(card['headline']),
                figures(problem_of, card, frames, ''), prose(card.get('text', []))))
        titles = {'grading': 'On the site', 'tree_change': 'Change the tree as well', 'also_check': 'Other leaves to check in this tree'}
        if with_blocks:
            for key in ('tree_change', 'grading', 'also_check'):
                if key in spec:
                    out.append('<div class="block %s"><h5>%s</h5>%s%s</div>' % (key.replace('_', '-'), titles[key], prose(spec[key].get('text', [])), figures(problem_of, spec[key], frames, '')))
            if note and 'grading' not in spec:
                out.append(site_note(pid, cases, impact))
        out.append('</section>')
        return ''.join(out)

    def group(cases):
        by = {}
        for c in cases:
            by.setdefault(c['id'], []).append(c)
        return sorted(by.items(), key=lambda kv: int(kv[0]))

    errors = [c for c in results if c['final'] == 'site_error' and c['case'] not in in_topic and shown(c)]
    solver_cases = [c for c in results if c['final'] == 'solver_error' and c['case'] not in in_topic and shown(c)]
    decide = [c for c in results if c['case'] in in_topic and shown(c)]
    def needs_tree(pid):
        return 'tree_change' in specs[pid]
    def changes_grading(pid):
        return 'grading' in specs[pid]
    error_groups = group(errors)
    tree_group = [(p, cs) for p, cs in error_groups if needs_tree(p)]
    grading_group = [(p, cs) for p, cs in error_groups if changes_grading(p) and not needs_tree(p)]
    label_group = [(p, cs) for p, cs in error_groups if not changes_grading(p) and not needs_tree(p)]
    leaves_of = lambda groups: sum(len(cs) for _, cs in groups)

    body = []
    body.append('<section id="decisions"><h2>1. Decisions for you <span class="count">%d leaves</span></h2>' % len(decide))
    body.append('<p class="section-lede">These leaves are not plain errors: the right S[] depends on how the site wants to label a shape or read a problem. Each decision is explained once, with a suggestion, and the leaves it settles follow.</p>')
    for t in topics:
        if t['key'] not in topic_specs:
            continue
        spec = topic_specs[t['key']]
        cases = [by_case[case] for case in t['cases'] if shown(by_case[case])]
        body.append('<div class="topic" id="topic-%s"><h3>%s</h3>' % (t['key'], html.escape(t['title'])))
        body.append(prose(spec['text']))
        body.append(figures(lambda d: problem_of_id(d['problem']), spec, frames, ''))
        body.append('<p class="suggestion"><b>Suggestion.</b> %s</p>' % html.escape(spec['suggestion']))
        for pid, cs in group(cases):
            body.append(problem_block(pid, cs, with_blocks=False))
        body.append('</div>')
    body.append('</section>')
    body.append('<section id="errors"><h2>2. Clear errors on the site <span class="count">%d leaves</span></h2>' % len(errors))
    body.append('<p class="section-lede">For each leaf, an analyst and two independent reviewers agree that the tree\'s S[] is wrong. The fixes fall into three groups, by what else they need.</p>')
    body.append('<h3 id="tree-changes">Change the tree as well <span class="count">%d problems</span></h3><p class="section-lede">With only the new S[], the site would grade some moves wrongly or not show the new status, usually because the tree lacks a move. Each problem says what to add or remove.</p>' % len(tree_group))
    body += [problem_block(p, cs) for p, cs in tree_group]
    body.append('<h3 id="grading-changes">Fixes that change which moves are correct <span class="count">%d problems</span></h3><p class="section-lede">The new S[] changes which moves the site accepts. A checker played the alternatives out and confirmed the new marks.</p>' % len(grading_group))
    body += [problem_block(p, cs) for p, cs in grading_group]
    body.append('<h3 id="label-fixes">Change the S[] <span class="count">%d leaves in %d problems</span></h3><p class="section-lede">No move changes between correct and wrong; where the problem\'s overall result changes, the problem says so.</p>' % (leaves_of(label_group), len(label_group)))
    rows = ''.join('<tr><td><a href="#p%s">%s</a></td><td>%s</td><td>%s</td></tr>' % (
        p, p, html.escape(problem_of_id(p).record['title']),
        '<br>'.join('<a href="#%s">%s</a> <code>%s</code>' % (c['case'], c['case'], html.escape(fix_label(c, None))) for c in sorted(cs, key=lambda c: c['case'])))
        for p, cs in label_group)
    body.append('<div class="table-wrap"><table class="index"><thead><tr><th>Problem</th><th>Title</th><th>Leaf and fix</th></tr></thead><tbody>%s</tbody></table></div>' % rows)
    body += [problem_block(p, cs) for p, cs in label_group]
    body.append('</section>')
    body.append('<section id="solver"><h2>Appendix A. The solver\'s mistakes <span class="count">%d leaves</span></h2><p class="section-lede">Here the tree is right and the solver was wrong; nothing to change on the site. These are for improving the checker.</p>' % len(solver_cases))
    body += [problem_block(p, cs, note=False) for p, cs in group(solver_cases)]
    body.append('</section>')
    toc = [('decisions', '1. Decisions for you', '%d leaves' % len(decide)),
           ('tree-changes', '2a. Change the tree as well', '%d problems' % len(tree_group)),
           ('grading-changes', '2b. Fixes that change which moves are correct', '%d problems' % len(grading_group)),
           ('label-fixes', '2c. Change the S[]', '%d leaves' % leaves_of(label_group)),
           ('solver', "A. The solver's mistakes", '%d leaves' % len(solver_cases)),
           ('method', 'B. How the leaves were checked', '')]
    counts = {'total': len(results), 'errors': len(errors), 'decide': len(decide), 'solver': len(solver_cases),
              'tree': len(tree_group), 'grading': len(grading_group), 'label_leaves': leaves_of(label_group), 'label_problems': len(label_group)}
    page = open(os.path.join(RESEARCH, 'report_style.html')).read()
    page = page.replace('TOC', ''.join('<li><a href="#%s">%s</a> <span class="count">%s</span></li>' % t for t in toc))
    page = page.replace('BODY', '\n'.join(body))
    for key, value in counts.items():
        page = page.replace('{%s}' % key, str(value))
    open(out_path, 'w').write(page)
    print('report:', counts)

if __name__ == '__main__' and sys.argv[1] == 'render':
    render(sys.argv[2], sys.argv[3])
