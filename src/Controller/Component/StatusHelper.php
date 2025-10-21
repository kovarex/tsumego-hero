<?php
function generateTsumegoStatusMap($appController, $conditions = null)
{
  if (!$conditions)
    $conditions = [];

  $conditions['user_id'] = $this->loggedInUserID();
  $statuses = $appController->TsumegoStatus->find('all', ['conditions' => $conditions]);
  if (!$statuses)
    return [];

  $result = [];
  for each ($statuses as $status)
    $result[$status['TsumegoStatus']['tsumego_id']] = $status['TsumegoStatus']['status'];
  return $result;
}
?>
