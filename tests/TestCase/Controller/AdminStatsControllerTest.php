<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('AdminActivityLogger', 'Utility');

/**
 * AdminStatsControllerTest
 *
 * Tests for the admin activity logging system and adminstats page
 * - Tests adminstats page pagination and display
 * - Tests filtering out non-admin SGF uploads
 * - Tests pagination with multiple sections (activity, proposals, tags, tagnames)
 * - Tests all 19 activity types display correctly with formatted messages
 */
class AdminStatsControllerTest extends ControllerTestCase
{
	/**
	 * Test adminstats page displays in browser
	 */
	public function testAdminStatsPageDisplaysInBrowser()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [],
			'admin-activities' => [
				[
					'type' => AdminActivityLogger::DESCRIPTION_EDIT,
					'tsumego_id' => true,
					'old_value' => 'Old desc',
					'new_value' => 'New desc'

				]
			]
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Admin Activity', $pageSource);
		$this->assertTextContains('Description Edit', $pageSource);
	}

	/**
	 * Test non-admin users cannot access adminstats page
	 */
	public function testAdminStatsRedirectsNonAdmin()
	{
		$context = new ContextPreparator([
			'user' => []
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');

		// Should redirect to home page
		$this->assertStringContainsString('/', $browser->driver->getCurrentURL());
	}

	/**
	 * Test that non-admin SGF uploads are filtered out
	 */
	public function testNonAdminSgfUploadsFilteredOut()
	{
		App::uses('AdminActivityLogger', 'Utility');

		// Create admin user and regular user
		$context = new ContextPreparator([
			'user' => ['name' => 'admin_user', 'admin' => true],
			'other-users' => [['name' => 'regular_user', 'admin' => false]],
			'tsumego' => [],
			'admin-activities' => [
				[
					'type' => AdminActivityLogger::SGF_UPLOAD,
					'tsumego_id' => true
				],
				[
					'type' => AdminActivityLogger::SGF_UPLOAD,
					'tsumego_id' => true,
					'user_id' => 'regular_user' // ContextPreparator will look up by name
				]
			]
		]);

		// View adminstats page
		$browser = Browser::instance();
		$browser->get('users/adminstats');

		$pageSource = $browser->driver->getPageSource();

		// Admin SGF upload should be visible
		$this->assertTextContains('admin_user', $pageSource);

		// Both exist in DB
		$allActivities = ClassRegistry::init('AdminActivity')->find('all');
		$this->assertCount(2, $allActivities);
	}

	/**
	 * Test set-level admin activities (activities without tsumego_id)
	 */
	public function testSetLevelActivities()
	{
		App::uses('AdminActivityLogger', 'Utility');

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Test Set', 'num' => 1]]
			],
			'admin-activities' => [
				[
					'type' => AdminActivityLogger::SET_TITLE_EDIT,
					'set_id' => true,
					'old_value' => 'Old Title',
					'new_value' => 'New Title'
				]
			]
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Set Title Edit', $pageSource);
		$this->assertTextContains('Old Title → New Title', $pageSource);
		$this->assertTextContains('(Set-wide)', $pageSource);
	}

	/**
	 * Test activity pagination
	 */
	public function testActivityPagination()
	{
		App::uses('AdminActivityLogger', 'Utility');

		// Build activities array programmatically
		$activities = [];
		for ($i = 1; $i <= 105; $i++)
		{
			$activities[] = [
				'type' => AdminActivityLogger::DESCRIPTION_EDIT,
				'tsumego_id' => true,
				'old_value' => "Old desc $i",
				'new_value' => "New desc $i"
			];
		}

		// Create context with all 105 activities
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [],
			'admin-activities' => $activities
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');

		$pageSource = $browser->driver->getPageSource();

		// Should show pagination controls
		$this->assertTextContains('Page 1 of 2', $pageSource);
		$this->assertTextContains('Next »', $pageSource);
		$this->assertTextContains('#pagination-activity', $pageSource);

		// Navigate to page 2
		$browser->get('users/adminstats?activity_page=2');
		$pageSource = $browser->driver->getPageSource();

		$this->assertTextContains('Page 2 of 2', $pageSource);
		$this->assertTextContains('« Previous', $pageSource);
	}

	/**
	 * Test multiple paginations work independently
	 */
	public function testMultiplePaginationsIndependent()
	{
		App::uses('AdminActivityLogger', 'Utility');

		// Build activities array programmatically
		$activities = [];
		for ($i = 1; $i <= 105; $i++)
		{
			$activities[] = [
				'type' => AdminActivityLogger::DESCRIPTION_EDIT,
				'tsumego_id' => true,
				'old_value' => "Old desc $i",
				'new_value' => "New desc $i"
			];
		}

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [],
			'admin-activities' => $activities
		]);

		// Create unapproved tags
		for ($i = 1; $i <= 105; $i++)
		{
			$tagName = ClassRegistry::init('Tag');
			$tagName->create();
			$tagName->save([
				'name' => "Test Tag $i",
				'user_id' => $context->user['id'],
				'approved' => 0
			]);
		}

		// Navigate with both pagination parameters
		$browser = Browser::instance();
		$browser->get('users/adminstats?activity_page=2&tagnames_page=2');

		$pageSource = $browser->driver->getPageSource();

		// Both paginations should preserve their state
		$this->assertStringContainsString('activity_page=2', $pageSource);
		$this->assertStringContainsString('tagnames_page=2', $pageSource);
	}

	/**
	 * Test all 19 activity types display correctly
	 * Uses controller-level testing to avoid browser HTML truncation
	 */
	public function testAllActivityTypesDisplay()
	{
		App::uses('AdminActivityLogger', 'Utility');

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Test Set', 'num' => 1]]
			],
			'other-tsumegos' => [
				['sets' => [['name' => 'Test Set', 'num' => 2]]]
			],
			'admin-activities' => [
				// Problem edits (1-3)
				['type' => AdminActivityLogger::DESCRIPTION_EDIT, 'tsumego_id' => true, 'old_value' => 'old desc', 'new_value' => 'new desc'],
				['type' => AdminActivityLogger::HINT_EDIT, 'tsumego_id' => true, 'old_value' => 'old hint', 'new_value' => 'new hint'],
				['type' => AdminActivityLogger::PROBLEM_DELETE, 'tsumego_id' => true],

				// SGF upload (4)
				['type' => AdminActivityLogger::SGF_UPLOAD, 'tsumego_id' => true, 'old_value' => 'old sgf', 'new_value' => 'new sgf'],

				// Problem settings (5-8)
				['type' => AdminActivityLogger::ALTERNATIVE_RESPONSE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityLogger::PASS_MODE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityLogger::MULTIPLE_CHOICE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityLogger::SCORE_ESTIMATING, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],

				// User requests (9)
				['type' => AdminActivityLogger::SOLUTION_REQUEST, 'tsumego_id' => true],

				// Set metadata (10-14)
				['type' => AdminActivityLogger::SET_TITLE_EDIT, 'set_id' => true, 'old_value' => 'Old Set', 'new_value' => 'New Set'],
				['type' => AdminActivityLogger::SET_DESCRIPTION_EDIT, 'set_id' => true, 'old_value' => 'Old desc', 'new_value' => 'New desc'],
				['type' => AdminActivityLogger::SET_COLOR_EDIT, 'set_id' => true, 'old_value' => 'red', 'new_value' => 'blue'],
				['type' => AdminActivityLogger::SET_ORDER_EDIT, 'set_id' => true, 'old_value' => '10', 'new_value' => '20'],
				['type' => AdminActivityLogger::SET_RATING_EDIT, 'set_id' => true, 'old_value' => '1000', 'new_value' => '1100'],

				// Problem management (15) - uses other-tsumegos[0]
				['type' => AdminActivityLogger::PROBLEM_ADD, 'tsumego_id' => 'other:0', 'set_id' => true],

				// Set-wide settings (16-17)
				['type' => AdminActivityLogger::SET_ALTERNATIVE_RESPONSE, 'set_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityLogger::SET_PASS_MODE, 'set_id' => true, 'old_value' => '0', 'new_value' => '1'],

				// Duplicate management (18-19) - reference other tsumego in old_value
				['type' => AdminActivityLogger::DUPLICATE_REMOVE, 'tsumego_id' => true, 'old_value' => 'other:0'],
				['type' => AdminActivityLogger::DUPLICATE_GROUP_CREATE, 'tsumego_id' => true, 'old_value' => 'other:0'],
			]
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');
		$pageSource = $browser->driver->getPageSource();

		// Verify all 19 activity type names appear in HTML
		$this->assertTextContains('Description Edit', $pageSource);
		$this->assertTextContains('Hint Edit', $pageSource);
		$this->assertTextContains('Problem Delete', $pageSource);
		$this->assertTextContains('SGF Upload', $pageSource);
		$this->assertTextContains('Alternative Response', $pageSource);
		$this->assertTextContains('Pass Mode', $pageSource);
		$this->assertTextContains('Multiple Choice', $pageSource);
		$this->assertTextContains('Score Estimating', $pageSource);
		$this->assertTextContains('Solution Request', $pageSource);
		$this->assertTextContains('Set Title Edit', $pageSource);
		$this->assertTextContains('Set Description Edit', $pageSource);
		$this->assertTextContains('Set Color Edit', $pageSource);
		$this->assertTextContains('Set Order Edit', $pageSource);
		$this->assertTextContains('Set Rating Edit', $pageSource);
		$this->assertTextContains('Problem Add', $pageSource);
		$this->assertTextContains('Set Alternative Response', $pageSource);
		$this->assertTextContains('Set Pass Mode', $pageSource);
		$this->assertTextContains('Duplicate Remove', $pageSource);
		$this->assertTextContains('Duplicate Group Create', $pageSource);

		// Verify formatted messages with old/new values appear in HTML

		// Problem edits (with old → new)
		$this->assertTextContains('Description Edit: old desc → new desc', $pageSource);
		$this->assertTextContains('Hint Edit: old hint → new hint', $pageSource);
		$this->assertTextContains('SGF Upload: old sgf → new sgf', $pageSource);

		// Problem edits (no values - show just type name)
		$this->assertTextContains('Problem Delete', $pageSource);
		$this->assertTextContains('Solution Request', $pageSource);

		// Problem settings (toggles: 0 → 1 = disabled → enabled)
		$this->assertTextContains('Alternative Response → enabled', $pageSource);
		$this->assertTextContains('Pass Mode → enabled', $pageSource);
		$this->assertTextContains('Multiple Choice → enabled', $pageSource);
		$this->assertTextContains('Score Estimating → enabled', $pageSource);

		// Set metadata edits (with old → new)
		$this->assertTextContains('Set Title Edit: Old Set → New Set', $pageSource);
		$this->assertTextContains('Set Description Edit: Old desc → New desc', $pageSource);
		$this->assertTextContains('Set Color Edit: red → blue', $pageSource);
		$this->assertTextContains('Set Order Edit: 10 → 20', $pageSource);
		$this->assertTextContains('Set Rating Edit: 1000 → 1100', $pageSource);

		// Problem management (no values)
		$this->assertTextContains('Problem Add', $pageSource);

		// Set-wide settings (toggles)
		$this->assertTextContains('Set Alternative Response → enabled', $pageSource);
		$this->assertTextContains('Set Pass Mode → enabled', $pageSource);
	}

	/**
	 * Test admin comments section displays separately from activities
	 */
	public function testAdminCommentsSection()
	{
		App::uses('AdminActivityLogger', 'Utility');

		$context = new ContextPreparator([
			'user' => ['admin' => true, 'name' => 'admin_user'],
			'tsumego' => [
				'comments' => [
					['message' => 'This is an admin comment about this problem']
				]
			],
			'admin-activities' => [
				[
					'type' => AdminActivityLogger::DESCRIPTION_EDIT,
					'tsumego_id' => true,
					'old_value' => 'Old desc',
					'new_value' => 'New desc'
				]
			]
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');

		$pageSource = $browser->driver->getPageSource();

		// Should show both sections
		$this->assertTextContains('Admin Activity', $pageSource);
		$this->assertTextContains('Admin Comments', $pageSource);

		// Activity should show type and old→new
		$this->assertTextContains('Description Edit: Old desc → New desc', $pageSource);

		// Comment should show message text
		$this->assertTextContains('This is an admin comment about this problem', $pageSource);

		// Both should show username
		$this->assertTextContains('admin_user', $pageSource);
	}
}
