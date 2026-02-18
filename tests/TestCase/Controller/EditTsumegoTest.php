<?php

use Facebook\WebDriver\WebDriverKeys;

class EditTsumegoTest extends ControllerTestCase
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
			'non-admin' => [['field' => 'description', 'value' => 'bar', 'result' => 'foo', 'admin' => false]],
			'invalid-tsumego' => [['field' => 'description', 'value' => 'bar', 'result' => 'foo', 'invalid-tsumego' => true]],
		];
	}

	/**
	 * @dataProvider editTsumegoProvider
	 */
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
				ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);

			if (($testCase['admin'] ?? null) === false)
			{
				Auth::getUser()['isAdmin'] = false;
				Auth::saveUser();
			}

			$browser->clickId("tsumego-edit-submit");

			if ($testCase['invalid-tsumego'] ?? false)
			{
				$this->assertTextContains('doesn\'t exist', $browser->driver->getPageSource());
				return;
			}

			$tsumego = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);

			if ($testCase['field'] == 'delete')
			{
				$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
				if ($testCase['value'] == 'delete')
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
				return;
			}

			$this->assertSame($testCase['field'] == 'description' ? $testCase['result'] : 'foo', $tsumego['Tsumego']['description']);
			$this->assertSame($testCase['field'] == 'hint' ? $testCase['result'] : 'think', $tsumego['Tsumego']['hint']);
			$this->assertSame($testCase['field'] == 'author' ? $testCase['result'] : 'Ivan Detkov', $tsumego['Tsumego']['author']);
			$expectedRating = 666.0;
			if ($testCase['field'] == 'minimum-rating'
				&& !is_null($tsumego['Tsumego']['minimum_rating'])
				&& $tsumego['Tsumego']['minimum_rating'] > 666.0)
					$expectedRating = $tsumego['Tsumego']['minimum_rating'];
			if ($testCase['field'] == 'maximum-rating'
				&& !is_null($tsumego['Tsumego']['maximum_rating'])
				&& $tsumego['Tsumego']['maximum_rating'] < 666.0)
					$expectedRating = $tsumego['Tsumego']['maximum_rating'];
			$this->assertSame($testCase['field'] == 'rating' ? $testCase['result'] : $expectedRating, $tsumego['Tsumego']['rating']);
			$this->assertSame($testCase['field'] == 'minimum-rating' ? $testCase['result'] : null, $tsumego['Tsumego']['minimum_rating']);
			$this->assertSame($testCase['field'] == 'maximum-rating' ? $testCase['result'] : null, $tsumego['Tsumego']['maximum_rating']);

			$adminActivities = ClassRegistry::init('AdminActivity')->find('all');
			if ($testCase['result'] == $testCase['value']) // when we expect it to succeed
			{$this->assertSame($testCase['value'], $adminActivities[0]['AdminActivity']['new_value']);
				$this->assertCount(1, $adminActivities);
				if ($testCase['field'] == 'description')
				{
					$this->assertSame(AdminActivityType::DESCRIPTION_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("foo", $adminActivities[0]['AdminActivity']['old_value']);
				}
				if ($testCase['field'] == 'hint')
				{
					$this->assertSame(AdminActivityType::HINT_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("think", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase['field'] == 'author')
				{
					$this->assertSame(AdminActivityType::AUTHOR_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("Ivan Detkov", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase['field'] == 'rating')
				{
					$this->assertSame(AdminActivityType::RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame("666", $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase['field'] == 'minimum-rating')
				{
					$this->assertSame(AdminActivityType::MINIMUM_RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame(null, $adminActivities[0]['AdminActivity']['old_value']);
				}
				elseif ($testCase['field'] == 'maximum-rating')
				{
					$this->assertSame(AdminActivityType::MAXIMUM_RATING_EDIT, $adminActivities[0]['AdminActivity']['type']);
					$this->assertSame(null, $adminActivities[0]['AdminActivity']['old_value']);
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
