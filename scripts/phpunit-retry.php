<?php

//Runs phpunit, then runs once more the tests that failed only because the browser did: a
//WebDriverException of Selenium in a test marked #[RetryOnBrowserFailure]. A failed assertion is
//never retried, and nothing is while another test failed for real. A rerun prints [RETRY] with the
//tests it runs again, so the flaky ones stay countable in the log.
//The retry wraps phpunit instead of living in a test case because PHPUnit 10 gives a test case no
//way to run a test again (runBare() and run() are final, runTest() is private). It reads the JUnit
//log, which PHPUnit 9, 10 and 11 all write, so it works the same before and after the move to Cake 5.
//Usage: php scripts/phpunit-retry.php [phpunit arguments...]

const BROWSER_FAILURE = 'Facebook\WebDriver\Exception\WebDriverException';

//The arguments of the rerun: the original ones without the coverage and log options, which
//belong to the first run.
function rerunArguments(array $arguments): array
{
	$kept = [];
	for ($i = 0; $i < count($arguments); $i++)
	{
		$name = explode('=', $arguments[$i], 2)[0];
		if (!str_starts_with($name, '--coverage') && !str_starts_with($name, '--log-'))
			$kept[] = $arguments[$i];
		elseif (!str_contains($arguments[$i], '=') && isset($arguments[$i + 1]) && !str_starts_with($arguments[$i + 1], '-'))
			$i++; // the value of the option
	}
	return $kept;
}

function runPhpunit(array $arguments, string $junitFile): int
{
	@unlink($junitFile);
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../vendor/bin/phpunit')
		. ' ' . implode(' ', array_map('escapeshellarg', [...$arguments, '--log-junit', $junitFile]));
	passthru($command, $exitCode);
	return $exitCode;
}

//The failed tests, as class::method, each with whether a rerun may make it pass: the browser
//was all that failed in it and the test is marked to be retried.
/** @return array<string, bool> */
function failedTests(string $junitFile): array
{
	$failed = [];
	if (!is_file($junitFile))
		return $failed;
	foreach (simplexml_load_file($junitFile)->xpath('//testcase[error or failure]') as $testcase)
	{
		$class = (string) $testcase['class'];
		$method = preg_replace('/ with data set .*$/s', '', (string) $testcase['name']);
		$retryable = !$testcase->failure && is_a((string) $testcase->error['type'], BROWSER_FAILURE, true)
			&& isMarkedToRetry($class, $method, (string) $testcase['file']);
		$failed[$class . '::' . $method] = ($failed[$class . '::' . $method] ?? true) && $retryable;
	}
	return $failed;
}

//Whether the test's own class or method carries #[RetryOnBrowserFailure].
function isMarkedToRetry(string $class, string $method, string $file): bool
{
	if (!class_exists($class, false))
	{
		require_once __DIR__ . '/../tests/bootstrap.php';
		require_once $file;
	}
	$reflection = new ReflectionClass($class);
	return $reflection->getAttributes(RetryOnBrowserFailure::class) !== []
		|| ($reflection->hasMethod($method) && $reflection->getMethod($method)->getAttributes(RetryOnBrowserFailure::class) !== []);
}

require __DIR__ . '/../vendor/autoload.php';

$arguments = array_slice($argv, 1);
$junitFile = sys_get_temp_dir() . '/phpunit-retry-' . getmypid() . '.xml';
$exitCode = runPhpunit($arguments, $junitFile);

$failed = $exitCode !== 0 ? failedTests($junitFile) : [];
if ($failed && !in_array(false, $failed, true))
{
	echo PHP_EOL . '[RETRY] The browser alone failed, running these again: ' . implode(', ', array_keys($failed)) . PHP_EOL . PHP_EOL;
	$filter = '/^(' . implode('|', array_map(fn($test) => preg_quote($test, '/'), array_keys($failed))) . ')( with data set .*)?$/';
	$exitCode = runPhpunit([...rerunArguments($arguments), '--filter', $filter], $junitFile);
}

@unlink($junitFile);
exit($exitCode);
