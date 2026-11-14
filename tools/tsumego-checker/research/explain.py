#a page that explains the ko status of tree leaves move by move: numbered diagrams from a spec, threat tables and
#checks from the solver. usage: PROBLEMS=status_problems.jsonl explain.py <spec.json> <out.html>
#spec: {problem, title, description, intro: [html], leaves: [{heading, line, tree, right, text: [html],
#       steps: [{title, start, moves, first, caption, notes: {number: text}, check: {s, o, black_lives} or {status}}]}]}
#a leaf without line, tree and right is a plain section (no claim, no threat table); a check with wide: true uses
#the wider area, and check can be a list
#moves: points like A18, 'A18!' for a retake after a ko threat elsewhere, 'pass' for a move elsewhere
import html, json, os, re, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, here)
from check_tree import parse_sgf, build, opponent, all_nodes, region_of, groups_of, Problem, focused_region, Engine, Solver
from tsumego import add_wide
from go_figure import LETTERS

spec = json.load(open(sys.argv[1]))
for line in open(os.environ.get('PROBLEMS', 'problems.jsonl')):
    record = json.loads(line)
    if record['set_connection_id'] == spec['problem']:
        break
else:
    sys.exit('no problem %s in the export' % spec['problem'])
sgf_root = parse_sgf(record['sgf'])
root, solver_color, size = build(sgf_root)
goal = sgf_root.get('G')
tree_points = [n.move for n in all_nodes(root) if n.move]
box = region_of(size, list(root.board.stones) + tree_points)
color = opponent(solver_color) if goal == 'KILL' else solver_color
attacker = solver_color if goal == 'KILL' else opponent(solver_color)
stones = groups_of(root.board, color)[0]
solver = Solver(Engine(), Problem(root.board, focused_region(root.board, box, stones, tree_points)), attacker, stones, int(os.environ.get('BUDGET', '200000')))
solver.solver_color = solver_color
add_wide(solver, root.board, list(root.board.stones) + tree_points, stones, tree_points)
solver.wide.solver_color = solver_color
Solver.deadline = None
names = {'B': 'Black', 'W': 'White'}
name = lambda p: LETTERS[p[0]] + str(size - p[1])

def tokens(text):
    out = []
    for token in text.split():
        if token == 'pass':
            out.append((None, False))
        else:
            out.append(((LETTERS.index(token.rstrip('!')[0].upper()), size - int(token.rstrip('!')[1:])), token.endswith('!')))
    return out

def play_all(text, board=None, to_move=None, where='from the start'):
    #plays the moves, failing loudly on an illegal one
    board = (board or root.board).copy()
    to_move = to_move or solver_color
    for p, retake in tokens(text):
        if not board.play(to_move, p, ignore_ko=retake):
            sys.exit('illegal move %s for %s in "%s" (%s)' % (name(p) if p else 'pass', names[to_move], text, where))
        to_move = opponent(to_move)
    return board, to_move

#the frame of every diagram: the stones and tree moves, one line of margin
points = list(root.board.stones) + tree_points
frame = (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
         max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))

def figure(board, to_move, text, first, caption, extra_notes):
    x1, x2, y1, y2 = frame
    cell, margin = 26, 30
    width, height = margin * 2 + cell * (x2 - x1), margin * 2 + cell * (y2 - y1)
    px = lambda x: margin + (x - x1) * cell
    py = lambda y: margin + (y - y1) * cell
    label = html.escape(html.unescape(re.sub('<[^>]+>', '', caption)))
    out = ['<svg class="board" viewBox="0 0 %d %d" role="img" aria-label="%s">' % (width, height, label)]
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
    current = board.copy()
    numbers, ghosts, notes = {}, {}, []
    mover = to_move
    for number, (p, retake) in enumerate(tokens(text), first):
        if str(number) in extra_notes:
            notes.append('%d %s' % (number, extra_notes[str(number)]))
        elif p is None:
            notes.append('%d: %s plays elsewhere' % (number, names[mover]))
        if retake:
            notes.append('%d retakes after a ko threat elsewhere (answered)' % number)
        before = dict(current.stones)
        if p is not None and not current.play(mover, p, ignore_ko=retake):
            sys.exit('illegal move %s in "%s" from move %d' % (name(p), text, first))
        if p is None:
            current.play(mover, None)
        else:
            if p in ghosts:
                if ghosts[p][1]:
                    notes.append('%d is played where %d was captured' % (number, ghosts[p][1]))
                del ghosts[p]
            numbers[p] = number
            captured = [q for q in before if q not in current.stones]
            for q in captured:
                ghosts[q] = (before[q], numbers.pop(q, None))
            if captured:
                notes.append('%d captures %s' % (number, ' '.join(name(q) for q in sorted(captured, key=lambda q: (q[1], q[0])))))
        mover = opponent(mover)
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
    out.append('</svg>')
    note = ('<div class="notes">%s</div>' % '<br>'.join(html.escape(n) for n in notes)) if notes else ''
    return '<figure>%s<figcaption>%s</figcaption>%s</figure>' % (''.join(out), caption, note), current, mover

def lives(board, to_move, s, o, wide=False):
    return (solver.wide if wide else solver).solver_wins(board, to_move, s, o)

