<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\ClassroomMemberStatusTable;
use App\Model\Table\ClassroomMemberTable;
use App\Model\Table\MessageTable;
use App\Utility\HeaderCounts;
use App\Utility\MistakeTraining;
use App\Utility\Util;

//The one statement of the menu's counts gives what each of the counts gives alone
class HeaderCountsTest extends AppTestCase
{
	public function testTheCountsAreThoseOfEachTable(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player'], 'other-users' => [['name' => 'teacher'], ['name' => 'friend']], 'time-mode-ranks' => ['5k'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))]]]);
		$player = $context->user['id'];
		$teacher = $context->otherUsers[0]['id'];
		$friend = $context->otherUsers[1]['id'];

		foreach ([[$friend, 'first'], [$teacher, 'second'], [$friend, 'third']] as [$sender, $text])
			Util::execute('INSERT INTO message (sender_user_id, recipient_user_id, text) VALUES (?, ?, ?)', [$sender, $player, $text]);
		Util::execute('INSERT INTO classroom (name, teacher_user_id) VALUES (?, ?)', ['Class', $teacher]);
		Util::execute('INSERT INTO classroom_member (classroom_id, user_id, classroom_member_status_id) VALUES (LAST_INSERT_ID(), ?, ?)', [$player, ClassroomMemberStatusTable::INVITED]);
		$rank = $context->timeModeRanks[0]['id'];
		Util::execute('INSERT INTO challenge (created_by_user_id, minimum_time_mode_rank_id, maximum_time_mode_rank_id, seconds_per_problem) VALUES (?, ?, ?, 30)', [$friend, $rank, $rank]);
		Util::execute('INSERT INTO challenge_participant (challenge_id, user_id) VALUES (LAST_INSERT_ID(), ?)', [$player]);

		foreach ([$player, $teacher, $friend] as $userId)
		{
			$counts = HeaderCounts::forUser($userId);
			$this->assertSame(MessageTable::unreadCount($userId), $counts->unreadMessages);
			$this->assertSame(ClassroomMemberTable::countAwaitingAnswer($userId), $counts->classesAwaitingAnswer);
			$this->assertSame(ChallengeTable::countWaitingFor($userId), $counts->challengesWaiting);
			$this->assertSame(MistakeTraining::dueCount($userId), $counts->mistakeTrainingDue);
		}
		$counts = HeaderCounts::forUser($player);
		$this->assertSame([3, 1, 1, 1], [$counts->unreadMessages, $counts->classesAwaitingAnswer, $counts->challengesWaiting, $counts->mistakeTrainingDue]);
	}
}
