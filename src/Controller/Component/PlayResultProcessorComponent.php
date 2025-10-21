<?php
App::uses('Rating', 'Utility');

class PlayResultProcessorComponent extends Component
{
  static public function checkPreviousPlay($appController, &$loggedInUserFromDatabase, &$previousTsumego): void
  {
    if (!$previousTsumego)
      return;
    $result = PlayResultProcessorComponent::checkPreviousPlayAndGetResult($appController, $loggedInUserFromDatabase, $previousTsumego);
    PlayResultProcessorComponent::updateTsumegoStatus($appController, $loggedInUserFromDatabase, $previousTsumego, $result);
    PlayResultProcessorComponent::processEloChange($appController, $loggedInUserFromDatabase, $previousTsumego, $result);
  }

  static public function checkPreviousPlayAndGetResult($appController, &$loggedInUserFromDatabase, &$previousTsumego): string
  {
    if (PlayResultProcessorComponent::checkMisplay($appController, $loggedInUserFromDatabase, $previousTsumego))
      return 'l';
    if (PlayResultProcessorComponent::checkCorrectPlay($appController, $loggedInUserFromDatabase, $previousTsumego))
      return 'w';
    return '';
  }

  static private function updateTsumegoStatus($appController, &$loggedInUserFromDatabase, &$previousTsumego, $result): void
  {
    $previousTsumegoStatus = $appController->TsumegoStatus->find('first', ['order' => 'created DESC',
                                                                           'conditions' => ['tsumego_id' => (int)$previousTsumego['Tsumego']['id'],
                                                                                            'user_id' => $loggedInUserFromDatabase['User']['id']]]);
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
    else if ($previousTsumegoStatus['TsumegoStatus']['status'] != 'V')
      return; // don't update the status date when we just visited and it already has some state, this is to present times of when user solved problems

    $previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
    $appController->TsumegoStatus->save($previousTsumegoStatus);
    $sessionUts = $appController->Session->read('loggedInUser.uts');
    if (!$sessionUts)
      $sessionUts = [];
    $sessionUts[$previousTsumegoStatus['TsumegoStatus']['tsumego_id']] = $previousTsumegoStatus['TsumegoStatus']['status'];
    //$appController->Session->write('loggedInUser.uts', $sessionUts);
  }

  static private function processEloChange($appController, $loggedInUserFromDatabase, $previousTsumego, $result): void
  {
    if ($result == '')
      return;

    $userRating = (float) $appController->Session->read('loggedInUser.User.elo_rating_mode');
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
    $appController->Session->write('loggedInUser.User.elo_rating_mode', $newEloRating);
    $loggedInUserFromDatabase['User']['elo_rating_mode'] = $newEloRating;
    $previousTsumego['Tsumego']['elo_rating_mode'] += $newUserElo['tsumego'];
    $previousTsumego['Tsumego']['activity_value']++;
    $previousTsumego['Tsumego']['difficulty'] = $appController->convertEloToXp($previousTsumego['Tsumego']['elo_rating_mode']);
    if ($previousTsumego['Tsumego']['elo_rating_mode'] > 100)
      $appController->Tsumego->save($previousTsumego);
  }

  static private function checkMisplay($appController, &$loggedInUserFromDatabase, &$previousTsumego): bool
  {
    if (empty($_COOKIE['misplay']))
      return false;
    setcookie('misplay', false);
    return true;
  }

  static private function checkCorrectPlay($appController, &$loggedInUserFromDatabase, &$previousTsumego): bool
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

      $preSc = $appController->SetConnection->find('all', ['conditions' => ['tsumego_id' => (int)$_COOKIE['previousTsumegoID']]]);
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

        if (!$appController->Session->check('noLogin'))
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
            $appController->Session->write('loggedInUser.User.reuse4', 1);
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
            $appController->Session->write('loggedInUser.User.level', $loggedInUserFromDatabase['User']['level']);
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
            $appController->TsumegoAttempt->create();
            $ur = [];
            $ur['TsumegoAttempt']['user_id'] = $appController->loggedInUserID();
            $ur['TsumegoAttempt']['elo'] = $appController->Session->read('loggedInUser.User.elo_rating_mode');
            $ur['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
            $ur['TsumegoAttempt']['gain'] = $_COOKIE['score'];
            $ur['TsumegoAttempt']['seconds'] = $cookieSeconds;
            $ur['TsumegoAttempt']['solved'] = '1';
            $ur['TsumegoAttempt']['mode'] = 1;
            $ur['TsumegoAttempt']['tsumego_elo'] = $previousTsumego['Tsumego']['elo_rating_mode'];
            $appController->TsumegoAttempt->save($ur);
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
                'user_id' => $appController->loggedInUserID(),
                'category' => 'err',
              ],
            ]);
            if ($aCondition == null)
              $aCondition = [];
            $aCondition['AchievementCondition']['category'] = 'err';
            $aCondition['AchievementCondition']['user_id'] = $appController->loggedInUserID();
            $aCondition['AchievementCondition']['value']++;
            $appController->AchievementCondition->save($aCondition);
          }
          if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0')
          {
            $ranks = $appController->Rank->find('all', ['conditions' => ['session' => $appController->Session->read('loggedInUser.User.activeRank')]]);
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

      if ($mode == 1)
      {
        unset($_COOKIE['transition']);
        setcookie('transition', false);
      }
      unset($_COOKIE['score']);
      setcookie('score', false);
      unset($_COOKIE['sequence']);
      setcookie('sequence', false);
      unset($_COOKIE['type']);
      setcookie('type', false);
    }

    if ($_COOKIE['score'] != '0')
    {
      $previousTsumegoStatus = $appController->TsumegoStatus->find('first', ['order' => 'created DESC', 'conditions' => ['tsumego_id' => (int)$previousTsumego['Tsumego']['id'], 'user_id' => $loggedInUserFromDatabase['User']['id']]]);
      if ($previousTsumegoStatus == null)
      {
        $previousTsumegoStatus['TsumegoStatus'] = [];
        $previousTsumegoStatus['TsumegoStatus']['user_id'] = $loggedInUserFromDatabase['User']['id'];
        $previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
      }
      $_COOKIE['previousTsumegoBuffer'] = $previousTsumegoStatus['TsumegoStatus']['status'];
      if ($previousTsumegoStatus['TsumegoStatus']['status'] == 'W')
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'C';
      else
        $previousTsumegoStatus['TsumegoStatus']['status'] = 'S';

      $previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
      //$appController->TsumegoStatus->save($previousTsumegoStatus);
      $sessionUts = $appController->Session->read('loggedInUser.uts');
      if (!$sessionUts)
        $sessionUts = [];
      $sessionUts[$previousTsumegoStatus['TsumegoStatus']['tsumego_id']] = $previousTsumegoStatus['TsumegoStatus']['status'];
      //$appController->Session->write('loggedInUser.uts', $sessionUts);
    }
    return true;
  }
}