def predicted(label, s, o):
    #does the label say Black (the solver) reaches its goal with s and o threats
    match = re.fullmatch(r'KO([+-])(\d*)', label)
    n = int(match.group(2) or 1)
    return s >= o - (n - 1) if match.group(1) == '+' else s >= o + n

def check_text(step, board, to_move):
    #check: {status} or {s, o, black_lives}, with wide: true to search the wider area (for moves on the lower side),
    #or a list of such checks
    check = step.get('check')
    if not check:
        return ''
    if isinstance(check, list):
        return ''.join(check_text(dict(step, check=one), board, to_move) for one in check)
    wide = check.get('wide', False)
    who = 'Solver (wider area)' if wide else 'Solver (search area)'
    if 'status' in check:
        status, _ = (solver.wide if wide else solver).status(board, to_move)
        if status != check['status']:
            sys.exit('step "%s": the solver says %s, the spec %s' % (step['title'], status, check['status']))
        return '<p class="check">%s: %s to play, %s.</p>' % (who, names[to_move], {'DEAD': 'Black is dead', 'ALIVE': 'Black lives', 'KO': 'a ko', 'BENT4': 'bent four'}[status])
    result = lives(board, to_move, check['s'], check['o'], wide)
    if result is None or result != check['black_lives']:
        sys.exit('step "%s": the solver says %s at s=%d o=%d, the spec %s' % (step['title'], result, check['s'], check['o'], check['black_lives']))
    return '<p class="check">%s: %s to play, threats Black %d and White %d, Black %s.</p>' % (
        who, names[to_move], check['s'], check['o'], 'lives' if result else 'dies')

def table(leaf, board, to_move):
    rows = ['<tr><th scope="col">Black s \\ White o</th>%s</tr>' % ''.join('<th scope="col">o = %d</th>' % o for o in range(3))]
    for s in range(3):
        cells = []
        for o in range(3):
            result = lives(board, to_move, s, o)
            tree, right = predicted(leaf['tree'], s, o), predicted(leaf['right'], s, o)
            word = {True: 'lives', False: 'dies', None: '?'}[result]
            mark = ' class="differs"' if tree != result else ''
            cells.append('<td%s>%s<span class="pred">%s: %s</span></td>' % (mark, word, html.escape(leaf['tree']), 'lives' if tree else 'dies'))
        rows.append('<tr><th scope="row">s = %d</th>%s</tr>' % (s, ''.join(cells)))
    return '<div class="table-wrap"><table class="threats">%s</table></div>' % ''.join(rows)

def paragraph(text, css=''):
    #a text item: raw html when it starts with a tag, else a paragraph
    return text if text.startswith('<') else '<p%s>%s</p>' % (' class="%s"' % css if css else '', text)

body = []
for number, leaf in enumerate(spec['leaves'], 1):
    #a section without tree and right is plain text and steps, with no claim and no threat table
    body.append('<section class="leaf" id="leaf%d"><h2>%s</h2>' % (number, leaf['heading']))
    if 'tree' in leaf:
        leaf_board, leaf_to_move = play_all(leaf['line'], where='leaf %d' % number)
        body.append('<div class="claim"><span>Tree: <b>%s</b></span><span>Should be: <b>%s</b></span><span class="link"><a href="https://tsumego.com/%s">tsumego.com/%s</a></span></div>' % (
            html.escape(leaf['tree']), html.escape(leaf['right']), spec['problem'], spec['problem']))
    body.extend(paragraph(t) for t in leaf['text'])
    for step in leaf['steps']:
        board, to_move = play_all(step['start'], where=step['title'])
        svg, end, end_to_move = figure(board, to_move, step['moves'], step.get('first', 1), step['caption'], step.get('notes', {}))
        body.append('<div class="step"><h3>%s</h3><div class="step-body">%s<div class="step-text">%s%s</div></div></div>' % (
            step['title'], svg, ''.join(paragraph(t) for t in step.get('text', [])), check_text(step, end, end_to_move)))
    if 'tree' not in leaf:
        body.append('</section>')
        continue
    body.append('<h3>All threat counts</h3><p class="note">The solver\'s counted search in its search area, from the leaf (%s to play), for each number of ko threats: s for Black, o for White. Each cell also shows what S[%s] predicts; the cells where the prediction is wrong are marked. S[%s] fits every cell.</p>' % (
        names[leaf_to_move], html.escape(leaf['tree']), html.escape(leaf['right'])))
    for s in range(3):
        for o in range(3):
            if lives(leaf_board, leaf_to_move, s, o) != predicted(leaf['right'], s, o):
                sys.exit('leaf %d: S[%s] does not fit the solver at s=%d o=%d' % (number, leaf['right'], s, o))
    body.append(table(leaf, leaf_board, leaf_to_move))
    body.append('</section>')
    print('leaf', number, 'done', file=sys.stderr, flush=True)

style = open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'explain_style.html')).read()
page = style.replace('TITLE', html.escape(spec['title'])).replace('INTRO', ''.join(paragraph(t, 'intro') for t in spec['intro'])).replace('BODY', '\n'.join(body))
open(sys.argv[2], 'w').write(page)
print('wrote', sys.argv[2])
