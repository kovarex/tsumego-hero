#writes settle/grading_<problem>.txt for every problem whose clear fixes change which moves the site accepts: the fixed
#leaves with their new S[] and reason, and the moves besogo grades differently; prints the args for the grading checks
#usage: grading_files.py <results.json> <impact.json>
import json, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
results = json.load(open(sys.argv[1]))
impact = {r['key']: r for r in json.load(open(sys.argv[2]))}

def reason(c):
    decision = [j for j in c.get('judges') or [] if j.get('verdict') == 'site_error']
    if decision:
        return decision[-1]['explanation']
    return (c.get('analysis') or {}).get('summary', '')

by_problem = {}
for c in results:
    if c['final'] == 'site_error':
        by_problem.setdefault(c['id'], []).append(c)
checks = []
for pid, cases in sorted(by_problem.items(), key=lambda kv: int(kv[0])):
    r = impact.get('all:' + pid) or (impact.get(cases[0]['case']) if len(cases) == 1 else None)
    if r is None:
        sys.exit('no grading scenario for problem %s' % pid)
    if not r['flipped']:
        continue
    lines = ['Problem %s (https://tsumego.com/%s, "%s").' % (pid, pid, cases[0]['title']), '',
             'Leaves the research found wrong (each confirmed by an analyst and the reviewers, and by judges where they '
             'disagreed), with the proposed S[]:']
    for c in sorted(cases, key=lambda c: c['case']):
        lines.append('- case %s: leaf "%s" (moves from the start): S[%s] -> S[%s]. %s' % (
            c['case'], c['line'], c['authored'], c['correct_status'], reason(c)))
    lines += ['', "What besogo (the site's own grading code, run on the tree with all these fixes applied) then changes:"]
    if r['root_before'] != r['root_after']:
        lines.append("- the problem's overall status goes from %s to %s" % (r['root_before'], r['root_after']))
    for f in r['flipped']:
        before = f['path'].rsplit(' ', 1)[0] if ' ' in f['path'] else ''
        lines.append('- move %d %s after "%s" (%s) becomes %s; it is %s now. Its status: %s before, %s after.' % (
            f['number'], f['path'].split(' ')[-1], before, "the first player's move" if f['solver'] else "the opponent's move",
            f['after'], f['before'], f['status_before'] or 'none', f['status_after'] or 'none'))
    if r['flipped_total'] > len(r['flipped']):
        lines.append('(%d nodes flip in all; the list shows the topmost ones, whose subtrees follow them.)' % r['flipped_total'])
    open(os.path.join(HERE, 'settle', 'grading_%s.txt' % pid), 'w').write('\n'.join(lines) + '\n')
    checks.append({'id': pid, 'title': cases[0]['title']})
print(json.dumps({'mode': 'grading', 'problems': checks}))
