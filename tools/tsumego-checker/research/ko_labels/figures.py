#copies numbered diagrams out of a rendered page (the question page or the research report) into standalone SVG files,
#with the board styles inlined in the light theme, so a markdown document can show them as images
#usage: figures.py <page.html> <out dir> <figure>... where a figure is an element id (q1a-d1) or <section id>:<n>,
#the n-th diagram (from 1) inside the element with that id (topic-target-15678:2); writes <out dir>/<name>.svg, the name
#being the id with ':' replaced by '-'
import os, re, sys

STYLE = '''<style>
.wood { fill: #dcb97f; } .label-ground { fill: #dcb97f; }
.grid { stroke: #5e4728; stroke-width: 1; } .grid.edge { stroke-width: 2; }
.coord { font: 10px ui-monospace, Menlo, Consolas, monospace; fill: #5e4728; text-anchor: middle; }
.black { fill: #16191c; stroke: #16191c; } .white { fill: #f5f5f1; stroke: #3b3a35; stroke-width: 1; }
.number, .label { font: 600 11px ui-monospace, Menlo, Consolas, monospace; text-anchor: middle; }
.on-black { fill: #f5f5f1; } .on-white { fill: #16191c; } .on-wood { fill: #16191c; font-style: italic; }
.ghost { opacity: 0.35; stroke-dasharray: 3 2; } .ghost-number { fill: #5e4728; }
.ko { fill: none; stroke: #2a5d6f; stroke-width: 2; }
.frame-line { stroke: #8a3b23; stroke-width: 1.5; stroke-dasharray: 4 3; stroke-linecap: round; fill: none; }
</style>'''

def element(text, ident):
    #the element with this id, up to its matching closing tag
    start = text.find('id="%s"' % ident)
    if start < 0:
        sys.exit('no element with id %s' % ident)
    start = text.rfind('<', 0, start)
    tag = re.match(r'<(\w+)', text[start:]).group(1)
    depth = 0
    for match in re.compile(r'<(/?)%s\b[^>]*>' % tag).finditer(text, start):
        depth += -1 if match.group(1) else 1
        if depth == 0:
            return text[start:match.end()]
    sys.exit('element %s is not closed' % ident)

def standalone(svg):
    svg = re.sub(r'<svg class="board"', '<svg xmlns="http://www.w3.org/2000/svg" class="board"', svg, count=1)
    return re.sub(r'(<svg[^>]*>)', lambda m: m.group(1) + STYLE, svg, count=1)

page, out = sys.argv[1], sys.argv[2]
text = open(page).read()
os.makedirs(out, exist_ok=True)
for name in sys.argv[3:]:
    if ':' in name:
        ident, number = name.split(':')
        svgs = re.findall(r'<svg class="board".*?</svg>', element(text, ident), re.S)
        if not 1 <= int(number) <= len(svgs):
            sys.exit('%s has %d diagrams, not %s' % (ident, len(svgs), number))
        svg = svgs[int(number) - 1]
    else:
        svgs = re.findall(r'<svg class="board".*?</svg>', element(text, name), re.S)
        if len(svgs) != 1:
            sys.exit('%s holds %d diagrams' % (name, len(svgs)))
        svg = svgs[0]
    path = os.path.join(out, name.replace(':', '-') + '.svg')
    open(path, 'w').write(standalone(svg) + '\n')
    print(path)
