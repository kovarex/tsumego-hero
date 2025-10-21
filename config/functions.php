<?php

if (!function_exists('debug')) {
	/**
	 * Prints out debug information about given variable and returns the
	 * variable that was passed.
	 *
	 * Only runs if debug mode is enabled.
	 *
	 * @param mixed $var Variable to show debug information for.
	 * @param bool|null $showHtml If set to true, the method prints the debug data in a browser-friendly way.
	 * @param bool $showFrom If set to true, the method prints from where the function was called.
	 * @return mixed The same $var that was passed
	 */
	function debug($var, $showHtml = null, $showFrom = true) {
		if (!Configure::read('debug')) {
			return $var;
		}

		$location = [];
		if ($showFrom) {
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
			if (isset($backtrace[0]['line']) && isset($backtrace[0]['file'])) {
				$location = [
					'line' => $backtrace[0]['line'],
					'file' => $backtrace[0]['file'],
				];
			}
		}

		$template = <<<HTML
<div class="cake-debug-output" style="background: #ffffe0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
%s
<pre style="background: white; padding: 10px; overflow: auto;">%s</pre>
</div>
HTML;

		$locationInfo = '';
		if (!empty($location['file'])) {
			$locationInfo = sprintf(
				'<strong>%s</strong> (line <strong>%s</strong>)',
				trimPath($location['file']),
				$location['line']
			);
		}

		if (PHP_SAPI === 'cli' || $showHtml === false) {
			if (!empty($locationInfo)) {
				echo strip_tags($locationInfo) . "\n";
			}
			print_r($var);
			echo "\n";
		} else {
			printf($template, $locationInfo, htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8'));
		}

		return $var;
	}
}

if (!function_exists('stackTrace')) {
	/**
	 * Outputs a stack trace based on the supplied options.
	 *
	 * ### Options
	 *
	 * - `depth` - The number of stack frames to return. Defaults to 999
	 * - `args` - Should arguments for functions be shown? If true, the arguments for each method call
	 *   will be displayed.
	 * - `start` - The stack frame to start generating a trace from. Defaults to 1
	 *
	 * @param array $options Format for outputting stack trace
	 * @return void
	 */
	function stackTrace($options = []) {
		if (!Configure::read('debug')) {
			return;
		}

		$defaults = ['start' => 1, 'depth' => 999, 'args' => false];
		$options = array_merge($defaults, $options);

		$backtrace = debug_backtrace($options['args'] ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS);
		$backtrace = array_slice($backtrace, $options['start'], $options['depth']);

		foreach ($backtrace as $i => $frame) {
			$frame += ['file' => '[internal]', 'line' => '??', 'class' => '', 'function' => ''];

			$reference = $frame['function'];
			if (!empty($frame['class'])) {
				$type = isset($frame['type']) ? $frame['type'] : '::';
				$reference = $frame['class'] . $type . $frame['function'];
			}

			$file = trimPath($frame['file']);
			echo sprintf("#%d %s - %s, line %d\n", $i, $reference, $file, $frame['line']);
		}
	}
}

if (!function_exists('dd')) {
	/**
	 * Prints out debug information about given variable and dies.
	 *
	 * Only runs if debug mode is enabled.
	 * It will otherwise just continue code execution and ignore this function.
	 *
	 * @param mixed $var Variable to show debug information for.
	 * @param bool|null $showHtml If set to true, the method prints the debug data in a browser-friendly way.
	 * @return void
	 */
	function dd($var, $showHtml = null) {
		if (!Configure::read('debug')) {
			return;
		}

		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$location = [];
		if (isset($backtrace[0]['line']) && isset($backtrace[0]['file'])) {
			$location = [
				'line' => $backtrace[0]['line'],
				'file' => $backtrace[0]['file'],
			];
		}

		$template = <<<HTML
<div class="cake-debug-output" style="background: #ffcccc; padding: 10px; margin: 10px 0; border: 2px solid #cc0000;">
<h3 style="margin: 0 0 10px 0; color: #cc0000;">Debug &amp; Die</h3>
%s
<pre style="background: white; padding: 10px; overflow: auto;">%s</pre>
</div>
HTML;

		$locationInfo = '';
		if (!empty($location['file'])) {
			$locationInfo = sprintf(
				'<strong>%s</strong> (line <strong>%s</strong>)',
				trimPath($location['file']),
				$location['line']
			);
		}

		if (PHP_SAPI === 'cli' || $showHtml === false) {
			echo "\n=== DEBUG & DIE ===\n";
			if (!empty($locationInfo)) {
				echo strip_tags($locationInfo) . "\n";
			}
			print_r($var);
			echo "\n";
		} else {
			printf($template, $locationInfo, htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8'));
		}

		die(1);
	}
}

if (!function_exists('exportVar')) {
	/**
	 * Converts a variable to a string for debug output.
	 *
	 * @param mixed $var Variable to convert.
	 * @param int $maxDepth The depth to output to. Defaults to 3.
	 * @return string Variable as a formatted string
	 */
	function exportVar($var, $maxDepth = 3) {
		return _exportVarRecursive($var, $maxDepth, 0);
	}
}

if (!function_exists('_exportVarRecursive')) {
	/**
	 * Protected export function used to keep track of indentation and recursion.
	 *
	 * @param mixed $var The variable to dump.
	 * @param int $maxDepth Maximum depth.
	 * @param int $depth Current depth.
	 * @return string
	 */
	function _exportVarRecursive($var, $maxDepth, $depth) {
		$type = gettype($var);

		switch ($type) {
			case 'boolean':
				return $var ? 'true' : 'false';
			case 'integer':
			case 'double':
				return (string)$var;
			case 'string':
				return "'" . $var . "'";
			case 'NULL':
				return 'null';
			case 'array':
				if ($depth >= $maxDepth) {
					return '[maximum depth reached]';
				}
				if (empty($var)) {
					return '[]';
				}
				$out = "[\n";
				$indent = str_repeat("\t", $depth + 1);
				foreach ($var as $key => $value) {
					$out .= $indent . _exportVarRecursive($key, $maxDepth, $depth + 1);
					$out .= ' => ';
					$out .= _exportVarRecursive($value, $maxDepth, $depth + 1);
					$out .= ",\n";
				}
				$out .= str_repeat("\t", $depth) . ']';
				return $out;
			case 'object':
				if ($depth >= $maxDepth) {
					return 'object(' . get_class($var) . ') [maximum depth reached]';
				}
				$objectVars = get_object_vars($var);
				$out = 'object(' . get_class($var) . ") {\n";
				$indent = str_repeat("\t", $depth + 1);
				foreach ($objectVars as $key => $value) {
					$out .= $indent . "'" . $key . "' => ";
					$out .= _exportVarRecursive($value, $maxDepth, $depth + 1);
					$out .= "\n";
				}
				$out .= str_repeat("\t", $depth) . '}';
				return $out;
			case 'resource':
				return 'resource';
			default:
				return '(unknown)';
		}
	}
}

if (!function_exists('dump')) {
	/**
	 * Recursively formats and outputs the contents of the supplied variable.
	 *
	 * @param mixed $var The variable to dump.
	 * @param int $maxDepth The depth to output to. Defaults to 3.
	 * @return void
	 */
	function dump($var, $maxDepth = 3) {
		echo exportVar($var, $maxDepth) . "\n";
	}
}

if (!function_exists('trimPath')) {
	/**
	 * Shortens file paths by replacing the application root path with 'ROOT'.
	 *
	 * @param string $path Path to shorten.
	 * @return string Normalized path
	 */
	function trimPath($path) {
		if (defined('ROOT') && strpos($path, ROOT) === 0) {
			return str_replace(ROOT, 'ROOT', $path);
		}
		if (defined('APP') && strpos($path, APP) === 0) {
			return str_replace(APP, 'APP/', $path);
		}
		if (defined('CAKE_CORE_INCLUDE_PATH') && strpos($path, CAKE_CORE_INCLUDE_PATH) === 0) {
			return str_replace(CAKE_CORE_INCLUDE_PATH, 'CORE', $path);
		}

		return $path;
	}
}

if (!function_exists('excerpt')) {
	/**
	 * Grabs an excerpt from a file and highlights a given line of code.
	 *
	 * Usage:
	 *
	 * ```
	 * excerpt('/path/to/file', 100, 4);
	 * ```
	 *
	 * The above would return an array of 8 items. The 4th item would be the provided line,
	 * and would be wrapped in `<span class="code-highlight"></span>`.
	 *
	 * @param string $file Absolute path to a PHP file.
	 * @param int $line Line number to highlight.
	 * @param int $context Number of lines of context to extract above and below $line.
	 * @return array Set of lines highlighted
	 */
	function excerpt($file, $line, $context = 2) {
		$lines = [];
		if (!file_exists($file)) {
			return [];
		}
		$data = file_get_contents($file);
		if (!$data) {
			return $lines;
		}
		if (strpos($data, "\n") !== false) {
			$data = explode("\n", $data);
		}
		$line--;
		if (!isset($data[$line])) {
			return $lines;
		}
		for ($i = $line - $context; $i < $line + $context + 1; $i++) {
			if (!isset($data[$i])) {
				continue;
			}
			$string = str_replace(["\r\n", "\n"], '', highlight_string($data[$i], true));
			if ($i === $line) {
				$lines[] = '<span class="code-highlight">' . $string . '</span>';
			} else {
				$lines[] = $string;
			}
		}

		return $lines;
	}
}
