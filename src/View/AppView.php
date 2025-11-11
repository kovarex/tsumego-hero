<?php

App::uses('View', 'View');

/**
 * Application View
 *
 * Extends CakePHP's View class with enhanced error handling for templates
 */
class AppView extends View {
	/**
	 * Sandbox method to evaluate a template / view script with better error handling.
	 *
	 * Wraps the template include with an error handler that converts PHP errors
	 * to exceptions, ensuring that errors in templates show the correct file and
	 * line number instead of pointing to View.php.
	 *
	 * @param string $viewFile Filename of the view
	 * @param array $dataForView Data to include in rendered view.
	 * @return string Rendered output
	 */
	protected function _evaluate($viewFile, $dataForView) {
		$__viewFile = $viewFile;
		extract($dataForView);
		ob_start();

		try {
			include $__viewFile;
		} catch (Throwable $e) {
			ob_end_clean();
			unset($__viewFile);

			// The exception already contains the correct file and line
			// Just enhance the message to make it clearer this is from a template
			$errorMessage = sprintf(
				'%s in [%s, line %d]',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine());

			throw new RuntimeException(
				$errorMessage,
				$e->getCode(),
				$e);
		}

		unset($__viewFile);

		return ob_get_clean();
	}

}
