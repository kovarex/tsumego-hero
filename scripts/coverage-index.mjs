import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

// Reads the PHP Clover report (coverage/coverage.xml) and the JS coverage summary
// (coverage/frontend/coverage-summary.json), computes a combined coverage %,
// appends a snapshot to coverage/coverage-history.json and renders coverage/index.html
// (Chart.js trend + per-language summary).

const ROOT = process.cwd();
mkdirSync(join(ROOT, 'coverage'), { recursive: true });
const PHP_CLOVER = join(ROOT, 'coverage/coverage.xml');
const JS_SUMMARY = join(ROOT, 'coverage/frontend/coverage-summary.json');
const HISTORY = join(ROOT, 'coverage/coverage-history.json');
const INDEX = join(ROOT, 'coverage/index.html');

function parsePhpClover(file) {
	const xml = readFileSync(file, 'utf-8');
	let total = 0;
	let covered = 0;
	// Clover <line num="N" type="stmt|method|... " count="M"/>
	const re = /<line\s+[^>]*type="stmt"[^>]*count="(\d+)"/g;
	let m;
	while ((m = re.exec(xml))) {
		total++;
		if (parseInt(m[1], 10) > 0) covered++;
	}
	return { covered, total };
}

function parseJsSummary(file) {
	const data = JSON.parse(readFileSync(file, 'utf-8'));
	const lines = data.total?.lines || { total: 0, covered: 0 };
	return { covered: lines.covered, total: lines.total };
}

function pct(covered, total) {
	return total === 0 ? 0 : Math.round((covered / total) * 1000) / 10;
}

const php = existsSync(PHP_CLOVER) ? parsePhpClover(PHP_CLOVER) : { covered: 0, total: 0 };
const js = existsSync(JS_SUMMARY) ? parseJsSummary(JS_SUMMARY) : { covered: 0, total: 0 };
const combinedCovered = php.covered + js.covered;
const combinedTotal = php.total + js.total;

const snapshot = {
	date: new Date().toISOString().slice(0, 10),
	php: pct(php.covered, php.total),
	js: pct(js.covered, js.total),
	combined: pct(combinedCovered, combinedTotal),
};

// History: read existing (may be present from gh-pages restore), append, cap to latest 120.
let history = [];
if (existsSync(HISTORY)) {
	try {
		history = JSON.parse(readFileSync(HISTORY, 'utf-8'));
	} catch (e) {
		history = [];
	}
}
history.push(snapshot);
// Keep only the latest snapshot per day so the trend is a clean daily time-series
// (repeated same-day CI runs update the day's point rather than stacking duplicates).
if (history.length > 1 && history[history.length - 2].date === snapshot.date)
	history.splice(history.length - 2, 1);
if (history.length > 120) history = history.slice(-120);

writeFileSync(HISTORY, JSON.stringify(history, null, 2));

const chartData = history.map(h => ({ x: h.date, php: h.php, js: h.js, combined: h.combined }));

const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tsumego Hero Coverage</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
 body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 2rem; color: #222; }
 h1 { margin-bottom: .25rem; }
 .summary { display:flex; gap:2rem; margin:1.5rem 0; }
 .card { border:1px solid #ddd; border-radius:8px; padding:1rem 1.5rem; min-width:140px; }
 .card .big { font-size:2rem; font-weight:700; }
 .card .label { color:#666; font-size:.85rem; }
 canvas { max-width:900px; }
 a { color:#2563eb; }
 .links { margin:1rem 0; }
</style>
</head>
<body>
<h1>Tsumego Hero Code Coverage</h1>
<div class="summary">
 <div class="card"><div class="big">${snapshot.combined}%</div><div class="label">Combined (PHP + JS)</div></div>
 <div class="card"><div class="big">${snapshot.php}%</div><div class="label">PHP (${php.covered}/${php.total} lines)</div></div>
 <div class="card"><div class="big">${snapshot.js}%</div><div class="label">JS/TS (${js.covered}/${js.total} lines)</div></div>
</div>
<div class="links">
 <a href="./php/">PHP report</a> |
 <a href="./frontend/">JS/TS report</a>
</div>
<h2>Coverage history</h2>
<canvas id="chart"></canvas>
<script>
 const history = ${JSON.stringify(chartData)};
 const ctx = document.getElementById('chart');
 new Chart(ctx, {
  type: 'line',
  data: {
   labels: history.map(h => h.x),
   datasets: [
    { label: 'Combined', data: history.map(h => h.combined), borderColor: '#2563eb', tension: .2 },
    { label: 'PHP', data: history.map(h => h.php), borderColor: '#16a34a', tension: .2 },
    { label: 'JS/TS', data: history.map(h => h.js), borderColor: '#dc2626', tension: .2 },
   ]
  },
  options: { scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } } }
 });
</script>
</body>
</html>`;

writeFileSync(INDEX, html);
console.log(`Combined coverage: ${snapshot.combined}% (PHP ${snapshot.php}%, JS ${snapshot.js}%)`);
console.log(`History points: ${history.length}; wrote ${INDEX}`);
