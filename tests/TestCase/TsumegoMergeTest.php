<?php

class TsumegoMergeTest extends ControllerTestCase
{
	public function testMergeTwoTsumegos()
	{
		foreach (['notAdmin', 'masterNotSpecified', 'slaveNotSpecified', 'slaveAndMasterAlreadyMarged', 'merge', 'mergeWithDoubleFavorite'] as $testCase)
		{
			$browser = Browser::instance();
			$version1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
			$version2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[be]C[+])';
			$context = new ContextPreparator([
				'user' => ['admin' => ($testCase != 'notAdmin')],
				'time-mode-ranks' => ['5k'],
				'other-tsumegos' =>	[
					[
						'status' => 'V',
						'rating' => 850,
						'sgf' => $version1,
						'description' => 'Master tsumego',
						'sets' => [
							['name' => 'set 1', 'num' => '1'],
							['name' => 'set 3', 'num' => '1']],
						'attempts' => [['solved' => 0, 'seconds' => 5, 'gain' => 5]],
						'comments' => [['message' => 'master comment']],
						'tags' => [['name' => 'atari'], ['name' => 'tenuki']]
					],
					[
						'status' => 'S',
						'rating' => 100,
						'sgf' => $version2,
						'description' => 'Slave tsumego',
						'sets' => [['name' => 'set 2', 'num' => '1']],
						'attempts' => [['solved' => 1, 'seconds' => 5, 'gain' => 10]],
						'comments' => [['message' => 'slave comment']],
						'tags' => [['name' => 'snapback'], ['name' => 'atari']]
					]],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'tsumego_id' => 'other:1']]]]]);

			if ($testCase == 'mergeWithDoubleFavorite')
				$context->addFavorite($context->otherTsumegos[0]);
			$context->addFavorite($context->otherTsumegos[1]);

			$browser->get('/tsumegos/mergeForm');
			if ($testCase == 'notAdmin')
			{
				$this->assertSame(Util::getMyAddress() . '/', $browser->driver->getCurrentURL());
				continue;
			}
			if ($testCase != 'masterNotSpecified')
			{
				$browser->clickId('master-id');
				$browser->driver->getKeyboard()->sendKeys($context->otherTsumegos[0]['set-connections'][0]['id']);
			}
			if ($testCase != 'slaveNotSpecified')
			{
				$browser->clickId('slave-id');
				if ($testCase != 'slaveAndMasterAlreadyMarged')
					$browser->driver->getKeyboard()->sendKeys($context->otherTsumegos[1]['set-connections'][0]['id']);
				else
					$browser->driver->getKeyboard()->sendKeys($context->otherTsumegos[0]['set-connections'][1]['id']);
			}
			$browser->clickId('submit');
			if ($testCase == 'masterNotSpecified')
			{
				$this->assertTextContains('Master set connection does not exist.', $browser->driver->getPageSource());
				continue;
			}

			if ($testCase == 'slaveNotSpecified')
			{
				$this->assertTextContains('Slave set connection does not exist.', $browser->driver->getPageSource());
				continue;
			}

			if ($testCase == 'slaveAndMasterAlreadyMarged')
			{
				$this->assertTextContains('These are already merged.', $browser->driver->getPageSource());
				continue;
			}
			$this->assertSame(Util::getMyAddress() . '/tsumegos/mergeFinalForm', $browser->driver->getCurrentURL());
			$browser->clickId('submit');

			// the tsumegos were merged
			$this->assertSame($context->otherTsumegos[0]['set-connections'][0]['tsumego_id'], $context->otherTsumegos[0]['set-connections'][1]['tsumego_id']);
			$masterStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->otherTsumegos[0]['id']]]);

			// slave status is better than master, so it was merged
			$this->assertSame('S', $masterStatus['TsumegoStatus']['status']);

			// slave tsumego got actually deleted
			$this->assertSame(1, ClassRegistry::init('Tsumego')->find('count'));

			// tsumego attempts got merged
			$this->assertSame(2, ClassRegistry::init('TsumegoAttempt')->find('count'));

			// tsumego comments got merged
			$comments = ClassRegistry::init('TsumegoComment')->find('all');
			$this->assertSame(2, count($comments));
			$this->assertSame($comments[0]['TsumegoComment']['message'], 'master comment');
			$this->assertSame($comments[1]['TsumegoComment']['message'], 'slave comment');

			// favorites got merged
			$favorites = ClassRegistry::init('Favorite')->find('all');
			$this->assertSame(1, count($favorites));
			$this->assertSame($favorites[0]['Favorite']['tsumego_id'], $context->otherTsumegos[0]['id']);

			// tags got merged
			$tagConnections = ClassRegistry::init('TagConnection')->find('all');
			$this->assertSame(3, count($tagConnections));

			// time mode attempts got merged
			$timeModeAttempts = ClassRegistry::init('TimeModeAttempt')->find('all');
			$this->assertSame(1, count($timeModeAttempts));
			$this->assertSame($context->otherTsumegos[0]['id'], $timeModeAttempts[0]['TimeModeAttempt']['tsumego_id']);
		}
	}
}
