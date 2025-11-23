<?php

require("cleanCodeStyleConvertor/RemoveBracersAroundBlocksWithOneCommandFixer.php");
$finder = (new PhpCsFixer\Finder())
	->in(["src", "tests"]);

return (new PhpCsFixer\Config())
	->setRules(
		[
			'@PER-CS' => true,
			'no_unneeded_braces' => false,
			'single_space_around_construct' => false,
			'no_blank_lines_after_class_opening' => true,
			'CleanCodeStyle/remove_bracers_around_blocks_with_one_command' => true,
			'control_structure_continuation_position' => [ 'position' => 'next_line'],
			'control_structure_braces' => false,
			'curly_braces_position' =>
				[
					'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
					'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
					'functions_opening_brace' => 'next_line_unless_newline_at_signature_end'
				],
			'trailing_comma_in_multiline' => false,
			'method_argument_space' => ['on_multiline' => 'ignore']
		])
	->setIndent("\t")
	->setFinder($finder)
	->registerCustomFixers([new RemoveBracersAroundBlocksWithOneCommandFixer()]);
