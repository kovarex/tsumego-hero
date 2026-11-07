<?php
//Generic Error Page
//Handles 404, 500, and other HTTP errors
//Uses the default site layout with full navigation
use App\Utility\Util;

// Determine error details based on code
$code = $this->getResponse()->getStatusCode();
$errorTitle = 'Error';
$errorTone = 'neutral';
$errorMessage = 'An error occurred while processing your request.';
$errorDetails = 'Please try again later or contact support if the problem persists.';

switch ($code)
{
	case 404:
		$errorTitle = 'Page Not Found';
		$errorTone = 'neutral';
		$errorMessage = 'The requested page <strong class="error-page__address">' . h($url ?? Util::requestUri()) . '</strong> could not be found on this server.';
		$errorDetails = 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
		break;
	case 500:
		$errorTitle = 'Internal Server Error';
		$errorTone = 'danger';
		$errorMessage = 'An internal error occurred while processing your request.';
		$errorDetails = 'The server encountered an unexpected condition that prevented it from fulfilling the request. Our team has been notified.';
		break;
	case 400:
		$errorTitle = 'Bad Request';
		$errorTone = 'warning';
		$errorMessage = 'The request could not be understood or was missing required parameters.';
		$errorDetails = 'Please check the URL and try again.';
		break;
	case 403:
		$errorTitle = 'Forbidden';
		$errorTone = 'warning';
		$errorMessage = 'You do not have permission to access this resource.';
		$errorDetails = 'Please contact an administrator if you believe you should have access.';
		break;
	case 405:
		$errorTitle = 'Method Not Allowed';
		$errorTone = 'warning';
		$errorMessage = 'The request method is not allowed for this resource.';
		$errorDetails = 'Please use a different request method or check the URL.';
		break;
	default:
		$errorTitle = 'Error ' . $code;
		$errorTone = 'neutral';
		break;
}

$this->set('title_for_layout', $errorTitle);
?>
<?php //id and data-status let the browser tests tell an error page from a page (tests/Browser.php) ?>
<div id="error-page" class="error-page" data-status="<?php echo $code; ?>">
	<div class="error-page__heading">
		<h1 class="error-page__code error-page__code--<?php echo $errorTone; ?>"><?php echo $code; ?></h1>
		<h2 class="error-page__title"><?php echo $errorTitle; ?></h2>
	</div>
	<p class="error-page__message">
		<?php echo $errorMessage; ?>
	</p>
	<p class="error-page__details">
		<?php echo $errorDetails; ?>
	</p>
	
	<?php if (isset($error)): ?>
		<?php
		//the paths from the root of the site: shorter to report, and they don't tell how the server is laid out
		$relativePath = fn(string $file): string => str_starts_with($file, ROOT . DS) ? substr($file, strlen(ROOT . DS)) : $file;
		?>
		<div class="error-page__debug">
			<h3 class="error-page__debug-title">Debug Information</h3>
			
			<p class="error-page__fact">
				<strong class="error-page__fact-name">Error Type:</strong><br>
				<code class="error-page__fact-value"><?php echo h(get_class($error)); ?></code>
			</p>
			
			<p class="error-page__fact">
				<strong class="error-page__fact-name">Message:</strong><br>
				<code class="error-page__fact-value"><?php echo h($error->getMessage()); ?></code>
			</p>
			
			<?php if (method_exists($error, 'getFile') && method_exists($error, 'getLine')): ?>
				<p class="error-page__fact">
					<strong class="error-page__fact-name">Location:</strong><br>
				<code class="error-page__fact-value"><?php echo h($relativePath($error->getFile())); ?> (line <?php echo h($error->getLine()); ?>)</code>
				</p>
			<?php endif; ?>
			
			<?php if (method_exists($error, 'getTrace')): ?>
				<div class="error-page__trace">
					<strong class="error-page__trace-title">Stack Trace</strong>
					<pre class="error-page__trace-lines"><?php
						// Build trace with #-1 throw location
						$traceLines = [];
						$traceLines[] = '#-1 ' . h($relativePath($error->getFile())) . '(' . h($error->getLine()) . ')';
						foreach ($error->getTrace() as $i => $frame)
						{
							$line = '#' . $i . ' ';
							$line .= (isset($frame['file']) ? $relativePath($frame['file']) : '[internal function]') . '(' . ($frame['line'] ?? '') . '): ';
							if (!empty($frame['class']))
								$line .= $frame['class'] . ($frame['type'] ?? '->');
							$line .= $frame['function'] . '(';
							// the types of the arguments only: their values can be passwords, tokens, e-mails or SQL
							$line .= implode(', ', array_map(get_debug_type(...), $frame['args'] ?? []));
							$line .= ')';
							$traceLines[] = h($line);
						}
						$traceLines[] = '#' . count($error->getTrace()) . ' {main}';
						echo implode("\n", $traceLines);
					?></pre>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
