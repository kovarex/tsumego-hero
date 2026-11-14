#scenarios for grading_impact.cjs from the research results: each proposed S[] alone (the final label, else the analysis
#label, and any other label a reviewer or judge names), and per problem all its clear errors fixed together.
#usage: PROBLEMS=problems.jsonl impact_scenarios.py <results.json> <scenarios.json> [<topics.json>]
#(a topic's options add their labels for its leaves)
import json, os, re, sys

#besogo labels this tool can write (status.js loadStatusInternalFromString)
LABEL = re.compile(r'^(DEAD|ALIVE|SEKI\+?|BENT4|10kKO|DDKO|DSKO|ADKO|ASKO|(A[+-][0-9]+)?KO([+-][0-9]*)?)$')

results = json.load(open(sys.argv[1]))
options = {case: labels for topic in (json.load(open(sys.argv[3])) if len(sys.argv) > 3 else []) for case, labels in topic.get('options', {}).items()}
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(os.environ.get('PROBLEMS', 'problems.jsonl'))}

def labels(c):
    #the final label first (else the analysis label), then the other labels named for the case, each once
    a = c.get('analysis') or {}
    named = [c.get('correct_status'), a.get('correct_status')] + [k.get('correct_status') for k in c.get('checks') or []]
    named += [j.get('correct_status') for j in c.get('judges') or []] + options.get(c['case'], [])
    out = []
    for text in named:
        text = (text or '').strip()
        if text and text not in out:
            out.append(text)
    return out

scenarios, skipped = [], []
for c in results:
    if c['final'] == 'solver_error':
        continue
    for index, label in enumerate(labels(c)):
        if not LABEL.match(label):
            skipped.append('%s: label %r is not a besogo status' % (c['case'], label))
            continue
        if label == c['authored']:
            continue
        key = c['case'] if index == 0 else '%s@%s' % (c['case'], label)
        scenarios.append({'key': key, 'sgf': records[c['id']]['sgf'], 'fixes': [{'line': c['line'], 'status': label}]})
by_problem = {}
for c in results:
    label = c.get('correct_status', ((c.get('analysis') or {}).get('correct_status') or '').strip())
    if c['final'] == 'site_error' and LABEL.match(label):
        by_problem.setdefault(c['id'], []).append({'line': c['line'], 'status': label})
for problem, fixes in by_problem.items():
    if len(fixes) > 1:
        scenarios.append({'key': 'all:' + problem, 'sgf': records[problem]['sgf'], 'fixes': fixes})
json.dump(scenarios, open(sys.argv[2], 'w'))
print(len(scenarios), 'scenarios')
for line in skipped:
    print('skipped', line)
