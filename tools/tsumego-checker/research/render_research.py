#review page from the research results: clear site errors, questions to decide, and (folded) the solver's own errors.
#usage: PROBLEMS=status_problems.jsonl render_research.py <results.json> <out.html>
import html, json, os, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, here)
from check_tree import parse_sgf, build, opponent, all_nodes
from go_figure import figure, LETTERS

results = json.load(open(sys.argv[1]))
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(os.environ.get('PROBLEMS', 'problems.jsonl'))}
names = {'B': 'black', 'W': 'white'}

def parse_moves(board, to_move, text, size):
    #moves in evidence.py notation from a position: [(colour, point, note)], stops at the first illegal one
    moves, current = [], board.copy()
    for token in text.split():
        retake = token.endswith('!')
        token = token.rstrip('!')
        if token.lower() == 'pass':
            p = None
        else:
            try:
                p = (LETTERS.index(token[0].upper()), size - int(token[1:]))
            except (ValueError, IndexError):
                break
        if not current.play(to_move, p, ignore_ko=retake):
            break
        moves.append((to_move, p, 'retakes after a ko threat' if retake else ''))
        to_move = opponent(to_move)
    return moves

def figures(c):
    record = records[c['id']]
    root, solver_color, size = build(parse_sgf(record['sgf']))
    board, to_move, path = root.board.copy(), solver_color, []
    for token in c['line'].split():
        p = (LETTERS.index(token[0]), size - int(token[1:]))
        board.play(to_move, p)
        path.append((to_move, p))
        to_move = opponent(to_move)
    key = (c.get('analysis') or {}).get('key_line', '') or ''
    line = parse_moves(board, to_move, key, size)
    points = list(root.board.stones) + [p for _, p in path] + [m[1] for m in line if m[1]]
    frame = (max(0, min(p[0] for p in points) - 1), min(size - 1, max(p[0] for p in points) + 1),
             max(0, min(p[1] for p in points) - 1), min(size - 1, max(p[1] for p in points) + 1))
    out = figure(root.board, path, frame, 'Tree path to the leaf: the tree says %s here' % c['authored'])
    if line:
        out += figure(board, line, frame, 'From the leaf, %s to play: %s' % (names[to_move], (c.get('analysis') or {}).get('correct_status') or 'the line the analysis found'))
    return out

def solver_text(c):
    return c.get('solver_sign') or c['solver']

def card(c, kind):
    a = c.get('analysis') or {}
    right = a.get('correct_status') or '?'
    checks = c.get('checks') or []
    agreeing = [k['correct_status'] for k in checks if k.get('holds') and k.get('correct_status')]
    if kind == 'error' and agreeing and any(s != right for s in agreeing):
        right = '%s (reviewers: %s)' % (right, ', '.join(sorted(set(agreeing))))
    record = records[c['id']]
    sgf_root = parse_sgf(record['sgf'])
    first = build(sgf_root)[1]
    task = '%s to %s' % (names[first], 'kill' if sgf_root.get('G') == 'KILL' else 'live')
    head = '''<section class="card" id="%s">
  <header><label><input type="checkbox" class="done" data-id="%s"> <a href="https://tsumego.com/%s">%s</a></label>
  <span class="meta">%s · %s</span></header>
  <div class="verdict"><span class="said">Leaf: <b>%s</b></span></div>
  <div class="verdict"><span class="said">Tree: <b>%s</b></span> <span class="said">Solver: <b>%s</b></span> <span class="said">%s: <b>%s</b></span></div>
''' % (c['case'], c['case'], c['id'], c['id'], html.escape(record['title']), task,
       html.escape(c['line']), html.escape(c['authored']), html.escape(solver_text(c)),
       'Should be' if kind == 'error' else 'Analysis', html.escape(right))
    body = '<p class="why">%s</p>' % html.escape(a.get('summary', ''))
    if kind != 'error' and a.get('question'):
        body += '<p class="question"><b>To decide:</b> %s</p>' % html.escape(a['question'])
    try:
        body += '<div class="figures">%s</div>' % figures(c)
    except Exception as error:
        body += '<p class="meta">(diagram failed: %s)</p>' % html.escape(repr(error))
    if kind != 'error':
        for k in checks:
            body += '<p class="dissent"><b>%s:</b> %s</p>' % ('Reviewer agrees' if k.get('holds') else 'Reviewer disagrees', html.escape(k.get('explanation') or ''))
    more = ''
    for label, value in (('Variations', a.get('variations')), ('Solver checks', a.get('solver_checks'))):
        if value:
            more += '<p><b>%s:</b> %s</p>' % (label, html.escape(value))
    if kind == 'error':
        for k in checks:
            more += '<p><b>Reviewer:</b> %s</p>' % html.escape(k.get('explanation') or '')
    if more:
        body += '<details class="more"><summary>%s</summary>%s</details>' % ('Variations, solver checks and reviews' if kind == 'error' and checks else 'Variations and solver checks', more)
    return head + body + '</section>'

