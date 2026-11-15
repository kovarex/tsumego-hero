#merges the judge and fact-checker results of the settle workflows into out/judges.json, which to_results.py reads:
#{case: {judges: [verdict + role], facts: verdict or null, decision: verdict + source, split: bool}};
#a third judge decides where two disagree, a fact checker's own classification decides a definition case
#usage: settle_merge.py <settle journal.jsonl>...   (the cases and judge counts come from out/args_judge*.json)
import glob, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
STATUS = re.compile(r'(?<![\w+-])(DEAD|ALIVE|SEKI\+?|BENT4|10kKO|DDKO|DSKO|ADKO|ASKO|(?:A[+-][0-9]+)?KO(?:[+-][0-9]*)?)(?![\w+-])')

def value(result):
    return json.loads(result) if isinstance(result, str) else result

def option_list(options):
    #"answer: S[...] | answer: S[...]" -> [(answer, status)]; the status is the last S[...] of an item, else the first
    #status after its last colon (answers may name other leaves' labels in parentheses)
    out = []
    for item in (options or '').split(' | '):
        item = item.strip()
        if not item:
            continue
        wrapped = re.findall(r'S\[([^\]]+)\]', item)
        if wrapped:
            status = wrapped[-1].strip()
            answer = item[:item.rfind('S[' + wrapped[-1])].rstrip(' :')
        else:
            answer, _, tail = item.rpartition(':')
            found = STATUS.findall(tail)
            if not found:
                raise SystemExit('no status in option %r' % item)
            status = found[0]
        out.append([answer.strip(), status])
    return out

def differ(a, b):
    #the settle workflow's rule for calling a third judge
    return a['verdict'] != b['verdict'] or (a['verdict'] != 'definition' and a['correct_status'].strip() != b['correct_status'].strip()) or \
        (a['verdict'] == 'definition' and a['question'].strip() != b['question'].strip())

def same(a, b):
    if a['verdict'] != b['verdict']:
        return False
    if a['verdict'] == 'definition':
        return sorted(s for _, s in option_list(a['options'])) == sorted(s for _, s in option_list(b['options']))
    return a['correct_status'].strip() == b['correct_status'].strip()

merged = {}
for path in sys.argv[1:]:
    labels, results = {}, {}
    for line in open(path):
        record = json.loads(line)
        if record['type'] == 'started':
            labels[record['key']] = record['label']
        elif record['type'] == 'result':
            results[record['key']] = record['result']
    for key, label in labels.items():
        role, case = label.split(':', 1)
        entry = merged.setdefault(case, {'judges': [], 'facts': None, 'running': []})
        if key not in results:
            entry['running'].append(role)
            continue
        result = value(results[key])
        if result is None:
            entry['running'].append(role + ' (no result)')
        elif role == 'facts':
            entry['facts'] = result
        else:
            entry['judges'].append(dict(result, role=role))

expected = {}
for path in glob.glob(os.path.join(HERE, 'out', 'args_judge*.json')):
    for c in json.load(open(path))['cases']:
        expected[c['case']] = c['judges']
for path in glob.glob(os.path.join(HERE, 'out', 'args_*.json')):
    for c in json.load(open(path))['cases']:
        merged.setdefault(c['case'], {'judges': [], 'facts': None, 'running': ['not started']})

for case, entry in sorted(merged.items()):
    judges = {j['role']: j for j in entry['judges']}
    decision, split = None, False
    if case in expected and len([r for r in judges if r != 'judge-c']) + len([r for r in entry['running'] if r != 'judge-c']) < expected[case]:
        entry['running'].append('judge not started')
    if 'judge-a' in judges and 'judge-b' in judges and 'judge-c' not in judges and differ(judges['judge-a'], judges['judge-b']) \
            and 'judge-c' not in entry['running']:
        entry['running'].append('judge-c not started')
    if 'judge-c' in judges:
        decision = dict(judges['judge-c'], source='judge-c')
        #the third judge sided with neither earlier judge
        split = not any(same(judges['judge-c'], judges[r]) for r in ('judge-a', 'judge-b') if r in judges)
    elif judges and not entry['running']:
        first = judges.get('judge-a') or judges.get('judge-b')
        decision = dict(first, source=first['role'] if len(judges) == 1 else 'judges agree')
    elif entry['facts'] and not entry['running']:
        decision = dict(entry['facts'], source='facts')
    if decision:
        decision['option_list'] = option_list(decision['options']) if decision['verdict'] == 'definition' else []
    entry['decision'] = decision
    entry['split'] = split

#the leaves classified by hand where the reviews and finished judges agree that the label turns on an open question
#(the remaining judges were stopped: they could only confirm the question, which only the owner's answer settles)
manual_path = os.path.join(HERE, 'out', 'manual_decisions.json')
for case, m in (json.load(open(manual_path)) if os.path.exists(manual_path) else {}).items():
    entry = merged.setdefault(case, {'judges': [], 'facts': None, 'running': [], 'decision': None, 'split': False})
    if entry.get('decision'):
        continue
    entry['decision'] = dict(verdict='definition', correct_status='', confidence='high', source='reviews and judges, classified by hand',
                             option_list=option_list(m['options']), **m)
    entry['running'] = []
json.dump(merged, open(os.path.join(HERE, 'out', 'judges.json'), 'w'), indent=1)
for case, entry in sorted(merged.items()):
    d = entry['decision']
    if not d:
        print(case, 'waiting for', entry['running'])
        continue
    facts = entry['facts']
    print(case, d['source'], d['verdict'], d['correct_status'] or d['question'][:60], d['option_list'] or '',
          'SPLIT' if entry['split'] else '', '' if not facts else 'facts hold' if facts['facts_hold'] else 'FACTS CORRECTED')
