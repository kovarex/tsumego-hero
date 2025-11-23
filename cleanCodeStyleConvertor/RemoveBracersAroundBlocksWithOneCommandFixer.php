<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Your name <kovarex@email.com>
 * // TODO: For internal testing for now
 * // TODO: integrate properly and add tests
 */
final class RemoveBracersAroundBlocksWithOneCommandFixer extends AbstractFixer {
	public function getDefinition(): FixerDefinition {
		return new FixerDefinition(
			'Remove all braces around blocks with just one command.',
			[
				new CodeSample(
					"<?php if (a == b) { foo(); }",
				),
			],
		);
	}

	public function getName(): string {
		return "CleanCodeStyle/remove_bracers_around_blocks_with_one_command";
	}

	public function isCandidate(Tokens $tokens): bool {
		return true;
	}

	private function processStatementBeforeBraces($tokens, $index) {
		$result = [];
		if ($tokens[$index]->getContent() == 'if'
			|| $tokens[$index]->getContent() == 'while'
			|| $tokens[$index]->getContent() == 'elseif'
			|| $tokens[$index]->getContent() == 'for'
			|| $tokens[$index]->getContent() == 'foreach') {
			$result['else-check-needed'] = $tokens[$index]->getContent() == 'if' || $tokens[$index]->getContent() == 'elseif';
			$ifConditionStartIndex = $tokens->getNextNonWhitespace($index);
			if ($tokens[$ifConditionStartIndex]->getContent() != '(') {
				return null;
			}

			$ifConditionBlockType = Tokens::detectBlockType($tokens[$ifConditionStartIndex]);
			if (!$ifConditionBlockType) {
				return null;
			}

			$ifConditionEndIndex = $tokens->findBlockEnd($ifConditionBlockType['type'], $ifConditionStartIndex);
			$result['end'] = $ifConditionEndIndex;
			return $result;
		}
		if ($tokens[$index]->getContent() == 'else') {
			$result['end'] = $index + 1;
			return $result;
		}
		return null;
	}

