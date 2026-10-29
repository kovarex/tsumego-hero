<?php

use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Rating;
use Facebook\WebDriver\WebDriverKeys;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
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

	/** @dataProvider editTsumegoProvider */
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
		$browser->clickId("modify-description");

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
		$browser->clickId("modify-description");
		$this->assertSame($browser->getElements("#rating")[0]->getAttribute("value"), "10k");
		$this->assertSame($browser->getElements("#minimum-rating")[0]->getAttribute("value"), "5k");
		$this->assertSame($browser->getElements("#maximum-rating")[0]->getAttribute("value"), "15k");
	}
}
