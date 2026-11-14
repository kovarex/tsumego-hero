#review page from the research results: questions to decide, clear site errors, and (folded) the solver's own errors,
#with the site's grading change for each proposed fix when an impact file (grading_impact.cjs) is given.
#usage: PROBLEMS=status_problems.jsonl render_research.py <results.json> <out.html> [<impact.json> [<cases.json>]]
#(with the cases file, the leaves not in the results yet are listed as still under review)
import html, json, os, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, here)
from check_tree import parse_sgf, build, opponent, all_nodes
from go_figure import figure, LETTERS

results = json.load(open(sys.argv[1]))
impact = {r['key']: r for r in json.load(open(sys.argv[3]))} if len(sys.argv) > 3 else None
checked = {c['case'] for c in results}
pending = [c for c in json.load(open(sys.argv[4])) if c['case'] not in checked] if len(sys.argv) > 4 else []
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

def describe(r, first):
    #one scenario of grading_impact.cjs in words
    if r.get('error'):
        raise SystemExit('grading check failed for %s: %s' % (r['key'], r['error']))
    out = []
    if r['root_before'] != r['root_after']:
        out.append("the problem's status goes from %s to %s" % (html.escape(r['root_before']), html.escape(r['root_after'])))
    for f in r['flipped']:
        moves = f['path'].split()
        color = names[first] if f['solver'] else names[opponent(first)]
        after = ' after <code>%s</code>' % html.escape(' '.join(moves[:-1])) if len(moves) > 1 else ''
        out.append('%s %d %s%s becomes <b>%s</b> (now %s)' % (color.capitalize(), f['number'], moves[-1], after, f['after'], f['before']))
    return '; '.join(out) if out else 'no grading change'

def grading(c, first, fixable):
    #what the proposed S[] changes in the site's grading, for the analysis label, other labels named, and all fixes of the problem
    if impact is None:
        return ''
    a = c.get('analysis') or {}
    label = c.get('correct_status', (a.get('correct_status') or '').strip())
    lines = []
    main = impact.get(c['case'])
    if main:
        lines.append('With S[%s]: %s.' % (html.escape(label), describe(main, first)))
    elif fixable:
        lines.append('Grading not checked: %s.' % ('no single besogo label' if label != c['authored'] else 'the label stays'))
    for key, r in sorted(impact.items()):
        if key.startswith(c['case'] + '@'):
            text = describe(r, first)
            if not main or text != describe(main, first):
                lines.append('With S[%s] instead: %s.' % (html.escape(key.split('@', 1)[1]), text))
    together = impact.get('all:' + c['id'])
    if together and c['final'] == 'site_error' and (not main or describe(together, first) != describe(main, first)):
        lines.append('With all %d fixes of this problem: %s.' % (sum(1 for x in results if x['id'] == c['id'] and x['final'] == 'site_error'), describe(together, first)))
    if not lines:
        return ''
    changes = any(not l.endswith('no grading change.') for l in lines)
    return '<p class="grading%s"><b>On the site:</b> %s</p>' % (' changes' if changes else '', ' '.join(lines))

def solver_text(c):
    return c.get('solver_sign') or c['solver']

def card(c, kind):
    a = c.get('analysis') or {}
    right = c.get('correct_status', a.get('correct_status')) or '?'
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
    facts = c.get('facts')
    if facts:
        body += '<p class="dissent"><b>%s:</b> %s</p>' % ('Fact check agrees' if facts['facts_hold'] and facts['verdict'] == 'definition' else 'Fact check (%s)' % facts['verdict'].replace('_', ' '),
                                                       html.escape(' '.join(x for x in (facts.get('correction'), facts.get('explanation')) if x)))
    for j in c.get('judges') or []:
        if kind != 'error':
            body += '<p class="dissent"><b>Judge (%s%s):</b> %s%s</p>' % (j['verdict'].replace('_', ' '), ', ' + html.escape(j['correct_status']) if j.get('correct_status') else '',
                                                                       html.escape(j.get('explanation') or ''), ' <b>To decide:</b> ' + html.escape(j['question']) if j.get('question') else '')
    body += grading(c, first, kind != 'solver')
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
        for j in c.get('judges') or []:
            more += '<p><b>Judge:</b> %s</p>' % html.escape(j.get('explanation') or '')
    if more:
        body += '<details class="more"><summary>%s</summary>%s</details>' % ('Variations, solver checks and reviews' if kind == 'error' and checks else 'Variations and solver checks', more)
    return head + body + '</section>'

