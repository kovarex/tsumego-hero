<?php

use App\Model\Table\TsumegoIssueTable;
use App\Utility\Util;

class TsumegoIssueTest extends AppTestCase
{
	//The tabs count the standalone comments that aren't deleted, every issue, and the open issues
	//apart; a comment in an issue counts with its issue, and another problem's comments don't count.
	public function testCommentSectionCounts(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumegos' => [
				[
					'set_order' => 1,
					'comments' => [['message' => 'kept'], ['message' => 'also kept'], ['message' => 'deleted']],
					'issues' => [['message' => 'open one'], ['message' => 'closed one', 'status' => TsumegoIssueTable::CLOSED_STATUS]],
				],
				['set_order' => 2, 'comments' => [['message' => 'of another problem']], 'issues' => [['message' => 'of another problem']]],
			]]);
		Util::execute("UPDATE tsumego_comment SET deleted = NOW() WHERE message = 'deleted'");

		$this->assertSame(
			['total' => 4, 'comments' => 2, 'issues' => 2, 'openIssues' => 1],
			TsumegoIssueTable::getCommentSectionCounts((int) $context->tsumegos[0]['id']));
	}

	public function testCommentSectionCountsOfAProblemWithoutComments(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1]]);

		$this->assertSame(
			['total' => 0, 'comments' => 0, 'issues' => 0, 'openIssues' => 0],
			TsumegoIssueTable::getCommentSectionCounts((int) $context->tsumegos[0]['id']));
	}
}
