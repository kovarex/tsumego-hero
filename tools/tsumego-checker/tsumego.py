#SGF parsing, go rules and the bridge to the exact solver engine
import re, subprocess, os, time

class Node:
    def __init__(self, parent=None):
        self.properties = {}
        self.children = []
        self.parent = parent

    def get(self, key, default=None):
        values = self.properties.get(key)
        return values[0] if values else default

    def move(self):
        for color in ('B', 'W'):
            if color in self.properties:
                return color, self.properties[color][0]
        return None

def parse_sgf(text):
    #returns the root node of the first game tree
    position = 0
    root = None
    stack = []
    current = None
    property_pattern = re.compile(r'\s*([A-Za-z]+)\s*((?:\[(?:\\.|[^\]\\])*\]\s*)+)', re.S)
    value_pattern = re.compile(r'\[((?:\\.|[^\]\\])*)\]', re.S)
    while position < len(text):
        character = text[position]
        if character == '(':
            stack.append(current)
            position += 1
        elif character == ')':
            current = stack.pop()
            position += 1
            if not stack:
                break
        elif character == ';':
            node = Node(current)
            if current is None:
                root = node
            else:
                current.children.append(node)
            current = node
            position += 1
            while True:
                match = property_pattern.match(text, position)
                if not match:
                    break
                key = match.group(1)
                values = [re.sub(r'\\(.)', r'\1', value) for value in value_pattern.findall(match.group(2))]
                current.properties.setdefault(key, []).extend(values)
                position = match.end()
        else:
            position += 1
    return root

def point(text, size):
    if not text or text == 'tt' and size <= 19:
        return None
    return (ord(text[0]) - 97, ord(text[1]) - 97)

def expand_points(values, size):
    #compressed point lists like aa:cc
    result = []
    for value in values:
        if ':' in value:
            a, b = value.split(':')
            (x1, y1), (x2, y2) = point(a, size), point(b, size)
            for x in range(min(x1, x2), max(x1, x2) + 1):
                for y in range(min(y1, y2), max(y1, y2) + 1):
                    result.append((x, y))
        else:
            p = point(value, size)
            if p:
                result.append(p)
    return result

class Board:
    def __init__(self, size):
        self.size = size
        self.stones = {}
        self.ko = None

    def copy(self):
        board = Board(self.size)
        board.stones = dict(self.stones)
        board.ko = self.ko
        return board

    def neighbours(self, p):
        x, y = p
        for q in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
            if 0 <= q[0] < self.size and 0 <= q[1] < self.size:
                yield q

    def chain(self, p):
        color = self.stones[p]
        stones, liberties, todo = {p}, set(), [p]
        while todo:
            q = todo.pop()
            for r in self.neighbours(q):
                if r not in self.stones:
                    liberties.add(r)
                elif self.stones[r] == color and r not in stones:
                    stones.add(r)
                    todo.append(r)
        return stones, liberties

    def play(self, color, p, ignore_ko=False):
        #returns False on an illegal move; ignore_ko: a retake after a ko threat
        if p is None:
            self.ko = None
            return True
        if p in self.stones or (p == self.ko and not ignore_ko):
            return False #occupied, or retaking a ko at once
        opponent = 'W' if color == 'B' else 'B'
        self.stones[p] = color
        captured = []
        for q in self.neighbours(p):
            if self.stones.get(q) == opponent and q not in captured:
                stones, liberties = self.chain(q)
                if not liberties:
                    captured.extend(stones)
        for q in captured:
            del self.stones[q]
        own, liberties = self.chain(p)
        if not liberties:
            del self.stones[p]
            for q in captured:
                self.stones[q] = opponent
            return False
        self.ko = captured[0] if len(captured) == 1 and len(own) == 1 and len(liberties) == 1 else None
        return True

class Engine:
    def __init__(self, path=os.path.join(os.path.dirname(os.path.abspath(__file__)), 'engine')):
        path = os.path.abspath(path)
        self.process = subprocess.Popen([path], stdin=subprocess.PIPE, stdout=subprocess.PIPE, text=True, bufsize=1)

    def command(self, line):
        self.process.stdin.write(line + '\n')
        self.process.stdin.flush()
        return self.process.stdout.readline().strip()

