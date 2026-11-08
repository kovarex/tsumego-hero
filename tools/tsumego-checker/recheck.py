#runs the checker on single problems of the export and prints the findings
import json, sys, time, os
from check_tree import *
import check_tree
wanted = set(sys.argv[2:])
engine = Engine()
for line in open(os.environ.get('PROBLEMS', 'problems.jsonl')):
    record = json.loads(line)
    if record['set_connection_id'] not in wanted:
        continue
    Solver.deadline = time.time() + 90
    try:
        report = check(record['sgf'], engine, int(sys.argv[1]), description=record['description'], missing_depth=2)
    except TimeoutError:
        report = {'verdict': 'timeout', 'issues': []}
    print('https://tsumego.com/' + record['set_connection_id'], record['title'], '|', report.get('verdict'), report.get('goal'), 'unsettled', report.get('unsettled'), round(report.get('seconds', 0), 1))
    for issue in report['issues']:
        print('   ', issue)
