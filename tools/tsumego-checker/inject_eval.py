#scores check_tree.py on the generated trees: clean ones should have no issues, each mutation its own kind
import json, sys, collections
expected = {'status-clean': None, 'status-wrong': 'status-mismatch', 'status-missing': 'missing-status', 'clean': None, 'wrong-marked-correct': 'correct-move-fails', 'correct-missing': 'wrong-move-works', 'weak-refutation': 'refutation-does-not-refute'}
score = collections.defaultdict(lambda: collections.Counter())
for line in open(sys.argv[1]):
    d = json.loads(line)
    name = d['file'].split('/')[-1][:-4]
    kind = name.split('-', 1)[1]
    kinds = [i['kind'] for i in d['issues']]
    if d['verdict'] == 'goal-unclear':
        score[kind]['goal-unclear'] += 1
    elif expected[kind] is None:
        score[kind]['clean' if not kinds else 'false-alarm'] += 1
        if kinds: print('false alarm', name, d['issues'])
    else:
        score[kind]['caught' if expected[kind] in kinds else 'missed'] += 1
        extra = [k for k in kinds if k != expected[kind]]
        if extra: score[kind]['extra-issues'] += 1; print('extra', name, d['issues'])
    score[kind]['seconds'] += d['seconds']
for kind, c in score.items(): print(kind, dict(c))
