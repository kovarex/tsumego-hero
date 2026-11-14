#folds the settle runs into the research results: two judges per disputed case (their shared verdict, else unclear)
#and a fact check per definition question (a definition stays one only if the checker confirms the facts).
#Each case gets correct_status, the label proposed in the end.
#usage: merge_settled.py <results.json> <settled.json>... > merged.json
import json, sys

results = json.load(open(sys.argv[1]))
by_case = {c['case']: c for c in results}
for c in results:
    c['correct_status'] = ((c.get('analysis') or {}).get('correct_status') or '').strip()
for path in sys.argv[2:]:
    for settled in json.load(open(path)):
        c = by_case[settled['case']]
        if 'judges' in settled:
            judges = settled['judges']
            c['judges'] = judges
            c['final_before_judges'] = c['final']
            c['final'] = settled['final'] if settled['final'] in ('site_error', 'solver_error', 'definition') else 'unclear'
            labels = sorted({j['correct_status'].strip() for j in judges if j.get('correct_status', '').strip()})
            if c['final'] == 'site_error':
                if len(labels) != 1:
                    sys.exit('%s: judges agree the tree is wrong but name %s' % (c['case'], labels or 'no label'))
                c['correct_status'] = labels[0]
            elif c['final'] == 'solver_error':
                c['correct_status'] = c['authored']
            else:
                c['correct_status'] = ' / '.join(labels)
        elif 'facts' in settled:
            facts = settled['facts']
            c['facts'] = facts
            if c['final'] != 'definition':
                sys.exit('%s: fact check for a case that is %s' % (c['case'], c['final']))
            if not (facts['facts_hold'] and facts['verdict'] == 'definition'):
                c['final'] = 'unclear'
        else:
            sys.exit('%s: neither judges nor facts in %s' % (settled['case'], path))
json.dump(results, sys.stdout, indent=1)
