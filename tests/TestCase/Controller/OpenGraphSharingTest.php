<?php

use App\Utility\Avatar;
use App\Utility\Constants;

//End-to-end tests for Open Graph social sharing tags.
class OpenGraphSharingTest extends IntegrationTestCase
{
	public function testPuzzlePageShowsOpenGraphTags(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 42]]);
		$tsumegoId = $context->tsumegos[0]['id'];
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];

		$this->get('tsumegos/play/' . $tsumegoId);
		$body = $this->view;

		$this->assertStringContainsString('<meta property="og:title" content="test set 42/1">', $body);
		$this->assertStringContainsString('property="og:image" content="', $body);
		$this->assertStringContainsString('/tsumego-image/' . $setConnectionId, $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/' . $setConnectionId, $body);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $body);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $body);
	}

	//The title counts the problems of the set, and a problem in the trash is not in it anymore.
	public function testPuzzlePageTitleCountsTheProblemsOfTheSet(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'test set', 'num' => 1]]],
			['sets' => [['name' => 'test set', 'num' => 2]]],
			['sets' => [['name' => 'test set', 'num' => 3]], 'deleted' => date('Y-m-d H:i:s')]]]);

		$this->get('tsumegos/play/' . $context->tsumegos[0]['id']);

		$this->assertStringContainsString('<meta property="og:title" content="test set 1/2">', $this->view);
	}

	public function testSetPageShowsOpenGraphTagsWithCoverImage(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'test set', 'num' => 1]]]]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$this->fetchTable('Set')->updateAll(['image' => 'cover.jpg', 'description' => 'Basic life and death problems'], ['id' => $setId]);

		$this->get('sets/view/' . $setId);
		$body = $this->view;

		$this->assertStringContainsString('<meta property="og:title" content="test set">', $body);
		$this->assertStringContainsString('<meta property="og:description" content="Basic life and death problems">', $body);
		$this->assertStringContainsString('property="og:image" content="', $body);
		$this->assertStringContainsString('/img/cover.jpg', $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/sets/' . $setId . '"', $body);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $body);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary">', $body);
	}

	public function testSharedCollectionNamesItsOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob', 'display_name' => 'Bobby']],
		]);
		$setTable = $this->fetchTable('Set');
		$createdSet = $setTable->saveOrFail($setTable->newEntity([
			'user_id' => $context->otherUsers[0]['id'],
			'title' => 'Bobby Collection',
			'public' => 1,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]));

		$this->get('sets/' . $createdSet->id);
		$body = $this->view;

		$this->assertStringContainsString('<meta property="og:title" content="Bobby Collection by Bobby">', $body);
		$this->assertMatchesRegularExpression(
			'#<meta property="og:description" content="\d+ go problems collected by Bobby">#',
			$body);
	}

	public function testSharedCollectionOfADeletedAccountDoesNotNameItsOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob', 'display_name' => 'Bobby', 'deleted' => true]],
		]);
		$setTable = $this->fetchTable('Set');
		$createdSet = $setTable->saveOrFail($setTable->newEntity([
			'user_id' => $context->otherUsers[0]['id'],
			'title' => 'Bobby Collection',
			'public' => 1,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]));

		$this->get('sets/' . $createdSet->id);
		$body = $this->view;

		$this->assertStringContainsString('<meta property="og:title" content="Bobby Collection by ' . h(User::DELETED_NAME) . '">', $body);
		$this->assertStringNotContainsString('Bobby"', $body, 'the name of a deleted account stays out of the preview');
		$this->assertStringNotContainsString('collected by Bobby', $body);
	}

	public function testHomePageShowsDefaultOpenGraphTags(): void
	{
		$this->get('sites/index');
		$body = $this->view;

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

		$this->get('users/view/' . $userId);
		$body = $this->view;

		$this->assertStringContainsString('<meta property="og:title" content="Profile of DN_alice">', $body);
		$this->assertStringContainsString('<meta property="og:type" content="profile">', $body);
		$this->assertStringContainsString('<meta property="profile:username" content="DN_alice">', $body);
		$this->assertStringContainsString('property="og:url" content="', $body);
		$this->assertStringContainsString('/users/view/' . $userId, $body);
	}

	//A profile is shared with the picture of the player, which is the board the
	//site draws for the name. A share preview takes a png and not an svg.
	public function testUserProfileSharesTheBoardAvatar(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'alice']]]);
		$userId = $context->otherUsers[0]['id'];

		$this->get('users/view/' . $userId);
		$body = $this->view;

		$this->assertStringContainsString(Avatar::pngUrl('DN_alice') . '"', $body);
		$this->assertStringContainsString('<meta property="og:image:type" content="image/png">', $body);
		$this->assertStringContainsString('<meta property="og:image:width" content="' . Avatar::SHARE_SIZE . '">', $body);
		$this->assertStringContainsString('<meta property="og:image:height" content="' . Avatar::SHARE_SIZE . '">', $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}

	//A picture of another host is already a picture, so it is shared as it is.
	public function testUserProfileSharesThePickedGravatar(): void
	{
		$context = new ContextPreparator(['other-users' => [[
			'name' => 'alice',
			'email' => 'alice@example.com',
			'avatar_setting_id' => User::AVATAR_GRAVATAR,
		]]]);

		$this->get('users/view/' . $context->otherUsers[0]['id']);
		$body = $this->view;

		$this->assertStringContainsString('og:image" content="https://www.gravatar.com/avatar/' . User::gravatarHash('alice@example.com'), $body);			// Gravatar is told which size to send, so the size of the preview is known.
		$this->assertStringContainsString('<meta property="og:image:width" content="' . Avatar::SHARE_SIZE . '">', $body);
		$this->assertStringNotContainsString('og:image:type', $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}

	public function testUserProfileSharesThePickedGooglePicture(): void
	{
		$context = new ContextPreparator(['other-users' => [[
			'name' => 'alice',
			'email' => 'alice@example.com',
			'external_id' => '1234567890',
			'avatar_setting_id' => User::AVATAR_GOOGLE,
			'picture' => 'https://lh3.googleusercontent.com/a/abc=s96-c',
		]]]);

		$this->get('users/view/' . $context->otherUsers[0]['id']);
		$body = $this->view;

		$this->assertStringContainsString('og:image" content="https://lh3.googleusercontent.com/a/abc=s' . Avatar::SHARE_SIZE . '"', $body);
		// The format of a picture of another host is not known, so it is not claimed.
		$this->assertStringNotContainsString('og:image:type', $body);
		$this->assertStringNotContainsString('Tsumego-Hero-Logo.png', $body);
	}
}
