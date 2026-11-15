#writes the files the judges and fact checkers read: settle/<case>.a.md (the analysis first, then the reviews),
#settle/<case>.b.md (the reviews first, then the analysis) for every case a review did not uphold, and
#settle/<case>.facts.md for every definition case; prints the args lists for the settle workflow
#usage: settle_files.py
import json, os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'settle')
os.makedirs(OUT, exist_ok=True)
cases = json.load(open(os.path.join(HERE, 'out', 'results2.json')))

def header(c):
    lines = ['# Case %s: problem %s (%s)' % (c['case'], c['id'], c['title']), '',
             '- Leaf (moves from the start): %s' % c['line'],
             "- The tree's S[]: %s" % c['authored'],
             "- The checker's full label: %s" % c['label'],
             '- Group: %s' % c['group']]
    if c.get('research_case'):
        lines.append('- Earlier research: leaf %s, settled %s' % (c['research_case'], c.get('research_status') or '?'))
    lines.append('- Dossier with the checker\'s label parts and lines: /tmp/claude-0/proto/research/kores/dossiers/%s.md' % c['id'])
    return lines + ['']

def analysis(c):
    a = c['analysis']
    lines = ['## The analyst', '']
    for key in ('verdict', 'correct_status', 'confidence', 'summary', 'variations', 'key_line', 'solver_checks', 'checker_part', 'depends_on', 'earlier_research'):
        if a.get(key):
            lines.append('- **%s**: %s' % (key, a[key]))
    return lines + ['']

def reviews(c):
    lines = []
    for i, r in enumerate(c['reviews'], 1):
        lines += ['## Reviewer %d (%s lens): %s' % (i, r['lens'], 'upholds the analyst' if r.get('holds') else 'does not uphold the analyst'), '',
                  '- **correct_status**: %s' % (r.get('correct_status') or '(none given)'), '', r.get('explanation', ''), '']
    return lines

judge, facts = [], []
for c in cases:
    meta = {key: c[key] for key in ('case', 'id', 'title', 'line', 'authored', 'label')}
    if c['pending']:
        continue
    if any(not r.get('holds') for r in c['reviews']):
        open(os.path.join(OUT, c['case'] + '.a.md'), 'w').write('\n'.join(header(c) + analysis(c) + reviews(c)))
        open(os.path.join(OUT, c['case'] + '.b.md'), 'w').write('\n'.join(header(c) + reviews(c) + analysis(c)))
        judge.append(meta)
    elif c['analysis']['verdict'] == 'definition':
        open(os.path.join(OUT, c['case'] + '.facts.md'), 'w').write('\n'.join(header(c) + analysis(c)))
        facts.append(meta)
print(json.dumps({'judge': judge, 'facts': facts}))
print('pending:', [c['case'] for c in cases if c['pending']])
