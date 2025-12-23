<?php

use Facebook\WebDriver\WebDriverKeys;

require_once __DIR__ . '/../../EditTestCase.php';

class EditTsumegoTest extends ControllerTestCase
{
	public function testEditTsumego()
	{
		$testCases = [];
		$testCases[] = new EditTestCase('description', 'bar', 'bar');
		$testCases[] = new EditTestCase('hint', 'bar', 'bar');
		$testCases[] = new EditTestCase('author', 'bar', 'bar');
		$testCases[] = new EditTestCase('rating', '1968', 1968.0); // normal rating change
		$testCases[] = new EditTestCase('rating', '1d', 2100.); // 1d translated to 2100 rating (center of 1d)
		$testCases[] = new EditTestCase('rating', 'hello', 666.0); // invalid rating, nothing changes
		$testCases[] = new EditTestCase('minimum-rating', '1000', 1000.0);
		$testCases[] = new EditTestCase('minimum-rating', '10k', 1100.0);
		$testCases[] = new EditTestCase('minimum-rating', 'hello', null);
		$testCases[] = new EditTestCase('maximum-rating', '1234', 1234.0);
		$testCases[] = new EditTestCase('maximum-rating', 'hello', null);
		$testCases[] = new EditTestCase('maximum-rating', '2d', 2200.0);

		// test of trying to set minimum bigger than maximum
		$testCases[] = new EditTestCase('maximum-rating', '10k', null, field2: 'minimum-rating', value2: '5k');

		$testCases[] = new EditTestCase('delete', 'bla', true, public: 0);
		$testCases[] = new EditTestCase('delete', 'delete', true, public: 0);
		$testCases[] = new EditTestCase('description', 'bar', 'foo', admin: false);
		$testCases[] = new EditTestCase('description', 'bar', 'foo', invalidTsumego: true);

		foreach ($testCases as $testCase)
		{
			$context = new ContextPreparator([
				'user' => ['admin' => true],
				'tsumegos' => [[
					'sets' => [['name' => 'set-1', 'num' => 1, 'public' => $testCase->public]],
					'description' => 'foo',
					'hint' => 'think',
					'author' => 'Ivan Detkov',
					'rating' => 666]]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$browser->clickId("modify-description");

			$browser->clickCssSelect("#" . $testCase->field);
			$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
			$browser->driver->getKeyboard()->sendKeys($testCase->value);

			if ($testCase->field2 !== null)
			{
				$browser->clickCssSelect("#" . $testCase->field2);
				$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
				$browser->driver->getKeyboard()->sendKeys($testCase->value2);
			}

			if ($testCase->invalidTsumego)
				ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);

			if ($testCase->admin !== null && !$testCase->admin)
			{
				Auth::getUser()['isAdmin'] = false;
				Auth::saveUser();
			}

			$browser->clickId("tsumego-edit-submit");

			if ($testCase->invalidTsumego)
			{
				$this->assertTextContains('doesn\'t exist', $browser->driver->getPageSource());
				continue;
			}

			$tsumego = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);

			if ($testCase->field == 'delete')
			{
				$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
				if ($testCase->value == 'delete')
				{
					$this->assertCount(1, $adminActivities);
					$this->assertSame(AdminActivityType::PROBLEM_DELETE, $adminActivities[0]['AdminActivity']['type']);
					$this->assertNotNull($tsumego['Tsumego']['deleted']);
				}
				else
				{
					$this->assertCount(0, $adminActivities);
					$this->assertNull($tsumego['Tsumego']['deleted']);
				}
				continue;
			}

			$this->assertSame($testCase->field == 'description' ? $testCase->result : 'foo', $tsumego['Tsumego']['description']);
			$this->assertSame($testCase->field == 'hint' ? $testCase->result : 'think', $tsumego['Tsumego']['hint']);
			$this->assertSame($testCase->field == 'author' ? $testCase->result : 'Ivan Detkov', $tsumego['Tsumego']['author']);
			$expectedRating = 666.0;
			if ($testCase->field == 'minimum-rating'
				&& !is_null($tsumego['Tsumego']['minimum_rating'])
				&& $tsumego['Tsumego']['minimum_rating'] > 666.0)
					$expectedRating = $tsumego['Tsumego']['minimum_rating'];
			if ($testCase->field == 'maximum-rating'
				&& !is_null($tsumego['Tsumego']['maximum_rating'])
				&& $tsumego['Tsumego']['maximum_rating'] < 666.0)
					$expectedRating = $tsumego['Tsumego']['maximum_rating'];
			$this->assertSame($testCase->field == 'rating' ? $testCase->result : $expectedRating, $tsumego['Tsumego']['rating']);
			$this->assertSame($testCase->field == 'minimum-rating' ? $testCase->result : null, $tsumego['Tsumego']['minimum_rating']);
			$this->assertSame($testCase->field == 'maximum-rating' ? $testCase->result : null, $tsumego['Tsumego']['maximum_rating']);

			$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
			if ($testCase->result == $testCase->value) // when we expect it to succeed
			{$this->assertSame($testCase->value, $adminActivities[0]['AdminActivity']['new_value']);
				$this->assertCount(1, $adminActivities);
				if ($testCase->field == 'description')
				{
					$this->assertSame(AdminActivityType::DESCRIPTION_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("foo", $adminActivities[0]['AdminActivity']['old_value']);
				}
				if ($testCase->field == 'hint')
				{
					$this->assertSame(AdminActivityType::HINT_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("think", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase->field == 'author')
				{
					$this->assertSame(AdminActivityType::AUTHOR_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("Ivan Detkov", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase->field == 'rating')
				{
					$this->assertSame(AdminActivityType::RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("666", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase->field == 'minimum-rating')
				{
					$this->assertSame(AdminActivityType::MINIMUM_RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame(null, $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase->field == 'maximum-rating')
				{
					$this->assertSame(AdminActivityType::MAXIMUM_RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame(null, $adminActivities[0]['AdminActivity']['old_value']);
				}
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
		$this->assertSame($browser->getCssSelect("#rating")[0]->getAttribute("value"), "10k");
		$this->assertSame($browser->getCssSelect("#minimum-rating")[0]->getAttribute("value"), "5k");
		$this->assertSame($browser->getCssSelect("#maximum-rating")[0]->getAttribute("value"), "15k");
	}
}
