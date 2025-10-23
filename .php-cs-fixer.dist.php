<?php

$finder = (new PhpCsFixer\Finder())
		->in(["src", "tests"])
;

return (new PhpCsFixer\Config())->setRules(
	[
		'@PER-CS' => true,
		'no_blank_lines_after_class_opening' => true,
		'curly_braces_position' =>
		[
		 'control_structures_opening_brace' => 'same_line',
		 'classes_opening_brace' => 'same_line',
		 'functions_opening_brace' => 'same_line'
		]
	])
		->setIndent("\t")
		->setFinder($finder);
