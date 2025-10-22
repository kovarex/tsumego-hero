<?php
class TsumegoStatusHelper
{
  static function getMapForUser(int $loggedInUserID, $conditions = null): array
  {
    if (!$conditions)
      $conditions = [];

    $conditions['user_id'] = $loggedInUserID;
    $statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => $conditions]);
    if (!$statuses)
      return [];

    $result = [];
    foreach ($statuses as $status)
      $result[$status['TsumegoStatus']['tsumego_id']] = $status['TsumegoStatus']['status'];
    return $result;
  }
}
