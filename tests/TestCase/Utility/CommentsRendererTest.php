<?php

use App\Utility\CommentsRenderer;
use App\Utility\Util;

//The lists of comments of the Discuss page: every comment, and the player's own.
class CommentsRendererTest extends IntegrationTestCase
{
	private function render(?int $userId, int $page = 1): string
	{
		ob_start();
		new CommentsRenderer('comments', $userId, ['comments' => $page])->render();
		return ob_get_clean();
	}

	//The newest first, without the deleted comments and the comments of a problem in the trash;
	//a comment of a problem the player hasn't solved stays hidden.
	public function testTheListShowsTheCommentsNewestFirst(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'other player']],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S', 'comments' => [
					['message' => 'older comment', 'created' => '2026-01-01 10:00:00'],
					['message' => 'newer comment', 'created' => '2026-01-02 10:00:00'],
					['message' => 'deleted comment', 'created' => '2026-01-03 10:00:00']]],
				['set_order' => 2, 'comments' => [['message' => 'comment of an unsolved problem', 'created' => '2026-01-04 10:00:00']]],
				['set_order' => 3, 'status' => 'S', 'comments' => [['message' => 'comment of a problem in the trash', 'created' => '2026-01-05 10:00:00']]],
			]]);
		Util::execute("UPDATE tsumego_comment SET deleted = NOW() WHERE message = 'deleted comment'");
		Util::execute('UPDATE tsumego SET deleted = NOW() WHERE id = ?', [$context->tsumegos[2]['id']]);
		Util::execute("UPDATE tsumego_comment SET user_id = ? WHERE message = 'older comment'", [$context->otherUsers[0]['id']]);
		$this->login('kovarex');

		$all = $this->render(null);
		$this->assertStringNotContainsString('deleted comment', $all);
		$this->assertStringNotContainsString('in the trash', $all);
		$this->assertStringNotContainsString('comment of an unsolved problem', $all);
		$this->assertStringContainsString('[You need to solve this problem to see the comment]', $all);
		$this->assertLessThan(strpos($all, 'older comment'), strpos($all, 'newer comment'));
		$this->assertStringContainsString('<b>#1</b> | <a href="/' . $context->tsumegos[1]['set-connections'][0]['id'] . '?search=topics">', $all);

		$own = $this->render((int) $context->user['id']);
		$this->assertStringContainsString('newer comment', $own);
		$this->assertStringNotContainsString('older comment', $own);
	}

	//An admin's comment on a problem the player hasn't solved is both an admin comment and muted, in one class attribute.
	public function testAHiddenAdminCommentIsMuted(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'an admin', 'admin' => true]],
			'tsumego' => ['set_order' => 1, 'comments' => [['message' => 'admin comment', 'created' => '2026-01-01 10:00:00']]]]);
		Util::execute("UPDATE tsumego_comment SET user_id = ? WHERE message = 'admin comment'", [$context->otherUsers[0]['id']]);
		$this->login('kovarex');

		$all = $this->render(null);
		$this->assertMatchesRegularExpression('/<div class="commentBox2 text-muted" id="comment_\d+">\[You need to solve this problem to see the comment\]<\/div>/', $all);
		$this->assertStringNotContainsString('admin comment', $all);
	}

	//Ten comments a page, the next page starts where the previous ended.
	public function testTheListIsPaged(): void
	{
		$comments = [];
		for ($index = 1; $index <= 12; $index++)
			$comments[] = ['message' => 'comment number ' . $index, 'created' => sprintf('2026-01-01 10:00:%02d', $index)];
		new ContextPreparator(['user' => ['name' => 'kovarex'], 'tsumego' => ['set_order' => 1, 'status' => 'S', 'comments' => $comments]]);
		$this->login('kovarex');

		$first = $this->render(null);
		$this->assertStringContainsString('comment number 12', $first);
		$this->assertStringContainsString('comment number 3<', $first);
		$this->assertStringNotContainsString('comment number 2<', $first);
		$second = $this->render(null, 2);
		$this->assertStringContainsString('<b>#11</b>', $second);
		$this->assertStringContainsString('comment number 2<', $second);
		$this->assertStringContainsString('comment number 1<', $second);
		$this->assertStringNotContainsString('comment number 3<', $second);
	}
}
