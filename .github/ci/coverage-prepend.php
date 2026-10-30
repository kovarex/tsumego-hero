<?php

//The code coverage of each request the CI web server answers (master only, see ci.yml), saved as a
//php-code-coverage object in /tmp/coverage next to PHPUnit's phpunit.cov. phpcov merges them all.
if (!extension_loaded('pcov'))
	return;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

(static function (): void {
	$sourceDir = dirname(__DIR__, 2) . '/src';
	$filter = new SebastianBergmann\CodeCoverage\Filter();
	$filter->includeFiles((new SebastianBergmann\FileIterator\Facade())->getFilesAsArray($sourceDir, '.php'));

	// PCOV records only src/ (pcov.directory), so a request for a picture or a script records nothing.
	$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage((new SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter), $filter);
	$coverage->cacheStaticAnalysis(sys_get_temp_dir() . '/coverage-static-analysis');
	$coverage->start((string) ($_SERVER['REQUEST_URI'] ?? 'request'));

	register_shutdown_function(static function () use ($coverage): void {
		$coverage->stop();
		if ($coverage->getData(true)->coveredFiles() === [])
			return;
		$file = sprintf('/tmp/coverage/webserver-%s.cov', uniqid('', true));
		(new SebastianBergmann\CodeCoverage\Report\PHP())->process($coverage, $file);
	});
})();
