<?php

App::uses('User', 'Model');
App::uses('SgfParser', 'Utility');

/**
 * Tests that the pref_board_orientation user preference drives the
 * board corner on the puzzle page.
 * "Original" shows the canonical orientation (matching the thumbnail).
 */
class DefaultBoardOrientationTest extends TestCaseWithAuth
{
	private static function setOrientationPreference(int $value): void
	{
		ClassRegistry::init('User')->updateAll(
			['pref_board_orientation' => $value],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_board_orientation'] = $value;
	}

	public function testOriginalUsesTopLeftCorner(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'orientTopLeft'],
			'tsumego' => [
				'sets' => [['name' => 'orientTopLeftSet', 'num' => 1]],
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19]AW[dq][eq][ar][br]AB[co][go][dp][ep](;B[bs]C[+])'],
			],
		]);
		$this->login('orientTopLeft');
		$this->setOrientationPreference(User::PREF_BOARD_ORIENTATION_ORIGINAL);

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		// besogo's loadSgf normalizes the board to top-left internally, so
		// "Original" shows the canonical orientation (matching the thumbnail).
		$this->assertTextContains('options.corner = "top-left"', $this->view);
	}

	public function testRandomSelectsAValidCorner(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'orientRandom'],
			'tsumego' => ['sets' => [['name' => 'orientRandomSet', 'num' => 1]], 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];B[aa])']],
		]);
		$this->login('orientRandom');
		$this->setOrientationPreference(User::PREF_BOARD_ORIENTATION_RANDOM);

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertMatchesRegularExpression('/options\.corner = "(top-left|top-right|bottom-left|bottom-right)"/', $this->view);
	}

	public function testBoardOrientationPreferenceStoresValue(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'orientStores']]);
		$this->login('orientStores');

		$result = $this->testAction('/users/boardOrientationPreference', [
			'method' => 'post',
			'data' => ['orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL],
			'return' => 'contents',
		]);

		$this->assertSame('{"status":"ok"}', $result);
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::PREF_BOARD_ORIENTATION_ORIGINAL, (int) $user['User']['pref_board_orientation']);
	}

	public function testBoardOrientationPreferenceClampsOutOfRangeValue(): void
	{
		foreach ([99, -5, 2] as $invalid)
		{
			Auth::logout();
			$name = 'orientClamps' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name]]);
			$this->login($name);

			$this->testAction('/users/boardOrientationPreference', [
				'method' => 'post',
				'data' => ['orientation' => $invalid],
			]);

			$user = ClassRegistry::init('User')->findById($context->user['id']);
			$this->assertSame(User::PREF_BOARD_ORIENTATION_RANDOM, (int) $user['User']['pref_board_orientation']);
		}
	}

	public function testBoardOrientationPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/boardOrientationPreference', [
			'method' => 'post',
			'data' => ['orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL],
		]);
	}
}
