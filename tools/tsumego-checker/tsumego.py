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
    def __init__(self, path=os.environ.get('ENGINE') or os.path.join(os.path.dirname(os.path.abspath(__file__)), 'engine')):
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
STATUS_DDKO, STATUS_ADKO, STATUS_DSKO, STATUS_ASKO = 'DDKO', 'ADKO', 'DSKO', 'ASKO'
STATUS_SEKI, STATUS_SEKI_SENTE, STATUS_TEN_THOUSAND = 'SEKI', 'SEKI+', '10kKO'
SPECIAL_STATUSES = (STATUS_DDKO, STATUS_ADKO, STATUS_DSKO, STATUS_ASKO, STATUS_BENT4, STATUS_SEKI, STATUS_SEKI_SENTE)
#counted-threat searches are bigger than the plain ones: they get this many times the budget
KO_BUDGET_FACTOR = int(os.environ.get('KO_BUDGET_FACTOR', '5'))
#the ko label (research/ko_labels/design.md): thresholds within -LABEL_LIMIT..LABEL_LIMIT, holder profiles up to
#PASS_LIMIT forced passes, an undecided search retried once at ESCALATION times its budget and then expanded by one ply
LABEL_LIMIT = 6
PASS_LIMIT = 6
ESCALATION = 5
#the end playout of the counted searches (D13): 1 the attacker moves on (Design 3), 2 the side to move keeps the move
#(Design 2), which decides c121's held-ko check (undecided at 8M nodes with 1) and c027's counts at any history
END_PLAY = int(os.environ.get('END_PLAY', '2'))
#D7: at most this many candidate bars per attribution; a line ends once its winner wins with no threat against this many
THREAT_TESTS = 8
LINE_OUTRIGHT = 4

def other_colour(color):
    return 'W' if color == 'B' else 'B'

#besogo's statuses (webroot/besogo/js/status.js): the type ordinals status.better compares
STATUS_TYPES = {'DEAD': 2, 'BENT4': 3, 'DSKO': 4, 'DDKO': 5, '10kKO': 6, 'KO': 7, 'SEKI': 8, 'ADKO': 9, 'ASKO': 10, 'ALIVE': 11}

def besogo_string(approaches, extra_threats):
    #a KO status as status.js writes it: getKoApproachesStr + 'KO' + getKoStr
    text = ''
    if approaches:
        text = ('A+%d' if approaches > 0 else 'A-%d') % abs(approaches)
    text += 'KO' + ('+' if not extra_threats or extra_threats >= 0 else '-')
    if extra_threats > 0:
        text += str(extra_threats + 1)
    elif extra_threats < -1:
        text += str(-extra_threats)
    return text

def parse_status(text):
    #besogo.loadStatusInternalFromString for one status: {type, approaches, extra_threats, sente}, None for a text
    #besogo does not read
    if text in STATUS_TYPES:
        return {'type': text, 'approaches': 0, 'extra_threats': 0, 'sente': False}
    if text == 'SEKI+':
        return {'type': 'SEKI', 'approaches': 0, 'extra_threats': 0, 'sente': True}
    approaches = 0
    if text[:1] == 'A' and text[1:2] in ('+', '-'):
        digits = re.match(r'\d*', text[2:]).group()
        approaches = int(digits or '0') * (-1 if text[1] == '-' else 1)
        text = text[2 + len(digits):]
    if not text.startswith('KO'):
        return None
    status = {'type': 'KO', 'approaches': approaches, 'extra_threats': 0, 'sente': False}
    rest = text[2:]
    if rest in ('', '+'):
        return status
    if rest == '-':
        status['extra_threats'] = -1
        return status
    if rest[0] in '+-' and rest[1:].isdigit():
        number = int(rest[1:])
        status['extra_threats'] = number - 1 if rest[0] == '+' else -number
        return status
    return None

def label_threshold(text):
    #T_label = -(approaches + extraThreats): the least balance of threats at which the solver wins that the label implies
    status = parse_status(text)
    if status is None or status['type'] != 'KO':
        return None
    return -(status['approaches'] + status['extra_threats'])

def better(first, second, goal):
    #status.better: whether the status first is better than second for the solver, goal 'KILL' or 'LIVE'
    one, two = parse_status(first), parse_status(second)
    if one is None or two is None:
        raise ValueError('not a besogo status: %s / %s' % (first, second))
    ten_thousand = -1 if goal == 'KILL' else 1
    if one['type'] == '10kKO' and two['type'] == 'KO':
        return two['approaches'] < ten_thousand
    if two['type'] == '10kKO' and one['type'] == 'KO':
        return one['approaches'] > ten_thousand
    if one['type'] != two['type']:
        if goal == 'KILL':
            return STATUS_TYPES[one['type']] < STATUS_TYPES[two['type']]
        return STATUS_TYPES[one['type']] > STATUS_TYPES[two['type']]
    if one['type'] == 'KO':
        if one['approaches'] != two['approaches']:
            return one['approaches'] > two['approaches']
        return one['extra_threats'] > two['extra_threats']
    if one['type'] == 'SEKI':
        return one['sente'] and not two['sente']
    return False

def mode_class(a, d):
    #the class of modes A (the defender has unlimited ko threats) and D (the attacker has)
    if a == 'K':
        return STATUS_DEAD
    if a == 'N' and d == 'K':
        return STATUS_KO
    if d == 'N':
        return STATUS_ALIVE
    return STATUS_UNKNOWN

def wide_open(modes):
    #the classes an undecided class step of the wider area leaves possible beside the narrow KO: DEAD when its mode A is
    #undecided, ALIVE when its mode D is, with SEKI and SEKI+, since the seki tests that refine an ALIVE ran in neither
    #area (the narrow area's own tests stand for the refinements of its KO: bent four, double ko, superko)
    statuses = [STATUS_DEAD] if modes['A'] == 'U' else []
    if modes['D'] == 'U':
        statuses += [STATUS_ALIVE, STATUS_SEKI, STATUS_SEKI_SENTE]
    return statuses

def threshold_interval(record):
    #a threshold record as the counts it allows: (lowest, highest, decided), None for no bound (beyond the limit, or nothing
    #found on that side); a count beyond the limit is decided, as a bound
    if record['d'] is not None:
        return (record['d'], record['d'], True)
    if record['beyond']:
        return (None, -LABEL_LIMIT, True) if record['beyond'] < 0 else (LABEL_LIMIT + 1, None, True)
    low, high = record['bracket']
    return (None if low <= -LABEL_LIMIT else low, None if high > LABEL_LIMIT else high, False)

def count_order(value, sign):
    #the sort key of a count interval for the side that wants it low (sign 1, the solver) or high (-1): its best end
    end = value[0] if sign > 0 else value[1]
    return float('-inf') if end is None else end * sign

def intervals_meet(first, second):
    #whether two count intervals share a count
    lows = [value[0] for value in (first, second) if value[0] is not None]
    highs = [value[1] for value in (first, second) if value[1] is not None]
    return not lows or not highs or max(lows) <= min(highs)

def interval_text(value):
    #a count interval in words: the count, 'beyond +6', 'at least 2', 'between 2 and 4', 'undecided'
    low, high, decided = value
    if decided:
        return low if low == high else 'beyond %+d' % (LABEL_LIMIT if low is not None else -LABEL_LIMIT)
    if low is not None and high is not None:
        return 'between %d and %d' % (low, high)
    if low is not None:
        return 'at least %d' % low
    return 'at most %d' % high if high is not None else 'undecided'

def answer_fields(answer):
    return dict(item.split('=', 1) for item in answer.split()[1:])

