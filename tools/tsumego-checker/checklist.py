#the findings still to review as a table: problem, path with colours, what the tree says, what the solver says, its line
import json, glob, subprocess, os
confirmed = {'1703', '215', '528', '1807'}
#single findings kovarex confirmed in problems that still have others to review
confirmed_findings = {('2485', 'correct-move-fails')}
records = {}
for pattern in ('dump_check_status.*.jsonl', 'dump_check_marker.*.jsonl'):
    for f in glob.glob(pattern):
        for l in open(f):
            if l.strip():
                d = json.loads(l); records[d['set_connection_id']] = d
problems = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(os.environ.get('PROBLEMS', 'problems.jsonl'))}
def coloured(line, first='B'):
    colour, out = first, []
    for m in line.split():
        out.append(colour + ' ' + m)
        colour = 'W' if colour == 'B' else 'B'
    return ', '.join(out)
tree_says = {'correct-move-fails': 'marked correct', 'wrong-move-works': 'marked wrong', 'refutation-does-not-refute': 'refutation (black fails)',
             'missing-correct-move': 'not in tree (counts as wrong)'}
solver_says = {'correct-move-fails': 'fails', 'wrong-move-works': 'works', 'refutation-does-not-refute': 'does not refute', 'missing-correct-move': 'also works'}
rows = []
for sc, r in sorted(records.items(), key=lambda kv: int(kv[0])):
    if sc in confirmed or not r['issues'] or not r.get('unsettled', True):
        continue
    first = 'W' if 'W[' in (problems[sc]['sgf'] or '')[:0] else 'B'
    for issue in r['issues']:
        kind = issue['kind']
        if (sc, kind) in confirmed_findings:
            continue
        if kind in ('status-mismatch', 'status-almost'):
            tree, solver = issue['authored'], issue['engine']
        else:
            tree, solver = tree_says.get(kind, kind), solver_says.get(kind, issue.get('engine'))
        try:
            out = subprocess.run(['python3', 'line.py', sc, issue['line'], r['goal'], '300000'], capture_output=True, text=True, timeout=300).stdout.split('\n')[0]
            moves = out.split(':', 1)[1].replace('(status changed)', '').split()
            pv = ', '.join(m[0] + ' ' + m[1:] for m in moves[:6]) + (', … (ko fight)' if len(moves) > 6 else '')
        except Exception:
            pv = ''
        rows.append((sc, r['title'], r.get('goal', ''), coloured(issue['line']), tree, solver, pv, kind))
json.dump(rows, open('checklist.json', 'w'))
for row in rows:
    print(row)
