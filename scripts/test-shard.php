<?php

//Splits the test files into shards of about equal running time, so CI can run them in parallel jobs.
//tests/durations.json holds the seconds each file took on CI; a file that isn't there yet (a new
//test) counts as the average of the known ones. The biggest files are dealt out first, each to the
//shard with the least time so far, so every file lands in exactly one shard.
//Usage:
//  php scripts/test-shard.php <shard> <shards>          the files of shard 1..<shards>, one per line
//  php scripts/test-shard.php durations <junit.xml...>  the durations of JUnit logs, as tests/durations.json

const ROOT = __DIR__ . '/..';
const DURATIONS = ROOT . '/tests/durations.json';

//Every test file, relative to the root, sorted so all shards see the same list.
function testFiles(): array
{
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/tests/TestCase', FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file)
		if (str_ends_with($file->getFilename(), 'Test.php'))
			$files[] = substr($file->getPathname(), strlen(ROOT) + 1);
	sort($files);
	return $files;
}

/** @return array<string, list<string>> */
function shards(int $count): array
{
	$known = is_file(DURATIONS) ? json_decode(file_get_contents(DURATIONS), true, flags: JSON_THROW_ON_ERROR) : [];
	$default = $known ? array_sum($known) / count($known) : 1.0;
	$durations = [];
	foreach (testFiles() as $file)
		$durations[$file] = $known[$file] ?? $default;
	uksort($durations, fn($a, $b) => [$durations[$b], $a] <=> [$durations[$a], $b]);

	$shards = array_fill(1, $count, []);
	$load = array_fill(1, $count, 0.0);
	foreach ($durations as $file => $seconds)
	{
		$shard = array_keys($load, min($load))[0];
		$shards[$shard][] = $file;
		$load[$shard] += $seconds;
	}
	return $shards;
}

//The seconds of each test file, summed over the test cases of all the given JUnit logs.
function durations(array $junitFiles): string
{
	$durations = [];
	foreach ($junitFiles as $junitFile)
		foreach (simplexml_load_file($junitFile)->xpath('//testcase') as $testcase)
		{
			// the logs come from other machines, so only the part from tests/ on is the same
			$file = strstr((string) $testcase['file'], 'tests/TestCase/');
			$durations[$file] = ($durations[$file] ?? 0) + (float) $testcase['time'];
		}
	$durations = array_map(fn($seconds) => round($seconds, 1), $durations);
	ksort($durations);
	return json_encode($durations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if (($argv[1] ?? '') === 'durations')
{
	echo durations(array_slice($argv, 2));
	exit(0);
}

$shard = (int) ($argv[1] ?? 0);
$count = (int) ($argv[2] ?? 0);
if ($count < 1 || $shard < 1 || $shard > $count)
{
	fwrite(STDERR, "Usage: php scripts/test-shard.php <shard> <shards>\n");
	exit(1);
}
echo implode(PHP_EOL, shards($count)[$shard]) . PHP_EOL;
