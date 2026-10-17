<?php

use App\Utility\Constants;

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
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save(['Set' => [
			'user_id' => $context->otherUsers[0]['id'],
			'title' => 'Bobby Collection',
			'public' => 1,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]]);

		$body = $this->testAction('sets/' . $setModel->id, ['return' => 'contents']);

		$this->assertStringContainsString('<meta property="og:title" content="Bobby Collection by Bobby">', $body);
		$this->assertMatchesRegularExpression(
			'#<meta property="og:description" content="\d+ go problems collected by Bobby">#',
			$body);
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
}
