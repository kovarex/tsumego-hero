// The outside of the region is an uncapturable wall of the attacker: attacker chains touching it are safe, it gives no
// liberties to the defender.
// Exact local tsumego solver: depth-first iterative deepening proof search with a transposition table,
// restricted to a region, Benson's unconditional life as the static "alive" test.
// Protocol (stdin, one command per line):
//   problem <size> <attacker B|W> <board size*size chars: . X O #(outside, empty, unplayable)> <target indices comma separated>
//   solve <toMove B|W> <ko index or -1> <budget> <board>   -> "result A=<K|N|U> D=<K|N|U> nodes=<n> bestA=<i> bestD=<i>"
// A: the defender has infinite ko threats (attacker must kill without ko); D: the attacker has infinite ko threats.
// K = attacker kills (captures a target stone), N = attacker can't kill, U = budget ran out.
#include <bits/stdc++.h>
using namespace std;
typedef uint64_t u64;

enum { EMPTY = 0, BLACK = 1, WHITE = 2, OUTSIDE = 3 };
enum { UNKNOWN = 0, KILL = 1, NOKILL = 2 };
enum { MODE_A = 0, MODE_D = 1, MODE_N = 2 }; // N: each side has the counted ko threats of the state (none by default)
int searchMode = 0; // the ko mode of the search running now

int N, NN;
int koFreeColor = 0; // the colour that may not capture in a ko (clean solving), 0 for none
vector<array<int, 4>> neighbours;
vector<int> neighbourCount;
int attacker, defender;
vector<int> target;
vector<int> active; // the points inside the region
bool fringe[361]; // empty edge points, playable only while a chain next to them is short of liberties
u64 zobrist[3][361], zobristKo[362], zobristSide, zobristPass, zobristThreats[3][16];

struct State
{
  uint8_t c[361];
  int ko;
  int toMove;
  int passes;
  u64 hash;
  int koBreaks; // recaptures made against the ko rule by the side with unlimited threats, not part of the hash
  int threats[3]; // counted ko threats left per colour (mode N), part of the hash
};

static int markStamp = 1;
static int mark[361];

// stones of the chain at p, returns liberty count (stops counting at maxLiberties)
int chain(const State& s, int p, vector<int>* stones, int maxLiberties, bool* touchesOutside = nullptr)
{
  ++markStamp;
  int color = s.c[p];
  static int stack[361];
  int top = 0, liberties = 0;
  stack[top++] = p;
  mark[p] = markStamp;
  if (touchesOutside)
    *touchesOutside = false;
  while (top)
  {
    int q = stack[--top];
    if (stones)
      stones->push_back(q);
    for (int i = 0; i < neighbourCount[q]; ++i)
    {
      int r = neighbours[q][i];
      if (mark[r] == markStamp)
        continue;
      if (s.c[r] == color)
      {
        mark[r] = markStamp;
        stack[top++] = r;
      }
      else if (s.c[r] == EMPTY || (s.c[r] == OUTSIDE && color == attacker))
      {
        mark[r] = markStamp;
        ++liberties;
        if (s.c[r] == OUTSIDE && touchesOutside)
          *touchesOutside = true;
        if (liberties >= maxLiberties && !touchesOutside)
          return liberties;
      }
    }
  }
  return liberties;
}

u64 positionKey(const State& s)
{
  return s.hash ^ zobristKo[s.ko + 1] ^ (s.toMove == WHITE ? zobristSide : 0) ^ (s.passes ? zobristPass : 0) ^
         zobristThreats[BLACK][s.threats[BLACK]] ^ zobristThreats[WHITE][s.threats[WHITE]];
}

bool play(State& s, int p, bool ignoreKo, int* capturedOut = nullptr)
{
  int color = s.toMove, opponent = 3 - color;
  if (p < 0)
  {
    s.passes++;
    s.ko = -1;
    s.toMove = opponent;
    return true;
  }
  if (s.c[p] != EMPTY)
    return false;
  State before;
  bool koPoint = p == s.ko && !ignoreKo;
  if (koPoint)
    before = s;
  s.c[p] = color;
  s.hash ^= zobrist[color][p];
  int captured = 0, capturedPoint = -1;
  for (int i = 0; i < neighbourCount[p]; ++i)
  {
    int r = neighbours[p][i];
    if (s.c[r] != opponent)
      continue;
    vector<int> stones;
    if (chain(s, r, &stones, 1) == 0)
    {
      for (int q : stones)
      {
        s.c[q] = EMPTY;
        s.hash ^= zobrist[opponent][q];
      }
      captured += stones.size();
      capturedPoint = stones[0];
    }
  }
  // the ko rule forbids only the immediate recapture of the single stone
  if (koPoint && captured == 1)
  {
    s = before;
    return false;
  }
  if (p == s.ko && ignoreKo && captured == 1)
  {
    ++s.koBreaks;
    //counted threats: the retake stands for a threat, its answer and the retake
    if (searchMode == MODE_N)
      --s.threats[color];
  }
  // with clean solving on, the solver's side may not capture in a ko: a result it needs a ko capture for is a ko
  if (color == koFreeColor && captured == 1)
  {
    vector<int> stones;
    if (chain(s, p, &stones, 2) == 1 && stones.size() == 1)
      return false;
  }
  vector<int> own;
  int liberties = chain(s, p, &own, 2);
  if (liberties == 0)
    return false; // suicide
  s.ko = (captured == 1 && own.size() == 1 && liberties == 1) ? capturedPoint : -1;
  s.passes = 0;
  s.toMove = opponent;
  if (capturedOut)
    *capturedOut = captured;
  return true;
}