class Solver:
    #one formulation: attacker color + target stones
    deadline = None #time after which status() gives up, per problem
    wide = None #the same formulation over a wider region, asked again when the answer is a ko
    count_budget = None #the budget of a counted search, KO_BUDGET_FACTOR times the budget when None
    end_play = END_PLAY
    def __init__(self, engine, problem, attacker, target, budget, keep=()):
        self.engine = engine
        self.problem = problem
        self.attacker = attacker
        self.target = sorted(target)
        self.budget = budget
        self.keep = sorted(keep) #attacker stones whose capture means the defender lives (hand-built model boards)
        self.nodes = 0
        self.cache = {}
        self.solved = {}
        self.classes = {}
        self.counts = {}
        self.undecided = None #while a label is computed, its list of undecided searches
        self.part = None #the part of the label a search belongs to
        self.activate()

    def activate_engine(self):
        #the engine holds one problem at a time; another solver may have loaded its own since
        if getattr(self.engine, 'active', None) is not self:
            self.activate()

    def activate(self):
        indices = ','.join(str(self.problem.index(p)) for p in self.target)
        self.engine.command('problem %d %s %s %s' % (self.problem.size, self.attacker, self.problem.board_text(self.problem.board), indices))
        self.engine.active = self
        if self.keep:
            self.ask('keep ' + ','.join(str(self.problem.index(p)) for p in self.keep))

    def ask(self, line):
        #one engine command; an answer "error ..." raises, so a malformed search is never read as a result
        self.activate_engine()
        answer = self.engine.command(line)
        if not answer or answer.startswith('error'):
            raise RuntimeError('engine answered %r to %s' % (answer, line[:60]))
        if os.environ.get('TRACE'):
            print(answer)
        return answer

    def chain_of(self, stones, p):
        #a chain and its liberties as the engine counts them: the outside is a liberty of attacker chains only
        color = stones[p]
        chain, liberties, todo = {p}, set(), [p]
        while todo:
            q = todo.pop()
            for r in self.problem.board.neighbours(q):
                if r in stones:
                    if stones[r] == color and r not in chain:
                        chain.add(r)
                        todo.append(r)
                elif r in self.problem.region or r in self.problem.fringe or color == self.attacker:
                    liberties.add(r)
        return chain, liberties

    def play(self, board, color, p, ignore_ko=False):
        #the board after the move as the engine plays it in this area, None for an illegal move; p None is a pass
        child = board.copy()
        child.ko = None
        if p is None:
            return child
        if p in board.stones:
            return None
        child.stones[p] = color
        captured = set()
        for q in board.neighbours(p):
            if child.stones.get(q) == other_colour(color) and q not in captured:
                chain, liberties = self.chain_of(child.stones, q)
                if not liberties:
                    captured |= chain
        if p == board.ko and not ignore_ko and len(captured) == 1:
            return None
        for q in captured:
            del child.stones[q]
        own, liberties = self.chain_of(child.stones, p)
        if not liberties:
            return None
        if len(captured) == 1 and len(own) == 1 and len(liberties) == 1:
            child.ko = next(iter(captured))
        return child

    def line_moves(self, text, to_move):
        #an engine line (B<i>_W<i>_Bpass, ! a ban break, fp a forced pass, cycle a repetition) as [(colour, point, mark)]
        moves = []
        for item in ([] if text in ('-', '') else text.split('_')):
            if item == 'cycle':
                moves.append((None, None, 'cycle'))
            elif item == 'fp':
                moves.append((to_move, None, 'fp'))
                to_move = other_colour(to_move)
            else:
                rest = item[1:].rstrip('!')
                moves.append((item[0], None if rest == 'pass' else self.problem.from_index(int(rest)), '!' if item.endswith('!') else ''))
                to_move = other_colour(item[0])
        return moves

    def replay(self, board, moves):
        #the boards after each move of a line; a move the engine played that is illegal here is a bug, so it raises
        boards = []
        for color, p, mark in moves:
            if mark == 'cycle':
                break
            child = self.play(board, color, p, ignore_ko=mark == '!')
            if child is None:
                raise RuntimeError('line move %s %s is illegal in the area' % (color, p))
            boards.append(child)
            board = child
        return boards

    def ko_shapes(self, board):
        #D1: single stones in atari whose capture is a ko capture, as (holder, stone, capture point, banned)
        shapes = []
        for q, color in sorted(board.stones.items()):
            chain, liberties = self.chain_of(board.stones, q)
            if len(chain) != 1 or len(liberties) != 1:
                continue
            p = next(iter(liberties))
            child = self.play(board, other_colour(color), p, ignore_ko=True)
            if child is not None and child.ko == q:
                shapes.append((color, q, p, board.ko == q))
        return shapes

    def modes(self, board, to_move, budget=None, cycle=0, kofree=None, bentfour=False, sekikill=False):
        #one solve: modes A and D with these switches and their principal lines, the answer's fields (cached)
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        budget = budget or self.budget
        key = (text, ko, to_move, budget, cycle, kofree, bentfour, sekikill)
        if key not in self.solved:
            if Solver.deadline and time.time() > Solver.deadline:
                raise TimeoutError('problem time limit')
            switches = [(name, value, default) for name, value, default in (('cycle', cycle, 0), ('kofree', kofree or '0', '0'),
                        ('bentfour', int(bentfour), 0), ('sekikill', int(sekikill), 0), ('pv', 1, 0)) if value != default]
            for name, value, default in switches:
                self.ask('set %s %s' % (name, value))
            try:
                answer = self.ask('solve %s %d %d %s' % (to_move, ko, budget, text))
            finally:
                for name, value, default in switches:
                    self.ask('set %s %s' % (name, default))
            fields = answer_fields(answer)
            self.nodes += int(fields['nodes'])
            self.solved[key] = fields
        return self.solved[key]

    def note_undecided(self, record, part, search, fields, open_statuses=()):
        #an undecided solve of the class step, and the statuses it leaves open beside the one the class keeps
        record['undecided'].append({'part': part, 'search': search, 'nodes': int(fields['nodes'])})
        self.note_open(record, part, int(fields['nodes']), open_statuses)

    def note_open(self, record, part, nodes, open_statuses):
        #a refinement test of the class that stays undecided: the class keeps the status of the steps before it, and the
        #statuses the test could have given stay open beside it (open_tests: which test, at how many nodes)
        record['open'].extend(status for status in open_statuses if status not in record['open'])
        record['open_tests'].append({'part': part, 'nodes': nodes, 'open': list(open_statuses)})
        record['warnings'].append('%s test undecided' % part)

    def classify(self, board, to_move, budget=None, use_wide=True):
        #the class (design 4, step 1): modes A and D under cycle rule 0, the double-ko and superko tests, the wider area for
        #a ko, the clean result, the bent-four test and the seki tests. {status, best, modes, tests, warnings, undecided,
        #open: the statuses an undecided refinement test leaves open beside status, open_tests: those tests}
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        key = (text, ko, to_move, budget or self.budget, use_wide)
        if key in self.classes:
            return self.classes[key]
        plain = self.modes(board, to_move, budget)
        a, d = plain['A'], plain['D']
        best = self.problem.from_index(int(plain['bestA'] if a == 'K' or d != 'K' else plain['bestD']))
        record = {'status': mode_class(a, d), 'best': best,
                  'modes': {'A': a, 'D': d, 'nodes': int(plain['nodes']), 'cycles': int(plain['cycles'])},
                  'tests': {}, 'warnings': [], 'undecided': [], 'open': [], 'open_tests': [], 'decided_by': None}
        if record['status'] == STATUS_UNKNOWN:
            record['undecided'].append({'part': 'class', 'search': 'solve', 'nodes': int(plain['nodes'])})
        elif int(plain['cycles']):
            cycled = self.cycle_tests(board, to_move, plain, record, budget)
            if cycled is not None:
                record['status'] = cycled
                record['decided_by'] = 'cycle rules'
        if record['decided_by'] is None and record['status'] == STATUS_KO and use_wide and self.wide is not None:
            #a ko next to the region's edge can come from the wall taking a liberty; the wider region decides
            wide = self.wide.classify(board, to_move, budget)
            if wide['status'] != STATUS_UNKNOWN:
                record = dict(wide, decided_by='wide area', narrow=record)
            else:
                #undecided there, the narrow KO stands beside what the wider area's undecided modes leave possible
                self.note_undecided(record, 'wider-area', 'solve (wider area)', wide['modes'], wide_open(wide['modes']))
        if record['decided_by'] is None:
            if record['status'] in (STATUS_DEAD, STATUS_ALIVE):
                record['status'] = self.clean_status(board, to_move, record, budget)
            if record['status'] == STATUS_KO:
                record['status'] = self.bent_four_status(board, to_move, plain, record, budget)
            if record['status'] == STATUS_ALIVE:
                record['status'] = self.seki_status(board, to_move, record, budget)
        #a test can leave open the status a later step gave the class
        record['open'] = [status for status in record['open'] if status != record['status']]
        self.classes[key] = record
        return record

    def cycle_tests(self, board, to_move, plain, record, budget):
        #D10 when the rule-0 proofs adjudicated repetitions. Rule 1 (every cycle kills) only helps the attacker and rule 2
        #(every cycle lives) the defender, so a rule-0 kill stands under rule 1 and a rule-0 life under rule 2. A kill that
        #rule 2 undoes is a double ko when its cycle alternates between two kos, else superko, which rule 3 decides.
        #The status, or None when the cycle rule changes nothing
        a, d = plain['A'], plain['D']
        test = record['tests']['cycle'] = {}
        def solve(rule):
            fields = self.modes(board, to_move, budget, cycle=rule)
            test['rule %d' % rule] = '%s%s' % (fields['A'], fields['D'])
            return fields
        if a == 'K' or d == 'K':
            lives = solve(2)
            mode = 'A' if a == 'K' else 'D'
            if lives[mode] == 'U':
                self.note_undecided(record, 'double-ko', 'solve cycle 2', lives,
                                    [STATUS_DDKO if mode == 'A' else STATUS_ADKO] + self.superko_open(record))
                return None
            if lives[mode] == 'N':
                #the cycle shows on the rule-0 line when the defender's moves there happen to be its best, else on the line
                #where each side plays from its own proof
                if self.two_ko_cycle(board, self.line_moves(plain['pv' + mode], to_move)) or \
                        self.two_ko_cycle(board, self.proofs_line(board, to_move, mode, {'cycle': 0}, {'cycle': 2}, budget)):
                    return STATUS_DDKO if mode == 'A' else STATUS_ADKO
                return self.superko(board, to_move, record, budget)
            if a == 'K':
                return None
        kills = solve(1)
        if kills['A'] == 'U' or (kills['A'] == 'N' and kills['D'] == 'U' and d == 'N'):
            self.note_undecided(record, 'superko', 'solve cycle 1', kills, self.superko_open(record))
            return None
        if kills['A'] == 'K' or (d == 'N' and kills['D'] == 'K'):
            return self.superko(board, to_move, record, budget)
        return None

    def superko(self, board, to_move, record, budget):
        #rules 1 and 2 differ and no double ko explains it: positional superko (rule 3) decides, untested on real leaves.
        #It is superko only when rule 3 changes rule 0's class: a seki or a bent four whose cycles only rule 1 scores as a
        #kill keeps its class (authored SEKI, SEKI+ and BENT4 leaves of 9, 1808, 1881, 2225, 9820 and 13661)
        fields = self.modes(board, to_move, budget, cycle=3)
        record['tests']['cycle']['rule 3'] = fields['A'] + fields['D']
        status = mode_class(fields['A'], fields['D'])
        if status == STATUS_UNKNOWN:
            self.note_undecided(record, 'superko', 'solve cycle 3', fields, self.superko_open(record))
            return None
        plain = record['modes']
        if status == mode_class(plain['A'], plain['D']):
            return None
        record['warnings'].append('superko (unverified)')
        return {STATUS_DEAD: STATUS_DSKO, STATUS_ALIVE: STATUS_ASKO}.get(status, status)

    def superko_open(self, record):
        #what superko() can give: rule 3's class as DSKO, ASKO or KO, except the one of rule 0's class, which it keeps
        kept = {STATUS_DEAD: STATUS_DSKO, STATUS_ALIVE: STATUS_ASKO}.get(mode_class(record['modes']['A'], record['modes']['D']), STATUS_KO)
        return [status for status in (STATUS_DSKO, STATUS_ASKO, STATUS_KO) if status != kept]

    def proofs_line(self, board, to_move, mode, attacker_switches, defender_switches, budget, plies=24):
        #the line both sides play from their own proofs where two rules disagree: the attacker from its win under its
        #switches, the defender from its win under the others; it stops when a position repeats (marked cycle), after two
        #passes or at the end of the game
        moves, seen, passed = [], [], 0
        for ply in range(plies):
            seen.append((frozenset(board.stones.items()), board.ko, to_move, passed > 0))
            attacking = to_move == self.attacker
            fields = self.modes(board, to_move, budget, **(attacker_switches if attacking else defender_switches))
            best = int(fields['best' + mode])
            if fields[mode] != ('K' if attacking else 'N') or best == -2:
                break
            p = self.problem.from_index(best)
            retake = p is not None and p == board.ko
            child = self.play(board, to_move, p, ignore_ko=retake)
            if child is None:
                break
            moves.append((to_move, p, '!' if retake else ''))
            board, to_move, passed = child, other_colour(to_move), passed + 1 if p is None else 0
            if (frozenset(board.stones.items()), board.ko, to_move, passed > 0) in seen:
                moves.append((None, None, 'cycle'))
                break
            if passed >= 2:
                break
        return moves

    def two_ko_cycle(self, board, moves):
        #a line ending in a repetition whose cycle captures in exactly two kos and nowhere else
        if not moves or moves[-1][2] != 'cycle':
            return False
        boards = self.replay(board, moves)
        #the engine's key: board, ban, side to move (it alternates in a mode line) and whether the last move passed
        keys = [(frozenset(board.stones.items()), board.ko, 0, False)]
        keys += [(frozenset(b.stones.items()), b.ko, (i + 1) % 2, p is None) for i, (b, (c, p, m)) in enumerate(zip(boards, moves))]
        if keys[-1] not in keys[:-1]:
            return False
        start = keys.index(keys[-1])
        kos = set()
        before = board if start == 0 else boards[start - 1]
        for (color, p, mark), after in zip(moves[start:], boards[start:]):
            if p is not None and len(after.stones) <= len(before.stones):
                if after.ko is None:
                    return False
                kos.add(frozenset((p, after.ko)))
            before = after
        return len(kos) == 2

    def clean_status(self, board, to_move, record, budget):
        #a clean result may not rest on a ko capture by the solver's side; if every proof needs one, it is a ko
        status = record['status']
        solver_color = getattr(self, 'solver_color', None)
        success = STATUS_DEAD if solver_color == self.attacker else STATUS_ALIVE
        if not solver_color or status != success:
            return status
        clean = self.modes(board, to_move, budget, kofree=solver_color)
        mode = 'A' if success == STATUS_DEAD else 'D'
        record['tests']['clean'] = clean['A'] + clean['D']
        if clean[mode] == 'U':
            self.note_undecided(record, 'clean-result', 'solve kofree', clean, [STATUS_KO])
        if (mode == 'A' and clean['A'] == 'N') or (mode == 'D' and clean['D'] == 'K'):
            return STATUS_KO
        return status

    def bent_four_status(self, board, to_move, plain, record, budget):
        #D11: bent four in the corner when the attacker, removing the defender's threats at the end and starting the ko
        #when it likes, kills in mode A although mode A does not kill without that rule (both without kofree); the shape
        #must be on the killing line or on the line where the defender plays its win without the rule, or on the line of
        #that end (the attacker to move, no threats, the old end rule)
        if plain['A'] != 'N':
            return STATUS_KO
        rule = self.modes(board, to_move, budget, bentfour=True)
        record['tests']['bent four'] = rule['A'] + rule['D']
        if rule['A'] == 'U':
            self.note_undecided(record, 'bent-four', 'solve bentfour', rule, [STATUS_BENT4])
        if rule['A'] != 'K':
            return STATUS_KO
        defender = other_colour(self.attacker)
        open_ends = 0
        for line in (self.line_moves(rule['pvA'], to_move), self.proofs_line(board, to_move, 'A', {'bentfour': True}, {}, budget)):
            boards = [board] + self.replay(board, line)
            if len(line) >= 2 and line[-1][1] is None and line[-2][1] is None:
                kills, end_line, nodes = self.count(boards[-1], self.attacker, 0, 0, end=0, budget=budget, escalate=False,
                                                    record=record, part='bent-four end')
                boards += self.replay(boards[-1], end_line) if kills is not None else []
                open_ends += nodes if kills is None else 0
            if any(has_bent_four(b, defender) for b in boards):
                return STATUS_BENT4
        if open_ends:
            #the end's line is unknown, so the shape may be on it
            self.note_open(record, 'bent-four end', open_ends, [STATUS_BENT4])
        else:
            record['warnings'].append('bent-four rule kills, but no bent four on its lines')
        return STATUS_KO

    def seki_status(self, board, to_move, record, budget):
        #D11: the defender lives, but dies when a target that is not unconditionally alive at the end counts as dead: SEKI.
        #SEKI+ when the solver keeps sente: with the opponent to move, one forced opponent pass lets the solver improve;
        #with the solver to move, one forced solver pass keeps the seki
        killed = self.modes(board, to_move, budget, sekikill=True)
        record['tests']['seki'] = killed['A'] + killed['D']
        if killed['D'] == 'U':
            #the sente test does not run, so a seki would be SEKI or SEKI+
            self.note_undecided(record, 'seki', 'solve sekikill', killed, [STATUS_SEKI, STATUS_SEKI_SENTE])
            return STATUS_ALIVE
        if killed['D'] != 'K':
            return STATUS_ALIVE
        solver_color = getattr(self, 'solver_color', None)
        if not solver_color:
            return STATUS_SEKI
        attacker_solves = solver_color == self.attacker
        if to_move == solver_color:
            #the opponent gets a free move: the seki holds when it cannot live for real (attacker solving) or kill
            result, line, nodes = self.count(board, to_move, 0, 4, passes={to_move: 1}, sekikill=attacker_solves, budget=budget,
                                             escalate=False, record=record, part='seki-in-sente')
        else:
            #the solver gets a free move: it improves when it kills (attacker solving) or lives for real, or wins a ko for it
            result, line, nodes = self.count(board, to_move, 4, 0, passes={to_move: 1}, sekikill=not attacker_solves, budget=budget,
                                             escalate=False, record=record, part='seki-in-sente')
        record['tests']['seki in sente'] = result
        if result is None:
            self.note_open(record, 'seki-in-sente', nodes, [STATUS_SEKI_SENTE])
            return STATUS_SEKI
        return STATUS_SEKI_SENTE if result else STATUS_SEKI

    def status(self, board, to_move):
        #the target's class with to_move to play (DEAD / KO / ALIVE / BENT4 / SEKI / SEKI+ / DDKO / ADKO / DSKO / ASKO /
        #?), plus the best move found. A refinement test of the class that stays undecided leaves the class of the steps
        #before it and the statuses it could have given open beside it, and the class is then never given alone: it comes
        #as status_text writes it ('KO, BENT4 not ruled out (bent-four test undecided at 1,000,000 nodes)'), which
        #status_parts reads back; status_record() has the parts
        record = self.status_record(board, to_move)
        return status_text(record), record['best']

    def status_record(self, board, to_move):
        #the class record behind status() (classify, cached per position): status, best, open, open_tests, undecided
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        key = (text, ko, to_move)
        if key not in self.cache:
            self.cache[key] = self.classify(board, to_move)
        return self.cache[key]

    def solver_wins(self, board, to_move, solver_threats, opponent_threats):
        #True / False / None (undecided): the solver's side reaches its goal when each side has that many ko threats
        solver_color = self.solver_color
        black, white = (solver_threats, opponent_threats) if solver_color == 'B' else (opponent_threats, solver_threats)
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        self.activate_engine()
        answer = self.engine.command('solvecount %s %d %d %d %d %s' % (to_move, ko, self.budget * KO_BUDGET_FACTOR, black, white, text))
        fields = dict(item.split('=') for item in answer.split()[1:])
        self.nodes += int(fields['nodes'])
        self.last_best = self.problem.from_index(int(fields['best'])) if int(fields['best']) >= 0 else None
        if fields['N'] == 'U':
            return None
        return (fields['N'] == 'K') == (solver_color == self.attacker)

    def counted_line(self, board, to_move, solver_threats, opponent_threats, plies=24, **options):
        #the line of the counted game G(P; s, o) with count's options (D2): True / False / None for the solver and the
        #moves [(colour, point, mark)], every position on it decided with the same winner (walk_line)
        walk = self.walk_line(board, to_move, solver_threats, opponent_threats, plies, **options)
        return walk['result'], walk['moves']

    def walk_line(self, board, to_move, s, o, plies=24, passes=None, passed=False, **options):
        #counted_line's walk: the winner plays its search's move, the loser resists in the canonical order (resistance), and
        #the position after each move must be decided with the same winner (after a voluntary pass with that pass made,
        #passed). It stops at the end of the game, at a repetition (marked cycle), once the ko is over (after a move of the
        #winner it wins with no threat against LINE_OUTRIGHT for the loser), after plies moves, or where a search is
        #undecided. {result, moves, stop, threats left, nodes}
        options.setdefault('escalate', False)
        solver, opponent = self.solver_color, other_colour(self.solver_color)
        threats = {solver: s, opponent: o}
        passes = {color: k for color, k in (passes or {}).items() if k}
        start = self.nodes
        result, pv, nodes = self.count(board, to_move, s, o, passes=dict(passes) or None, passed=passed, **options)
        walk = {'result': result, 'moves': [], 'stop': 'undecided' if result is None else 'plies'}
        if result is not None:
            winner = solver if result else opponent
            voluntary = 1 if passed else 0
            #the engine's key: board, ban, side to move, a pass just made, threats left and forced passes
            position = lambda b, t: (frozenset(b.stones.items()), b.ko, t, voluntary > 0, threats[solver], threats[opponent],
                                     tuple(sorted(passes.items())))
            seen = {position(board, to_move)}
            #the point where the side to move may retake the last ko capture, and who made that capture
            last_ko = (board.ko, other_colour(to_move)) if board.ko is not None else None
            for ply in range(plies):
                if passes.get(to_move):
                    move = (to_move, None, 'fp')
                elif to_move == winner:
                    if not pv or pv[0][0] != to_move or pv[0][2] in ('fp', 'cycle'):
                        walk['stop'] = 'no move on the search line'
                        break
                    move = pv[0]
                else:
                    move = self.resistance(board, to_move, winner, threats, passes, last_ko, walk['moves'], options, voluntary > 0)
                color, p, mark = move
                child = self.play(board, color, p, ignore_ko=mark == '!')
                if child is None:
                    raise RuntimeError('line move %s %s is illegal in the area' % (color, self.point_name(p)))
                left, rest = dict(threats), dict(passes)
                if mark == '!':
                    left[color] -= 1
                if mark == 'fp':
                    rest[color] -= 1
                rest = {c: k for c, k in rest.items() if k}
                after, pv, nodes = self.count(child, other_colour(color), left[solver], left[opponent], passes=dict(rest) or None,
                                              passed=p is None and mark != 'fp', **options)
                if after != result:
                    walk['stop'] = 'undecided' if after is None else 'the result changes after %s %s' % (color, self.point_name(p))
                    break
                walk['moves'].append(move)
                board, to_move, threats, passes = child, other_colour(color), left, rest
                if child.ko is not None:
                    last_ko = (child.ko, color)
                voluntary = voluntary + 1 if p is None and mark != 'fp' else 0
                if not any(q in board.stones for q in self.target):
                    walk['stop'] = 'target captured'
                elif voluntary >= 2 and options.get('end', self.end_play) == 0:
                    walk['stop'] = 'two passes'
                elif position(board, to_move) in seen:
                    walk['moves'].append((None, None, 'cycle'))
                    walk['stop'] = 'repetition'
                elif color == winner and mark != 'fp' and self.outright(board, to_move, winner, passes, options, voluntary > 0):
                    walk['stop'] = 'ko over'
                else:
                    seen.add(position(board, to_move))
                    continue
                break
        walk['threats'] = {'S': threats[solver], 'O': threats[opponent]}
        walk['nodes'] = self.nodes - start
        return walk

    def resistance(self, board, to_move, winner, threats, passes, last_ko, moves, options, passed=False):
        #the loser's move on a counted line (passed: the winner's last move was a pass): a retake of the winner's last ko
        #capture that it can afford (D2), else the first move that still costs the winner its whole margin (tight) of: the
        #moves next to the last move (the answer to a local threat), a save of its chain from an atari (the answer to a
        #direct capture; connecting its own ko stone resolves the ko and is no answer); else the tight move nearest the last
        #move by solvemoves; else a pass. D2 passes while a pass stays tight and then takes the search's own move, but that
        #move depends on the search's history and can ignore a threat it could answer (c001's B19), and a tight pass can
        #still skip the fight: after two passes the end playout goes on (c081's take at C19), and c014's answer A17 is no
        #neighbour of the last move
        if last_ko is not None and last_ko[1] == winner:
            point = last_ko[0]
            banned = board.ko == point
            child = self.play(board, to_move, point, ignore_ko=True)
            if child is not None and child.ko is not None and (not banned or threats[to_move] > 0) \
                    and not self.barred(to_move, point, banned, options):
                return (to_move, point, '!' if banned else '')
        candidates = []
        last = moves[-1][1] if moves else None
        if last is not None:
            near = list(board.neighbours(last))
            for q in board.neighbours(last):
                if q in board.stones:
                    chain, liberties = self.chain_of(board.stones, q)
                    if len(liberties) <= 2:
                        near.extend(sorted(liberties))
            candidates += [q for q in dict.fromkeys(near) if q not in board.stones and q in self.problem.region][:4]
        ko_connections = {point for holder, stone, point, banned in self.ko_shapes(board) if holder == to_move}
        for q, color in sorted(board.stones.items()):
            if color == to_move:
                chain, liberties = self.chain_of(board.stones, q)
                if len(liberties) == 1:
                    p = next(iter(liberties))
                    child = self.play(board, to_move, p)
                    playable = p in self.problem.region or p in self.problem.fringe
                    if playable and p not in candidates and p not in ko_connections and child is not None \
                            and len(self.chain_of(child.stones, p)[1]) >= 2:
                        candidates.append(p)
        for p in candidates:
            if self.tight(board, to_move, p, winner, threats, passes, options):
                return (to_move, p, '')
        #every move at once: the loser wins after a tight move when the winner's margin is one smaller
        solver = self.solver_color
        smaller = self.smaller_margin(threats, winner, to_move)
        if smaller is not None:
            table = self.counted_moves(board, to_move, smaller[solver], smaller[other_colour(solver)], passes=dict(passes) or None,
                                       bar=options.get('bar'), bar_banned=options.get('bar_banned'), end=options.get('end'), budget=self.budget,
                                       passed=passed)
            distance = lambda p: (abs(p[0] - last[0]) + abs(p[1] - last[1]) if last else 0, p)
            tight = sorted((p for p, mark, wins, nodes in table if wins and p is not None and mark == ''), key=distance)
            if tight:
                return (to_move, tight[0], '')
        return (to_move, None, '')

    def smaller_margin(self, threats, winner, loser):
        #the threats with the winner's margin one smaller: one threat less for it, else one more for the loser (None at 15)
        smaller = dict(threats)
        if smaller[winner]:
            smaller[winner] -= 1
        elif smaller[loser] < 15:
            smaller[loser] += 1
        else:
            return None
        return smaller

    def outright(self, board, to_move, winner, passes, options, passed=False):
        #the ko is over: the winner wins with no threat against LINE_OUTRIGHT threats for the other side
        solver = self.solver_color
        s, o = (0, LINE_OUTRIGHT) if winner == solver else (LINE_OUTRIGHT, 0)
        result, line, nodes = self.count(board, to_move, s, o, passes=dict(passes) or None, passed=passed, part='line end test', **options)
        return result is not None and result == (winner == solver)

    def tight(self, board, to_move, p, winner, threats, passes, options):
        #after the loser's move p the winner still needs its whole margin: with one threat less, or the loser one more, it
        #fails
        if self.barred(to_move, p, board.ko is not None, options):
            return False
        child = self.play(board, to_move, p)
        if child is None:
            return False
        solver = self.solver_color
        smaller = self.smaller_margin(threats, winner, to_move)
        if smaller is None:
            return False
        result, line, nodes = self.count(child, other_colour(to_move), smaller[solver], smaller[other_colour(solver)],
                                         passes=dict(passes) or None, **options)
        return result is not None and result != (winner == solver)

    def barred(self, color, p, banned, options):
        #whether the search's options bar color from p (for the whole search, or while it is under a ko ban)
        if p is None:
            return False
        return p in (options.get('bar') or {}).get(color, ()) or (banned and p in (options.get('bar_banned') or {}).get(color, ()))

    def ko_points(self, board):
        #the ko of a position with a ban: the point to retake and the stone the retake captures
        for holder, stone, point, banned in self.ko_shapes(board):
            if point == board.ko:
                return (point, stone)
        return None

    def saves(self, board, color, p):
        #the move fills the last liberty of one of color's chains: it answers an atari (a direct capture's threat)
        return any(board.stones.get(q) == color and self.chain_of(board.stones, q)[1] == {p} for q in board.neighbours(p))

    def ko_sign(self, board, to_move, limit=6):
        #who is ahead in a direct ko, as besogo writes it: KO+ the solver takes first, KO+n the opponent needs n-1
        #threats before it can start, KO- the opponent takes first, KO-n the solver needs n-1 threats to start.
        #Found with counted threats: the solver wins when its threats minus the opponent's reach some balance k.
        #None when a search is undecided or the balance is beyond the limit.
        if Solver.deadline and time.time() > Solver.deadline:
            raise TimeoutError('problem time limit')
        if self.wide is not None:
            #who is ahead in a ko often turns on an approach move at the region's edge: the wider region decides
            wide = self.wide.ko_sign(board, to_move, limit)
            if wide is not None:
                return wide
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

    def counted_options(self, board, to_move, s, o, passes, bar, bar_banned, cycle, end, sekikill, pv=False, passed=False):
        #the arguments of solvex and solvemoves after the budget, the solver's threats s against the opponent's o; passed: the
        #last move was a voluntary pass (ps=1)
        black, white = (s, o) if self.solver_color == 'B' else (o, s)
        options = ['p%s=%d' % (color.lower(), k) for color, k in sorted((passes or {}).items()) if k]
        if passed:
            options.append('ps=1')
        for prefix, bars in (('f', bar), ('k', bar_banned)):
            for color, points in sorted((bars or {}).items()):
                if points:
                    options.append('%s%s=%s' % (prefix, color.lower(), ';'.join(str(self.problem.index(p)) for p in sorted(points))))
        options.append('end=%d' % (self.end_play if end is None else end))
        if cycle is not None:
            options.append('cyc=%d' % cycle)
        if sekikill:
            options.append('sk=1')
        if pv:
            options.append('pv=1')
        ko = self.problem.index(board.ko) if board.ko else -1
        return '%d %d %s %s' % (black, white, ','.join(options), self.problem.board_text(board)), ko

    def count(self, board, to_move, s, o, passes=None, bar=None, bar_banned=None, cycle=None, end=None, sekikill=False,
              pv=True, budget=None, escalate=True, record=None, part=None, passed=False):
        #the counted game G(P; s, o) of the design: True / False / None (undecided) for the solver's side with s threats
        #against the opponent's o, its line as [(colour, point, mark)] and the nodes. passes {colour: k} forced passes, bar
        #and bar_banned {colour: points} barred for the whole search or while that colour is under a ko ban, passed: the
        #last move was a voluntary pass, so another one can end the game. An undecided search is retried at ESCALATION
        #times the budget, then expanded by one ply (solvemoves); still undecided it is None and goes on the label's list
        #of undecided searches
        arguments, ko = self.counted_options(board, to_move, s, o, passes, bar, bar_banned, cycle, end, sekikill, pv, passed)
        budget = budget or self.count_budget or self.budget * KO_BUDGET_FACTOR
        key = (to_move, ko, arguments, budget, escalate)
        if key not in self.counts:
            if Solver.deadline and time.time() > Solver.deadline:
                raise TimeoutError('problem time limit')
            nodes, verdict, line = 0, 'U', []
            for factor in ((1, ESCALATION) if escalate else (1,)):
                fields = answer_fields(self.ask('solvex %s %d %d %s' % (to_move, ko, budget * factor, arguments)))
                nodes += int(fields['nodes'])
                verdict = fields['N']
                if verdict != 'U':
                    line = self.line_moves(fields.get('pv', '-'), to_move)
                    break
            if verdict == 'U' and escalate:
                #the children of one ply are smaller searches
                fields = answer_fields(self.ask('solvemoves %s %d %d %s' % (to_move, ko, budget, arguments.replace(',pv=1', ''))))
                nodes += int(fields['nodes'])
                verdict = fields['N']
                if verdict != 'U':
                    want = 'K' if to_move == self.attacker else 'N'
                    for item in ([] if fields['moves'] == '-' else fields['moves'].split(',')):
                        move, result, child_nodes = item.split(':')
                        if result == want:
                            index = int(move.rstrip('!'))
                            line = [(to_move, self.problem.from_index(index), '!' if move.endswith('!') else '')]
                            break
            self.nodes += nodes
            result = None if verdict == 'U' else (verdict == 'K') == (self.solver_color == self.attacker)
            self.counts[key] = (result, line, nodes)
        result, line, nodes = self.counts[key]
        record = record if record is not None else self.undecided
        if result is None and record is not None:
            entry = {'part': part or self.part, 'search': '%s to move s=%d o=%d %s' % (to_move, s, o, arguments.split()[2]), 'nodes': nodes}
            undecided = record['undecided'] if isinstance(record, dict) else record
            if entry not in undecided:
                undecided.append(entry)
        return result, line, nodes

    def counted_moves(self, board, to_move, s, o, passes=None, bar=None, budget=None, bar_banned=None, end=None, passed=False):
        #solvemoves: [(point, mark, mover wins True/False/None, nodes)] for every legal move and the pass of the side to move
        arguments, ko = self.counted_options(board, to_move, s, o, passes, bar, bar_banned, None, end, False, passed=passed)
        budget = budget or self.count_budget or self.budget * KO_BUDGET_FACTOR
        key = ('moves', to_move, ko, arguments, budget)
        if key not in self.counts:
            if Solver.deadline and time.time() > Solver.deadline:
                raise TimeoutError('problem time limit')
            fields = answer_fields(self.ask('solvemoves %s %d %d %s' % (to_move, ko, budget, arguments)))
            self.nodes += int(fields['nodes'])
            moves = []
            for item in ([] if fields['moves'] == '-' else fields['moves'].split(',')):
                move, result, nodes = item.split(':')
                index = int(move.rstrip('!'))
                wins = None if result == 'U' else (result == 'K') == (to_move == self.attacker)
                moves.append((self.problem.from_index(index), '!' if move.endswith('!') else '', wins, int(nodes)))
            self.counts[key] = moves
        return self.counts[key]

    def threshold(self, board, to_move, L=LABEL_LIMIT, start=0, shifted=True, **options):
        #d(P): the least d in -L..L with the solver winning G(P; d+, d-), by galloping from start and bisecting. d is None
        #when the searches leave a bracket, or beyond the limit ('beyond' -1: the solver wins at -L, +1: it fails at L).
        #The shifted check (shifted): the solver still wins with one more threat each and still fails one below (None:
        #undecided)
        results, nodes = {}, 0
        low, high = -L - 1, L + 1 #the solver fails at low and wins at high; the bounds stand for "not known"
        direction_tests = 0
        while high - low > 1:
            open_points = [k for k in range(low + 1, high) if k not in results]
            if not open_points:
                break
            if not results:
                k = max(-L, min(L, start))
            elif low == -L - 1 and high <= L:
                k = max(-L, high - 2 ** (direction_tests - 1)) #galloping down from a win
            elif high == L + 1 and low >= -L:
                k = min(L, low + 2 ** (direction_tests - 1)) #galloping up from a failure
            else:
                middle = (low + high) / 2.0
                k = min(open_points, key=lambda point: (abs(point - middle), point))
            if k in results:
                k = min(open_points, key=lambda point: abs(point - k))
            result, line, spent = self.count(board, to_move, max(k, 0), max(-k, 0), **options)
            nodes += spent
            results[k] = (result, line)
            if result:
                high = k
            elif result is False:
                low = k
            direction_tests = direction_tests + 1 if (low == -L - 1) != (high == L + 1) else 0
        decided = high - low == 1
        record = {'d': None, 'decided': decided, 'beyond': 0, 'bracket': (low + 1, high), 'nodes': nodes,
                  'table': {}, 'pv_win': [], 'pv_fail': [], 'shifted_check': None}
        if decided and high == -L:
            record['beyond'] = -1
        elif decided and low == L:
            record['beyond'] = 1
        elif decided:
            record['d'] = high
        for k in range(-3, 4):
            known = results.get(k, (None, []))[0]
            record['table'][k] = True if k >= high else False if k <= low else known
        if high in results:
            record['pv_win'] = results[high][1]
        if low in results:
            record['pv_fail'] = results[low][1]
        if record['d'] is not None and shifted:
            d = record['d']
            #a consistency check, not a part of the label: no escalation
            wins, line, spent = self.count(board, to_move, max(d, 0) + 1, max(-d, 0) + 1, escalate=False, **options)
            fails, line, more = self.count(board, to_move, max(d - 1, 0) + 1, max(1 - d, 0) + 1, escalate=False, **options)
            record['nodes'] += spent + more
            record['shifted_check'] = None if wins is None or fails is None else bool(wins and not fails)
        return record

    def principal_line(self, board, to_move, d, first=None, plies=12):
        #D2's principal line from a position at threshold d: the solver's moves from its win at d, the opponent's from its
        #win at d-1, so that every move keeps d. It stops after the first ko capture. [(colour, point, mark, board after)]
        #and why it stopped: 'take', 'undecided', 'inconsistent', 'no take'
        line, passes = [], 0
        for ply in range(plies):
            if ply == 0 and first is not None:
                color, p, mark = to_move, first[0], first[1]
            else:
                balance = d if to_move == self.solver_color else d - 1
                result, moves, nodes = self.count(board, to_move, max(balance, 0), max(-balance, 0), passed=passes > 0)
                if result is None:
                    return line, 'undecided'
                if result != (to_move == self.solver_color) or not moves or moves[0][2] in ('fp', 'cycle'):
                    return line, 'inconsistent'
                color, p, mark = moves[0]
            child = self.play(board, color, p, ignore_ko=mark == '!')
            if child is None:
                raise RuntimeError('principal line move %s %s is illegal' % (color, p))
            line.append((color, p, mark, child))
            passes = passes + 1 if p is None else 0
            board, to_move = child, other_colour(color)
            if p is not None and child.ko is not None:
                return line, 'take'
            if passes >= 2 or not any(q in board.stones for q in self.target):
                break
        return line, 'no take'

    def first_take(self, board, to_move, last_move=None, d=None, first=None):
        #D2: the side taking the ko first, the ko (the point to retake, the taking stone), the preparation before it and the
        #positions P_F right after the take and P_other right after the other side's retake, each (board, side to move).
        #From the leaf's ban, else the principal line at d (one search confirms d(P_F) = d), else (no d, or a line that
        #stops before a take) the mode lines. A first move of the side to move (D12) whose line stops leaves the take
        #undecided: the mode lines do not start with it
        take = {'side': None, 'ko': None, 'preparation': [], 'P_F': None, 'P_other': None, 'to_other': None, 'how': None,
                'notes': [], 'confirmed': None, 'first_move': first}
        line, end = self.principal_line(board, to_move, d, first=first) if board.ko is None and d is not None else ([], None)
        if end not in (None, 'take'):
            take['notes'].append('principal line: %s' % end)
        if board.ko is not None:
            take.update(side=other_colour(to_move), ko=(board.ko, last_move), P_F=(board, to_move), how='leaf')
        elif end not in (None, 'take') and first is not None:
            return take
        elif end == 'take':
            color, p, mark, after = line[-1]
            take.update(side=color, ko=(after.ko, p), preparation=[(c, q) for c, q, m, b in line[:-1]], P_F=(after, other_colour(color)),
                        how='line')
            #the other side of the threshold at P_F follows from the line's searches; one search checks it
            balance = d - 1 if color == self.solver_color else d
            result, moves, nodes = self.count(after, other_colour(color), max(balance, 0), max(-balance, 0))
            take['confirmed'] = None if result is None else result == (color != self.solver_color)
            if take['confirmed'] is False:
                take['notes'].append('d(P_F) differs from d(leaf)')
        else:
            #the mode lines: mode D's (the attacker wins it) and mode A's (the defender wins it) must take the same ko first;
            #the preparation comes from the line the taker wins (with one ko shape and no agreement, both captures of it)
            plain = self.modes(board, to_move)
            found = {}
            for mode in ('D', 'A'):
                moves = self.line_moves(plain['pv' + mode], to_move)
                for i, ((color, p, mark), after) in enumerate(zip(moves, self.replay(board, moves))):
                    if p is not None and after.ko is not None:
                        found[mode] = (color, p, [(c, q) for c, q, m in moves[:i]], after)
                        break
            if len(found) < 2 or found['D'][:2] != found['A'][:2]:
                take['notes'].append('the mode lines do not agree on a first take')
                #with one ko shape on the board both sides' captures are still known, so are their profiles
                shapes = self.ko_shapes(board)
                if len(shapes) == 1:
                    holder, stone, point, banned = shapes[0]
                    taken = self.play(board, other_colour(holder), point)
                    retaken = taken and self.play(taken, holder, taken.ko, ignore_ko=True)
                    if taken is not None and taken.ko is not None and retaken is not None and retaken.ko is not None:
                        take['positions'] = {other_colour(holder): (taken, holder), holder: (retaken, other_colour(holder))}
                return take
            color, p, preparation, after = found['D' if found['D'][0] == self.attacker else 'A']
            take.update(side=color, ko=(after.ko, p), preparation=preparation, P_F=(after, other_colour(color)), how='modes')
        after, retaker = take['P_F']
        child = self.play(after, retaker, after.ko, ignore_ko=True)
        if child is not None and child.ko is not None:
            take['P_other'] = (child, take['side'])
            take['to_other'] = [(retaker, after.ko, '!')]
        elif d is not None:
            #the other side's first capture of the ko further on the principal line
            line, end = self.principal_line(after, retaker, d)
            points = set(take['ko'])
            for i, (color, p, mark, later) in enumerate(line):
                if color == retaker and p in points and later.ko is not None:
                    take['P_other'] = (later, other_colour(color))
                    take['to_other'] = [(c, q, m) for c, q, m, b in line[:i + 1]]
                    break
        if take['P_other'] is None:
            take['notes'].append('the other side has no capture of the ko: its count is n/a, taken as 0')
        return take

    def profile(self, board, to_move, holder, K=PASS_LIMIT, Lo=4):
        #D3-D5 for the holder X of the ko at P_X (board, to_move = Y under the ban): t, the least k <= K for which X wins
        #outright (0 threats against Lo) while Y is forced to pass k times, n = t - 1 its approaches, the held-ko check (Y
        #also barred from retaking for the whole search) and v, the threshold with Y forced to pass n times. fill: X's free
        #move on v's line fills the ko (D5), and v_unfilled is v with X barred from the filling point. alternatives: X's
        #first free moves that keep v (v_unfilled after a fill) in v's own game, where Y may answer them, the others in
        #alternatives_rejected; approach: the one the text names (approach_alternatives)
        mover = to_move
        result = {'n': None, 't': None, 'decided': False, 'bracket': None, 'outright': {}, 'moves': [], 'resolving': None,
                  'approach_moves': [], 'approach': None, 'alternatives': None, 'alternatives_rejected': None, 'held_ok': None,
                  'v': None, 'line': [], 'Lo': Lo, 'nodes': 0, 'fill': None, 'v_unfilled': None}
        start = self.nodes
        s, o = (0, Lo) if holder == self.solver_color else (Lo, 0)
        wins = lambda verdict: None if verdict is None else verdict == (holder == self.solver_color)
        low, high, lines = 0, None, {}
        for k in range(1, K + 1):
            verdict, line, nodes = self.count(board, mover, s, o, passes={mover: k}, part='profile %s k=%d' % (holder, k))
            result['outright'][k] = wins(verdict)
            lines[k] = line
            if wins(verdict):
                high = k
                break
            if wins(verdict) is False:
                low = k
        result['bracket'] = (low, high)
        result['decided'] = high is not None and high == low + 1
        if result['decided']:
            result['t'] = high
            result['n'] = high - 1
            result['line'] = lines[high]
            line = lines[high]
            result['moves'] = [line[i + 1][1] for i in range(len(line) - 1) if line[i][2] == 'fp' and line[i + 1][0] == holder][:high]
            #the order of the free moves is the PV's (with Y passing any order works): the last one settles it
            result['resolving'] = result['moves'][-1] if result['moves'] else None
            result['approach_moves'] = result['moves'][:-1]
            if result['n'] >= 1:
                #D4: barred from retaking, Y's rounds must have been its only defence
                verdict, line, nodes = self.count(board, mover, s, o, passes={mover: 1}, bar={mover: [board.ko]} if board.ko else None,
                                                   part='held-ko check %s' % holder)
                result['held_ok'] = wins(verdict)
                #D3: the first free moves that keep the outright win while Y passes, from the first approach position
                first = self.play(board, None, None)
                moves = self.counted_moves(first, holder, s, o, passes={mover: high - 1} if high > 1 else None)
                candidates = [(p, wins_move) for p, mark, wins_move, nodes in moves if wins_move is not False and p is not None]
                if result['held_ok']:
                    self.part = 'direct ko %s' % holder
                    begin = 1 if holder == self.solver_color else 0
                    result['v'] = self.threshold(board, mover, start=begin, passes={mover: result['n']})
                    result['fill'] = self.ko_fill(board, holder, result['v'])
                    if result['fill']:
                        self.part = 'direct ko %s without the fill' % holder
                        result['v_unfilled'] = self.threshold(board, mover, start=begin, passes={mover: result['n']},
                                                              bar={holder: [board.ko]})
                self.part = 'approach alternatives %s' % holder
                result['alternatives'], result['alternatives_rejected'] = self.approach_alternatives(board, holder, result, candidates)
        result['nodes'] = self.nodes - start
        return result

    def approach_alternatives(self, board, holder, profile, candidates):
        #D3: the holder X's first free moves at P_X (board, Y to move under the ban) that keep its outright win while Y must
        #pass (candidates [(point, True | None)], from solvemoves) are approaches only when they also work when Y may answer
        #them: in v's own game (Y's forced pass, X's move, Y free to answer, Y forced to pass for the rounds left; X barred
        #from the filling point after a fill, the reading the approaches belong to) the count after the move must equal v. A
        #move that works only because Y must pass is rejected with its count: the capture played before the approach
        #(10005's E19), a self-atari that the retake captures (13169's D19) or that Y takes later as a local threat (c121's
        #E19). v's own first free move counts v by v's searches. When X passes on v's line (a mutual approach, c081) the
        #candidates' best count stands for v. An undecided count is kept as undecided only while it can still equal the
        #reference; a v beyond the limit against X leaves X no approach, and every candidate is rejected with that bound.
        #profile['approach'] is v's first free move when it is kept, else the first kept move, never a move outside the
        #candidates (7487's A15). ([(point, True | None)], [(point, its count: a number, 'beyond +6', 'at least 2', ... or
        #why)])
        mover = other_colour(holder)
        v = profile['v_unfilled'] if profile['fill'] else profile['v']
        bar = {holder: [board.ko]} if profile['fill'] else None
        if v is None or not v['decided']:
            return [(p, None) for p, wins in candidates], []
        #X's preference: the solver wants a lower count, the opponent a higher one; a count beyond the limit against X means
        #X fails even with that many threats more
        sign = 1 if holder == self.solver_color else -1
        against = lambda value: value[2] and (value[1] is None if sign > 0 else value[0] is None)
        if against(threshold_interval(v)):
            return [], [(p, interval_text(threshold_interval(v))) for p, wins in candidates]
        line = v['pv_win'] if holder == self.solver_color else v['pv_fail']
        first_free = next((line[i + 1][1] for i in range(len(line) - 1) if line[i][2] == 'fp' and line[i + 1][0] == holder), None)
        first = self.play(board, None, None)
        passes = {mover: profile['n'] - 1} if profile['n'] > 1 else None
        counts = {}
        for p, wins in candidates:
            after = self.play(first, holder, p)
            if after is None or self.barred(holder, p, False, {'bar': bar}):
                counts[p] = 'illegal or barred'
            elif p == first_free:
                counts[p] = threshold_interval(v)
            else:
                record = self.threshold(after, mover, start=v['d'] if v['d'] is not None else 0, shifted=False, bar=bar, passes=passes,
                                        escalate=False)
                counts[p] = threshold_interval(record)
        if first_free is None:
            #X passes on v's line: the reference is the candidates' best count, known once no undecided count can beat it
            found = [value for value in counts.values() if not isinstance(value, str)]
            decided = [value for value in found if value[2]]
            if not decided:
                return [(p, None) for p, wins in candidates], []
            reference = min(decided, key=lambda value: count_order(value, sign))
            if any(count_order(value, sign) < count_order(reference, sign) for value in found if not value[2]):
                return [(p, None) for p, wins in candidates], []
        else:
            reference = threshold_interval(v)
        kept, rejected = [], []
        for p, wins in candidates:
            value = counts[p]
            if isinstance(value, str):
                rejected.append((p, value))
            elif value == reference and not against(value):
                kept.append((p, wins))
            elif not value[2] and intervals_meet(value, reference) and not against(reference):
                kept.append((p, None))
            else:
                rejected.append((p, interval_text(value)))
        decided_moves = [p for p, wins in kept if counts[p] == reference]
        profile['approach'] = first_free if first_free in decided_moves else decided_moves[0] if decided_moves else None
        return kept, rejected

    def ko_fill(self, board, holder, v):
        #D5: a free move of the holder X on its winning line behind v that fills the ko at P_X's retake point is a throw-in,
        #not an approach: Y captures the stones it joins, and v counts what follows, which the outright tests do not (Y's
        #threats win it). What follows is named by the solver's class of the position after the capture, with X to move and
        #with Y to move, never by its shape: in 215 and 13169 the corner has the shape of a bent four, but it is a ko that X
        #must start at once (KO, and Y lives if X passes), not BENT4. {point, capture, status, reply} (the class records,
        #undecided ones included) or None
        if board.ko is None or v['d'] is None:
            return None
        line = v['pv_win'] if holder == self.solver_color else v['pv_fail']
        fills = [i for i in range(len(line) - 1) if line[i][2] == 'fp' and line[i + 1][0] == holder and line[i + 1][1] == board.ko]
        if not fills:
            return None
        fill = {'point': board.ko, 'capture': None, 'status': None, 'reply': None}
        filled = self.replay(board, line[:fills[0] + 2])[-1]
        chain, liberties = self.chain_of(filled.stones, board.ko)
        if len(liberties) != 1:
            return fill
        fill['capture'] = next(iter(liberties))
        captured = self.play(filled, other_colour(holder), fill['capture'])
        if captured is None:
            return fill
        for key, to_move in (('status', holder), ('reply', other_colour(holder))):
            record = self.classify(captured, to_move, use_wide=False)
            if record['status'] == STATUS_UNKNOWN or record['open']:
                record = self.classify(captured, to_move, budget=ESCALATION * self.budget, use_wide=False)
            fill[key] = record
            if self.undecided is not None:
                self.undecided.extend(dict(item, part='second ko %s' % item['part']) for item in record['undecided'])
        return fill

    def leaf_moves(self, board, to_move, d):
        #D12: the moves of the side to move that keep d, nearest the target first, at most 3
        balance = d if to_move == self.solver_color else d - 1
        moves = self.counted_moves(board, to_move, max(balance, 0), max(-balance, 0))
        distance = lambda p: min(abs(p[0] - t[0]) + abs(p[1] - t[1]) for t in self.target)
        keep = sorted([(p, mark) for p, mark, wins, nodes in moves if wins],
                      key=lambda item: (item[0] is None, item[0] and distance(item[0])))
        return keep[:3], moves

    def label_parts(self, take, profiles, d_full, approach_rule=True):
        #D6, D9 and the stages of D4: approaches a and extra threats e from the first take, the holders' approach counts
        #and direct-ko values and the full count; a part that is undecided shows as '?' (A?KO?: nothing is known of the
        #approaches either)
        solver = self.solver_color
        kill = solver == self.attacker
        parts = {'label': None, 'besogo': None, 'a': None, 'e': None, 'd_conv': None, 'x': None, 'strict_label': None,
                 'net_label': None, 'mutual': None, 'stages': [], 'notes': [], 'v': None, 'second_ko': None, 'alternative': None}
        if take['side'] is None and not take.get('positions'):
            parts['label'] = 'A?KO?'
            return parts
        counts, signs = {}, {}
        for side in ('S', 'O'):
            profile = profiles[side]
            if profile is None:
                counts[side] = 0
                parts['notes'].append('%s: no capture of the ko, count n/a taken as 0' % side)
            elif not profile['decided']:
                counts[side] = None
                signs[side] = profile['bracket'][0] >= 1
            elif profile['n'] >= 1 and profile['held_ok'] is False:
                counts[side] = 0
                parts['stages'].append({'side': side, 'rounds': profile['n'], 'moves': profile['moves']})
            elif profile['n'] >= 1 and profile['held_ok'] is None:
                counts[side] = None
                signs[side] = True
            else:
                counts[side] = profile['n']
        n_s, n_o = counts['S'], counts['O']
        a = e = None
        approaches_text = None
        if n_s is None or n_o is None:
            if n_s is None and n_o == 0 and signs.get('S'):
                approaches_text = 'A-?'
            elif n_o is None and n_s == 0 and signs.get('O'):
                approaches_text = 'A+?'
            else:
                approaches_text = 'A?'
        elif n_s == 0 and n_o == 0:
            a = 0
            e = None if d_full is None else -d_full
        elif n_o == 0 or n_s == 0:
            side = 'S' if n_s else 'O'
            a = -n_s if n_s else n_o
            profile = profiles[side]
            parts['v'] = profile['v'] and profile['v']['d']
            if side == 'S':
                e_of = lambda v: 1 - v if take['side'] == solver else -v
            else:
                e_of = lambda v: -v if take['side'] == solver else -(v + 1)
            if profile['fill']:
                #D5: the free move fills the ko and a second ko follows. Counted with it the capture is direct (a = 0 and the
                #count's e); the A-form with the fill barred is the other reading (design 13, question 5)
                unfilled = profile['v_unfilled'] and profile['v_unfilled']['d']
                beyond = profile['v_unfilled']['beyond'] if profile['v_unfilled'] else 0
                parts['second_ko'] = dict(profile['fill'], side=side, a=a, v=parts['v'], v_unfilled=unfilled, v_unfilled_beyond=beyond)
                if take['side'] is not None and not beyond:
                    #a v beyond the limit has no label: against X it leaves X no approach in that reading (label_text)
                    parts['alternative'] = besogo_string(a, e_of(unfilled)) if unfilled is not None else besogo_string(a, 0)[:-3] + 'KO?'
                parts['v'] = None
                a = 0
                e = None if d_full is None else -d_full
            elif parts['v'] is not None and take['side'] is not None:
                e = e_of(parts['v'])
        else:
            #D9: neither side's capture threatens anything
            attacker_count = n_s if kill else n_o
            parts['mutual'] = {'S': n_s, 'O': n_o}
            a = -attacker_count if kill else attacker_count
            e = None if d_full is None else -d_full - a
            if attacker_count >= 2:
                parts['notes'].append('mutual approach (the defender needs %d too)' % (n_o if kill else n_s))
            if d_full is not None:
                parts['net_label'] = besogo_string(n_o - n_s, -d_full - (n_o - n_s))
        if take['side'] is None:
            e = None #without the first take only the approaches are known
        if a is not None and d_full is not None:
            parts['strict_label'] = besogo_string(a, -d_full - a)
            if not approach_rule:
                e = -d_full - a
        parts['a'], parts['e'] = a, e
        if a is not None and e is not None:
            parts['d_conv'] = -(a + e)
            parts['x'] = None if take['side'] is None else e - (0 if take['side'] == solver else -1)
        if parts['mutual'] and abs(a) == 1:
            parts['label'] = STATUS_TEN_THOUSAND
            parts['besogo'] = {'type': STATUS_TEN_THOUSAND, 'a_form': besogo_string(a, e) if e is not None else None}
        elif a is not None and e is not None:
            parts['label'] = besogo_string(a, e)
            parts['besogo'] = {'type': 'KO', 'approaches': a, 'extraThreats': e}
        else:
            if approaches_text is None:
                approaches_text = besogo_string(a, 0)[:-3]
            parts['label'] = approaches_text + 'KO' + ('?' if e is None else besogo_string(0, e)[2:])
        return parts

    def area_label(self, board, to_move, last_move=None, approach_rule=True):
        #the label in this solver's area (design 4, steps 1-5): the record of design 7.1 with what the core computes
        record = {'label': None, 'class': None, 'class_inferred': False, 'class_open': [], 'class_open_tests': [], 'besogo': None,
                  'count': None, 'd_conv': None,
                  'first_take': None, 'approaches': None, 'direct_ko': None, 'gain': None, 'strict_label': None,
                  'net_label': None, 'second_ko': None, 'alternative': None, 'special': None, 'area': None, 'a': None, 'e': None,
                  'extra_threats': None, 'warnings': [], 'undecided': [], 'nodes': 0, 'seconds': 0, 'solver': self.solver_color,
                  'goal': 'KILL' if self.solver_color == self.attacker else 'LIVE'}
        start, started = self.nodes, time.time()
        self.undecided = record['undecided']
        self.label_state = None
        try:
            self.compute_label(board, to_move, last_move, approach_rule, record)
        finally:
            self.undecided = None
            record['nodes'] = self.nodes - start
            record['seconds'] = round(time.time() - started, 2)
        return record

    def compute_label(self, board, to_move, last_move, approach_rule, record):
        solver = self.solver_color
        goal = 'KILL' if solver == self.attacker else 'LIVE'
        #step 1: the class, once more at ESCALATION times the budget when it or one of its tests is undecided
        self.part = 'class'
        start = self.nodes
        status = self.classify(board, to_move, use_wide=False)
        if status['undecided']:
            status = self.classify(board, to_move, budget=ESCALATION * self.budget, use_wide=False)
        record['class'] = status['status']
        record['class_open'] = list(status['open'])
        record['class_open_tests'] = list(status['open_tests'])
        record['class_tests'] = {'modes': status['modes'], 'tests': status['tests'], 'nodes': self.nodes - start}
        record['warnings'].extend(status['warnings'])
        record['undecided'].extend(status['undecided'])
        if status['status'] in SPECIAL_STATUSES:
            record['label'] = record['special'] = status['status']
            record['besogo'] = {'type': parse_status(status['status'])['type']}
            return
        if status['status'] in (STATUS_DEAD, STATUS_ALIVE):
            last_mover = other_colour(to_move) if last_move is not None else None
            if ko_taken_by(board, last_mover, solver, status['status']) == STATUS_KO:
                record['label'] = 'KO+'
                record['besogo'] = {'type': 'KO', 'approaches': 0, 'extraThreats': 0}
                record['warnings'].append("the solver's clean result: %s" % status['status'])
            else:
                record['label'] = status['status']
                record['besogo'] = {'type': status['status']}
            return
        if status['status'] == STATUS_UNKNOWN and not self.ko_shapes(board):
            record['label'] = STATUS_UNKNOWN
            return
        #steps 2 and 3: with a ban the first take is the leaf's and the count comes after the profiles (budget order)
        count = None
        if board.ko is not None:
            start = self.nodes
            takes = [self.first_take(board, to_move, last_move)]
        else:
            self.part = 'count'
            count = self.threshold(board, to_move)
            self.part = 'first take'
            start = self.nodes
            if count['d'] is not None:
                keep, moves = self.leaf_moves(board, to_move, count['d'])
                takes = [self.first_take(board, to_move, d=count['d'], first=move) for move in (keep if len(keep) > 1 else [None])]
            else:
                takes = [self.first_take(board, to_move)]
        take_nodes = self.nodes - start
        #step 4: the holders' profiles, for every candidate first move of the side to move (D12)
        Lo = 4 if count is None or count['d'] is None else min(15, max(4, abs(count['d']) + 2))
        candidates = [(take, self.take_profiles(take, Lo)) for take in takes]
        if count is None:
            self.part = 'count'
            count = self.threshold(board, to_move)
            if count['d'] is not None and abs(count['d']) + 2 > Lo:
                #the outright tests must stand against as many threats as the count can use
                Lo = min(15, abs(count['d']) + 2)
                candidates = [(take, self.take_profiles(take, Lo)) for take, profiles in candidates]
        record['count'] = {'d_full': count['d'], 'decided': count['decided'], 'beyond': count['beyond'], 'bracket': count['bracket'],
                           'table': count['table'], 'shifted_check': count['shifted_check'], 'nodes': count['nodes'],
                           'pv_win': self.line_names(count['pv_win']), 'pv_fail': self.line_names(count['pv_fail'])}
        if count['shifted_check'] is False:
            record['warnings'].append('the count depends on the threat counts themselves, not only on their balance')
        if count['beyond']:
            record['warnings'].append('the count is beyond %+d' % (count['beyond'] * LABEL_LIMIT))
        #step 5: the label of each candidate, the best for the side to move (D12)
        labelled = [(take, profiles, self.label_parts(take, profiles, count['d'], approach_rule)) for take, profiles in candidates]
        take, profiles, parts = labelled[0]
        decided = [item for item in labelled if '?' not in item[2]['label']]
        if len(labelled) > 1:
            if len(decided) < len(labelled):
                record['warnings'].append('a first move of the side to move has an undecided label')
            #the best for the side to move: nothing better for the solver, or nothing worse for the opponent
            for item in decided:
                for other in decided:
                    first, second = (other, item) if to_move == solver else (item, other)
                    if better(first[2]['label'], second[2]['label'], goal):
                        break
                else:
                    take, profiles, parts = item
                    break
        record['label'] = parts['label']
        record['besogo'] = parts['besogo']
        record['a'], record['e'] = parts['a'], parts['e']
        record['d_conv'] = parts['d_conv']
        record['extra_threats'] = parts['x']
        record['strict_label'] = parts['strict_label']
        record['net_label'] = parts['net_label']
        record['warnings'].extend(parts['notes'])
        if parts['second_ko']:
            #D5: the label counts the second ko after the fill; the other reading, the fill barred, comes with its own full
            #count
            second = parts['second_ko']
            holder = solver if second['side'] == 'S' else other_colour(solver)
            self.part = 'count without the fill'
            unfilled = self.threshold(board, to_move, bar={holder: [second['point']]})
            record['second_ko'] = dict({key: second[key] for key in ('side', 'a', 'v', 'v_unfilled', 'v_unfilled_beyond')}, holder=holder,
                                       point=self.point_name(second['point']),
                                       capture=self.point_name(second['capture']) if second['capture'] else None)
            for key in ('status', 'reply'):
                #the solver's classes after the capture, with the holder and with the other side to move
                found = second[key]
                record['second_ko'].update({key: found and found['status'], key + '_open': found and list(found['open']),
                                            key + '_text': found and status_text(found)})
            record['alternative'] = {'label': parts['alternative'], 'count': unfilled['d'], 'beyond': unfilled['beyond'],
                                     'bracket': unfilled['bracket'], 'v_beyond': second['v_unfilled_beyond'],
                                     'strict_label': None if unfilled['d'] is None else besogo_string(second['a'], -unfilled['d'] - second['a']),
                                     'reading': '%s may not fill the ko at %s' % (COLOUR_NAMES[holder], record['second_ko']['point'])}
            record['warnings'].append('second ko after the capture: %s; the label counts it, %s (design 13, question 5)' % (
                fill_text(record['second_ko'], self.attacker), '%s is the label without it' % parts['alternative'] if parts['alternative'] else
                'without it the direct-ko value is beyond %+d' % (second['v_unfilled_beyond'] * LABEL_LIMIT)))
        record['first_take'] = {'side': take['side'], 'point': self.point_name(take['ko'][1]) if take['ko'] else None,
                                'retake_point': self.point_name(take['ko'][0]) if take['ko'] else None, 'how': take['how'],
                                'preparation': [(c, self.point_name(p)) for c, p in take['preparation']], 'confirmed': take['confirmed'],
                                'notes': take['notes'], 'nodes': take_nodes,
                                'candidates': None if len(labelled) == 1 else
                                [{'move': self.point_name(t['first_move'][0]), 'label': p['label']} for t, f, p in labelled]}
        record['approaches'] = {side: self.profile_summary(profiles[side]) for side in ('S', 'O')}
        side = 'S' if parts['a'] is not None and parts['a'] < 0 else 'O' if parts['a'] else None
        side = None if parts['mutual'] else side
        v = profiles[side]['v'] if side and profiles[side] else None
        record['direct_ko'] = {'v': parts['v'], 'side': side, 'v_line': self.line_names(v['pv_win']) if v else None, 'line': None,
                               'local_threats': None, 'stages': [dict(stage, kind='held-ko check') for stage in parts['stages']],
                               'attribution': None}
        record['gain'] = gain_record(record, count['d'], side)
        #steps 6-8 run on the area the label comes from (explain_label)
        self.label_state = {'board': board, 'to_move': to_move, 'take': take, 'profiles': profiles, 'parts': parts, 'count': count,
                            'approach_side': side}
        if record['class'] == STATUS_UNKNOWN:
            if all(profiles[side] and profiles[side]['t'] for side in ('S', 'O')):
                record['class'] = STATUS_KO
                record['class_inferred'] = True
            else:
                record['warnings'].append('class undecided')
        for side in ('S', 'O'):
            for p in (profiles[side] or {}).get('moves') or []:
                if self.at_wall(p):
                    record['warnings'].append('%s approach move %s touches the area wall' % (side, self.point_name(p)))

    def take_profiles(self, take, Lo):
        #both holders' profiles for a first take: S's from P_S, O's from P_O (None when that side has no capture of the ko)
        profiles = {'S': None, 'O': None}
        if take['side'] is None and not take.get('positions'):
            return profiles
        for side, color in (('S', self.solver_color), ('O', other_colour(self.solver_color))):
            if take['side'] is None:
                position = take['positions'][color]
            else:
                position = take['P_F'] if take['side'] == color else take['P_other']
            if position is not None:
                self.part = 'profile %s' % side
                profiles[side] = self.profile(position[0], position[1], color, Lo=Lo)
        return profiles

    def profile_summary(self, profile):
        if profile is None:
            return None
        names = lambda points: [self.point_name(p) for p in points]
        return {'n': profile['n'], 't': profile['t'], 'decided': profile['decided'], 'bracket': profile['bracket'],
                'outright': profile['outright'], 'moves': names(profile['moves']), 'resolving': self.point_name(profile['resolving']),
                'approach_moves': names(profile['approach_moves']),
                'approach': self.point_name(profile['approach']) if profile['approach'] else None,
                'alternatives': None if profile['alternatives'] is None else
                ['%s%s' % (self.point_name(p), '' if wins else '?') for p, wins in profile['alternatives']],
                'alternatives_rejected': None if profile['alternatives_rejected'] is None else
                [(self.point_name(p), value) for p, value in profile['alternatives_rejected']],
                'held_ok': profile['held_ok'], 'v': profile['v'] and profile['v']['d'],
                'v_decided': profile['v'] and profile['v']['decided'], 'v_beyond': profile['v'] and profile['v']['beyond'],
                'v_table': profile['v'] and profile['v']['table'],
                'fill': self.point_name(profile['fill']['point']) if profile['fill'] else None,
                'v_unfilled': profile['v_unfilled'] and profile['v_unfilled']['d'],
                'outright_line': self.line_names(profile['line']), 'line': None, 'Lo': profile['Lo'], 'nodes': profile['nodes']}

    def point_name(self, p):
        if p is None:
            return 'pass'
        return 'ABCDEFGHJKLMNOPQRST'[p[0]] + str(self.problem.size - p[1])

    def line_names(self, moves):
        #a line as text: B A19, W B19! (a retake against the ban), fp (a forced pass), cycle
        out = []
        for color, p, mark in moves or []:
            out.append(mark if mark in ('fp', 'cycle') else '%s %s%s' % (color, self.point_name(p), mark))
        return ', '.join(out)

    def at_wall(self, p):
        #a point next to the outside of the area or to its fringe
        return p is not None and any(q not in self.problem.region or q in self.problem.fringe for q in self.problem.board.neighbours(p))

    def ko_label(self, board, to_move, last_move=None, approach_rule=True, start=None, explain=True):
        #the label of a leaf (design 4 and 7), in the wider area and in this one: the wider area's label when it is
        #decided, else this one's; a count undecided there comes from the other area, and a decided label that differs
        #between the areas is flagged. explain: steps 6-8 (attribution, gains, lines) and the text on the area the label
        #comes from; start: the moves from the problem's start to the leaf, for the lines' diagrams (research/report.py)
        areas = [('wide', self.wide), ('narrow', self)] if self.wide is not None and self.wide is not self else [('narrow', self)]
        records = [(name, solver.area_label(board, to_move, last_move, approach_rule)) for name, solver in areas]
        #a decided label first, else a decided class, the wider area on a tie
        decided = lambda record: ('?' not in record['label'], record['class'] not in (None, STATUS_UNKNOWN))
        name, main = max(records, key=lambda item: decided(item[1]) + (item[0] == 'wide',))
        result = dict(main, warnings=list(main['warnings']), undecided=[dict(item, area=name) for item in main['undecided']])
        result['area'] = {part: name for part in ('label', 'class', 'count', 'first_take', 'approaches', 'direct_ko', 'gain', 'lines')}
        result['areas'] = {other: record['label'] for other, record in records}
        for other, record in records:
            if other == name:
                continue
            result['undecided'] += [dict(item, area=other) for item in record['undecided']]
            if '?' not in record['label'] and record['label'] != main['label']:
                result['warnings'].append('the %s area gives %s' % (other, record['label']))
            if main['count'] is not None and not main['count']['decided'] and record['count'] is not None and record['count']['decided']:
                result['count'] = record['count']
                result['area']['count'] = other
                d, a = record['count']['d_full'], main['a']
                if d is not None and a is not None:
                    result['strict_label'] = besogo_string(a, -d - a)
                    result['gain'] = gain_record(result, d, (main['direct_ko'] or {}).get('side'))
        result['nodes'] = sum(record['nodes'] for other, record in records)
        result['seconds'] = round(sum(record['seconds'] for other, record in records), 2)
        result['start'] = start
        result['lines'] = []
        if explain:
            solver = dict(areas)[name]
            started, nodes, undecided = time.time(), solver.nodes, []
            solver.undecided = undecided
            try:
                solver.explain_label(result, start)
            finally:
                solver.undecided = None
                result['undecided'] += [dict(item, area=name) for item in undecided]
                result['explain_nodes'] = solver.nodes - nodes
                result['nodes'] += result['explain_nodes']
                result['seconds'] = round(result['seconds'] + time.time() - started, 2)
        result['text'] = label_text(result)
        return result

    def line_roles(self, board, to_move, moves, ko=None, resolved=False, fixed=None):
        #Design 1's events for the moves of a line from (board, to_move); ko: the main ko's two points, resolved: the line
        #ends because the ko is over, fixed: {index: role} for moves placed by construction (a gain move). Roles: takes the
        #ko, retake after a threat, free retake, second ko, elsewhere (approach round), elsewhere, approach, local threat and
        #its answer, answer (it saves a chain from an atari, as after a direct capture), extra move (stage), gain move,
        #captures (target stones), resolves the ko (the last move, when the ko is over), preparation, local move
        ko = set(ko or ())
        boards = [board] + self.replay(board, moves)
        roles, taken = [], board.ko is not None and board.ko in ko
        for i, (color, p, mark) in enumerate(moves):
            if mark == 'cycle':
                roles.append('repetition')
                continue
            before, after = boards[i], boards[i + 1]
            captured = [q for q in before.stones if q not in after.stones]
            if mark == 'fp':
                role = 'elsewhere (approach round)'
            elif p is None:
                role = 'elsewhere'
            elif after.ko is not None and p in ko:
                role = 'retake after a threat' if mark == '!' else 'free retake' if taken else 'takes the ko'
                taken = True
            elif after.ko is not None:
                role = 'second ko'
            elif any(q in self.target for q in captured):
                role = 'captures'
            elif i > 0 and moves[i - 1][2] == 'fp':
                role = 'approach'
            elif before.ko is not None and self.saves(before, color, p):
                role = 'answer'
            else:
                role = ''
            roles.append(role)
        #a move of the side under a ban that the other side answers locally is a local threat (the free retake follows)
        for i in range(len(roles) - 1):
            if roles[i] == '' and boards[i].ko is not None and roles[i + 1] in ('', 'answer') and moves[i + 1][1] is not None:
                roles[i], roles[i + 1] = 'local threat', 'answer'
        #a non-capturing move of X after X's own direct capture (answered) and before the other side's free retake: a stage
        for i in range(2, len(roles) - 1):
            if roles[i] == '' and moves[i][0] == moves[i - 2][0] and roles[i - 2] in ('takes the ko', 'free retake', 'retake after a threat') \
                    and roles[i - 1] == 'answer' and roles[i + 1] == 'free retake' and len(boards[i + 1].stones) > len(boards[i].stones):
                roles[i] = 'extra move (stage)'
        for i, role in (fixed or {}).items():
            roles[i] = role
        if resolved and roles and roles[-1] in ('', 'second ko'):
            roles[-1] = 'resolves the ko'
        first = next((i for i, role in enumerate(roles) if role in ('takes the ko', 'free retake', 'retake after a threat')), None)
        return [role or ('preparation' if first is not None and i < first and not (board.ko is not None and board.ko in ko) else 'local move')
                for i, role in enumerate(roles)]

    def line_record(self, name, board, to_move, moves, balance, result, ko=None, fixed=None, stop=None, start=None, caption=None):
        #a line of the report (7.2) from the leaf: its moves as research/report.py's diagrams take them ('pass' for a move
        #elsewhere, 'A18!' a retake after a threat), the role of each, the numbered text, and a diagram spec numbered from 1
        solver = self.solver_color
        roles = self.line_roles(board, to_move, moves, ko, resolved=stop == 'ko over', fixed=fixed)
        boards = [board] + self.replay(board, moves)
        captures = [[self.point_name(q) for q in sorted(before.stones) if q not in after.stones] for before, after in zip(boards, boards[1:])]
        tokens, colours, texts, threats_used = [], [], [], {}
        for i, ((color, p, mark), role) in enumerate(zip(moves, roles)):
            if mark == 'cycle':
                texts.append('(the position repeats)')
                break
            tokens.append('pass' if p is None else self.point_name(p) + ('!' if mark == '!' else ''))
            colours.append(color)
            if role == 'retake after a threat':
                threats_used[color] = threats_used.get(color, 0) + 1
                role = 'threat %d' % threats_used[color]
            if p is None:
                texts.append('%d %s elsewhere%s' % (i + 1, color, ' (approach round)' if mark == 'fp' else ''))
            else:
                texts.append('%d %s %s%s (%s)' % (i + 1, color, self.point_name(p), '!' if mark == '!' else '', role))
        winner = None if result is None else solver if result else other_colour(solver)
        diagram = {'moves': ' '.join(tokens), 'first': 1, 'caption': caption or name}
        if start is not None:
            diagram['start'] = start
        return {'name': name, 'moves': diagram['moves'], 'colours': colours, 'roles': roles[:len(tokens)], 'captures': captures[:len(tokens)],
                'text': ', '.join(texts),
                'balance': balance, 'threats': {'S': max(balance, 0), 'O': max(-balance, 0)}, 'winner': winner, 'stop': stop,
                'decided': winner is not None and stop not in ('undecided', 'no move on the search line')
                and not str(stop).startswith('the result'), 'diagram': diagram}

    def built_line(self, name, leaf, prefix, position, balance, ko, fixed=None, passes=None, start=None, caption=None, **options):
        #a line from the leaf: the moves of prefix (the first take, the approach rounds), then counted_line's walk from
        #position at the solver's balance there; the balance at the leaf adds the threats the prefix's retakes spend
        board, to_move = position
        walk = self.walk_line(board, to_move, max(balance, 0), max(-balance, 0), passes=passes, **options)
        cost = sum(1 if color == self.solver_color else -1 for color, p, mark in prefix if mark == '!')
        record = self.line_record(name, leaf[0], leaf[1], prefix + walk['moves'], balance + cost, walk['result'], ko, fixed,
                                  walk['stop'], start, caption)
        record['prefix'] = len(prefix)
        return record

    def take_prefix(self, take, color):
        #the moves from the leaf to the position right after color took the ko (P_S or P_O): the preparation, the first take
        #and, for the other side, the moves to its retake; None when they are not known
        if take['side'] is None:
            return None
        moves = [(c, p, '') for c, p in take['preparation']]
        if take['how'] != 'leaf':
            moves.append((take['side'], take['ko'][1], ''))
        if color != take['side']:
            if take['to_other'] is None:
                return None
            moves += take['to_other']
        return moves

    def local_threats(self, board, to_move, x, d, lines, options):
        #D7: the local threats of the side holding the extra threats x (> 0 the solver's, < 0 the opponent's) in the direct
        #ko (board, to_move, options: the forced passes behind v) with threshold d. Its candidate moves are barred
        #cumulatively while it is under a ban (kb/kw: they stay playable otherwise, as a move it needs later must), and the
        #balance its win needs is tested again: each unit change of d is one threat, and the points barred for it are its
        #forms (a point the change does not need is unbarred again). Candidates: its moves under a ban that are neither ko
        #captures nor saves of its own chains from an atari, on the winners' lines at d and d-1 (lines) and on those of
        #every test, and when these run out on counted_line where it wins (there the loser retakes when it can afford to,
        #so the side is under a ban). {side, threats [{side, points, answers, units, d_before, d_after}], d_after,
        #units_left, tests, nodes}
        solver = self.solver_color
        side = solver if x > 0 else other_colour(solver)
        sign = 1 if side == solver else -1
        winning = lambda d_now: d_now if side == solver else d_now - 1
        #a bar that leaves a test undecided is kept and the next candidate added, so the tests are not escalated
        counted = lambda balance, bars: self.count(board, to_move, max(balance, 0), max(-balance, 0), bar_banned={side: list(bars)},
                                                   escalate=False, **options)
        answers, order = {}, []
        def collect(moves):
            boards = [board] + self.replay(board, moves)
            for i, (color, p, mark) in enumerate(moves[:len(boards) - 1]):
                if color != side or p is None or mark != '' or boards[i].ko is None or boards[i + 1].ko is not None:
                    continue
                if self.saves(boards[i], color, p) or p in answers:
                    continue
                reply = moves[i + 1] if i + 1 < len(moves) else None
                answers[p] = reply[1] if reply and reply[0] == other_colour(side) and reply[2] == '' else None
                order.append(p)
        for moves in lines:
            collect(moves)
        start = self.nodes
        barred, group, found, tested, walked = [], [], [], set(), set()
        d_now, tests = d, 0
        while sum(threat['units'] for threat in found) < abs(x) and tests < THREAT_TESTS:
            fresh = [p for p in order if p not in tested]
            if not fresh:
                if tuple(barred) in walked:
                    break
                walked.add(tuple(barred))
                balance = winning(d_now)
                walk = self.walk_line(board, to_move, max(balance, 0), max(-balance, 0), bar_banned={side: list(barred)}, **options)
                collect(walk['moves'])
                continue
            p = fresh[0]
            tested.add(p)
            barred.append(p)
            group.append(p)
            tests += 1
            results = {}
            for balance in (d_now, d_now - 1):
                results[balance] = counted(balance, barred)
                collect(results[balance][1])
            result = results[winning(d_now)][0]
            if result is None or result == (side == solver):
                continue
            #the side no longer wins at its balance: one unit when it still wins one step further
            further, line, nodes = counted(winning(d_now) + sign, barred)
            collect(line)
            if further is None:
                break
            step = 1
            if further != (side == solver):
                record = self.threshold(board, to_move, start=d_now + 2 * sign, shifted=False, bar_banned={side: list(barred)},
                                        escalate=False, **options)
                if record['d'] is None:
                    break
                step = abs(record['d'] - d_now)
            #the forms: the points of the group whose bar the change needs (kept, and marked, when the test is undecided)
            open_forms = []
            for q in list(group):
                rest = [r for r in barred if r != q]
                result, line, nodes = counted(winning(d_now), rest)
                if result is None:
                    open_forms.append(q)
                elif result != (side == solver):
                    barred.remove(q)
                    group.remove(q)
            touching = [q for q in group + [answers.get(q) for q in group] if self.at_wall(q)]
            found.append({'side': side, 'points': [self.point_name(q) for q in group],
                          'answers': {self.point_name(q): self.point_name(answers[q]) for q in group if answers.get(q) is not None},
                          'units': step, 'd_before': d_now, 'd_after': d_now + sign * step,
                          'forms_undecided': [self.point_name(q) for q in open_forms],
                          'wall': [self.point_name(q) for q in dict.fromkeys(touching)]})
            d_now += sign * step
            group = []
        return {'side': side, 'threats': found, 'd_after': d_now, 'units_left': abs(x) - sum(threat['units'] for threat in found),
                'barred': [self.point_name(q) for q in barred], 'tests': tests, 'nodes': self.nodes - start}

    def approach_rounds(self, start, holder, profile):
        #D8's conventional approach line from P_X = start (the holder X just took the ko, Y to move under the ban): in each of
        #X's n rounds Y plays elsewhere (forced), X approaches, Y retakes for free, and before the next round X retakes after
        #a threat. The search of R_i with Y forced to pass for the rounds left, at X's winning balance with the direct-ko
        #value v, validates the round; X's approach a_i is an approach in the outright sense (round_approach).
        #{rounds: [{start, approach, nodes}], moves, end: (board, X) after the last free retake, ko, broken}
        board, other = start
        n, v = profile['n'], profile['v']['d']
        solver = self.solver_color
        ko = self.ko_points(board)
        result = {'rounds': [], 'moves': [], 'end': None, 'ko': ko, 'broken': None}
        if ko is None or v is None:
            result['broken'] = 'no ko at P_X' if ko is None else 'the direct-ko value is undecided'
            return result
        retake, stone = ko
        win = v if holder == solver else v - 1
        for i in range(1, n + 1):
            self.part = 'approach round %d' % i
            verdict, pv, nodes = self.count(board, other, max(win, 0), max(-win, 0), passes={other: n - i + 1})
            if verdict != (holder == solver):
                result['broken'] = 'round %d: the holder does not win R_%d at the direct-ko value' % (i, i)
                break
            approach = self.round_approach(board, other, holder, i, profile, win, pv, ko)
            if approach is None:
                result['broken'] = 'round %d: no approach keeps the outright win and the direct-ko value' % i
                break
            after = self.play(self.play(board, other, None), holder, approach)
            retaken = after and self.play(after, other, retake)
            if retaken is None or retaken.ko != stone:
                result['broken'] = 'round %d: no free retake after the approach' % i
                break
            result['rounds'].append({'start': (board, other), 'approach': approach, 'nodes': nodes})
            result['moves'] += [(other, None, 'fp'), (holder, approach, ''), (other, retake, '')]
            if i == n:
                result['end'] = (retaken, holder)
                break
            board = self.play(retaken, holder, stone, ignore_ko=True)
            if board is None or board.ko != retake:
                result['broken'] = 'round %d: no retake after the free retake' % i
                break
            result['moves'].append((holder, stone, '!'))
        return result

    def round_approach(self, board, other, holder, i, profile, win, pv, ko):
        #X's approach in round i at R_i (board, Y = other to move): an approach in the outright sense, never a move that
        #only keeps the count (7487's A15). Round 1 takes the one the alternatives test names; otherwise the search line's
        #move when it keeps X's outright win (0 threats against Lo, Y forced to pass for this round, the rounds left and the
        #settling move), else the first move that keeps it and X's win at the round's balance; None when none does
        if i == 1 and profile['approach'] is not None:
            return profile['approach']
        n = profile['n']
        first = self.play(board, other, None)
        s, o = (0, profile['Lo']) if holder == self.solver_color else (profile['Lo'], 0)
        outright = [p for p, mark, wins, nodes in self.counted_moves(first, holder, s, o, passes={other: n - i + 1})
                    if wins and p is not None and p not in ko]
        searched = pv[1][1] if len(pv) >= 2 and pv[0][2] == 'fp' and pv[1][0] == holder else None
        if searched in outright:
            return searched
        for p in outright:
            verdict, line, nodes = self.count(self.play(first, holder, p), other, max(win, 0), max(-win, 0),
                                              passes={other: n - i} if n > i else None)
            if verdict == (holder == self.solver_color):
                return p
        return None

    def round_position(self, rounds, i, holder, n, move):
        #round i with Y's move (None: the forced tenuki) in place of the tenuki, then X's approach a_i, Y's free retake and,
        #before the last round, X's retake after a threat: (moves, board, side to move, forced passes, threat offset), or
        #why the move leaves no room for the round
        board, other = rounds['rounds'][i - 1]['start']
        approach = rounds['rounds'][i - 1]['approach']
        retake, stone = rounds['ko']
        first = self.play(board, other, move)
        if first is None:
            return 'illegal'
        after = self.play(first, holder, approach)
        if after is None:
            return 'the approach is no longer possible'
        retaken = self.play(after, other, retake)
        if retaken is None or retaken.ko != stone:
            return 'no free retake after the approach'
        moves = [(other, move, 'fp' if move is None else ''), (holder, approach, ''), (other, retake, '')]
        if i == n:
            return moves, retaken, holder, None, 0
        again = self.play(retaken, holder, stone, ignore_ko=True)
        if again is None or again.ko != retake:
            return 'no retake for the next round'
        return moves + [(holder, stone, '!')], again, other, {other: n - i}, 1 if holder == self.solver_color else -1

    def round_gains(self, rounds, holder, n, candidates):
        #D8: per approach round i, the opponent Y's local move m in place of its forced tenuki against the tenuki itself:
        #g_i(m) = d(R_i, m, a_i, Y's free retake) - d(R_i, pass, a_i, Y's free retake), from Y's side. Before the last
        #round X then retakes after a threat and Y's forced passes stay for the rounds left (d of that position plus the
        #retake's threat). Candidates: Y's moves on the full count's lines, then the legal moves nearest the target, at most
        #6. A threshold decided beyond the limit is a bound ('beyond +6'), not a value; when the round with the tenuki is
        #beyond in X's favour, Y's free retake loses outright: the round fails (fails), Y must answer a_i (a sente approach,
        #design risk 2) and there is no gain to measure. [{round, value (the best gain, 0 for none), move, d_with, d_without,
        #base, fails, tried, positions: {move: (moves, position, forced passes, threshold)}}]
        solver = self.solver_color
        other = other_colour(holder)
        sign = 1 if other != solver else -1
        distance = lambda p: min(abs(p[0] - t[0]) + abs(p[1] - t[1]) for t in self.target)
        gains = []
        for i in range(1, len(rounds['rounds']) + 1):
            self.part = 'gain round %d' % i
            board, to_move = rounds['rounds'][i - 1]['start']
            nearest = sorted((p for p in self.problem.region if p not in board.stones and p not in rounds['ko']
                              and self.play(board, other, p) is not None), key=lambda p: (distance(p), p))
            tried_moves = [p for p in candidates if p in nearest] + nearest
            tried_moves = list(dict.fromkeys(tried_moves))[:6]
            values, positions, beyond = {}, {}, {}
            for move in [None] + tried_moves:
                position = self.round_position(rounds, i, holder, n, move)
                if isinstance(position, str):
                    values[move] = position
                    continue
                moves, after, side, passes, offset = position
                begin = 0 if None not in values or not isinstance(values[None], int) else values[None] - offset
                record = self.threshold(after, side, start=begin, shifted=False, passes=passes)
                if record['d'] is not None:
                    values[move] = record['d'] + offset
                elif record['beyond']:
                    values[move] = 'beyond %+d' % (record['beyond'] * LABEL_LIMIT + offset)
                    beyond[move] = record['beyond']
                else:
                    values[move] = None
                positions[move] = (moves, (after, side), passes, record['d'])
            base = values[None]
            entry = {'round': i, 'value': None, 'move': None, 'd_without': base if isinstance(base, int) else None, 'd_with': None,
                     'base': base, 'fails': None in beyond and (beyond[None] > 0) == (holder != solver),
                     'tried': [{'move': self.point_name(m), 'value': (values[m] - base) * sign if isinstance(values[m], int) and isinstance(base, int)
                                else values[m]} for m in tried_moves], 'positions': positions}
            if isinstance(base, int):
                best = max((m for m in tried_moves if isinstance(values[m], int)), key=lambda m: (values[m] - base) * sign, default=None)
                entry['value'] = 0
                if best is not None and (values[best] - base) * sign > 0:
                    entry.update(value=(values[best] - base) * sign, move=best, d_with=values[best])
            gains.append(entry)
        return gains

    def explain_label(self, record, start=None):
        #design 4, steps 6-8, on the area the label comes from: the attribution of the extra threats (D7) with the stage
        #fallback, the gains of the approach rounds (D8) and the lines of 7.2 (approach, direct ko, gain and its no-gain
        #twin), each built by counted_line from decided positions; the record's count may come from the other area
        state = self.label_state
        if state is None or record.get('special') or record['label'] in (None, STATUS_UNKNOWN, STATUS_DEAD, STATUS_ALIVE):
            return
        board, take, profiles, parts = state['board'], state['take'], state['profiles'], state['parts']
        solver, leaf = self.solver_color, (state['board'], state['to_move'])
        x, d_leaf, side = parts['x'], state['count']['d'], state['approach_side']
        holder = None if side is None else solver if side == 'S' else other_colour(solver)
        profile = profiles[side] if side else None
        ko = self.ko_points(take['P_F'][0]) if take['P_F'] else None
        attribution = record['direct_ko']['attribution'] = {'x': x, 'position': None, 'd': None}
        winning = lambda color: (lambda d: d if color == solver else d - 1)
        #the direct ko: the leaf for a direct ko, else P_X with Y forced to pass for the approaches (the position behind v)
        rounds, position, options, d, lines = None, None, {}, None, []
        if side is None and not parts['mutual']:
            position, d, lines = leaf, d_leaf, [state['count']['pv_win'], state['count']['pv_fail']]
            attribution['position'] = 'the leaf'
        elif side is not None and profile['v'] is not None:
            P_X = take['P_F'] if take['side'] == holder else take['P_other']
            position, d, lines = P_X, profile['v']['d'], [profile['v']['pv_win'], profile['v']['pv_fail']]
            options = {'passes': {other_colour(holder): profile['n']}}
            attribution['position'] = 'P_%s with %d forced pass%s' % (side, profile['n'], 'es' if profile['n'] > 1 else '')
            prefix = self.take_prefix(take, holder)
            if P_X is not None and prefix is not None:
                rounds = self.approach_rounds(P_X, holder, profile)
                rounds['prefix'] = prefix
                if rounds['broken']:
                    record['warnings'].append('approach line: %s' % rounds['broken'])
        attribution['d'] = d
        threats = None
        if x and position is not None and d is not None:
            self.part = 'local threats'
            threats = self.local_threats(position[0], position[1], x, d, lines, options)
            attribution.update({key: threats[key] for key in ('side', 'tests', 'barred', 'units_left', 'd_after', 'nodes')})
            record['direct_ko']['local_threats'] = threats['threats']
        elif x and position is None and parts['mutual']:
            attribution['position'] = 'none: a mutual approach has no single direct ko'
        elif x:
            attribution['undecided'] = 'no direct-ko position or value'
        elif x == 0 and position is not None:
            record['direct_ko']['local_threats'] = []
        #the position after the conventional rounds (the last free retake): beyond the limit in X's favour, Y's free retake
        #loses outright and the last approach is sente (design risk 2)
        end = None
        if rounds is not None and rounds['end'] is not None:
            self.part = 'approach line'
            end = self.threshold(rounds['end'][0], rounds['end'][1], start=d or 0, shifted=False)
        sente = end is not None and end['d'] is None and end['beyond'] != 0 and (end['beyond'] > 0) == (holder != solver)
        def line_at(name, winner, caption):
            #a line from the leaf (through the approach rounds) where winner wins, at its balance from the threshold at the
            #walk's start; a threshold beyond the limit in winner's favour gives the line at the limit
            if rounds is not None:
                if end is None:
                    return None
                d_end = end['d']
                if d_end is None and end['beyond'] and (end['beyond'] > 0) == (winner != solver):
                    d_end = LABEL_LIMIT + 1 if end['beyond'] > 0 else -LABEL_LIMIT
                if d_end is None:
                    return None
                return self.built_line(name, leaf, rounds['prefix'] + rounds['moves'], rounds['end'], winning(winner)(d_end), ko,
                                       start=start, caption=caption)
            if side is None and d_leaf is not None:
                return self.built_line(name, leaf, [], leaf, winning(winner)(d_leaf), ko, start=start, caption=caption)
            return None
        #the approach line: X wins at the label's count, Y plays elsewhere in X's rounds
        approach_line = None
        if rounds is not None:
            self.part = 'approach line'
            if sente:
                caption = 'The approach round fails: after %s\'s approach %s, %s\'s free retake loses even with %d threats more' % (
                    COLOUR_NAMES[holder], self.point_name(rounds['rounds'][-1]['approach']), COLOUR_NAMES[other_colour(holder)], LABEL_LIMIT)
            else:
                caption = 'The approach line: %s approaches while %s plays elsewhere' % (COLOUR_NAMES[holder], COLOUR_NAMES[other_colour(holder)])
            approach_line = line_at('approach round fails' if sente else 'approach', holder, caption)
            record['approaches'][side]['round_moves'] = [self.point_name(item['approach']) for item in rounds['rounds']]
            if approach_line is not None:
                record['approaches'][side]['line'] = approach_line
                record['lines'].append(approach_line)
        #the direct-ko line: where the side with the local threats wins (they are its winning moves there), where the side
        #with a stage wins when no bar explains the extra threats, else where the approaching side or the solver wins
        extra = None
        if threats and threats['threats']:
            extra = threats['side']
        elif threats and threats['units_left']:
            extra = other_colour(threats['side'])
        self.part = 'direct-ko line'
        if sente:
            #the conventional rounds never reach the direct ko
            direct = None
        elif approach_line is not None and extra in (None, holder):
            direct = approach_line
        else:
            if parts['mutual']:
                direct = line_at('full count', solver, 'The full count: both sides need an approach')
            else:
                direct = line_at('direct ko', extra or holder or solver, 'The direct ko%s' % (
                    ': %s wins it' % COLOUR_NAMES[extra] if extra else ''))
            if direct is not None:
                record['lines'].append(direct)
        record['direct_ko']['line'] = direct
        #each threat as the record of 7.1 has it: an answer, and where it shows on the direct-ko line
        for threat in (threats or {}).get('threats') or []:
            threat['answer'] = next((threat['answers'][point] for point in threat['points'] if point in threat['answers']), None)
            shown = [i for i, (token, role, color) in enumerate(zip(direct['moves'].split(), direct['roles'], direct['colours']))
                     if role == 'local threat' and color == threat['side'] and token in threat['points']] if direct else []
            threat['line'] = {'name': direct['name'], 'number': shown[0] + 1} if shown else None
        #the stage fallback (D7): extra threats no bar explains, read on the line where the side with the stage wins
        if threats and threats['units_left'] > 0:
            staged = other_colour(threats['side'])
            stage_line = direct if direct is not None and direct['winner'] == staged else None
            if stage_line is None:
                stage_line = line_at('stage', staged, 'The direct ko: %s needs an extra move' % COLOUR_NAMES[staged])
                if stage_line is not None:
                    record['lines'].append(stage_line)
            found = []
            for i, role in enumerate(stage_line['roles'] if stage_line else []):
                if role == 'extra move (stage)' and stage_line['colours'][i] == staged and len(found) < threats['units_left']:
                    found.append({'side': staged, 'move': stage_line['moves'].split()[i], 'kind': 'extra move', 'line': stage_line['name'],
                                  'number': i + 1})
            record['direct_ko']['stages'] += found
            attribution['unattributed'] = threats['units_left'] - len(found)
        #the gains of the approach rounds (D8), each located gain with its line and the no-gain twin
        if rounds is not None and rounds['rounds']:
            other = other_colour(holder)
            candidates = []
            for moves in (state['count']['pv_win'], state['count']['pv_fail']):
                boards = [board] + self.replay(board, moves)
                for i, (color, p, mark) in enumerate(moves[:len(boards) - 1]):
                    if color == other and p is not None and mark == '' and boards[i + 1].ko is None and p not in candidates:
                        candidates.append(p)
            gain = record['gain']
            gain['rounds'] = []
            v_line = profile['v']['pv_win'] if other == solver else profile['v']['pv_fail']
            for entry in self.round_gains(rounds, holder, profile['n'], candidates):
                item = {'round': entry['round'], 'side': other, 'move': self.point_name(entry['move']) if entry['move'] else None,
                        'value': entry['value'], 'd_with': entry['d_with'], 'd_without': entry['d_without'], 'base': entry['base'],
                        'fails': entry['fails'], 'tried': entry['tried'], 'line_with': None, 'line_without': None}
                if entry['fails']:
                    #Y's answer to the approach on the line of v where Y wins (Y moves there once the forced passes are over)
                    approach = rounds['rounds'][entry['round'] - 1]['approach']
                    answer = next((v_line[k + 2][1] for k in range(len(v_line) - 2) if v_line[k][2] == 'fp' and v_line[k + 1][:2] == (holder, approach)
                                   and v_line[k + 2][0] == other and v_line[k + 2][1] is not None and v_line[k + 2][1] not in rounds['ko']), None)
                    failure = {'round': entry['round'], 'approach': self.point_name(approach), 'answer': answer and self.point_name(answer),
                               'base': entry['base']}
                    record['approaches'][side].setdefault('round_fails', []).append(failure)
                    record['warnings'].append('approach round %d fails: %s must answer %s%s instead of retaking for free (%s after the free '
                                              'retake; a sente approach, design risk 2)' % (
                                                  entry['round'], COLOUR_NAMES[other], failure['approach'],
                                                  ' at %s' % failure['answer'] if answer else '', entry['base']))
                if entry['value']:
                    before = rounds['prefix'] + rounds['moves'][:4 * (entry['round'] - 1)]
                    twin = entry['positions'][None][3]
                    self.part = 'gain line round %d' % entry['round']
                    for key, move in (('line_with', entry['move']), ('line_without', None)):
                        moves, after, passes, value = entry['positions'][move]
                        caption = 'The gain line: %s plays %s instead of elsewhere' % (COLOUR_NAMES[other], self.point_name(move)) if move \
                            else 'The no-gain twin: %s plays elsewhere' % COLOUR_NAMES[other]
                        fixed = {len(before): 'gain move', len(before) + 1: 'approach'} if move else None
                        item[key] = self.built_line('gain' if move else 'no gain', leaf, before + moves, after, winning(holder)(twin), ko,
                                                    fixed=fixed, passes=passes, start=start, caption=caption)
                        record['lines'].append(item[key])
                gain['rounds'].append(item)
            gain_totals(gain)
            if gain['total'] is not None and gain['total'] < 0 and gain['d_full'] == d_leaf:
                self.part = 'full-count line'
                line = self.built_line('full count', leaf, [], leaf, winning(holder)(d_leaf), ko, start=start,
                                       caption='The full count: %s needs fewer threats than the label says' % COLOUR_NAMES[holder])
                gain['line'] = line
                record['lines'].append(line)
            if gain['d_full'] is None and record['d_conv'] is not None and record['a'] is not None and \
                    all(item['value'] is not None for item in gain['rounds']):
                #the full count is undecided: the label count with the located gains, marked as such
                parts_count = record['d_conv'] + gain['located'] * (1 if gain['side'] != solver else -1)
                record['strict_from_parts'] = besogo_string(record['a'], -parts_count - record['a'])
                gain['count_from_parts'] = parts_count
        if (sente or not record['lines']) and d_leaf is not None and not any(line['name'] == 'full count' for line in record['lines']):
            #no approach or direct-ko line could be built (an approach round without a free retake), or the approach round
            #fails: the full count's line
            self.part = 'full-count line'
            line = self.built_line('full count', leaf, [], leaf, d_leaf, ko, start=start, caption='The full count')
            record['lines'].append(line)
        #walls create false approaches and threats (design 7.2): every threat point, answer and gain move touching one
        for threat in record['direct_ko']['local_threats'] or []:
            if threat['wall']:
                record['warnings'].append('%s local threat %s or its answer touches the area wall at %s' % (
                    COLOUR_NAMES[threat['side']], '/'.join(threat['points']), ', '.join(threat['wall'])))
        for item in (record['gain'] or {}).get('rounds') or []:
            if item['value'] and self.at_wall(self.problem_point(item['move'])):
                record['warnings'].append('%s gain move %s touches the area wall' % (COLOUR_NAMES[item['side']], item['move']))
        record['lines_decided'] = all(line['decided'] for line in record['lines'])

    def problem_point(self, name):
        #a point from its name (A19), None for 'pass'
        if name in (None, 'pass'):
            return None
        return ('ABCDEFGHJKLMNOPQRST'.index(name[0]), self.problem.size - int(name[1:]))

