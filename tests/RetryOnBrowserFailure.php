<?php

//A test, or every test of the class it marks, that scripts/phpunit-retry.php runs once more when
//only the browser failed it: a WebDriverException of Selenium. A failed assertion is not retried.
//It counts where it is written, on the test's own class or method, not on a parent class.
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RetryOnBrowserFailure {}