// Benson's unconditional life for the defender; chains touching the outside count as safe.
// Fills alive[point] for every defender stone.
static bool alive[361];
static int chainOf[361], regionOf[361];
void bensonAlive(const State& s)
{
  for (int i = 0; i < NN; ++i)
  {
    chainOf[i] = regionOf[i] = -1;
    alive[i] = false;
  }
  static vector<int> chainStones[361];
  int chainCount = 0;
  bool safe[361];
  for (int i : active)
    if (s.c[i] == defender && chainOf[i] < 0)
    {
      chainStones[chainCount].clear();
      bool outside;
      chain(s, i, &chainStones[chainCount], 1000, &outside);
      for (int q : chainStones[chainCount])
        chainOf[q] = chainCount;
      safe[chainCount] = outside;
      ++chainCount;
    }
  // regions of non-defender points, those reaching the outside are useless
  static vector<int> regionEmpties[361], regionBorder[361];
  static bool regionValid[361];
  int regionCount = 0;
  static int stack[361];
  for (int i : active)
    if (s.c[i] != defender && s.c[i] != OUTSIDE && regionOf[i] < 0)
    {
      int id = regionCount++;
      regionEmpties[id].clear();
      regionBorder[id].clear();
      regionValid[id] = true;
      int top = 0;
      stack[top++] = i;
      regionOf[i] = id;
      while (top)
      {
        int q = stack[--top];
        if (s.c[q] == EMPTY)
          regionEmpties[id].push_back(q);
        for (int k = 0; k < neighbourCount[q]; ++k)
        {
          int r = neighbours[q][k];
          if (s.c[r] == OUTSIDE)
            regionValid[id] = false;
          else if (s.c[r] == defender)
          {
            int c = chainOf[r];
            if (find(regionBorder[id].begin(), regionBorder[id].end(), c) == regionBorder[id].end())
              regionBorder[id].push_back(c);
          }
          else if (regionOf[r] < 0)
          {
            regionOf[r] = id;
            stack[top++] = r;
          }
        }
      }
    }
  // vitalTo[region] = chains to which every empty point of the region is adjacent
  static vector<int> vitalTo[361];
  bool regionAlive[361], chainAlive[361];
  for (int r = 0; r < regionCount; ++r)
  {
    vitalTo[r].clear();
    regionAlive[r] = regionValid[r];
    if (!regionValid[r])
      continue;
    for (int chainIndex : regionBorder[r])
    {
      bool vital = true;
      for (int q : regionEmpties[r])
      {
        bool adjacent = false;
        for (int k = 0; k < neighbourCount[q]; ++k)
          if (chainOf[neighbours[q][k]] == chainIndex)
            adjacent = true;
        if (!adjacent)
        {
          vital = false;
          break;
        }
      }
      if (vital)
        vitalTo[r].push_back(chainIndex);
    }
  }
  for (int c = 0; c < chainCount; ++c)
    chainAlive[c] = true;
  bool changed = true;
  while (changed)
  {
    changed = false;
    int vitalCount[361] = {0};
    for (int r = 0; r < regionCount; ++r)
      if (regionAlive[r])
        for (int chainIndex : vitalTo[r])
          ++vitalCount[chainIndex];
    for (int c = 0; c < chainCount; ++c)
      if (chainAlive[c] && !safe[c] && vitalCount[c] < 2)
      {
        chainAlive[c] = false;
        changed = true;
      }
    for (int r = 0; r < regionCount; ++r)
      if (regionAlive[r])
        for (int chainIndex : regionBorder[r])
          if (!chainAlive[chainIndex])
          {
            regionAlive[r] = false;
            changed = true;
            break;
          }
  }
  for (int c = 0; c < chainCount; ++c)
    if (chainAlive[c])
      for (int q : chainStones[c])
        alive[q] = true;
}

