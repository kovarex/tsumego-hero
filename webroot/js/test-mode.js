/**
 * Test Mode Preservation for htmx Requests
 * 
 * When running PHPUnit tests, query parameters (PHPUNIT_TEST, TEST_TOKEN) 
 * identify test mode and which test database to use. This script ensures 
 * htmx AJAX requests preserve these parameters.
 */
document.addEventListener('DOMContentLoaded', function()
{
	document.body.addEventListener('htmx:configRequest', function(evt)
	{
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('PHPUNIT_TEST'))
		{
			evt.detail.parameters['PHPUNIT_TEST'] = '1';
			evt.detail.parameters['TEST_TOKEN'] = urlParams.get('TEST_TOKEN') || '';
		}
	});
});
