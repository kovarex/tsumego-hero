<?php

use App\Model\Table\FriendshipTable;
use App\Utility\PlayerNameSuggestions;
use App\Utility\Util;

//The players offered under the invitation field of a challenge: friends first, then the others, only
//what the list shows, never a deleted account or the asking player.
class PlayerNameSuggestionsTest extends IntegrationTestCase
{
	private function prepare(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'inviter', 'display_name' => 'Kovarex'],
			'other-users' => [
				['name' => 'stranger', 'display_name' => 'Kogo Alpha', 'rating' => 1500],
				['name' => 'friend', 'display_name' => 'Zed Kogo', 'rating' => 2100],
				['name' => 'asked', 'display_name' => 'Kogo Beta'],
				['name' => 'gone', 'display_name' => 'Kogo Gone'],
				['name' => 'other', 'display_name' => 'Shusaku']],
		]);
		$userId = fn(string $name) => (int) ContextPreparator::getUserIdFromName($name);
		FriendshipTable::ask($userId('friend'), $userId('inviter'));
		FriendshipTable::accept($userId('inviter'), $userId('friend'));
		// only asked, so not a friend yet
		FriendshipTable::ask($userId('inviter'), $userId('asked'));
		Util::execute('UPDATE user SET deleted = NOW() WHERE id = ?', [$userId('gone')]);
	}

	/** @return list<array<string, mixed>> */
	private function suggestions(string $typed): array
	{
		$this->get('/challenges/players?query=' . rawurlencode($typed));
		$this->assertResponseOk();
		$this->assertContentType('application/json');
		return json_decode((string) $this->_response->getBody(), true)['players'];
	}

	public function testFriendsComeFirstThenTheOthers(): void
	{
		$this->prepare();

		$players = $this->suggestions('kogo');

		// the friend matches in the middle of the name; the others only from its start
		$this->assertSame(['Zed Kogo', 'Kogo Alpha', 'Kogo Beta'], array_column($players, 'name'));
		$this->assertSame([true, false, false], array_column($players, 'friend'));
	}

	public function testOnlyWhatTheListShowsGoesOut(): void
	{
		$this->prepare();

		$players = $this->suggestions('Kogo A');

		$this->assertSame([['name' => 'Kogo Alpha', 'rank' => '6k', 'friend' => false]], $players);
	}

	public function testNoDeletedAccountsAndNotTheAskingPlayer(): void
	{
		$this->prepare();

		$this->assertNotContains('Kogo Gone', array_column($this->suggestions('Kogo'), 'name'));
		$this->assertSame([], $this->suggestions('Kovarex'));
	}

	public function testTooShortATextAsksForNothing(): void
	{
		$this->prepare();

		$this->assertSame([], $this->suggestions('K'));
		$this->assertSame([], $this->suggestions('  '));
		// the wildcards of LIKE are only characters of a name
		$this->assertSame([], $this->suggestions('%%'));
		$this->assertSame([], $this->suggestions('__'));
	}

	public function testTheListIsLimited(): void
	{
		$this->prepare();
		$userId = (int) ContextPreparator::getUserIdFromName('inviter');
		for ($i = 0; $i < PlayerNameSuggestions::LIMIT + 3; $i++)
			Util::execute("INSERT INTO user (name, display_name, email, password_hash) VALUES (?, ?, 'x@example.com', 'x')", ['many' . $i, 'Many ' . $i]);

		$this->assertCount(PlayerNameSuggestions::LIMIT, PlayerNameSuggestions::for($userId, 'Many'));
	}

	public function testGuestsGetNothing(): void
	{
		$this->prepare();
		$this->logout();

		$this->get('/challenges/players?query=Kogo');

		$this->assertResponseCode(401);
	}
}
