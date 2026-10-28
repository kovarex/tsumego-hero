<?php
//Collects the page's JavaScript errors for the browser tests (tests/Browser.php), which fail on any of them.
//Included first in the head in debug mode, so errors while loading are caught too.
?>
<script>
	(function ()
	{
		window.__jsErrors = [];
		window.__consoleErrors = [];

		//Uncaught errors, including resources that failed to load (target)
		window.addEventListener('error', function (event)
		{
			window.__jsErrors.push({
				raw: JSON.stringify({
					message: event.message,
					source: event.filename,
					line: event.lineno,
					column: event.colno,
					type: event.type,
					target: event.target && event.target.tagName ? event.target.tagName : null,
					outerHTML: event.target && event.target.outerHTML ? event.target.outerHTML.slice(0, 200) : null,
					stack: event.error ? event.error.stack : null
				}),
				time: Date.now()
			});
		}, true);

		window.addEventListener('unhandledrejection', function (event)
		{
			var reason = event.reason || {};
			window.__jsErrors.push({
				message: reason.message || String(reason),
				source: null,
				line: null,
				column: null,
				stack: reason.stack || null,
				time: Date.now()
			});
		}, true);

		var originalConsoleError = console.error;
		console.error = function ()
		{
			try
			{
				window.__consoleErrors.push({args: Array.prototype.slice.call(arguments), time: Date.now()});
			}
			catch (exception) {}
			originalConsoleError.apply(console, arguments);
		};
	})();
</script>
