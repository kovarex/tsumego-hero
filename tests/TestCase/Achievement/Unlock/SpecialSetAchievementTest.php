<?php

use App\Utility\AchievementId;
use App\Utility\AchievementChecker;

class SpecialSetAchievementTest extends AchievementTestCase
{
	/* Test AchievementId::LIFE_DEATH_ELEMENTARY
	 * Requires completing all tsumegos in sets: 50, 52, 53, 54 */
	public function testLifeAndDeathElementary()
	{
		$context = new ContextPreparator();

		$this->createAndSolveSets($context->user['id'], [50, 52, 53, 54]);
		new AchievementChecker()->setAchievementSpecial('cc1');
		$this->assertAchievementUnlocked(AchievementId::LIFE_DEATH_ELEMENTARY, 'Life & Death Elementary should unlock when all sets 50,52,53,54 are completed');
	}

	//Test AchievementId::LIFE_DEATH_INTERMEDIATE
	//Requires completing all tsumegos in sets: 41, 49, 65, 66
	public function testLifeAndDeathIntermediate()
	{
		$context = new ContextPreparator();
		$this->createAndSolveSets($context->user['id'], [41, 49, 65, 66]);
		new AchievementChecker()->setAchievementSpecial('cc2');
		$this->assertAchievementUnlocked(AchievementId::LIFE_DEATH_INTERMEDIATE, 'Life & Death Intermediate should unlock');
	}

	/* Test AchievementId::LIFE_DEATH_ADVANCED
	 * Requires completing all tsumegos in sets: 186, 187, 196, 203 */
	public function testLifeAndDeathAdvanced()
	{
		$context = new ContextPreparator();
		$this->createAndSolveSets($context->user['id'], [186, 187, 196, 203]);
		new AchievementChecker()->setAchievementSpecial('cc3');
		$this->assertAchievementUnlocked(AchievementId::LIFE_DEATH_ADVANCED, 'Life & Death Advanced should unlock');
	}

	/* Test AchievementId::WEIQI_1000_FIRST_HALF
	 * Requires completing all tsumegos in sets: 190, 193, 198 */
	public function test1000WeiqiFirstHalf()
	{
		$context = new ContextPreparator();
		$this->createAndSolveSets($context->user['id'], [190, 193, 198]);
		new AchievementChecker()->setAchievementSpecial('1000w1');
		$this->assertAchievementUnlocked(AchievementId::WEIQI_1000_FIRST_HALF, '1000 Weiqi 1st half should unlock');
	}

	/* Test AchievementId::WEIQI_1000_SECOND_HALF
	 * Requires completing all tsumegos in set: 216 */
	public function test1000WeiqiSecondHalf()
	{
		$context = new ContextPreparator([]);
		$this->createAndSolveSets($context->user['id'], [216]);
		new AchievementChecker()->setAchievementSpecial('1000w2');
		$this->assertAchievementUnlocked(AchievementId::WEIQI_1000_SECOND_HALF, '1000 Weiqi 2nd half should unlock');
	}

	//One problem of the sets still unsolved keeps the achievement locked
	public function test1000WeiqiFirstHalfNeedsEveryProblemSolved()
	{
		$context = new ContextPreparator();
		$tsumegoIDs = $this->createAndSolveSets($context->user['id'], [190, 193, 198]);
		$this->fetchTable('TsumegoStatus')->updateAll(['status' => 'F'], ['user_id' => $context->user['id'], 'tsumego_id' => $tsumegoIDs[7]]);
		new AchievementChecker()->setAchievementSpecial('1000w1');
		$this->assertAchievementNotUnlocked(AchievementId::WEIQI_1000_FIRST_HALF);
	}

	//A problem in the trash is not in the set anymore, so it doesn't have to be solved
	public function testAProblemInTheTrashIsNotNeeded()
	{
		$context = new ContextPreparator();
		$tsumegoIDs = $this->createAndSolveSets($context->user['id'], [216]);
		$this->fetchTable('TsumegoStatus')->deleteAll(['user_id' => $context->user['id'], 'tsumego_id' => $tsumegoIDs[1]]);
		ClassRegistry::init('Tsumego')->updateAll(['deleted' => "'" . date('Y-m-d H:i:s') . "'"], ['id' => $tsumegoIDs[1]]);
		new AchievementChecker()->setAchievementSpecial('1000w2');
		$this->assertAchievementUnlocked(AchievementId::WEIQI_1000_SECOND_HALF, 'The problem in the trash should not be needed');
	}

	// Helper: Create sets with tsumegos in them and mark them all as solved, returns the tsumego ids
	private function createAndSolveSets($userId, $setIds): array
	{
		$Set = ClassRegistry::init('Set');
		$Tsumego = ClassRegistry::init('Tsumego');
		$TsumegoStatus = $this->fetchTable('TsumegoStatus');
		$SetConnection = ClassRegistry::init('SetConnection');

		$tsumegoIDs = [];
		foreach ($setIds as $setId)
		{
			$Set->create();
			$Set->save(['id' => $setId, 'title' => "Test Set $setId"]);

			for ($i = 0; $i < 3; $i++)
			{
				$Tsumego->create();
				$Tsumego->save(['rating' => 1000, 'author' => '']);
				$tsumegoId = $Tsumego->id;
				$tsumegoIDs[] = (int) $tsumegoId;

				$SetConnection->create();
				$SetConnection->save(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => $i + 1]);

				$TsumegoStatus->saveOrFail($TsumegoStatus->newEntity(['user_id' => $userId, 'tsumego_id' => $tsumegoId, 'status' => 'S']));
			}
		}
		return $tsumegoIDs;
	}
}
