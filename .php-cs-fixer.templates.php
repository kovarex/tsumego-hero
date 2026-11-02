<?php

//The templates mix PHP into markup, so they get the rules of the code with the exceptions that
//only fit code: a PHP block inside markup keeps the indentation of the markup around it, and a
//comment that ends at the closing PHP tag keeps its space before it
$config = require __DIR__ . "/.php-cs-fixer.dist.php";

return $config
	->setCacheFile(__DIR__ . "/.php-cs-fixer.templates.cache")
	->setRules(array_merge($config->getRules(), [
		'statement_indentation' => false,
		'no_trailing_whitespace_in_comment' => false
	]))
	->setFinder((new PhpCsFixer\Finder())->in([__DIR__ . "/templates"]));
