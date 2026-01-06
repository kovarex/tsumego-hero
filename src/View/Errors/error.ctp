<?php
/**
 * Generic Error Page
 * Handles 404, 500, and other HTTP errors
 * Uses the default site layout with full navigation
 */

// Determine error details based on code
$code = $this->response->statusCode();
$errorTitle = 'Error';
$errorColor = '#666';
$errorMessage = 'An error occurred while processing your request.';
$errorDetails = 'Please try again later or contact support if the problem persists.';

switch ($code)
{
	case 404:
		$errorTitle = 'Page Not Found';
		$errorColor = '#666';
		$errorMessage = 'The requested page <strong style="color: var(--primary-color, #4CAF50);">' . h($url ?? $_SERVER['REQUEST_URI'] ?? '/') . '</strong> could not be found on this server.';
		$errorDetails = 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
		break;
	case 500:
		$errorTitle = 'Internal Server Error';
		$errorColor = '#d32f2f';
		$errorMessage = 'An internal error occurred while processing your request.';
		$errorDetails = 'The server encountered an unexpected condition that prevented it from fulfilling the request. Our team has been notified.';
		break;
	case 403:
		$errorTitle = 'Forbidden';
		$errorColor = '#f57c00';
		$errorMessage = 'You do not have permission to access this resource.';
		$errorDetails = 'Please contact an administrator if you believe you should have access.';
		break;
	default:
		$errorTitle = 'Error ' . $code;
		$errorColor = '#666';
		break;
}
?>
<div style="position: relative; max-width: 800px; margin: 100px auto; padding: 40px; text-align: center;">
	<div style="position: relative; z-index: 1;">
		<h1 style="font-size: 120px; color: <?php echo $errorColor; ?>; opacity: 0.15; margin: 0; line-height: 1; position: absolute; top: -20px; left: 50%; transform: translateX(-50%);"><?php echo $code; ?></h1>
		<h2 style="font-size: 36px; color: var(--text-color, #333); margin: 60px 0 20px 0; position: relative; z-index: 2;"><?php echo $errorTitle; ?></h2>
	</div>
	<p style="font-size: 18px; color: var(--text-color-secondary, #666); line-height: 1.6; position: relative; z-index: 2;">
		<?php echo $errorMessage; ?>
	</p>
	<p style="font-size: 16px; color: var(--text-color-secondary, #666); line-height: 1.6; margin-top: 20px; position: relative; z-index: 2;">
		<?php echo $errorDetails; ?>
	</p>
	
	<?php if (Configure::read('debug') > 0 && isset($error)): ?>
		<div style="margin-top: 60px; padding: 30px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; text-align: left; position: relative; z-index: 2;">
			<h3 style="color: var(--text-color, #333); margin-top: 0; margin-bottom: 20px;">Debug Information</h3>
			
			<p style="margin: 15px 0; padding: 10px; background: rgba(0, 0, 0, 0.05); border-radius: 4px;">
				<strong style="color: var(--text-color, #333);">Error Type:</strong><br>
				<code style="color: #c7254e; background: rgba(0, 0, 0, 0.04); padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 5px;"><?php echo h(get_class($error)); ?></code>
			</p>
			
			<p style="margin: 15px 0; padding: 10px; background: rgba(0, 0, 0, 0.05); border-radius: 4px;">
				<strong style="color: var(--text-color, #333);">Message:</strong><br>
				<code style="color: #c7254e; background: rgba(0, 0, 0, 0.04); padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 5px;"><?php echo h($error->getMessage()); ?></code>
			</p>
			
			<?php if (method_exists($error, 'getFile') && method_exists($error, 'getLine')): ?>
				<p style="margin: 15px 0; padding: 10px; background: rgba(0, 0, 0, 0.05); border-radius: 4px;">
					<strong style="color: var(--text-color, #333);">Location:</strong><br>
					<code style="color: #c7254e; background: rgba(0, 0, 0, 0.04); padding: 4px 8px; border-radius: 3px; display: inline-block; margin-top: 5px;"><?php echo h($error->getFile()); ?> (line <?php echo h($error->getLine()); ?>)</code>
				</p>
			<?php endif; ?>
			
			<?php if (method_exists($error, 'getTrace')): ?>
				<details style="margin-top: 20px;">
					<summary style="cursor: pointer; font-weight: bold; color: var(--primary-color, #4CAF50); padding: 10px; background: rgba(0, 0, 0, 0.03); border-radius: 4px;">▶ Stack Trace (click to expand)</summary>
					<pre style="overflow: auto; max-height: 500px; background: rgba(0, 0, 0, 0.8); color: #f8f8f2; padding: 20px; border-radius: 5px; font-size: 13px; line-height: 1.6; margin-top: 10px; font-family: 'Consolas', 'Monaco', monospace;"><?php echo h(print_r($error->getTrace(), true)); ?></pre>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
