<?php

class TsumegoMergeTest extends ControllerTestCase
{
	public function testMergeTwoTsumegos()
	{
		foreach (['masterNotSpecified', 'slaveNotSpecified', 'slaveAndMasterAlreadyMarged'] as $testCase)
		{
			$browser = Browser::instance();
			$version1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])';
			$version2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[be]C[+])';
			$context = new ContextPreparator([
				'user' => ['admin' => true],
				'other-tsumegos' =>	[
					[
						'status' => 'V',
						'rating' => 850,
						'sgf' => $version1,
						'description' => 'Master tsumego',
						'sets' => [
							['name' => 'set 1', 'num' => '1'],
							['name' => 'set 3', 'num' => '1']]
					],
					[
						'status' => 'S',
						'rating' => 100,
						'sgf' => $version2,
						'description' => 'Slave tsumego',
						'sets' => [['name' => 'set 2', 'num' => '1']]
					]]]);
			$browser->get('/users/duplicates');
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
		}
	}
}