// The defender's area: the empty and defender points connected to the target. When every attacker chain around it is
// safe (touches the outside wall), the empty points are all the eye space there will ever be. A part of at most 2
// points gives at most one eye, a part of 3 gives two only when the defender moves next.
bool noTwoEyes(const State& s)
{
  static int seen[361], seenStamp = 0;
  ++seenStamp;
  static int stack[361], component[361];
  int top = 0;
  for (int p : target)
    if (s.c[p] == defender && seen[p] != seenStamp)
    {
      seen[p] = seenStamp;
      stack[top++] = p;
    }
  vector<int> empties;
  vector<int> borderAttackers;
  while (top)
  {
    int q = stack[--top];
    if (s.c[q] == EMPTY)
      empties.push_back(q);
    for (int k = 0; k < neighbourCount[q]; ++k)
    {
      int r = neighbours[q][k];
      if (seen[r] == seenStamp)
        continue;
      if (s.c[r] == EMPTY || s.c[r] == defender)
      {
        seen[r] = seenStamp;
        stack[top++] = r;
      }
      else if (s.c[r] == attacker)
      {
        seen[r] = seenStamp;
        borderAttackers.push_back(r);
      }
    }
  }
  if (empties.size() > 8)
    return false;
  for (int a : borderAttackers)
  {
    bool outside;
    chain(s, a, nullptr, 1000, &outside);
    if (!outside)
      return false;
  }
  // components of the empty points
  static int componentStamp[361], stampValue = 0;
  ++stampValue;
  for (int e : empties)
    component[e] = -1;
  int eyes = 0;
  for (int e : empties)
  {
    if (componentStamp[e] == stampValue)
      continue;
    int size = 0;
    top = 0;
    stack[top++] = e;
    componentStamp[e] = stampValue;
    while (top)
    {
      int q = stack[--top];
      ++size;
      for (int k = 0; k < neighbourCount[q]; ++k)
      {
        int r = neighbours[q][k];
        if (s.c[r] == EMPTY && componentStamp[r] != stampValue && seen[r] == seenStamp)
        {
          componentStamp[r] = stampValue;
          stack[top++] = r;
        }
      }
    }
    if (size <= 2)
      eyes += 1;
    else if (size == 3)
      eyes += s.toMove == defender ? 2 : 1;
    else
      eyes += 2;
    if (eyes >= 2)
      return false;
  }
  return true;
}

bool bentFourRule = false;
struct Result;
Result proofSearch(State& s, int mode);
bool bentFourEnd(const State& s);

// a bent four in a corner: a 4-point L of non-defender points (empty or attacker stones), enclosed by defender stones
bool bentFour(const State& s)
{
  int corners[4][2] = {{0, 0}, {N - 1, 0}, {0, N - 1}, {N - 1, N - 1}};
  for (auto& corner : corners)
  {
    int cx = corner[0], cy = corner[1], dx = cx == 0 ? 1 : -1, dy = cy == 0 ? 1 : -1;
    int shapes[2][4][2] = {{{cx, cy}, {cx + dx, cy}, {cx + 2 * dx, cy}, {cx, cy + dy}},
                           {{cx, cy}, {cx, cy + dy}, {cx, cy + 2 * dy}, {cx + dx, cy}}};
    for (auto& shape : shapes)
    {
      bool inShape[361] = {false};
      bool ok = true;
      for (auto& q : shape)
      {
        int p = q[1] * N + q[0];
        if (s.c[p] == defender || s.c[p] == OUTSIDE)
          ok = false;
        inShape[p] = true;
      }
      for (auto& q : shape)
        if (ok)
        {
          int p = q[1] * N + q[0];
          for (int k = 0; k < neighbourCount[p]; ++k)
          {
            int r = neighbours[p][k];
            if (!inShape[r] && s.c[r] != defender)
              ok = false;
          }
        }
      if (ok)
        return true;
    }
  }
  return false;
}

// The attacker kills when no target point holds a defender stone; the defender lives when a target stone is in an
// unconditionally alive chain. The outside is the attacker's wall, so reaching it is no escape (a group running out of
// the region, like 840 towards B10, counts as shut in).
int terminalUncached(const State& s);

// the end tests repeat for every transposition, so their results are kept by position
const int TERMINAL_BITS = 20;
struct TerminalEntry { u64 key; int8_t result; };
vector<TerminalEntry> terminalTable(1 << TERMINAL_BITS);

int terminal(const State& s)
{
  if (s.passes >= 2)
    return terminalUncached(s);
  u64 key = s.hash ^ (s.toMove == WHITE ? zobristSide : 0) ^ 0x9e3779b97f4a7c15ULL;
  TerminalEntry& entry = terminalTable[key & ((1 << TERMINAL_BITS) - 1)];
  if (entry.key == key)
    return entry.result;
  int result = terminalUncached(s);
  entry = {key, (int8_t)result};
  return result;
}