GROUPS = (('definition', 'Definition questions', 'How a status should be labelled, or what the problem asks beyond the target\'s life. A fact checker played out the lines each question rests on.'),
          ('disputed', 'Reviewers split', 'The reviewers did not agree with each other, or with the analysis.'),
          ('refuted', 'Error claims both reviewers rejected', 'The analysis called the tree wrong; both reviewers found a hole or were not sure.'),
          ('unclear', 'Not settled', 'Checkers and two independent judges could not agree, or could not settle it.'))
errors = [c for c in results if c['final'] == 'site_error']
decide = [c for c in results if c['final'] in [g[0] for g in GROUPS]]
solver = [c for c in results if c['final'] == 'solver_error']
order = lambda c: (int(c['id']), c['line'])
body = '<h2>1. Unclear or to be decided <span class="count">%d</span></h2>' % len(decide)
for final, title, note in GROUPS:
    group = sorted([c for c in decide if c['final'] == final], key=order)
    if group:
        body += '<h3>%s <span class="count">%d</span></h3><p class="intro">%s</p>' % (title, len(group), note)
        body += '\n'.join(card(c, final) for c in group)
body += '<h2>2. Clear status errors on the site <span class="count">%d</span></h2>' % len(errors)
body += '<p class="intro">The analysis and two independent reviewers agree the tree\'s S[] is wrong here (for a disputed case, two independent judges settled it). "On the site" says which moves the site would grade differently after the fix, from besogo\'s own grading code.</p>'
body += '\n'.join(card(c, 'error') for c in sorted(errors, key=order))
if pending:
    body += '<h2>Still under review <span class="count">%d</span></h2><p class="intro">Reviewers are still checking these; they will be added to the lists above.</p><ul class="rest">%s</ul>' % (len(pending), ''.join(
        '<li><a href="https://tsumego.com/%s">%s</a> %s, tree %s, solver %s</li>' % (c['id'], c['id'], html.escape(c['line']), html.escape(c['authored']), html.escape(c.get('solver_sign') or c['solver'])) for c in sorted(pending, key=order)))
body += '<details><summary>Solver errors, the tree is right (%d)</summary><ul class="rest">%s</ul></details>' % (len(solver), ''.join(
    '<li><a href="https://tsumego.com/%s">%s</a> %s, tree %s, solver %s: %s</li>' % (c['id'], c['id'], html.escape(c['line']), html.escape(c['authored']), html.escape(solver_text(c)), html.escape((c.get('analysis') or {}).get('summary', ''))) for c in sorted(solver, key=order)))
style = open(os.path.join(here, 'status_examples_style.html')).read()
style = style.replace('<title>Status Leaf Review</title>', '<title>Status Disagreement Research</title>').replace('<h1>Status Leaf Review</h1>', '<h1>Status Disagreement Research</h1>')
start = style.index('<p class="intro">Leaves of the status problems'); end = style.index('</p>', start) + 4
intro = '<p class="intro">Every leaf of the status problems where the solver and the tree disagree (export of 10 November), checked one by one: an analyst read each position step by step with the solver as a calculator, and reviewers tried to refute every conclusion. %s: %d to decide, %d clear errors on the site, %d solver errors (the tree is right).</p>' % (
    '%d leaves' % len(results) if not pending else '%d of %d leaves checked so far' % (len(results), len(results) + len(pending)), len(decide), len(errors), len(solver))
style = style[:start] + intro + style[end:]
style = style.replace('</style>', '.grading { margin: 0; font-size: 0.9rem; color: var(--muted); } .grading.changes { color: var(--ink); } .grading code { font: 0.85em var(--mono); }\n.why { margin: 0; } .question { margin: 0; color: var(--ink); } .dissent { margin: 0; color: var(--muted); font-size: 0.9rem; }\ndetails summary { cursor: pointer; font-weight: 600; margin-top: 8px; }\nh3 { font-size: 1.05rem; margin: 10px 0 0; } .more { font-size: 0.9rem; color: var(--muted); } .more summary { font-weight: 500; margin: 0; } .more p { margin: 6px 0 0; }\n</style>', 1)
style = style.replace("'ko-review-'", "'status-research-'")
open(sys.argv[2], 'w').write(style.replace('BODY', body).replace('TOTAL', str(len(results))))
print(len(results), 'cases:', len(errors), 'errors,', len(decide), 'to decide,', len(solver), 'solver errors')
