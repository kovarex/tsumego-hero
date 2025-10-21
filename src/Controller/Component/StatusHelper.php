<?php
function generateTsumegoStatusMap($appController, $conditions = null)
{
  if (!$conditions)
    $conditions = [];

  $conditions['user_id'] = $appController->loggedInUserID();
  $statuses = $appController->TsumegoStatus->find('all', ['conditions' => $conditions]);
  if (!$statuses)
    return [];

  $result = [];
  foreach ($statuses as $status)
    $result[$status['TsumegoStatus']['tsumego_id']] = $status['TsumegoStatus']['status'];
  return $result;
}
?>
