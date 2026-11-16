#one dossier per problem for the third research round: the tree's leaves, the merged checker's label of each new
#disagreeing leaf, and the verdicts of the earlier rounds on the problem's other leaves
#usage: make_dossiers.py <recheck.jsonl>... (cases: the 'N new, pass 4' group of the validation, plus keys given with --keys=<file>)
import collections, json, os, sys
CHECKER = '/home/user/tsumego-hero/tools/tsumego-checker'
sys.path.insert(0, CHECKER)
from check_tree import parse_sgf, build
from go_figure import LETTERS
HERE = os.path.dirname(os.path.abspath(__file__))
V = CHECKER + '/research/ko_labels/validation'
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open('/tmp/claude-0/dump2/status_problems.jsonl')}
groups = json.load(open(V + '/groups.json'))
gname = {tuple(k): g for g, v in groups.items() for k in v}
first_round = {(c['id'], c['line']): c for c in json.load(open(CHECKER + '/research/results/results.json'))}
second_round = collections.defaultdict(list)
for c in json.load(open(CHECKER + '/research/ko_labels/research/out/results_k.json')):
    second_round[c['id']].append(c)
args = [a for a in sys.argv[1:] if not a.startswith('--')]
options = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--'))
labels = {}
for path in args:
    for line in open(path):
        r = json.loads(line)
        labels[(r['id'], r['line'])] = r
#a leaf the merged checker timed out on keeps its pass-4 label (the checker before the merged fixes), marked as such
validated = {(r['id'], r['line']): r for r in json.load(open(V + '/labels.json')) if not r.get('timed_out')}
for key, r in list(labels.items()):
    if r.get('timed_out') and key in validated:
        labels[key] = dict(validated[key], old_label=True, timed_out_seconds=r['seconds'])
want = [] if 'only-keys' in options else [tuple(k) for k in groups['N new, pass 4']]
if 'keys' in options:
    want += [tuple(l.rstrip('\n').split('\t')) for l in open(options['keys']) if l.strip()]
names = {'B': 'Black', 'W': 'White'}
by = collections.defaultdict(list)
for key in want:
    if key not in labels:
        sys.exit('no recheck label for %s' % (key,))
    by[key[0]].append(labels[key])
cases, n = [], int(options.get('first', 101)) - 1
for pid in sorted(by, key=int):
    record = records[pid]
    root_sgf = parse_sgf(record['sgf'])
    root, first, size = build(root_sgf)
    goal = root_sgf.get('G')
    name = lambda p: 'pass' if p is None else LETTERS[p[0]] + str(size - p[1])
    leaves = []
    def walk(node, path):
        if not node.children:
            leaves.append((node.status_text or '(no S[])', ' '.join(path)))
        for child in node.children:
            walk(child, path + [name(child.move)])
    walk(root, [])
    L = ['# Problem %s: %s' % (pid, record['title']), '', 'https://tsumego.com/%s. %s plays first (the solver); %s to %s.' % (
        pid, names[first], names[first], 'kill' if goal == 'KILL' else 'live'), '']
    description = '\n'.join(root_sgf.properties.get('C', [])).strip()
    if description:
        L += ['Description: ' + description, '']
    L += ['## Leaves of the tree (S[] and the moves from the start, in tree order, numbered from 1)', '']
    L += ['%d. %s: %s' % (i + 1, s, p) for i, (s, p) in enumerate(leaves)]
    L += ['', '## The disagreeing leaves (labels of the merged checker, 16 November)', '']
    for d in by[pid]:
        n += 1
        case = 'k%03d' % n
        lab = d['label'] or {}
        earlier = first_round.get((d['id'], d['line']))
        index = [i + 1 for i, (s, p) in enumerate(leaves) if p == d['line']]
        L += ['### Case %s: leaf %s, tree S[%s], checker %s' % (case, index[0] if index else '?', d['authored'], lab.get('label')), '',
              '- Moves from the start: %s' % d['line'],
              '- Group: %s' % gname.get((d['id'], d['line']), options.get('group', 'rechecked')),
              '- Checker class: %s' % (d.get('status_text') or d.get('class')),
              *(['- NOTE: the merged checker timed out on this leaf after %.0f s (limit 300 s); the label below is the one '
                 'the checker gave before the merged fixes, with a longer limit (pass 4). Rerun it yourself with '
                 '--budget as needed.' % d['timed_out_seconds']] if d.get('old_label') else []),
              '- Checker label: %s; strict label %s; net reading %s; full count %s (decided %s); label count d_conv %s' % (
                  lab.get('label'), lab.get('strict_label') or lab.get('strict_from_parts'), lab.get('net_label'), lab.get('count'),
                  lab.get('count_decided'), lab.get('d_conv')),
              '- Checker text: %s' % lab.get('text'),
              '- Parts: first take %s; approaches %s; free moves %s; local threats %s; stages %s; gain %s; second ko %s; alternative reading %s' % (
                  json.dumps(lab.get('first_take')), json.dumps(lab.get('approaches')), json.dumps(lab.get('free_moves')),
                  json.dumps(lab.get('local_threats')), json.dumps(lab.get('stages')), json.dumps(lab.get('gain')),
                  json.dumps(lab.get('second_ko')), json.dumps(lab.get('alternative'))),
              '- Comparison with the tree: %s' % json.dumps(lab.get('comparison')),
              '- Warnings: %s; undecided parts: %s' % (json.dumps(lab.get('warnings')), json.dumps(lab.get('undecided'))),
              '- Checker lines (move lists from the leaf): %s' % json.dumps(lab.get('lines'))[:3000]]
        if earlier:
            L += ['- First research (case %s): verdict %s, settled S[%s]. Analysis: %s' % (
                earlier['case'], earlier['final'], earlier.get('correct_status'), (earlier.get('analysis') or {}).get('summary'))]
        L.append('')
        cases.append({'case': case, 'id': pid, 'title': record['title'], 'line': d['line'], 'authored': d['authored'],
                      'label': lab.get('label'), 'group': gname.get((d['id'], d['line']), 'rechecked')})
    if second_round.get(pid):
        L += ['## The second round on this problem\'s other leaves (15-16 November)', '']
        for c in sorted(second_round[pid], key=lambda c: c['case']):
            L.append('- %s, leaf "%s": tree S[%s], verdict %s%s. %s' % (
                c['case'], c['line'], c['authored'], c['final'], ', right S[%s]' % c['correct_status'] if c.get('correct_status') else '',
                ((c.get('analysis') or {}).get('summary') or '')[:600]))
        L.append('')
    open('%s/dossiers/%s.md' % (HERE, pid), 'w').write('\n'.join(L))
json.dump(cases, open(options.get('cases', HERE + '/cases.json'), 'w'), indent=1)
print(len(cases), 'cases in', len(by), 'problems')
