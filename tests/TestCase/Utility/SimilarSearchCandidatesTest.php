<?php

use App\Utility\SimilarSearchLogic;

//Which problems the similar search compares and how it shows the ones it finds.
class SimilarSearchCandidatesTest extends AppTestCase
{
	private const string SOURCE = '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][ff];B[aa]C[+])';

	private function search(ContextPreparator $context): SimilarSearchLogic
	{
		$setConnection = $this->fetchTable('SetConnection')->get($context->setConnections[0]['id']);
		$search = new SimilarSearchLogic($setConnection);
		$search->execute();
		return $search;
	}

	//The tsumego ids of what the search found, with the difference to the source.
	private function found(SimilarSearchLogic $search): array
	{
		$found = [];
		foreach ($search->result->items as $item)
			$found[$item->tsumegoButton->tsumegoID] = $item->difference;
		return $found;
	}

	public function testAProblemFourStonesApartIsFoundSixAreNot(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['set_order' => 1, 'sgf' => self::SOURCE],
			['set_order' => 2, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][ff][ee][eg][ge][gg];B[aa]C[+])'],
			['set_order' => 3, 'sgf' => '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][ff][ee][eg][ge][gg][ec][ce];B[aa]C[+])'],
			['set_order' => 4, 'sgf' => self::SOURCE]]]);

		$found = $this->found($this->search($context));

		$this->assertSame([$context->tsumegos[3]['id'] => 0, $context->tsumegos[1]['id'] => 4], $found);
	}

	public function testOnlyTheProblemsOfTheSitesCollectionsAreCompared(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['sets' => [['name' => 'site set', 'num' => 1]], 'sgf' => self::SOURCE],
			['sets' => [['name' => 'own set', 'num' => 1, 'public' => 0, 'user_id' => 'self']], 'sgf' => self::SOURCE],
			['sets' => [['name' => 'sandbox', 'num' => 1, 'public' => 0]], 'sgf' => self::SOURCE],
			['sets' => [['name' => 'site set', 'num' => 2]], 'sgf' => self::SOURCE, 'deleted' => '2026-01-01 00:00:00'],
			['sets' => [['name' => 'own set', 'num' => 2, 'public' => 0, 'user_id' => 'self'], ['name' => 'other site set', 'num' => 7]], 'sgf' => self::SOURCE]]]);

		$search = $this->search($context);

		$this->assertSame([$context->tsumegos[4]['id'] => 0], $this->found($search));
		$item = $search->result->items[0];
		$this->assertSame('other site set', $item->title, 'a match is shown in a collection of the site');
		$this->assertSame(7, $item->tsumegoButton->order);
	}

	public function testAMatchShowsTheStatusOfThePlayer(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['set_order' => 1, 'sgf' => self::SOURCE],
			['set_order' => 2, 'sgf' => self::SOURCE, 'status' => 'S'],
			['set_order' => 3, 'sgf' => self::SOURCE]]]);

		$statuses = array_map(fn($item) => $item->tsumegoButton->status, $this->search($context)->result->items);

		$this->assertSame(['S', 'N'], $statuses);
	}
}
