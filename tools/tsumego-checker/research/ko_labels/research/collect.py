#merges the analyses and reviews of the research workflows into out/results2.json, one entry per case of cases.json;
#reviews that started but have no result yet are listed as pending
#usage: collect.py <journal.jsonl>...
import json, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
cases = json.load(open(os.path.join(HERE, 'cases.json')))
by_case = {c['case']: dict(c, analysis=None, reviews=[], pending=[]) for c in cases}

def value(result):
    return json.loads(result) if isinstance(result, str) else result

for path in sys.argv[1:]:
    labels, results = {}, {}
    for line in open(path):
        record = json.loads(line)
        if record['type'] == 'started':
            labels[record['key']] = record['label']
        elif record['type'] == 'result':
            results[record['key']] = record['result']
    for key, label in labels.items():
        kind, name = label.split(':', 1)
        if key not in results:
            if kind.startswith('review-'):
                by_case[name]['pending'].append(kind[len('review-'):])
            else:
                for c in by_case.values():
                    if c['id'] == name:
                        c['pending'].append('analysis')
            continue
        result = value(results[key])
        if result is None:
            continue
        if kind == 'analyse':
            for entry in result['cases']:
                by_case[entry['case']]['analysis'] = entry
        else:
            by_case[name]['reviews'].append(dict(lens=kind[len('review-'):], **result))

#a verdict whose reviews have not started yet is pending too: two reviews for a site error, one for a solver error
expected = {'site_error': 2, 'solver_error': 1}
for c in by_case.values():
    if c['analysis'] and len(c['reviews']) + len(c['pending']) < expected.get(c['analysis']['verdict'], 0):
        c['pending'].append('not started')
out = [by_case[c['case']] for c in cases]
json.dump(out, open(os.path.join(HERE, 'out', 'results2.json'), 'w'), indent=1)
verdicts = {}
for c in out:
    verdict = c['analysis']['verdict'] if c['analysis'] else 'no analysis'
    verdicts[verdict] = verdicts.get(verdict, 0) + 1
print('cases', len(out), verdicts)
print('pending', {c['case']: c['pending'] for c in out if c['pending']})
print('not holding', {c['case']: [r['lens'] for r in c['reviews'] if not r.get('holds')] for c in out if any(not r.get('holds') for r in c['reviews'])})
