<?php

class TsumegoAttempt extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'tsumego_attempt';
		parent::__construct($id, $table, $ds);
	}

	//The latest attempt of the player on the problem. An unsolved one collects the fails until
	//the problem is solved, a solve starts a new one.
	public static function findLast(int $userID, int $tsumegoID): ?array
	{
		$attempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $userID, 'tsumego_id' => $tsumegoID],
			'order' => 'id DESC']);
		return $attempt ?: null;
	}

	//The fails of the player on the problem since it was last solved.
	public static function failsInARow(?array $lastAttempt): int
	{
		if (!$lastAttempt || $lastAttempt['TsumegoAttempt']['solved'])
			return 0;
		return (int) $lastAttempt['TsumegoAttempt']['misplays'];
	}
}