int terminalUncached(const State& s)
{
  bool anyLeft = false;
  for (int p : target)
    if (s.c[p] == defender)
    {
      anyLeft = true;
      bool outside;
      chain(s, p, nullptr, 1000, &outside);
      if (outside)
        return NOKILL;
    }
  if (!anyLeft)
    return KILL;
  if (s.passes >= 2)
  {
    // with the bent four rule on, a defender left with a bent four in the corner is dead: the attacker removes all
    // ko threats first and then starts the ko
    if (bentFourRule && searchMode == MODE_A && bentFourEnd(s))
      return KILL;
    // the game ends: a defender chain still in atari (a target chain, or a stone the defender's eye shape depends on,
    // like a ko stone making a false eye) is taken at the end, and the defender has no threats left to retake. So
    // unless the target is unconditionally alive, only a group where nothing can be taken (seki or life) survives
    bensonAlive(s);
    for (int p : target)
      if (s.c[p] == defender && alive[p])
        return NOKILL;
    //with counted threats left the defender could still retake, so the attacker has to play it out instead of passing
    //only chains in the target's own area count: a stray defender stone at the region's edge decides nothing
    if (searchMode != MODE_N || s.threats[defender] == 0)
    {
      static int inArea[361], areaStamp = 0;
      static int stack[361];
      ++areaStamp;
      int top = 0;
      for (int p : target)
        if (s.c[p] == defender && inArea[p] != areaStamp)
        {
          inArea[p] = areaStamp;
          stack[top++] = p;
        }
      while (top)
      {
        int q = stack[--top];
        for (int k = 0; k < neighbourCount[q]; ++k)
        {
          int r = neighbours[q][k];
          if (inArea[r] != areaStamp && (s.c[r] == EMPTY || s.c[r] == defender))
          {
            inArea[r] = areaStamp;
            stack[top++] = r;
          }
        }
      }
      //and the defender stones next to an attacker chain there that could be captured (not touching the outside wall):
      //they decide that capture
      static int seenAttacker[361];
      vector<int> attackerStones;
      for (int p : active)
        if (inArea[p] == areaStamp && s.c[p] != attacker)
          for (int k = 0; k < neighbourCount[p]; ++k)
          {
            int r = neighbours[p][k];
            if (s.c[r] != attacker || seenAttacker[r] == areaStamp)
              continue;
            attackerStones.clear();
            bool outside;
            chain(s, r, &attackerStones, 1000, &outside);
            for (int a : attackerStones)
              seenAttacker[a] = areaStamp;
            if (!outside)
              for (int a : attackerStones)
                for (int j = 0; j < neighbourCount[a]; ++j)
                  if (s.c[neighbours[a][j]] == defender)
                    inArea[neighbours[a][j]] = areaStamp;
          }
      for (int p : active)
        if (s.c[p] == defender && inArea[p] == areaStamp && chain(s, p, nullptr, 2) == 1)
          return KILL;
    }
    return NOKILL;
  }
  bensonAlive(s);
  for (int p : target)
    if (s.c[p] == defender && alive[p])
      return NOKILL;
  if (noTwoEyes(s))
    return KILL;
  return UNKNOWN;
}

struct Entry { u64 key; int8_t result; int8_t depth; int16_t best; };
const int TT_BITS = 21;
vector<Entry> table[2];
long long nodes, budget;
bool aborted;
vector<u64> path;
vector<int> pathKoBreaks; // koBreaks of the positions on the path
int history[362];

struct Result { int result; bool repetition; int best; };

int distanceToTarget[361];

