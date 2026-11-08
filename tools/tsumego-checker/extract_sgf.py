#the problem's current SGF from saved tsumego.com pages: extract_sgf.py <page dir> <ids...> > records.jsonl
import json, sys, os
def sgf_of(page):
    #the page carries the SGF as a JSON string value: "sgf":"(;..."
    if '"sgf":"' in page:
        return json.JSONDecoder().raw_decode(page, page.index('"sgf":"') + 6)[0]
    start = page.index('(;')
    depth, i, inside = 0, start, False
    while True:
        c = page[i]
        if inside:
            if c == '\\': i += 1
            elif c == ']': inside = False
        elif c == '[': inside = True
        elif c == '(': depth += 1
        elif c == ')':
            depth -= 1
            if depth == 0:
                return page[start:i + 1]
        i += 1
records = {json.loads(l)['set_connection_id']: json.loads(l) for l in open(os.environ.get('PROBLEMS', 'problems.jsonl'))}
for sc in sys.argv[2:]:
    record = records[sc]
    record['sgf'] = sgf_of(open('%s/%s.html' % (sys.argv[1], sc)).read())
    print(json.dumps(record))
