#the leaves where the solver and the tree disagree, one case each: the status class differs (ALIVE / DEAD / KO / BENT4),
#or both say a plain ko and the signs differ. usage: make_cases.py '<status results glob>' <problems.jsonl> <cases.json>
import collections, glob, json, os, re, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from check_tree import parse_sgf, build

DECIDED = ('ALIVE', 'DEAD', 'KO', 'BENT4')
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(sys.argv[2])}
cases = []
for path in sorted(glob.glob(sys.argv[1])):
    for l in open(path):
        result = json.loads(l)
        for leaf in result['leaves']:
            engine, expected, authored = leaf['engine'], leaf['expected'], leaf['authored'].strip()
            kind = None
            if engine in DECIDED and expected in DECIDED and engine != expected:
                kind = 'status'
            elif engine == 'KO' and expected == 'KO' and re.fullmatch(r'KO[+-]\d*', authored) and leaf.get('sign') and leaf['sign'] != authored:
                kind = 'ko'
            if kind:
                record = records[result['set_connection_id']]
                solver_color = build(parse_sgf(record['sgf']))[1]
                cases.append({'id': result['set_connection_id'], 'url': 'https://tsumego.com/' + result['set_connection_id'],
                              'title': record['title'], 'description': record['description'], 'goal': result['goal'],
                              'first': solver_color, 'line': leaf['line'], 'authored': authored, 'authored_class': expected,
                              'solver': engine, 'solver_sign': leaf.get('sign'), 'kind': kind})
cases.sort(key=lambda c: (int(c['id']), c['line']))
for number, c in enumerate(cases):
    c['case'] = 'c%03d' % number
json.dump(cases, open(sys.argv[3], 'w'), indent=1)
print(len(cases), 'cases', dict(collections.Counter(c['kind'] for c in cases)), 'in', len({c['id'] for c in cases}), 'problems')