Result search(State& s, int depth, int mode)
{
  if (++nodes > budget)
  {
    aborted = true;
    return {UNKNOWN, false, -2};
  }
  int t = terminal(s);
  if (t)
    return {t, false, -2};
  u64 key = positionKey(s);
  for (u64 k : path)
    if (k == key)
      return {NOKILL, true, -2};
  Entry& entry = table[mode][key & ((1 << TT_BITS) - 1)];
  int ttBest = -2;
  if (entry.key == key)
  {
    if (entry.result != UNKNOWN)
      return {entry.result, false, entry.best};
    if (entry.depth >= depth)
      return {UNKNOWN, false, entry.best};
    ttBest = entry.best;
  }
  if (depth == 0)
    return {UNKNOWN, false, -2};

  bool attackerMoves = s.toMove == attacker;
  bool ignoreKo = (mode == MODE_D) == attackerMoves;
  int want = attackerMoves ? KILL : NOKILL;

  // target liberties for ordering
  static int targetLiberty[361];
  static int targetLibertyStamp = 0;
  ++targetLibertyStamp;
  {
    vector<int> stones;
    for (int p : target)
      if (s.c[p] == defender && targetLiberty[p] != targetLibertyStamp)
      {
        size_t before = stones.size();
        chain(s, p, &stones, 1000);
        for (size_t i = before; i < stones.size(); ++i)
          targetLiberty[stones[i]] = targetLibertyStamp;
      }
    for (int q : stones)
      for (int k = 0; k < neighbourCount[q]; ++k)
        if (s.c[neighbours[q][k]] == EMPTY)
          targetLiberty[neighbours[q][k]] = targetLibertyStamp;
  }

  struct Child { State state; int move; int score; };
  vector<Child> children;
  for (int p = 0; p < NN; ++p)
  {
    if (s.c[p] != EMPTY)
      continue;
    Child child{s, p, 0};
    int captured = 0;
    if (!play(child.state, p, ignoreKo, &captured))
      continue;
    int score = -distanceToTarget[p] * 10 + history[p];
    if (captured)
      score += 1000 + captured * 50;
    if (targetLiberty[p] == targetLibertyStamp)
      score += 500;
    if (p == ttBest)
      score += 1000000;
    child.score = score;
    children.push_back(child);
  }
  sort(children.begin(), children.end(), [](const Child& a, const Child& b) { return a.score > b.score; });
  {
    Child pass{s, -1, ttBest == -1 ? 1000000 : -1000000};
    play(pass.state, -1, ignoreKo);
    if (ttBest == -1)
      children.insert(children.begin(), pass);
    else
      children.push_back(pass);
  }

  path.push_back(key);
  bool anyUnknown = false, repetition = false;
  int result = 0, best = -2;
  for (Child& child : children)
  {
    Result r = search(child.state, depth - 1, mode);
    if (aborted)
    {
      path.pop_back();
      return {UNKNOWN, false, -2};
    }
    if (r.result == want)
    {
      result = want;
      repetition = r.repetition;
      best = child.move;
      history[child.move + 1 > 0 ? child.move : 361] += depth * depth;
      break;
    }
    if (r.result == UNKNOWN)
      anyUnknown = true;
    else
      repetition = repetition || r.repetition;
  }
  path.pop_back();
  if (!result)
  {
    if (anyUnknown)
    {
      if (entry.key != key || entry.result == UNKNOWN)
        entry = {key, UNKNOWN, (int8_t)depth, (int16_t)(ttBest)};
      return {UNKNOWN, false, ttBest};
    }
    result = attackerMoves ? NOKILL : KILL;
  }
  if (!repetition)
    entry = {key, (int8_t)result, (int8_t)depth, (int16_t)best};
  return {result, repetition, best};
}

// df-pn (Nagai; Kishimoto & Müller for tsumego): the attacker proves KILL, OR nodes are attacker moves.
const uint32_t INF = 100000000;
struct ProofEntry { u64 key; uint32_t pn, dn; int16_t best; uint32_t generation; };
// a solve only sees its own entries: with repetitions a proof can depend on the path that led to the position
uint32_t generation = 1;
const int PN_BITS = 22;
vector<ProofEntry> proofTable[3];

struct ProofChild { State state; int move; u64 key; int score; int terminal; int liberties; };

void lookup(int mode, u64 key, uint32_t& pn, uint32_t& dn, int& best)
{
  ProofEntry& e = proofTable[mode][key & ((1 << PN_BITS) - 1)];
  if (e.key == key && e.generation == generation) { pn = e.pn; dn = e.dn; best = e.best; }
  else { pn = 1; dn = 1; best = -2; }
}

void store(int mode, u64 key, uint32_t pn, uint32_t dn, int best)
{
  ProofEntry& e = proofTable[mode][key & ((1 << PN_BITS) - 1)];
  // proven entries are kept over unproven ones of other positions
  if (e.key != key && e.generation == generation && (e.pn == 0 || e.dn == 0) && pn != 0 && dn != 0)
    return;
  e = {key, pn, dn, (int16_t)best, generation};
}

