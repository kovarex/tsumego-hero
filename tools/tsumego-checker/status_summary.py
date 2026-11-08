#confusion of authored against solved leaf statuses on the status-based problems
import json, sys, glob, collections
confusion = collections.Counter(); problems = 0; seconds = 0; per_problem = []
for path in sorted(glob.glob(sys.argv[1])):
    for line in open(path):
        d = json.loads(line); problems += 1; seconds += d['seconds']
        agree = 0
        for leaf in d['leaves']:
            engine = leaf['engine']
            confusion[(leaf['expected'], engine)] += 1
            agree += leaf['expected'] == engine
        per_problem.append((d['url'], agree, len(d['leaves']), d.get('timed_out', False)))
leaves = sum(confusion.values()); decided = sum(v for (e, g), v in confusion.items() if g != '?')
agreed = sum(v for (e, g), v in confusion.items() if e == g)
print('problems', problems, 'leaves', leaves, 'decided', decided, 'agree', agreed, 'rate %.0f%%' % (100 * agreed / max(1, decided)), 'seconds', round(seconds))
for key, value in sorted(confusion.items(), key=lambda kv: -kv[1]):
    print('authored %-6s solver %-6s %d' % (key[0], key[1], value))
if len(sys.argv) > 2:
    for url, agree, total, timed_out in per_problem:
        if agree < total:
            print(url, agree, '/', total, 'timeout' if timed_out else '')
