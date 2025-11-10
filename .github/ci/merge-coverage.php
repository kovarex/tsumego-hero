<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Xdebug3Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;

static $includePrefix = '/home/runner/work/tsumego-hero/tsumego-hero/src/';

function isExcluded($fileName): bool
{
	global $includePrefix;
	return !str_starts_with($fileName, $includePrefix);
}

$filter = new Filter();
$driver = new Xdebug3Driver($filter);
$mergedRaw = [];
$mergedCount = 0;
$skipped = 0;

foreach (glob('/tmp/coverage/webservercoverage*.cov') as $file) {
	$data = @file_get_contents($file);
	if ($data === false) continue;

	$decoded = @unserialize($data);
	if ($decoded instanceof CodeCoverage) {
		// Directly merge newer-format files
		$raw = $decoded->getData(true);
		$array = $raw->asArray();
	} elseif (is_array($decoded)) {
		// Raw Xdebug array
		$array = $decoded;
	} else {
		$skipped++;
		continue;
	}

	// Merge arrays efficiently
	foreach ($array as $fileName => $lines) {
		if (isExcluded($fileName)) {
			continue;
		}
		if (!isset($mergedRaw[$fileName])) {
			$mergedRaw[$fileName] = $lines;
		} else {
			foreach ($lines as $line => $hit) {
				if (($mergedRaw[$fileName][$line] ?? 0) !== 1 && $hit === 1) {
					$mergedRaw[$fileName][$line] = 1;
				}
			}
		}
	}

	$mergedCount++;
}

echo "[merge] Found {$mergedCount} valid coverage files, {$skipped} skipped\n";

$coverage = new CodeCoverage($driver, $filter);
$coverage->append(
	RawCodeCoverageData::fromXdebugWithoutPathCoverage($mergedRaw),
	'merged'
);

// Merge PHPUnit CLI coverage if present
$phpunitCov = '/tmp/coverage/phpunit.cov';
if (file_exists($phpunitCov)) {
	$decoded = require($phpunitCov);
	if ($decoded instanceof CodeCoverage) {
		// Merge first to preserve raw hit data
		$coverage->merge($decoded);
		echo "[merge] Merged PHPUnit CodeCoverage successfully\n";

		// Now prune unwanted files from final merged dataset
		$raw = $coverage->getData(true)->lineCoverage();
		$filtered = [];
		foreach ($raw as $fileName => $lines) {
			if (!isExcluded($fileName)) {
				$filtered[$fileName] = $lines;
			}
		}

		// Replace with the filtered dataset
		$coverage = new CodeCoverage($driver, $filter);
		$coverage->append(RawCodeCoverageData::fromXdebugWithoutPathCoverage($filtered),'merged');
	} else {
		echo "[merge] The main file phpunit.cov did not return a CodeCoverage instance (" . gettype($decoded) . ")\n";
	}
} else {
	echo "[merge] The main file phpunit.cov couldn't be found.\n";
}

$reportDir = __DIR__ . '/../../coverage';
@mkdir($reportDir, 0777, true);

$report = new HtmlReport();
$report->process($coverage, $reportDir);

echo "✅ Merged coverage written to ./coverage\n";