void generate(const State& s, bool ignoreKo, vector<ProofChild>& children, int ttBest)
{
  static int targetLiberty[361];
  static int stamp = 0;
  ++stamp;
  vector<int> stones;
  for (int p : target)
    if (s.c[p] == defender && targetLiberty[p] != stamp)
    {
      size_t before = stones.size();
      chain(s, p, &stones, 1000);
      for (size_t i = before; i < stones.size(); ++i)
        targetLiberty[stones[i]] = stamp;
    }
  for (int q : stones)
    for (int k = 0; k < neighbourCount[q]; ++k)
      if (s.c[neighbours[q][k]] == EMPTY)
        targetLiberty[neighbours[q][k]] = stamp;
  for (int p : active)
  {
    if (s.c[p] != EMPTY)
      continue;
    if (fringe[p])
    {
      // the attacker may always take the defender's outside liberties, else a loose group keeps them forever
      bool threatened = false;
      for (int k = 0; k < neighbourCount[p] && !threatened; ++k)
      {
        int q = neighbours[p][k];
        if (s.c[q] == defender && s.toMove == attacker)
          threatened = true;
        else if ((s.c[q] == BLACK || s.c[q] == WHITE) && chain(s, q, nullptr, 3) <= 2)
          threatened = true;
      }
      if (!threatened)
        continue;
    }
    ProofChild child{s, p, 0, 0, 0, 0};
    int captured = 0;
    if (!play(child.state, p, ignoreKo, &captured))
      continue;
    int score = -distanceToTarget[p] * 10 + history[p];
    if (captured)
      score += 1000 + captured * 50;
    if (targetLiberty[p] == stamp)
      score += 500;
    if (p == ttBest)
      score += 1000000;
    child.score = score;
    child.key = positionKey(child.state);
    child.terminal = terminal(child.state);
#ifdef HEURISTIC_INIT
    child.liberties = 1000;
    for (int t : target)
      if (child.state.c[t] == defender)
        child.liberties = min(child.liberties, chain(child.state, t, nullptr, 8));
#endif
    children.push_back(child);
  }
  ProofChild pass{s, -1, 0, ttBest == -1 ? 1000000 : -1000000, 0, 0};
  play(pass.state, -1, ignoreKo);
  pass.key = positionKey(pass.state);
  pass.terminal = terminal(pass.state);
  children.push_back(pass);
  stable_sort(children.begin(), children.end(), [](const ProofChild& a, const ProofChild& b) { return a.score > b.score; });
}

// child values with terminal positions and repetitions resolved
void childValues(int mode, ProofChild& child, uint32_t& pn, uint32_t& dn)
{
#ifndef NO_REPETITION
  for (size_t i = 0; i < path.size(); ++i)
    if (path[i] == child.key)
    {
      // a cycle through a ko recapture is a ko nobody resolves: at the end the attacker takes it, dead;
      // a cycle of legal captures (sending two, returning one) is a seki
      if (child.state.koBreaks > pathKoBreaks[i]) { pn = 0; dn = INF; }
      else { pn = INF; dn = 0; }
      return;
    }
#endif
  if (child.terminal == KILL) { pn = 0; dn = INF; return; }
  if (child.terminal == NOKILL) { pn = INF; dn = 0; return; }
  int best;
  lookup(mode, child.key, pn, dn, best);
#ifdef HEURISTIC_INIT
  // df-pn+: an unexplored position starts from how short of liberties the target is
  if (pn == 1 && dn == 1 && child.liberties < 1000)
  {
    pn = max(1, child.liberties - 1);
    dn = max(1, 4 - child.liberties);
  }
#endif
}

void mid(State& s, u64 key, uint32_t thpn, uint32_t thdn, int mode)
{
  if (++nodes > budget)
  {
    aborted = true;
    return;
  }
  bool orNode = s.toMove == attacker;
  bool ignoreKo = mode == MODE_N ? s.threats[s.toMove] > 0 : (mode == MODE_D) == orNode;
  uint32_t pn, dn;
  int ttBest;
  lookup(mode, key, pn, dn, ttBest);
  vector<ProofChild> children;
  generate(s, ignoreKo, children, ttBest);
  path.push_back(key);
  pathKoBreaks.push_back(s.koBreaks);
  int best = -2;
  while (true)
  {
    // aggregate
    uint32_t minValue = INF, second = INF, sum = 0;
    int bestIndex = -1;
    uint32_t bestPn = 0, bestDn = 0;
    for (size_t i = 0; i < children.size(); ++i)
    {
      uint32_t cpn, cdn;
      childValues(mode, children[i], cpn, cdn);
      uint32_t selector = orNode ? cpn : cdn, other = orNode ? cdn : cpn;
      sum = min(INF, sum + other);
      if (selector < minValue)
      {
        second = minValue;
        minValue = selector;
        bestIndex = i;
        bestPn = cpn;
        bestDn = cdn;
      }
      else if (selector < second)
        second = selector;
    }
    if (orNode) { pn = minValue; dn = sum; }
    else { dn = minValue; pn = sum; }
    if (bestIndex >= 0)
      best = children[bestIndex].move;
    if (pn >= thpn || dn >= thdn || aborted)
      break;
    ProofChild& child = children[bestIndex];
    uint32_t childThpn, childThdn;
    if (orNode)
    {
      childThpn = min(thpn, second == INF ? INF : second + second / 4 + 1); // 1+epsilon trick
      childThdn = min((uint64_t)INF, (uint64_t)thdn - dn + bestDn);
    }
    else
    {
      childThdn = min(thdn, second == INF ? INF : second + second / 4 + 1);
      childThpn = min((uint64_t)INF, (uint64_t)thpn - pn + bestPn);
    }
    mid(child.state, child.key, childThpn, childThdn, mode);
    if (aborted)
      break;
  }
  path.pop_back();
  pathKoBreaks.pop_back();
  if (!aborted)
  {
    store(mode, key, pn, dn, best);
    if (pn == 0 || dn == 0)
      history[best < 0 ? 361 : best] += 1;
  }
}