def gain_record(record, d_full, side):
    #D8's totals: g = d_full - d_conv from the side that does not approach (Y), the gains located by round and the rest
    #(unlocated); for a direct ko or a mutual approach the label already is the count. The total is None while d_full is
    #undecided
    solver = record['solver']
    if side is None or record['d_conv'] is None:
        return {'side': None, 'total': None if d_full is None or record['d_conv'] is None else d_full - record['d_conv'], 'd_full': d_full,
                'd_conv': record['d_conv'], 'rounds': [], 'located': 0, 'unlocated': None}
    other = other_colour(solver) if side == 'S' else solver
    sign = 1 if other != solver else -1
    gain = {'side': other, 'total': None if d_full is None else (d_full - record['d_conv']) * sign, 'd_full': d_full, 'd_conv': record['d_conv'],
            'rounds': (record.get('gain') or {}).get('rounds') or [], 'located': 0, 'unlocated': None}
    gain_totals(gain)
    return gain

def gain_totals(gain):
    #the located gains (the positive round values) and the rest of the total, which no round explains
    gain['located'] = sum(item['value'] for item in gain['rounds'] if item['value'])
    gain['unlocated'] = None if gain['total'] is None else gain['total'] - gain['located']

COLOUR_NAMES = {'B': 'Black', 'W': 'White'}

