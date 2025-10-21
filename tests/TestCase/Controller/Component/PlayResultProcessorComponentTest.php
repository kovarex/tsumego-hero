<?php

class PlayResultProcessorComponentTest extends ControllerTestCase
{
  public function testResultProcessor(): void
  {
    foreach (['nothing', 'misplay', 'solved'] as $testCase)
    {
      $user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
      $tsumego = ClassRegistry::init('Tsumego')->find('first');
      $statusCondition = ['conditions' => ['user_id' => $user['User']['id'],
                                          ['tsumego_id' => $tsumego['Tsumego']['id']]]];
      $originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
      if ($originalTsumegoStatus)
        ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);

      CakeSession::write('loggedInUser.User.id', $user['User']['id']);
      $_COOKIE['previousTsumegoID'] =$tsumego['Tsumego']['id'];
      if ($testCase == 'misplay')
        $_COOKIE['misplay'] = '1';
      elseif ($testCase == 'solved')
      {
        $_COOKIE['mode'] = '1';
        $_COOKIE['score'] = '1';
      }

      $this->testAction('sets/view/');

      $tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
      $this->assertTrue(!empty($tsumegoStatus));
      $this->assertSame($tsumegoStatus['TsumegoStatus']['user_id'], $user['User']['id']);
      $this->assertSame($tsumegoStatus['TsumegoStatus']['tsumego_id'], $tsumego['Tsumego']['id']);

      $this->assertTrue(empty($_COOKIE['misplay'])); // should be processed and cleared
      $this->assertTrue(empty($_COOKIE['score'])); // should be processed and cleared

      if ($testCase == 'misplay')
        $expectedStatus = 'F';
      elseif ($testCase == 'solved')
        $expectedStatus = 'S';
      elseif ($testCase == 'nothing')
        $expectedStatus = 'V';
      $this->assertSame($tsumegoStatus['TsumegoStatus']['status'], $expectedStatus);
    }
  }
}