GROUPS = (('definition', 'Definition questions', 'How a status should be labelled, or what the problem asks beyond the target\'s life.'),
          ('disputed', 'Reviewers split', 'The reviewers did not agree with each other, or with the analysis.'),
          ('refuted', 'Error claims both reviewers rejected', 'The analysis called the tree wrong; both reviewers found a hole or were not sure.'),
          ('unclear', 'Not settled', 'The analysis could not settle these within its time.'))
errors = [c for c in results if c['final'] == 'site_error']
decide = [c for c in results if c['final'] in [g[0] for g in GROUPS]]
solver = [c for c in results if c['final'] == 'solver_error']
order = lambda c: (int(c['id']), c['line'])
body = '<h2>Clear status errors on the site <span class="count">%d</span></h2>' % len(errors)
body += '<p class="intro">The analysis and two independent reviewers agree the tree\'s S[] is wrong here.</p>'
body += '\n'.join(card(c, 'error') for c in sorted(errors, key=order))
body += '<h2>Unclear or to be decided <span class="count">%d</span></h2>' % len(decide)
for final, title, note in GROUPS:
    group = sorted([c for c in decide if c['final'] == final], key=order)
    if group:
        body += '<h3>%s <span class="count">%d</span></h3><p class="intro">%s</p>' % (title, len(group), note)
        body += '\n'.join(card(c, final) for c in group)
body += '<details><summary>Solver errors, the tree is right (%d)</summary><ul class="rest">%s</ul></details>' % (len(solver), ''.join(
    '<li><a href="https://tsumego.com/%s">%s</a> %s, tree %s, solver %s: %s</li>' % (c['id'], c['id'], html.escape(c['line']), html.escape(c['authored']), html.escape(solver_text(c)), html.escape((c.get('analysis') or {}).get('summary', ''))) for c in sorted(solver, key=order)))
style = open(os.path.join(here, 'status_examples_style.html')).read()
style = style.replace('<title>Status Leaf Review</title>', '<title>Status Disagreement Research</title>').replace('<h1>Status Leaf Review</h1>', '<h1>Status Disagreement Research</h1>')
start = style.index('<p class="intro">Leaves of the status problems'); end = style.index('</p>', start) + 4
intro = '<p class="intro">Every leaf of the status problems where the solver and the tree disagree (export of 10 November), checked one by one: an analyst read each position step by step with the solver as a calculator, and reviewers tried to refute every conclusion. %d leaves: %d clear errors on the site, %d to decide, %d solver errors.</p>' % (len(results), len(errors), len(decide), len(solver))
style = style[:start] + intro + style[end:]
style = style.replace('</style>', '.why { margin: 0; } .question { margin: 0; color: var(--ink); } .dissent { margin: 0; color: var(--muted); font-size: 0.9rem; }\ndetails summary { cursor: pointer; font-weight: 600; margin-top: 8px; }\nh3 { font-size: 1.05rem; margin: 10px 0 0; } .more { font-size: 0.9rem; color: var(--muted); } .more summary { font-weight: 500; margin: 0; } .more p { margin: 6px 0 0; }\n</style>', 1)
style = style.replace("'ko-review-'", "'status-research-'")
open(sys.argv[2], 'w').write(style.replace('BODY', body).replace('TOTAL', str(len(results))))
print(len(results), 'cases:', len(errors), 'errors,', len(decide), 'to decide,', len(solver), 'solver errors')