Result proofSearch(State& s, int mode)
{
  int previousMode = searchMode;
  searchMode = mode;
  int t = terminal(s);
  if (t)
  {
    searchMode = previousMode;
    return {t, false, -2};
  }
  u64 key = positionKey(s);
  // a search can run inside another one (the bent four end below), which keeps its own path
  vector<u64> outerPath;
  vector<int> outerKoBreaks;
  swap(outerPath, path);
  swap(outerKoBreaks, pathKoBreaks);
  s.koBreaks = 0;
  mid(s, key, INF - 1, INF - 1, mode);
  swap(outerPath, path);
  swap(outerKoBreaks, pathKoBreaks);
  searchMode = previousMode;
  uint32_t pn, dn;
  int best;
  lookup(mode, key, pn, dn, best);
  if (pn == 0)
    return {KILL, false, best};
  if (dn == 0)
    return {NOKILL, false, best};
  return {UNKNOWN, false, best};
}

// The game ended while the defender relies on ko threats: the attacker first removes them and then starts the ko when
// it likes. If it wins that with neither side having threats, the defender was dead all along (bent four in the corner).
bool bentFourEnd(const State& s)
{
  State t = s;
  t.passes = 0;
  t.ko = -1;
  t.threats[BLACK] = t.threats[WHITE] = 0;
  t.toMove = attacker;
  bool previousAborted = aborted;
  Result r = proofSearch(t, MODE_N);
  bool result = !aborted && r.result == KILL;
  aborted = previousAborted;
  return result;
}

// Move ordering learned in earlier queries usually helps, but it can also send a search into endless cycling.
// An undecided search gets one more try with fresh ordering, a new table generation and the same budget.
Result retryFresh(State& s, int mode)
{
  memset(history, 0, sizeof(history));
  ++generation;
  long long spent = nodes;
  nodes = 0;
  aborted = false;
  Result r = proofSearch(s, mode);
  nodes += spent;
  return r;
}

void parseBoard(const string& text, State& s)
{
  for (int i = 0; i < NN; ++i)
  {
    char ch = text[i];
    s.c[i] = ch == 'X' ? BLACK : ch == 'O' ? WHITE : ch == '#' ? OUTSIDE : EMPTY; // 'f' is an empty fringe point
  }
  s.hash = 0;
  for (int i = 0; i < NN; ++i)
    if (s.c[i] == BLACK || s.c[i] == WHITE)
      s.hash ^= zobrist[s.c[i]][i];
}

