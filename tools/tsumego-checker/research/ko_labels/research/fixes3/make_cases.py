#the checker faults left after the merged fixes: every leaf whose right label the research settled and the merged checker
#still misses, with the research's reading and the merged checker's label; usage: make_cases.py <recheck.jsonl>...
import json, os, sys
HERE = os.path.dirname(os.path.abspath(__file__))
R = '/home/user/tsumego-hero/tools/tsumego-checker/research/ko_labels/research/out/results_k.json'
CASES = ['k023', 'k029', 'k031', 'k032', 'k035', 'k041', 'k043', 'k047', 'k048', 'k049']
labels = {}
for path in sys.argv[1:]:
    for line in open(path):
        r = json.loads(line)
        labels[(r['id'], r['line'])] = r
results = {c['case']: c for c in json.load(open(R))}
L = ['# The checker faults left after the merged fixes (16 November)', '',
     'Each case: a leaf whose right label the ko-label research settled (an analyst and reviewers, judges where they',
     'disagreed), and the label the merged checker (tsumego-checker at e019960d) still gives. The tree S[] is the owner\'s;',
     '"right" is the research\'s verdict (for a site error the tree is wrong too). The merged checker\'s label and parts come',
     'from validate.py at budget 200000 in the narrow area with a 300 s limit.', '']
for case in CASES:
    c = results[case]
    r = labels[(c['id'], c['line'])]
    lab = r.get('label') or {}
    a = c.get('analysis') or {}
    L += ['## %s: problem %s (%s)' % (case, c['id'], c['title']), '',
          '- Leaf: %s' % c['line'],
          '- Tree S[%s]; research verdict %s; right S[%s]' % (c['authored'], c['final'], c.get('correct_status')),
          '- Merged checker: label %s; class %s; strict %s; net %s; count %s (decided %s); d_conv %s; %s s, %s nodes' % (
              lab.get('label'), r.get('status_text') or r.get('class'), lab.get('strict_label'), lab.get('net_label'), lab.get('count'),
              lab.get('count_decided'), lab.get('d_conv'), r.get('seconds'), r.get('nodes')),
          '- Checker text: %s' % lab.get('text'),
          '- Parts: first take %s; approaches %s; free moves %s; local threats %s; stages %s; gain %s; second ko %s; alternative %s' % (
              json.dumps(lab.get('first_take')), json.dumps(lab.get('approaches')), json.dumps(lab.get('free_moves')),
              json.dumps(lab.get('local_threats')), json.dumps(lab.get('stages')), json.dumps(lab.get('gain')),
              json.dumps(lab.get('second_ko')), json.dumps(lab.get('alternative'))),
          '- Warnings: %s' % json.dumps(lab.get('warnings')),
          '- Undecided parts: %s' % json.dumps(lab.get('undecided')),
          '- Analyst (%s, says %s): %s' % (a.get('verdict'), a.get('correct_status'), a.get('summary')),
          '- Key line from the leaf: %s' % a.get('key_line'),
          '- Variations: %s' % a.get('variations'),
          '- Searches: %s' % (a.get('solver_checks') or '')[:2500]]
    if a.get('checker_part'):
        L.append('- Wrong part of the label (analyst): %s' % a['checker_part'])
    for i, k in enumerate(c.get('checks') or [], 1):
        L.append('- Reviewer %d (%s, %s): %s' % (i, k.get('lens'), 'upholds' if k.get('holds') else 'disputes', (k.get('explanation') or '')[:3000]))
    for j in c.get('judges') or []:
        L.append('- Judge (%s, says %s): %s' % (j.get('verdict'), j.get('correct_status'), (j.get('explanation') or '')[:2000]))
    if c.get('answered'):
        L.append("- The owner's answer: %s" % json.dumps(c['answered']))
    L.append('')
open(os.path.join(HERE, 'cases.md'), 'w').write('\n'.join(L))
print(len(CASES), 'cases,', len('\n'.join(L)), 'characters')
