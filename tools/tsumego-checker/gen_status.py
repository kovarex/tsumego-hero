#status-based variants of the clean trees: G[goal], S[] at every leaf from the goal, then one wrong status and one missing
import json, re, sys, os
out = sys.argv[2]; os.makedirs(out, exist_ok=True)
for line in open(sys.argv[1]):
    d = json.loads(line)
    if not d['file'].endswith('-clean.sgf') or d.get('goal') not in ('KILL', 'LIVE'):
        continue
    text = open(d['file']).read()
    good, bad = ('DEAD', 'ALIVE') if d['goal'] == 'KILL' else ('ALIVE', 'DEAD')
    branches = re.findall(r'\((;[^()]*)\)', text)
    head = text[:text.index('(;B') if '(;B' in text else text.index('(;W')]
    head = head.replace('SZ[19]', 'SZ[19]G[%s]' % d['goal'], 1)
    def leaf(branch):
        return branch.replace('C[+]', '') + ('S[%s]' % (good if 'C[+]' in branch else bad))
    statused = [leaf(b) for b in branches]
    name = os.path.basename(d['file'])[:-len('-clean.sgf')]
    open(os.path.join(out, name + '-status-clean.sgf'), 'w').write(head + ''.join('(' + b + ')' for b in statused) + ')')
    wrong = list(statused); wrong[-1] = wrong[-1].replace('S[%s]' % bad, 'S[%s]' % good)
    open(os.path.join(out, name + '-status-wrong.sgf'), 'w').write(head + ''.join('(' + b + ')' for b in wrong) + ')')
    missing = list(statused); missing[-1] = re.sub(r'S\[[A-Z]+\]', '', missing[-1])
    open(os.path.join(out, name + '-status-missing.sgf'), 'w').write(head + ''.join('(' + b + ')' for b in missing) + ')')
