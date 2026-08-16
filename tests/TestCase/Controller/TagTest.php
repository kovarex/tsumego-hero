<?php

use PHPUnitRetry\RetryTrait;

/**
 * Tag editor tests — React component with data-testid selectors.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class TagTest extends ControllerTestCase
{
	use RetryTrait;

	private function openEditorAndType(Browser $browser, string $query): void
	{
		$browser->clickCssSelect('[data-testid="tag-search-input"]');
		$browser->find('[data-testid="tag-search-input"]')->sendKeys($query);
	}

	public function testAddTagConnection()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Wait for React tag editor to mount
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->openEditorAndType($browser, 'snap');
		$browser->waitUntilCssSelectorExists('[role="option"]');
		$browser->clickId('tag-snapback');
		$browser->waitUntilCssSelectorExists('[data-testid="tag-snapback"]');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$tagConnection = ClassRegistry::init('TagConnection')->find('first', []);
		$this->assertNotNull($tagConnection);
		$this->assertCount(1, $browser->getCssSelect('[data-testid="tag-snapback"]'));
	}

	public function testAddTagDoesntOfferAlreadyExistingTag()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumegos' => [['set_order' => 1, 'tags' => [['name' => 'atari']]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// atari is already added — shouldn't show in suggestions
		$this->openEditorAndType($browser, 'ata');
		$this->assertCount(0, $browser->getCssSelect('[data-testid="tag-editor"] [role="option"]'));
	}

	public function testShowMyUnapprovedTagsInTagListAndNotInTagsToAdd()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumegos' => [['set_order' => 1, 'tags' => [['name' => 'atari', 'approved' => 0]]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertCount(1, $browser->getCssSelect('[data-testid="tag-atari"]'));
		$this->openEditorAndType($browser, 'ata');
		$this->assertCount(0, $browser->getCssSelect('[data-testid="tag-editor"] [role="option"]'));
	}

	public function testDontShowOthersApprovedTagsInAddTags()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'Ivan detkov']],
			'tsumegos' => [[
				'set_order' => 1,
				'tags' => [['name' => 'atari', 'approved' => 1, 'user' => 'Ivan detkov']]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->openEditorAndType($browser, 'snap');
		$this->assertCount(1, $browser->getCssSelect('[data-testid="tag-editor"] [role="option"]'));
		$browser->clickId('tag-snapback');

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 5, 200);
		$wait->until(function () use ($browser) {
			return count($browser->getCssSelect('[data-testid="tag-snapback"]')) === 1;
		});
	}

	public function testShowOthersUnapprovedTagsInAddTagsButNotClickable()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'Ivan detkov']],
			'tsumegos' => [[
				'set_order' => 1,
				'tags' => [['name' => 'atari', 'approved' => 0, 'user' => 'Ivan detkov']]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertCount(0, $browser->getCssSelect('[data-testid="tag-atari"]'));
		$this->openEditorAndType($browser, 'ata');
		$this->assertCount(0, $browser->getCssSelect('[data-testid="tag-editor"] [role="option"]'));
		$this->assertStringContainsString('already proposed', $browser->driver->getPageSource());
	}

	private function getTagListText(Browser $browser): string
	{
		return $browser->find('[data-testid="tag-list"]')->getText();
	}

	private function tagListContains(Browser $browser, string $tagName): bool
	{
		return count($browser->getCssSelect('[data-testid="tag-' . $tagName . '"]')) > 0;
	}

	// --- Hint visibility ---

	public function testHintTagHiddenWhenUnsolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'other']],
			'tsumego' => ['set_order' => 1, 'tags' => [
				['name' => 'snapback', 'is_hint' => 1, 'user' => 'other'],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->assertFalse($this->tagListContains($browser, 'snapback'));
		$this->assertStringContainsString('1 hidden', $this->getTagListText($browser));
	}

	public function testHintTagVisibleWhenSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'other']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'tags' => [
				['name' => 'snapback', 'is_hint' => 1, 'user' => 'other'],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->assertTrue($this->tagListContains($browser, 'snapback'));
		$this->assertStringNotContainsString('hidden', $this->getTagListText($browser));
	}

	public function testOwnHintTagVisibleWhenUnsolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [
				['name' => 'snapback', 'is_hint' => 1],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->assertTrue($this->tagListContains($browser, 'snapback'));
		$this->assertStringNotContainsString('hidden', $this->getTagListText($browser));
	}

	public function testHiddenCountShowsForHintTags(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'other']],
			'tsumego' => ['set_order' => 1, 'tags' => [
				['name' => 'atari', 'user' => 'other'],
				['name' => 'snapback', 'is_hint' => 1, 'user' => 'other'],
				['name' => 'ko', 'is_hint' => 1, 'user' => 'other'],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->assertTrue($this->tagListContains($browser, 'atari'));
		$this->assertFalse($this->tagListContains($browser, 'snapback'));
		$this->assertStringContainsString('2 hidden', $this->getTagListText($browser));
	}

	public function testHiddenCountGoneWhenSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'other']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'tags' => [
				['name' => 'atari', 'user' => 'other'],
				['name' => 'snapback', 'is_hint' => 1, 'user' => 'other'],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->assertTrue($this->tagListContains($browser, 'atari'));
		$this->assertTrue($this->tagListContains($browser, 'snapback'));
		$this->assertStringNotContainsString('hidden', $this->getTagListText($browser));
	}

	public function testHiddenCountExcludesOwnHints(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'other']],
			'tsumego' => ['set_order' => 1, 'tags' => [
				['name' => 'snapback', 'is_hint' => 1, 'user' => 'other'],
				['name' => 'ko', 'is_hint' => 1],
			]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		// My hint (ko) is visible, other's hint (snapback) is hidden
		$this->assertTrue($this->tagListContains($browser, 'ko'));
		$this->assertFalse($this->tagListContains($browser, 'snapback'));
		// Only 1 hidden (not 2), because my own hint is excluded
		$this->assertStringContainsString('1 hidden', $this->getTagListText($browser));
	}

	public function testHintIndicatorInDropdown(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [['name' => 'snapback', 'is_hint' => 1]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilCssSelectorExists('[data-testid="tag-editor"]');

		$this->openEditorAndType($browser, 'snap');
		$browser->waitUntilCssSelectorExists('[role="option"]');

		$option = $browser->find('[role="option"]');
		$this->assertStringContainsString('(hint)', $option->getText());
	}

	private function clickAndWaitForError(Browser $browser, string $selector): void
	{
		$browser->find($selector)->click();
		$browser->waitUntilCssSelectorExists('[data-testid="tag-error"]');
	}

	// --- Error handling (AJAX backend, same as before) ---

	public function testTryToAddTagWhenNotLoggedIn()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => 1,
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->openEditorAndType($browser, 'snap');
		$browser->driver->manage()->deleteAllCookies();
		$this->clickAndWaitForError($browser, '#tag-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("Not logged in", $error);
	}

	public function testTryToAddNonExistingTag()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => 1,
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->openEditorAndType($browser, 'snap');
		ClassRegistry::init('Tag')->delete($context->tags[0]['id']);
		$this->clickAndWaitForError($browser, '#tag-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("doesn't exist", $error);
	}

	public function testTryToAddTagToNonExistingTsumego()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => 1,
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->openEditorAndType($browser, 'snap');
		ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);
		$this->clickAndWaitForError($browser, '#tag-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("wasn't found", $error);
	}

	public function testTryToAddDupliciteTag()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => 1,
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		ClassRegistry::init('TagConnection')->create();
		ClassRegistry::init('TagConnection')->save([
			'tag_id' => $context->tags[0]['id'],
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 1,
		]);
		$this->openEditorAndType($browser, 'snap');
		$this->clickAndWaitForError($browser, '#tag-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains('already has tag', $error);
	}

	public function testRemoveMyUnapprovedTag()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'atari', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertCount(1, $browser->getCssSelect('[data-testid="tag-atari"]'));
		$this->assertNotEmpty(ClassRegistry::init('TagConnection')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));

		$browser->clickId("remove-atari");
		$browser->waitUntilCssSelectorDoesntExist('[data-testid="tag-atari"]');
		$this->assertCount(0, $browser->getCssSelect('[data-testid="tag-atari"]'));
		$this->assertEmpty(ClassRegistry::init('TagConnection')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
	}

	public function testTryToRemoveTagWhenNotLoggedIn()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->driver->manage()->deleteAllCookies();
		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("Not logged in", $error);
	}

	public function testTryToRemoveNonExistingTag()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('Tag')->delete($context->tags[0]['id']);
		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("doesn't exist", $error);
	}

	public function testTryToRemoveFromNonExistingTsumego()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);
		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("wasn't found", $error);
	}

	public function testTryToRemoveTagConnectionWhichDoesntExist()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('TagConnection')->deleteAll(['1=1']);
		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("isn't assigned", $error);
	}

	public function testTryToRemoveApprovedTagAsNonAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$tagConnection = ClassRegistry::init('TagConnection')->findById($context->tsumegos[0]['tag-connections'][0]['id']);
		$tagConnection['TagConnection']['approved'] = true;
		ClassRegistry::init('TagConnection')->save($tagConnection);

		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains('Only admins can remove', $error);
	}

	public function testTryToRemoveTagProposedBySomeoneElse()
	{
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'other-users' => [['name' => 'Ivan Detkov']],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'approved' => 0]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$tagConnection = ClassRegistry::init('TagConnection')->findById($context->tsumegos[0]['tag-connections'][0]['id']);
		$tagConnection['TagConnection']['user_id'] = $context->otherUsers[0]['id'];
		ClassRegistry::init('TagConnection')->save($tagConnection);

		$this->clickAndWaitForError($browser, '#remove-snapback');
		$error = $browser->find('[data-testid="tag-error"]')->getText();
		$this->assertTextContains("can't remove tag proposed", $error);
	}

	public function testAddNewTagAsAdmin()
	{
		foreach ([false, true] as $isHint)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'status' => 'S']]);
			$browser->get('/' . $context->setConnections[0]['id']);

			$browser->clickId('create-new-tag');

			$browser->clickId('tag_name');
			$browser->driver->getKeyboard()->sendKeys('atari');
			$browser->clickId('tag_description');
			$browser->driver->getKeyboard()->sendKeys('Not the console, which is named by this by the way.');
			$browser->clickId('tag_reference');
			$browser->driver->getKeyboard()->sendKeys('tag.example.com');
			if ($isHint)
				$browser->clickId('tag_hint_true');
			else
				$browser->clickId('tag_hint_false');
			$browser->clickId('submit_tag');

			$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
			$wait->until(function () use ($browser) {
				return strpos($browser->driver->getCurrentURL(), '/tags/view/') !== false;
			});

			$tagAdded = ClassRegistry::init('Tag')->find('first')['Tag'];
			$this->assertSame(Util::getMyAddress() . '/tags/view/' . $tagAdded['id'], $browser->driver->getCurrentURL());
			$this->assertSame($tagAdded['name'], 'atari');
			$this->assertSame($tagAdded['description'], 'Not the console, which is named by this by the way.');
			$this->assertSame($tagAdded['link'], 'tag.example.com');
			$this->assertSame($tagAdded['hint'], $isHint ? 1 : 0);
			$this->assertSame($tagAdded['user_id'], Auth::getUserID());
			$this->assertSame($tagAdded['approved'], 1);
		}
	}

	public function testAddNewTagAsNonAdmin()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$browser->get('/' . $context->setConnections[0]['id']);

		$browser->clickId('create-new-tag');

		$browser->clickId('tag_name');
		$browser->driver->getKeyboard()->sendKeys('self atari');
		$browser->clickId('tag_description');
		$browser->driver->getKeyboard()->sendKeys('A self-atari is a move that puts your own stones into atari.');
		$browser->clickId('submit_tag');

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return strpos($browser->driver->getCurrentURL(), '/tags/view/') !== false;
		});

		$tagAdded = ClassRegistry::init('Tag')->find('first')['Tag'];
		$this->assertSame(Util::getMyAddress() . '/tags/view/' . $tagAdded['id'], $browser->driver->getCurrentURL());
		$this->assertSame($tagAdded['name'], 'self atari');
		$this->assertSame($tagAdded['approved'], 0);
	}

	public function testAddTagWithoutDescriptionShowsError()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);
		$browser->get('/tags/add');

		$browser->clickId('tag_name');
		$browser->driver->getKeyboard()->sendKeys('test tag no description');
		$browser->clickId('submit_tag');

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return str_contains($browser->driver->getPageSource(), 'alert-error');
		});

		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('description', strtolower($pageSource));
		$this->assertStringContainsString('/tags/add', $browser->driver->getCurrentURL());
		$this->assertEmpty(ClassRegistry::init('Tag')->find('all'));
	}

	public function testApproveNewTag()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['admin' => true], 'tags' => [['name' => 'snapback', 'approved' => 0]]]);
		$browser->get('users/adminstats');

		$this->assertSame(ClassRegistry::init('Tag')->find('first')['Tag']['approved'], 0);
		$browser->clickId('tag-accept-' . $context->tags[0]['id']);
		$this->assertSame(ClassRegistry::init('Tag')->find('first')['Tag']['approved'], 1);
	}

	public function testRejectNewTag()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['admin' => true], 'tags' => [['name' => 'snapback', 'approved' => 0]]]);
		$browser->get('users/adminstats');
		$browser->clickId('tag-reject-' . $context->tags[0]['id']);
		$tagAdded = ClassRegistry::init('Tag')->find('first')['Tag'];
		$this->assertNull($tagAdded);
	}

	public function testEditTag()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tags' => [['name' => 'snapback', 'description' => 'Hello']]]);
		$browser->get('/tags/view/' . $context->tags[0]['id']);
		$browser->clickId('tag-edit');
		$descField = $browser->find('#tag_description');
		$descField->clear();
		$descField->sendKeys('World');

		$browser->clickId('tag_link');
		$browser->driver->getKeyboard()->sendKeys('bla.example.com');
		$browser->clickId('submit_tag');
		$tag = ClassRegistry::init('Tag')->find('first')['Tag'];
		$this->assertSame('World', $tag['description']);
		$this->assertSame('bla.example.com', $tag['link']);
		$this->assertSame(0, $tag['hint']);
	}

	public function testEditTagWithoutDescriptionShowsError()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tags' => [['name' => 'snapback', 'description' => 'Hello']]]);
		$browser->get('/tags/view/' . $context->tags[0]['id']);
		$browser->clickId('tag-edit');
		$descField = $browser->find('#tag_description');
		$descField->clear();
		$browser->clickId('submit_tag');

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return str_contains($browser->driver->getPageSource(), 'alert-error');
		});

		$this->assertStringContainsString('/tags/edit/', $browser->driver->getCurrentURL());
		$this->assertSame('Hello', ClassRegistry::init('Tag')->find('first')['Tag']['description']);
	}

	public function testEditTagEnableHint()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tags' => [['name' => 'snapback', 'description' => 'Hello']]]);
		$browser->get('/tags/view/' . $context->tags[0]['id']);
		$browser->clickId('tag-edit');
		$browser->clickId('tag_hint_true');
		$browser->clickId('submit_tag');
		$tag = ClassRegistry::init('Tag')->find('first')['Tag'];
		$this->assertSame(1, $tag['hint']);
	}

	public function testEditTagDisableHint()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tags' => [['name' => 'snapback', 'description' => 'Hello', 'is_hint' => true]]]);
		$this->assertSame(1, $context->tags[0]['hint']);
		$browser->get('/tags/view/' . $context->tags[0]['id']);
		$browser->clickId('tag-edit');
		$browser->clickId('tag_hint_false');
		$browser->clickId('submit_tag');
		$tag = ClassRegistry::init('Tag')->find('first')['Tag'];
		$this->assertSame(0, $tag['hint']);
	}

	public function testAdminAcceptsTagProposal()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]]);

		$tag = ClassRegistry::init('Tag');
		$tag->create();
		$tag->save([
			'Tag' => [
				'name' => 'tesuji',
				'description' => 'A clever move',
				'user_id' => $context->otherUsers[0]['id'],
				'approved' => 0,
			],
		]);
		$tagId = $tag->id;

		$this->assertSame(0, ClassRegistry::init('Tag')->findById($tagId)['Tag']['approved']);

		$browser = Browser::instance();
		$browser->get('/tags/acceptTagProposal/' . $tagId);

		$this->assertStringEndsWith('/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertSame(1, ClassRegistry::init('Tag')->findById($tagId)['Tag']['approved']);
		$this->assertStringContainsString('was approved', $browser->driver->getPageSource());

		$contrib = ClassRegistry::init('UserContribution')->find('first', [
			'conditions' => ['user_id' => $context->otherUsers[0]['id']]]);
		$this->assertSame(1, (int) $contrib['UserContribution']['created_tag']);
	}

	public function testAcceptAlreadyApprovedTagShowsError()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']]]);

		$tag = ClassRegistry::init('Tag');
		$tag->create();
		$tag->save([
			'Tag' => [
				'name' => 'already_approved',
				'description' => 'Test',
				'user_id' => $context->otherUsers[0]['id'],
				'approved' => 1,
			],
		]);
		$tagId = $tag->id;

		$browser = Browser::instance();
		$browser->get('/tags/acceptTagProposal/' . $tagId);

		$this->assertStringEndsWith('/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertStringContainsString('already approved', $browser->driver->getPageSource());
	}

	public function testNonAdminCannotAcceptTagProposal()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'other-users' => [['name' => 'proposer']]]);

		$tag = ClassRegistry::init('Tag');
		$tag->create();
		$tag->save([
			'Tag' => [
				'name' => 'unapproved_tag',
				'description' => 'Test',
				'user_id' => $context->otherUsers[0]['id'],
				'approved' => 0,
			],
		]);
		$tagId = $tag->id;

		$browser = Browser::instance();
		$browser->get('/tags/acceptTagProposal/' . $tagId);

		$this->assertStringNotContainsString('adminstats', $browser->driver->getCurrentURL());
		$this->assertSame(0, ClassRegistry::init('Tag')->findById($tagId)['Tag']['approved']);
	}

	public function testTagHighscoreUpdatesAfterAcceptance()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]]);

		$tagConnectionId = $context->tsumegos[0]['tag-connections'][0]['id'];

		$browser = Browser::instance();

		$browser->get('/users/added_tags');
		$this->assertStringNotContainsString('proposer', $browser->driver->getPageSource());

		$browser->get('/users/acceptTagConnectionProposal/' . $tagConnectionId);

		$browser->get('/users/added_tags');
		$this->assertStringContainsString('proposer', $browser->driver->getPageSource());
	}
}
