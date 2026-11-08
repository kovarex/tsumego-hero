#Fills the report template with the results of the export runs
import json, glob, collections, re, subprocess, html, os

#the folder with the run results and these scripts, and the folder the report and the questions go to
proto = os.environ.get('RESULTS', '.') + '/'
CONFIRMED = {'215', '528', '1807', '2485'} #findings kovarex confirmed (1703 has its own row)
scratch = os.environ.get('REPORT_DIR', '.') + '/'

def load(pattern):
    #one record per problem: workers from both ends of a list can meet in the middle
    records = {}
    for f in sorted(glob.glob(proto + pattern)):
        for l in open(f):
            if l.strip():
                d = json.loads(l)
                records[d['set_connection_id']] = d
    return list(records.values())

#status test
confusion = collections.Counter(); seconds = 0
status = load('dump_status.*.jsonl')
for d in status:
    seconds += d['seconds']
    for leaf in d['leaves']:
        engine = leaf['engine']
        confusion[(leaf['expected'], engine)] += 1
leaves = sum(confusion.values())
decided = sum(v for (e, g), v in confusion.items() if g != '?')
agree = sum(v for (e, g), v in confusion.items() if e == g)
status_rows = ''.join('<tr><td>%s</td><td>%s</td><td class="num">%d</td></tr>' % (e or '–', 'undecided' if g == '?' else g, v)
                      for (e, g), v in sorted(confusion.items(), key=lambda kv: -kv[1]))

#full checks
def check_row(name, records):
    verdicts = collections.Counter(r['verdict'] for r in records)
    flagged = sum(1 for r in records if r['issues'] and r.get('unsettled', True))
    not_proven = verdicts['goal-unclear'] + sum(1 for r in records if r['verdict'] in ('clean', 'issues') and not r.get('unsettled', True))
    clean = len(records) - flagged - not_proven - verdicts['timeout'] - verdicts['unsupported-board'] - verdicts['error']
    times = sorted(r['seconds'] for r in records) or [0]
    return '<tr><td>%s</td><td class="num">%d</td><td class="num">%d</td><td class="num">%d</td><td class="num">%d</td><td class="num">%d</td><td class="num">%.1f s</td></tr>' % (
        name, len(records), clean, flagged, not_proven, verdicts['timeout'], times[len(times) // 2]), flagged

status_checks = load('dump_check_status.*.jsonl')
marker_checks = load('dump_check_marker.*.jsonl')
row1, flagged_status = check_row('Status problems (all)', status_checks)
row2, flagged_marker = check_row('Marker problems stating kill/live (random 400)', marker_checks)

#review rows: every flagged problem but 1703, with the solver's line for each finding
labels = {'status-almost': ('info', 'bent four vs dead'), 'status-mismatch': ('warn', 'status differs'), 'correct-move-fails': ('bad', 'correct move fails'),
          'wrong-move-works': ('bad', 'wrong move works'), 'refutation-does-not-refute': ('bad', 'refutation fails'),
          'missing-correct-move': ('warn', 'missing correct move'), 'missing-status': ('info', 'missing status')}
rows = []
for r in sorted(status_checks + marker_checks, key=lambda r: int(r['set_connection_id'])):
    if not r['issues'] or not r.get('unsettled', True) or r['set_connection_id'] == '1703':
        continue
    for issue in r['issues'][:4]:
        cls, text = labels.get(issue['kind'], ('info', issue['kind']))
        detail = 'line %s' % issue['line']
        if issue['kind'] == 'status-mismatch':
            detail += ': authored %s, solver %s' % (issue['authored'], issue['engine'])
            try:
                out = subprocess.run(['python3', proto + 'line.py', r['set_connection_id'], issue['line'], r['goal'], '300000'],
                                     capture_output=True, text=True, timeout=300).stdout.split('\n')[0]
                moves = out.split(':', 1)[1].replace('(status changed)', '').split()
                detail += '; solver plays ' + ' '.join(m[0] + ' ' + m[1:] for m in moves[:6]) + (' … (ko fight continues)' if len(moves) > 6 else '')
            except Exception:
                pass
        elif issue.get('refutation'):
            detail += ', refuted by %s' % issue['refutation']
        elif issue.get('solver-continues'):
            detail += ', solver continues %s' % issue['solver-continues']
        state = '<span class="pill good">confirmed by you</span>' if r['set_connection_id'] in CONFIRMED else '<span class="pill info">to review</span>'
        rows.append('<tr><td><a href="%s">%s</a><br><span class="small">%s, %s</span></td><td><span class="pill %s">%s</span></td><td>%s</td><td>%s</td></tr>' % (
            r['url'], r['set_connection_id'], html.escape(r['title']), (r.get('goal') or '').lower(), cls, text, html.escape(detail), state))

questions = re.findall(r'^\d+\. (.*)$', open(scratch + 'questions-for-kovarex.md').read(), re.M)
def markdown(text):
    text = html.escape(text)
    text = re.sub(r'\*\*(.+?)\*\*', r'<b>\1</b>', text)
    text = re.sub(r'`(.+?)`', r'<code>\1</code>', text)
    return re.sub(r'(https://tsumego\.com/\d+)', r'<a href="\1">\1</a>', text)
question_items = ''.join('<li>%s</li>' % markdown(q) for q in questions)

body = open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'report_body.html')).read()
values = {
    'STATUS_RATE': '%.0f%%' % (100 * agree / max(1, decided)), 'STATUS_AGREE': str(agree), 'STATUS_DECIDED': str(decided),
    'STATUS_UNDECIDED_SHARE': '%.0f%%' % (100 * (leaves - decided) / max(1, leaves)), 'STATUS_ROWS': status_rows,
    'STATUS_PROBLEMS': str(len(status)), 'STATUS_LEAVES': str(leaves), 'STATUS_SECONDS': str(round(seconds)),
    'CHECK_FLAGGED': str(flagged_status), 'CHECK_DONE': str(len(status_checks)), 'CHECK_ROWS': row1 + row2,
    'REVIEW_ROWS': '\n'.join(rows), 'QUESTION_ITEMS': question_items,
}
for key in sorted(values, key=len, reverse=True):
    body = body.replace(key, values[key])
head = '<title>Tsumego Correctness Checker</title>\n<meta name="claude:viewport-fit-sides" content="cover">\n<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">\n'
open(scratch + 'tsumego-checker-report.html', 'w').write(head + open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'report_style.html')).read() + '\n' + body)
print('status', len(status), 'leaves', leaves, 'decided', decided, 'agree', agree, 'review rows', len(rows), 'checks', len(status_checks), len(marker_checks))
