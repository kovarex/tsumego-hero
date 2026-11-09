<?php

use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\Rating;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\DataProvider;

#[RetryOnBrowserFailure]
class EditTsumegoTest extends IntegrationTestCase
{
	public static function editTsumegoProvider(): array
	{
		return [
			'description' => [['field' => 'description', 'value' => 'bar', 'result' => 'bar']],
			'hint' => [['field' => 'hint', 'value' => 'bar', 'result' => 'bar']],
			'author' => [['field' => 'author', 'value' => 'bar', 'result' => 'bar']],
			'rating-numeric' => [['field' => 'rating', 'value' => '1968', 'result' => 1968.0]],
			'rating-rank' => [['field' => 'rating', 'value' => '1d', 'result' => 2100.]],
			'rating-invalid' => [['field' => 'rating', 'value' => 'hello', 'result' => 666.0]],
			'min-rating-numeric' => [['field' => 'minimum-rating', 'value' => '1000', 'result' => 1000.0]],
			'min-rating-rank' => [['field' => 'minimum-rating', 'value' => '10k', 'result' => 1100.0]],
			'min-rating-invalid' => [['field' => 'minimum-rating', 'value' => 'hello', 'result' => null]],
			'max-rating-numeric' => [['field' => 'maximum-rating', 'value' => '1234', 'result' => 1234.0]],
			'max-rating-invalid' => [['field' => 'maximum-rating', 'value' => 'hello', 'result' => null]],
			'max-rating-rank' => [['field' => 'maximum-rating', 'value' => '2d', 'result' => 2200.0]],
			'min-bigger-than-max' => [['field' => 'maximum-rating', 'value' => '10k', 'field2' => 'minimum-rating', 'value2' => '5k', 'result' => null]],
			'delete-invalid' => [['field' => 'delete', 'value' => 'bla', 'result' => true, 'public' => 0]],
			'delete-confirm' => [['field' => 'delete', 'value' => 'delete', 'result' => true, 'public' => 0]],
			'delete-published' => [['field' => 'delete', 'value' => 'delete', 'result' => true, 'public' => 1]],
			'non-admin' => [['field' => 'description', 'value' => 'bar', 'result' => 'foo', 'admin' => false]],
			'invalid-tsumego' => [['field' => 'description', 'value' => 'bar', 'result' => 'foo', 'invalid-tsumego' => true]],
		];
	}

	private function postEdit(ContextPreparator $context, array $fields): void
	{
		$this->post('/tsumegos/edit/' . $context->tsumegos[0]['id'], $fields + [
			'description' => 'foo', 'hint' => 'think', 'author' => 'Ivan Detkov', 'rating' => '666', 'redirect' => '/sets']);
	}