	public function processBlockAndReturnCountOfCommandsAndUnclosedIfInfo(Tokens $tokens, $startIndex, $endIndex) {
		$result = [];
		$count = 0;
		$result['whitespace-line-break-count'] = 0;
		$result['if-without-else'] = false;
		$result['comment-count'] = 0;
		for ($index = $startIndex + 1; $index < $endIndex; $index++) {
			if ($tokens[$index]->getContent() == ';') {
				$count++;
			}
			if ($tokens[$index]->isWhitespace()) {
				$result['whitespace-line-break-count'] += substr_count($tokens[$index]->getContent(), "\n");
			}

			if ($tokens[$index]->isComment()) {
				$result['comment-count'] += 1;
			}

			if ($tokens[$index]->getContent() == '{' || $tokens[$index]->getContent() == '(') {
				$bodyBlockType = Tokens::detectBlockType($tokens[$index]);
				if (!$bodyBlockType) {
					continue;
				}

				if ($tokens[$index]->getContent() == '{') {
					$count++;
				}

				$index = $tokens->findBlockEnd($bodyBlockType['type'], $index);

				//
				// when processing statement with block, like
				// if (a)
				// {
				//   foo();
				// }
				//
				// the white-space between closing bracket of if and opening bracket of block is skipped.
				// the effect is, that this newline is not counted towards white-space-line-break-count
				// as this is not top level, and doesn't affect the reasoning, whether this block as a whole
				// should be de-bracketet
				if ($tokens[$index]->getContent() == ')') {
					$nextIndexAfterBracket = $tokens->getNextNonWhitespace($index);
					if ($tokens[$nextIndexAfterBracket]->getContent() == '{') {
						$index = $nextIndexAfterBracket - 1;
					}
				}
				continue;
			} elseif ($tokens[$index]->getContent() == 'if' || $tokens[$index]->getContent() == 'elseif') {
				$result['if-without-else'] = true;
			} elseif ($tokens[$index]->getContent() == 'else') {
				$result['if-without-else'] = false;
			}

			// the if + else blocks count as one command when it comes to us needing to contain in curly braces
			if ($tokens[$index]->getContent() == 'else' || $tokens[$index]->getContent() == 'elseif') {
				$count--;
				$result['whitespace-line-break-count']--;
			}
		}
		$result['command-count'] = $count;
		return $result;
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void {
		/*
		foreach ($tokens as $index => $token) {
			$content = $tokens[$index]->getContent();
			echo "#$index ". str_replace("\n", "<newline>", $content)."\n";
		}*/

		foreach ($tokens as $index => $token) {
			// find if, else, elseif, while and skip their condition blocks (if relevant)
			// the $validStatement contains info:
			// $validStatement['end'] Where should we start looking for the block or command
			// $validStatement['else-check-needed'] it is if or elseif, so else re-conneciton needs to be checked
			$validStatement = $this->processStatementBeforeBraces($tokens, $index);
			if (!$validStatement) {
				continue;
			}

			// now we expect the block to check for removal, everything else than '{' isn't a block
			// so we do nothing
			$openingBraceIndex = $tokens->getNextNonWhitespace($validStatement['end']);
			if ($tokens[$openingBraceIndex]->getContent() != '{') {
				continue;
			}

			$bodyBlockType = Tokens::detectBlockType($tokens[$openingBraceIndex]);
			// it is different kind of "{" opener (string/comment/special) we do nothing
			if (!$bodyBlockType) {
				continue;
			}

			$closingBraceIndex = $tokens->findBlockEnd($bodyBlockType['type'], $openingBraceIndex);

			// now we process the {} block, and get related info to decide whether we can remove it or not
			// block['command-count'] - number of commands the block had
			// block['if-without-else'] - whether the block has last command if or elseif without and else
			// block['whitespace-line-break-count'] how many newlines are in the blocks (top level only)
			// block['comment-count'] how many comments are in the block (top level only)
			$blockInfo = $this->processBlockAndReturnCountOfCommandsAndUnclosedIfInfo($tokens, $openingBraceIndex, $closingBraceIndex);

			// When there is no command, we can't remove the block
			// if (a == b)
			//   {}
			//
			if ($blockInfo['command-count'] == 0) {
				continue;
			}

			// Obviously, when there are more than one command we can't remove the block
			// if (a == b)
			// {
			//   foo();
			//   bar();
			// }
			if ($blockInfo['command-count'] > 1) {
				continue;
			}

			// Brackets can't be removed even when we have just one commands insides when the
			// else would get connected to the inner if instead of the outer one.
			// if (a == b) {
			//   if (a == c)
			//       foo();
			// }
			// else
			//   bar();
			//
			$nextTokenIndex = $tokens->getNextNonWhitespace($closingBraceIndex);
			if (is_numeric($nextTokenIndex)
				&& ($tokens[$nextTokenIndex]->getContent() == 'else' || $tokens[$nextTokenIndex]->getContent() == "elseif")
				&& $blockInfo['if-without-else'] == true) {
				continue;
			}

			// More than 2 newlines might mean a lot of things, but mainly it could be
			// commented command, which we want to keep the brackets around
			// if (a == b) {
			//    foo();
			//    //bar();
			// }
			//
			if ($blockInfo['whitespace-line-break-count'] > 3) {
				continue;
			}

			// commends on the commands itself wouldn't be a problem, if we cared only about fixing:
			// if (a == b) {
			//   foo(); // comment
			// }
			// into
			// if (a == b)
			//   foo(); // comment
			//
			// But the reverse operation of adding pbrackets would change the result to:
			// if (a == b) {
			//   foo();
			// } // comment
			//
			// Which I consider error in the reverse operation logic, and shhould be fixed, but
			// for now, we will just not remove brackets without comments
			if ($blockInfo['comment-count'] > 0) {
				continue;
			}

			// all checks passed, now we are performing the operation

			// removal of the whitespace before the opening bracket we want to remove
			// if (a == b)<here>{
			// so after removal, it won't become trailing space
			// if (a==b)<here>
			if ($tokens[$openingBraceIndex - 1]->isWhitespace()) {
				$tokens->clearAt($openingBraceIndex - 1);
			}

			// removal of the whitespace before the closing bracket we want to remove
			//           if (a == b) {
			//             foo();<here>
			// <and here>}
			/// to not intrudes a newline after the bracket is removed
			if ($tokens[$closingBraceIndex - 1]->isWhitespace()) {
				$tokens->clearAt($closingBraceIndex - 1);
			}

			// everything is in place, now we just remove the brackets
			$tokens->clearTokenAndMergeSurroundingWhitespace($closingBraceIndex);
			$tokens->clearTokenAndMergeSurroundingWhitespace($openingBraceIndex);
		}
	}
}
