#go figures as inline SVG: a position, numbered moves on top of it, captures applied
import html
LETTERS = 'ABCDEFGHJKLMNOPQRST'

def figure(board, moves, frame, caption):
    #board: position before the moves; drawn as it stands after them, captured stones as faint outlines
    x1, x2, y1, y2 = frame
    cell, margin = 26, 30
    width, height = margin * 2 + cell * (x2 - x1), margin * 2 + cell * (y2 - y1)
    size = board.size
    name = lambda p: LETTERS[p[0]] + str(size - p[1])
    out = ['<svg class="board" viewBox="0 0 %d %d" role="img" aria-label="%s">' % (width, height, html.escape(caption))]
    out.append('<rect class="wood" x="0" y="0" width="%d" height="%d" rx="4"/>' % (width, height))
    px = lambda x: margin + (x - x1) * cell
    py = lambda y: margin + (y - y1) * cell
    for x in range(x1, x2 + 1):
        top = py(y1) if y1 == 0 else py(y1) - cell / 2
        bottom = py(y2) if y2 == size - 1 else py(y2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if x in (0, size - 1) else '', px(x), top, px(x), bottom))
        out.append('<text class="coord" x="%d" y="%d">%s</text>' % (px(x), height - 8, LETTERS[x]))
    for y in range(y1, y2 + 1):
        left = px(x1) if x1 == 0 else px(x1) - cell / 2
        right = px(x2) if x2 == size - 1 else px(x2) + cell / 2
        out.append('<line class="grid%s" x1="%d" y1="%d" x2="%d" y2="%d"/>' % (' edge' if y in (0, size - 1) else '', left, py(y), right, py(y)))
        out.append('<text class="coord" x="%d" y="%d">%d</text>' % (11, py(y) + 4, size - y))
    current = board.copy()
    numbers = {} #point -> number of the move whose stone stands there
    ghosts = {} #captured point -> (colour, number or None)
    notes = []
    for number, move in enumerate(moves, 1):
        colour, p = move[0], move[1]
        if len(move) > 2 and move[2]:
            notes.append('%d %s' % (number, move[2]))
        if p is None:
            notes.append('%d plays elsewhere (pass or a ko threat)' % number)
            current.play(colour, None) #a pass lifts the ko ban
            continue
        before = dict(current.stones)
        current.play(colour, p, ignore_ko=len(move) > 2 and bool(move[2]))
        if p in ghosts:
            if ghosts[p][1]:
                notes.append('%d at %s was captured, %d is played there again' % (ghosts[p][1], name(p), number))
            del ghosts[p]
        numbers[p] = number
        captured = [q for q in before if q not in current.stones]
        for q in captured:
            ghosts[q] = (before[q], numbers.pop(q, None))
        if captured:
            notes.append('%d captures %s' % (number, ' '.join(name(q) for q in sorted(captured))))
    inside = lambda q: x1 <= q[0] <= x2 and y1 <= q[1] <= y2
    for q, (colour, number) in ghosts.items():
        if inside(q):
            out.append('<circle class="ghost %s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(q[0]), py(q[1])))
            if number:
                out.append('<text class="number ghost-number" x="%d" y="%d">%d</text>' % (px(q[0]), py(q[1]) + 4, number))
    for q, colour in current.stones.items():
        if inside(q):
            out.append('<circle class="%s" cx="%d" cy="%d" r="11.5"/>' % ('black' if colour == 'B' else 'white', px(q[0]), py(q[1])))
            if q in numbers:
                out.append('<text class="number %s" x="%d" y="%d">%d</text>' % ('on-black' if colour == 'B' else 'on-white', px(q[0]), py(q[1]) + 4, numbers[q]))
    out.append('</svg>')
    note = ('<div class="notes">%s</div>' % html.escape('; '.join(notes))) if notes else ''
    return '<figure>%s<figcaption>%s</figcaption>%s</figure>' % (''.join(out), html.escape(caption), note)

first_name = lambda moves: 'black first' if not moves or moves[0][0] == 'B' else 'white first'
