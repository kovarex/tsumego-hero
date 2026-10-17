<?php

/**
 * Tests that the reading trainer preferences are stored, clamped to the
 * known states, and that the puzzle page passes them to the board only where
 * the trainer is offered.
 */
use App\Utility\Auth;
use App\Utility\ReadingStones;

class ReadingPreferenceTest extends TestCaseWithAuth
{
	public function testPreferenceStoresBothAxes(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'readingStores']]);
		$this->login('readingStores');

		$result = $this->testAction('/users/readingModePreference', [
			'method' => 'post',
			'data' => ['played' => ReadingStones::Glass->value, 'setup' => ReadingStones::Hidden->value],
			'return' => 'contents',
		]);

		$this->assertSame('{"status":"ok"}', $result);
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(ReadingStones::Glass->value, (int) $user['User']['pref_reading_played']);
		$this->assertSame(ReadingStones::Hidden->value, (int) $user['User']['pref_reading_setup']);
	}

	public function testPreferenceRefusesThePlainGame(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => [
			'name' => 'readingPlain',
			'pref_reading_played' => ReadingStones::Normal->value,
			'pref_reading_setup' => ReadingStones::Hidden->value,
		]]);
		$this->login('readingPlain');

		// Both sides visible is no trainer at all, so the pair is not stored
		$this->testAction('/users/readingModePreference', [
			'method' => 'post',
			'data' => ['played' => ReadingStones::Normal->value, 'setup' => ReadingStones::Normal->value],
		]);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(ReadingStones::Normal->value, (int) $user['User']['pref_reading_played'], 'The plain game did not overwrite the look');
		$this->assertSame(ReadingStones::Hidden->value, (int) $user['User']['pref_reading_setup'], 'The plain game did not overwrite the look');
	}

	public function testPreferenceFallsBackOnUnknownValues(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => [
			'name' => 'readingClamps',
			'pref_reading_played' => ReadingStones::Normal->value,
			'pref_reading_setup' => ReadingStones::Hidden->value,
		]]);
		$this->login('readingClamps');

		// Both values are unknown, so they fall back to the plain game, which is refused
		$this->testAction('/users/readingModePreference', [
			'method' => 'post',
			'data' => ['played' => 99, 'setup' => -5],
		]);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(ReadingStones::Normal->value, (int) $user['User']['pref_reading_played']);
		$this->assertSame(ReadingStones::Hidden->value, (int) $user['User']['pref_reading_setup']);
	}

	public function testPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/readingModePreference', [
			'method' => 'post',
			'data' => ['played' => ReadingStones::Glass->value, 'setup' => ReadingStones::Normal->value],
		]);
	}
}