int main()
{
  mt19937_64 random(12345);
  for (int c = 0; c < 3; ++c)
    for (int i = 0; i < 361; ++i)
      zobrist[c][i] = random();
  for (int i = 0; i < 362; ++i)
    zobristKo[i] = random();
  zobristSide = random();
  zobristPass = random();
  for (int c = 0; c < 3; ++c)
    for (int i = 0; i < 16; ++i)
      zobristThreats[c][i] = i ? random() : 0;
  ios::sync_with_stdio(false);
  string command;
  while (cin >> command)
  {
    if (command == "problem")
    {
      string attackerText, board, targetText;
      cin >> N >> attackerText >> board >> targetText;
      NN = N * N;
      neighbours.assign(NN, {});
      neighbourCount.assign(NN, 0);
      for (int y = 0; y < N; ++y)
        for (int x = 0; x < N; ++x)
        {
          int p = y * N + x;
          if (x > 0) neighbours[p][neighbourCount[p]++] = p - 1;
          if (x < N - 1) neighbours[p][neighbourCount[p]++] = p + 1;
          if (y > 0) neighbours[p][neighbourCount[p]++] = p - N;
          if (y < N - 1) neighbours[p][neighbourCount[p]++] = p + N;
        }
      attacker = attackerText == "B" ? BLACK : WHITE;
      defender = 3 - attacker;
      target.clear();
      stringstream stream(targetText);
      string item;
      while (getline(stream, item, ','))
        target.push_back(stoi(item));
      active.clear();
      for (int i = 0; i < NN; ++i)
        if (board[i] != '#')
          active.push_back(i);
      for (int i = 0; i < NN; ++i)
        fringe[i] = board[i] == 'f';
      for (int i = 0; i < NN; ++i)
      {
        int best = 1000;
        for (int p : target)
          best = min(best, abs(p % N - i % N) + abs(p / N - i / N));
        distanceToTarget[i] = best;
      }
      for (int m = 0; m < 3; ++m)
        proofTable[m].assign(1 << PN_BITS, ProofEntry{0, 0, 0, 0, 0});
      terminalTable.assign(1 << TERMINAL_BITS, TerminalEntry{0, 0});
      memset(history, 0, sizeof(history));
      cout << "ok" << endl;
    }
    else if (command == "kofree")
    {
      string side;
      cin >> side;
      koFreeColor = side == "B" ? BLACK : side == "W" ? WHITE : 0;
      cout << "ok" << endl;
    }
    else if (command == "bentfour")
    {
      int value;
      cin >> value;
      bentFourRule = value != 0;
      cout << "ok" << endl;
    }
    else if (command == "solvecount")
    {
      // counted ko threats: <toMove> <ko> <budget> <black threats> <white threats> <board>
      string side, board;
      int ko, blackThreats, whiteThreats;
      cin >> side >> ko >> budget >> blackThreats >> whiteThreats >> board;
      ++generation;
      State s;
      parseBoard(board, s);
      s.toMove = side == "B" ? BLACK : WHITE;
      s.ko = ko;
      s.passes = 0;
      s.koBreaks = 0;
      s.threats[0] = 0;
      s.threats[BLACK] = min(15, blackThreats);
      s.threats[WHITE] = min(15, whiteThreats);
      nodes = 0;
      aborted = false;
      Result r = proofSearch(s, MODE_N);
      if (r.result == UNKNOWN)
        r = retryFresh(s, MODE_N);
      cout << "result N=" << (r.result == KILL ? "K" : r.result == NOKILL ? "N" : "U") << " nodes=" << nodes << " best=" << r.best << endl;
    }
    else if (command == "solveplain")
    {
      // neither side has ko threats: the plain ko rule for both
      string side, board;
      int ko;
      cin >> side >> ko >> budget >> board;
      ++generation;
      State s;
      parseBoard(board, s);
      s.toMove = side == "B" ? BLACK : WHITE;
      s.ko = ko;
      s.passes = 0;
      s.koBreaks = 0;
      s.threats[BLACK] = s.threats[WHITE] = s.threats[0] = 0;
      nodes = 0;
      aborted = false;
      Result r = proofSearch(s, MODE_N);
      cout << "result N=" << (r.result == KILL ? "K" : r.result == NOKILL ? "N" : "U") << " nodes=" << nodes << endl;
    }
    else if (command == "solve")
    {
      string side, board;
      int ko;
      cin >> side >> ko >> budget >> board;
      ++generation;
      State s;
      parseBoard(board, s);
      s.toMove = side == "B" ? BLACK : WHITE;
      s.ko = ko;
      s.passes = 0;
      s.koBreaks = 0;
      s.threats[BLACK] = s.threats[WHITE] = s.threats[0] = 0;
      string out[2];
      int bests[2];
      long long total = 0;
      long long budgetPerMode = budget;
      for (int mode = 0; mode < 2; ++mode)
      {
        nodes = 0;
        budget = budgetPerMode;
        aborted = false;
        Result r{UNKNOWN, false, -2};
        r = proofSearch(s, mode);
        if (r.result == UNKNOWN)
          r = retryFresh(s, mode);
        total += nodes;
        out[mode] = r.result == KILL ? "K" : r.result == NOKILL ? "N" : "U";
        bests[mode] = r.best;
        // a kill without ko is a kill with ko too
        if (mode == MODE_A && r.result == KILL)
        {
          out[1] = "K";
          bests[1] = r.best;
          break;
        }
      }
      if (getenv("PV"))
        for (int mode = 0; mode < 2; ++mode)
        {
          // the line the proof follows, from the stored best moves
          State line = s;
          cerr << "pv" << (mode ? "D" : "A") << ":";
          for (int ply = 0; ply < 30; ++ply)
          {
            uint32_t pn, dn;
            int best;
            lookup(mode, positionKey(line), pn, dn, best);
            if (best == -2 || terminal(line))
              break;
            bool attackerMoves = line.toMove == attacker;
            if (!play(line, best, (mode == MODE_D) == attackerMoves))
              break;
            cerr << " " << (best < 0 ? string("pass") : string(1, 'a' + best % N) + string(1, 'a' + best / N)) << "[" << pn << "/" << dn << "]";
          }
          cerr << " terminal=" << terminal(line) << endl;
        }
      cout << "result A=" << out[0] << " D=" << out[1] << " nodes=" << total << " bestA=" << bests[0] << " bestD=" << bests[1] << endl;
    }
  }
}
