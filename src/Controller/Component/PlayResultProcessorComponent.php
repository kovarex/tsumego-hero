<?php
App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');

class PlayResultProcessorComponent extends Component
{
  public $components = ['Session'];

  function checkPreviousPlay($appController, &$loggedInUserFromDatabase, &$previousTsumego): void
  {
    if (!$previousTsumego)
      return;
    $result = $this->checkPreviousPlayAndGetResult($appController, $loggedInUserFromDatabase, $previousTsumego);
    $this->updateTsumegoStatus($loggedInUserFromDatabase, $previousTsumego, $result);
    $this->processEloChange($appController, $loggedInUserFromDatabase, $previousTsumego, $result);
  }

  public function checkPreviousPlayAndGetResult($appController, &$loggedInUserFromDatabase, &$previousTsumego): string
  {
    if ($this->checkMisplay())
      return 'l';
    if ($this->checkCorrectPlay($appController, $loggedInUserFromDatabase, $previousTsumego))
      return 'w';
    return '';
  }

  private function updateTsumegoStatus($loggedInUserFromDatabase, $previousTsumego, $result): void
  {
    $tsumegoStatusModel = ClassRegistry::init('TsumegoStatus');
    $previousTsumegoStatus = $tsumegoStatusModel->find('first', ['order' => 'created DESC',
                                                                 'conditions' => ['tsumego_id' => (int)$previousTsumego['Tsumego']['id'],
                                                                                  'user_id' => (int)$loggedInUserFromDatabase['User']['id']]]);
    if ($previousTsumegoStatus == null)
    {
      $previousTsumegoStatus['TsumegoStatus'] = [];
      $previousTsumegoStatus['TsumegoStatus']['user_id'] = $loggedInUserFromDatabase['User']['id'];
      $previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
      $previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
    }
    $_COOKIE['previousTsumegoBuffer'] = $previousTsumegoStatus['TsumegoStatus']['status'];

    if ($result == 'w')
    {
      if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'W') // half xp state
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'C'; // double solved
      else
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'S'; // solved once
    }
    else if ($result == 'l')
    {
      if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'F') // failed already
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'X'; // double failed
      else if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'V') // if it was just visited so far (so we don't overwrite solved
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'F'; // set to failed
    }

    $previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
    $tsumegoStatusModel->save($previousTsumegoStatus);
  }

  private function processEloChange($appController, $loggedInUserFromDatabase, $previousTsumego, $result): void
  {
    if ($result == '')
      return;

    $userRating = (float) $this->Session->read('loggedInUser.User.elo_rating_mode');
    $tsumegoRating = (float) $previousTsumego['Tsumego']['elo_rating_mode'];
    $eloDifference = abs($userRating - $tsumegoRating);
    if ($userRating > $tsumegoRating)
      $eloBigger = 'u';
    else
      $eloBigger = 't';
    if (!empty($_COOKIE['av']))
    {
      $activityValue = $_COOKIE['av'];
      unset($_COOKIE['av']);
      setcookie('av', false);
    }
    else
      $activityValue = 1;
    $newUserElo = $appController->getNewElo($eloDifference, $eloBigger, $activityValue, $previousTsumego['Tsumego']['id'], $result);
    $newEloRating = $userRating + $newUserElo['user'];
    $this->Session->write('loggedInUser.User.elo_rating_mode', $newEloRating);
    $loggedInUserFromDatabase['User']['elo_rating_mode'] = $newEloRating;

    $userModel = ClassRegistry::init('User');
    $userModel->id = $loggedInUserFromDatabase['User']['id'];
    $userModel->saveField('elo_rating_mode', $newEloRating);

    $previousTsumego['Tsumego']['elo_rating_mode'] += $newUserElo['tsumego'];
    $previousTsumego['Tsumego']['activity_value']++;
    $previousTsumego['Tsumego']['difficulty'] = $appController->convertEloToXp($previousTsumego['Tsumego']['elo_rating_mode']);
    if ($previousTsumego['Tsumego']['elo_rating_mode'] > 100)
      ClassRegistry::init('Tsumego')->save($previousTsumego);
  }

  private function checkMisplay(): bool
  {
    if (empty($_COOKIE['misplay']))
      return false;
    Util::clearCookie('misplay');
    return true;
  }

  private function checkCorrectPlay($appController, &$loggedInUserFromDatabase, &$previousTsumego): bool
  {
    if (empty($_COOKIE['mode']))
      return false;
    if (empty($_COOKIE['score']))
      return false;

    if (($_COOKIE['mode'] == '1' || $_COOKIE['mode'] == '2') && $_COOKIE['score'] != '0')
    {
      $suspiciousBehavior = false;
      $exploit = null;
      $_COOKIE['score'] = $appController->decrypt($_COOKIE['score']);
      $scoreArr = explode('-', $_COOKIE['score']);
      $isNum = $previousTsumego['Tsumego']['num'] == $scoreArr[0];
      $isSet = true;
      $isNumSc = false;
      $isSetSc = false;

      $preSc = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => (int)$previousTsumego['Tsumego']['id']]]);
      if (!$preSc)
        $preSc = [];
      $preScCount = count($preSc);
      for ($i = 0;$i < $preScCount;$i++)
      {
        if ($preSc[$i]['SetConnection']['set_id'] == $previousTsumego['Tsumego']['set_id'])
          $isSetSc = true;
        if ($preSc[$i]['SetConnection']['num'] == $previousTsumego['Tsumego']['num'])
          $isNumSc = true;
      }
      $isNum = $isNumSc;
      $isSet = $isSetSc;
      $_COOKIE['score'] = $scoreArr[1];
      $solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['elo_rating_mode']);

      if ($isNum && $isSet)
      {
        if (isset($_COOKIE['previousTsumegoID']))
          $resetCookies = true;

        if (!$this->Session->check('noLogin'))
        {
          $ub = [];
          $ub['UserBoard']['user_id'] = $loggedInUserFromDatabase['User']['id'];
          $ub['UserBoard']['b1'] = (int)$_COOKIE['previousTsumegoID'];
          $appController->UserBoard->create();
          $appController->UserBoard->save($ub);
          if ($_COOKIE['score'] >= 3000)
          {
            $_COOKIE['score'] = 0;
            $suspiciousBehavior = true;
          }
          if ($loggedInUserFromDatabase['User']['reuse3'] > 12000)
          {
            $this->Session->write('loggedInUser.User.reuse4', 1);
            $loggedInUserFromDatabase['User']['reuse4'] = 1;
          }
        }
        if (!$suspiciousBehavior)
        {
          $xpOld = $loggedInUserFromDatabase['User']['xp'] + (int)($_COOKIE['score']);
          $loggedInUserFromDatabase['User']['reuse2']++;
          $loggedInUserFromDatabase['User']['reuse3'] += (int)($_COOKIE['score']);
          if ($xpOld >= $loggedInUserFromDatabase['User']['nextlvl'])
          {
            $xpOnNewLvl = -1 * ($loggedInUserFromDatabase['User']['nextlvl'] - $xpOld);
            $loggedInUserFromDatabase['User']['xp'] = $xpOnNewLvl;
            $loggedInUserFromDatabase['User']['level'] += 1;
            $loggedInUserFromDatabase['User']['nextlvl'] += $appController->getXPJump($loggedInUserFromDatabase['User']['level']);
            $loggedInUserFromDatabase['User']['health'] = $appController->getHealth($loggedInUserFromDatabase['User']['level']);
            $this->Session->write('loggedInUser.User.level', $loggedInUserFromDatabase['User']['level']);
          }
          else
          {
            $loggedInUserFromDatabase['User']['xp'] = $xpOld;
            $loggedInUserFromDatabase['User']['ip'] = $_SERVER['REMOTE_ADDR'];
          }
          if ($loggedInUserFromDatabase['User']['id'] != 33) // noUser
          {
            if (!isset($_COOKIE['seconds']))
              $cookieSeconds = 0;
            else
              $cookieSeconds = $_COOKIE['seconds'];

            $tsumegoAttempt = [];
            $tsumegoAttempt['TsumegoAttempt']['user_id'] = $loggedInUserFromDatabase['User']['id'];
            $tsumegoAttempt['TsumegoAttempt']['elo'] = $this->Session->read('loggedInUser.User.elo_rating_mode');
            $tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
            $tsumegoAttempt['TsumegoAttempt']['gain'] = $_COOKIE['score'];
            $tsumegoAttempt['TsumegoAttempt']['seconds'] = $cookieSeconds;
            $tsumegoAttempt['TsumegoAttempt']['solved'] = '1';
            $tsumegoAttempt['TsumegoAttempt']['mode'] = 1;
            $tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['elo_rating_mode'];
            ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
            $correctSolveAttempt = true;

            $appController->saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
            $appController->updateGems($solvedTsumegoRank);
            if ($_COOKIE['sprint'] == 1)
              $appController->updateSprintCondition(true);
            else
              $appController->updateSprintCondition();
            if ($_COOKIE['type'] == 'g')
              $appController->updateGoldenCondition(true);
            $aCondition = $appController->AchievementCondition->find('first', [
              'order' => 'value DESC',
              'conditions' => [
                'user_id' => $loggedInUserFromDatabase['User']['id'],
                'category' => 'err',
              ],
            ]);
            if ($aCondition == null)
              $aCondition = [];
            $aCondition['AchievementCondition']['category'] = 'err';
            $aCondition['AchievementCondition']['user_id'] = $loggedInUserFromDatabase['User']['id'];
            $aCondition['AchievementCondition']['value']++;
            $appController->AchievementCondition->save($aCondition);
          }
          if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0')
          {
            $ranks = $appController->Rank->find('all', ['conditions' => ['session' => $this->Session->read('loggedInUser.User.activeRank')]]);
            if (!$ranks)
              $ranks = [];
            $currentNum = $ranks[0]['Rank']['currentNum'];
            $ranksCount = count($ranks);
            for ($i = 0; $i < $ranksCount; $i++)
              if ($ranks[$i]['Rank']['num'] == $currentNum - 1)
              {
                if ($_COOKIE['rank'] != 'solved' && $_COOKIE['rank'] != 'failed' && $_COOKIE['rank'] != 'skipped' && $_COOKIE['rank'] != 'timeout')
                  $_COOKIE['rank'] = 'failed';
                $ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
                $ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds'] / 10;
                $appController->Rank->save($ranks[$i]);
              }
          }
        }
      }
      else
        $loggedInUserFromDatabase['User']['penalty'] += 1;

      Util::clearCookie('score');
      Util::clearCookie('sequence');
      Util::clearCookie('type');
    }
    return true;
  }
}
