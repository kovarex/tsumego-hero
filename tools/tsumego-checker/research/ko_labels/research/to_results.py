#turns out/results2.json (and out/judges.json when it exists) into the research results format that report.py and
#impact_scenarios.py read: final verdict, correct_status, analysis, checks (the reviews), judges; definition cases get
#their question and options ([answer, S[]] pairs) in out/options.json
#usage: to_results.py <out results.json>
import glob, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
STATUS = re.compile(r'(?<![\w+-])(DEAD|ALIVE|SEKI\+?|BENT4|10kKO|DDKO|DSKO|ADKO|ASKO|(?:A[+-][0-9]+)?KO(?:[+-][0-9]*)?)(?![\w+-])')
cases = json.load(open(os.path.join(HERE, 'out', 'results2.json')))
#the third round (round3_collect.py), in the same format
round3_path = os.path.join(HERE, 'out', 'results2_round3.json')
cases += json.load(open(round3_path)) if os.path.exists(round3_path) else []
#the fourth round (round4_collect.py), in the same format
round4_path = os.path.join(HERE, 'out', 'results2_round4.json')
cases += json.load(open(round4_path)) if os.path.exists(round4_path) else []
judges_path = os.path.join(HERE, 'out', 'judges.json')
judges = json.load(open(judges_path)) if os.path.exists(judges_path) else {}
#grading checks per problem (out/grading_<problem>.json: {id, grading, reviews}), kept on each fixed leaf
gradings = {}
for path in glob.glob(os.path.join(HERE, 'out', 'grading_*.json')):
    g = json.load(open(path))
    gradings[g['id']] = g

def statuses(text):
    return [m.group(1) for m in STATUS.finditer(text or '')]

#the owner's answers to open questions: {question: {answer, cases: {case: [final, correct_status]}}}
answers_path = os.path.join(HERE, 'out', 'answers.json')
answered = {case: (q, a['answer'], final, status) for q, a in (json.load(open(answers_path)) if os.path.exists(answers_path) else {}).items()
            for case, (final, status) in a['cases'].items()}
out, options = [], {}
for c in cases:
    a = c['analysis']
    entry = {key: c[key] for key in ('case', 'id', 'title', 'line', 'authored', 'label', 'group', 'research_case', 'research_status')}
    entry['analysis'] = a
    entry['checks'] = c['reviews']
    entry['pending'] = c['pending']
    entry['judges'] = judges.get(c['case'], {}).get('judges', [])
    decision = judges.get(c['case'], {}).get('decision')
    entry['final'] = decision['verdict'] if decision else a['verdict']
    entry['correct_status'] = (decision['correct_status'] if decision else a['correct_status']).strip()
    entry['facts'] = judges.get(c['case'], {}).get('facts')
    entry['split'] = judges.get(c['case'], {}).get('split', False)
    if c['case'] in answered:
        q, answer, final, status = answered[c['case']]
        entry['answered'] = {'question': q, 'answer': answer, 'before': entry['final']}
        entry['final'], entry['correct_status'] = final, status
    if entry['final'] == 'definition':
        entry['tree_right_under_some_answer'] = decision.get('tree_right_under_some_answer') if decision else None
        #the judges' or fact checker's answers with their S[], else every status the texts name
        if decision and decision.get('option_list'):
            options[c['case']] = {'question': decision['question'], 'options': decision['option_list']}
        else:
            named = statuses(entry['correct_status']) + statuses(a['correct_status']) + statuses(a['depends_on'])
            for r in c['reviews'] + entry['judges']:
                named += statuses(r.get('correct_status'))
            seen = []
            for status in named:
                if status not in seen:
                    seen.append(status)
            options[c['case']] = {'question': a.get('depends_on', ''), 'options': [['', status] for status in seen]}
    #a grading check stays with its leaf when the leaf became a decision (11332's k029 on 16 November)
    if entry['final'] in ('site_error', 'definition') and c['id'] in gradings:
        entry['grading_check'] = gradings[c['id']]['grading']
        entry['grading_reviews'] = gradings[c['id']].get('reviews', [])
    out.append(entry)
missing = sorted(set(gradings) - {e['id'] for e in out if e.get('grading_check')})
if missing:
    sys.exit('grading checks for problems without a fixed leaf: %s' % ', '.join(missing))
json.dump(out, open(sys.argv[1], 'w'), indent=1)
json.dump(options, open(os.path.join(HERE, 'out', 'options.json'), 'w'), indent=1)
counts = {}
for e in out:
    counts[e['final']] = counts.get(e['final'], 0) + 1
print(counts)
for case, o in options.items():
    print(case, o['question'][:50], [status for _, status in o['options']])
