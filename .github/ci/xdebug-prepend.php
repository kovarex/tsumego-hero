<?php
if (!function_exists('xdebug_start_code_coverage')) return;
try {
	@xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	register_shutdown_function(static function (): void {
		$data = @xdebug_get_code_coverage();
		if (empty($data)) return;
		$dir = '/tmp/coverage';
		if (!is_dir($dir)) @mkdir($dir, 0777, true);
		$file = sprintf('%s/webservercoverage-%s-%s.cov', $dir, getmypid(), uniqid('', true));
		@file_put_contents($file, serialize($data));
		@xdebug_stop_code_coverage();
	});
} catch (Throwable $e) {}
