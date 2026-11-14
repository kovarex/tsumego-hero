#one dossier per problem for the report writers: the tree's leaves, every verdict and check of the research on the
#problem's leaves, the grading effect of the fixes, and which blocks the problem's report spec needs.
#usage: PROBLEMS=problems.jsonl report_dossier.py <impact.json> <out dir>
import json, os, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, here)
from check_tree import parse_sgf, build, all_nodes
from go_figure import LETTERS

RESEARCH = os.path.dirname(os.path.abspath(__file__))
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(os.environ.get('PROBLEMS', 'problems.jsonl'))}
results = json.load(open(os.path.join(RESEARCH, 'results', 'results.json')))
topics = json.load(open(os.path.join(RESEARCH, 'topics.json')))
impact = {r['key']: r for r in json.load(open(sys.argv[1]))}
out_dir = sys.argv[2]
os.makedirs(out_dir, exist_ok=True)
in_topic = {case: t for t in topics for case in t['cases']}
names = {'B': 'Black', 'W': 'White'}

def needs_tree(check):
    tree = check['tree_changes'].strip()
    return (tree and not tree.lower().startswith('none')) or not check['holds']

def leaves(sgf):
    root, first, size = build(parse_sgf(sgf))
    name = lambda p: 'pass' if p is None else LETTERS[p[0]] + str(size - p[1])
    out = []
    def walk(node, path):
        if not node.children:
            out.append(('S[%s]' % node.status_text if node.status_text else '(no S[])', ' '.join(path)))
        for child in node.children:
            walk(child, path + [name(child.move)])
    walk(root, [])
    return out, first, size

def describe(r):
    lines = []
    if r['root_before'] != r['root_after']:
        lines.append("the problem's overall status goes from %s to %s" % (r['root_before'], r['root_after']))
    for f in r['flipped']:
        lines.append('move %d (the last move of "%s") becomes %s, now %s; its status %s before, %s after' % (
            f['number'], f['path'], f['after'], f['before'], f['status_before'] or 'none', f['status_after'] or 'none'))
    return '; '.join(lines) or 'no change in grading'

by_problem = {}
for c in results:
    by_problem.setdefault(c['id'], []).append(c)
for pid, cases in sorted(by_problem.items(), key=lambda kv: int(kv[0])):
    record = records[pid]
    sgf_root = parse_sgf(record['sgf'])
    tree_leaves, first, size = leaves(record['sgf'])
    goal = sgf_root.get('G')
    lines = ['# Problem %s: %s' % (pid, record['title']), '',
             'https://tsumego.com/%s. %s plays first; %s to %s.' % (pid, names[first], names[first], 'kill' if goal == 'KILL' else 'live'), '']
    comment = '\n'.join(sgf_root.properties.get('C', [])).strip()
    if comment:
        lines += ['Description: %s' % comment, '']
    lines += ['## Leaves of the tree (S[] and the moves from the start)', '']
    lines += ['- %s: %s' % (status, path) for status, path in tree_leaves]
    lines += ['', '## Leaves of this problem in the report', '']
    checks = None
    for c in sorted(cases, key=lambda c: c['case']):
        a = c.get('analysis') or {}
        topic = in_topic.get(c['case'])
        if topic:
            category = 'decision for the owner: %s (options: %s)' % (topic['title'], ' or '.join(topic['options'][c['case']]))
        elif c['final'] == 'site_error':
            category = 'clear error on the site: S[%s] should be S[%s]' % (c['authored'], c['correct_status'])
        elif c['final'] == 'solver_error':
            category = 'solver error: the tree S[%s] is right' % c['authored']
        else:
            sys.exit('%s: final %s outside the topics' % (c['case'], c['final']))
        lines += ['### Leaf %s: %s' % (c['case'], category), '',
                  '- Leaf: %s (moves from the start)' % c['line'],
                  '- Tree S[%s]; the solver said %s' % (c['authored'], c.get('solver_sign') or c['solver']),
                  '- Analysis (%s, %s confidence, says %s): %s' % (a.get('verdict'), a.get('confidence'), a.get('correct_status') or '?', a.get('summary')),
                  '- Variations: %s' % a.get('variations'),
                  '- Key line from the leaf: %s' % (a.get('key_line') or '(none)')]
        if a.get('question'):
            lines.append('- The analysis asks: %s' % a['question'])
        for i, k in enumerate(c.get('checks') or [], 1):
            lines.append('- Reviewer %d (%s%s): %s' % (i, 'agrees the tree is wrong' if k['holds'] and a.get('verdict') != 'solver_error' else 'agrees the tree is right' if k['holds'] else 'disagrees with the analysis',
                                                    ', says ' + k['correct_status'] if k.get('correct_status') else '', k['explanation']))
        for name, j in zip(('A', 'B'), c.get('judges') or []):
            lines.append('- Judge %s (%s%s): %s%s' % (name, j['verdict'], ', says ' + j['correct_status'] if j.get('correct_status') else '', j['explanation'],
                                                     ' Question: ' + j['question'] if j.get('question') else ''))
        if c.get('facts'):
            f = c['facts']
            lines.append('- Fact check (facts %s, verdict %s): %s %s' % ('confirmed' if f['facts_hold'] else 'corrected', f['verdict'], f['explanation'], f.get('correction') or ''))
        r = impact.get(c['case'])
        if r:
            lines.append('- Grading on the site with S[%s] on this leaf alone: %s' % (r['fixes'][0]['status'], describe(r)))
        for key, r in sorted(impact.items()):
            if key.startswith(c['case'] + '@'):
                lines.append('- Grading with S[%s] instead: %s' % (key.split('@', 1)[1], describe(r)))
        lines.append('')
        checks = checks or c.get('grading_check')
    together = impact.get('all:' + pid)
    if together:
        lines += ['## Grading with all clear fixes of this problem together', '', describe(together), '']
    lines += ['## Grading check (a checker tried to show the new grading wrong)', '']
    if checks:
        lines += ['- New marks right with the S[] fixes alone: %s' % ('yes' if checks['holds'] else 'no'),
                  '- Explanation: %s' % checks['explanation'],
                  '- Tree changes needed: %s' % (checks['tree_changes'] or 'none'),
                  '- Other leaves: %s' % (checks['other_leaves'] or 'none'), '']
    else:
        lines += ['None for this problem.', '']
    blocks = ['cards: one card per leaf above (%s)' % ', '.join(sorted(c['case'] for c in cases))]
    if checks:
        blocks.append('grading: yes, show which moves change between correct and wrong, and whether that is right')
        if needs_tree(checks):
            blocks.append('tree_change: yes, show every change to make in the tree beyond the S[] fixes')
        other = checks['other_leaves'].strip()
        if other and not other.lower().startswith('none'):
            blocks.append('also_check: yes, show the other leaves the grading checker found')
    lines += ['## What the spec needs', ''] + ['- ' + b for b in blocks] + ['']
    open(os.path.join(out_dir, '%s.md' % pid), 'w').write('\n'.join(lines))
print(len(by_problem), 'dossiers')
