<?php

use App\Utility\Auth;

/**
 * Tests that the pref_board_orientation user preference drives the
 * board corner on the puzzle page.
 * "Original" shows the canonical orientation (matching the thumbnail).
 */
class DefaultBoardOrientationTest extends IntegrationTestCase
{
	private static function setOrientationPreference(int $value): void
	{
		Auth::saveUserField('pref_board_orientation', $value);
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

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

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

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertMatchesRegularExpression('/options\.corner = "(top-left|top-right|bottom-left|bottom-right)"/', $this->view);
	}

	public function testBoardOrientationPreferenceStoresValue(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'orientStores']]);
		$this->login('orientStores');

		$this->post('/users/boardOrientationPreference', ['orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL]);

		$this->assertSame('{"status":"ok"}', $this->view);
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

			$this->post('/users/boardOrientationPreference', ['orientation' => $invalid]);

			$user = ClassRegistry::init('User')->findById($context->user['id']);
			$this->assertSame(User::PREF_BOARD_ORIENTATION_RANDOM, (int) $user['User']['pref_board_orientation']);
		}
	}

	public function testBoardOrientationPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->post('/users/boardOrientationPreference', ['orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL]);

		$this->assertResponseCode(401);
	}
}
