#compares the recheck of the 127 disagreements with the labels they had and the research's verdicts
#usage: recheck_compare.py <recheck.jsonl>...   (prints a table and a summary; writes recheck_table.json beside this script)
import collections, json, os, sys
R = '/home/user/tsumego-hero/tools/tsumego-checker/research/ko_labels'
old = {(d['id'], d['line']): d for d in json.load(open(R + '/validation/disagreements.json'))}
research = {(c['id'], c['line']): c for c in json.load(open(R + '/research/out/results_k.json'))}
new = {}
for path in sys.argv[1:]:
    for line in open(path):
        r = json.loads(line)
        new[(r['id'], r['line'])] = r
label = lambda r: (r.get('label') or {}).get('label') if r else None
groups = json.load(open(R + '/validation/groups.json'))
gname = {tuple(k): g for g, v in groups.items() for k in v}
first_round = {(c['id'], c['line']): c for c in json.load(open(R + '/../results/results.json'))}
rows, tally = [], collections.Counter()
for key, d in sorted(old.items(), key=lambda kv: (int(kv[0][0]), kv[0][1])):
    r = new.get(key)
    c = research.get(key)
    before, after = label(d), label(r)
    row = {'id': key[0], 'line': key[1], 'tree': d['authored'], 'before': before, 'after': after, 'case': c and c['case'],
           'verdict': c and c['final'], 'correct': c and c.get('correct_status'), 'seconds': r and r.get('seconds')}
    if r is None:
        kind = 'not rechecked yet'
    elif c and c['final'] == 'solver_error':
        kind = 'solver error fixed' if after == c['correct_status'] else 'solver error still wrong'
    elif c and c['final'] == 'site_error':
        if after == c['correct_status']:
            kind = 'site error, solver agrees with the fix'
        elif before == c['correct_status']:
            kind = 'site error, REGRESSION (agreed before)'
        else:
            kind = 'site error, solver still differs from the fix'
    elif c and c['final'] == 'definition':
        kind = 'decision leaf'
    elif after == d['authored']:
        kind = '%s: now agrees with the tree' % gname.get(key)
    elif after == before:
        kind = '%s: unchanged' % gname.get(key)
    else:
        kind = '%s: CHANGED' % gname.get(key)
    settled = first_round.get(key)
    if settled and not c:
        row['correct'] = settled.get('correct_status')
        row['case'] = settled['case']
    row['kind'] = kind
    tally[kind] += 1
    rows.append(row)
for kind in sorted(tally):
    print('\n== %s (%d)' % (kind, tally[kind]))
    for row in rows:
        if row['kind'] == kind:
            print('  %-6s %-5s tree %-8s before %-8s after %-8s %s%s  [%s]' % (row['id'], row['case'] or '', row['tree'], row['before'], row['after'],
                  'correct %s ' % row['correct'] if row['correct'] else '', row['line'], row['seconds']))
json.dump(rows, open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'recheck_table.json'), 'w'), indent=1)
print('\n', dict(tally))
