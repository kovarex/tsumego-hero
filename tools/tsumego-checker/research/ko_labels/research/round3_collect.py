#the third research round (16 November: the pass-4 leaves and 13462) in the results2 format that to_results.py reads:
#out/results2_round3.json; usage: round3_collect.py <round-3 journal.jsonl>...
import json, os, sys
HERE = os.path.dirname(os.path.abspath(__file__))
R3 = '/tmp/claude-0/proto/research/kores3'
meta = {c['case']: c for path in ('cases.json', 'cases-13462.json') for c in json.load(open(os.path.join(R3, path)))}
analyses, reviews = {}, {}
for path in sys.argv[1:]:
    labels = {}
    for line in open(path):
        record = json.loads(line)
        if record['type'] == 'started':
            labels[record['key']] = record['label']
        elif record['type'] == 'result':
            result = record['result']
            result = json.loads(result) if isinstance(result, str) else result
            label = labels.get(record['key'], '')
            if label.startswith('analyse:'):
                for c in result['cases']:
                    analyses[c['case']] = c
            elif label.startswith('review-'):
                lens, case = label[len('review-'):].split(':')
                reviews.setdefault(case, []).append(dict(result, lens=lens))
out = []
for case in sorted(meta):
    if case not in analyses:
        sys.exit('no analysis for %s' % case)
    m = meta[case]
    out.append({'case': case, 'id': m['id'], 'title': m['title'], 'line': m['line'], 'authored': m['authored'], 'label': m['label'],
                'group': m['group'], 'research_case': None, 'research_status': None, 'analysis': analyses[case],
                'reviews': reviews.get(case, []), 'pending': [], 'round': 3})
json.dump(out, open(os.path.join(HERE, 'out', 'results2_round3.json'), 'w'), indent=1)
for e in out:
    print(e['case'], e['id'], e['analysis']['verdict'], e['analysis']['correct_status'][:60], [(r['lens'], r['holds'], r['correct_status']) for r in e['reviews']])
