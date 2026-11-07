<?php

use App\Controller\AppController;
use App\Model\Table\TsumegoStatusTable;
use App\Utility\Constants;
use App\Utility\Util;

//A play route asked for JSON answers with all the page needs to show the problem without loading again
//(AppController::renderPlay()), /api/v1/problems/{id} is the one of /{id}. A prefetch writes nothing of
//the visit, which the page records when it shows the problem (TsumegosController::visit()).
class ProblemApiTest extends IntegrationTestCase
{
	/** @param array<string, string> $headers */
	private function getJson(string $url, array $headers = []): array
	{
		$this->configRequest(['headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'] + $headers]);
		$this->get($url);
		$this->assertResponseOk();
		$this->assertContentType('application/json');
		return json_decode($this->view, true, flags: JSON_THROW_ON_ERROR);
	}

	private static function link(ContextPreparator $context, int $index): string
	{
		return '/' . $context->tsumegos[$index]['set-connections'][0]['id'];
	}

	private static function statusOf(ContextPreparator $context, int $index): ?string
	{
		return TsumegoStatusTable::statusOf($context->user['id'], $context->tsumegos[$index]['id']);
	}

	public function testTheProblemComesWithAllThePageShowsOfIt()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2, 3]]);
		$data = $this->getJson('/api/v1/problems' . self::link($context, 1));

		// what the page's script reads, as the page prints it
		$this->get(self::link($context, 1));
		$page = $this->playPage();
		$this->assertSame(array_keys($page['page']), array_keys($data['page']));
		$this->assertSame(array_keys($page['problem']), array_keys($data['problem']));
		$this->assertSame((int) $context->tsumegos[1]['id'], $data['problem']['tsumegoID']);
		$this->assertSame($page['problem']['sgf'], $data['problem']['sgf']);
		$this->assertSame(self::link($context, 0), $data['problem']['previousLink']);
		$this->assertSame(self::link($context, 2), $data['problem']['nextLink']);
		// the title of the window and the content of the page, its buttons, without the layout
		$this->assertStringEndsWith(' 2/3 on Tsumego Hero', $data['title']);
		$this->assertStringContainsString('class="problem-nav"', $data['content']);
		$this->assertStringContainsString('id="target"', $data['content']);
		$this->assertStringNotContainsString('<html', $data['content']);
		$this->assertNull($data['visit'], 'The request recorded the visit itself');
		$this->assertSame(TsumegoStatusTable::$VISITED, self::statusOf($context, 1));
	}

	//The page and the problem share their address, a cache keeps them apart
	public function testThePageAndTheProblemVaryByWhatIsAccepted()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1]]);
		$this->getJson(self::link($context, 0));
		$this->assertSame('Accept', $this->headerValue('Vary'));
		$this->assertSame('no-store', $this->headerValue('Cache-Control'));

		$this->configRequest(['headers' => ['Accept' => 'text/html']]);
		$this->get(self::link($context, 0));
		$this->assertResponseOk();
		$this->assertStringContainsString('<html', $this->view);
		$this->assertSame('Accept', $this->headerValue('Vary'));
	}

	public function testAPrefetchWritesNothingOfTheVisitUntilThePageShowsTheProblem()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1, 2]]);
		$setConnectionID = $context->tsumegos[1]['set-connections'][0]['id'];
		$data = $this->getJson('/api/v1/problems/' . $setConnectionID, [AppController::PREFETCH_HEADER => '1']);

		$this->assertSame(['setConnectionID' => $setConnectionID, 'markAsVisited' => true], $data['visit']);
		$this->assertNull(self::statusOf($context, 1));
		$this->assertArrayNotHasKey('lastVisit', $this->browserCookies());

		$this->post('/api/v1/problems/' . $setConnectionID . '/visit', ['markAsVisited' => 1]);
		$this->assertResponseCode(204);
		$this->assertSame(TsumegoStatusTable::$VISITED, self::statusOf($context, 1));
	}

	//A visit leaves a status the player has alone, and marks nothing for a route that doesn't mark
	public function testAVisitKeepsTheStatusThePlayerHas()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [
			['set_order' => 1, 'status' => 'S'],
			['set_order' => 2]]]);

		$this->post('/api/v1/problems' . self::link($context, 0) . '/visit', ['markAsVisited' => 1]);
		$this->assertResponseCode(204);
		$this->post('/api/v1/problems' . self::link($context, 1) . '/visit', ['markAsVisited' => 0]);
		$this->assertResponseCode(204);

		$this->assertSame('S', self::statusOf($context, 0));
		$this->assertNull(self::statusOf($context, 1));
	}

	//A collection the player may not play (a sandbox one) is refused, its problem as its visit
	public function testAProblemOfACollectionThePlayerMayNotPlayIsRefused()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'other-users' => [['name' => 'stranger', 'mode' => Constants::$LEVEL_MODE]],
			'tsumegos' => [['sets' => [['name' => 'sandbox', 'public' => 0, 'num' => '1']]]]]);
		$setConnectionID = $context->tsumegos[0]['set-connections'][0]['id'];
		$this->login('stranger');

		$this->configRequest(['headers' => ['Accept' => 'application/json']]);
		$this->get('/api/v1/problems/' . $setConnectionID);
		$this->assertResponseCode(403);
		$this->post('/api/v1/problems/' . $setConnectionID . '/visit', ['markAsVisited' => 1]);
		$this->assertResponseCode(403);
		$this->assertNull(TsumegoStatusTable::statusOf($context->otherUsers[0]['id'], $context->tsumegos[0]['id']));
	}

	public function testAProblemInTheTrashIsNotFound()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => [1]]);
		Util::execute('UPDATE tsumego SET deleted = NOW() WHERE id = ?', [$context->tsumegos[0]['id']]);

		$this->configRequest(['headers' => ['Accept' => 'application/json']]);
		$this->get('/api/v1/problems' . self::link($context, 0));
		$this->assertResponseCode(404);
		$this->post('/api/v1/problems' . self::link($context, 0) . '/visit', ['markAsVisited' => 1]);
		$this->assertResponseCode(404);
	}

	public function testAMissingProblemIsNotFound()
	{
		new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		$this->configRequest(['headers' => ['Accept' => 'application/json']]);
		$this->get('/api/v1/problems/999999');
		$this->assertResponseCode(404);
	}

	//As /{id}, the problem is played in the level mode
	public function testTheProblemIsPlayedInTheLevelMode()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE], 'tsumegos' => [1]]);
		$data = $this->getJson('/api/v1/problems' . self::link($context, 0));

		$this->assertSame(Constants::$LEVEL_MODE, $data['page']['mode']);
		$this->assertSame(Constants::$LEVEL_MODE, (int) $context->reloadUser()['mode_id']);
	}

	public function testAGuestGetsTheProblem()
	{
		$context = new ContextPreparator(['user' => null, 'tsumegos' => [1, 2]]);
		$data = $this->getJson('/api/v1/problems' . self::link($context, 0));

		$this->assertSame(0, $data['page']['userID']);
		$this->assertSame(self::link($context, 1), $data['problem']['nextLink']);
	}

	//The route of a mode answers in JSON as well, with the links of its own row
	public function testTheRatingModeAnswersWithItsProblem()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE], 'tsumegos' => [['set_order' => 1, 'rating' => 1500]]]);
		Util::execute('UPDATE user SET rating = 1500 WHERE id = ?', [$context->user['id']]);
		$data = $this->getJson('/ratingMode');

		$this->assertSame((int) $context->tsumegos[0]['id'], $data['problem']['tsumegoID']);
		$this->assertSame('/ratingMode', $data['problem']['nextLink']);
		$this->assertSame(Constants::$RATING_MODE, $data['page']['mode']);
	}
}
