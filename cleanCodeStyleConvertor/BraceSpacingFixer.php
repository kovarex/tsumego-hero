<?php

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Unified fixer for brace spacing issues
 * Handles both closing paren before brace and spaces around else keyword
 */
final class BraceSpacingFixer extends AbstractFixer
{
	public function getDefinition(): FixerDefinition
	{
		return new FixerDefinition(
			'Ensures proper spacing around braces in control structures.',
			[
				new CodeSample("<?php if (true){ echo 'test'; } }else{ echo 'other'; }"),
			],
		);
	}

	public function getName(): string
	{
		return "CleanCodeStyle/brace_spacing";
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return true;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		foreach ($tokens as $index => $token) {
			// Handle: anything ) followed by opening brace
			if ($token->equals(')')) {
				$nextIndex = $tokens->getNextMeaningfulToken($index);
				if ($nextIndex !== null && $tokens[$nextIndex]->equals('{')) {
					$this->ensureSpaceBetween($tokens, $index, $nextIndex);
				}
			}

			// Handle: any keyword that can be followed directly by brace
			if ($this->isKeywordBeforeBrace($token)) {
				$nextIndex = $tokens->getNextMeaningfulToken($index);
				if ($nextIndex !== null && $tokens[$nextIndex]->equals('{')) {
					$this->ensureSpaceBetween($tokens, $index, $nextIndex);
				}
			}

			// Handle: closing brace followed by keyword that needs space before it
			if ($token->equals('}')) {
				$nextIndex = $tokens->getNextMeaningfulToken($index);
				if ($nextIndex !== null && $this->isKeywordAfterBrace($tokens[$nextIndex])) {
					$this->ensureSpaceBetween($tokens, $index, $nextIndex);
				}
			}
		}
	}

	/**
	 * Checks if a token is a keyword that can be directly followed by an opening brace
	 * Includes: control structures, function declarations, class declarations, etc.
	 */
	private function isKeywordBeforeBrace(Token $token): bool
	{
		return $token->isGivenKind([
			T_IF,
			T_ELSEIF,
			T_ELSE,
			T_FOR,
			T_FOREACH,
			T_WHILE,
			T_DO,
			T_SWITCH,
			T_TRY,
			T_CATCH,
			T_FINALLY,
			T_FUNCTION,
			T_CLASS,
			T_INTERFACE,
			T_TRAIT,
			T_NAMESPACE,
		]);
	}

	/**
	 * Checks if a token is a keyword that should have space before it when following a brace
	 * Primarily: else, elseif, catch, finally, and while (in do...while)
	 */
	private function isKeywordAfterBrace(Token $token): bool
	{
		return $token->isGivenKind([
			T_ELSE,
			T_ELSEIF,
			T_CATCH,
			T_FINALLY,
			T_WHILE,
		]);
	}

	/**
	 * Ensures there is at least one space character between two token positions
	 */
	private function ensureSpaceBetween(Tokens $tokens, int $startIndex, int $endIndex): void
	{
		// Check if there is already a proper space
		$hasProperSpace = false;
		for ($i = $startIndex + 1; $i < $endIndex; $i++) {
			if ($tokens[$i]->isWhitespace() && strpos($tokens[$i]->getContent(), ' ') !== false) {
				$hasProperSpace = true;
				break;
			}
		}

		if (!$hasProperSpace) {
			// Insert space right after start token
			$tokens->insertAt($startIndex + 1, new Token([T_WHITESPACE, ' ']));
		}
	}
}