class Problem:
    #a position with the region of interest and the formulation (attacker, target) the engine works with
    def __init__(self, board, region):
        self.board = board
        self.size = board.size
        #a point with a stone in the start position is playable once the stone is captured
        self.region = set(region) | {p for p in board.stones if any(q in region for q in board.neighbours(p)) or p in region}
        self.fringe = set()
        if DYNAMIC:
            self.fringe = {q for p in self.region for q in board.neighbours(p) if q not in self.region and q not in board.stones}

    def board_text(self, board):
        characters = []
        for y in range(self.size):
            for x in range(self.size):
                p = (x, y)
                color = board.stones.get(p)
                if color:
                    characters.append('X' if color == 'B' else 'O')
                else:
                    characters.append('.' if p in self.region else 'f' if p in self.fringe else '#')
        return ''.join(characters)

    def index(self, p):
        return p[1] * self.size + p[0]

    def from_index(self, i):
        return None if i < 0 else (i % self.size, i // self.size)

def region_of(size, points, margin=1):
    xs = [p[0] for p in points]
    ys = [p[1] for p in points]
    x1, x2 = max(0, min(xs) - margin), min(size - 1, max(xs) + margin)
    y1, y2 = max(0, min(ys) - margin), min(size - 1, max(ys) + margin)
    return {(x, y) for x in range(x1, x2 + 1) for y in range(y1, y2 + 1)}

def chains_of(board, color, region):
    seen, result = set(), []
    for p, c in board.stones.items():
        if c == color and p not in seen:
            stones, liberties = board.chain(p)
            seen |= stones
            result.append(stones)
    return sorted(result, key=len, reverse=True)

#dynamic region: the empty points around the region are a fringe, playable once a chain next to them is short of
#liberties (they count as liberties all the time)
DYNAMIC = os.environ.get("DYNAMIC", "1") == "1"
WEAK_LIBERTIES = int(os.environ.get("WEAK_LIBERTIES", "4"))
SMALL_CHAIN = int(os.environ.get("SMALL_CHAIN", "1"))
ENCLOSED_LIMIT = int(os.environ.get("ENCLOSED_LIMIT", "30"))
DISTANCE = int(os.environ.get("REGION_DISTANCE", "1"))
STATUS_DEAD, STATUS_KO, STATUS_ALIVE, STATUS_UNKNOWN = 'DEAD', 'KO', 'ALIVE', '?'
STATUS_BENT4 = 'BENT4'

def other_colour(color):
    return 'W' if color == 'B' else 'B'

class Solver:
    #one formulation: attacker color + target stones
    deadline = None #time after which status() gives up, per problem
    wide = None #the same formulation over a wider region, asked again when the answer is a ko
    def __init__(self, engine, problem, attacker, target, budget):
        self.engine = engine
        self.problem = problem
        self.attacker = attacker
        self.target = sorted(target)
        self.budget = budget
        self.nodes = 0
        self.cache = {}
        self.activate()

    def activate_engine(self):
        #the engine holds one problem at a time; another solver may have loaded its own since
        if getattr(self.engine, 'active', None) is not self:
            self.activate()

    def activate(self):
        indices = ','.join(str(self.problem.index(p)) for p in self.target)
        self.engine.command('problem %d %s %s %s' % (self.problem.size, self.attacker, self.problem.board_text(self.problem.board), indices))
        self.engine.active = self

    def status(self, board, to_move):
        #DEAD / KO / ALIVE of the target with to_move to play, plus the best moves found
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        key = (text, ko, to_move)
        if key in self.cache:
            return self.cache[key]
        if Solver.deadline and time.time() > Solver.deadline:
            raise TimeoutError('problem time limit')
        self.activate_engine()
        answer = self.engine.command('solve %s %d %d %s' % (to_move, ko, self.budget, text))
        if os.environ.get('TRACE'):
            print(answer)
        fields = dict(item.split('=') for item in answer.split()[1:])
        self.nodes += int(fields['nodes'])
        a, d = fields['A'], fields['D']
        if a == 'K':
            status = STATUS_DEAD
        elif a == 'N' and d == 'K':
            status = STATUS_KO
        elif a == 'N' and d == 'N':
            status = STATUS_ALIVE
        else:
            status = STATUS_UNKNOWN
            if a == 'U' and d == 'N':
                status = STATUS_ALIVE
        best = self.problem.from_index(int(fields['bestA'] if a == 'K' or d != 'K' else fields['bestD']))
        result = (status, best)
        if status == STATUS_KO and self.wide is not None:
            #a ko next to the region's edge can come from the wall taking a liberty; the wider region decides
            wide = self.wide.status(board, to_move)
            if wide[0] != STATUS_UNKNOWN:
                result = wide
        solver_color = getattr(self, 'solver_color', None)
        success = STATUS_DEAD if solver_color == self.attacker else STATUS_ALIVE
        if solver_color and result[0] == success:
            #a clean result may not rest on a ko capture by the solver's side; if every proof needs one, it is a ko
            self.activate_engine()
            self.engine.command('kofree ' + solver_color)
            clean = self.engine.command('solve %s %d %d %s' % (to_move, ko, self.budget, text))
            self.engine.command('kofree 0')
            fields_clean = dict(item.split('=') for item in clean.split()[1:])
            if success == STATUS_DEAD and fields_clean['A'] == 'N':
                result = (STATUS_KO, result[1])
            if success == STATUS_ALIVE and fields_clean['D'] == 'K':
                result = (STATUS_KO, result[1])
        if result[0] == STATUS_KO:
            #bent four in the corner: when the game ends with the defender relying on ko threats, the attacker removes
            #them and starts the ko when it likes; if that kills, the ko was never the defender's to start
            self.activate_engine()
            self.engine.command('bentfour 1')
            answer = self.engine.command('solve %s %d %d %s' % (to_move, ko, self.budget, text))
            self.engine.command('bentfour 0')
            if answer.split()[1] == 'A=K':
                result = (STATUS_BENT4, result[1])
        self.cache[key] = result
        return result

    def solver_wins(self, board, to_move, solver_threats, opponent_threats):
        #True / False / None (undecided): the solver's side reaches its goal when each side has that many ko threats
        solver_color = self.solver_color
        black, white = (solver_threats, opponent_threats) if solver_color == 'B' else (opponent_threats, solver_threats)
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        self.activate_engine()
        answer = self.engine.command('solvecount %s %d %d %d %d %s' % (to_move, ko, self.budget, black, white, text))
        fields = dict(item.split('=') for item in answer.split()[1:])
        self.nodes += int(fields['nodes'])
        self.last_best = self.problem.from_index(int(fields['best'])) if int(fields['best']) >= 0 else None
        if fields['N'] == 'U':
            return None
        return (fields['N'] == 'K') == (solver_color == self.attacker)

    def counted_line(self, board, to_move, solver_threats, opponent_threats, plies=10):
        #the line the counted-threat search follows: [(colour, point or None, note)], each move keeps the result;
        #a retake against the ko ban uses one of the mover's threats
        threats = {self.solver_color: solver_threats, other_colour(self.solver_color): opponent_threats}
        expected = self.solver_wins(board, to_move, solver_threats, opponent_threats)
        if expected is None:
            return None, []
        line, passes = [], 0
        board = board.copy()
        for ply in range(plies):
            #the ko is over once the position is clean life or death whoever has the threats
            if ply > 0 and self.status(board, to_move)[0] != STATUS_KO:
                break
            self.solver_wins(board, to_move, threats[self.solver_color], threats[other_colour(self.solver_color)])
            #the ko first (a retake, if a threat is left), then the search's own move, then captures, then the rest
            captures = []
            for p in sorted(self.problem.region):
                if p not in board.stones and p != board.ko:
                    child = board.copy()
                    if child.play(to_move, p) and len(child.stones) <= len(board.stones):
                        captures.append(p)
            candidates = [board.ko, self.last_best] + captures + sorted(self.problem.region) + [None]
            chosen = None
            for p in candidates:
                if p is not None and p in board.stones:
                    continue
                if p is None and board.ko is not None and p in candidates[:1]:
                    continue
                retake = p is not None and p == board.ko
                if retake and threats[to_move] == 0:
                    continue
                child = board.copy()
                if not child.play(to_move, p, ignore_ko=retake):
                    continue
                left = dict(threats)
                if retake:
                    left[to_move] -= 1
                if self.solver_wins(child, other_colour(to_move), left[self.solver_color], left[other_colour(self.solver_color)]) == expected:
                    chosen = (p, child, left, retake)
                    break
            if chosen is None:
                break
            p, board, threats, retake = chosen
            line.append((to_move, p, 'retakes after a ko threat' if retake else ''))
            passes = passes + 1 if p is None else 0
            to_move = other_colour(to_move)
            if passes >= 2 or not any(q in board.stones for q in self.target):
                break
        return expected, line

    def ko_sign(self, board, to_move, limit=3):
        #who is ahead in a direct ko, as besogo writes it: KO+ the solver takes first, KO+n the opponent needs n-1
        #threats before it can start, KO- the opponent takes first, KO-n the solver needs n-1 threats to start.
        #Found with counted threats: the solver wins when its threats minus the opponent's reach some balance k.
        #None when a search is undecided or the balance is beyond the limit.
        if Solver.deadline and time.time() > Solver.deadline:
            raise TimeoutError('problem time limit')
        even = self.solver_wins(board, to_move, 0, 0)
        if even is None:
            return None
        if even:
            for t in range(1, limit + 1):
                result = self.solver_wins(board, to_move, 0, t)
                if result is None:
                    return None
                if not result:
                    return 'KO+' if t == 1 else 'KO+%d' % t
            return None
        for t in range(1, limit + 1):
            result = self.solver_wins(board, to_move, t, 0)
            if result is None:
                return None
            if result:
                return 'KO-' if t == 1 else 'KO-%d' % t
        return None

def groups_of(board, color):
    #chains of one color joined when they share a liberty or touch diagonally, the largest first
    chains = chains_of(board, color, None)
    infos = [(stones, board.chain(next(iter(stones)))[1]) for stones in chains]
    parent = list(range(len(chains)))
    def find(i):
        while parent[i] != i:
            i = parent[i]
        return i
    for i in range(len(infos)):
        for j in range(i + 1, len(infos)):
            close = infos[i][1] & infos[j][1] or any(
                (x + dx, y + dy) in infos[j][0] for (x, y) in infos[i][0] for dx in (-1, 1) for dy in (-1, 1))
            if close:
                parent[find(i)] = find(j)
    groups = {}
    for i, (stones, _) in enumerate(infos):
        groups.setdefault(find(i), set()).update(stones)
    return sorted(groups.values(), key=len, reverse=True)

def focused_region(board, box, target, extra_points=(), distance=DISTANCE):
    #the defender's side of the attacker's wall near the target: empty and defender points reachable from the target
    #without crossing attacker stones, within a distance of the target, plus the moves of the tree
    color = board.stones[next(iter(target))]
    #an enclosed area of the defender is taken whole, a leaking one only near the target
    enclosed, todo = set(target), list(target)
    while todo and len(enclosed) <= ENCLOSED_LIMIT:
        x, y = todo.pop()
        for q in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
            if 0 <= q[0] < board.size and 0 <= q[1] < board.size and q not in enclosed and board.stones.get(q) in (None, color):
                enclosed.add(q)
                todo.append(q)
    if len(enclosed) <= ENCLOSED_LIMIT:
        distance = 99
    reach, todo = set(target), list(target)
    while todo:
        x, y = todo.pop()
        for q in ((x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)):
            if q in box and q not in reach and board.stones.get(q) in (None, color):
                if min(max(abs(q[0] - t[0]), abs(q[1] - t[1])) for t in target) <= distance:
                    reach.add(q)
                    todo.append(q)
    region = {p for p in reach}
    #attacker stones bordering it, they touch the outside wall unless the region surrounds them
    for p in list(reach):
        for q in board.neighbours(p):
            if q in box and board.stones.get(q) not in (None, color):
                stones, liberties = board.chain(q)
                region |= {s for s in stones if s in box}
                #an attacker chain that is short of liberties can be captured, so all its liberties take part (like
                #E19 when B19-D19 gets threatened); walls with many liberties stay safe
                if not DYNAMIC and (len(liberties - reach) <= SMALL_CHAIN or len(liberties) <= WEAK_LIBERTIES):
                    region |= {l for l in liberties if l in box}
    region |= set(extra_points)
    if not DYNAMIC:
        #the empty points bordering the region, so no stone at its edge loses a liberty to the wall
        region |= {q for p in list(region) if p not in board.stones for q in board.neighbours(p) if q not in board.stones}
    #holes: points whose every neighbour is already in the region or a stone, like the corner of an eye space
    for _ in range(3):
        holes = {p for p in box if p not in region and p not in board.stones
                 and all(q in region or q in board.stones for q in board.neighbours(p))}
        if not holes:
            break
        region |= holes
    return region

def escape(value):
    return value.replace('\\', '\\\\').replace(']', '\\]')

def serialize(node):
    #SGF text of the tree, properties in their original order
    def node_text(n):
        return ';' + ''.join(key + ''.join('[%s]' % escape(v) for v in values) for key, values in n.properties.items())
    def tree(n):
        text = node_text(n)
        if len(n.children) == 1:
            return text + tree(n.children[0])
        return text + ''.join('(' + tree(child) + ')' for child in n.children)
    return '(' + tree(node) + ')'

_wide_engine = None

def wide_engine():
    #a second engine process for the wider regions, so the main one keeps its tables
    global _wide_engine
    if _wide_engine is None:
        _wide_engine = Engine()
    return _wide_engine

def add_wide(solver, board, points, target, extra_points, rings=2):
    #the region grown by empty points next to its empty points, so stones at its edge get the liberties they have
    region = set(solver.problem.region)
    for _ in range(rings):
        ring = {q for p in region if p not in board.stones for q in board.neighbours(p) if q not in region and q not in board.stones}
        region |= ring
    problem = Problem(board, region)
    solver.wide = Solver(wide_engine(), problem, solver.attacker, target, solver.budget)
    return solver

def bent_four_shapes(size):
    #the 4-point L shapes in each corner: the corner point, two more along one edge, one along the other
    shapes = []
    for cx, dx in ((0, 1), (size - 1, -1)):
        for cy, dy in ((0, 1), (size - 1, -1)):
            shapes.append(frozenset({(cx, cy), (cx + dx, cy), (cx + 2 * dx, cy), (cx, cy + dy)}))
            shapes.append(frozenset({(cx, cy), (cx, cy + dy), (cx, cy + 2 * dy), (cx + dx, cy)}))
    return shapes

def has_bent_four(board, defender):
    #an area of non-defender points enclosed by the defender alone that is a bent four in a corner
    shapes = bent_four_shapes(board.size)
    for shape in shapes:
        if any(board.stones.get(p) == defender for p in shape):
            continue
        enclosed = all(board.stones.get(q) == defender for p in shape for q in board.neighbours(p) if q not in shape)
        if enclosed:
            return True
    return False

def ko_kind(solver, board, to_move, plies=24):
    #follows the solver's ko line; a ko that comes from a bent four in the corner is BENT4 (dead unless the attacker is
    #forced to resolve it), else KO
    defender = 'W' if solver.attacker == 'B' else 'B'
    board = board.copy()
    for _ in range(plies):
        if has_bent_four(board, defender):
            return 'BENT4'
        status, best = solver.status(board, to_move)
        if status != STATUS_KO:
            break
        move = None
        if best is not None:
            child = board.copy()
            if child.play(to_move, best) and solver.status(child, 'W' if to_move == 'B' else 'B')[0] == STATUS_KO:
                move = best
        if move is None:
            #moves near the target first, so the line stays in the fight instead of wandering off
            near = lambda p: min(abs(p[0] - t[0]) + abs(p[1] - t[1]) for t in solver.target)
            for p in sorted(solver.problem.region, key=near):
                child = board.copy()
                if p not in child.stones and child.play(to_move, p) and solver.status(child, 'W' if to_move == 'B' else 'B')[0] == STATUS_KO:
                    move = p
                    break
        if move is None or not board.play(to_move, move):
            break
        to_move = 'W' if to_move == 'B' else 'B'
    return 'KO'

def ko_taken_by(board, last_mover, solver_color, status):
    #the convention for leaves: a line that ends with the solver's side capturing a ko is a ko (KO+, the solver took
    #first), even when the solver could steer back to clean life or death later; the direct move is the clean one
    if board.ko is not None and last_mover == solver_color and status in (STATUS_DEAD, STATUS_ALIVE):
        return STATUS_KO
    return status
