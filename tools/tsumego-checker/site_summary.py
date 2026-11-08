#summary of site check results: verdicts, finding kinds, the problems with findings
import json, sys, glob, collections
records = list({json.loads(l)['set_connection_id']: json.loads(l) for f in sorted(glob.glob(sys.argv[1] + '.*.jsonl')) for l in open(f) if l.strip()}.values())
verdicts = collections.Counter(r['verdict'] for r in records)
kinds = collections.Counter(i['kind'] for r in records if r.get('unsettled', True) for i in r['issues'])
status = sum(1 for r in records if r.get('status_mode'))
print('problems', len(records), dict(verdicts))
print('findings', dict(kinds))
print('status problems', status); print('assumed goal', sum(1 for r in records if r.get('goal_assumed')))
times = sorted(r['seconds'] for r in records)
print('median s', round(times[len(times) // 2], 2) if times else 0, 'total s', round(sum(times)))
if len(sys.argv) > 2:
    for r in sorted(records, key=lambda r: int(r['set_connection_id'])):
        if r['issues'] and r.get('unsettled', True):
            print(r['url'], '|', r['title'], '|', r.get('goal'), '|', '; '.join('%s %s%s' % (i['kind'], i['line'], ' -> ' + i['refutation'] if i.get('refutation') else '') for i in r['issues'][:6]))