#why the class step gives a special status (D10, D11)
SPECIAL_REASONS = {
    STATUS_DDKO: 'mode A kills under cycle rule 0 (a cycle with a ban break kills) and not under rule 2 (every cycle lives), and the '
                 'cycle alternates between two kos: a double ko',
    STATUS_ADKO: 'mode D kills under cycle rule 0 and not under rule 2, and the cycle alternates between two kos: the defender always '
                 'has a legal capture in the other ko',
    STATUS_DSKO: 'the result rests on a repetition that positional superko (cycle rule 3) decides for the attacker (unverified)',
    STATUS_ASKO: 'the result rests on a repetition that positional superko (cycle rule 3) decides for the defender (unverified)',
    STATUS_BENT4: 'mode A kills with the bent-four rule (the attacker removes the threats and starts the ko when it likes) and not '
                  'without it, and the bent four is on the line',
    STATUS_SEKI: 'the defender lives, but dies when a target without unconditional life counts as dead at the end',
    STATUS_SEKI_SENTE: 'a seki in which the solver keeps sente',
}

NUMBER_WORDS = {1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six'}

def either(items):
    #'A', 'A or B', 'A, B or C'
    items = list(items)
    return items[0] if len(items) == 1 else '%s or %s' % (', '.join(items[:-1]), items[-1])

def open_text(record, key='open', tests_key='open_tests'):
    #the statuses a class record leaves open and the undecided tests behind them, '' for none: 'BENT4 not ruled out
    #(bent-four test undecided at 1,000,000 nodes)'
    statuses = record.get(key) or []
    if not statuses:
        return ''
    tests = ['%s test undecided at %s nodes' % (test['part'], format(test['nodes'], ',')) for test in record.get(tests_key) or []
             if set(test['open']) & set(statuses)]
    return '%s not ruled out (%s)' % (either(statuses), '; '.join(tests))

def status_text(record):
    #a class as a caller prints it: the status, and what its undecided tests leave open ('KO, BENT4 not ruled out
    #(bent-four test undecided at 1,000,000 nodes)')
    extra = open_text(record)
    return record['status'] + (', ' + extra if extra else '')

def status_parts(text):
    #status_text read back: (the class, [the statuses left open])
    if ' not ruled out (' not in text:
        return text, []
    status, rest = text.split(', ', 1)
    return status, re.split(', | or ', rest.split(' not ruled out (', 1)[0])

def balance_text(solver, d):
    #what a count d (the least balance of threats the solver wins at) means in words
    opponent = other_colour(solver)
    if d > 0:
        return '%s needs %d threat%s more than %s' % (COLOUR_NAMES[solver], d, 's' if d > 1 else '', COLOUR_NAMES[opponent])
    if d == 0:
        return '%s wins with as many threats as %s' % (COLOUR_NAMES[solver], COLOUR_NAMES[opponent])
    return '%s wins even when %s has %d threat%s more' % (COLOUR_NAMES[solver], COLOUR_NAMES[opponent], -d, 's' if d < -1 else '')

def beyond_text(solver, beyond):
    #a count beyond the limit in words: the solver wins even when the opponent has the limit's threats more, or fails even
    #with that many more
    opponent = other_colour(solver)
    if beyond < 0:
        return '%s wins even when %s has %d threats more' % (COLOUR_NAMES[solver], COLOUR_NAMES[opponent], LABEL_LIMIT)
    return '%s fails even with %d threats more than %s' % (COLOUR_NAMES[solver], LABEL_LIMIT, COLOUR_NAMES[opponent])

def count_words(record):
    #a count of the record (count, beyond): the count, 'beyond +6' (decided, as a bound) or 'undecided'
    if record.get('count') is not None:
        return str(record['count'])
    return 'beyond %+d' % (record['beyond'] * LABEL_LIMIT) if record.get('beyond') else 'undecided'

def undecided_text(record):
    #the undecided searches of a label by part, with their nodes ('' for none)
    undecided = {}
    for item in record.get('undecided') or []:
        undecided[item['part']] = undecided.get(item['part'], 0) + item['nodes']
    return 'Undecided: %s' % ', '.join('%s (%d nodes)' % item for item in undecided.items()) if undecided else ''

def approach_listing(summary, take, other):
    #the approach moves of a side: the rounds' moves, else for one approach the one the alternatives test names (D3: it
    #keeps v in v's own game, where the other side may answer it), for more the outright line's; for one approach also the
    #other first free moves that keep that count (the alternatives; the ko's own points are no approach), the undecided
    #ones last, or the candidates the test rejects when it keeps none. other: the other side's name
    if summary['n'] == 1:
        moves = summary.get('round_moves') or ([summary['approach']] if summary.get('approach') else [])
    else:
        moves = summary.get('round_moves') or summary['approach_moves']
    ko_points = (take.get('point'), take.get('retake_point'))
    others = [alternative for alternative in summary['alternatives'] or [] if alternative.rstrip('?') not in moves
              and alternative.rstrip('?') not in ko_points] if summary['n'] == 1 else []
    decided = [alternative for alternative in others if not alternative.endswith('?')]
    open_moves = [alternative.rstrip('?') for alternative in others if alternative.endswith('?')]
    if moves:
        return ' and '.join(moves) + (', or %s' % ' or '.join(decided) if decided else '') + \
            ('; %s undecided' % ', '.join(open_moves) if open_moves else '')
    if open_moves:
        return '%s undecided' % ', '.join(open_moves)
    rejected = summary.get('alternatives_rejected') or []
    if summary['n'] == 1 and rejected:
        return 'none keeps the count when %s may answer: %s' % (other, ', '.join(
            '%s %s' % (point, 'count %d' % value if isinstance(value, int) else value) for point, value in rejected))
    return 'none found'

def fill_text(second, attacker):
    #D5 in words: the holder's fill, the other side's capture, and what follows by the solver's classes there, with the
    #holder to move and with the other side to move ('a second ko that Black must start at once': KO, and the other side
    #settles it when the holder plays elsewhere)
    holder, other = second['holder'], other_colour(second['holder'])
    text = '%s fills the ko at %s' % (COLOUR_NAMES[holder], second['point'])
    if second.get('capture') is None:
        return text + ', and the stones it joins are not in atari'
    text += ', %s captures at %s' % (COLOUR_NAMES[other], second['capture'])
    status, reply = second.get('status'), second.get('reply')
    #the other side's result without a ko: life when it defends, death when it attacks
    settled = (STATUS_DEAD,) if other == attacker else (STATUS_ALIVE, STATUS_SEKI, STATUS_SEKI_SENTE)
    if status == STATUS_KO and not second.get('status_open'):
        if reply in settled and not second.get('reply_open'):
            return text + ', and a second ko follows that %s must start at once' % COLOUR_NAMES[holder]
        if reply == STATUS_UNKNOWN or second.get('reply_open'):
            return text + ', and a second ko follows; whether %s must start it at once is open (with %s to move: %s)' % (
                COLOUR_NAMES[holder], COLOUR_NAMES[other], second.get('reply_text'))
        return text + ', and a second ko follows'
    if status == STATUS_BENT4 and not second.get('status_open'):
        return text + ', and a bent four in the corner follows (the solver\'s BENT4)'
    if status in (None, STATUS_UNKNOWN):
        return text + ', and the class after the capture is undecided'
    return text + ', and the solver\'s class after the capture is %s' % second.get('status_text')

def bracket_text(low, high):
    #what the outright tests of an undecided approach count say: low forced passes are not enough, high are (None: not known)
    passes = lambda k: '%d forced pass is' % k if k == 1 else '%d forced passes are' % k
    if low and high:
        return '%s not enough, %d %s' % (passes(low), high, 'is' if high == 1 else 'are')
    if high:
        return '%s enough, fewer undecided' % passes(high)
    if low:
        return '%s not enough, more undecided' % passes(low)
    return 'every forced-pass test undecided'

def label_text(record):
    #design 7.2: the label and one sentence per part with its reason; an undecided part shows as '?' with its nodes, and the
    #decided parts of a partly undecided label are written all the same
    solver = record['solver']
    side_colour = lambda side: solver if side == 'S' else other_colour(solver)
    label = record['label']
    sentences = []
    #what an undecided test of the class step leaves open beside the class
    class_open = open_text(record, 'class_open', 'class_open_tests')
    if record.get('special'):
        return '%s: %s%s.' % (label, SPECIAL_REASONS.get(label, 'from the class step'), '; ' + class_open if class_open else '')
    if label in (STATUS_DEAD, STATUS_ALIVE):
        return '%s: no ko (class %s%s).' % (label, record.get('class'), ', ' + class_open if class_open else '')
    if label is None or 'KO' not in label:
        undecided = undecided_text(record)
        return '%s: the class is undecided and the leaf has no ko shape%s.' % (label, '. ' + undecided if undecided else '')
    if record.get('class') in (STATUS_DEAD, STATUS_ALIVE):
        return '%s: the line ends with the solver\'s capture of a ko, which the leaves count as taking first; without it the result ' \
               'is clean %s%s.' % (label, record['class'], ', ' + class_open if class_open else '')
    head = label + (' (class KO inferred: each side wins while it holds the ko)' if record.get('class_inferred') else
                    ' (class undecided)' if record.get('class') == STATUS_UNKNOWN else
                    ' (class KO, %s)' % class_open if class_open else '')
    take = record.get('first_take') or {}
    if take.get('side') is None:
        sentences.append('Who takes first is undecided')
    elif take['how'] == 'leaf':
        sentences.append('%s took first (%s)' % (COLOUR_NAMES[take['side']], take['point']))
    else:
        preparation = ', '.join('%s %s' % move for move in take['preparation'])
        sentences.append('%s takes first at %s%s%s' % (COLOUR_NAMES[take['side']], take['point'],
                                                       ' after the preparation %s' % preparation if preparation else '',
                                                       ' (from the mode lines)' if take['how'] == 'modes' else ''))
    approaches = record.get('approaches') or {}
    second = record.get('second_ko')
    approaching = [side for side in ('S', 'O') if (approaches.get(side) or {}).get('n') and not (second and second['side'] == side)]
    failures = []
    for side in ('S', 'O'):
        summary = approaches.get(side)
        if summary is None:
            continue
        name = COLOUR_NAMES[side_colour(side)]
        other = (approaches.get('O' if side == 'S' else 'S') or {}).get('n', 0)
        if summary['n'] is None:
            sentences.append('%s needs ? approaches (%s)' % (name, bracket_text(*summary['bracket'])))
        elif summary['n'] == 0 and other is None:
            sentences.append('%s\'s capture is direct' % name)
        elif summary['n'] and summary['held_ok'] is False:
            sentences.append('%s\'s %d round%s are stages, not approaches (the held-ko check fails), so they count as threats' % (
                name, summary['n'], 's' if summary['n'] > 1 else ''))
        elif second and second['side'] == side:
            #D5: the free move fills the ko, so the capture threatens a second ko
            other_name = COLOUR_NAMES[other_colour(second['holder'])]
            alternative = record.get('alternative') or {}
            sentences.append('%s\'s capture threatens a second ko: if %s ignores it, %s. The label counts it, so the ko is direct' % (
                name, other_name, fill_text(second, record['solver'] if record['goal'] == 'KILL' else other_colour(record['solver']))))
            beyond = alternative.get('v_beyond')
            if beyond and (beyond > 0) == (side == 'S'):
                #the direct-ko value without the fill is beyond the limit against the holder: no approach in that reading
                rejected = [point for point, value in summary.get('alternatives_rejected') or []]
                sentences.append('Without it (%s) %s has no approach: whatever %s plays with the free move, %s%s; full count %s. Which '
                                 'reading the labels follow is open (design 13, question 5)' % (
                                     alternative.get('reading'), name, name, beyond_text(record['solver'], beyond),
                                     ', so %s %s no approach' % (either(rejected).replace(' or ', ' and '), 'is' if len(rejected) == 1 else 'are')
                                     if rejected else '', count_words(alternative)))
            else:
                sentences.append('Without it (%s) %s needs %s approach%s (%s): %s, full count %s. Which reading the labels follow is open '
                                 '(design 13, question 5)' % (
                                     alternative.get('reading'), name, NUMBER_WORDS.get(summary['n'], summary['n']), 'es' if summary['n'] > 1 else '',
                                     approach_listing(summary, take, other_name), alternative.get('label') or
                                     ('no label (the direct ko after it is beyond %+d)' % (beyond * LABEL_LIMIT) if beyond else 'label undecided'),
                                     count_words(alternative)))
        elif summary['n']:
            sentences.append('%s needs %s approach%s (%s) before the ko is direct%s' % (
                name, NUMBER_WORDS.get(summary['n'], summary['n']), 'es' if summary['n'] > 1 else '',
                approach_listing(summary, take, COLOUR_NAMES[other_colour(side_colour(side))]),
                '' if summary['held_ok'] else ' (held-ko check undecided)'))
        for failure in summary.get('round_fails') or []:
            failures.append('But approach round %d fails: %s must answer %s%s instead of retaking for free (%s after the free '
                            'retake), so the approach is sente (design risk 2)' % (
                                failure['round'], COLOUR_NAMES[other_colour(side_colour(side))], failure['approach'],
                                ' at %s' % failure['answer'] if failure['answer'] else '', failure['base']))
    if not approaching and approaches.get('S') and approaches.get('O') and all(approaches[side]['n'] == 0 for side in ('S', 'O')):
        sentences.append('Both captures are direct')
    if len(approaching) == 2:
        attacker_side = 'S' if record['goal'] == 'KILL' else 'O'
        count = approaches[attacker_side]['n']
        sentences.append('Neither capture threatens anything, a mutual approach: %s' % (
            'the attacker needs one approach, so 10kKO (D9)' if count == 1 else
            'the attacker needs %d, so the A-form with its count, flagged' % count))
    direct = record.get('direct_ko') or {}
    threats, stages = direct.get('local_threats'), direct.get('stages') or []
    x = record.get('extra_threats')
    parts = []
    for threat in threats or []:
        forms = ['%s%s' % (point, ' answered at %s' % threat['answers'][point] if point in threat['answers'] else '') for point in threat['points']]
        where = 'at %s' % forms[0] if len(forms) == 1 else 'in %s forms (%s)' % (NUMBER_WORDS.get(len(forms), len(forms)), ', '.join(forms))
        if threat.get('forms_undecided'):
            where += ' (whether %s %s needed is undecided)' % (' and '.join(threat['forms_undecided']), 'is' if len(threat['forms_undecided']) == 1 else 'are')
        parts.append('%s has a local threat %s, which buys a free retake' % (COLOUR_NAMES[threat['side']], where))
    for stage in stages:
        if stage.get('kind') == 'extra move':
            parts.append('%s needs an extra move at %s (a stage), and %s retakes for free in between' % (
                COLOUR_NAMES[stage['side']], stage['move'], COLOUR_NAMES[other_colour(stage['side'])]))
    attribution = direct.get('attribution') or {}
    if attribution.get('unattributed'):
        parts.append('%d extra threat%s not located' % (attribution['unattributed'], 's' if attribution['unattributed'] > 1 else ''))
    if x and threats is None:
        parts.append('the extra threats are not attributed (%s)' % (attribution.get('undecided') or 'undecided'))
    if x == 0 and threats is not None:
        parts.append('nobody has a local threat')
    if approaching and len(approaching) == 1 and direct.get('v') is not None:
        side = approaching[0]
        v = direct['v']
        kind = besogo_string(0, -v)
        sentences.append('After %s, the direct ko is %s%s: %s%s' % (
            'it' if approaches[side]['n'] == 1 else 'them', 'a plain ' if not x else '', kind, balance_text(solver, v),
            ', and ' + '; '.join(parts) if parts else ''))
    elif approaching and len(approaching) == 1 and approaches[approaching[0]].get('v_beyond'):
        #decided, as a bound: no value for the label's threats
        beyond = approaches[approaching[0]]['v_beyond']
        sentences.append('After %s, the direct ko is beyond %+d: %s' % (
            'it' if approaches[approaching[0]]['n'] == 1 else 'them', beyond * LABEL_LIMIT, beyond_text(solver, beyond)))
    elif parts:
        sentences.append('In the direct ko ' + '; '.join(parts))
    sentences += failures
    if record.get('d_conv') is not None:
        sentences.append('Label count: %s' % balance_text(solver, record['d_conv']))
    count = record.get('count') or {}
    gain = record.get('gain') or {}
    d_full = count.get('d_full')
    located = [item for item in gain.get('rounds') or [] if item['value']]
    explained = []
    for item in located:
        explained.append(gain_sentence(item))
    if d_full is not None:
        text = 'Full count %d' % d_full
        if gain.get('total') and gain['total'] > 0:
            text += ': ' + '; '.join(explained) if explained else ''
            if explained:
                sentences.append(text)
                sentences.append('Left out by the approach rule')
            else:
                sentences.append(text)
            if gain.get('unlocated'):
                sentences.append('%+d of the gain not located' % gain['unlocated'])
        elif gain.get('total') and gain['total'] < 0:
            better_side = other_colour(gain['side'])
            line = gain.get('line')
            kos = [token for token, role, color in zip(line['moves'].split(), line['roles'], line['colours'])
                   if role == 'second ko' and color == better_side] if line else []
            sentences.append('%s: the count is %s threat%s better for %s than the label (not located%s)' % (
                text, NUMBER_WORDS.get(-gain['total'], -gain['total']), 's' if gain['total'] < -1 else '', COLOUR_NAMES[better_side],
                '; its line uses a second ko at %s' % ', '.join(kos) if kos else ''))
        else:
            sentences.append(text)
    elif count and count.get('beyond'):
        #decided, as a bound
        sentences.append('Full count beyond %+d' % (count['beyond'] * LABEL_LIMIT))
    elif count:
        #the bracket's bounds stand for decided searches only inside -LABEL_LIMIT < low and high <= LABEL_LIMIT
        low, high = count.get('bracket') or (-LABEL_LIMIT, LABEL_LIMIT + 1)
        if low > -LABEL_LIMIT and high <= LABEL_LIMIT:
            bracket = 'between %d and %d' % (low, high)
        elif low > -LABEL_LIMIT:
            bracket = 'at least %d' % low
        elif high <= LABEL_LIMIT:
            bracket = 'at most %d' % high
        else:
            bracket = 'no balance decided'
        sentences.append('Full count undecided (%s)%s' % (bracket, ': located ' + '; '.join(explained) if explained else ''))
        if explained:
            sentences.append('Left out by the approach rule')
    if record.get('strict_label') and record['strict_label'] != label:
        sentences.append('Strict label %s' % record['strict_label'])
    elif record.get('strict_from_parts') and record['strict_from_parts'] != label:
        sentences.append('Strict label from the parts %s (the label count plus the located gains; the full count is undecided)' % record['strict_from_parts'])
    if record.get('net_label'):
        sentences.append('Net reading %s' % record['net_label'])
    if label == STATUS_TEN_THOUSAND and (record.get('besogo') or {}).get('a_form'):
        sentences.append('besogo ranks it as %s' % record['besogo']['a_form'])
    if undecided_text(record):
        sentences.append(undecided_text(record))
    return head + ': ' + '. '.join(sentences) + '.'

def gain_sentence(item):
    #a located gain of an approach round: Y's move, and what it becomes on the gain line (its later local threat)
    name = COLOUR_NAMES[item['side']]
    text = '%s\'s %s, played with the free move approach round %d gives %s' % (name, item['move'], item['round'], name)
    line = item.get('line_with')
    if line:
        tokens = line['moves'].split()
        for i, role in enumerate(line['roles']):
            if i > line['prefix'] and role == 'local threat' and line['colours'][i] == item['side']:
                taken = line['captures'][i]
                text += ', later becomes the local threat %s%s' % (tokens[i], ' (takes %s)' % ', '.join(taken) if taken else '')
                break
    return text + ' (%+d)' % item['value']

def label_summary(record, authored, goal):
    #the label fields check_tree and status_eval add to a leaf: the parts, the text, the lines as research/report.py's
    #diagrams, the undecided parts and the comparison with the tree's S[] (design 7.3)
    count = record.get('count') or {}
    take = record.get('first_take') or {}
    direct = record.get('direct_ko') or {}
    gain = record.get('gain') or {}
    return {'label': record['label'], 'strict_label': record.get('strict_label'), 'strict_from_parts': record.get('strict_from_parts'),
            'net_label': record.get('net_label'), 'alternative': record.get('alternative'), 'second_ko': record.get('second_ko'),
            'class': record.get('class'), 'class_inferred': record.get('class_inferred'), 'class_open': record.get('class_open'),
            'class_open_tests': record.get('class_open_tests'),
            'count': count.get('d_full'), 'count_decided': count.get('decided'), 'd_conv': record.get('d_conv'),
            'first_take': {key: take.get(key) for key in ('side', 'point', 'how', 'preparation')},
            'approaches': {side: (summary or {}).get('n') for side, summary in (record.get('approaches') or {}).items()},
            'local_threats': direct.get('local_threats'), 'stages': direct.get('stages'),
            'gain': {key: gain.get(key) for key in ('side', 'total', 'located', 'unlocated')} if gain else None,
            'gain_rounds': [{key: item.get(key) for key in ('round', 'move', 'value')} for item in gain.get('rounds') or []],
            'text': record.get('text'), 'lines': [dict(line['diagram'], name=line['name'], text=line['text']) for line in record.get('lines') or []],
            'areas': record.get('areas'), 'warnings': record.get('warnings'), 'undecided': record.get('undecided'),
            'nodes': record.get('nodes'), 'seconds': record.get('seconds'), 'comparison': compare_label(authored, record, goal)}

def allows(summary, n):
    #whether a side's approach profile (its summary in the label record; None: no capture of the ko, count 0) allows the
    #approach count n: a decided n, 0 when its rounds are stages (the held-ko check fails, or has not run), else any n of
    #its bracket of outright tests
    if summary is None:
        return n == 0
    if summary['n'] is not None:
        if summary['n'] == 0 or summary['held_ok'] is False:
            return n == 0
        return n in (0, summary['n']) if summary['held_ok'] is None else n == summary['n']
    low, high = summary['bracket']
    return n == 0 or (n >= low and (high is None or n < high))

def approaches_allowed(tree, record, goal):
    #whether the decided parts of a label allow the tree's approaches: a known approach count a (the label's prefix when it
    #has no '?'), else the counts the profiles allow (D6: one side approaches; D9: a mutual approach prints the attacker's
    #count, 10kKO for 1, which besogo ranks equal to the attacker's A-form). None when nothing is known of them
    kill = goal == 'KILL'
    ten_thousand = -1 if kill else 1
    tree_a = ten_thousand if tree['type'] == STATUS_TEN_THOUSAND else tree['approaches']
    label = record.get('label') or ''
    prefix = label[:label.index('KO')] if 'KO' in label else '?'
    a = record.get('a')
    if a is None and '?' not in prefix and label != STATUS_TEN_THOUSAND:
        a = parse_status(prefix + 'KO')['approaches']
    if a is not None:
        return a == tree_a
    approaches = record.get('approaches') or {}
    if all(approaches.get(side) is None for side in ('S', 'O')):
        return None
    attacker, defender = (approaches.get('S'), approaches.get('O')) if kill else (approaches.get('O'), approaches.get('S'))
    if tree_a == 0:
        return allows(attacker, 0) and allows(defender, 0)
    if (tree_a < 0) == kill:
        return allows(attacker, abs(tree_a))
    return allows(defender, abs(tree_a)) and allows(attacker, 0)

def compare_label(authored, record, goal):
    #design 7.3: the tree's S[] against the label: the class first, then the approaches, then the threats, and the count
    #beside them. [{kind, severity, ...}]: status-mismatch (error), ko-approach (major; same_count when T_label is equal),
    #ko-threats (minor), ko-notation (note: 10kKO against an A-form besogo ranks equal), ko-convention (note: the tree is
    #the label without the second ko, design 13 question 5), ko-count (info: the tree's T_label against the full count, or
    #outside the count's decided bracket while the count is undecided), label-undecided (info), class-open (info: an
    #undecided test of the class step leaves other statuses open; a tree status among them is no status-mismatch). A partly
    #undecided label is compared on its decided parts: the approach counts its profiles allow, and the count's bracket
    text = authored.strip()
    tree = parse_status(text)
    label = record.get('label') or STATUS_UNKNOWN
    if tree is None:
        return [{'kind': 'status-unreadable', 'severity': 'note', 'authored': text}]
    if label == STATUS_UNKNOWN or record.get('class') in (None, STATUS_UNKNOWN) and not record.get('class_inferred'):
        return [{'kind': 'label-undecided', 'severity': 'info', 'authored': text, 'label': label}]
    ko_types = ('KO', STATUS_TEN_THOUSAND)
    mine = parse_status(label) if '?' not in label else {'type': 'KO'}
    issues = []
    open_statuses = record.get('class_open') or []
    if open_statuses:
        #the tree's status by its class: any ko label is the class KO
        tree_class = STATUS_SEKI_SENTE if tree['sente'] else STATUS_KO if tree['type'] in ko_types else tree['type']
        issues.append({'kind': 'class-open', 'severity': 'info', 'authored': text, 'label': label, 'open': list(open_statuses),
                       'tree_open': tree_class in open_statuses, 'text': open_text(record, 'class_open', 'class_open_tests')})
    if tree['type'] not in ko_types or mine['type'] not in ko_types:
        if (tree['type'], tree['sente']) != (mine['type'], mine.get('sente', False)) and not (issues and issues[0]['tree_open']):
            issues.append({'kind': 'status-mismatch', 'severity': 'error', 'authored': text, 'label': label})
        return issues
    count = record.get('count') or {}
    tree_count = label_threshold(text)
    alternative = record.get('alternative') or {}
    if alternative.get('label') == text:
        #the tree is the other reading of a second ko (the fill barred), whose count is that reading's own
        issues.append({'kind': 'ko-convention', 'severity': 'note', 'authored': text, 'label': label, 'alternative': text})
        if tree_count is not None and alternative.get('count') is not None and tree_count != alternative['count']:
            issues.append({'kind': 'ko-count', 'severity': 'info', 'authored': text, 'label': label, 'tree_count': tree_count,
                           'count': alternative['count']})
        if '?' in label:
            issues.append({'kind': 'label-undecided', 'severity': 'info', 'authored': text, 'label': label})
        return issues
    if '?' in label:
        #the decided parts only
        if approaches_allowed(tree, record, goal) is False:
            issues.append({'kind': 'ko-approach', 'severity': 'major', 'authored': text, 'label': label, 'same_count': None,
                           'allowed': {side: None if summary is None else {'n': summary['n'], 'bracket': summary['bracket'],
                                                                           'held_ok': summary['held_ok']}
                                       for side, summary in (record.get('approaches') or {}).items()}})
        issues.append({'kind': 'label-undecided', 'severity': 'info', 'authored': text, 'label': label})
    elif tree['type'] != mine['type']:
        #10kKO against a KO: equal when besogo ranks neither above the other
        if not better(text, label, goal) and not better(label, text, goal):
            issues.append({'kind': 'ko-notation', 'severity': 'note', 'authored': text, 'label': label})
        else:
            issues.append({'kind': 'ko-approach', 'severity': 'major', 'authored': text, 'label': label, 'same_count': None})
    elif tree['type'] == 'KO' and tree['approaches'] != mine['approaches']:
        issues.append({'kind': 'ko-approach', 'severity': 'major', 'authored': text, 'label': label,
                       'same_count': label_threshold(text) == label_threshold(label)})
    elif tree['type'] == 'KO' and tree['extra_threats'] != mine['extra_threats']:
        issues.append({'kind': 'ko-threats', 'severity': 'minor', 'authored': text, 'label': label})
    d_full = count.get('d_full')
    if tree_count is not None and d_full is not None and tree_count != d_full:
        issues.append({'kind': 'ko-count', 'severity': 'info', 'authored': text, 'label': label, 'tree_count': tree_count, 'count': d_full})
    elif tree_count is not None and d_full is None and count.get('bracket'):
        #the bounds of an undecided count that searches decided: the solver fails below low, wins at high
        low, high = count['bracket']
        if (low > -LABEL_LIMIT and tree_count < low) or (high <= LABEL_LIMIT and tree_count > high):
            issues.append({'kind': 'ko-count', 'severity': 'info', 'authored': text, 'label': label, 'tree_count': tree_count,
                           'count': None, 'bracket': [low, high]})
    if alternative.get('label') and issues:
        for issue in issues:
            issue['alternative'] = alternative['label']
    return issues

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

def ko_taken_by(board, last_mover, solver_color, status):
    #the convention for leaves: a line that ends with the solver's side capturing a ko is a ko (KO+, the solver took
    #first), even when the solver could steer back to clean life or death later; the direct move is the clean one
    if board.ko is not None and last_mover == solver_color and status in (STATUS_DEAD, STATUS_ALIVE):
        return STATUS_KO
    return status
