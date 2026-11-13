#evidence for every case (evidence.py output, read first by the analysts): <evidence dir>/<case>.txt
#usage: PROBLEMS=status_problems.jsonl precompute.py <cases.json> <evidence dir> <worker> <workers>
import json, os, subprocess, sys
here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
cases_path, folder, worker, workers = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])
problems = os.environ.get('PROBLEMS', 'problems.jsonl')
os.makedirs(folder, exist_ok=True)
for number, c in enumerate(json.load(open(cases_path))):
    if number % workers != worker:
        continue
    out = os.path.join(folder, '%s.txt' % c['case'])
    if os.path.exists(out) and os.path.getsize(out) > 0:
        continue
    flags = ['--tree', '--moves', '--pv']
    if c['kind'] == 'ko' or 'KO' in (c['authored_class'], c['solver']):
        flags.append('--ko')
    try:
        result = subprocess.run(['python3', os.path.join(here, 'evidence.py'), c['id'], c['line'], '--problems=' + problems] + flags,
                                capture_output=True, text=True, timeout=1500)
        text = result.stdout + (('\nERRORS:\n' + result.stderr[-2000:]) if result.returncode else '')
    except subprocess.TimeoutExpired:
        text = 'evidence timed out after 1500 s'
    header = 'Case %s: problem %s, leaf %s\nTree S[%s] (class %s), solver %s%s\n' % (
        c['case'], c['id'], c['line'], c['authored'], c['authored_class'], c['solver'], (' sign ' + c['solver_sign']) if c.get('solver_sign') else '')
    open(out, 'w').write(header + '\n' + text)
    print(c['case'], flush=True)
