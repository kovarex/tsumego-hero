import sys, time
sys.path.insert(0, '/tmp/claude-0/proto/research/kodesign/game/py')
import m121
from m121 import eng, position, idx
for line in ('', 'A17!'):
    text, ko, tm = position(line)
    for b in (200000, 2000000):
        t0 = time.time()
        print('[%s] %s to move budget %d: %s (%.1fs)' % (line or 'leaf', tm, b, eng.command('solve %s %d %d %s' % (tm, ko if ko is not None else -1, b, text)), time.time() - t0))
        sys.stdout.flush()
