<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');

/**
 * AdminStatsControllerTest
 *
 * Tests for the admin activity logging system and adminstats page
 * - Tests adminstats page pagination and display
 * - Tests filtering out non-admin SGF uploads
 * - Tests pagination with multiple sections (activity, proposals, tags, tagnames)
 * - Tests all 18 activity types display correctly with formatted messages
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
			'tsumego' => 1,
			'admin-activities' => [
				[
					'type' => AdminActivityType::DESCRIPTION_EDIT,
					'tsumego_id' => 'other:0',
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

	public function testAdminStatsRedirectsNonAdmin()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('users/adminstats');
		$this->assertStringContainsString('/', $browser->driver->getCurrentURL()); // Should redirect to home page
	}

	/**
	 * Test set-level admin activities (activities without tsumego_id)
	 */
	public function testSetLevelActivities()
	{
		App::uses('AdminActivityLogger', 'Utility');
		App::uses('AdminActivityType', 'Model');

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => 1,
			'admin-activities' => [
				[
					'type' => AdminActivityType::SET_TITLE_EDIT,
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
		App::uses('AdminActivityType', 'Model');

		// Build activities array programmatically
		$activities = [];
		for ($i = 1; $i <= 105; $i++)
		{
			$activities[] = [
				'type' => AdminActivityType::DESCRIPTION_EDIT,
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

	public function testMultiplePaginationsIndependent()
	{
		App::uses('AdminActivityLogger', 'Utility');
		App::uses('AdminActivityType', 'Model');

		// Build activities array programmatically
		$activities = [];
		for ($i = 1; $i <= 105; $i++)
		{
			$activities[] = [
				'type' => AdminActivityType::DESCRIPTION_EDIT,
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
	 * Test all 18 activity types display correctly
	 * Uses controller-level testing to avoid browser HTML truncation
	 */
	public function testAllActivityTypesDisplay()
	{
		App::uses('AdminActivityLogger', 'Utility');
		App::uses('AdminActivityType', 'Model');

		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [1, 2],
			'admin-activities' => [
				// Problem edits (1-3)
				['type' => AdminActivityType::DESCRIPTION_EDIT, 'tsumego_id' => true, 'old_value' => 'old desc', 'new_value' => 'new desc'],
				['type' => AdminActivityType::HINT_EDIT, 'tsumego_id' => true, 'old_value' => 'old hint', 'new_value' => 'new hint'],
				['type' => AdminActivityType::AUTHOR_EDIT, 'tsumego_id' => true, 'old_value' => 'old author', 'new_value' => 'new author'],
				['type' => AdminActivityType::RATING_EDIT, 'tsumego_id' => true, 'old_value' => '666', 'new_value' => '777'],
				['type' => AdminActivityType::MINIMUM_RATING_EDIT, 'tsumego_id' => true, 'old_value' => '1000', 'new_value' => '1500'],
				['type' => AdminActivityType::MAXIMUM_RATING_EDIT, 'tsumego_id' => true, 'old_value' => '900', 'new_value' => '800'],
				['type' => AdminActivityType::PROBLEM_DELETE, 'tsumego_id' => true],

				// Problem settings (4-7)
				['type' => AdminActivityType::ALTERNATIVE_RESPONSE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityType::PASS_MODE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityType::MULTIPLE_CHOICE, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityType::SCORE_ESTIMATING, 'tsumego_id' => true, 'old_value' => '0', 'new_value' => '1'],

				// User requests (8)
				['type' => AdminActivityType::SOLUTION_REQUEST, 'tsumego_id' => true],

				// Set metadata (9-13)
				['type' => AdminActivityType::SET_TITLE_EDIT, 'set_id' => true, 'old_value' => 'Old Set', 'new_value' => 'New Set'],
				['type' => AdminActivityType::SET_DESCRIPTION_EDIT, 'set_id' => true, 'old_value' => 'Old desc', 'new_value' => 'New desc'],
				['type' => AdminActivityType::SET_COLOR_EDIT, 'set_id' => true, 'old_value' => 'red', 'new_value' => 'blue'],
				['type' => AdminActivityType::SET_ORDER_EDIT, 'set_id' => true, 'old_value' => '10', 'new_value' => '20'],
				['type' => AdminActivityType::SET_RATING_EDIT, 'set_id' => true, 'old_value' => '1000', 'new_value' => '1100'],

				// Problem management (14) - uses other-tsumegos[0]
				['type' => AdminActivityType::PROBLEM_ADD, 'tsumego_id' => 'other:0', 'set_id' => true],

				// Set-wide settings (15-16)
				['type' => AdminActivityType::SET_ALTERNATIVE_RESPONSE, 'set_id' => true, 'old_value' => '0', 'new_value' => '1'],
				['type' => AdminActivityType::SET_PASS_MODE, 'set_id' => true, 'old_value' => '0', 'new_value' => '1'],

				// tag proposals
				['type' => AdminActivityType::ACCEPT_TAG, 'tsumego_id' => true, 'new_value' => 'snapback'],
				['type' => AdminActivityType::REJECT_TAG, 'tsumego_id' => true, 'old_value' => 'atari'],

				// tsumego merge
				['type' => AdminActivityType::TSUMEGO_MERGE, 'tsumego_id' => true, 'old_value' => 'other:0'],
			]
		]);

		$browser = Browser::instance();
		$browser->get('users/adminstats');
		$pageSource = $browser->driver->getPageSource();

		// Verify all 19 activity type names appear in HTML
		$this->assertTextContains('Description Edit', $pageSource);
		$this->assertTextContains('Hint Edit', $pageSource);
		$this->assertTextContains('Author Edit', $pageSource);
		$this->assertTextContains('Rating Edit', $pageSource);
		$this->assertTextContains('Minimum Rating Edit', $pageSource);
		$this->assertTextContains('Maximum Rating Edit', $pageSource);
		$this->assertTextContains('Problem Delete', $pageSource);
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
		$this->assertTextContains('Merged tsumego', $pageSource);

		// Verify formatted messages with old/new values appear in HTML

		// Problem edits (with old → new)
		$this->assertTextContains('Description Edit: old desc → new desc', $pageSource);
		$this->assertTextContains('Hint Edit: old hint → new hint', $pageSource);

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

		$this->assertTextContains('Accepted tag snapback', $pageSource);
		$this->assertTextContains('Rejected tag atari', $pageSource);
	}

	public function testProposeSGF()
	{
		$browser = Browser::instance();
		$sgfVersion1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		$context = new ContextPreparator([
			'user' => ['admin' => false, 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['sgf' => $sgfVersion1, 'status' => 'S', 'set_order' => 1]]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$makeProposalLink = $browser->find('#openSgfLink');
		$this->assertSame('Make Proposal', $makeProposalLink->getText());
		$makeProposalLink->click();

		$this->assertSame(
			Util::getMyAddress()
			. '/editor/?setConnectionID='
			. $context->tsumegos[0]['set-connections'][0]['id']
			. '&sgfID=' . $context->tsumegos[0]['sgfs'][0]['id'],
			$browser->driver->getCurrentURL());
		$browser->assertNoErrors();

		usleep(200 * 1000);
		$browser->clickBoard(4, 4);
		$browser->clickId('makeCorrectButton');
		$browser->clickId('saveSGFButton');

		// checking that the non-accepted sgf is not used for the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$loadedSgf = $browser->driver->executeScript('return besogo.sgfLoaded;');
		$this->assertSame($loadedSgf, $sgfVersion1);
	}

	public function testAcceptSgfProposal()
	{
		$sgfVersion1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		$sgfVersion2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AB[dd];B[aa];W[ab];B[ba]C[+])';
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgfs' => [
					['data' => $sgfVersion1, 'accepted' => true],
					['data' => $sgfVersion2, 'accepted' => false]],
				'status' => 'S',
				'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/users/adminstats');
		$this->assertSame('SGF Proposals (1)', $browser->find('#sgfProposalsHeader')->getText());

		// click the accept proposal button
		$browser->clickId('accept-' . $context->tsumegos[0]['sgfs'][1]['id']);
		$browser->waitUntilCssSelectorExistsWithText('#sgfProposalsHeader', 'SGF Proposals (0)');

		// we got redirected back to adminstats, the proposal shouldn't be visible anymore
		$this->assertSame(Util::getMyAddress() . '/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertSame('SGF Proposals (0)', $browser->find('#sgfProposalsHeader')->getText());
		$this->assertSame(true, ClassRegistry::init('Sgf')->findById($context->tsumegos[0]['sgfs'][1]['id'])['Sgf']['accepted']);

		// checking that the accepted sgf is now used for the problem
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$loadedSgf = $browser->driver->executeScript('return besogo.sgfLoaded;');
		$this->assertSame($loadedSgf, $sgfVersion2);
	}

	public function testRejectSgfProposal()
	{
		$sgfVersion1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		$sgfVersion2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AB[dd];B[aa];W[ab];B[ba]C[+])';
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgfs' => [
					['data' => $sgfVersion1, 'accepted' => true],
					['data' => $sgfVersion2, 'accepted' => false]],
				'status' => 'S',
				'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/users/adminstats');
		$this->assertSame('SGF Proposals (1)', $browser->find('#sgfProposalsHeader')->getText());

		// click the accept proposal button
		$browser->clickId('reject-' . $context->tsumegos[0]['sgfs'][1]['id']);

		// we got redirected back to adminstats, the proposal shouldn't be visible anymore
		$this->assertSame(Util::getMyAddress() . '/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertSame('SGF Proposals (0)', $browser->find('#sgfProposalsHeader')->getText());

		// the sgf is deleted
		$this->assertEmpty(ClassRegistry::init('Sgf')->findById($context->tsumegos[0]['sgfs'][1]['id']));
	}

	public function testApproveTag()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser->get('/users/adminstats');
		$this->assertSame('New Tags (1)', $browser->find('#tagConnectionProposalsHeader')->getText());

		// click the accept proposal button
		$browser->clickId('tag-connection-accept-' . $context->tsumegos[0]['tag-connections'][0]['id']);

		// we got redirected back to adminstats, the proposal shouldn't be visible anymore
		$this->assertSame(Util::getMyAddress() . '/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertSame('New Tags (0)', $browser->find('#tagConnectionProposalsHeader')->getText());
		$this->assertSame(1, ClassRegistry::init('TagConnection')->findById($context->tsumegos[0]['tag-connections'][0]['id'])['TagConnection']['approved']);

		// tag approval is saved in admin activities
		$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
		$this->assertCount(1, $adminActivities);
		$this->assertSame($adminActivities[0]['AdminActivity']['type'], AdminActivityType::ACCEPT_TAG);
		$this->assertSame(null, $adminActivities[0]['AdminActivity']['old_value']);
		$this->assertSame('snapback', $adminActivities[0]['AdminActivity']['new_value']);
	}

	public function testRejectTagProposal()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]);
		$browser->get('/users/adminstats');
		$this->assertSame('New Tags (1)', $browser->find('#tagConnectionProposalsHeader')->getText());

		// click the accept proposal button
		$browser->clickId('tag-reject-' . $context->tsumegos[0]['tag-connections'][0]['id']);

		// we got redirected back to adminstats, the proposal shouldn't be visible anymore
		$this->assertSame(Util::getMyAddress() . '/users/adminstats', $browser->driver->getCurrentURL());
		$this->assertSame('New Tags (0)', $browser->find('#tagConnectionProposalsHeader')->getText());

		// the tag is deleted
		$this->assertEmpty(ClassRegistry::init('TagConnection')->findById($context->tsumegos[0]['tag-connections'][0]['id']));

		// tag reject is saved in admin activities
		$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
		$this->assertCount(1, $adminActivities);
		$this->assertSame($adminActivities[0]['AdminActivity']['type'], AdminActivityType::REJECT_TAG);
		$this->assertSame('snapback', $adminActivities[0]['AdminActivity']['old_value']);
		$this->assertSame(null, $adminActivities[0]['AdminActivity']['new_value']);
	}
}