	private function prepareProblem(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]], 'description' => 'foo', 'hint' => 'think', 'author' => 'Ivan Detkov', 'rating' => 666]]]);
	}

	public function testAWrongRatingIsNamedAfterItsFieldAndNothingIsSaved(): void
	{
		foreach (['rating' => 'Rating', 'minimum-rating' => 'Minimum rating', 'maximum-rating' => 'Maximum rating'] as $field => $label)
		{
			$context = $this->prepareProblem();
			$this->postEdit($context, [$field => 'hello', 'description' => 'changed']);

			$this->assertSame('error', CookieFlash::getType());
			$this->assertStringStartsWith($label . ' parse error:', (string) CookieFlash::get());
			$this->assertSame('foo', $this->fetchTable('Tsumego')->get($context->tsumegos[0]['id'])->description);
		}
	}

	//Each field the form changed is noted with its old and new value, the ones it kept are not.
	public function testTheChangedFieldsAreNotedInTheAdminActivity(): void
	{
		$context = $this->prepareProblem();

		$this->postEdit($context, ['description' => 'bar', 'author' => 'Someone', 'minimum-rating' => '1000']);

		$activities = array_map(fn($activity) => [$activity->type, $activity->old_value, $activity->new_value],
			$this->fetchTable('AdminActivity')->find()->orderBy('id')->toArray());
		$this->assertSame([
			[AdminActivityTypeTable::DESCRIPTION_EDIT, 'foo', 'bar'],
			[AdminActivityTypeTable::AUTHOR_EDIT, 'Ivan Detkov', 'Someone'],
			[AdminActivityTypeTable::MINIMUM_RATING_EDIT, null, '1000'],
		], $activities);
		$tsumego = $this->fetchTable('Tsumego')->get($context->tsumegos[0]['id']);
		$this->assertSame(1000.0, $tsumego->rating, 'the rating is raised to the minimum');
	}

	#[DataProvider('editTsumegoProvider')]
	public function testEditTsumego(array $testCase)
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1, 'public' => ($testCase['public'] ?? null) !== null ? $testCase['public'] : 1]],
				'description' => 'foo',
				'hint' => 'think',
				'author' => 'Ivan Detkov',
				'rating' => 666]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->openPlayEditSection();

		$browser->clickCssSelect("#" . $testCase['field']);
		$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
		$browser->driver->getKeyboard()->sendKeys($testCase['value']);

		if ($testCase['field2'] ?? null)
		{
			$browser->clickCssSelect("#" . $testCase['field2']);
			$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
			$browser->driver->getKeyboard()->sendKeys($testCase['value2']);
		}

		if ($testCase['invalid-tsumego'] ?? false)
			$this->fetchTable('Tsumego')->deleteAll(['id' => $context->tsumegos[0]['id']]);

		if (($testCase['admin'] ?? null) === false)
			Auth::saveUserField('user_role_id', UserTable::ROLE_PLAYER);

		$browser->clickId("tsumego-edit-submit");

		if ($testCase['invalid-tsumego'] ?? false)
		{
			$this->assertTextContains('doesn\'t exist', $browser->driver->getPageSource());
			return;
		}

		$tsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $context->tsumegos[0]['id']])->first();

		if ($testCase['field'] == 'delete')
		{
			$adminActivities = $this->fetchTable('AdminActivity')->find()->orderBy('id')->toArray();
			if ($testCase['value'] == 'delete')
			{
				$this->assertCount(1, $adminActivities);
				$this->assertSame(AdminActivityTypeTable::PROBLEM_DELETE, $adminActivities[0]->type);
				$this->assertNotNull($tsumego->deleted);
			}
			else
			{
				$this->assertCount(0, $adminActivities);
				$this->assertNull($tsumego->deleted);
			}
			return;
		}

		$this->assertSame($testCase['field'] == 'description' ? $testCase['result'] : 'foo', $tsumego->description);
		$this->assertSame($testCase['field'] == 'hint' ? $testCase['result'] : 'think', $tsumego->hint);
		$this->assertSame($testCase['field'] == 'author' ? $testCase['result'] : 'Ivan Detkov', $tsumego->author);
		$expectedRating = 666.0;
		if ($testCase['field'] == 'minimum-rating'
			&& !is_null($tsumego->minimum_rating)
			&& $tsumego->minimum_rating > 666.0)
				$expectedRating = $tsumego->minimum_rating;
		if ($testCase['field'] == 'maximum-rating'
			&& !is_null($tsumego->maximum_rating)
			&& $tsumego->maximum_rating < 666.0)
				$expectedRating = $tsumego->maximum_rating;
		$this->assertSame($testCase['field'] == 'rating' ? $testCase['result'] : $expectedRating, $tsumego->rating);
		$this->assertSame($testCase['field'] == 'minimum-rating' ? $testCase['result'] : null, $tsumego->minimum_rating);
		$this->assertSame($testCase['field'] == 'maximum-rating' ? $testCase['result'] : null, $tsumego->maximum_rating);

		$adminActivities = $this->fetchTable('AdminActivity')->find()->orderBy('id')->toArray();
		if ($testCase['result'] == $testCase['value']) // when we expect it to succeed
		{$this->assertSame($testCase['value'], $adminActivities[0]->new_value);
			$this->assertCount(1, $adminActivities);
			if ($testCase['field'] == 'description')
			{
				$this->assertSame(AdminActivityTypeTable::DESCRIPTION_EDIT, $adminActivities[0]->type);
				$this->assertSame("foo", $adminActivities[0]->old_value);
			}
			if ($testCase['field'] == 'hint')
			{
				$this->assertSame(AdminActivityTypeTable::HINT_EDIT, $adminActivities[0]->type);
				$this->assertSame("think", $adminActivities[0]->old_value);
			}
			elseif ($testCase['field'] == 'author')
			{
				$this->assertSame(AdminActivityTypeTable::AUTHOR_EDIT, $adminActivities[0]->type);
				$this->assertSame("Ivan Detkov", $adminActivities[0]->old_value);
			}
			elseif ($testCase['field'] == 'rating')
			{
				$this->assertSame(AdminActivityTypeTable::RATING_EDIT, $adminActivities[0]->type);
				$this->assertSame("666", $adminActivities[0]->old_value);
			}
			elseif ($testCase['field'] == 'minimum-rating')
			{
				$this->assertSame(AdminActivityTypeTable::MINIMUM_RATING_EDIT, $adminActivities[0]->type);
				$this->assertSame(null, $adminActivities[0]->old_value);
			}
			elseif ($testCase['field'] == 'maximum-rating')
			{
				$this->assertSame(AdminActivityTypeTable::MAXIMUM_RATING_EDIT, $adminActivities[0]->type);
				$this->assertSame(null, $adminActivities[0]->old_value);
			}
		}
	}

	public function testInitialValuesForRatingShowsRanksWhenPossible()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'description' => 'foo',
				'hint' => 'think',
				'author' => 'Ivan Detkov',
				'rating' => Rating::getRankMiddleRatingFromReadableRank("10k"),
				'minimum_rating' => Rating::getRankMiddleRatingFromReadableRank("5k"),
				'maximum_rating' => Rating::getRankMiddleRatingFromReadableRank("15k")]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->openPlayEditSection();
		$this->assertSame($browser->getElements("#rating")[0]->getAttribute("value"), "10k");
		$this->assertSame($browser->getElements("#minimum-rating")[0]->getAttribute("value"), "5k");
		$this->assertSame($browser->getElements("#maximum-rating")[0]->getAttribute("value"), "15k");
	}

	//The admin's goal field shows the goal in the colors the player sees and follows the color button, its placeholder
	//(the default goal) too; saving stores the true colors, by the colors seen when the form is sent
	public function testTheGoalFieldFollowsTheColorButtonAndSavesTheTrueColors(): void
	{
		$sgf = ['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])', 'first_move_color' => 'B'];
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL],
			'tsumegos' => [['set_order' => 1, 'description' => 'Black to kill', 'sgf' => $sgf], ['set_order' => 2, 'description' => '', 'sgf' => $sgf]]]);
		$browser = Browser::instance();
		$field = fn() => $browser->driver->executeScript('const field = document.getElementById("description");
			return [field.value, field.placeholder, field.form.elements.color_swapped.value, document.getElementById("description-note").textContent];');

		// the second problem has the default goal: its placeholder follows the colors
		$browser->get($context->tsumegos[1]['set-connections'][0]['id'] . '?playercolor=white');
		$this->assertSame(['', 'White to play', '1', 'Describe the problem. White is to move.'], $field());
		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertSame(['', 'Black to play', '0', 'Describe the problem. Black is to move.'], $field());

		// the first one, opened in swapped colors, swapped back and forth, and saved in the colors it was opened in
		$browser->get($context->tsumegos[0]['set-connections'][0]['id'] . '?playercolor=white');
		$browser->openPlayEditSection();
		$this->assertSame('White to kill', $field()[0]);
		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertSame(['Black to kill', '0'], [$field()[0], $field()[2]]);
		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertSame(['White to kill', '1'], [$field()[0], $field()[2]]);
		$browser->clickCssSelect('#description');
		$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
		$browser->driver->getKeyboard()->sendKeys('White to live');
		$browser->clickId('tsumego-edit-submit');
		$this->assertSame('Black to live', $this->fetchTable('Tsumego')->get($context->tsumegos[0]['id'])->description);

		// saved after one swap, in the true colors seen then
		$browser->get($context->tsumegos[0]['set-connections'][0]['id'] . '?playercolor=white');
		$browser->openPlayEditSection();
		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertSame('Black to live', $field()[0]);
		$browser->clickCssSelect('#description');
		$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
		$browser->driver->getKeyboard()->sendKeys('Black to kill');
		$browser->clickId('tsumego-edit-submit');
		$this->assertSame('Black to kill', $this->fetchTable('Tsumego')->get($context->tsumegos[0]['id'])->description);
		$browser->assertNoJsErrors();
	}
}
