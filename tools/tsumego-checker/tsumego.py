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
        #an undecided search of the class step, and the statuses it leaves open beside the one the class keeps
        record['undecided'].append({'part': part, 'search': search, 'nodes': int(fields['nodes'])})
        record['open'].extend(status for status in open_statuses if status not in record['open'])
        record['warnings'].append('%s test undecided' % part)

    def classify(self, board, to_move, budget=None, use_wide=True):
        #the class (design 4, step 1): modes A and D under cycle rule 0, the double-ko and superko tests, the wider area for
        #a ko, the clean result, the bent-four test and the seki tests. {status, best, modes, tests, warnings, undecided}
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
                  'tests': {}, 'warnings': [], 'undecided': [], 'open': [], 'decided_by': None}
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
        if record['decided_by'] is None:
            if record['status'] in (STATUS_DEAD, STATUS_ALIVE):
                record['status'] = self.clean_status(board, to_move, record, budget)
            if record['status'] == STATUS_KO:
                record['status'] = self.bent_four_status(board, to_move, plain, record, budget)
            if record['status'] == STATUS_ALIVE:
                record['status'] = self.seki_status(board, to_move, record, budget)
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
                self.note_undecided(record, 'double-ko', 'solve cycle 2', lives, [STATUS_DDKO if mode == 'A' else STATUS_ADKO])
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
            self.note_undecided(record, 'superko', 'solve cycle 1', kills, [STATUS_DSKO, STATUS_ASKO])
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
            self.note_undecided(record, 'superko', 'solve cycle 3', fields, [STATUS_DSKO, STATUS_ASKO])
            return None
        plain = record['modes']
        if status == mode_class(plain['A'], plain['D']):
            return None
        record['warnings'].append('superko (unverified)')
        return {STATUS_DEAD: STATUS_DSKO, STATUS_ALIVE: STATUS_ASKO}.get(status, status)

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
        for line in (self.line_moves(rule['pvA'], to_move), self.proofs_line(board, to_move, 'A', {'bentfour': True}, {}, budget)):
            boards = [board] + self.replay(board, line)
            if len(line) >= 2 and line[-1][1] is None and line[-2][1] is None:
                kills, end_line, nodes = self.count(boards[-1], self.attacker, 0, 0, end=0, budget=budget, escalate=False,
                                                    record=record, part='bent-four end')
                boards += self.replay(boards[-1], end_line) if kills is not None else []
            if any(has_bent_four(b, defender) for b in boards):
                return STATUS_BENT4
        record['warnings'].append('bent-four rule kills, but no bent four on its lines')
        return STATUS_KO

    def seki_status(self, board, to_move, record, budget):
        #D11: the defender lives, but dies when a target that is not unconditionally alive at the end counts as dead: SEKI.
        #SEKI+ when the solver keeps sente: with the opponent to move, one forced opponent pass lets the solver improve;
        #with the solver to move, one forced solver pass keeps the seki
        killed = self.modes(board, to_move, budget, sekikill=True)
        record['tests']['seki'] = killed['A'] + killed['D']
        if killed['D'] == 'U':
            self.note_undecided(record, 'seki', 'solve sekikill', killed, [STATUS_SEKI])
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
                                             escalate=False, record=record, part='seki in sente')
        else:
            #the solver gets a free move: it improves when it kills (attacker solving) or lives for real, or wins a ko for it
            result, line, nodes = self.count(board, to_move, 4, 0, passes={to_move: 1}, sekikill=not attacker_solves, budget=budget,
                                             escalate=False, record=record, part='seki in sente')
        record['tests']['seki in sente'] = result
        if result is None:
            record['open'].append(STATUS_SEKI_SENTE)
            record['warnings'].append('seki-in-sente test undecided')
            return STATUS_SEKI
        return STATUS_SEKI_SENTE if result else STATUS_SEKI

    def status(self, board, to_move):
        #the target's status with to_move to play (DEAD / KO / ALIVE / BENT4 / SEKI / SEKI+ / DDKO / ADKO / DSKO / ASKO /
        #?), plus the best move found. A refinement test of the class that stays undecided leaves the status of the steps
        #before it, as the clean-result and bent-four tests always did; classify() lists it with the statuses it leaves open
        text = self.problem.board_text(board)
        ko = self.problem.index(board.ko) if board.ko else -1
        key = (text, ko, to_move)
        if key not in self.cache:
            record = self.classify(board, to_move)
            self.cache[key] = (record['status'], record['best'])
        return self.cache[key]

    def special(self, board, to_move):
        #DDKO / ADKO / DSKO / ASKO / BENT4 / SEKI / SEKI+ of the class step, None for another status
        status = self.status(board, to_move)[0]
        return status if status in SPECIAL_STATUSES else None

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

    def counted_options(self, board, to_move, s, o, passes, bar, bar_banned, cycle, end, sekikill, pv=False):
        #the arguments of solvex and solvemoves after the budget, the solver's threats s against the opponent's o
        black, white = (s, o) if self.solver_color == 'B' else (o, s)
        options = ['p%s=%d' % (color.lower(), k) for color, k in sorted((passes or {}).items()) if k]
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
              pv=True, budget=None, escalate=True, record=None, part=None):
        #the counted game G(P; s, o) of the design: True / False / None (undecided) for the solver's side with s threats
        #against the opponent's o, its line as [(colour, point, mark)] and the nodes. passes {colour: k} forced passes, bar
        #and bar_banned {colour: points} barred for the whole search or while that colour is under a ko ban. An undecided
        #search is retried at ESCALATION times the budget, then expanded by one ply (solvemoves); still undecided it is None
        #and goes on the label's list of undecided searches
        arguments, ko = self.counted_options(board, to_move, s, o, passes, bar, bar_banned, cycle, end, sekikill, pv)
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

    def counted_moves(self, board, to_move, s, o, passes=None, bar=None, budget=None):
        #solvemoves: [(point, mark, mover wins True/False/None, nodes)] for every legal move and the pass of the side to move
        arguments, ko = self.counted_options(board, to_move, s, o, passes, bar, None, None, None, False)
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

    def threshold(self, board, to_move, L=LABEL_LIMIT, start=0, **options):
        #d(P): the least d in -L..L with the solver winning G(P; d+, d-), by galloping from start and bisecting. d is None
        #when the searches leave a bracket, or beyond the limit ('beyond' -1: the solver wins at -L, +1: it fails at L).
        #The shifted check: the solver still wins with one more threat each and still fails one below (None: undecided)
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
        if record['d'] is not None:
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
                result, moves, nodes = self.count(board, to_move, max(balance, 0), max(-balance, 0))
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
        #From the leaf's ban, else the principal line at d (one search confirms d(P_F) = d), else the mode lines
        take = {'side': None, 'ko': None, 'preparation': [], 'P_F': None, 'P_other': None, 'how': None, 'notes': [],
                'confirmed': None, 'first_move': first}
        if board.ko is not None:
            take.update(side=other_colour(to_move), ko=(board.ko, last_move), P_F=(board, to_move), how='leaf')
        elif d is not None:
            line, end = self.principal_line(board, to_move, d, first=first)
            if end != 'take':
                take['notes'].append('principal line: %s' % end)
                return take
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
            #the preparation comes from the line the taker wins
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
        elif d is not None:
            #the other side's first capture of the ko further on the principal line
            line, end = self.principal_line(after, retaker, d)
            points = set(take['ko'])
            for color, p, mark, later in line:
                if color == retaker and p in points and later.ko is not None:
                    take['P_other'] = (later, other_colour(color))
                    break
        if take['P_other'] is None:
            take['notes'].append('the other side has no capture of the ko: its count is n/a, taken as 0')
        return take

    def profile(self, board, to_move, holder, K=PASS_LIMIT, Lo=4):
        #D3-D5 for the holder X of the ko at P_X (board, to_move = Y under the ban): t, the least k <= K for which X wins
        #outright (0 threats against Lo) while Y is forced to pass k times, n = t - 1 its approaches, the held-ko check (Y
        #also barred from retaking for the whole search) and v, the threshold with Y forced to pass n times
        mover = to_move
        result = {'n': None, 't': None, 'decided': False, 'bracket': None, 'outright': {}, 'moves': [], 'resolving': None,
                  'approach_moves': [], 'alternatives': None, 'held_ok': None, 'v': None, 'line': [], 'Lo': Lo, 'nodes': 0}
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
                #D3: the approach moves that keep the win, from the first approach position
                first = self.play(board, None, None)
                moves = self.counted_moves(first, holder, s, o, passes={mover: high - 1} if high > 1 else None)
                result['alternatives'] = [(p, wins_move) for p, mark, wins_move, nodes in moves if wins_move is not False]
                if result['held_ok']:
                    self.part = 'direct ko %s' % holder
                    result['v'] = self.threshold(board, mover, start=1 if holder == self.solver_color else 0, passes={mover: result['n']})
        result['nodes'] = self.nodes - start
        return result

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
        #and direct-ko values and the full count; a part that is undecided shows as '?'
        solver = self.solver_color
        kill = solver == self.attacker
        parts = {'label': None, 'besogo': None, 'a': None, 'e': None, 'd_conv': None, 'x': None, 'strict_label': None,
                 'net_label': None, 'mutual': None, 'stages': [], 'notes': [], 'v': None}
        if take['side'] is None and not take.get('positions'):
            parts['label'] = 'KO?'
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
            v = profiles[side]['v']
            parts['v'] = v and v['d']
            if parts['v'] is not None and take['side'] is not None:
                if side == 'S':
                    e = 1 - parts['v'] if take['side'] == solver else -parts['v']
                else:
                    e = -parts['v'] if take['side'] == solver else -(parts['v'] + 1)
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
        record = {'label': None, 'class': None, 'class_inferred': False, 'class_open': [], 'besogo': None, 'count': None, 'd_conv': None,
                  'first_take': None, 'approaches': None, 'direct_ko': None, 'gain': None, 'strict_label': None,
                  'net_label': None, 'special': None, 'area': None, 'a': None, 'e': None, 'extra_threats': None, 'warnings': [],
                  'undecided': [], 'nodes': 0, 'seconds': 0}
        start, started = self.nodes, time.time()
        self.undecided = record['undecided']
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
        record['first_take'] = {'side': take['side'], 'point': self.point_name(take['ko'][1]) if take['ko'] else None,
                                'retake_point': self.point_name(take['ko'][0]) if take['ko'] else None, 'how': take['how'],
                                'preparation': [(c, self.point_name(p)) for c, p in take['preparation']], 'confirmed': take['confirmed'],
                                'notes': take['notes'], 'nodes': take_nodes,
                                'candidates': None if len(labelled) == 1 else
                                [{'move': self.point_name(t['first_move'][0]), 'label': p['label']} for t, f, p in labelled]}
        record['approaches'] = {side: self.profile_summary(profiles[side]) for side in ('S', 'O')}
        side = 'S' if parts['a'] is not None and parts['a'] < 0 else 'O' if parts['a'] else None
        v = profiles[side]['v'] if side and profiles[side] and not parts['mutual'] else None
        record['direct_ko'] = {'v': parts['v'], 'side': side, 'line': self.line_names(v['pv_win']) if v else None,
                               'local_threats': None, 'stages': parts['stages']}
        if parts['d_conv'] is not None and count['d'] is not None:
            record['gain'] = {'total': count['d'] - parts['d_conv'], 'rounds': None, 'unlocated': None}
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
                'alternatives': None if profile['alternatives'] is None else
                ['%s%s' % (self.point_name(p), '' if wins else '?') for p, wins in profile['alternatives']],
                'held_ok': profile['held_ok'], 'v': profile['v'] and profile['v']['d'],
                'v_decided': profile['v'] and profile['v']['decided'],
                'v_table': profile['v'] and profile['v']['table'],
                'line': self.line_names(profile['line']), 'Lo': profile['Lo'], 'nodes': profile['nodes']}

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

    def ko_label(self, board, to_move, last_move=None, approach_rule=True):
        #the label of a leaf (design 4 and 7.1), in the wider area and in this one: the wider area's label when it is
        #decided, else this one's; a count undecided there comes from the other area, and a decided label that differs
        #between the areas is flagged
        areas = [('wide', self.wide), ('narrow', self)] if self.wide is not None and self.wide is not self else [('narrow', self)]
        records = [(name, solver.area_label(board, to_move, last_move, approach_rule)) for name, solver in areas]
        #a decided label first, else a decided class, the wider area on a tie
        decided = lambda record: ('?' not in record['label'], record['class'] not in (None, STATUS_UNKNOWN))
        name, main = max(records, key=lambda item: decided(item[1]) + (item[0] == 'wide',))
        result = dict(main, warnings=list(main['warnings']), undecided=[dict(item, area=name) for item in main['undecided']])
        result['area'] = {part: name for part in ('label', 'class', 'count', 'first_take', 'approaches', 'direct_ko')}
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
                    if main['d_conv'] is not None:
                        result['gain'] = {'total': d - main['d_conv'], 'rounds': None, 'unlocated': None}
        result['nodes'] = sum(record['nodes'] for other, record in records)
        result['seconds'] = round(sum(record['seconds'] for other, record in records), 2)
        return result

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
