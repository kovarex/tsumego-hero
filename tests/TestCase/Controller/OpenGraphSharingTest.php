<?php

use App\Utility\Avatar;

/**
 * End-to-end tests for Open Graph social sharing tags.
 */
class OpenGraphSharingTest extends TestCaseWithAuth
{
	public function testPuzzlePageShowsOpenGraphTags(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 42]]);
		$tsumegoId = $context->tsumegos[0]['id'];
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];

		$body = $this->testAction('tsumegos/play/' . $tsumegoId, ['return' => 'contents']);

		$this->assertStringContainsString('<meta property="og:title" content="test set 42/1">', $body);
		$this->assertStringContainsString('property="og:image" content="', $body);
		$this->assertStringContainsString('/tsumego-image/' . $setConnectionId, $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/' . $setConnectionId, $body);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $body);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $body);
	}

	public function testSetPageShowsOpenGraphTagsWithCoverImage(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'test set', 'num' => 1]]]]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$setModel = ClassRegistry::init('Set');
		$setModel->id = $setId;
		$setModel->saveField('image', 'cover.jpg');
		$setModel->saveField('description', 'Basic life and death problems');

		$body = $this->testAction('sets/view/' . $setId, ['return' => 'contents']);

		$this->assertStringContainsString('<meta property="og:title" content="test set">', $body);
		$this->assertStringContainsString('<meta property="og:description" content="Basic life and death problems">', $body);
		$this->assertStringContainsString('property="og:image" content="', $body);
		$this->assertStringContainsString('/img/cover.jpg', $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/sets/view/' . $setId, $body);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $body);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary">', $body);
	}

	public function testHomePageShowsDefaultOpenGraphTags(): void
	{
		$body = $this->testAction('sites/index', ['return' => 'contents']);

		$this->assertStringContainsString('<meta property="og:title" content="Tsumego Hero">', $body);
		$this->assertStringContainsString('<meta property="og:description" content="Interactive tsumego database. Solve go problems, get stronger, level up, have fun.">', $body);
		$this->assertStringContainsString('property="og:image" content="', $body);
		$this->assertStringContainsString('/img/Tsumego-Hero-Logo.png', $body);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $body);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary">', $body);
	}

	public function testUserProfileShowsOpenGraphTags(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'alice']]]);
		$userId = $context->otherUsers[0]['id'];

		$body = $this->testAction('users/view/' . $userId, ['return' => 'contents']);

		$this->assertStringContainsString('<meta property="og:title" content="Profile of DN_alice">', $body);
		$this->assertStringContainsString('<meta property="og:type" content="profile">', $body);
		$this->assertStringContainsString('<meta property="profile:username" content="DN_alice">', $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/users/view/' . $userId, $body);
	}

	/**
	 * A profile is shared with the picture of the player, which is the board the
	 * site draws for the name. A share preview takes a png and not an svg.
	 */
	public function testUserProfileSharesTheBoardAvatar(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'alice']]]);
		$userId = $context->otherUsers[0]['id'];

		$body = $this->testAction('users/view/' . $userId, ['return' => 'contents']);

		$this->assertStringContainsString('/board/' . Avatar::key('DN_alice') . '.png"', $body);
		$this->assertStringContainsString('<meta property="og:image:type" content="image/png">', $body);
		$this->assertStringContainsString('<meta property="og:image:width" content="' . Avatar::PNG_SIZE . '">', $body);
		$this->assertStringContainsString('<meta property="og:image:height" content="' . Avatar::PNG_SIZE . '">', $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}

	/**
	 * A picture of another host is already a picture, so it is shared as it is.
	 */
	public function testUserProfileSharesThePickedGravatar(): void
	{
		$context = new ContextPreparator(['other-users' => [[
			'name' => 'alice',
			'email' => 'alice@example.com',
			'pref_avatar' => User::AVATAR_GRAVATAR,
		]]]);

		$body = $this->testAction('users/view/' . $context->otherUsers[0]['id'], ['return' => 'contents']);

		$this->assertStringContainsString('og:image" content="https://www.gravatar.com/avatar/' . User::gravatarHash('alice@example.com'), $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}

	public function testUserProfileSharesThePickedGooglePicture(): void
	{
		$context = new ContextPreparator(['other-users' => [[
			'name' => 'alice',
			'email' => 'alice@example.com',
			'external_id' => '1234567890',
			'pref_avatar' => User::AVATAR_GOOGLE,
			'picture' => 'https://lh3.googleusercontent.com/a/abc=s96-c',
		]]]);

		$body = $this->testAction('users/view/' . $context->otherUsers[0]['id'], ['return' => 'contents']);

		$this->assertStringContainsString('og:image" content="https://lh3.googleusercontent.com/a/abc=s' . Avatar::PNG_SIZE . '"', $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}
}
