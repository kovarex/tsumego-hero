<?php

// Separate config for CakePHP template files (.ctp)

require_once("cleanCodeStyleConvertor/BraceSpacingFixer.php");

$finder = (new PhpCsFixer\Finder())
	->in([__DIR__ . "/src/View"])
	->name('*.ctp')
    ->notName('*.php');  // Exclude regular PHP files

return (new PhpCsFixer\Config())
	->setRules(
		[
			'binary_operator_spaces' => ['default' => 'single_space'],
			'concat_space' => ['spacing' => 'one'],
			'ternary_operator_spaces' => true,
			'function_declaration' => ['closure_function_spacing' => 'one'],
			'single_space_around_construct' => true,
			'control_structure_continuation_position' => false, 
			'no_spaces_inside_parenthesis' => true,
			'no_trailing_whitespace' => true,
			'no_whitespace_in_blank_line' => true,
			'spaces_inside_parentheses' => ['space' => 'none'],
			'CleanCodeStyle/brace_spacing' => true, 
			'no_closing_tag' => false,
			'blank_line_after_opening_tag' => false,
			'single_blank_line_at_eof' => true,
			'control_structure_braces' => false,
			'statement_indentation' => false,
		])
	->setIndent("\t")
	->setFinder($finder)
	->registerCustomFixers([
		new BraceSpacingFixer(),
	]);

