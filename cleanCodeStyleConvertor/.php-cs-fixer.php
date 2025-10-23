<?php

require("cleanCodeStyleConvertor/RemoveBracersAroundBlocksWithOneCommandFixer.php");
$finder = (new PhpCsFixer\Finder())
    ->in(["src", "tests"])
;

return (new PhpCsFixer\Config())
  ->setRules(
    [
      '@PER-CS' => true,
      'no_blank_lines_after_class_opening' => true,
      'CleanCodeStyle/remove_bracers_around_blocks_with_one_command' => true,
      'control_structure_continuation_position' => [ 'position' => 'next_line'],
      'curly_braces_position' =>
      [
       'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
       'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
       'functions_opening_brace' => 'next_line_unless_newline_at_signature_end'
      ]
    ])
  ->setIndent("\t")
  ->setFinder($finder)
  ->registerCustomFixers([new RemoveBracersAroundBlocksWithOneCommandFixer()]);
