<?php

use App\Utility\AchievementId;
use App\Utility\AchievementChecker;

//The completion achievements: solving every problem of the collections that share a
//set.completion_achievement_id unlocks it
class SpecialSetAchievementTest extends AchievementTestCase
{
	public function testEveryCompletionAchievementUnlocksWhenItsSetsAreSolved()
	{
		foreach ([AchievementId::LIFE_DEATH_ELEMENTARY, AchievementId::LIFE_DEATH_INTERMEDIATE, AchievementId::LIFE_DEATH_ADVANCED,
			AchievementId::WEIQI_1000_FIRST_HALF, AchievementId::WEIQI_1000_SECOND_HALF] as $achievementID)
		{
			$context = new ContextPreparator();
			[$setIDs] = $this->createAndSolveSets($context->user['id'], 3, $achievementID);
			$this->check($setIDs[0]);
			$this->assertAchievementUnlocked($achievementID, $achievementID->name . ' should unlock when all its sets are solved');
		}
	}

	//One problem of the sets still unsolved keeps the achievement locked
	public function testEveryProblemOfEverySetIsNeeded()
	{
		$context = new ContextPreparator();
		[$setIDs, $tsumegoIDs] = $this->createAndSolveSets($context->user['id'], 3, AchievementId::WEIQI_1000_FIRST_HALF);
		$this->fetchTable('TsumegoStatus')->updateAll(['status' => 'F'], ['user_id' => $context->user['id'], 'tsumego_id' => $tsumegoIDs[7]]);
		$this->check($setIDs[0]);
		$this->assertAchievementNotUnlocked(AchievementId::WEIQI_1000_FIRST_HALF);
	}

	//A problem in the trash is not in the set anymore, so it doesn't have to be solved
	public function testAProblemInTheTrashIsNotNeeded()
	{
		$context = new ContextPreparator();
		[$setIDs, $tsumegoIDs] = $this->createAndSolveSets($context->user['id'], 1, AchievementId::WEIQI_1000_SECOND_HALF);
		$this->fetchTable('TsumegoStatus')->deleteAll(['user_id' => $context->user['id'], 'tsumego_id' => $tsumegoIDs[1]]);
		$this->fetchTable('Tsumego')->updateAll(['deleted' => date('Y-m-d H:i:s')], ['id' => $tsumegoIDs[1]]);
		$this->check($setIDs[0]);
		$this->assertAchievementUnlocked(AchievementId::WEIQI_1000_SECOND_HALF, 'The problem in the trash should not be needed');
	}

	//A collection in the trash is not part of the group anymore
	public function testASetInTheTrashIsNotNeeded()
	{
		$context = new ContextPreparator();
		[$setIDs, $tsumegoIDs] = $this->createAndSolveSets($context->user['id'], 2, AchievementId::LIFE_DEATH_ELEMENTARY);
		$this->fetchTable('TsumegoStatus')->deleteAll(['user_id' => $context->user['id'], 'tsumego_id' => $tsumegoIDs[4]]);
		$this->fetchTable('Set')->updateAll(['deleted' => date('Y-m-d H:i:s')], ['id' => $setIDs[1]]);
		$this->check($setIDs[0]);
		$this->assertAchievementUnlocked(AchievementId::LIFE_DEATH_ELEMENTARY, 'The set in the trash should not be needed');
	}

	//The sets of another completion achievement, and sets without one, don't count
	public function testOnlyTheSetsOfTheSameAchievementCount()
	{
		$context = new ContextPreparator();
		[$setIDs] = $this->createAndSolveSets($context->user['id'], 2, AchievementId::LIFE_DEATH_ELEMENTARY);
		[, $otherTsumegoIDs] = $this->createAndSolveSets($context->user['id'], 1, AchievementId::LIFE_DEATH_INTERMEDIATE);
		[$plainSetIDs, $plainTsumegoIDs] = $this->createAndSolveSets($context->user['id'], 1, null);
		$this->fetchTable('TsumegoStatus')->deleteAll(['user_id' => $context->user['id'], 'tsumego_id IN' => [$otherTsumegoIDs[0], $plainTsumegoIDs[0]]]);

		$this->check($setIDs[0]);
		$this->assertAchievementUnlocked(AchievementId::LIFE_DEATH_ELEMENTARY, 'Only the sets of the achievement count');

		$this->check($plainSetIDs[0]);
		$this->assertAchievementNotUnlocked(AchievementId::LIFE_DEATH_INTERMEDIATE);
	}

	private function check(int $setID): void
	{
		new AchievementChecker()->checkCompletionAchievement($this->fetchTable('Set')->get($setID));
	}

	//Creates sets of the completion achievement with 3 problems each, all solved by the user;
	//returns the set ids and the tsumego ids
	private function createAndSolveSets(int $userID, int $setCount, ?AchievementId $achievementID): array
	{
		$setTable = $this->fetchTable('Set');
		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumegoStatusTable = $this->fetchTable('TsumegoStatus');
		$setConnectionTable = $this->fetchTable('SetConnection');

		$setIDs = [];
		$tsumegoIDs = [];
		for ($setIndex = 0; $setIndex < $setCount; $setIndex++)
		{
			$setID = $setTable->saveOrFail($setTable->newEntity([
				'title' => 'Test Set ' . ($achievementID?->name ?? 'plain') . ' ' . $setIndex,
				'public' => 1,
				'completion_achievement_id' => $achievementID?->value]))->id;
			$setIDs[] = (int) $setID;
			for ($i = 0; $i < 3; $i++)
			{
				$tsumegoID = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['rating' => 1000, 'author' => '']))->id;
				$tsumegoIDs[] = (int) $tsumegoID;
				$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['set_id' => $setID, 'tsumego_id' => $tsumegoID, 'num' => $i + 1]));
				$tsumegoStatusTable->saveOrFail($tsumegoStatusTable->newEntity(['user_id' => $userID, 'tsumego_id' => $tsumegoID, 'status' => 'S']));
			}
		}
		return [$setIDs, $tsumegoIDs];
	}
}
