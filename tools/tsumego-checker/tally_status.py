#agreement of the solver with the authored S[] leaves: usage tally_status.py '<glob>'
import json, glob, collections, re, sys
def tally(pattern):
    c = collections.Counter(); probs = collections.Counter(); dis = collections.Counter(); ks = collections.Counter(); leaves = {}
    for f in glob.glob(pattern):
        for l in open(f):
            r = json.loads(l); bad = False; probs['problems'] += 1; probs['timeouts'] += bool(r.get('timed_out')); probs['errors'] += bool(r.get('error'))
            for lf in r['leaves']:
                e = lf['engine']; x = lf['expected']; a = lf['authored'].strip()
                key = (r['set_connection_id'], lf['line'])
                if e not in ('ALIVE', 'DEAD', 'KO', 'BENT4') or x is None:
                    c['undecided/unclassified'] += 1; leaves[key] = 'undecided'; continue
                if e != x:
                    c['status_disagree'] += 1; dis[(x, e)] += 1; bad = True; leaves[key] = 'status %s/%s' % (x, e); continue
                if e == 'KO' and re.fullmatch(r'KO[+-]\d*', a):
                    if lf.get('sign') is None:
                        c['ko_sign_undecided'] += 1; leaves[key] = 'sign undecided'; continue
                    if lf['sign'] != a:
                        c['ko_sign_disagree'] += 1; ks[(a, lf['sign'])] += 1; bad = True; leaves[key] = 'sign %s/%s' % (a, lf['sign']); continue
                c['agree'] += 1; leaves[key] = 'agree'
            probs['with_disagreement'] += bad
    return c, probs, dis, ks, leaves
if __name__ == '__main__':
    c, probs, dis, ks, leaves = tally(sys.argv[1])
    print(dict(c), dict(probs)); print(dis.most_common()); print(ks.most_common())
